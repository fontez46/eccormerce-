<?php
session_start();
require 'includes/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid CSRF token');
    }

    $product_id = (int)$_POST['product_id'];

    // Fetch product details
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $product_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Product not found');
    }

    $product = $result->fetch_assoc();

    // Check stock for simple product
    if ($product['stock'] < 1) {
        throw new Exception('Product is out of stock');
    }

    // Add to cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Check if product already in cart
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        // FIXED: Changed 'id' to 'product_id'
        if (isset($item['product_id']) && $item['product_id'] == $product_id && empty($item['attribute_type'])) {
            // Check if adding would exceed stock
            if ($item['quantity'] + 1 > $product['stock']) {
                throw new Exception('Cannot add more than available stock');
            }
            
            $item['quantity']++;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $_SESSION['cart'][] = [
            'product_id' => $product_id, // FIXED: Changed key to 'product_id'
            'name' => $product['name'],
            'price' => ($product['offer_price'] > 0) ? $product['offer_price'] : $product['price'],
            'quantity' => 1,
            'image_url' => $product['image_url']
        ];
    }

    // Calculate new cart count
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

    echo json_encode([
        'success' => true,
        'cart_count' => $cart_count
    ]);
} catch (Exception $e) {
    // Return detailed error message
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
exit;
?>