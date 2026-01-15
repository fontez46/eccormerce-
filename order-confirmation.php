<?php
session_start();
require 'includes/config.php';

// Debug logging
file_put_contents('order_confirmation.log', date('Y-m-d H:i:s') . " - Order confirmation started\n", FILE_APPEND);

// Check if order ID is provided
if (empty($_GET['order_id'])) {
    header("Location: index.php");
    exit;
}

$order_id = $_GET['order_id'];

// Verify the order belongs to the current user
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT user_id FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if ($order && $order['user_id'] != $_SESSION['user_id']) {
        header("Location: index.php");
        exit;
    }
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: index.php");
    exit;
}

// Get order items
$stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Clear cart if this is a completed order
if ($order['status'] === 'completed' && isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - JOEMAKEIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .confirmation-icon {
            font-size: 80px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .order-details {
            margin: 30px 0;
            text-align: left;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .order-items {
            margin: 20px 0;
        }
        .order-item {
            display: flex;
            margin: 15px 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #f5f5f5;
        }
        .order-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            margin-right: 20px;
        }
        .order-item-details {
            flex: 1;
        }
        .summary-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .summary-table tr:last-child td {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
        }
        .action-buttons {
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #088178;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 10px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #066a63;
        }
        .btn-outline {
            background-color: transparent;
            border: 1px solid #088178;
            color: #088178;
        }
        .btn-outline:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <section id="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="logo-text">JOE<span>MAKEIT</span></div>
        </div>
        <div>
            <ul id="navbar">
                <li><a href="index.php">Home</a></li>
                <li><a href="hop.php">Shop</a></li>
                <li><a href="about.html">About Us</a></li>
                <li><a href="contact.html">Help Center</a></li>
                <?php include 'account-component.php'; ?>
                <li id="bag">
                    <a href="cart.php">
                        <i class="fa fa-shopping-cart cart"></i>
                        <span id="cart-count">0</span>
                    </a>
                </li>
                <a id="clese" href="#"><i class="fa fa-times"></i></a>
            </ul>
            <div id="mobile">
                <a href="cart.php">
                    <i class="fa fa-shopping-cart cart"></i>
                    <span id="cart-count-mobile">0</span>
                </a>
                <i id="bar" class="fas fa-outdent"></i>
            </div>
        </div>
    </section>

    <section id="page-header" class="about-header">
        <h2>Order Confirmation</h2>
        <p>Thank you for your purchase!</p>
    </section>

    <section id="confirmation" class="section-p1">
        <div class="confirmation-container">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Your Order is Confirmed!</h2>
            <p>We've received your order ROO#<?= $order['order_id'] ?> and it's being processed.</p>
            <p>A confirmation email has been sent to <?= $_SESSION['user_email'] ?? 'your email' ?>.</p>
            
            <div class="order-details">
                <h3>Order Details</h3>
                <p><strong>Order Number:</strong> ROO#<?= $order['order_id'] ?></p>
                <p><strong>Date:</strong> <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                <p><strong>Total:</strong> Ksh <?= number_format($order['total_amount'], 2) ?></p>
                <p><strong>Payment Method:</strong> M-Pesa</p>
                <p><strong>Status:</strong> Processing</p>
                
                <h4 style="margin-top: 20px;">Delivery Address</h4>
                <p><?= htmlspecialchars($order['Full_Name']) ?></p>
                <p><?= htmlspecialchars($order['address_line1']) ?></p>
                <?php if (!empty($order['address_line2'])): ?>
                    <p><?= htmlspecialchars($order['address_line2']) ?></p>
                <?php endif; ?>
                <p><?= htmlspecialchars($order['town']) ?>, <?= htmlspecialchars($order['county']) ?></p>
                <p>Postal Code: <?= htmlspecialchars($order['postal_code']) ?></p>
                <p>Phone: <?= htmlspecialchars($order['phone']) ?></p>
                
                <div class="order-items">
                    <h4>Order Items</h4>
                    <?php foreach ($items as $item): ?>
                        <div class="order-item">
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                            <div class="order-item-details">
                                <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                <?php if (!empty($item['attribute_type']) && !empty($item['attribute_value'])): ?>
                                    <p><?= ucfirst($item['attribute_type']) ?>: <?= htmlspecialchars($item['attribute_value']) ?></p>
                                <?php endif; ?>
                                <p>Quantity: <?= $item['quantity'] ?></p>
                            </div>
                            <div>Ksh <?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <table class="summary-table">
                    <tr>
                        <td>Subtotal</td>
                        <td>Ksh <?= number_format($order['subtotal'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Shipping</td>
                        <td>Ksh <?= number_format($order['shipping_fee'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Total</td>
                        <td>Ksh <?= number_format($order['total_amount'], 2) ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="action-buttons">
                <a href="hop.php" class="btn">Continue Shopping</a>
                <a href="track-order.php?order_id=<?= $order['order_id'] ?>" class="btn btn-outline">Track Order</a>
            </div>
        </div>
    </section>

    <footer class="section-p1">
        <!-- Your footer content -->
    </footer>

    <script src="script.js"></script>
</body>
</html>