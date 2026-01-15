<?php
require 'includes/config.php';

$region = $_GET['region'] ?? '';
$towns = [];

if ($region) {
    $stmt = $conn->prepare("SELECT town FROM shipping_rates WHERE region = ? ORDER BY town");
    $stmt->bind_param("s", $region);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $towns[] = $row['town'];
    }
    
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($towns);
$conn->close();