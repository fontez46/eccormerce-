<?php
session_start();
require 'includes/config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    } else {
        header("Location: login.php");
        exit();
    }
}

// Verify CSRF token for both AJAX and form submissions
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    } else {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: profile.php#addresses");
        exit();
    }
}

// Get form data
$user_id = $_SESSION['user_id'];
$address_id = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
$full_name = trim($_POST['Full_Name']);
$phone = trim($_POST['phone_number']);
$address_line1 = trim($_POST['address_line1']);
$address_line2 = isset($_POST['address_line2']) ? trim($_POST['address_line2']) : '';
$county = trim($_POST['county']);
$constituency = trim($_POST['constituency']);
$town = trim($_POST['Town']);
$postal_code = trim($_POST['postal_code']);
$is_default = isset($_POST['is_default']) ? 1 : 0;

// Validate required fields
$required = ['Full_Name', 'phone_number', 'address_line1', 'county', 'constituency', 'Town', 'postal_code'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => "Please fill in all required fields"]);
            exit;
        } else {
            $_SESSION['error'] = "Please fill in all required fields";
            header("Location: profile.php#addresses");
            exit();
        }
    }
}

try {
    // If setting as default, reset other defaults
    if ($is_default) {
        $reset_stmt = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
        $reset_stmt->bind_param("i", $user_id);
        $reset_stmt->execute();
        $reset_stmt->close();
    }

    if ($address_id > 0) {
        // Update existing address - first verify it belongs to user
        $check_stmt = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $address_id, $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("
                UPDATE addresses SET 
                    Full_Name = ?, 
                    phone_number = ?, 
                    address_line1 = ?, 
                    address_line2 = ?, 
                    county = ?, 
                    constituency = ?, 
                    Town = ?, 
                    postal_code = ?, 
                    is_default = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ssssssssii",
                $full_name, $phone, $address_line1, $address_line2, 
                $county, $constituency, $town, $postal_code, 
                $is_default, $address_id
            );
        } else {
            throw new Exception("Address not found or doesn't belong to user");
        }
        $check_stmt->close();
    } else {
        // Insert new address
        $stmt = $conn->prepare("
            INSERT INTO addresses (
                user_id, Full_Name, phone_number, address_line1, address_line2, 
                county, constituency, Town, postal_code, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "issssssssi",
            $user_id, $full_name, $phone, $address_line1, $address_line2,
            $county, $constituency, $town, $postal_code, $is_default
        );
    }

    if ($stmt->execute()) {
        $new_address_id = $address_id ?: $stmt->insert_id;
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode([
                'success' => true, 
                'address_id' => $new_address_id,
                'message' => 'Address saved successfully'
            ]);
        } else {
            $_SESSION['success'] = "Address saved successfully";
            header("Location: profile.php#addresses");
        }
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        $_SESSION['error'] = $e->getMessage();
        header("Location: profile.php#addresses");
    }
    exit();
}