<?php
include 'includes/config.php';
requireRole(ROLE_FARMER);

$farmer_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Create images/products directory if it doesn't exist
$upload_dir = 'images/products';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle add/edit product with image upload or account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize($_POST['action']);

    if ($action === 'delete_account') {
        // Delete product images first so there are no orphan files
        $image_query = "SELECT image_url FROM products WHERE farmer_id = ?";
        $image_stmt = $conn->prepare($image_query);
        $image_stmt->bind_param('i', $farmer_id);
        $image_stmt->execute();
        $image_result = $image_stmt->get_result();
        while ($image_row = $image_result->fetch_assoc()) {
            if ($image_row['image_url'] && file_exists($image_row['image_url'])) {
                unlink($image_row['image_url']);
            }
        }
        $image_stmt->close();

        // Delete the user account and rely on cascade deletes for related data
        $delete_query = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $farmer_id);
        if ($stmt->execute()) {
            $stmt->close();
            session_destroy();
            header('Location: login.php');
            exit;
        } else {
            $error = 'Error deleting account';
            $stmt->close();
        }
    } else {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);
        $image_url = null;

        if (empty($name) || $price <= 0) {
            $error = 'Product name and valid price are required';
        } else {
            // Handle image upload
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['product_image']['tmp_name'];
                $file_name = $_FILES['product_image']['name'];
                $file_size = $_FILES['product_image']['size'];
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file_tmp);
                finfo_close($finfo);
                
                if (!in_array($mime_type, $allowed_types)) {
                    $error = 'Only image files (JPG, PNG, GIF, WebP) are allowed';
                } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                    $error = 'Image size must be less than 5MB';
                } else {
                    // Generate unique filename
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $unique_filename = 'product_' . $farmer_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . '/' . $unique_filename;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $image_url = $file_path;
                    } else {
                        $error = 'Error uploading image';
                    }
                }
            }

            // Only proceed if no upload error or no image was uploaded
            if (empty($error)) {
                if ($action === 'add') {
                    $insert_query = "INSERT INTO products (farmer_id, name, description, category, price, quantity, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param('isssids', $farmer_id, $name, $description, $category, $price, $quantity, $image_url);
                    if ($stmt->execute()) {
                        $success = 'Product added successfully!';
                    } else {
                        $error = 'Error adding product';
                    }
                    $stmt->close();
                } elseif ($action === 'edit') {
                    $product_id = intval($_POST['product_id']);
                    if ($image_url) {
                        // Delete old image if exists
                        $old_query = "SELECT image_url FROM products WHERE id = ? AND farmer_id = ?";
                        $old_stmt = $conn->prepare($old_query);
                        $old_stmt->bind_param('ii', $product_id, $farmer_id);
                        $old_stmt->execute();
                        $old_result = $old_stmt->get_result();
                        if ($old_result->num_rows > 0) {
                            $old_row = $old_result->fetch_assoc();
                            if ($old_row['image_url'] && file_exists($old_row['image_url'])) {
                                unlink($old_row['image_url']);
                            }
                        }
                        $old_stmt->close();
                        
                        $update_query = "UPDATE products SET name = ?, description = ?, category = ?, price = ?, quantity = ?, image_url = ? WHERE id = ? AND farmer_id = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param('sssddisi', $name, $description, $category, $price, $quantity, $image_url, $product_id, $farmer_id);
                    } else {
                        $update_query = "UPDATE products SET name = ?, description = ?, category = ?, price = ?, quantity = ? WHERE id = ? AND farmer_id = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param('sssdii', $name, $description, $category, $price, $quantity, $product_id, $farmer_id);
                    }
                    if ($stmt->execute()) {
                        $success = 'Product updated successfully!';
                    } else {
                        $error = 'Error updating product';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Handle delete product
if (isset($_GET['delete'])) {
    $product_id = intval($_GET['delete']);
    $delete_query = "DELETE FROM products WHERE id = ? AND farmer_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('ii', $product_id, $farmer_id);
    if ($stmt->execute()) {
        $success = 'Product deleted successfully!';
    } else {
        $error = 'Error deleting product';
    }
    $stmt->close();
}

// Get farmer's products
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'price_asc';
$products_query = "SELECT * FROM products WHERE farmer_id = ?";

switch ($sort) {
    case 'price_desc':
        $products_query .= " ORDER BY price DESC";
        break;
    case 'price_asc':
    default:
        $products_query .= " ORDER BY price ASC";
        break;
    case 'newest':
        $products_query .= " ORDER BY created_at DESC";
        break;
    case 'name':
        $products_query .= " ORDER BY name ASC";
        break;
}

$stmt = $conn->prepare($products_query);
$stmt->bind_param('i', $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

// Get farmer stats
$stats_query = "SELECT COUNT(*) as total_products, SUM(quantity) as total_quantity FROM products WHERE farmer_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param('i', $farmer_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard - AGROBIASHARA</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <div class="logo-placeholder">
                    <i class="fas fa-leaf"></i>
                </div>
                <span>AGROBIASHARA</span>
            </a>

            <div style="flex: 1;"></div>

            <div class="header-nav">
                <span style="color: var(--text-dark); font-weight: 500;">
                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?> (Farmer)
                </span>
                <form method="POST" action="farmer-dashboard.php" style="display: inline;">
                    <input type="hidden" name="action" value="delete_account">
                    <button type="submit" class="btn-delete" style="margin-right: 0.75rem;" onclick="return confirm('Delete your account permanently? This cannot be undone.')">
                        <i class="fas fa-user-slash"></i> Delete Account
                    </button>
                </form>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Dashboard -->
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-tractor"></i> Farmer Dashboard</h1>
                <p style="color: var(--text-light); margin-top: 0.5rem;">Manage your farm produce</p>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('addProductForm').style.display = 'block'">
                <i class="fas fa-plus"></i> Add Product
            </button>
        </div>

        <!-- Stats -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <h3><i class="fas fa-box"></i> Total Products</h3>
                <div class="number"><?php echo $stats['total_products']; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-cubes"></i> Total Quantity</h3>
                <div class="number"><?php echo $stats['total_quantity'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Product Form -->
        <div id="addProductForm" style="display: none; background: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: var(--shadow);">
            <h2 style="margin-bottom: 1.5rem; color: var(--primary-green);">
                <i class="fas fa-plus"></i> Add New Product
            </h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" id="product_id" value="">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" required placeholder="e.g., Fresh Tomatoes">
                    </div>

                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="vegetables">Vegetables</option>
                            <option value="fruits">Fruits</option>
                            <option value="grains">Grains</option>
                            <option value="dairy">Dairy</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (KES) *</label>
                        <input type="number" id="price" name="price" required step="0.01" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="quantity">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" required placeholder="0">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Product description..."></textarea>
                </div>

                <div class="form-group">
                    <label for="product_image"><i class="fas fa-image"></i> Product Image</label>
                    <input type="file" id="product_image" name="product_image" accept="image/*" placeholder="Upload product image">
                    <small style="color: var(--text-light); display: block; margin-top: 0.5rem;">
                        <i class="fas fa-info-circle"></i> Accepted formats: JPG, PNG, GIF, WebP (Max 5MB)
                    </small>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                    <button type="button" class="btn" style="background: var(--text-light); color: white;" onclick="document.getElementById('addProductForm').style.display = 'none'">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Sort Products -->
        <div style="padding: 1rem 0; display: flex; gap: 1rem; align-items: center;">
            <label for="sort" style="font-weight: 600;">Sort by:</label>
            <select id="sort" onchange="window.location.href='?sort=' + this.value" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
            </select>
        </div>

        <!-- Products Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price (KES)</th>
                        <th>Quantity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                <i class="fas fa-inbox" style="font-size: 2rem; color: #ccc; display: block; margin-bottom: 1rem;"></i>
                                No products yet. <a href="#" onclick="document.getElementById('addProductForm').style.display = 'block'; return false;" style="color: var(--primary-green); font-weight: 600;">Add your first product</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if ($product['image_url'] && file_exists($product['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="color: #ccc;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                <td><?php echo ucfirst($product['category']); ?></td>
                                <td>KES <?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo $product['quantity']; ?> units</td>
                                <td>
                                    <button class="btn-edit" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" style="padding: 0.4rem 0.8rem; font-size: 0.9rem;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?delete=<?php echo $product['id']; ?>" class="btn-delete" style="padding: 0.4rem 0.8rem; font-size: 0.9rem; text-decoration: none; display: inline-block;" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2026 AGROBIASHARA. Farmer Dashboard.</p>
    </footer>

    <script>
        function editProduct(product) {
            document.querySelector('input[name="action"]').value = 'edit';
            document.getElementById('product_id').value = product.id;
            document.getElementById('name').value = product.name;
            document.getElementById('category').value = product.category;
            document.getElementById('price').value = product.price;
            document.getElementById('quantity').value = product.quantity;
            document.getElementById('description').value = product.description;
            document.getElementById('product_image').value = ''; // Clear file input
            document.getElementById('addProductForm').style.display = 'block';
            document.querySelector('#addProductForm h2').innerHTML = '<i class="fas fa-edit"></i> Edit Product';
            window.scrollTo(0, 0);
        }
    </script>
</body>
</html>
