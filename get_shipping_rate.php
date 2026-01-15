<?php
require 'includes/config.php';

$region = $_GET['region'] ?? '';
$town = $_GET['town'] ?? '';
$rate = 0;

if ($region && $town) {
    $stmt = $conn->prepare("SELECT rate FROM shipping_rates WHERE region = ? AND town = ?");
    $stmt->bind_param("ss", $region, $town);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $rate = (float)$row['rate'];
    }
    
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode(['rate' => $rate]);
$conn->close();