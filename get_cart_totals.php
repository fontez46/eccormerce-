<?php
session_start();

$subtotal = 0;
$tax = 0;
$shipping = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $price = (float)($item['price'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 0);
        $tax_rate = (float)($item['tax_rate'] ?? 0);
        $itemSubtotal = $price * $quantity;
        $subtotal += $itemSubtotal;
        $tax += $itemSubtotal * $tax_rate;
    }
}

$shipping_rates = include 'shipping_rates.php';
$selected_location = $_SESSION['delivery_location'] ?? '';
$shipping = $shipping_rates[$selected_location] ?? 0;

$total = $subtotal + $tax + $shipping;

echo json_encode([
    'success' => true,
    'subtotal' => number_format($subtotal, 2),
    'tax' => number_format($tax, 2),
    'shipping' => number_format($shipping, 2),
    'total' => number_format($total, 2)
]);