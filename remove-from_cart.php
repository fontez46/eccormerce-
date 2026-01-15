<?php
session_start();

// Set JSON response header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize the index
    $index = isset($_POST['index']) ? (int)$_POST['index'] : null;

    if ($index !== null && isset($_SESSION['cart'])) {
        // Check if index exists
        if (isset($_SESSION['cart'][$index])) {
            // Remove the item from the cart
            unset($_SESSION['cart'][$index]);

            // Re-index the cart array to avoid gaps
            $_SESSION['cart'] = array_values($_SESSION['cart']);

            // Calculate new cart quantity
            $cart_quantity = 0;
            if (!empty($_SESSION['cart'])) {
                $cart_quantity = array_sum(array_column($_SESSION['cart'], 'quantity'));
            }

            // Return success response with consistent format
            echo json_encode([
                'success' => true,
                'cart_count' => $cart_quantity,
                'message' => 'Item removed successfully'
            ]);
            exit;
        }
    }
    
    // Return error response with consistent format
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit;
}

// Return invalid method response
echo json_encode([
    'success' => false,
    'message' => 'Invalid method'
]);
exit;
?>