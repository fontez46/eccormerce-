<?php
// Start session with secure settings
session_start([
    'cookie_httponly' => 1,
    'cookie_secure' => isset($_SERVER['HTTPS'])
]);
$active_tab = 'overview';
if (isset($_GET['tab'])) {
    $active_tab = $_GET['tab'];
} elseif (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '#addresses') !== false) {
    $active_tab = 'addresses';
}
// Include configuration file
require 'includes/config.php';

// AJAX endpoint for fetching address
if (isset($_GET['action']) && $_GET['action'] === 'get_address' && isset($_GET['id']) && isset($_GET['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    $address_id = (int)$_GET['id'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $address_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $address = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($address);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Address not found']);
        exit;
    }
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$user = [];
$addresses = [];
$notifications = [];
$orders = [];
$error = '';
$success = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT Full_Name, email, total_orders, last_order_date, join_date FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    $error = "User not found";
}
$stmt->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        if (isset($_POST['save_address'])) {
    $address_id = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
    $full_name = trim($_POST['Full_Name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $county = trim($_POST['county'] ?? '');
    $constituency = trim($_POST['constituency'] ?? '');
    $Town = trim($_POST['Town'] ?? ''); // Fixed this line
    $postal_code = trim($_POST['postal_code'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    // Validate required fields - Changed $ward to $Town here
    if (empty($full_name) || empty($phone) || empty($address_line1) || empty($county) ||
        empty($constituency) || empty($Town) || empty($postal_code)) {
        $error = "Please fill in all required fields";
            } else {
                // Reset previous defaults if setting new default
                if ($is_default) {
                    $reset_stmt = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
                    $reset_stmt->bind_param("i", $user_id);
                    $reset_stmt->execute();
                    $reset_stmt->close();
                }

                if ($address_id > 0) {
                    // Update existing address
                    $check_stmt = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
                    $check_stmt->bind_param("ii", $address_id, $user_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();

                    if ($check_result->num_rows > 0) {
                        $stmt = $conn->prepare("UPDATE addresses SET Full_Name = ?, phone_number = ?, address_line1 = ?, address_line2 = ?, county = ?, constituency = ?, Town = ?, postal_code = ?, is_default = ? WHERE id = ?");
                        $stmt->bind_param("ssssssssii", $full_name, $phone, $address_line1, $address_line2, $county, $constituency, $Town, $postal_code, $is_default, $address_id);

                        if ($stmt->execute()) {
                            $success = "Address updated successfully!";
                        } else {
                            $error = "Error updating address: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = "Address not found or you don't have permission";
                    }
                    $check_stmt->close();
                } else {
                    // Insert new address
                    $stmt = $conn->prepare("INSERT INTO addresses (user_id, Full_Name, phone_number, address_line1, address_line2, county, constituency, Town, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssssssi", $user_id, $full_name, $phone, $address_line1, $address_line2, $county, $constituency, $Town, $postal_code, $is_default);

                    if ($stmt->execute()) {
                        $success = "Address saved successfully!";
                    } else {
                        $error = "Error saving address: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
        // Handle password change
        elseif (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = "All password fields are required";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match";
            } elseif (strlen($new_password) < 8) {
                $error = "Password must be at least 8 characters long";
            } else {
                // Verify current password
                $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                if (password_verify($current_password, $row['password_hash'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $update_stmt->bind_param("si", $hashed_password, $user_id);

                    if ($update_stmt->execute()) {
                        $success = "Password updated successfully!";
                    } else {
                        $error = "Error updating password: " . $update_stmt->error;
                    }
                    $update_stmt->close();
                } else {
                    $error = "Current password is incorrect";
                }
            }
        }
        // Handle address deletion
        elseif (isset($_POST['delete_address'])) {
            $address_id = (int)$_POST['address_id'];

            // Verify address belongs to user
            $check_stmt = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
            $check_stmt->bind_param("ii", $address_id, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $delete_stmt = $conn->prepare("DELETE FROM addresses WHERE id = ?");
                $delete_stmt->bind_param("i", $address_id);

                if ($delete_stmt->execute()) {
                    $success = "Address deleted successfully!";
                } else {
                    $error = "Error deleting address: " . $delete_stmt->error;
                }
                $delete_stmt->close();
            } else {
                $error = "Address not found or you don't have permission";
            }
            $check_stmt->close();
        }
        // Handle profile update
        elseif (isset($_POST['update_profile'])) {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            
            if (empty($full_name) || empty($email)) {
                $error = "Name and email are required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format";
            } else {
                $stmt = $conn->prepare("UPDATE users SET Full_Name = ?, email = ? WHERE user_id = ?");
                $stmt->bind_param("ssi", $full_name, $email, $user_id);
                
                if ($stmt->execute()) {
                    $success = "Profile updated successfully!";
                    // Update session data if needed
                    $user['Full_Name'] = $full_name;
                    $user['email'] = $email;
                } else {
                    $error = "Error updating profile: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        // Handle preferences update
        elseif (isset($_POST['update_preferences'])) {
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
            $promotional_emails = isset($_POST['promotional_emails']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE users SET 
                email_notifications = ?, 
                sms_notifications = ?, 
                promotional_emails = ? 
                WHERE user_id = ?
            ");
            $stmt->bind_param("iiii", $email_notifications, $sms_notifications, $promotional_emails, $user_id);
            
            if ($stmt->execute()) {
                $success = "Preferences updated successfully!";
            } else {
                $error = "Error updating preferences: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch user addresses
$address_stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC");
$address_stmt->bind_param("i", $user_id);
$address_stmt->execute();
$address_result = $address_stmt->get_result();
$addresses = $address_result->fetch_all(MYSQLI_ASSOC);
$address_stmt->close();

// Fetch notifications
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$notifications = $notif_result->fetch_all(MYSQLI_ASSOC);
$notif_stmt->close();


// Fetch orders with item count
// Corrected orders query
$order_stmt = $conn->prepare("
    SELECT o.order_id, o.created_at AS order_date, o.total_amount, o.status, 
           COUNT(oi.order_item_id) AS item_count, o.payment_method, 
           a.county AS delivery_location 
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN addresses a ON o.address_id = a.id
    WHERE o.user_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$orders = $order_result->fetch_all(MYSQLI_ASSOC);
$order_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['Full_Name'] ?? 'User') ?>'s Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="profile.css">
     <style>
     /* Modal Grid Layout */
.order-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 8px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    font-size: 0.9rem;
}

.detail-label {
    font-weight: 600;
    color: #555;
    margin-bottom: 0.25rem;
}

.detail-value {
    color: #333;
}

/* Order Items */
.order-items {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.order-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #fff;
    border: 1px solid #eee;
    border-radius: 8px;
    transition: box-shadow 0.2s;
}

.order-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.item-image {
    width: 60px;
    height: 60px;
    background-size: cover;
    background-position: center;
    border-radius: 8px;
    margin-right: 1rem;
}

.item-details {
    flex: 1;
}

.item-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.25rem;
}

.item-price {
    color: #555;
    font-size: 0.9rem;
}

.item-total {
    font-weight: 600;
    color: #27ae60;
    font-size: 0.9rem;
}

/* Close Button */
.close-modal-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #333;
    transition: color 0.2s;
}

.close-modal-btn:hover {
    color: #c0392b;
}

/* Modal Active State */
.modal.active {
    opacity: 1;
    pointer-events: auto;
}

/* Ensure status classes exist */
.status-pending { background: rgba(241, 196, 15, 0.15); color: #f39c12; }
.status-processing { background: rgba(52, 152, 219, 0.15); color: #3498db; }
.status-shipped { background: rgba(46, 204, 113, 0.15); color: #27ae60; }
.status-delivered { background: rgba(46, 204, 113, 0.3); color: #16a085; font-weight: bold; }
.status-cancelled { background: rgba(231, 76, 60, 0.15); color: #c0392b; }
     /* Enhanced stat card hover effect */
.stat-card {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Modal styling for order details */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}
.modal-content {
    background: #fff;
    padding: 2rem;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}
.close-modal {
    position: absolute;
    top: 1rem;
    right: 1rem;
}
.profile-menu a.active {
    background: linear-gradient(90deg, #6e8efb, #a777e3);
    color: white;
    box-shadow: 0 4px 10px rgba(30, 107, 82, 0.2);
}

/* Improve visibility for active section */
section:not(.hidden) {
    display: block;
    animation: fadeIn 0.5s ease;
} 


     </style>
</head>
<body class="kenyan-pattern">
    <!-- Display messages -->
    <?php if ($error): ?>
        <div class="message error-message" id="errorMessage">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="message success-message" id="successMessage">
            <?= htmlspecialchars($success) ?>
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
                    <h1><?= htmlspecialchars($user['Full_Name'] ?? 'User') ?></h1>
                    <p><?= htmlspecialchars($user['email'] ?? '') ?></p>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="location.href='hop.php'">
                    <i class="fas fa-shopping-cart"></i> Shop Now
                </button>
            </div>
        </div>
    </header>
    
    <div class="profile-container">
        <!-- Navigation -->
       <aside class="profile-nav fade-in">
    <nav class="profile-menu">
        <a href="#overview" class="<?= $active_tab === 'overview' ? 'active' : '' ?>"><i class="fas fa-user"></i> Overview</a>
        <a href="#orders" class="<?= $active_tab === 'orders' ? 'active' : '' ?>"><i class="fas fa-shopping-bag"></i> Orders</a>
        <a href="#security" class="<?= $active_tab === 'security' ? 'active' : '' ?>"><i class="fas fa-shield-alt"></i> Security</a>
        <a href="#addresses" class="<?= $active_tab === 'addresses' ? 'active' : '' ?>"><i class="fas fa-map-marker-alt"></i> Addresses</a>
        <a href="#notifications" class="<?= $active_tab === 'notifications' ? 'active' : '' ?>"><i class="fas fa-bell"></i> Notifications</a>
        <a href="#settings" class="btn btn-outline <?= $active_tab === 'settings' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Settings</a>
    </nav>
</aside>

        <!-- Main Content -->
        <main class="profile-content fade-in">
            <!-- Overview Section -->
            <section id="overview">
    <h2 class="section-title">Account Overview</h2>
    
    <div class="stats-grid">
        <div class="stat-card" onclick="navigateTo('orders')">
            <div class="stat-label">Total Orders</div>
            <div class="stat-value"><?= $user['total_orders'] ?? 0 ?></div>
            <div class="stat-desc">Since joining</div>
        </div>
        
        <div class="stat-card" onclick="navigateTo('orders')">
            <div class="stat-label">Last Purchase</div>
            <div class="stat-value"><?= !empty($user['last_order_date']) ? date('M d, Y', strtotime($user['last_order_date'])) : 'No orders' ?></div>
            <div class="stat-desc">View order history</div>
        </div>
        
        <div class="stat-card" onclick="navigateTo('settings')">
            <div class="stat-label">Account Status</div>
            <div class="stat-value">Active</div>
          
            <div class="stat-desc">Member since <?= !empty($user['join_date']) ? date('M d, Y', strtotime($user['join_date'])) : 'N/A' ?></div>
        </div>
    </div>

    <div class="mb-3">
        <h3 class="section-title">Quick Actions</h3>
        <div class="stats-grid">
            <button class="btn btn-primary" style="width:100%;" onclick="location.href='#'">
                <i class="fas fa-heart"></i> View Wishlist
            </button>
            <button class="btn btn-primary history-btn <?= !empty($user['last_order_date']) ? 'has-orders' : 'no-orders' ?>" 
                    style="width:100%;" 
                    onclick="location.href='#'"
                    title="<?= !empty($user['last_order_date']) ? 'View your recent purchases' : 'No orders yet' ?>">
                <i class="fas fa-history"></i> Purchase History
            </button>
            <button class="btn btn-primary" style="width:100%;" onclick="location.href='#'">
                <i class="fas fa-gift"></i> My Rewards
            </button>
        </div>
    </div>
</section>

            <!-- Security Section -->
            <section id="security" class="hidden">
                <h2 class="section-title">Security Settings</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Password
                        </button>
                        <button type="reset" class="btn" style="background: #eee; color: #333;">
                            Cancel
                        </button>
                    </div>
                </form>
            </section>

            <!-- Orders Section -->
            <section id="orders" class="hidden">
                <h2 class="section-title">Recent Orders</h2>
                
                <div class="orders-grid">
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div>
                                        <div class="order-id">#ORD-<?= $order['order_id'] ?></div>
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
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Payment Method</span>
                                        <span class="detail-value"><?= $order['payment_method'] ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Delivery To</span>
                                        <span class="detail-value"><?= $order['delivery_location'] ?? 'Not specified' ?></span>
                                    </div>
                                </div>
                                
                                <div class="order-actions">
                                    <button class="btn view-order-details" data-order-id="<?= $order['order_id'] ?>">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <button class="btn btn-primary reorder-btn" data-order-id="<?= $order['order_id'] ?>">
                                        <i class="fas fa-redo"></i> Reorder
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center" style="grid-column:1/-1; padding:2rem;">
                            <i class="fas fa-shopping-bag" style="font-size:3rem; color:#ccc; margin-bottom:1rem;"></i>
                            <h3>No orders yet</h3>
                            <p>Your orders will appear here once you make a purchase</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Addresses Section -->
            <section id="addresses" class="hidden">
                <h2 class="section-title">Delivery Addresses</h2>
                
                <div class="mb-3">
                    <p>Manage your delivery addresses for faster checkout</p>
                </div>
                
                <div class="address-grid">
                    <?php foreach ($addresses as $address): ?>
                    <div class="address-card <?= $address['is_default'] ? 'default' : '' ?>">
                        <div class="address-actions">
                            <button class="edit-address" data-id="<?= $address['id'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="address_id" value="<?= $address['id'] ?>">
                                <button type="button" class="delete-address" data-id="<?= $address['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                        
                        <h4><?= htmlspecialchars($address['Full_Name']) ?></h4>
                        <p><?= htmlspecialchars($address['address_line1']) ?></p>
                        <?php if (!empty($address['address_line2'])): ?>
                            <p><?= htmlspecialchars($address['address_line2']) ?></p>
                        <?php endif; ?>
                        <p><strong>County:</strong> <?= htmlspecialchars($address['county']) ?></p>
                        <p><strong>Constituency:</strong> <?= htmlspecialchars($address['constituency']) ?></p>
                        <p><strong>Town:</strong> <?= htmlspecialchars($address['Town']) ?></p>
                        <p><strong>Postal Code:</strong> <?= htmlspecialchars($address['postal_code']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($address['phone_number']) ?></p>
                        
                        <?php if ($address['is_default']): ?>
                            <span class="default-badge">Default Delivery Address</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($addresses)): ?>
                        <div class="text-center" style="grid-column:1/-1; padding:2rem;">
                            <i class="fas fa-map-marker-alt" style="font-size:3rem; color:#ccc; margin-bottom:1rem;"></i>
                            <h3>No addresses saved</h3>
                            <p>Add your first address to get started</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button id="addAddressBtn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Address
                </button>
                
                <div id="addressFormContainer" class="mt-3 hidden">
                    <h3 class="section-title" id="addressFormTitle">Add New Address</h3>
                    
                    <div class="mb-3">
                        <h4>Select County</h4>
                        <div class="county-selector">
                            <div class="county-card" data-county="Nairobi">
                                <i class="fas fa-city"></i>
                                <div>Nairobi</div>
                            </div>
                            <div class="county-card" data-county="Mombasa">
                                <i class="fas fa-umbrella-beach"></i>
                                <div>Mombasa</div>
                            </div>
                            <div class="county-card" data-county="Nakuru">
                                <i class="fas fa-mountain"></i>
                                <div>Nakuru</div>
                            </div>
                            <div class="county-card" data-county="Eldoret">
                                <i class="fas fa-tractor"></i>
                                <div>Eldoret</div>
                            </div>
                        </div>
                    </div>
                    
                    <form id="addressForm" class="form-grid" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="save_address" value="1">
                        <input type="hidden" name="address_id" id="address_id" value="">
                        
                        <div class="form-group">
                            <label for="Full_Name">Full Name *</label>
                            <input type="text" id="Full_Name" name="Full_Name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone_number">Phone Number *</label>
                            <input type="tel" id="phone_number" name="phone_number" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="address_line1">Address Line 1 *</label>
                            <input type="text" id="address_line1" name="address_line1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="address_line2">Address Line 2 (Optional)</label>
                            <input type="text" id="address_line2" name="address_line2">
                        </div>
                        
                        <div class="form-group">
                            <label for="county">County *</label>
                            <select id="county" name="county" required>
                                <option value="">Select County</option>
                                <option value="Nairobi">Nairobi</option>
                                <option value="Mombasa">Mombasa</option>
                                <option value="Kisumu">Kisumu</option>
                                <option value="Nakuru">Nakuru</option>
                                <option value="Eldoret">Eldoret</option>
                                <!-- Add more Kenyan counties -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="constituency">Constituency *</label>
                            <input type="text" id="constituency" name="constituency" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="Town">Town*</label>
                            <input type="text" id="Town" name="Town" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code">Postal Code *</label>
                            <input type="text" id="postal_code" name="postal_code" required>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>
                                <input type="checkbox" id="is_default" name="is_default">
                                Set as default delivery address
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="saveAddressBtn">
                                <i class="fas fa-save"></i> Save Address
                            </button>
                            <button type="button" id="cancelAddressBtn" class="btn" style="background: #eee; color: #333;">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </section>
            
            <!-- Notifications Section -->
            <section id="notifications" class="hidden">
                <h2 class="section-title">Notifications</h2>
                
                <div class="mb-3">
                    <h3 class="section-title">Notification Preferences</h3>
                    <div class="form-grid">
                        <div style="display: flex; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #eee;">
                            <div>
                                <h4>Order Updates</h4>
                                <p class="text-muted">Get notified about order status</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #eee;">
                            <div>
                                <h4>Promotions</h4>
                                <p class="text-muted">Special offers and discounts</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #eee;">
                            <div>
                                <h4>Newsletter</h4>
                                <p class="text-muted">Monthly updates and news</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <h3 class="section-title">Recent Notifications</h3>
                
                <div class="notifications-list">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item">
                            <div class="notification-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                                <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                                <div class="notification-date"><?= date('M d, Y h:i A', strtotime($notification['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center" style="padding:2rem;">
                            <i class="fas fa-bell-slash" style="font-size:3rem; color:#ccc; margin-bottom:1rem;"></i>
                            <h3>No notifications</h3>
                            <p>You don't have any notifications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Settings Section -->
            <section id="settings" class="hidden">
                <h2 class="section-title">Account Settings</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <h3>Personal Information</h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" 
                                       value="<?= htmlspecialchars($user['Full_Name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" 
                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="form-group">
                        <h3>Communication Preferences</h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="email_notifications" value="1" checked>
                                    Email Notifications
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="sms_notifications" value="1" checked>
                                    SMS Notifications
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="promotional_emails" value="1">
                                    Promotional Offers
                                </label>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_preferences" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <footer class="profile-footer">
        <p>© <?= date('Y') ?> JOEMAKEIT. All rights reserved. <span class="kenyan-flag"></span> Proudly Kenyan</p>
        <p class="text-muted">Need help? Contact our support team at support@joemakeit.co.ke</p>
    </footer>
    
    
       <script>
// Navigation helper function
function navigateTo(sectionId) {
    const link = document.querySelector(`.profile-menu a[href="#${sectionId}"]`);
    if (link) {
        link.click();
    }
}

// Tab Switching
document.querySelectorAll('.profile-menu a').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('.profile-menu a').forEach(l => l.classList.remove('active'));
        e.target.classList.add('active');
        document.querySelectorAll('section').forEach(s => s.classList.add('hidden'));
        document.querySelector(e.target.hash).classList.remove('hidden');
    });
});

// Address Form Toggle
document.getElementById('addAddressBtn').addEventListener('click', () => {
    document.getElementById('addressFormContainer').classList.remove('hidden');
    document.getElementById('addAddressBtn').classList.add('hidden');
    document.getElementById('addressFormTitle').textContent = 'Add New Address';
    document.getElementById('saveAddressBtn').textContent = 'Save Address';
    document.getElementById('addressForm').reset();
    document.getElementById('address_id').value = '';
    document.querySelectorAll('.county-card').forEach(c => c.classList.remove('selected'));
});

document.getElementById('cancelAddressBtn').addEventListener('click', () => {
    document.getElementById('addressFormContainer').classList.add('hidden');
    document.getElementById('addAddressBtn').classList.remove('hidden');
    document.getElementById('addressForm').reset();
    document.getElementById('address_id').value = '';
    document.getElementById('addressFormTitle').textContent = 'Add New Address';
    document.getElementById('saveAddressBtn').textContent = 'Save Address';
    document.querySelectorAll('.county-card').forEach(c => c.classList.remove('selected'));
});

// County Selection
document.querySelectorAll('.county-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.county-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        document.getElementById('county').value = card.dataset.county;
    });
});

// Edit Address
document.querySelectorAll('.edit-address').forEach(button => {
    button.addEventListener('click', function() {
        const addressId = this.dataset.id;
        document.getElementById('addressFormContainer').classList.remove('hidden');
        document.getElementById('addAddressBtn').classList.add('hidden');
        document.getElementById('addressFormTitle').textContent = 'Edit Address';
        document.getElementById('saveAddressBtn').textContent = 'Update Address';
        
        // Show loading state
        const saveBtn = document.getElementById('saveAddressBtn');
        const originalBtnText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

        // Fetch address details via AJAX
        fetch(`profile.php?action=get_address&id=${addressId}&csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(address => {
                // Populate form
                document.getElementById('address_id').value = addressId;
                document.getElementById('Full_Name').value = address.Full_Name || '';
                document.getElementById('phone_number').value = address.phone_number || '';
                document.getElementById('address_line1').value = address.address_line1 || '';
                document.getElementById('address_line2').value = address.address_line2 || '';
                document.getElementById('county').value = address.county || '';
                document.getElementById('constituency').value = address.constituency || '';
                document.getElementById('ward').value = address.ward || '';
                document.getElementById('postal_code').value = address.postal_code || '';
                document.getElementById('is_default').checked = address.is_default == 1;

                // Update county selector
                document.querySelectorAll('.county-card').forEach(card => {
                    card.classList.toggle('selected', card.dataset.county === address.county);
                });

                // Restore button
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalBtnText;
            })
            .catch(error => {
                console.error('Error fetching address:', error);
                showMessage('Failed to load address details. Please try again.', 'error');
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalBtnText;
            });
    });
});

// Delete Address
document.querySelectorAll('.delete-address').forEach(button => {
    button.addEventListener('click', function() {
        const addressId = this.dataset.id;
        if (confirm('Are you sure you want to delete this address?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="address_id" value="${addressId}">
                <input type="hidden" name="delete_address" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
});

// Order Details Modal
document.querySelectorAll('.view-order-details').forEach(button => {
    button.addEventListener('click', function() {
        const orderId = this.dataset.orderId;
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        
        fetch(`order_details.php?order_id=${orderId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(order => {
                this.innerHTML = originalText;
                
                // Define deliveryLocation
                const deliveryLocation = order.shipping_address || 'Not specified';

                // Handle missing or empty items
                const itemsList = order.items && order.items.length > 0 
                    ? order.items.map(item => `
                        <div class="order-item">
                            <div class="item-image" style="background-image:url('${item.image_url || 'img/default-product.jpg'}')"></div>
                            <div class="item-details">
                                <div class="item-name">${item.name || 'Unknown Item'}</div>
                                <div class="item-price">Ksh ${Number(item.price || 0).toFixed(2)} x ${item.quantity || 1}</div>
                                <div class="item-total">Ksh ${Number((item.price || 0) * (item.quantity || 1)).toFixed(2)}</div>
                            </div>
                        </div>
                    `).join('')
                    : '<div class="order-item">No items found for this order</div>';

                // Create modal structure
                const modal = document.createElement('div');
                modal.className = 'modal active';
                modal.innerHTML = `
                    <div class="modal-content">
                        <button class="btn close-modal-btn">×</button>
                        <h3>Order #${order.order_id || 'Unknown'}</h3>
                        <div class="order-details-grid">
                            <div class="detail-item">
                                <span class="detail-label">Order Date:</span>
                                <span class="detail-value">${
                                    order.order_date 
                                        ? new Date(order.order_date).toLocaleDateString('en-US', { 
                                            month: 'long', day: '2-digit', year: 'numeric' 
                                          }) 
                                        : 'N/A'
                                }</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value status-${(order.status || 'unknown').toLowerCase()}">${order.status || 'Unknown'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Total Amount:</span>
                                <span class="detail-value">Ksh ${Number(order.total_amount || 0).toFixed(2)}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Payment Method:</span>
                                <span class="detail-value">${order.payment_method || 'Not specified'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Delivery Location:</span>
                                <span class="detail-value">${deliveryLocation}</span>
                            </div>
                        </div>
                        <h4>Order Items</h4>
                        <div class="order-items">
                            ${itemsList}
                        </div>
                        
<div class="order-actions">
    ${order.status !== 'Cancelled' ? `
        <button class="btn btn-primary" onclick="location.href='track-order.php?order_id=${order.order_id || ''}'">
            <i class="fas fa-map-marker-alt"></i> Track Order
        </button>
    ` : ''}
</div>
                    </div>
                `;
                
                
                // Remove existing modals
                document.querySelectorAll('.modal').forEach(m => m.remove());
                
                // Append new modal
                document.body.appendChild(modal);
                
                // Add close functionality
                modal.querySelector('.close-modal-btn').addEventListener('click', () => {
                    modal.remove();
                });

                // Close modal when clicking outside
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.remove();
                    }
                });
            })
            .catch(error => {
                console.error('Error loading order details:', error);
                this.innerHTML = originalText;
                showMessage('Failed to load order details. Please try again.', 'error');
            });
    });
});
// Reorder functionality
document.querySelectorAll('.reorder-btn').forEach(button => {
    button.addEventListener('click', function() {
        const orderId = this.dataset.orderId;
        
        if (confirm('Add all items from this order to your cart?')) {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            fetch(`reorder.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        this.innerHTML = '<i class="fas fa-check"></i> Added!';
                        setTimeout(() => {
                            this.innerHTML = originalText;
                        }, 2000);
                        showMessage('Items added to cart successfully!', 'success');
                    } else {
                        this.innerHTML = originalText;
                        showMessage(result.message || 'Failed to add items to cart', 'error');
                    }
                })
                .catch(error => {
                    console.error('Reorder error:', error);
                    this.innerHTML = originalText;
                    showMessage('Failed to process reorder. Please try again.', 'error');
                });
        }
    });
});

// Message Display Function
function showMessage(message, type = 'error') {
    const msgDiv = document.createElement('div');
    msgDiv.className = `message ${type === 'error' ? 'error-message' : 'success-message'}`;
    msgDiv.textContent = message;
    document.body.appendChild(msgDiv);

    setTimeout(() => {
        msgDiv.style.opacity = '1';
        msgDiv.style.top = '30px';
    }, 100);

    setTimeout(() => {
        msgDiv.style.opacity = '0';
        setTimeout(() => msgDiv.remove(), 300);
    }, 5000);
}

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
// Add this to the script section
// Function to activate tab based on URL fragment
function activateTabFromFragment() {
    const hash = window.location.hash;
    if (hash) {
        const tabLink = document.querySelector(`.profile-menu a[href="${hash}"]`);
        if (tabLink) {
            // Remove active class from all links
            document.querySelectorAll('.profile-menu a').forEach(l => l.classList.remove('active'));
            
            // Hide all sections
            document.querySelectorAll('section').forEach(s => s.classList.add('hidden'));
            
            // Activate the target tab
            tabLink.classList.add('active');
            document.querySelector(hash).classList.remove('hidden');
        }
    }
}

// Call the function on page load
document.addEventListener('DOMContentLoaded', activateTabFromFragment);

// Also call it when hash changes
window.addEventListener('hashchange', activateTabFromFragment);

// Update the tab click handler
document.querySelectorAll('.profile-menu a').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const hash = link.getAttribute('href');
        
        // Update URL without reloading the page
        window.location.hash = hash;
        
        // Update tab state
        activateTabFromFragment();
    });
});
    </script>
</body>
</html>