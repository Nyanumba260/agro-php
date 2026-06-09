<?php
include 'includes/config.php';
requireRole(ROLE_BUYER);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $user_id = $_SESSION['user_id'];
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    // Check if product exists
    $check_query = "SELECT id FROM products WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Check if product already in cart
        $cart_query = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($cart_query);
        $stmt->bind_param('ii', $user_id, $product_id);
        $stmt->execute();
        $cart_result = $stmt->get_result();

        if ($cart_result->num_rows > 0) {
            // Update quantity
            $cart_item = $cart_result->fetch_assoc();
            $new_quantity = $cart_item['quantity'] + $quantity;
            $update_query = "UPDATE cart SET quantity = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('ii', $new_quantity, $cart_item['id']);
            $stmt->execute();
        } else {
            // Add to cart
            $insert_query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('iii', $user_id, $product_id, $quantity);
            $stmt->execute();
        }
    }
    $stmt->close();
}

// Redirect back to products
header('Location: index.php');
exit;
?>
