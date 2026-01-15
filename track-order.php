<?php
// Start session with secure settings
session_start([
    'cookie_httponly' => 1,
    'cookie_secure' => isset($_SERVER['HTTPS'])
]);

// Include configuration file
require 'includes/config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$order = [];
$tracking_info = [];
$recent_orders = [];
$error = '';
$success = '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if we're viewing a specific order or order list
$viewing_specific_order = false;

// Validate order_id if provided
if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    $viewing_specific_order = true;
    
    // Fetch order details
    $stmt = $conn->prepare("
        SELECT o.order_id, o.created_at AS order_date, o.total_amount, o.status, o.payment_method, 
               a.county AS delivery_location, COUNT(oi.order_item_id) AS item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN addresses a ON o.address_id = a.id
        WHERE o.order_id = ? AND o.user_id = ?
        GROUP BY o.order_id
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        
        // Calculate progress based on status
        $progress = 0;
        $progress_label = 'Order Placed';
        switch ($order['status']) {
            case 'Pending':
                $progress = 25;
                $progress_label = 'Payment Processing';
                break;
            case 'Processing':
                $progress = 50;
                $progress_label = 'Preparing for Shipment';
                break;
            case 'Shipped':
                $progress = 75;
                $progress_label = 'In Transit';
                break;
            case 'Delivered':
                $progress = 100;
                $progress_label = 'Delivered';
                break;
            case 'Cancelled':
                $progress = 100;
                $progress_label = 'Cancelled';
                break;
        }
        $order['progress'] = $progress;
        $order['progress_label'] = $progress_label;
    } else {
        $error = "Order not found or you don't have permission to view it";
    }
    $stmt->close();

    // Fetch tracking information
    if (empty($error)) {
        $track_stmt = $conn->prepare("
            SELECT status, location, notes, created_at
            FROM order_tracking
            WHERE order_id = ?
            ORDER BY created_at DESC
        ");
        $track_stmt->bind_param("i", $order_id);
        $track_stmt->execute();
        $track_result = $track_stmt->get_result();
        $tracking_info = $track_result->fetch_all(MYSQLI_ASSOC);
        $track_stmt->close();
    }
} 
// If no order ID provided, fetch recent orders
else {
    // Fetch recent orders
    $stmt = $conn->prepare("
        SELECT order_id, created_at AS order_date, total_amount, status, 
               COUNT(order_item_id) AS item_count 
        FROM orders
        LEFT JOIN order_items USING (order_id)
        WHERE user_id = ?
        GROUP BY order_id
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order #<?= htmlspecialchars($order['order_id'] ?? '') ?> - JOEMAKEIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            
            --danger: #ef476f;
            --warning: #ffd166;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --primary: #1e6b52;
            --primary-light: #2d9b78;
             --secondary: #f39c12;
             --success: #10b981;
            --accent: #e74c3c;
            --light: #f8f9fa;
            --dark: #1e293b;
            --gray: #6c757d;
            --light-gray: #e2e8f0;
            --radius: 12px;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
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
            padding-bottom: 60px;
        }

        .kenyan-pattern {
             background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--dark);
            min-height: 100vh;
            padding-bottom: 2rem;}

                /* Header Styles */
                .profile-header {
                    background: linear-gradient(90deg, #6e8efb, #a777e3); 
            color: white;
            padding: 2rem;
            border-radius: 0 0 var(--radius) var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
                }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            z-index: 2;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border: 3px solid white;
                }

        .user-details h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .user-details p {
            color: var(--gray);
            font-size: 0.9rem;
        }

            .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

                .btn-primary {
            background: var(--secondary);
            color: white;
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
        }

        .btn-primary:hover {
            background: #e67e22;
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

        /* Main Container */
        .profile-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .profile-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .section-title {
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--light-gray);
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
        }

        /* Order Summary Card */
        .order-summary-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .order-id {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
        }

        .order-date {
            color: var(--gray);
            font-size: 0.95rem;
        }

        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .detail-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .detail-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .detail-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--dark);
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-pending { background: rgba(241, 196, 15, 0.15); color: #f39c12; }
        .status-processing { background: rgba(52, 152, 219, 0.15); color: #3498db; }
        .status-shipped { background: rgba(46, 204, 113, 0.15); color: #27ae60; }
        .status-delivered { background: rgba(46, 204, 113, 0.3); color: #16a085; font-weight: bold; }
        .status-cancelled { background: rgba(231, 76, 60, 0.15); color: #c0392b; }

        /* Progress Bar */
        .progress-container {
            margin: 2rem 0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .progress-title {
            font-weight: 600;
            color: var(--dark);
        }

        .progress-bar {
            height: 10px;
            background: var(--light-gray);
            border-radius: 5px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: var(--success);
            border-radius: 5px;
            transition: width 0.5s ease;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            position: relative;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            width: 25%;
        }
        
        .step-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        
        .step-label {
            font-size: 0.75rem;
            text-align: center;
            color: var(--gray);
            max-width: 80px;
        }
        
        .step-completed .step-icon {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        
        .step-active .step-icon {
            border-color: var(--success);
            background: var(--success);
            color: white;
            transform: scale(1.1);
        }
        
        .step-active .step-label {
            color: var(--dark);
            font-weight: 500;
        }

        /* Timeline */
        .tracking-section {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
        }

        .tracking-timeline {
            position: relative;
            padding: 1rem 0;
            margin: 1rem 0;
        }

        .tracking-timeline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 24px;
            height: 100%;
            width: 3px;
            background: var(--light-gray);
        }

        .tracking-item {
            position: relative;
            margin-bottom: 2rem;
            padding-left: 3.5rem;
        }

        .tracking-item:last-child {
            margin-bottom: 0;
        }

        .tracking-icon {
            position: absolute;
            left: 12px;
            top: 0;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        .tracking-item.active .tracking-icon {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .tracking-item.active .tracking-status {
            color: var(--primary);
        }

        .tracking-date {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }

        .tracking-status {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }

        .tracking-location {
            color: var(--gray);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0.5rem;
        }

        .tracking-notes {
            color: var(--dark);
            font-size: 0.95rem;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }

        /* No tracking info */
        .no-tracking {
            text-align: center;
            padding: 2.5rem 1rem;
        }

        .no-tracking i {
            font-size: 3.5rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }

        .no-tracking h3 {
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .no-tracking p {
            color: var(--gray);
            max-width: 500px;
            margin: 0 auto;
        }

        /* Footer */
        .profile-footer {
    max-width: 1400px;
    margin: 3rem auto 0;
    padding: 2rem;
    text-align: center;
    color: var(--gray);
    font-size: 0.9rem;
    border-top: 1px solid var(--light-gray);
}
        .profile-footer p {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

      kenyan-flag {
    height: 20px;
    width: 30px;
    background: linear-gradient(180deg, 
        #000 0%, #000 33.33%, 
        #fff 33.33%, #fff 66.66%, 
        #006600 66.66%, #006600 100%);
    border: 1px solid #ddd;
    display: inline-block;
    vertical-align: middle;
    margin-right: 8px;
    border-radius: 2px;
}

.kenyan-pattern {
    background-image: 
        radial-gradient(circle at 10% 20%, rgba(30, 107, 82, 0.1) 0%, transparent 20%),
        radial-gradient(circle at 90% 80%, rgba(243, 156, 18, 0.1) 0%, transparent 20%);
}


        /* Messages */
        .message {
            position: fixed;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 2rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 1000;
            opacity: 0;
            transition: all 0.5s ease;
            max-width: 90%;
            text-align: center;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message {
            background: var(--danger);
        }

        .success-message {
            background: var(--success);
        }
        /* Add to your existing CSS */
.order-selection-container {
    background: white;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    padding: 2rem;
    margin-top: 2rem;
}

.orders-grid {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    margin: 2rem 0;
}

.order-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    padding: 1.5rem;
    transition: transform 0.3s ease;
}

.order-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.no-orders {
    text-align: center;
    padding: 3rem;
}

.no-orders i {
    font-size: 3rem;
    color: #ddd;
    margin-bottom: 1rem;
}

.order-actions {
    margin-top: 1.5rem;
    display: flex;
    justify-content: center;
}
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .header-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .progress-steps {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .progress-step {
                width: 45%;
            }
        }
    </style>
</head>
<body class="kenyan-pattern">
    <!-- Display messages -->
    <?php if ($error): ?>
        <div class="message error-message" id="errorMessage">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="message success-message" id="successMessage">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <header class="profile-header">
        <div class="header-content">
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <h1>Track Your Order</h1>
                    <p>Real-time updates on your purchase</p>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="location.href='profile.php#orders'">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </button>
            </div>
        </div>
    </header>

    <div class="profile-container">
        <main class="profile-content fade-in">
            <?php if ($viewing_specific_order): ?>
                <?php if (!empty($order)): ?>
                <section>
                    <div class="order-summary-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?= htmlspecialchars($order['order_id']) ?></div>
                                <div class="order-date">Placed on <?= date('F d, Y', strtotime($order['order_date'])) ?></div>
                            </div>
                            <div class="status-badge status-<?= strtolower($order['status']) ?>">
                                <?= htmlspecialchars($order['status']) ?>
                            </div>
                        </div>
                        
                        <div class="order-details-grid">
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-money-bill-wave"></i> Total Amount
                                </div>
                                <div class="detail-value">Ksh <?= number_format($order['total_amount'], 2) ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-credit-card"></i> Payment Method
                                </div>
                                <div class="detail-value"><?= htmlspecialchars($order['payment_method']) ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-map-marker-alt"></i> Delivery Location
                                </div>
                                <div class="detail-value"><?= htmlspecialchars($order['delivery_location'] ?? 'Not specified') ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-box-open"></i> Items
                                </div>
                                <div class="detail-value"><?= $order['item_count'] ?> products</div>
                            </div>
                        </div>
                        
                        <div class="progress-container">
                            <div class="progress-header">
                                <div class="progress-title">Delivery Progress</div>
                                <div><?= $order['progress'] ?>% Complete</div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $order['progress'] ?>%;"></div>
                            </div>
                            
                            <div class="progress-steps">
                                <div class="progress-step <?= $order['progress'] >= 25 ? 'step-completed' : '' ?> <?= $order['progress_label'] == 'Payment Processing' ? 'step-active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <div class="step-label">Order Placed</div>
                                </div>
                                
                                <div class="progress-step <?= $order['progress'] >= 50 ? 'step-completed' : '' ?> <?= $order['progress_label'] == 'Preparing for Shipment' ? 'step-active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                    <div class="step-label">Processing</div>
                                </div>
                                
                                <div class="progress-step <?= $order['progress'] >= 75 ? 'step-completed' : '' ?> <?= $order['progress_label'] == 'In Transit' ? 'step-active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-shipping-fast"></i>
                                    </div>
                                    <div class="step-label">Shipped</div>
                                </div>
                                
                                <div class="progress-step <?= $order['progress'] >= 100 ? 'step-completed' : '' ?> <?= $order['progress_label'] == 'Delivered' ? 'step-active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="step-label">Delivered</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tracking-section">
                        <h2 class="section-title">
                            <i class="fas fa-shipping-fast"></i> Tracking History
                        </h2>
                        
                        <?php if (!empty($tracking_info)): ?>
                            <div class="tracking-timeline">
                                <?php 
                                    // Set the first item as active
                                    $tracking_info[0]['active'] = true;
                                ?>
                                <?php foreach ($tracking_info as $index => $track): ?>
                                    <div class="tracking-item <?= $index === 0 ? 'active' : '' ?>">
                                        <div class="tracking-icon">
                                            <i class="fas <?= 
                                                $track['status'] === 'Shipped' ? 'fa-truck' : 
                                                ($track['status'] === 'Delivered' ? 'fa-check' : 'fa-box') 
                                            ?>"></i>
                                        </div>
                                        <div class="tracking-date"><?= date('M d, Y h:i A', strtotime($track['created_at'])) ?></div>
                                        <div class="tracking-status"><?= htmlspecialchars($track['status']) ?></div>
                                        <div class="tracking-location">
                                            <i class="fas fa-map-pin"></i> 
                                            <?= htmlspecialchars($track['location'] ?? 'N/A') ?>
                                        </div>
                                        <?php if (!empty($track['notes'])): ?>
                                            <div class="tracking-notes">
                                                <i class="fas fa-info-circle"></i> <?= htmlspecialchars($track['notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-tracking">
                                <i class="fas fa-map-marker-alt"></i>
                                <h3>No tracking information available</h3>
                                <p>Tracking details will be updated once your order is processed. We'll notify you when your order ships.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php else: ?>
                    <div class="no-tracking" style="margin-top: 3rem;">
                        <i class="fas fa-exclamation-circle"></i>
                        <h3>Order Not Found</h3>
                        <p>The order you are trying to track does not exist or you don't have permission to view it.</p>
                        <button class="btn btn-outline" style="margin-top: 1.5rem;" onclick="location.href='profile.php#orders'">
                            <i class="fas fa-arrow-left"></i> View Your Orders
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- ORDER SELECTION INTERFACE -->
                <section>
                    <h2 class="section-title">
                        <i class="fas fa-truck"></i> Track Your Order
                    </h2>
                    
                    <div class="order-selection-container">
                        <p>Select an order to track its delivery progress</p>
                        
                        <?php if (!empty($recent_orders)): ?>
                            <div class="orders-grid">
                                <?php foreach ($recent_orders as $order): ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div>
                                            <div class="order-id">Order #<?= $order['order_id'] ?></div>
                                            <div class="order-date"><?= date('M d, Y', strtotime($order['order_date'])) ?></div>
                                        </div>
                                        <div class="order-status status-<?= strtolower($order['status']) ?>">
                                            <?= $order['status'] ?>
                                        </div>
                                    </div>
                                    
                                    <div class="order-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Total Amount</span>
                                            <span class="detail-value">Ksh <?= number_format($order['total_amount'], 2) ?></span>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <span class="detail-label">Items</span>
                                            <span class="detail-value"><?= $order['item_count'] ?> products</span>
                                        </div>
                                    </div>
                                    
                                    <div class="order-actions">
                                        <a href="track-order.php?order_id=<?= $order['order_id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-truck"></i> Track This Order
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-orders">
                                <i class="fas fa-shopping-bag"></i>
                                <h3>No orders found</h3>
                                <p>You haven't placed any orders yet</p>
                                <a href="hop.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-shopping-cart"></i> Shop Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <footer class="profile-footer">
        <p>© <?= date('Y') ?> JOEMAKEIT. All rights reserved. <span class="kenyan-flag"></span></p>
        <p class="text-muted">Need help? Contact our support team at support@Joemakeit.co.ke</p>
    </footer>

    <script>
        // Fade messages
        const messages = document.querySelectorAll('.message');
        if (messages.length > 0) {
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '1';
                    message.style.top = '30px';
                }, 100);
                
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 300);
                }, 5000);
            });
        }
        
        // Add subtle animations to timeline
        document.querySelectorAll('.tracking-item').forEach((item, index) => {
            setTimeout(() => {
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, 200 * index);
        });
    </script>
</body>
</html>