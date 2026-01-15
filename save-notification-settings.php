<?php
session_start();
require 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_updates = isset($_POST['order_updates']) ? 1 : 0;
$promotions = isset($_POST['promotions']) ? 1 : 0;
$newsletter = isset($_POST['newsletter']) ? 1 : 0;

try {
    // Check if settings exist
    $check_stmt = $conn->prepare("SELECT user_id FROM notification_settings WHERE user_id = ?");
    $check_stmt->bind_param('i', $user_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;

    if ($exists) {
        // Update existing settings
        $stmt = $conn->prepare("
            UPDATE notification_settings 
            SET order_updates = ?, promotions = ?, newsletter = ? 
            WHERE user_id = ?
        ");
        $stmt->bind_param('iiii', $order_updates, $promotions, $newsletter, $user_id);
    } else {
        // Insert new settings
        $stmt = $conn->prepare("
            INSERT INTO notification_settings (user_id, order_updates, promotions, newsletter)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('iiii', $user_id, $order_updates, $promotions, $newsletter);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to save settings']);
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>