<?php
// Pagination setup
$current_page = $_GET['page'] ?? 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

try {
    $stmt = $conn->prepare("
        SELECT o.order_id, o.total_amount, o.created_at, 
               COUNT(oi.product_id) AS item_count 
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $_SESSION['user_id'], $items_per_page, $offset);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $count_stmt->bind_param('i', $_SESSION['user_id']);
    $count_stmt->execute();
    $total_orders = $count_stmt->get_result()->fetch_row()[0];
    $total_pages = ceil($total_orders / $items_per_page);
} catch(Exception $e) {
    error_log("Order history error: " . $e->getMessage());
    die("Error loading order history");
}
?>

<div class="order-history">
    <?php if (count($orders) > 0): ?>
        <?php foreach ($orders as $order): ?>
        <div class="order-item">
            <div class="order-header">
                <span class="order-id">#<?= htmlspecialchars($order['order_id']) ?></span>
                <span class="order-date"><?= date('M j, Y', strtotime($order['created_at'])) ?></span>
                <span class="order-total">Ksh <?= number_format($order['total_amount'], 2) ?></span>
            </div>
            <div class="order-details">
                <span><?= $order['item_count'] ?> items</span>
                <a href="order-details.php?id=<?= $order['order_id'] ?>" class="view-details">
                    View Details <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i == $current_page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php else: ?>
        <p>No orders found.</p>
    <?php endif; ?>
</div>