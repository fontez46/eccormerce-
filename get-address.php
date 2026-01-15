<?php
session_start();
require 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$address_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$address_id || !is_numeric($address_id)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid address ID']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $address_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Address not found']);
        exit;
    }
    
    $address = $result->fetch_assoc();
    echo json_encode($address);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>