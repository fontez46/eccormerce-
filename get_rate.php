<?php
session_start();
require 'includes/config.php';

$town = $_GET['town'];
$rate = 0;

$sql = "SELECT rate FROM shipping_rates WHERE town = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $town);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $rate = $row['rate'];
}

echo json_encode(['rate' => $rate]);
?>