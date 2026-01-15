<?php
session_start();
require 'includes/config.php';

// Debug logging
file_put_contents('payment_debug.log', date('Y-m-d H:i:s') . " - Checkout started\n", FILE_APPEND);

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// Check if address is set
if (empty($_SESSION['delivery_address'])) {
    header("Location: cart.php");
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Calculate totals
$subtotal = 0;
$shipping_fee = 0;
$total_amount = 0;

foreach ($_SESSION['cart'] as $item) {
    $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
}

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

// Process payment when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Validate minimum amount
    if ($total_amount < 1) {
        echo json_encode([
            'success' => false,
            'message' => 'Amount must be at least Ksh 1'
        ]);
        exit;
    }

    // Process M-Pesa payment
    $phone = preg_replace('/\D/', '', $_SESSION['delivery_address']['phone_number']);
    
    // Convert phone number to Safaricom format (254...)
    if (substr($phone, 0, 2) === '07' && strlen($phone) === 10) {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 1) === '7' && strlen($phone) === 9) {
        $phone = '254' . $phone;
    } elseif (substr($phone, 0, 1) === '1' && strlen($phone) === 9) {
        $phone = '254' . $phone;
    }

    // Generate unique transaction reference
    $transaction_ref = 'JM' . time() . rand(100, 999);

    // Initiate STK Push
    $result = initiateSTKPush($phone, $total_amount, $transaction_ref);
    
    if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
        // Payment initiated successfully - create temporary order ID
        $_SESSION['checkout_request_id'] = $result['CheckoutRequestID'];
        $_SESSION['payment_initiated'] = true;
        $_SESSION['pending_transaction'] = [
            'phone' => $phone,
            'amount' => $total_amount,
            'transaction_ref' => $transaction_ref
        ];
        
        echo json_encode([
            'success' => true,
            'ResponseCode' => '0',
            'redirect' => 'payment-processing.php',
            'checkout_request_id' => $result['CheckoutRequestID']
        ]);
        exit;
    } else {
        $error_msg = $result['errorMessage'] ?? ($result['ResponseDescription'] ?? 'Failed to initiate payment');
        echo json_encode([
            'success' => false,
            'message' => $error_msg
        ]);
        exit;
    }
}

function initiateSTKPush($phone, $amount, $transaction_ref) {
    // M-Pesa API credentials
    $consumer_key = 'pxJCHdZT8KXPy4lVEmyeSsm9FFxpPLGzZVGEqUTJ4J07qGbO';
    $consumer_secret = 'Xlt0YJCSVLCzie6bNURloSPV24QmIsTnIaY68edvXGCj6S9oMycGb9D91Ymb64LL';
    $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    $business_shortcode = '174379';
    
    // Get access token
    $credentials = base64_encode($consumer_key.':'.$consumer_secret);
    $ch = curl_init('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic '.$credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    $access_token = $result['access_token'] ?? '';
    
    if (empty($access_token)) {
        return ['ResponseCode' => '1', 'errorMessage' => 'Failed to get access token'];
    }
    
    // Prepare STK Push request
    $timestamp = date('YmdHis');
    $password = base64_encode($business_shortcode.$passkey.$timestamp);
    $callback_url = 'https://87f8320d454c.ngrok-free.app/SHOPP/mpesa-callback.php'; 
    
    $payload = [
        'BusinessShortCode' => $business_shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => round($amount),
        'PartyA' => $phone,
        'PartyB' => $business_shortcode,
        'PhoneNumber' => $phone,
        'CallBackURL' => $callback_url,
        'AccountReference' => $transaction_ref,
        'TransactionDesc' => 'Payment for order #'.$transaction_ref
    ];
    
    $ch = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Payment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-color: #088178;
            --secondary-color: #f3f4f6;
            --accent-color: #10b981;
            --dark-color: #1f2937;
            --light-color: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: rgba(0, 0, 0, 0.5);
            color: var(--dark-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="payment-modal">
        <button class="close-btn" onclick="window.parent.closePaymentModal()">&times;</button>
        
        <div class="payment-header">
            <h2>Complete Payment</h2>
            <p>You'll receive an M-Pesa prompt on your phone</p>
        </div>

        <div class="payment-content">
            <div class="phone-display">
                <p>Payment prompt will be sent to: <strong><?= htmlspecialchars($_SESSION['delivery_address']['phone_number']) ?></strong></p>
            </div>

            <div class="payment-summary">
                <div class="summary-item">
                    <span>Subtotal</span>
                    <span>Ksh <?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="summary-item">
                    <span>Shipping</span>
                    <span>Ksh <?= number_format($shipping_fee, 2) ?></span>
                </div>
                <div class="summary-item summary-total">
                    <span>Total Amount</span>
                    <span>Ksh <?= number_format($total_amount, 2) ?></span>
                </div>
            </div>

            <form id="paymentForm" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="payment-method-card selected" id="mpesaMethod">
                    <div class="payment-method-header">
                        <div class="payment-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="payment-method-title">M-Pesa Mobile Payment</div>
                    </div>
                    <div class="payment-method-description">
                        Complete payment securely via M-Pesa STK Push. You'll receive a payment request on your phone.
                    </div>
                </div>
                
                <button type="submit" class="payment-btn" id="payButton">
                    <i class="fas fa-lock"></i> Pay Ksh <?= number_format($total_amount, 2) ?>
                </button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('payButton');
            btn.disabled = true;
            btn.innerHTML = '<span class="loader"></span> Initiating Payment...';            
            fetch('checkout.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(new FormData(this))
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.redirect) {
                    // Redirect to payment processing page
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Payment initiation failed');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock"></i> Pay Ksh <?= number_format($total_amount, 2) ?>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing your payment');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock"></i> Pay Ksh <?= number_format($total_amount, 2) ?>';
            });
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target === document.body) {
                window.parent.closePaymentModal();
            }
        });
    </script>
</body>
</html>