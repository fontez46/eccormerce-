<?php
require 'includes/config.php';

header('Content-Type: application/json');

$region = $_POST['region'] ?? '';
$town = $_POST['town'] ?? '';
$shipping_fee = 0;

if ($region && $town) {
    $stmt = $conn->prepare("SELECT rate FROM shipping_rates WHERE region = ? AND town = ?");
    $stmt->bind_param("ss", $region, $town);
    $stmt->execute();
    $result = $stmt->get_result();
         if ($row = $result->fetch_assoc()) {
        $shipping_fee = (float)$row['rate'];
    }
    
    $stmt->close();
}

echo json_encode(['shipping_fee' => $shipping_fee]);
$conn->close();