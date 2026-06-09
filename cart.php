<?php
include 'includes/config.php';
requireRole(ROLE_BUYER);

$user_id = $_SESSION['user_id'];

// Handle remove from cart
if (isset($_GET['remove'])) {
    $cart_id = intval($_GET['remove']);
    $delete_query = "DELETE FROM cart WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('ii', $cart_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header('Location: cart.php');
    exit;
}

// Get cart items
$cart_query = "SELECT c.id, p.id as product_id, p.name, p.price, u.name as farmer_name, c.quantity FROM cart c 
               JOIN products p ON c.product_id = p.id 
               JOIN users u ON p.farmer_id = u.id 
               WHERE c.user_id = ?
               ORDER BY c.added_at DESC";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = [];
$subtotal = 0;

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $subtotal += $row['price'] * $row['quantity'];
}
$stmt->close();

// Get delivery charges
$delivery_query = "SELECT location, charge FROM delivery_charges ORDER BY location";
$delivery_result = $conn->query($delivery_query);
$delivery_charges = [];
while ($row = $delivery_result->fetch_assoc()) {
    $delivery_charges[$row['location']] = $row['charge'];
}

$delivery_location = isset($_POST['delivery_location']) ? sanitize($_POST['delivery_location']) : 'Nairobi';
$delivery_charge = $delivery_charges[$delivery_location] ?? 300;
$total = $subtotal + $delivery_charge;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - AGROBIASHARA</title>
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
                <a href="index.php" class="btn btn-primary" style="margin: 0;">
                    <i class="fas fa-arrow-left"></i> Continue Shopping
                </a>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Cart Content -->
    <div class="dashboard-container">
        <h1><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>

        <?php if (empty($cart_items)): ?>
            <div style="text-align: center; padding: 3rem; background: white; border-radius: 8px; margin-top: 2rem;">
                <i class="fas fa-shopping-cart" style="font-size: 3rem; color: #ccc; display: block; margin-bottom: 1rem;"></i>
                <p style="font-size: 1.2rem; color: var(--text-light); margin-bottom: 1rem;"><strong>Your cart is empty</strong></p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem; margin-top: 2rem;">
                <!-- Cart Items -->
                <div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Farmer</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['farmer_name']); ?></td>
                                        <td>KES <?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>KES <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                        <td>
                                            <a href="?remove=<?php echo $item['id']; ?>" class="btn-delete" style="padding: 0.4rem 0.8rem; font-size: 0.9rem; text-decoration: none;" onclick="return confirm('Remove from cart?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Order Summary -->
                <div>
                    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow);">
                        <h3 style="margin-bottom: 1.5rem; color: var(--primary-green);">Order Summary</h3>

                        <form method="POST">
                            <div class="form-group">
                                <label for="delivery_location">Delivery Location *</label>
                                <select id="delivery_location" name="delivery_location" onchange="this.form.submit()" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <?php foreach ($delivery_charges as $location => $charge): ?>
                                        <option value="<?php echo $location; ?>" <?php echo $delivery_location === $location ? 'selected' : ''; ?>>
                                            <?php echo $location; ?> - KES <?php echo number_format($charge, 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 1rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span>Subtotal:</span>
                                <strong>KES <?php echo number_format($subtotal, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                                <span>Delivery:</span>
                                <strong>KES <?php echo number_format($delivery_charge, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 1.2rem; color: var(--primary-green); font-weight: 700; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                                <span>Total:</span>
                                <strong>KES <?php echo number_format($total, 2); ?></strong>
                            </div>
                        </div>

                        <a href="checkout.php" class="btn btn-primary" style="width: 100%; text-align: center; display: block; margin-top: 1.5rem; padding: 1rem;">
                            <i class="fas fa-credit-card"></i> Proceed to Checkout
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 AGROBIASHARA. Shopping Cart.</p>
    </footer>
</body>
</html>
