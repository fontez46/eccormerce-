<?php
session_start();
require 'includes/config.php';

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production, log errors instead
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log'); // Specify a log file

// Set JSON response header
header('Content-Type: application/json');

try {
    // CSRF Token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid CSRF token');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Sanitize and validate input
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT);
    $quantities = filter_input(INPUT_POST, 'quantity', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    $price_modifiers = filter_input(INPUT_POST, 'price_modifier', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

    if (!$product_id) {
        throw new Exception('Invalid product ID');
    }

    if (!$quantities || !$price_modifiers) {
        throw new Exception('No variations selected');
    }

    // Fetch product details including offer price
    $stmt = $conn->prepare("SELECT name, price, offer_price, image_url FROM products WHERE product_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $product_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result->num_rows) {
        throw new Exception('Product not found');
    }

    $product = $result->fetch_assoc();
    $stmt->close();

    // Determine base price
    $base_price = ($product['offer_price'] > 0) ? (float)$product['offer_price'] : (float)$product['price'];

    // Initialize cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Process each attribute type and value
    $added_items = 0;
    $error_messages = [];

    foreach ($quantities as $attribute_type => $values) {
        if (!is_array($values)) {
            $error_messages[] = "Invalid data for attribute type: $attribute_type";
            continue;
        }

        foreach ($values as $value => $quantity) {
            $quantity = (int)$quantity;
            if ($quantity < 1) continue;

            // Sanitize attribute type and value
            $attribute_type = filter_var($attribute_type, FILTER_SANITIZE_STRING);
            $value = filter_var($value, FILTER_SANITIZE_STRING);

            // Verify attribute exists
            $stmt = $conn->prepare("SELECT stock, price_modifier FROM product_attributes 
                                   WHERE product_id = ? 
                                   AND attribute_type = ? 
                                   AND value = ?");
            if (!$stmt) {
                $error_messages[] = "Prepare failed for attribute $attribute_type: $value";
                continue;
            }

            $stmt->bind_param("iss", $product_id, $attribute_type, $value);
            if (!$stmt->execute()) {
                $error_messages[] = "Execute failed for attribute $attribute_type: $value";
                $stmt->close();
                continue;
            }

            $result = $stmt->get_result();
            if (!$result->num_rows) {
                $error_messages[] = "Attribute $attribute_type: $value not found";
                $stmt->close();
                continue;
            }

            $attr_data = $result->fetch_assoc();
            $stmt->close();

            // Check stock
            if ($attr_data['stock'] < $quantity) {
                $error_messages[] = "Insufficient stock for $attribute_type: $value. Only {$attr_data['stock']} available.";
                continue;
            }

            // Get price modifier
            $price_modifier = isset($price_modifiers[$attribute_type][$value])
                ? (float)$price_modifiers[$attribute_type][$value]
                : 0.0;

            // Calculate final price
            $item_price = $base_price + $price_modifier;

            // Create unique key
            $cart_key = $product_id . '_' . $attribute_type . '_' . $value;

            // Check if item exists in cart
            $item_exists = false;
            foreach ($_SESSION['cart'] as &$cart_item) {
                if ($cart_item['key'] === $cart_key) {
                    $new_quantity = $cart_item['quantity'] + $quantity;

                    // Check stock again
                    if ($new_quantity > $attr_data['stock']) {
                        $error_messages[] = "Cannot add $quantity more of $attribute_type: $value. Only " .
                                            ($attr_data['stock'] - $cart_item['quantity']) . " available.";
                        continue;
                    }

                    $cart_item['quantity'] = $new_quantity;
                    $item_exists = true;
                    $added_items++;
                    break;
                }
            }
            unset($cart_item); // Break reference

            // Add new item if needed
            if (!$item_exists) {
                $_SESSION['cart'][] = [
                    'key' => $cart_key,
                    'product_id' => $product_id,
                    'name' => $product['name'],
                    'price' => $item_price,
                    'image_url' => $product['image_url'],
                    'attribute_type' => $attribute_type,
                    'value' => $value,
                    'quantity' => $quantity
                ];
                $added_items++;
            }
        }
    }

    // Calculate total cart quantity
    $total_cart_quantity = array_sum(array_column($_SESSION['cart'], 'quantity'));

    // Prepare response
    $response = [
        'status' => $added_items > 0 ? 'success' : 'error',
        'cart_quantity' => $total_cart_quantity,
        'added_items' => $added_items,
        'errors' => $error_messages
    ];

    if ($added_items > 0) {
        $response['message'] = 'Item(s) added to cart successfully.';
    } else {
        $response['message'] = implode("; ", $error_messages) ?: 'No items could be added to the cart.';
    }

    // Ensure proper JSON encoding
    header('HTTP/1.1 200 OK');
    echo json_encode($response, JSON_THROW_ON_ERROR);
} catch (Exception $e) {
    // Log error for debugging
    error_log("Add-to-cart error: " . $e->getMessage());

    // Return detailed error
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'errors' => [],
        'cart_quantity' => isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0
    ], JSON_THROW_ON_ERROR);
}
exit;
?>