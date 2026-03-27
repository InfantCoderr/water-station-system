<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/order_logic.php';

require_active_session($conn, ['customer'], '../../index.php');

$customer_id = $_SESSION['user_id'];

// Get filter
$status_filter = sanitize_status_filter($_GET['status'] ?? 'all', ['all', 'pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered', 'cancelled']);
$where_clause = '';
if ($status_filter !== 'all') {
    $where_clause = " AND o.order_status = ? ";
}

// Get all orders with details
$orders_query = "
    SELECT o.*, i.item_name, oi.quantity, oi.unit_price,
           d.delivery_status, d.delivered_at,
           s.full_name as staff_name
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN inventory i ON oi.inventory_id = i.inventory_id
    LEFT JOIN deliveries d ON o.order_id = d.order_id
    LEFT JOIN users s ON d.staff_id = s.user_id
    WHERE o.customer_id = ? $where_clause
    ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($orders_query);
if ($status_filter !== 'all') {
    $stmt->bind_param("is", $customer_id, $status_filter);
} else {
    $stmt->bind_param("i", $customer_id);
}
$stmt->execute();
$orders = $stmt->get_result();

// Get counts for filter buttons
$counts = $conn->query("
    SELECT 
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN order_status = 'out_for_delivery' THEN 1 ELSE 0 END) as delivering,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        COUNT(*) as total
    FROM orders WHERE customer_id = $customer_id
")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link rel="stylesheet" href="../../style/customer/history.css?v=20260325">
   
</head>
<body>
    <div class="header">
        <h1>ISRAPHIL Water Station</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        
        <div class="nav-tabs">
            <a href="dashboard.php" class="nav-tab">Place Order</a>
            <a href="history.php" class="nav-tab active">Order History</a>
            <a href="profile.php" class="nav-tab">My Profile</a>
        </div>

        <!-- Filter Buttons -->
        <div class="filter-bar">
            <a href="history.php" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                All <span class="count"><?php echo $counts['total']; ?></span>
            </a>
            <a href="history.php?status=pending" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                Pending <span class="count"><?php echo $counts['pending']; ?></span>
            </a>
            <a href="history.php?status=confirmed" class="filter-btn <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">
                Confirmed <span class="count"><?php echo $counts['confirmed']; ?></span>
            </a>
            <a href="history.php?status=out_for_delivery" class="filter-btn <?php echo $status_filter == 'out_for_delivery' ? 'active' : ''; ?>">
                Out for Delivery <span class="count"><?php echo $counts['delivering']; ?></span>
            </a>
            <a href="history.php?status=delivered" class="filter-btn <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
                Delivered <span class="count"><?php echo $counts['delivered']; ?></span>
            </a>
            <a href="history.php?status=cancelled" class="filter-btn <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
                Cancelled <span class="count"><?php echo $counts['cancelled']; ?></span>
            </a>
        </div>

        <!-- Orders List -->
        <div class="section">
            <div class="section-header">
                <div>
                    <h2><?php echo $status_filter !== 'all' ? ucfirst(str_replace('_', ' ', $status_filter)) : 'All'; ?> Orders</h2>
                    <p class="section-copy">Review every order, delivery detail, and assignment history in one place.</p>
                </div>
            </div>
            
            <?php if ($orders->num_rows > 0): ?>
                <?php while ($order = $orders->fetch_assoc()): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                            <div class="order-date"><?php echo date('F d, Y g:i A', strtotime($order['order_date'])); ?></div>
                        </div>
                        <span class="status status-<?php echo $order['order_status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                        </span>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-group">
                            <span class="detail-label">Item</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['item_name']); ?></span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Quantity</span>
                            <span class="detail-value"><?php echo $order['quantity']; ?> gallons</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Price per Unit</span>
                            <span class="detail-value">&#8369;<?php echo number_format($order['unit_price'], 2); ?></span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Total Amount</span>
                            <span class="detail-value price">&#8369;<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="order-details" style="margin-top: 15px;">
                        <div class="detail-group">
                            <span class="detail-label">Delivery Address</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['delivery_address']); ?></span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Contact Number</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['contact_number']); ?></span>
                        </div>
                        <?php if ($order['delivery_date']): ?>
                        <div class="detail-group">
                            <span class="detail-label">Preferred Delivery Date</span>
                            <span class="detail-value"><?php echo date('M d, Y', strtotime($order['delivery_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($order['notes']): ?>
                    <div class="note-card">
                        <span class="detail-label">Notes:</span>
                        <p><?php echo htmlspecialchars($order['notes']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="delivery-info">
                        <div>
                            <?php if ($order['staff_name']): ?>
                                <span class="staff-badge">Assigned to: <?php echo htmlspecialchars($order['staff_name']); ?></span>
                            <?php else: ?>
                                <span class="staff-badge pending">Waiting for assignment</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($order['delivered_at']): ?>
                            <div class="delivery-stamp">
                                Delivered on <?php echo date('M d, Y g:i A', strtotime($order['delivered_at'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">OH</div>
                    <h3>No orders found</h3>
                    <p>You don't have any <?php echo $status_filter !== 'all' ? $status_filter : ''; ?> orders yet.</p>
                    <?php if ($status_filter === 'all'): ?>
                        <a href="dashboard.php" class="nav-tab">Place Your First Order</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
