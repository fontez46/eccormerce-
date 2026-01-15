<?php
session_start();
require 'includes/config.php';

$shipping_rates = [
    'nairobi_cbd' => 200,
    'nairobi_suburbs' => 350,
    'mombasa' => 500,
    'kisumu' => 450,
    'other' => 600
];

$selected_location = $_POST['region'] ?? '';
$_SESSION['delivery_location'] = $selected_location;

$subtotal = 0;
$shipping_fee = isset($shipping_rates[$selected_location]) ? $shipping_rates[$selected_location] : 0;
$referral_discount = isset($_SESSION['referral_code']) ? 0.10 * $subtotal : 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
    }
}

$total = $subtotal + $shipping_fee - $referral_discount;

echo json_encode([
    'shipping_fee' => number_format($shipping_fee, 2),
    'total' => number_format($total, 2)
]);
?>
