<?php
session_start();
require 'includes/config.php'; // Ensure this points to your config file

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate order_id
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

$order_id = (int)$_GET['order_id'];
$user_id = (int)$_SESSION['user_id'];

try {
    // Fetch order details
    $stmt = $conn->prepare("
        SELECT o.order_id, o.created_at AS order_date, o.total_amount, o.status, o.payment_method,
               a.address_line1, a.address_line2, a.county, a.constituency, a.Town, a.postal_code, a.Full_Name AS address_name
        FROM orders o
        LEFT JOIN addresses a ON o.address_id = a.id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    $order = $result->fetch_assoc();
    $stmt->close();

    // Format shipping address
    $shipping_address = $order['address_name'] . ', ' . $order['address_line1'];
    if (!empty($order['address_line2'])) {
        $shipping_address .= ', ' . $order['address_line2'];
    }
    $shipping_address .= ', ' . $order['Town'] . ', ' . $order['constituency'] . ', ' . $order['county'] . ', ' . $order['postal_code'];

    // Fetch order items with image_url from products
    $stmt = $conn->prepare("
        SELECT p.name, oi.quantity, oi.price AS price, COALESCE(p.image_url, '/img/default-product.jpg') AS image_url
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Prepare response
    $response = [
        'order_id' => $order['order_id'],
        'order_date' => $order['order_date'],
        'status' => $order['status'],
        'total_amount' => $order['total_amount'],
        'payment_method' => $order['payment_method'],
        'shipping_address' => $shipping_address,
        'items' => $items
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    error_log('Order details error: ' . $e->getMessage());
}
exit;
?>