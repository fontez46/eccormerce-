<?php
session_start([
    'cookie_httponly' => 1,
    'cookie_secure' => isset($_SERVER['HTTPS'])
]);

header('Content-Type: application/json');

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
?>