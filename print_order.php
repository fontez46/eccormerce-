<?php
require_once '../admin/config.php';

session_start([
    'cookie_httponly' => 1,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => 1
]);

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: admin.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
    die("Invalid order ID");
}

// Fetch order details
$order_stmt = $conn->prepare("SELECT o.order_id, o.created_at, o.total_amount, o.status, o.payment_method, u.username, u.email, u.phone_number, a.address_line1, a.address_line2, a.county, a.constituency, a.Town, a.postal_code FROM orders o JOIN users u ON o.user_id = u.user_id LEFT JOIN addresses a ON o.address_id = a.id WHERE o.order_id = ?");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order_details = $order_result->fetch_assoc();
$order_stmt->close();

if (!$order_details) {
    die("Order not found");
}

// Fetch order items
$items_stmt = $conn->prepare("SELECT p.name, p.image_url, oi.quantity, oi.price, (oi.price * oi.quantity) AS subtotal FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$order_items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

// Fetch tracking information
$track_stmt = $conn->prepare("SELECT status, location, notes, created_at FROM order_tracking WHERE order_id = ? ORDER BY created_at DESC");
$track_stmt->bind_param("i", $order_id);
$track_stmt->execute();
$track_result = $track_stmt->get_result();
$tracking_info = $track_result->fetch_all(MYSQLI_ASSOC);
$track_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Order #<?= $order_id ?> - JOEMAKEIT</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { max-width: 100px; }
        .order-details { margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background: #f4f4f4; }
        .tracking-timeline { margin-top: 20px; }
        .timeline-item { margin-bottom: 20px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container">
        <div class="header">
            <h1>JOEMAKEIT</h1>
            <p>Order #<?= $order_id ?> - Print Receipt</p>
        </div>

        <div class="order-details">
            <h2>Order Details</h2>
            <p><strong>Customer:</strong> <?= htmlspecialchars($order_details['username']) ?> (<?= htmlspecialchars($order_details['email']) ?>)</p>
            <p><strong>Order Date:</strong> <?= date('F d, Y', strtotime($order_details['created_at'])) ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($order_details['status']) ?></p>
            <p><strong>Payment Method:</strong> <?= htmlspecialchars($order_details['payment_method']) ?></p>
            <p><strong>Delivery Address:</strong> <?= htmlspecialchars(implode(", ", array_filter([
                $order_details['address_line1'],
                $order_details['address_line2'],
                $order_details['Town'],
                $order_details['constituency'],
                $order_details['county'],
                $order_details['postal_code']
            ]))) ?></p>
        </div>

        <h2>Order Items</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>Ksh <?= number_format($item['price'], 2) ?></td>
                        <td>Ksh <?= number_format($item['subtotal'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th colspan="3">Total</th>
                    <td>Ksh <?= number_format($order_details['total_amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="tracking-timeline">
            <h2>Tracking History</h2>
            <?php if (!empty($tracking_info)): ?>
                <?php foreach ($tracking_info as $track): ?>
                    <div class="timeline-item">
                        <p><strong>Status:</strong> <?= htmlspecialchars($track['status']) ?></p>
                        <p><strong>Date:</strong> <?= date('M d, Y h:i A', strtotime($track['created_at'])) ?></p>
                        <p><strong>Location:</strong> <?= htmlspecialchars($track['location'] ?? 'N/A') ?></p>
                        <?php if (!empty($track['notes'])): ?>
                            <p><strong>Notes:</strong> <?= htmlspecialchars($track['notes']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No tracking information available.</p>
            <?php endif; ?>
        </div>

        <div class="no-print">
            <button onclick="window.close()">Close</button>
        </div>
    </div>
</body>
</html>