<?php
// Initialize order-specific variables
$orders = [];
$tracking_info = [];
$order_details = [];
$order_items = [];
$search_term = '';
$status_filter = '';
$error = '';
$success = '';

// Handle search form submission
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['search'])) {
        $search_term = filter_var(trim($_GET['search']), FILTER_SANITIZE_STRING);
        if (strlen($search_term) > 100) {
            $search_term = substr($search_term, 0, 100);
        }
    }
    if (isset($_GET['status_filter'])) {
        $status_filter = filter_var($_GET['status_filter'], FILTER_SANITIZE_STRING);
    }
}

// Handle tracking update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracking'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token validation failed";
    } else {
        $order_id = (int)$_POST['order_id'];
        $status = $_POST['status'];
        $location = $_POST['location'];
        $notes = $_POST['notes'];
        
        // Validate inputs
        if (empty($status)) {
            $error = "Status is required";
        } else {
            // Fetch order details
            $order_stmt = $conn->prepare("SELECT o.order_id, o.created_at, o.total_amount, o.status, o.payment_method, u.email, u.username, a.address_line1, a.address_line2, a.county, a.constituency, a.Town, a.postal_code FROM orders o JOIN users u ON o.user_id = u.user_id LEFT JOIN addresses a ON o.address_id = a.id WHERE o.order_id = ?");
            $order_stmt->bind_param("i", $order_id);
            $order_stmt->execute();
            $order_result = $order_stmt->get_result();
            $order_data = $order_result->fetch_assoc();
            $order_stmt->close();

            if (!$order_data || empty($order_data['email'])) {
                $error = "Could not find order or user email";
            } else {
                // Fetch order items
                $items_stmt = $conn->prepare("SELECT p.name, p.image_url, oi.quantity, oi.price, (oi.price * oi.quantity) AS subtotal FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
                $items_stmt->bind_param("i", $order_id);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();
                $order_items = $items_result->fetch_all(MYSQLI_ASSOC);
                $items_stmt->close();

                // Insert new tracking record
                $stmt = $conn->prepare("INSERT INTO order_tracking (order_id, status, location, notes) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $order_id, $status, $location, $notes);
                
                if ($stmt->execute()) {
                    // Update order status
                    $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                    $update_stmt->bind_param("si", $status, $order_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Construct the email body with modern styling and product images
                    $to = $order_data['email'];
                    $subject = "Order #{$order_id} Tracking Update";
                    
                    // Base URL for images
                    $base_image_url = 'https://joemakeit.com/';

                    // Build HTML table for order items
                    $items_table = "
                        <table style='border-collapse: collapse; width: 100%; max-width: 600px; font-family: Arial, Helvetica, sans-serif; font-size: 14px;'>
                            <thead>
                                <tr style='background-color: #f8f9fa; color: #212529;'>
                                    <th style='padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;'>Image</th>
                                    <th style='padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;'>Item</th>
                                    <th style='padding: 12px; text-align: center; border-bottom: 1px solid #dee2e6;'>Quantity</th>
                                    <th style='padding: 12px; text-align: right; border-bottom: 1px solid #dee2e6;'>Price</th>
                                </tr>
                            </thead>
                            <tbody>";
                    foreach ($order_items as $item) {
                        // Construct image URL
                        $image_url = !empty($item['image_url']) && filter_var($item['image_url'], FILTER_VALIDATE_URL) 
                            ? $item['image_url'] 
                            : $base_image_url . ltrim($item['image_url'] ?? '', '/');
                        $image_url = !empty($item['image_url']) ? $image_url : $base_image_url . 'IMG/offer.png';
                        $image_html = !empty($item['image_url']) 
                            ? "<img src='{$image_url}' alt='" . htmlspecialchars($item['name']) . "' style='max-width: 60px; height: auto; display: block; border-radius: 4px;' onerror='this.style.display=\"none\";'>"
                            : '';
                        $items_table .= "
                            <tr>
                                <td style='padding: 12px; border-bottom: 1px solid #dee2e6;'>{$image_html}</td>
                                <td style='padding: 12px; border-bottom: 1px solid #dee2e6;'>" . htmlspecialchars($item['name']) . "</td>
                                <td style='padding: 12px; text-align: center; border-bottom: 1px solid #dee2e6;'>" . (int)$item['quantity'] . "</td>
                                <td style='padding: 12px; text-align: right; border-bottom: 1px solid #dee2e6;'>Ksh " . number_format($item['price'], 2) . "</td>
                            </tr>";
                    }
                    $items_table .= "
                            </tbody>
                        </table>";

                    // Format delivery address
                    $delivery_address = implode(", ", array_filter([
                        $order_data['address_line1'],
                        $order_data['address_line2'],
                        $order_data['Town'],
                        $order_data['constituency'],
                        $order_data['county'],
                        $order_data['postal_code']
                    ]));

                    // Construct the full email body
                    $body = "
                        <!DOCTYPE html>
                        <html lang='en'>
                        <head>
                            <meta charset='utf-8'>
                            <meta name='viewport' content='width=device-width, initial-scale=1'>
                            <title>Order Tracking Update</title>
                        </head>
                        <body style='margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #212529; background-color: #f4f4f4;'>
                            <div style='max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                                <!-- Header -->
                                <div style='background: #007bff; color: #ffffff; padding: 20px; text-align: center;'>
                                    <h1 style='margin: 0; font-size: 24px; font-weight: 600;'>Joemakeit</h1>
                                    <p style='margin: 8px 0 0; font-size: 16px; font-weight: 400;'>Order #{$order_id} Tracking Update</p>
                                </div>
                                <!-- Content -->
                                <div style='padding: 24px;'>
                                    <p style='font-size: 16px; line-height: 1; margin: 0 0 16px;'>Dear " . htmlspecialchars($order_data['username']) . ",</p>
                                    <p style='font-size: 16px; line-height: 1; margin: 0 0 16px;'>Your order has been updated to status: <strong>" . htmlspecialchars($status) . "</strong></p>
                                    <p style='font-size: 16px; line-height: 1; margin: 0 0 16px;'><strong>Location:</strong> " . htmlspecialchars($location) . "</p>
                                    <p style='font-size: 16px; line-height: 1; margin: 0 0 16px;'><strong>Notes:</strong> " . htmlspecialchars($notes) . "</p>
                                    <h2 style='font-size: 20px; font-weight: 600; margin: 24px 0 16px; color: #343a40;'>Order Details</h2>
                                    <p style='font-size: 16px; line-height: 1; margin: 0 0 8px;'><strong>Order Date:</strong> " . date('F j, Y, g:i a', strtotime($order_data['created_at'])) . "</p>
                                    <p style='font-size: 16px; line-height: 1; margin: 0 0 8px;'><strong>Total Amount:</strong> Ksh " . number_format($order_data['total_amount'], 2) . "</p>
                                    <p style='font-size: 16px; line-height: 1; margin: 0 0 8px;'><strong>Payment Method:</strong> " . htmlspecialchars($order_data['payment_method']) . "</p>
                                    <p style='font-size: 16px; line-height: 1; margin: 0 0 8px;'><strong>Delivery Address:</strong> " . htmlspecialchars($delivery_address) . "</p>
                                    <h2 style='font-size: 20px; font-weight: 600; margin: 24px 0 16px; color: #343a40;'>Your Package Contains:</h2>
                                    $items_table
                                    <p style='font-size: 16px; line-height: 1.5; margin: 24px 0 0;'>Thank you for shopping with us!</p>
                                </div>
                                <!-- Footer -->
                                <div style='background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d;'>
                                    <p style='margin: 0 0 8px;'>Joemakeit Team</p>
                                    <p style='margin: 0;'>
                                        <a href='https://joemakeit.com' style='color: #007bff; text-decoration: none; margin: 0 8px;'>Visit our website</a> |
                                        <a href='mailto:support@joemakeit.com' style='color: #007bff; text-decoration: none; margin: 0 8px;'>Contact Support</a>
                                    </p>
                                </div>
                            </div>
                        </body>
                        </html>";

                    // Plain text version for non-HTML email clients
                    $alt_body = "Order Tracking Update\n\n"
                              . "Dear {$order_data['username']},\n\n"
                              . "Your order #{$order_id} has been updated to: {$status}\n"
                              . "Location: {$location}\n"
                              . "Notes: {$notes}\n\n"
                              . "Order Details\n"
                              . "Order Date: " . date('F j, Y, g:i a', strtotime($order_data['created_at'])) . "\n"
                              . "Total Amount: Ksh " . number_format($order_data['total_amount'], 2) . "\n"
                              . "Payment Method: {$order_data['payment_method']}\n"
                              . "Delivery Address: {$delivery_address}\n\n"
                              . "Your Package Contains:\n"
                              . "Image | Item | Quantity | Price\n";
                    foreach ($order_items as $item) {
                        $image_url = !empty($item['image_url']) && filter_var($item['image_url'], FILTER_VALIDATE_URL) 
                            ? $item['image_url'] 
                            : $base_image_url . ltrim($item['image_url'] ?? '', '/');
                        $image_url = !empty($item['image_url']) ? $image_url : $base_image_url . 'IMG/offer.png';
                        $alt_body .= "{$image_url} | {$item['name']} | {$item['quantity']} | Ksh " . number_format($item['price'], 2) . "\n";
                    }
                    $alt_body .= "\nThank you for shopping with us!\nJoemakeit Team";

                    // Send email notification
                    $email_result = sendEmail($to, $subject, $body, $alt_body);
                    if (!$email_result['success']) {
                        $error = "Tracking updated, but failed to send email notification";
                    } else {
                        $success = "Tracking information updated successfully and email sent!";
                    }
                    
                    // Refresh the page to show updated data
                              
                  echo '<script>window.location.replace("?page=orders&view_order='.$order_id.'");</script>';
                  exit();
              } else {
                  $error = "Error updating tracking information";
              }
              $stmt->close();
          }
        }
    }
}

// Pagination variables
$per_page = 2; // Number of orders per page

// Fetch total orders for pagination
$query = "SELECT COUNT(*) as total FROM orders o 
          JOIN users u ON o.user_id = u.user_id 
          LEFT JOIN addresses a ON o.address_id = a.id";
$where = [];
$params = [];
$types = '';

if (!empty($search_term)) {
    $where[] = "(o.order_id LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search_term%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$count_stmt = $conn->prepare($query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_orders = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_orders / $per_page);

// Validate page parameter
$page = isset($_GET['p']) ? max(1, min((int)$_GET['p'], $total_pages)) : 1;
$offset = ($page - 1) * $per_page;

// Redirect if page is out of range
if ($page > $total_pages && $total_pages > 0) {
    header("Location: ?page=orders&p=$total_pages&search=" . urlencode($search_term) . "&status_filter=" . urlencode($status_filter));
    exit();
}

// Fetch orders with parameterized query
$query = "SELECT o.order_id, o.created_at, o.total_amount, o.status, u.username, u.email, a.county AS delivery_location 
          FROM orders o 
          JOIN users u ON o.user_id = u.user_id 
          LEFT JOIN addresses a ON o.address_id = a.id";
$where = [];
$params = [];
$types = '';

if (!empty($search_term)) {
    $where[] = "(o.order_id LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search_term%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
array_push($params, $per_page, $offset);
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// If viewing a specific order
if (isset($_GET['view_order'])) {
    $order_id = (int)$_GET['view_order'];
    
    // Fetch order details
    $detail_stmt = $conn->prepare("SELECT o.order_id, o.created_at, o.total_amount, o.status, o.payment_method, u.username, u.email, u.phone_number, a.address_line1, a.address_line2, a.county, a.constituency, a.Town, a.postal_code FROM orders o JOIN users u ON o.user_id = u.user_id LEFT JOIN addresses a ON o.address_id = a.id WHERE o.order_id = ?");
    $detail_stmt->bind_param("i", $order_id);
    $detail_stmt->execute();
    $detail_result = $detail_stmt->get_result();
    $order_details = $detail_result->fetch_assoc();
    $detail_stmt->close();
    
    // Fetch order items
    $items_stmt = $conn->prepare("SELECT p.name, p.image_url, oi.price, oi.quantity, (oi.price * oi.quantity) AS subtotal FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $order_items = $items_result->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();
    
    // Fetch tracking information
    $track_stmt = $conn->prepare("SELECT status, location, notes, created_at FROM order_tracking WHERE order_id = ? ORDER BY created_at DESC");
    $track_stmt->bind_param("i", $order_id);
    $track_stmt->execute();
    $track_result = $track_stmt->get_result();
    $tracking_info = $track_result->fetch_all(MYSQLI_ASSOC);
    $track_stmt->close();
}

// Get order statistics
$total_orders_count = 0;
$delivered_orders = 0;
$transit_orders = 0;

$stats_stmt = $conn->prepare("SELECT 
    (SELECT COUNT(*) FROM orders) AS total_orders,
    (SELECT COUNT(*) FROM orders WHERE status = 'Delivered') AS delivered_orders,
    (SELECT COUNT(*) FROM orders WHERE status = 'Shipped') AS transit_orders
");
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
if ($stats_row = $stats_result->fetch_assoc()) {
    $total_orders_count = $stats_row['total_orders'];
    $delivered_orders = $stats_row['delivered_orders'];
    $transit_orders = $stats_row['transit_orders'];
}
$stats_stmt->close();
?>

<!-- Orders Dashboard -->
<div style="background: white; border-radius: 12px; box-shadow: var(--card-shadow); padding: 2rem; margin-bottom: 2rem;">
    <?php if ($error): ?>
        <div class="message error-message">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="message success-message">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <h1 style="font-size: 2rem; margin-bottom: 1rem; color: var(--primary);">
        <i class="fas fa-truck"></i> Order Tracking System
    </h1>
    <p style="font-size: 1.2rem; color: var(--gray); margin-bottom: 2rem;">
        Manage and track customer orders
    </p>
    
    <div class="dashboard-stats">
        <div class="stat-card primary-stat">
            <div class="stat-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-value">
                <?= $total_orders_count ?>
            </div>
            <div class="stat-label">Total Orders</div>
        </div>
        
        <div class="stat-card success-stat">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value">
                <?= $delivered_orders ?>
            </div>
            <div class="stat-label">Delivered</div>
        </div>
        
        <div class="stat-card warning-stat">
            <div class="stat-icon">
                <i class="fas fa-shipping-fast"></i>
            </div>
            <div class="stat-value">
                <?= $transit_orders ?>
            </div>
            <div class="stat-label">In Transit</div>
        </div>
    </div>
    
    <!-- Order Status Chart -->
    <div class="chart-container">
        <canvas id="orderStatusChart"></canvas>
    </div>
    
    <!-- Search Form -->
    <div class="search-container">
        <h2 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-search"></i> Search Orders
        </h2>
        <form method="GET" class="search-form">
            <input type="hidden" name="page" value="orders">
            <div class="form-inline-group">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Search by Order ID, Customer, or Email"
                    value="<?= htmlspecialchars($search_term) ?>"
                >
            </div>
            
            <div class="form-inline-group">
                <select name="status_filter" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Processing" <?= $status_filter === 'Processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="Shipped" <?= $status_filter === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="Delivered" <?= $status_filter === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="form-inline-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
            
            <?php if (!empty($search_term) || !empty($status_filter)): ?>
                <div class="form-inline-group">
                    <a href="?page=orders" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Orders Table -->
    <div class="orders-container">
        <h2 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-list"></i> Recent Orders
        </h2>
        
        <?php if (!empty($orders)): ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= $order['order_id'] ?></td>
                                <td>
                                    <div><?= htmlspecialchars($order['username']) ?></div>
                                    <div style="font-size: 0.9rem; color: var(--gray);"><?= htmlspecialchars($order['email']) ?></div>
                                </td>
                                <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                <td>Ksh <?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a 
                                        href="?page=orders&view_order=<?= $order['order_id'] ?>&p=<?= $page ?>&search=<?= urlencode($search_term) ?>&status_filter=<?= urlencode($status_filter) ?>" 
                                        class="btn btn-outline"
                                        style="padding: 0.5rem 1rem;"
                                    >
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a 
                                        href="#" 
                                        class="btn btn-success"
                                        style="padding: 0.5rem 1rem;"
                                        onclick="printOrder(<?= $order['order_id'] ?>)"
                                    >
                                        <i class="fas fa-print"></i> Print
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <!-- Previous Button -->
                    <?php if ($page > 1): ?>
                        <a href="?page=orders&p=<?= $page - 1 ?>&search=<?= urlencode($search_term) ?>&status_filter=<?= urlencode($status_filter) ?>" 
                           class="btn btn-outline" 
                           aria-label="Previous Page">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline disabled" aria-disabled="true">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $range = 2;
                    $start = max(1, $page - $range);
                    $end = min($total_pages, $page + $range);

                    if ($start > 1): ?>
                        <a href="?page=orders&p=1&search=<?= urlencode($search_term) ?>&status_filter=<?= urlencode($status_filter) ?>" 
                           class="btn btn-outline" 
                           aria-label="Page 1">1</a>
                        <?php if ($start > 2): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=orders&p=<?= $i ?>&search=<?= urlencode($search_term) ?>&status_filter=<?= urlencode($status_filter) ?>" 
                           class="btn btn-outline <?= $i === $page ? 'btn-primary' : '' ?>" 
                           aria-label="Page <?= $i ?>" 
                           <?= $i === $page ? 'aria-current="page"' : '' ?>>
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php endif; ?>
                        <a href="?page=orders&p=<?= $total_pages ?>&search=<?= urlencode($search_term) ?>&status_filter=<?= urlencode($status_filter) ?>" 
                           class="btn btn-outline" 
                           aria-label="Last Page">
                            <?= $total_pages ?>
                        </a>
                    <?php endif; ?>

                    <!-- Next Button -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=orders&p=<?= $page + 1 ?>&search=<?= urlencode($search_term) ?>&status_filter=<?= urlencode($status_filter) ?>" 
                           class="btn btn-outline" 
                           aria-label="Next Page">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline disabled" aria-disabled="true">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-box-open" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                <h3>No Orders Found</h3>
                <p>No orders match your search criteria</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Order Details -->
    <?php if (!empty($order_details)): ?>
        <div class="order-detail-container">
            <div class="order-header">
                <div>
                    <div class="order-id">Order #<?= $order_details['order_id'] ?></div>
                    <div class="order-date">Placed on <?= date('F d, Y', strtotime($order_details['created_at'])) ?></div>
                </div>
                <div class="status-badge status-<?= strtolower($order_details['status']) ?>">
                    <?= htmlspecialchars($order_details['status']) ?>
                </div>
            </div>
            
            <div class="order-grid">
                <div>
                    <div class="order-section">
                        <h3 class="order-section-title">
                            <i class="fas fa-user"></i> Customer Information
                        </h3>
                        <div class="order-section-content">
                            <div class="detail-row">
                                <div class="detail-label">Name:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['username']) ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Email:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['email']) ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Phone:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['phone_number'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-section">
                        <h3 class="order-section-title">
                            <i class="fas fa-map-marker-alt"></i> Delivery Address
                        </h3>
                        <div class="order-section-content">
                            <div class="detail-row">
                                <div class="detail-label">Address Line 1:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['address_line1'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Address Line 2:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['address_line2'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">County:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['county'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Constituency:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['constituency'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Town:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['Town'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Postal Code:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['postal_code'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="order-section">
                        <h3 class="order-section-title">
                            <i class="fas fa-receipt"></i> Order Summary
                        </h3>
                        <div class="order-section-content">
                            <div class="detail-row">
                                <div class="detail-label">Payment Method:</div>
                                <div class="detail-value"><?= htmlspecialchars($order_details['payment_method']) ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Order Status:</div>
                                <div class="detail-value status-<?= strtolower($order_details['status']) ?>">
                                    <?= htmlspecialchars($order_details['status']) ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Total Amount:</div>
                                <div class="detail-value">Ksh <?= number_format($order_details['total_amount'], 2) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-section">
                        <h3 class="order-section-title">
                            <i class="fas fa-shopping-basket"></i> Order Items
                        </h3>
                        <div class="order-section-content">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #e2e8f0;">
                                        <th style="padding: 1rem; text-align: left;">Image</th>
                                        <th style="padding: 1rem; text-align: left;">Product</th>
                                        <th style="padding: 1rem; text-align: left;">Price</th>
                                        <th style="padding: 1rem; text-align: left;">Qty</th>
                                        <th style="padding: 1rem; text-align: left;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr style="border-bottom: 1px solid var(--light-gray);">
                                            <td style="padding: 1rem;">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?= $item['image_url'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="max-width: 60px; height: auto; border-radius: 4px;">
                                                <?php else: ?>
                                                    <div style="width: 60px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                                        <i class="fas fa-image" style="color: #ccc;"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 1rem;"><?= htmlspecialchars($item['name']) ?></td>
                                            <td style="padding: 1rem;">Ksh <?= number_format($item['price'], 2) ?></td>
                                            <td style="padding: 1rem;"><?= $item['quantity'] ?></td>
                                            <td style="padding: 1rem;">Ksh <?= number_format($item['subtotal'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr style="border-top: 2px solid var(--dark);">
                                        <td colspan="4" style="text-align: right; padding: 1rem; font-weight: bold;">Total:</td>
                                        <td style="padding: 1rem; font-weight: bold;">Ksh <?= number_format($order_details['total_amount'], 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tracking Update Form -->
        <div class="tracking-form-container">
            <h2 class="order-section-title">
                <i class="fas fa-truck-loading"></i> Update Tracking Status
            </h2>
            
            <form method="POST">
                <input type="hidden" name="order_id" value="<?= $order_details['order_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="form-grid">
                     <div class="form-group">
                    <label class="form-label"><i class="fas fa-flag"></i> Status *</label>
                    <select name="status" id="status-select" class="form-control" required>
                    <option value="">Select Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                                       
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Location</label>
                        <input 
                            type="text" 
                            name="location" 
                            class="form-control" 
                            placeholder="Enter current location"
                        >
                    </div>
                                         
                                        <div class="form-group form-group-full">
                        <label class="form-label"><i class="fas fa-sticky-note"></i> Notes</label>
                        <textarea 
                            name="notes" 
                            id="notes-textarea" 
                            class="form-control" 
                            placeholder="Enter tracking notes..."
                            style="min-height: 120px;"
                        ></textarea>
                    </div>

                <div class="action-buttons">
                    <button type="submit" name="update_tracking" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Update Tracking
                    </button>
                    <button type="button" class="btn btn-success" onclick="printOrder(<?= $order_details['order_id'] ?>)">
                        <i class="fas fa-print"></i> Print Order
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Tracking History -->
        <div class="order-detail-container">
            <h2 class="order-section-title">
                <i class="fas fa-history"></i> Tracking History
            </h2>
            
            <?php if (!empty($tracking_info)): ?>
                <div class="timeline">
                    <?php foreach ($tracking_info as $index => $track): ?>
                        <div class="timeline-item <?= $index === 0 ? 'active' : '' ?>">
                            <div class="timeline-icon">
                                <i class="fas <?= 
                                    $track['status'] === 'Shipped' ? 'fa-truck' : 
                                    ($track['status'] === 'Delivered' ? 'fa-check' : 'fa-box') 
                                ?>"></i>
                            </div>
                            <div class="timeline-date">
                                <i class="fas fa-clock"></i> <?= date('M d, Y h:i A', strtotime($track['created_at'])) ?>
                            </div>
                            <div class="timeline-status">
                                <?= htmlspecialchars($track['status']) ?>
                            </div>
                            <div class="timeline-location">
                                <i class="fas fa-map-pin"></i> 
                                <?= htmlspecialchars($track['location'] ?? 'N/A') ?>
                            </div>
                            <?php if (!empty($track['notes'])): ?>
                                <div class="timeline-notes">
                                    <i class="fas fa-info-circle"></i> <?= htmlspecialchars($track['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-map-marked-alt" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <h3>No Tracking Information</h3>
                    <p>No tracking updates have been added for this order yet.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Print Button -->
<?php if (!empty($order_details)): ?>
    <div class="print-btn" onclick="printOrder(<?= $order_details['order_id'] ?>)">
        <i class="fas fa-print"></i>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status-select');
    const notesTextarea = document.getElementById('notes-textarea');
    
    if (statusSelect && notesTextarea) {
        // Automate notes based on status selection
        statusSelect.addEventListener('change', function() {
            const status = this.value;
            let note = '';
            
            switch(status) {
                case 'Pending':
                    note = 'Payment being processed';
                    break;
                case 'Processing':
                    note = 'Being packaged for shipping';
                    break;
                case 'Shipped':
                    note = 'Ready for pickup';
                    break;
                case 'Delivered':
                    note = 'Already delivered to the customer';
                    break;
                case 'Cancelled':
                    note = 'Order has been cancelled';
                    break;
                default:
                    note = '';
            }
            
            notesTextarea.value = note;
        });
        
        // Set initial value if status is pre-selected
        const currentStatus = "<?= $order_details['status'] ?? '' ?>";
        if (currentStatus && statusSelect.value === currentStatus) {
            statusSelect.dispatchEvent(new Event('change'));
        }
    }
});
    // Initialize order status chart
    const ctx = document.getElementById('orderStatusChart');
    if (ctx) {
        const statusData = {
            'Pending': <?= $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'")->fetch_assoc()['count'] ?? 0 ?>,
            'Processing': <?= $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Processing'")->fetch_assoc()['count'] ?? 0 ?>,
            'Shipped': <?= $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Shipped'")->fetch_assoc()['count'] ?? 0 ?>,
            'Delivered': <?= $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Delivered'")->fetch_assoc()['count'] ?? 0 ?>,
            'Cancelled': <?= $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Cancelled'")->fetch_assoc()['count'] ?? 0 ?>
        };
        
        const orderStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(statusData),
                datasets: [{
                    data: Object.values(statusData),
                    backgroundColor: [
                        'rgba(241, 196, 15, 0.7)',
                        'rgba(52, 152, 219, 0.7)',
                        'rgba(46, 204, 113, 0.7)',
                        'rgba(46, 204, 113, 0.9)',
                        'rgba(231, 76, 60, 0.7)'
                    ],
                    borderColor: [
                        'rgba(241, 196, 15, 1)',
                        'rgba(52, 152, 219, 1)',
                        'rgba(46, 204, 113, 1)',
                        'rgba(46, 204, 113, 1)',
                        'rgba(231, 76, 60, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Order Status Distribution'
                    }
                }
            }
        });
    }
    
    // Print order function
    function printOrder(orderId) {
        const printWindow = window.open(`print_order.php?order_id=${orderId}`, '_blank');
        const printCheck = setInterval(() => {
            if (printWindow.closed) {
                clearInterval(printCheck);
                window.location.reload();
            }
        }, 500);
    }
    
    // Save scroll position before navigation
    function saveScrollPosition() {
        sessionStorage.setItem('scrollPosition', window.scrollY);
    }
    
    // Restore scroll position after page load
    document.addEventListener('DOMContentLoaded', function() {
        const scrollPosition = sessionStorage.getItem('scrollPosition');
        if (scrollPosition !== null) {
            window.scrollTo({
                top: parseInt(scrollPosition),
                behavior: 'auto'
            });
            sessionStorage.removeItem('scrollPosition');
        }
    });
    
    // Attach saveScrollPosition to pagination and view links
    document.querySelectorAll('.pagination a, .action-buttons a').forEach(link => {
        link.addEventListener('click', function() {
            saveScrollPosition();
        });
    });
</script>