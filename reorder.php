<?php
session_start();
require 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

$order_id = (int)$_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Verify order belongs to user
$stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}
$stmt->close();

// Fetch order items
$stmt = $conn->prepare("
    SELECT p.product_id, p.name, oi.unit_price AS price, oi.quantity, pi.image_url, oi.size
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN product_images pi ON p.product_id = pi.product_id
    WHERE oi.order_id = ?
    GROUP BY oi.order_item_id
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'No items found in this order']);
    exit;
}

// Initialize or update cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add items to cart
foreach ($items as $item) {
    $cart_item = [
        'product_id' => $item['product_id'],
        'name' => $item['name'],
        'price' => $item['price'],
        'quantity' => $item['quantity'],
        'image_url' => $item['image_url'] ?: 'IMG/products/S2.png', // Fallback image
        'size' => $item['size'] ?: '' // Handle null size
    ];


 

    // Check if item already exists in cart (based on product_id and size)
    $item_key = array_search($item['product_id'] . ($item['size'] ?: ''), 
        array_column(array_map(function($cart_item) {
            return ['id_size' => $cart_item['id'] . ($cart_item['size'] ?: '')];
        }, $_SESSION['cart']), 'id_size'));

    if ($item_key !== false) {
        // Update quantity if item exists
        $_SESSION['cart'][$item_key]['quantity'] += $item['quantity'];
    } else {
        // Add new item
        $_SESSION['cart'][] = $cart_item;
    }
}

echo json_encode(['success' => true, 'message' => 'Items added to cart successfully']);
exit;
?>