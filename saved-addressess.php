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
$error = '';
$success = '';
$addresses = [];
$default_address_id = null;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        // Handle address deletion
        if (isset($_POST['delete_address'])) {
            $address_id = (int)$_POST['address_id'];
            
            // Verify the address belongs to the user
            $stmt = $conn->prepare("SELECT user_id FROM addresses WHERE id = ?");
            $stmt->bind_param("i", $address_id);
            $stmt->execute();
            $stmt->bind_result($addr_user_id);
            $stmt->fetch();
            $stmt->close();
            
            if ($addr_user_id == $user_id) {
                $stmt = $conn->prepare("DELETE FROM addresses WHERE id = ?");
                $stmt->bind_param("i", $address_id);
                if ($stmt->execute()) {
                    $success = "Address deleted successfully";
                } else {
                    $error = "Failed to delete address";
                }
                $stmt->close();
            } else {
                $error = "You don't have permission to delete this address";
            }
        }
        // Handle setting default address
        elseif (isset($_POST['set_default'])) {
            $address_id = (int)$_POST['address_id'];
            
            // Verify the address belongs to the user
            $stmt = $conn->prepare("SELECT user_id FROM addresses WHERE id = ?");
            $stmt->bind_param("i", $address_id);
            $stmt->execute();
            $stmt->bind_result($addr_user_id);
            $stmt->fetch();
            $stmt->close();
            
            if ($addr_user_id == $user_id) {
                // First clear any existing defaults
                $reset_stmt = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
                $reset_stmt->bind_param("i", $user_id);
                $reset_stmt->execute();
                $reset_stmt->close();
                
                // Set new default
                $stmt = $conn->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $address_id, $user_id);
                if ($stmt->execute()) {
                    $success = "Default address updated successfully";
                } else {
                    $error = "Failed to update default address";
                }
                $stmt->close();
            } else {
                $error = "You don't have permission to set this address as default";
            }
        }
    }
}

// Fetch all user addresses
$stmt = $conn->prepare("
    SELECT id, Full_Name, phone_number, address_line1, address_line2, 
           county, constituency, Town AS ward, postal_code, is_default
    FROM addresses 
    WHERE user_id = ?
    ORDER BY is_default DESC, created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$addresses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Addresses - JOEMAKEIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e6b52;
            --primary-light: #2d9b78;
            --secondary: #f39c12;
            --success: #10b981;
            --danger: #ef476f;
            --warning: #ffd166;
            --light: #f8f9fa;
            --dark: #1e293b;
            --gray: #6c757d;
            --light-gray: #e2e8f0;
            --radius: 12px;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
            padding-bottom: 2rem;
        }

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

        /* Address Cards */
        .address-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .address-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            position: relative;
            transition: var(--transition);
            border: 1px solid var(--light-gray);
        }

        .address-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .address-card.default {
            border: 2px solid var(--primary);
            background: rgba(30, 107, 82, 0.03);
        }

        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .address-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .default-badge {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .address-type {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .address-details {
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .address-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
        }

        .edit-btn {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .edit-btn:hover {
            background: rgba(52, 152, 219, 0.2);
        }

        .delete-btn {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .delete-btn:hover {
            background: rgba(231, 76, 60, 0.2);
        }

        .default-btn {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .default-btn:hover {
            background: rgba(46, 204, 113, 0.2);
        }

        .add-address-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border: 2px dashed var(--light-gray);
            min-height: 250px;
        }

        .add-address-card:hover {
            border-color: var(--primary);
            background: rgba(30, 107, 82, 0.03);
        }

        .add-address-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(30, 107, 82, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .add-address-text {
            font-weight: 500;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .add-address-subtext {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 3.5rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--gray);
            max-width: 500px;
            margin: 0 auto 1.5rem;
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

        .kenyan-flag {
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
            
            .address-grid {
                grid-template-columns: 1fr;
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
                    <h1>Saved Addresses</h1>
                    <p>Manage your delivery addresses</p>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="location.href='profile.php'">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </button>
            </div>
        </div>
    </header>

    <div class="profile-container">
        <main class="profile-content fade-in">
            <section>
                <h2 class="section-title">
                    <i class="fas fa-map-marker-alt"></i> Your Addresses
                </h2>
                
                <div class="address-grid">
                    <?php if (!empty($addresses)): ?>
                        <?php foreach ($addresses as $address): ?>
                            <div class="address-card <?= $address['is_default'] ? 'default' : '' ?>">
                                <div class="address-header">
                                    <div class="address-title"><?= htmlspecialchars($address['Full_Name']) ?></div>
                                    <?php if ($address['is_default']): ?>
                                        <div class="default-badge">Default</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="address-details">
                                    <?= htmlspecialchars($address['address_line1']) ?><br>
                                    <?php if (!empty($address['address_line2'])): ?>
                                        <?= htmlspecialchars($address['address_line2']) ?><br>
                                    <?php endif; ?>
                                    <strong>County:</strong> <?= htmlspecialchars($address['county']) ?><br>
                                    <strong>Constituency:</strong> <?= htmlspecialchars($address['constituency']) ?><br>
                                    <strong>Ward:</strong> <?= htmlspecialchars($address['ward']) ?><br>
                                    <strong>Postal Code:</strong> <?= htmlspecialchars($address['postal_code']) ?><br>
                                    <strong>Phone:</strong> <?= htmlspecialchars($address['phone_number']) ?>
                                </div>
                                
                                <div class="address-actions">
                                    <button class="action-btn edit-btn" onclick="location.href='edit-address.php?id=<?= $address['id'] ?>'">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <?php if (!$address['is_default']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="address_id" value="<?= $address['id'] ?>">
                                            <button type="submit" name="set_default" class="action-btn default-btn">
                                                <i class="fas fa-check-circle"></i> Set Default
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="address_id" value="<?= $address['id'] ?>">
                                        <button type="submit" name="delete_address" class="action-btn delete-btn" 
                                                onclick="return confirm('Are you sure you want to delete this address?')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-map-marked-alt"></i>
                            <h3>No Saved Addresses</h3>
                            <p>You haven't saved any addresses yet. Add your first address to make checkout faster.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="add-address-card" onclick="location.href='add-address.php'">
                        <div class="add-address-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="add-address-text">Add New Address</div>
                        <div class="add-address-subtext">Click here to add a new delivery address</div>
                    </div>
                </div>
            </section>
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
    </script>
</body>
</html>