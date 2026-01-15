<?php
session_start();
header('Content-Type: application/json');
require 'includes/config.php';

// Validate CSRF Token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']));
}

try {
    $index = filter_input(INPUT_POST, 'index', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 999]
    ]);

    if ($index === false || $quantity === false || !isset($_SESSION['cart'][$index])) {
        throw new InvalidArgumentException('Invalid cart operation');
    }

    if ($quantity === 0) {
        // Remove item
        array_splice($_SESSION['cart'], $index, 1);
        $response = [
            'status' => 'success',
            'message' => 'Item removed',
            'removedIndex' => $index
        ];
    } else {
        // Update quantity
        $_SESSION['cart'][$index]['quantity'] = $quantity;
        $response = [
            'status' => 'success',
            'message' => 'Quantity updated',
            'item' => [
                'index' => $index,
                'newQuantity' => $quantity,
                'newSubtotal' => number_format($_SESSION['cart'][$index]['price'] * $quantity, 2)
            ]
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}