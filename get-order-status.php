<?php
// get-order-status.php
require 'includes/config.php';

$order_id = $_GET['order_id'] ?? 0;

$response = ['status' => 'pending'];

if ($order_id) {
    $stmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        $response['status'] = $order['status'];
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($response);
?>