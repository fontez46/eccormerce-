<?php
session_start();
header('Content-Type: application/json');

// Debugging logs to see if the session is working
error_log("Cart Session Data: " . json_encode($_SESSION['cart']));

$totalQuantity = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;

echo json_encode(['cart_quantity' => $totalQuantity]);
?>
