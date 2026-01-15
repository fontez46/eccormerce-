<?php
session_start();
require 'includes/config.php';

$response = ['success' => false, 'message' => 'An error occurred'];

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $response['message'] = 'Invalid CSRF token';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Validate required fields
$required_fields = ['full_name', 'phone_number', 'address_line1', 'county', 'town', 'postal_code'];
foreach ($required_fields as $field) {
    if (empty(trim($_POST[$field] ?? ''))) {
        $response['message'] = 'Please fill in all required fields. Missing: ' . $field;
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
// Validate phone number format
$phone_number = trim($_POST['phone_number']);
$phone_number = preg_replace('/\D/', '', $phone_number); // Remove non-digits

// Accepted formats: +254704003130, 254704003130, 0704003130, or 0104003130
if (!preg_match('/^(\+?254\d{9}|07\d{8}|01\d{8})$/', $_POST['phone_number']) && 
    !preg_match('/^(254|07|01)\d{8}$/', $phone_number)) {
    $response['message'] = 'Invalid phone number. Please use format: 0704003130, 0104003130, or +254704003130';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Normalize to 254 format for storage
if (strlen($phone_number) === 10 && (strpos($phone_number, '07') === 0 || strpos($phone_number, '01') === 0)) {
    $phone_number = '254' . substr($phone_number, 1);
}

// Validate postal code format - simplified
$postal_code = trim($_POST['postal_code']);
if (!preg_match('/^[0-9]{3,10}$/', $postal_code)) {
    $response['message'] = 'Postal code must be 3-10 digits';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Sanitize inputs
$full_name = trim($_POST['full_name']);
$address_line1 = trim($_POST['address_line1']);
$address_line2 = trim($_POST['address_line2'] ?? '');
$county = trim($_POST['county']);
$town = trim($_POST['town']);
$postal_code = trim($_POST['postal_code']);
$saved_address_id = $_POST['saved_address_id'] ?? '';

try {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $is_default = 1; // Always set as default

        // First check if user has any existing address
        $check_stmt = $conn->prepare("SELECT id FROM addresses WHERE user_id = ? LIMIT 1");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $existing_address_id = $result->num_rows > 0 ? $result->fetch_assoc()['id'] : null;
        $check_stmt->close();

        // Reset default addresses if setting as default
        $reset_stmt = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
        $reset_stmt->bind_param("i", $user_id);
        $reset_stmt->execute();
        $reset_stmt->close();

        if ($existing_address_id) {
            // Always update the existing address
            $stmt = $conn->prepare("
                UPDATE addresses SET 
                    Full_Name = ?, 
                    phone_number = ?, 
                    address_line1 = ?, 
                    address_line2 = ?, 
                    county = ?, 
                    Town = ?, 
                    postal_code = ?, 
                    is_default = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param(
                "sssssssiii",
                $full_name, $phone_number, $address_line1, $address_line2,
                $county, $town, $postal_code, $is_default,
                $existing_address_id, $user_id
            );
        } else {
            // Create new address only if none exists
            $stmt = $conn->prepare("
                INSERT INTO addresses (
                    user_id, Full_Name, phone_number, address_line1, address_line2,
                    county, Town, postal_code, is_default
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "isssssssi",
                $user_id, $full_name, $phone_number, $address_line1, $address_line2,
                $county, $town, $postal_code, $is_default
            );
        }

        if ($stmt->execute()) {
            $address_id = $existing_address_id ?: $conn->	insert_id;
            
            $_SESSION['delivery_region'] = $county;
            $_SESSION['delivery_town'] = $town;
            $_SESSION['delivery_address'] = [
                'id' => $address_id,
                'full_name' => $full_name,
                'phone_number' => $phone_number,
                'address_line1' => $address_line1,
                'address_line2' => $address_line2,
                'county' => $county,
                'town' => $town,
                'postal_code' => $postal_code
            ];
            
            $response = [
                'success' => true, 
                'message' => 'Address saved successfully',
                'address_id' => $address_id
            ];
        } else {
            throw new Exception('Failed to save address: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        // Guest user - store in session
        $_SESSION['delivery_region'] = $county;
        $_SESSION['delivery_town'] = $town;
        $_SESSION['delivery_address'] = [
            'full_name' => $full_name,
            'phone_number' => $phone_number,
            'address_line1' => $address_line1,
            'address_line2' => $address_line2,
            'county' => $county,
            'town' => $town,
            'postal_code' => $postal_code
        ];
        $response = ['success' => true, 'message' => 'Address stored temporarily'];
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
?>