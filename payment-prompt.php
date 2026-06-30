<?php
include 'includes/config.php';
include 'includes/mpesa.php';
requireRole(ROLE_BUYER);

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
$amount = isset($_GET['amount']) ? trim($_GET['amount']) : '';

if (!$order_id || !$phone || !$amount) {
    header('Location: index.php');
    exit;
}

$order_query = "SELECT id, total_amount, delivery_location, delivery_charge, delivery_address, payment_method, status FROM orders WHERE id = ? AND user_id = ?";
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

$payment_prompt = "Hello! Please complete your AgroBiashara payment of KES {$amount} for order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . ". Reply with the transaction code once you have paid.";

$payment_record = null;
$stmt = $conn->prepare("SELECT * FROM mpesa_payments WHERE order_id = ? LIMIT 1");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$payment_result = $stmt->get_result();
$payment_record = $payment_result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_verified = isset($_POST['payment_verified']) ? 1 : 0;

    if ($payment_verified && $payment_record && $payment_record['status'] === 'completed') {
        header('Location: order-confirmation.php?order_id=' . $order_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Prompt - AGROBIASHARA</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
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

    <div class="dashboard-container">
        <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: var(--shadow); max-width: 700px; margin: 2rem auto;">
            <h1 style="color: var(--primary-green); margin-bottom: 1rem;"><i class="fas fa-mobile-alt"></i> M-Pesa Payment Request Sent</h1>
            <p style="margin-bottom: 1rem; color: var(--text-light);">
                A real M-Pesa STK push request has been sent to <strong><?php echo htmlspecialchars($phone); ?></strong> for <strong>KES <?php echo number_format($amount, 2); ?></strong>.
            </p>

            <div style="background: var(--light-bg); padding: 1rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid var(--primary-green);">
                <strong>Prompt message:</strong><br>
                <?php echo htmlspecialchars($payment_prompt); ?>
            </div>

            <form method="POST">
                <input type="hidden" name="payment_verified" value="1">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.05rem;">
                    <i class="fas fa-check-circle"></i> Check payment status
                </button>
            </form>

            <p style="margin-top: 1rem; color: var(--text-light); font-size: 0.95rem;">
                Your order will be completed automatically once Safaricom confirms the transaction through the callback URL.
            </p>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; 2026 AGROBIASHARA. Payment Prompt.</p>
    </footer>
</body>
</html>
