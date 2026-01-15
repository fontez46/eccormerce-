<?php
session_start();
require 'includes/config.php';

if (!isset($_SESSION['pending_verification']) || !$_SESSION['pending_verification']) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ? AND is_verified = FALSE");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No pending verification found.");
}

$user = $result->fetch_assoc();
$email = $user['email'];

// Generate a new code
$verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$code_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// Update the user's record
$stmt = $conn->prepare("UPDATE users SET verification_code = ?, code_expires_at = ? WHERE user_id = ?");
$stmt->bind_param('ssi', $verification_code, $code_expires_at, $user_id);
$stmt->execute();

$verification_link = "https://yourdomain.com/verify-code.php?user_id=$user_id&code=$verification_code";

// Send the email
$email_body = "
    <h2>Welcome to Joemakit!</h2>
    <p>Your new verification code is: <strong>$verification_code</strong></p>
    <p>Please enter this code on the verification page to complete your registration.</p>
        <p>This code will expire in 15 minutes.</p>
";
sendEmail($email, "Verify Your Email Address", $email_body);

header('Location: verify-code.php');
exit;
?>