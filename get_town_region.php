<?php
require 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_GET['town'])) {
    echo json_encode(['error' => 'Town not specified']);
    exit;
}

$town = $conn->real_escape_string($_GET['town']);

$query = "SELECT r.region_id 
          FROM shipping_towns t
          JOIN shipping_regions r ON t.region_id = r.region_id
          WHERE t.town_name = '$town' LIMIT 1";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(['error' => 'Region not found for town']);
}
?>