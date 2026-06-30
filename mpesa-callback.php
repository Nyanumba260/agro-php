<?php
include 'includes/config.php';
include 'includes/mpesa.php';

ensureMpesaTables();

$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (!$payload) {
    http_response_code(400);
    exit('Invalid payload');
}

if (!empty($payload['Body']['stkCallback'])) {
    $callback = $payload['Body']['stkCallback'];
    $checkout_request_id = $callback['CheckoutRequestID'] ?? null;
    $result_code = $callback['ResultCode'] ?? null;
    $result_desc = $callback['ResultDesc'] ?? null;
    $receipt_number = null;

    if (!empty($callback['CallbackMetadata']['Item'])) {
        foreach ($callback['CallbackMetadata']['Item'] as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $receipt_number = $item['Value'] ?? null;
            }
        }
    }

    $status = ($result_code === 0) ? 'completed' : 'failed';

    $order_query = "SELECT order_id FROM mpesa_payments WHERE checkout_request_id = ? LIMIT 1";
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param('s', $checkout_request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if ($payment) {
        $order_id = $payment['order_id'];
        $stmt = $conn->prepare("UPDATE mpesa_payments SET status = ?, result_code = ?, result_desc = ?, receipt_number = ? WHERE checkout_request_id = ?");
        $stmt->bind_param('sssss', $status, $result_code, $result_desc, $receipt_number, $checkout_request_id);
        $stmt->execute();
        $stmt->close();

        if ($status === 'completed') {
            completeMpesaOrder($order_id, $receipt_number);
        }
    }
}

http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
