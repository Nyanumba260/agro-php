<?php
include 'includes/config.php';
requireRole(ROLE_BUYER);

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Get order details
$order_query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: index.php');
    exit;
}

// Get order items
$items_query = "SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - AGROBIASHARA</title>
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
        </div>
    </header>

    <!-- Order Confirmation -->
    <div class="dashboard-container">
        <div style="text-align: center; padding: 3rem; background: white; border-radius: 8px; margin-top: 2rem; box-shadow: var(--shadow);">
            <div style="font-size: 3rem; color: #27ae60; margin-bottom: 1rem;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 style="color: #27ae60; margin-bottom: 1rem;">Order Confirmed!</h1>
            <p style="font-size: 1.1rem; color: var(--text-light); margin-bottom: 2rem;">
                Thank you for your order. Your order has been successfully placed.
            </p>

            <div style="background: var(--light-bg); padding: 2rem; border-radius: 8px; margin-bottom: 2rem; text-align: left;">
                <h2 style="margin-bottom: 1.5rem; color: var(--primary-green);">Order Details</h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <div>
                        <p style="color: var(--text-light); margin-bottom: 0.5rem;">Order ID</p>
                        <p style="font-size: 1.3rem; font-weight: 700; color: var(--primary-green);">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
                    </div>
                    <div>
                        <p style="color: var(--text-light); margin-bottom: 0.5rem;">Order Date</p>
                        <p style="font-size: 1.3rem; font-weight: 700;"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div>
                        <p style="color: var(--text-light); margin-bottom: 0.5rem;">Delivery Location</p>
                        <p style="font-size: 1.3rem; font-weight: 700;"><?php echo htmlspecialchars($order['delivery_location']); ?></p>
                    </div>
                    <div>
                        <p style="color: var(--text-light); margin-bottom: 0.5rem;">Payment Method</p>
                        <p style="font-size: 1.3rem; font-weight: 700;"><?php echo ucfirst($order['payment_method']); ?></p>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                    <h3 style="margin-bottom: 1rem; color: var(--primary-green);">Items Ordered</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th style="text-align: left; padding: 0.5rem;">Product</th>
                                <th style="text-align: center; padding: 0.5rem;">Qty</th>
                                <th style="text-align: right; padding: 0.5rem;">Price</th>
                                <th style="text-align: right; padding: 0.5rem;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.5rem;"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td style="text-align: center; padding: 0.5rem;"><?php echo $item['quantity']; ?></td>
                                    <td style="text-align: right; padding: 0.5rem;">KES <?php echo number_format($item['price'], 2); ?></td>
                                    <td style="text-align: right; padding: 0.5rem;">KES <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem; margin-top: 1.5rem;">
                    <div style="display: flex; justify-content: flex-end; gap: 2rem; margin-bottom: 1rem;">
                        <div>
                            <p style="color: var(--text-light);">Subtotal:</p>
                            <p style="font-weight: 700;">KES <?php echo number_format($order['total_amount'] - $order['delivery_charge'], 2); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-light);">Delivery:</p>
                            <p style="font-weight: 700;">KES <?php echo number_format($order['delivery_charge'], 2); ?></p>
                        </div>
                        <div style="border-left: 2px solid var(--border-color); padding-left: 2rem;">
                            <p style="color: var(--text-light);">Total:</p>
                            <p style="font-size: 1.3rem; font-weight: 700; color: var(--primary-green);">KES <?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: center;">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Continue Shopping
                </a>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="btn" style="background: var(--text-light); color: white;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2026 AGROBIASHARA. Order Confirmation.</p>
    </footer>
</body>
</html>
