<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['cart']) || !isset($_POST['index']) || !isset($_POST['quantity'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$index = (int)$_POST['index'];
$new_quantity = (int)$_POST['quantity'];

if (!isset($_SESSION['cart'][$index])) {
    echo json_encode(['status' => 'error', 'message' => 'Item not found in cart']);
    exit;
}

$item = $_SESSION['cart'][$index];
$max_quantity = null;

// Check stock availability
if (isset($item['attribute_type']) && isset($item['value'])) {
    $stmt = $conn->prepare("SELECT stock FROM product_attributes 
                           WHERE product_id = ? 
                           AND attribute_type = ? 
                           AND value = ?");
    $stmt->bind_param("iss", $item['product_id'], $item['attribute_type'], $item['value']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stock = $result->fetch_assoc()['stock'];
        $max_quantity = $stock;
        
        if ($new_quantity > $stock) {
            $new_quantity = $stock;
        }
    }
} else {
    // Check product stock if no attribute
    $stmt = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $item['product_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stock = $result->fetch_assoc()['stock'];
        $max_quantity = $stock;
        
        if ($new_quantity > $stock) {
            $new_quantity = $stock;
        }
    }
}

// Update quantity in cart
$_SESSION['cart'][$index]['quantity'] = $new_quantity;

// Calculate updated cart count
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

$response = [
    'status' => 'success',
    'cart_count' => $cart_count
];

if ($max_quantity !== null) {
    $response['max_quantity'] = $max_quantity;
}

echo json_encode($response);
?>