<?php
// verify-payment.php
require 'includes/config.php';

// Get input data
$order_id = $_POST['order_id'] ?? null;
$checkout_request_id = $_POST['checkout_request_id'] ?? null;
$amount = $_POST['amount'] ?? 0;

if (!$order_id || !$checkout_request_id) {
    exit("Invalid request");
}

// Set higher timeout for background process
set_time_limit(120);

// Verify payment status
$payment_status = checkMpesaPaymentStatus($checkout_request_id);

file_put_contents('payment_verification.log', 
    date('Y-m-d H:i:s') . " - Background verification for $order_id:\n" . 
    print_r($payment_status, true) . "\n", 
    FILE_APPEND
);

if (isset($payment_status['ResultCode'])) {
    if ($payment_status['ResultCode'] == '0') {
        // Payment successful
        $mpesa_receipt = $payment_status['MpesaReceiptNumber'] ?? '';
        $phone = $payment_status['PhoneNumber'] ?? '';
        
        $conn->begin_transaction();
        try {
            // Update order status
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = 'completed', 
                    mpesa_receipt = ?, 
                    phone_number = ?,
                    updated_at = NOW()
                WHERE order_id = ?
            ");
            $stmt->bind_param("ssi", $mpesa_receipt, $phone, $order_id);
            $stmt->execute();
            $stmt->close();
            
            // Insert payment record
            $stmt = $conn->prepare("
                INSERT INTO payments (
                    order_id, 
                    payment_method, 
                    amount, 
                    payment_status, 
                    transaction_id,
                    created_at
                ) VALUES (?, 'M-Pesa', ?, 'Completed', ?, NOW())
            ");
            $stmt->bind_param("ids", $order_id, $amount, $mpesa_receipt);
            $stmt->execute();
            $stmt->close();
            
            // Deduct stock
            $stmt = $conn->prepare("SELECT product_id, quantity, attribute_type, attribute_value 
                                   FROM order_items 
                                   WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            foreach ($items as $item) {
                if (!empty($item['attribute_type']) && !empty($item['attribute_value'])) {
                    $stmt = $conn->prepare("
                        UPDATE product_attributes 
                        SET stock = stock - ? 
                        WHERE product_id = ? 
                        AND attribute_type = ? 
                        AND value = ?
                    ");
                    $stmt->bind_param("iiss", $item['quantity'], $item['product_id'], 
                                     $item['attribute_type'], $item['attribute_value']);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE products 
                        SET stock = stock - ? 
                        WHERE product_id = ?
                    ");
                    $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                }
                $stmt->execute();
                $stmt->close();
            }
            
            $conn->commit();
            
            file_put_contents('payment_verification.log', 
                date('Y-m-d H:i:s') . " - Order $order_id verified successfully\n", 
                FILE_APPEND
            );
        } catch (Exception $e) {
            $conn->rollback();
            file_put_contents('payment_verification.log', 
                date('Y-m-d H:i:s') . " - Error completing order $order_id: " . $e->getMessage() . "\n", 
                FILE_APPEND
            );
        }
    } else {
        // Payment failed
        $error_msg = $payment_status['ResultDesc'] ?? 'Payment verification failed';
        
        // Delete order and related records
        $conn->begin_transaction();
        try {
            // Delete payment record if exists
            $delete_payment = $conn->prepare("DELETE FROM payments WHERE order_id = ?");
            $delete_payment->bind_param("i", $order_id);
            $delete_payment->execute();
            $delete_payment->close();
            
            // Delete order items
            $delete_items = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            $delete_items->bind_param("i", $order_id);
            $delete_items->execute();
            $delete_items->close();
            
            // Delete order
            $delete_order = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
            $delete_order->bind_param("i", $order_id);
            $delete_order->execute();
            $delete_order->close();
            
            $conn->commit();
            
            file_put_contents('payment_verification.log', 
                date('Y-m-d H:i:s') . " - Order $order_id deleted. Reason: $error_msg\n", 
                FILE_APPEND
            );
        } catch (Exception $e) {
            $conn->rollback();
            file_put_contents('payment_verification.log', 
                date('Y-m-d H:i:s') . " - Error deleting order $order_id: " . $e->getMessage() . "\n", 
                FILE_APPEND
            );
        }
    }
}function checkMpesaPaymentStatus($checkout_request_id) {
    $access_token = getMpesaAccessToken();
    
    if (empty($access_token)) {
        return ['error' => 'Failed to get access token'];
    }

    // Sandbox credentials
    $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    $business_shortcode = '174379';
    $timestamp = date('YmdHis');
    $password = base64_encode($business_shortcode.$passkey.$timestamp);
    
    $payload = [
        'BusinessShortCode' => $business_shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $checkout_request_id
    ];


    
    
    $ch = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function getMpesaAccessToken() {
    $consumer_key = 'pxJCHdZT8KXPy4lVEmyeSsm9FFxpPLGzZVGEqUTJ4J07qGbO';
    $consumer_secret = 'Xlt0YJCSVLCzie6bNURloSPV24QmIsTnIaY68edvXGCj6S9oMycGb9D91Ymb64LL';
    
    $ch = curl_init('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic '.base64_encode($consumer_key.':'.$consumer_secret)]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['access_token'] ?? null;
}
?>