<?php
require_once __DIR__ . '/config.php';
OB_START();

// Start session with strict security settings
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => 1,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => 1
    ]);
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$error = '';
$success = '';
$current_page = isset($_GET['page']) ? $_GET['page'] : 'orders';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Verify CSRF token for login
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token validation failed";
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        // Validate inputs
        if (empty($username) || empty($password)) {
            $error = "Username and password are required";
        } else {
            $stmt = $conn->prepare("SELECT user_id, password_hash, is_admin FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Verify password with secure method
                if (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    $_SESSION['username'] = $username;
                    
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    
                    header("Location: ".$_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $error = "Invalid username or password";
                    sleep(2); // Prevent brute force
                }
            } else {
                $error = "Invalid username or password";
                sleep(2); // Prevent brute force
            }
            $stmt->close();
        }
    }
}

// Handle logout via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token validation failed";
    } else {
        // Destroy session completely
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', time() - 42000, '/');
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - JOEMAKEIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #06d6a0;
            --danger: #ef476f;
            --warning: #ffd166;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #94a3b8;
            --light-gray: #e2e8f0;
            --success: #10b981;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            /* Chat specific variables */
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f5f8fa;
            --light-border: #e1e8ed;
            --card-bg: #ffffff;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --radius: 10px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--dark);
            min-height: 100vh;
        }

        .kenyan-pattern {
            background-image: 
                linear-gradient(45deg, #e4edf5 25%, transparent 25%, transparent 75%, #e4edf5 75%),
                linear-gradient(45deg, #e4edf5 25%, #f5f7fa 25%, #f5f7fa 75%, #e4edf5 75%);
            background-size: 40px 40px;
            background-position: 0 0, 20px 20px;
        }

        /* Login Form */
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 2.5rem;
            max-width: 500px;
            margin: 2rem auto;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, #000, #ff0000, #006600);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        #header.logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            letter-spacing: -0.5px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        #header.logo-text span {
            color: var(--primary);
        }

        .login-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .login-subtitle {
            color: var(--gray);
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 45px;
            cursor: pointer;
            color: var(--gray);
        }

        .btn {
            padding: 1rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 1.1rem;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .btn-block {
            width: 100%;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #d12d55;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #0da271;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            margin-bottom: 1.5rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-message {
            background: var(--danger);
        }

        .success-message {
            background: var(--success);
        }

        /* Admin tracking styles */
        .admin-header {
            background: white;
            box-shadow: var(--card-shadow);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            margin-bottom: 2rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .admin-actions {
            display: flex;
            gap: 15px;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Dashboard styles */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 1rem;
        }

        .primary-stat {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .success-stat {
            background: linear-gradient(135deg, var(--success) 0%, #0da271 100%);
            color: white;
        }

        .warning-stat {
            background: linear-gradient(135deg, var(--warning) 0%, #f9c74f 100%);
            color: var(--dark);
        }

        /* Chart container */
        .chart-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            height: 300px;
        }

        /* Search Form */
        .search-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .form-inline-group {
            flex: 1;
            min-width: 200px;
        }

        /* Orders Table */
        .orders-container, .products-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .table th {
            text-align: left;
            padding: 1rem;
            background: #f8fafc;
            color: var(--gray);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--light-gray);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            min-width: 100px;
        }

        .status-pending { background: rgba(241, 196, 15, 0.15); color: #f39c12; }
        .status-processing { background: rgba(52, 152, 219, 0.15); color: #3498db; }
        .status-shipped { background: rgba(46, 204, 113, 0.15); color: #27ae60; }
        .status-delivered { background: rgba(46, 204, 113, 0.3); color: #16a085; font-weight: bold; }
        .status-cancelled { background: rgba(231, 76, 60, 0.15); color: #c0392b; }

        /* Order Details */
        .order-detail-container, .product-form-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .order-header, .product-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--light-gray);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-id, .product-id {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .order-date {
            color: var(--gray);
            font-size: 1rem;
        }

        .order-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 900px) {
            .order-grid {
                grid-template-columns: 1fr;
            }
        }

        .order-section {
            margin-bottom: 1.5rem;
        }

        .order-section-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .order-section-content {
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 8px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }

        .detail-row {
            display: flex;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
            flex-wrap: wrap;
        }

        .detail-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            width: 150px;
            font-size: 0.95rem;
            color: var(--gray);
            font-weight: 500;
        }

        .detail-value {
            flex: 1;
            font-weight: 500;
            color: var(--dark);
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding: 2rem 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 30px;
            height: 100%;
            width: 4px;
            background: var(--light-gray);
            z-index: 1;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2.5rem;
            padding-left: 5rem;
            z-index: 2;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-icon {
            position: absolute;
            left: 18px;
            top: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .timeline-item.active .timeline-icon {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .timeline-date {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .timeline-status {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .timeline-location {
            color: var(--gray);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1rem;
        }

        .timeline-notes {
            color: var(--dark);
            font-size: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Tracking Form */
        .tracking-form-container, .product-form-content {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group-full {
            grid-column: span 2;
        }

        @media (max-width: 768px) {
            .form-group-full {
                grid-column: span 1;
            }
        }
        
        /* Product image preview */
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 1rem;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            display: none;
        }
        
        .checkbox-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        /* Print Button */
        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 6px 10px rgba(0,0,0,0.2);
            z-index: 100;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .print-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.3);
        }

        /* Footer */
        .admin-footer {
            background: var(--dark);
            color: white;
            text-align: center;
            padding: 1.5rem;
            margin-top: 3rem;
            border-radius: 0 0 12px 12px;
        }

        .admin-footer p {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .kenyan-flag {
            display: inline-block;
            width: 20px;
            height: 20px;
            background: linear-gradient(to bottom, 
                black 0%, black 33%, 
                red 33%, red 66%, 
                green 66%, green 100%);
            border-radius: 50%;
            margin: 0 5px;
            vertical-align: middle;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                padding: 1.5rem;
            }
            
            .logo {
                flex-direction: column;
                gap: 10px;
            }
            
            .admin-header .header-content {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .admin-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .form-inline-group {
                min-width: 100%;
            }
        }

        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* New sidebar styles */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            padding: 1.5rem 0;
            transition: all 0.3s ease;
            z-index: 1000;
            /* Sticky positioning */
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
                
        .sidebar-logo {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            gap: 12px;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-link i {
            width: 24px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .hamburger {
            display: none;
            background: transparent;
            border: none;
            color: var(--dark);
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 1rem;
        }
        
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        @media (max-width: 900px) {
            .sidebar {
                position: fixed;
                left: -250px;
                height: 100vh;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .hamburger {
                display: block;
            }
            
            .mobile-overlay.active {
                display: block;
            }
        }
        /* Add to your existing styles */
.action-buttons {
    display: flex;
    gap: 10px;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.attributes-container .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

        /* Chat Admin Styles */
        .chat-admin-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            height: calc(100vh - 200px);
            display: flex;
            flex-direction: column;
        }

        .chat-dashboard {
            display: flex;
            gap: 25px;
            height: 100%;
        }

        .chat-panel {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--light-border);
        }

        .chat-panel-header {
            padding: 18px 20px;
            background: var(--secondary-color);
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-user-list-container {
            width: 320px;
        }

        .chat-user-list {
            padding: 15px;
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .chat-user-item {
            padding: 15px;
            border-radius: 8px;
            background: #f9fbfd;
            border: 1px solid var(--light-border);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .chat-user-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }

        .chat-user-item.active {
            background: #e3f2fd;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
            transform: translateY(-2px);
        }

        .chat-user-item.active .user-id {
            color: var(--primary-dark);
            font-weight: 700;
        }

        .chat-user-item .user-id {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chat-user-item .last-message {
            color: #7f8c8d;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 30px;
        }

        .chat-user-item .unread-count {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--danger-color);
            color: white;
            min-width: 24px;
            height: 24px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            padding: 0 6px;
            transition: var(--transition);
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 1px solid var(--light-border);
            background: rgba(255, 255, 255, 0.9);
        }

        .current-user {
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-status {
            font-size: 0.85rem;
            font-weight: normal;
            color: var(--success-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-status::before {
            content: "";
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success-color);
        }

        .chat-actions {
            display: flex;
            gap: 12px;
        }

        .chat-actions button {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid var(--light-border);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            color: var(--secondary-color);
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-actions button:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .chat-messages {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            background: rgba(248, 250, 252, 0.7);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message {
            max-width: 75%;
            padding: 15px;
            border-radius: 18px;
            position: relative;
            animation: fadeIn 0.4s ease;
            transition: var(--transition);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .user-message {
            background: #e3f2fd;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }

        .admin-message {
            background: #d1ecf1;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        .message .sender {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 5px;
            color: var(--secondary-color);
        }

        .message .timestamp {
            font-size: 0.75rem;
            color: #7f8c8d;
            text-align: right;
            margin-top: 8px;
        }

        .no-messages {
            text-align: center;
            color: #95a5a6;
            padding: 40px 20px;
            font-style: italic;
        }

        .chat-input-area {
            padding: 20px;
            border-top: 1px solid var(--light-border);
            background: rgba(255, 255, 255, 0.9);
        }

        .chat-input {
            display: flex;
            gap: 12px;
        }

        #message-input {
            flex: 1;
            padding: 15px 20px;
            border: 1px solid var(--light-border);
            border-radius: 30px;
            font-size: 1rem;
            outline: none;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.8);
        }

        #message-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        #send-btn {
            padding: 0 25px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        #send-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.4);
        }

        #send-btn:active {
            transform: translateY(0);
        }

        #send-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        .connection-status {
            background: rgba(240, 247, 255, 0.9);
            padding: 10px 15px;
            border-radius: 30px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .connection-status.connected {
            color: var(--success-color);
            background: rgba(232, 250, 240, 0.9);
        }

        .connection-status.connecting {
            color: var(--warning-color);
            background: rgba(254, 249, 231, 0.9);
        }

        .connection-status.disconnected {
            color: var(--danger-color);
            background: rgba(253, 237, 236, 0.9);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-left: 4px solid var(--primary-color);
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            transform: translateX(120%);
            transition: transform 0.4s ease;
            z-index: 1000;
        }

        .notification.show {
            transform: translateX(0);
        }

        /* User Details Panel */
        .user-details-panel {
            width: 280px;
        }

        .user-info {
            padding: 15px;
        }

        .user-info-item {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--light-border);
        }

        .user-info-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 4px;
        }

        .user-info-value {
            color: #555;
        }

        .no-user-selected {
            text-align: center;
            padding: 30px 20px;
            color: #95a5a6;
            font-style: italic;
        }

        /* Mobile Responsiveness for Chat */
        @media (max-width: 992px) {
            .chat-dashboard {
                flex-direction: column;
                height: auto;
            }

            .chat-user-list-container {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .chat-dashboard {
                flex-direction: column;
            }
            
            .chat-user-list-container, 
            .chat-container,
            .user-details-panel {
                width: 100% !important;
                max-height: 300px;
            }
            
            .message {
                max-width: 90%;
            }
            
            .chat-user-item .unread-count {
                top: 10px;
                right: 10px;
            }
            
            .chat-input {
                flex-direction: column;
            }
            
            #send-btn {
                width: 100%;
                margin-top: 10px;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* [Rest of your existing styles remain the same...] */
    </style>
</head>
<body class="kenyan-pattern">
    <div class="container">
        <?php if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']): ?>
            <!-- Login Form -->
            <div class="login-container">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="logo-text">JOE<span>MAKEIT</span></div>
                </div>
                
                <h1 class="login-title">Admin Portal</h1>
                <p class="login-subtitle">Please sign in to access the tracking system</p>
                
                <?php if ($error): ?>
                    <div class="message error-message">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Username</label>
                        <input 
                            type="text" 
                            name="username" 
                            class="form-control" 
                            placeholder="Enter your username"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-lock"></i> Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            id="passwordField"
                            class="form-control" 
                            placeholder="Enter your password"
                            required
                        >
                        <span class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Admin Panel Layout -->
            <div class="admin-layout">
                <!-- Sidebar Navigation -->
                <div class="sidebar" id="sidebar">
                    <div class="sidebar-logo">
                        <div class="logo">
                            <div class="logo-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="logo-text">JOE<span>MAKEIT</span></div>
                        </div>
                    </div>
                    
                    <a href="?page=orders" class="nav-link <?= $current_page === 'orders' ? 'active' : '' ?>">
                        <i class="fas fa-truck"></i>
                        <span>Order Tracking</span>
                    </a>
                    
                    <a href="?page=products" class="nav-link <?= $current_page === 'products' ? 'active' : '' ?>">
                        <i class="fas fa-box-open"></i>
                        <span>Product Management</span>
                    </a>
                    
                    <a href="?page=attributes" class="nav-link <?= $current_page === 'attributes' ? 'active' : '' ?>">
                        <i class="fas fa-tags"></i>
                        <span>Manage Attributes</span>
                    </a>
                    
                    <a href="?page=livechat" class="nav-link <?= $current_page === 'livechat' ? 'active' : '' ?>">
                        <i class="fas fa-comments"></i>
                        <span>Live Chat Support</span>
                    </a>
                    
                    <form method="POST" class="nav-link">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="submit" name="logout" style="background: none; border: none; color: inherit; display: flex; align-items: center; gap: 12px; width: 100%;">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
                
                <div class="mobile-overlay" id="mobileOverlay"></div>
                
                <!-- Main Content Area -->
                <div class="main-content">
                    <!-- Admin Header -->
                    <div class="admin-header">
                        <div class="header-content">
                            <button class="hamburger" id="hamburger">
                                <i class="fas fa-bars"></i>
                            </button>
                            <div class="logo">
                                <div class="logo-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="logo-text">JOE<span>MAKEIT</span> Admin</div>
                            </div>
                            <div class="admin-actions">
                                <div style="margin-right: 1rem; font-weight: 500;">                       
                                    <i class="fas fa-user"></i> 
                                    <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest' ?>
                                </div>
                                <button class="btn btn-outline" onclick="location.href='<?= $_SERVER['PHP_SELF'] ?>'">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Page Content -->
                    <div class="admin-container">
                        <?php if ($current_page === 'orders'): ?>
                            <?php include 'orders.php'; ?>
                        <?php elseif ($current_page === 'products'): ?>
                            <?php include 'products.php'; ?>
                        <?php elseif ($current_page === 'attributes'): ?>
                            <?php include 'attributes.php'; ?>
                        <?php elseif ($current_page === 'livechat'): ?>
                            <div class="chat-admin-container">
                                <div class="notification" id="notification">
                                    <strong>New Message!</strong> <span id="notification-text"></span>
                                </div>

                                <div class="chat-dashboard">
                                    <div class="chat-panel chat-user-list-container">
                                        <div class="chat-panel-header">
                                            <i class="fas fa-users"></i>
                                            Active Users <span id="user-count">(0)</span>
                                        </div>
                                        <div class="chat-user-list" id="user-list">
                                            <div class="no-users">No active users</div>
                                        </div>
                                    </div>

                                    <div class="chat-panel chat-container">
                                        <div class="chat-header">
                                            <div class="current-user" id="chat-header">
                                                <i class="fas fa-user"></i>
                                                <span id="current-user-name">Select a user to start chatting</span>
                                                <span class="user-status" id="user-status"></span>
                                            </div>
                                            <div class="chat-actions">
                                                <button title="Clear chat" id="clear-chat">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <button title="User details" id="user-details">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                                <button title="Close chat" id="close-chat">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="chat-messages" id="chat-messages">
                                            <div class="no-messages">
                                                <p>No conversation selected</p>
                                            </div>
                                        </div>

                                        <div class="chat-input-area">
                                            <div class="chat-input">
                                                <input type="text" id="message-input" placeholder="Type your message here..." disabled>
                                                <button id="send-btn" disabled>
                                                    <i class="fas fa-paper-plane"></i> Send
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- User Details Panel -->
                                    <div class="chat-panel user-details-panel">
                                        <div class="chat-panel-header">
                                            <i class="fas fa-user-circle"></i>
                                            User Details
                                        </div>
                                        <div id="user-details-content">
                                            <div class="no-user-selected">Select a user to view details</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <footer class="admin-footer">
                        <p>© <?= date('Y') ?> JOEMAKEIT. All rights reserved. <span class="kenyan-flag"></span> Proudly Kenyan</p>
                        <p class="text-muted">Administrator Panel - Order Tracking System</p>
                    </footer>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle password visibility
        const passwordField = document.getElementById('passwordField');
        const passwordToggle = document.getElementById('passwordToggle');
        
        if (passwordToggle) {
            passwordToggle.addEventListener('click', function() {
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    passwordToggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    passwordField.type = 'password';
                    passwordToggle.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });
        }

        // Toggle sidebar on mobile
        const sidebar = document.getElementById('sidebar');
        const hamburger = document.getElementById('hamburger');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
        });
        
        mobileOverlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            mobileOverlay.classList.remove('active');
        });

</script>
<?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] && $current_page === 'livechat'): ?>
    <script src="chart.js"></script>
<?php endif; ?>
</body>
<?php ob_end_flush(); ?>
</html>
