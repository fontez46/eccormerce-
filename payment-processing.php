<?php
session_start();
require 'includes/config.php';

// Enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set higher timeout limit
set_time_limit(180); // 3 minutes

// Debug logging
file_put_contents('payment_processing.log', date('Y-m-d H:i:s') . " - Payment processing started\n", FILE_APPEND);

// Check if we have a pending transaction
if (empty($_SESSION['pending_transaction']) || empty($_SESSION['checkout_request_id'])) {
    file_put_contents('payment_processing.log', "No pending transaction found\n", FILE_APPEND);
    header("Location: cart.php");
    exit;
}

$transaction = $_SESSION['pending_transaction'];
$checkout_request_id = $_SESSION['checkout_request_id'];

// FIX: Increased timeout handling
$max_attempts = 40; // 120 seconds total (40×3 seconds)
$attempt = 0;
$payment_completed = false;

// Initial delay before polling starts
sleep(5);

while ($attempt < $max_attempts && !$payment_completed) {
    $attempt++;
    
    // Check payment status via API
    $payment_status = checkMpesaPaymentStatus($checkout_request_id);
    
    file_put_contents('payment_processing.log', 
        date('Y-m-d H:i:s') . " - Attempt $attempt: " . json_encode($payment_status) . "\n", 
        FILE_APPEND
    );
    
    if (isset($payment_status['ResultCode'])) {
        if ($payment_status['ResultCode'] == '0') {
            $payment_completed = true;
        } else {
            // Payment failed - break loop
            break;
        }
    }
    
    // Wait before next attempt
    sleep(3);
}

if ($payment_completed) {
    // Create order
    $order_id = saveOrder(
        $transaction['transaction_ref'],
        'completed',
        $checkout_request_id,
        $transaction['phone'],
        $payment_status['MpesaReceiptNumber'] ?? ''
    );
    
    if ($order_id) {
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
        $stmt->bind_param("ids", $order_id, $transaction['amount'], $payment_status['MpesaReceiptNumber']);
        $stmt->execute();
        $stmt->close();
        
        // Clear session data
        unset($_SESSION['cart']);
        unset($_SESSION['pending_transaction']);
        unset($_SESSION['checkout_request_id']);
        
        header("Location: order-confirmation.php?order_id=".$order_id);
        exit;
    } else {
        $error_msg = 'Failed to create order';
    }
} else {
    $error_msg = $payment_status['ResultDesc'] ?? 'Payment processing timeout';
}

// Payment failed - clean up
unset($_SESSION['pending_transaction']);
unset($_SESSION['checkout_request_id']);
header("Location: checkout.php?error=" . urlencode($error_msg));
exit;

function checkMpesaPaymentStatus($checkout_request_id) {
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

function saveOrder($transaction_ref, $status, $checkout_request_id, $phone, $mpesa_receipt) {
    global $conn;
    
    $user_id = $_SESSION['user_id'] ?? null;
    $delivery_address = $_SESSION['delivery_address'];
    $cart = $_SESSION['cart'];
    
    // Calculate totals
    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    $shipping_fee = 0;
    if (!empty($_SESSION['delivery_region']) && !empty($_SESSION['delivery_town'])) {
        $sql = "SELECT rate FROM shipping_rates WHERE region = ? AND town = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $_SESSION['delivery_region'], $_SESSION['delivery_town']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $shipping_fee = $result->fetch_assoc()['rate'];
        }
        $stmt->close();
    }
    
    $total_amount = $subtotal + $shipping_fee;
    
    // Prepare variables for binding
    $full_name = $delivery_address['full_name'];
    $address_line1 = $delivery_address['address_line1'];
    $address_line2 = $delivery_address['address_line2'] ?? '';
    $county = $delivery_address['county'];
    $town = $delivery_address['town'];
    $postal_code = $delivery_address['postal_code'];
    $address_id = $delivery_address['id'] ?? null;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert order
        $stmt = $conn->prepare("
            INSERT INTO orders (
                user_id, transaction_ref, checkout_request_id, status, 
                subtotal, shipping_fee, total_amount, address_id, phone_number, mpesa_receipt, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "isssdddiss",
            $user_id,
            $transaction_ref,
            $checkout_request_id,
            $status,
            $subtotal,
            $shipping_fee,
            $total_amount,
            $address_id,
            $phone,
            $mpesa_receipt
        );
        $stmt->execute();
        $order_id = $conn->insert_id;
        $stmt->close();
        
        // Insert order items
        $stmt = $conn->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, price, quantity, 
                attribute_type, attribute_value, image_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($cart as $item) {
            $product_id = $item['product_id'];
            $product_name = $item['name'];
            $price = $item['price'];
            $quantity = $item['quantity'];
            $attribute_type = $item['attribute_type'] ?? null;
            $attribute_value = $item['value'] ?? null;
            $image_url = $item['image_url'];
            
            $stmt->bind_param(
                "iisdisss",
                $order_id,
                $product_id,
                $product_name,
                $price,
                $quantity,
                $attribute_type,
                $attribute_value,
                $image_url
            );
            $stmt->execute();
        }
        $stmt->close();
        
        $conn->commit();
        return $order_id;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Failed to save order: " . $e->getMessage());
        return false;
    }
}
?>