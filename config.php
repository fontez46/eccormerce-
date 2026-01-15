<?php
$servername = "localhost";
$username = "root";
$password = 
$dbname = "eccormerce";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    exit("Internal server error. Please try again later.");
}

// Email configuration (for PHPMailer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verify autoloader exists
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    error_log('PHPMailer autoloader not found at: ' . $autoloadPath);
    exit('Email functionality is unavailable. Please contact the administrator.');
}

require_once $autoloadPath;

function sendEmail($to, $subject, $body, $alt_body = null) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'josephngangamunyui@gmail.com';
        $mail->Password = ''; // Ensure this is a valid Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Debugging settings
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer[$level]: $str");
        };

        // Recipients
        $mail->setFrom('no-reply@joemakeit.com', 'Joemakeit');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $alt_body ?: strip_tags($body);

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Generate a secure token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

?>
