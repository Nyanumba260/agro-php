<?php
include 'includes/config.php';

// Get all products, sorted by price
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'price_asc';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Validate sort parameter to prevent SQL injection
$allowed_sorts = ['price_asc', 'price_desc', 'newest', 'name'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'price_asc';
}

$query = "SELECT p.*, u.name as farmer_name FROM products p JOIN users u ON p.farmer_id = u.id WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR u.name LIKE ? OR p.category LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = [$search_param, $search_param, $search_param];
    $types = 'sss';
}

// Apply sorting
switch ($sort) {
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'price_asc':
    default:
        $query .= " ORDER BY p.price ASC";
        break;
    case 'newest':
        $query .= " ORDER BY p.created_at DESC";
        break;
    case 'name':
        $query .= " ORDER BY p.name ASC";
        break;
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

// Get cart count
$cart_count = 0;
if (isLoggedIn()) {
    $cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_query);
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $cart_result = $stmt->get_result();
    $cart_row = $cart_result->fetch_assoc();
    $cart_count = $cart_row['total'] ?? 0;
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGROBIASHARA - Fresh Farm Produce</title>
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

            <form method="GET" style="flex: 1; margin: 0 2rem; display: flex; gap: 0.5rem;">
                <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 4px;">
                <button type="submit" class="btn btn-primary" style="margin: 0;">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>

            <div class="header-nav">
                <?php if (isLoggedIn()): ?>
                    <span style="color: var(--text-dark); font-weight: 500;">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </span>
                    <a href="cart.php" style="position: relative;">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cart_count > 0): ?>
                            <span style="position: absolute; top: -8px; right: -8px; background: var(--accent-orange); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;">
                                <?php echo $cart_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <form method="POST" action="logout.php" style="display: inline;">
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary" style="margin: 0;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="register.php" class="btn btn-secondary" style="margin: 0;">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Banner -->
    <section class="hero-banner">
        <h1>Fresh Farm Produce</h1>
        <p>Direct from Local Farmers to Your Table</p>
        <?php if (!isLoggedIn()): ?>
            <a href="register.php" class="btn btn-secondary" style="font-size: 1.1rem;">
                <i class="fas fa-shopping-cart"></i> Shop Now
            </a>
        <?php endif; ?>
    </section>

    <!-- Sorting Options -->
    <div style="padding: 1rem 2rem; max-width: 1400px; margin: 0 auto; display: flex; gap: 1rem; align-items: center;">
        <label for="sort" style="font-weight: 600;">Sort by:</label>
        <select id="sort" onchange="window.location.href='?sort=' + this.value + '&search=<?php echo urlencode($search); ?>'" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
            <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
        </select>
    </div>

    <!-- Products -->
    <div class="products-grid">
        <?php if (empty($products)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                <i class="fas fa-search" style="font-size: 3rem; color: #ccc; display: block; margin-bottom: 1rem;"></i>
                <p style="font-size: 1.2rem; color: var(--text-light);"><strong>No products found</strong></p>
                <p style="color: #999; margin-top: 0.5rem;">Try searching with different keywords</p>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <div class="product-card" onclick="window.location.href='product-details.php?id=<?php echo $product['id']; ?>';" style="cursor: pointer;">
                    <div class="product-image">
                        <?php if ($product['image_url'] && file_exists($product['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="product-farmer">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($product['farmer_name']); ?>
                        </div>
                        <div class="product-price">KES <?php echo number_format($product['price'], 2); ?></div>
                        <?php if ($product['quantity'] <= 5 && $product['quantity'] > 0): ?>
                            <div style="background: #fff3cd; color: #856404; padding: 0.5rem; border-radius: 4px; font-size: 0.85rem; margin-bottom: 0.5rem; text-align: center;">
                                <i class="fas fa-exclamation-triangle"></i> Only <?php echo $product['quantity']; ?> left
                            </div>
                        <?php elseif ($product['quantity'] <= 0): ?>
                            <div style="background: #f8d7da; color: #721c24; padding: 0.5rem; border-radius: 4px; font-size: 0.85rem; margin-bottom: 0.5rem; text-align: center;">
                                <i class="fas fa-times-circle"></i> Out of Stock
                            </div>
                        <?php endif; ?>
                        <div class="product-actions">
                            <?php if ($product['quantity'] <= 0): ?>
                                <button type="button" class="btn-add-cart" style="width: 100%; padding: 0.5rem; opacity: 0.5; cursor: not-allowed;" disabled onclick="event.stopPropagation();">
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </button>
                            <?php elseif (isLoggedIn() && getUserRole() === ROLE_BUYER): ?>
                                <form method="POST" action="add-to-cart.php" style="flex: 1;" onclick="event.stopPropagation();">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="btn-add-cart" style="width: 100%; padding: 0.5rem;">
                                        <i class="fas fa-shopping-cart"></i> Add
                                    </button>
                                </form>
                            <?php elseif (!isLoggedIn()): ?>
                                <a href="login.php" class="btn-add-cart" style="flex: 1; text-align: center; padding: 0.5rem; display: block; text-decoration: none;" onclick="event.stopPropagation();">
                                    <i class="fas fa-shopping-cart"></i> Add
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 AGROBIASHARA. Fresh Farm Produce Marketplace.</p>
        <p>Email: Agrobiashara@gmail.com | Phone: +254 722 736 023</p>
    </footer>
</body>
</html>
