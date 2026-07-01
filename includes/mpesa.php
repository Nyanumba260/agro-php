<?php
function mpesaBaseUrl() {
    return (defined('MPESA_ENV') && MPESA_ENV === 'live')
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';
}

function normalizeMpesaPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', trim($phone));

    if (strlen($phone) === 9) {
        $phone = '254' . $phone;
    } elseif (strlen($phone) === 10 && strpos($phone, '0') === 0) {
        $phone = '254' . substr($phone, 1);
    }

    return $phone;
}

function ensureMpesaTables() {
    global $conn;

    $conn->query("
        CREATE TABLE IF NOT EXISTS mpesa_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            checkout_request_id VARCHAR(100) NULL,
            merchant_request_id VARCHAR(100) NULL,
            receipt_number VARCHAR(100) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            result_code VARCHAR(20) NULL,
            result_desc TEXT NULL,
            phone_number VARCHAR(20) NULL,
            amount DECIMAL(10,2) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_order_payment (order_id)
        )
    ");
}

function getMpesaAccessToken() {
    $consumer_key = defined('MPESA_CONSUMER_KEY') ? MPESA_CONSUMER_KEY : '';
    $consumer_secret = defined('MPESA_CONSUMER_SECRET') ? MPESA_CONSUMER_SECRET : '';

    if (empty($consumer_key) || empty($consumer_secret)) {
        return ['success' => false, 'message' => 'M-Pesa credentials are not configured.'];
    }

    $ch = curl_init(mpesaBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($consumer_key . ':' . $consumer_secret)],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($http_code === 200 && !empty($data['access_token'])) {
        return ['success' => true, 'token' => $data['access_token']];
    }

    return [
        'success' => false,
        'message' => $data['error_description'] ?? 'Unable to generate M-Pesa access token.',
        'details' => $data,
    ];
}

function sendMpesaStkPush($phone, $amount, $order_id) {
    $shortcode = defined('MPESA_SHORTCODE') ? MPESA_SHORTCODE : '';
    $passkey = defined('MPESA_PASSKEY') ? MPESA_PASSKEY : '';
    $callback_url = defined('MPESA_CALLBACK_URL') ? MPESA_CALLBACK_URL : '';

    if (empty($shortcode) || empty($passkey) || empty($callback_url)) {
        return ['success' => false, 'message' => 'M-Pesa is not configured correctly. Set your Daraja shortcode, passkey, and a public callback URL such as https://your-ngrok-url/mpesa-callback.php.'];
    }

    $phone = normalizeMpesaPhone($phone);
    $timestamp = gmdate('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);
    $amount = intval($amount);

    $token_result = getMpesaAccessToken();
    if (!$token_result['success']) {
        return $token_result;
    }

    $payload = [
        'BusinessShortCode' => intval($shortcode),
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => intval($shortcode),
        'PhoneNumber' => $phone,
        'CallBackURL' => $callback_url,
        'AccountReference' => 'AGROBIASHARA-' . $order_id,
        'TransactionDesc' => 'Agrobiashara order ' . $order_id,
    ];

    $ch = curl_init(mpesaBaseUrl() . '/mpesa/stkpush/v1/processrequest');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token_result['token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($http_code === 200 && !empty($data['ResponseCode']) && $data['ResponseCode'] === '0') {
        return [
            'success' => true,
            'response' => $data,
            'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $data['MerchantRequestID'] ?? null,
        ];
    }

    return [
        'success' => false,
        'message' => $data['errorMessage'] ?? ($data['ResponseDescription'] ?? 'M-Pesa STK push request failed.'),
        'details' => $data,
    ];
}

function storeMpesaPaymentRecord($order_id, $checkout_request_id, $merchant_request_id, $status, $result_code = null, $result_desc = null, $phone_number = null, $amount = null, $receipt_number = null) {
    global $conn;

    $stmt = $conn->prepare(
        "INSERT INTO mpesa_payments (order_id, checkout_request_id, merchant_request_id, status, result_code, result_desc, phone_number, amount, receipt_number)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         checkout_request_id = VALUES(checkout_request_id),
         merchant_request_id = VALUES(merchant_request_id),
         status = VALUES(status),
         result_code = VALUES(result_code),
         result_desc = VALUES(result_desc),
         phone_number = VALUES(phone_number),
         amount = VALUES(amount),
         receipt_number = VALUES(receipt_number)"
    );

    $stmt->bind_param('issssssds', $order_id, $checkout_request_id, $merchant_request_id, $status, $result_code, $result_desc, $phone_number, $amount, $receipt_number);
    $stmt->execute();
    $stmt->close();
}

function completeMpesaOrder($order_id, $receipt_number = null) {
    global $conn;

    if (!$order_id) {
        return false;
    }

    $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();

    $clear_query = "DELETE FROM cart WHERE user_id = (SELECT user_id FROM orders WHERE id = ?)";
    $stmt = $conn->prepare($clear_query);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();

    if ($receipt_number) {
        $stmt = $conn->prepare("UPDATE mpesa_payments SET receipt_number = ?, status = 'completed' WHERE order_id = ?");
        $stmt->bind_param('si', $receipt_number, $order_id);
        $stmt->execute();
        $stmt->close();
    }

    return true;
}
