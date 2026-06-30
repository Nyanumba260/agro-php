<?php
include 'includes/config.php';
include 'includes/mpesa.php';
requireRole(ROLE_BUYER);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get cart items
$cart_query = "SELECT SUM(p.price * c.quantity) as subtotal FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_data = $result->fetch_assoc();
$subtotal = $cart_data['subtotal'] ?? 0;
$stmt->close();

// Get delivery charges
$delivery_query = "SELECT location, charge FROM delivery_charges ORDER BY location";
$delivery_result = $conn->query($delivery_query);
$delivery_charges = [];
while ($row = $delivery_result->fetch_assoc()) {
    $delivery_charges[$row['location']] = $row['charge'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $delivery_location = sanitize($_POST['delivery_location']);
    $delivery_address = sanitize($_POST['delivery_address']);
    $payment_method = sanitize($_POST['payment_method']);

    if (empty($name) || empty($email) || empty($phone) || empty($delivery_location) || empty($delivery_address)) {
        $error = 'All fields are required';
    } else {
        $delivery_charge = $delivery_charges[$delivery_location] ?? 300;
        $total = $subtotal + $delivery_charge;

        ensureMpesaTables();

        // Create order
        $order_query = "INSERT INTO orders (user_id, total_amount, delivery_location, delivery_charge, delivery_address, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($order_query);
        $stmt->bind_param('iddsss', $user_id, $total, $delivery_location, $delivery_charge, $delivery_address, $payment_method);

        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;

            // Get cart items and add to order_items
            $get_cart_query = "SELECT c.product_id, c.quantity, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?";
            $stmt2 = $conn->prepare($get_cart_query);
            $stmt2->bind_param('i', $user_id);
            $stmt2->execute();
            $cart_result = $stmt2->get_result();
            $cart_items_array = [];

            while ($item = $cart_result->fetch_assoc()) {
                $cart_items_array[] = $item;
                $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                $stmt3 = $conn->prepare($item_query);
                $stmt3->bind_param('iiid', $order_id, $item['product_id'], $item['quantity'], $item['price']);
                $stmt3->execute();
                $stmt3->close();
            }
            $stmt2->close();

            if ($payment_method === 'mpesa') {
                $mpesa_result = sendMpesaStkPush($phone, $total, $order_id);

                if ($mpesa_result['success']) {
                    storeMpesaPaymentRecord(
                        $order_id,
                        $mpesa_result['checkout_request_id'] ?? null,
                        $mpesa_result['merchant_request_id'] ?? null,
                        'pending',
                        null,
                        null,
                        $phone,
                        $total
                    );

                    $redirect_url = 'payment-prompt.php?order_id=' . $order_id . '&phone=' . urlencode($phone) . '&amount=' . urlencode($total);
                    header('Location: ' . $redirect_url);
                    exit;
                }

                $error = $mpesa_result['message'] ?? 'Unable to start M-Pesa payment.';
            }

            // Update product quantities (reduce by quantity purchased)
            $update_qty_query = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
            $stmt5 = $conn->prepare($update_qty_query);

            foreach ($cart_items_array as $item) {
                $qty = $item['quantity'];
                $prod_id = $item['product_id'];
                $stmt5->bind_param('ii', $qty, $prod_id);
                $stmt5->execute();
            }
            $stmt5->close();

            // Clear cart
            $clear_query = "DELETE FROM cart WHERE user_id = ?";
            $stmt4 = $conn->prepare($clear_query);
            $stmt4->bind_param('i', $user_id);
            $stmt4->execute();
            $stmt4->close();

            header('Location: order-confirmation.php?order_id=' . $order_id);
            exit;
        } else {
            $error = 'Error creating order';
        }
        $stmt->close();
    }
}

$delivery_charge = $delivery_charges['Nairobi'] ?? 300;
$total = $subtotal + $delivery_charge;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - AGROBIASHARA</title>
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

    <!-- Checkout Form -->
    <div class="dashboard-container">
        <h1><i class="fas fa-credit-card"></i> Checkout</h1>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem; margin-top: 2rem;">
            <!-- Checkout Form -->
            <form method="POST" class="form-container" style="max-width: 100%; background: white; padding: 2rem; border-radius: 8px; box-shadow: var(--shadow);">
                <h2 style="margin-bottom: 1.5rem; color: var(--primary-green);">Delivery Information</h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required placeholder="Your name">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required placeholder="your@email.com">
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required placeholder="+254 7XX XXX XXX">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="delivery_location">Delivery Location *</label>
                        <select id="delivery_location" name="delivery_location" required onchange="updateTotal()">
                            <?php foreach ($delivery_charges as $location => $charge): ?>
                                <option value="<?php echo $location; ?>" <?php echo $location === 'Nairobi' ? 'selected' : ''; ?>>
                                    <?php echo $location; ?> - KES <?php echo number_format($charge, 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="cash">Cash on Delivery</option>
                            <option value="mpesa">M-Pesa</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="delivery_address">Delivery Address *</label>
                    <textarea id="delivery_address" name="delivery_address" required placeholder="Enter your full delivery address..." style="min-height: 100px;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                    <i class="fas fa-check"></i> Pay Now
                </button>
                <p style="margin-top: 0.75rem; color: var(--text-light); font-size: 0.95rem;">
                    For M-Pesa, a payment prompt will be sent to the phone number above so you can complete the purchase securely.
                </p>
            </form>

            <!-- Order Summary -->
            <div>
                <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow);">
                    <h3 style="margin-bottom: 1.5rem; color: var(--primary-green);">Order Summary</h3>

                    <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Subtotal:</span>
                            <strong>KES <?php echo number_format($subtotal, 2); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Delivery:</span>
                            <strong id="deliveryChargeDisplay">KES <?php echo number_format($delivery_charge, 2); ?></strong>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 1.2rem; color: var(--primary-green); font-weight: 700;">
                        <span>Total:</span>
                        <strong id="totalDisplay">KES <?php echo number_format($total, 2); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2026 AGROBIASHARA. Checkout.</p>
    </footer>

    <script>
        const deliveryCharges = <?php echo json_encode($delivery_charges); ?>;
        const subtotal = <?php echo $subtotal; ?>;

        function updateTotal() {
            const location = document.getElementById('delivery_location').value;
            const charge = deliveryCharges[location] || 300;
            const total = subtotal + charge;

            document.getElementById('deliveryChargeDisplay').textContent = 'KES ' + charge.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('totalDisplay').textContent = 'KES ' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    </script>
</body>
</html>
