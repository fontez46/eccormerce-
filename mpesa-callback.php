<?php
require 'includes/config.php';

// Debug logging
file_put_contents('mpesa_callback.log', date('Y-m-d H:i:s') . " - M-Pesa callback started\n", FILE_APPEND);

// Log raw callback data
$callback_data = file_get_contents('php://input');
file_put_contents('mpesa_callback.log', date('Y-m-d H:i:s') . " - Raw data: " . $callback_data . "\n", FILE_APPEND);

$data = json_decode($callback_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    file_put_contents('mpesa_callback.log', date('Y-m-d H:i:s') . " - Invalid JSON data\n", FILE_APPEND);
    header("Content-Type: application/json");
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON data']);
    exit;
}

// Check the proper callback structure
if (!isset($data['Body']['stkCallback'])) {
    file_put_contents('mpesa_callback.log', date('Y-m-d H:i:s') . " - Missing stkCallback in data\n", FILE_APPEND);
    header("Content-Type: application/json");
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback data']);
    exit;
}

$callback = $data['Body']['stkCallback'];
$checkout_request_id = $callback['CheckoutRequestID'] ?? '';
$result_code = $callback['ResultCode'] ?? '1';
$result_desc = $callback['ResultDesc'] ?? 'Unknown error';

file_put_contents('mpesa_callback.log', date('Y-m-d H:i:s') . " - Callback received for CheckoutRequestID: $checkout_request_id, ResultCode: $result_code\n", FILE_APPEND);

// Only process successful payments
if ($result_code == '0') {
    // Extract payment details
    $amount = 0;
    $mpesa_receipt = '';
    $phone = '';
    
    if (isset($callback['CallbackMetadata']) && isset($callback['CallbackMetadata']['Item'])) {
        foreach ($callback['CallbackMetadata']['Item'] as $item) {
            switch ($item['Name']) {
                case 'Amount':
                    $amount = $item['Value'] ?? 0;
                    break;
                case 'MpesaReceiptNumber':
                    $mpesa_receipt = $item['Value'] ?? '';
                    break;
                case 'PhoneNumber':
                    $phone = $item['Value'] ?? '';
                    break;
            }
        }
    }
    
    // Get pending transaction from session
    if (!isset($_SESSION['pending_transaction'])) {
        file_put_contents('mpesa_callback.log', date('Y-m-d H:i:s') . " - No pending transaction found\n", FILE_APPEND);
        header("Content-Type: application/json");
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success - No pending transaction']);
        exit;
    }
    
    $transaction = $_SESSION['pending_transaction'];
    $expected_amount = (int)round($transaction['amount']);
    $paid_amount = (int)$amount;
    
    // Log amounts for verification
    file_put_contents('mpesa_callback.log', 
        "Amount verification: Paid $paid_amount vs Expected $expected_amount (Original: {$transaction['amount']})\n", 
        FILE_APPEND
    );
    
    // Validate payment amount
    if ($paid_amount !== $expected_amount) {
        file_put_contents('mpesa_callback.log', "Amount mismatch: Paid $paid_amount vs Expected $expected_amount\n", FILE_APPEND);
        header("Content-Type: application/json");
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success - Amount mismatch']);
        exit;
    }
    
    // Start transaction for order creation
    $conn->begin_transaction();
    
    try {
        // Create order only now
        $order_id = saveOrder(
            $transaction['transaction_ref'],
            'completed',
            $checkout_request_id,
            $transaction['phone'],
            $mpesa_receipt
        );
        
        if (!$order_id) {
            throw new Exception('Failed to save order');
        }
        
        // Insert payment record
        $stmt = $conn->prepare("
            INSERT INTO payments (
                order_id, 
                payment_method, 
                amount, 
                payment_status, 
                transaction_id,
                created_at,
                updated_at
            ) VALUES (?, 'M-Pesa', ?, 'Completed', ?, NOW(), NOW())
        ");
        $stmt->bind_param("ids", $order_id, $transaction['amount'], $mpesa_receipt);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        // Clear session data
        unset($_SESSION['pending_transaction']);
        unset($_SESSION['cart']);
        
        file_put_contents('mpesa_callback.log', date('Y-m-d H:i:s') . " - Order $order_id created. Receipt: $mpesa_receipt\n", FILE_APPEND);
    } catch (Exception $e) {
        $conn->rollback();
        file_put_contents('mpesa_callback.log', date('Y-m-d H:i:s') . " - Error creating order: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Always respond successfully to Safaricom
header("Content-Type: application/json");
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);

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