<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']), // Enable for HTTPS
    'cookie_samesite' => 'Strict'
]);
require 'includes/config.php';

// Validate user session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Generate unique order ID
$order_id = 'ORD-' . strtoupper(uniqid());

// Get order details from POST
$name = htmlspecialchars($_POST['name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$address = htmlspecialchars($_POST['address'] ?? '');
$payment_method = htmlspecialchars($_POST['payment_method'] ?? '');
$cart_items = $_SESSION['cart'] ?? [];
$total = $_SESSION['total'] ?? 0;
$order_user_id = $_SESSION['user_id'];

// Validate cart contents
if (empty($cart_items)) {
    die(json_encode(['error' => 'Your cart is empty']));
}

// Calculate subtotal
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

try {
    $conn->begin_transaction();

    // Insert order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, order_id, customer_name, customer_email, items, total_amount, shipping_address, payment_method) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssdss", $order_user_id, $order_id, $name, $email, json_encode($cart_items), $total, $address, $payment_method);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    unset($_SESSION['cart']);
    unset($_SESSION['total']);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Order error: " . $e->getMessage());
    die(json_encode(['error' => 'An error occurred while processing your order. Please try again.']));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - JOEMAKEIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <section id="header">
        <a href="#"><img src="IMG/logo.JPG" class="logo" alt=""></a>
        <div>
            <ul id="navbar">
                <li><a href="index.php">Home</a></li>
                <li><a href="hop.php">Shop</a></li>
                <li><a href="about.html">About Us</a></li>
                <li><a href="contact.html">Contact</a></li>
                <?php include 'account-component.php'; ?>
                <li id="bag"><a href="cart.php"><i class="fa fa-shopping-cart cart"></i>
                    <span id="cart-count">0</span>
                </a></li>
            </ul>
        </div>
    </section>

    <section id="pages-header" class="order-header">
        <h2>Order Confirmation</h2>
        <p>Thank you for your purchase!</p>
    </section>

    <section id="order-details" class="section-p1">
        <div class="order-container">
            <div class="order-summary">
                <div class="user-info">
                    <h3>Customer Information</h3>
                    <p><strong>Name:</strong> <?= htmlspecialchars($name) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
                    <p><strong>Address:</strong> <?= htmlspecialchars($address) ?></p>
                    <p><strong>Payment Method:</strong> <?= htmlspecialchars($payment_method) ?></p>
                    <p><strong>Order ID:</strong> <?= htmlspecialchars($order_id) ?></p>
                </div>

                <div class="order-items">
                    <h3>Order Items</h3>
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td class="product-details">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                         class="product-image">
                                    <div>
                                        <h4><?= htmlspecialchars($item['name']) ?></h4>
                                        <?php if (!empty($item['size'])): ?>
                                            <p>Size: <?= htmlspecialchars($item['size']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>Ksh <?= number_format($item['price'], 0) ?></td>
                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                                <td>Ksh <?= number_format($item['price'] * $item['quantity'], 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="order-totals">
                    <h3>Order Summary</h3>
                    <table>
                        <tr>
                            <td>Subtotal:</td>
                            <td>Ksh <?= number_format($subtotal, 0) ?></td>
                        </tr>
                        <tr>
                            <td>Shipping:</td>
                            <td>Ksh <?= number_format($_SESSION['shipping_fee'] ?? 0, 0) ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Grand Total:</strong></td>
                            <td><strong>Ksh <?= number_format($total, 0) ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="order-actions">
                <a href="hop.php" class="btn continue-shopping">Continue Shopping</a>
                <button class="btn print-order" onclick="window.print()">Print Receipt</button>
            </div>
        </div>
    </section>

    <section id="newsletter" class="section-p1 section-ml">
        <!-- Keep existing newsletter section -->
    </section>

    <footer class="section-p1">
        <!-- Keep existing footer -->
    </footer>

    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="script.js"></script>
</body>
</html>
<?php $conn->close(); ?>