<?php
session_start();
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_logic.php';
require_once __DIR__ . '/../../includes/customer_navbar.php';

require_active_session($conn, ['customer'], '../../index.php');

$customer_id = $_SESSION['user_id'];
ensure_free_gallon_redemption_usage_column($conn);

// Get filter
$status_filter = sanitize_status_filter($_GET['status'] ?? 'all', ['all', 'pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered', 'cancelled']);
$where_clause = '';
if ($status_filter !== 'all') {
    $where_clause = " AND o.order_status = ? ";
}

// Get all orders with details
$orders_query = "
    SELECT o.*, i.item_name, oi.quantity, oi.unit_price,
           COALESCE(fr.redeemed_free_gallons, 0) AS redeemed_free_gallons,
           d.delivery_status, d.delivered_at,
           s.full_name as staff_name
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN inventory i ON oi.inventory_id = i.inventory_id
    LEFT JOIN (
        SELECT used_order_id, COUNT(*) AS redeemed_free_gallons
        FROM free_gallon_redemptions
        WHERE status = 'used' AND used_order_id IS NOT NULL
        GROUP BY used_order_id
    ) fr ON fr.used_order_id = o.order_id
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

function customer_history_status_badge_class($status) {
    switch ($status) {
        case 'pending':
            return 'text-bg-warning';
        case 'confirmed':
            return 'text-bg-info';
        case 'processing':
            return 'text-bg-primary';
        case 'out_for_delivery':
            return 'text-bg-primary';
        case 'delivered':
            return 'text-bg-success';
        case 'cancelled':
            return 'text-bg-danger';
        default:
            return 'text-bg-secondary';
    }
}

function customer_history_delivery_badge_class($status) {
    switch ($status) {
        case 'assigned':
            return 'text-bg-info';
        case 'in_transit':
            return 'text-bg-primary';
        case 'delivered':
            return 'text-bg-success';
        case 'failed':
        case 'returned':
            return 'text-bg-danger';
        case 'cancelled':
            return 'text-bg-secondary';
        default:
            return 'text-bg-secondary';
    }
}

// Get counts for filter buttons
$counts_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN order_status = 'out_for_delivery' THEN 1 ELSE 0 END) as delivering,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        COUNT(*) as total
    FROM orders WHERE customer_id = ?
");
$counts_stmt->bind_param("i", $customer_id);
$counts_stmt->execute();
$counts = $counts_stmt->get_result()->fetch_assoc();
$counts_stmt->close();

$total_count = (int) ($counts['total'] ?? 0);
$pending_count = (int) ($counts['pending'] ?? 0);
$confirmed_count = (int) ($counts['confirmed'] ?? 0);
$processing_count = (int) ($counts['processing'] ?? 0);
$delivering_count = (int) ($counts['delivering'] ?? 0);
$delivered_count = (int) ($counts['delivered'] ?? 0);
$cancelled_count = (int) ($counts['cancelled'] ?? 0);
$active_count = $pending_count + $confirmed_count + $processing_count + $delivering_count;

$history_filter_options = [
    'all' => ['label' => 'All Orders', 'count' => $total_count, 'href' => 'history.php'],
    'pending' => ['label' => 'Pending', 'count' => $pending_count, 'href' => 'history.php?status=pending'],
    'confirmed' => ['label' => 'Confirmed', 'count' => $confirmed_count, 'href' => 'history.php?status=confirmed'],
    'processing' => ['label' => 'Processing', 'count' => $processing_count, 'href' => 'history.php?status=processing'],
    'out_for_delivery' => ['label' => 'Out for Delivery', 'count' => $delivering_count, 'href' => 'history.php?status=out_for_delivery'],
    'delivered' => ['label' => 'Delivered', 'count' => $delivered_count, 'href' => 'history.php?status=delivered'],
    'cancelled' => ['label' => 'Cancelled', 'count' => $cancelled_count, 'href' => 'history.php?status=cancelled'],
];
$current_filter_label = $history_filter_options[$status_filter]['label'] ?? 'All Orders';
$current_filter_count = $history_filter_options[$status_filter]['count'] ?? $total_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style/customer/history.css">
    <link rel="stylesheet" href="../../style/customer/navbar.css">
    <link rel="stylesheet" href="../../style/system_skeleton.css?v=20260527d">
</head>
<body class="bg-light system-loading skeleton-customer skeleton-customer-history">
    <?php render_customer_navbar('history'); ?>

    <main class="container py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
            <div>
                <span class="badge text-bg-primary mb-3">Order History</span>
                <h1 class="h3 fw-bold mb-2"><?php echo htmlspecialchars($current_filter_label); ?></h1>
                <p class="text-secondary mb-0">Review every order, delivery detail, and assignment history in one place.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="small text-secondary">Total Orders</div>
                        <div class="fs-4 fw-bold"><?php echo $total_count; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="small text-secondary">Active</div>
                        <div class="fs-4 fw-bold text-primary"><?php echo $active_count; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="small text-secondary">Delivered</div>
                        <div class="fs-4 fw-bold text-success"><?php echo $delivered_count; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="small text-secondary">Cancelled</div>
                        <div class="fs-4 fw-bold text-danger"><?php echo $cancelled_count; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-4">
            <div>
                <div class="small text-secondary">Order status</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($current_filter_label); ?> <span class="badge text-bg-primary ms-1"><?php echo $current_filter_count; ?></span></div>
            </div>
            <div class="dropdown">
                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo htmlspecialchars($current_filter_label); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-sm-end">
                    <?php foreach ($history_filter_options as $filter_key => $filter_option): ?>
                        <?php $filter_active = $status_filter === $filter_key; ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between gap-3 <?php echo $filter_active ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($filter_option['href']); ?>">
                                <span><?php echo htmlspecialchars($filter_option['label']); ?></span>
                                <span class="badge <?php echo $filter_active ? 'text-bg-light text-primary' : 'text-bg-primary'; ?>"><?php echo (int) $filter_option['count']; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <section class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
                    <div>
                        <h2 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($current_filter_label); ?></h2>
                        <p class="small text-secondary mb-0"><?php echo $orders->num_rows; ?> order(s) shown for this view.</p>
                    </div>
                    <?php if ($status_filter !== 'all'): ?>
                        <a href="history.php" class="btn btn-outline-secondary btn-sm">Clear Filter</a>
                    <?php endif; ?>
                </div>
            <?php if ($orders->num_rows > 0): ?>
                <div class="d-grid gap-3">
                <?php while ($order = $orders->fetch_assoc()): ?>
                <?php
                    $payment_method = order_payment_method_label($order['payment_method'] ?? 'cash_on_delivery');
                    $payment_status = order_payment_status_label($order['payment_status'] ?? 'pending');
                ?>
                <article class="card border">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-2 pb-3 mb-3 border-bottom">
                        <div>
                                <h2 class="h5 fw-bold mb-1">Order #<?php echo $order['order_id']; ?></h2>
                                <p class="small text-secondary mb-0"><?php echo htmlspecialchars($order['item_name']); ?> &middot; <?php echo date('M d, Y g:i A', strtotime($order['order_date'])); ?></p>
                        </div>
                            <div class="d-flex flex-wrap gap-2">
                            <span class="badge <?php echo customer_history_status_badge_class($order['order_status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                        </span>
                                <?php if ($order['delivery_status']): ?>
                                    <span class="badge <?php echo customer_history_delivery_badge_class($order['delivery_status']); ?>">
                                        Delivery: <?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                    </div>
                    
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-3">
                                <div class="small text-secondary">Item</div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($order['item_name']); ?></div>
                                <?php if ((int) ($order['redeemed_free_gallons'] ?? 0) > 0): ?>
                                    <div class="small text-success fw-semibold">Includes <?php echo (int) $order['redeemed_free_gallons']; ?> free gallon(s)</div>
                                <?php endif; ?>
                        </div>
                            <div class="col-6 col-md-3">
                                <div class="small text-secondary">Quantity</div>
                                <div class="fw-semibold">
                                    <?php echo (int) $order['quantity']; ?> gallons
                                    <?php if ((int) ($order['redeemed_free_gallons'] ?? 0) > 0): ?>
                                        <span class="small text-secondary">(<?php echo (int) $order['quantity'] - (int) $order['redeemed_free_gallons']; ?> paid + <?php echo (int) $order['redeemed_free_gallons']; ?> free)</span>
                                    <?php endif; ?>
                                </div>
                        </div>
                            <div class="col-6 col-md-3">
                                <div class="small text-secondary">Payment</div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($payment_method); ?></div>
                                <div class="small text-secondary"><?php echo htmlspecialchars($payment_status); ?></div>
                        </div>
                            <div class="col-6 col-md-3">
                                <div class="small text-secondary">Total Amount</div>
                                <div class="fw-bold text-primary">PHP <?php echo number_format($order['total_amount'], 2); ?></div>
                        </div>
                    </div>
                    
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="small text-secondary">Delivery Address</div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($order['delivery_address']); ?></div>
                        </div>
                            <div class="col-md-6">
                                <div class="small text-secondary">Contact Number</div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($order['contact_number']); ?></div>
                        </div>
                        <?php if ($order['delivery_date']): ?>
                            <div class="col-md-6">
                                <div class="small text-secondary">Preferred Delivery Date</div>
                                <div class="fw-semibold"><?php echo date('M d, Y', strtotime($order['delivery_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($order['notes']): ?>
                        <div class="alert alert-light border mt-3 mb-0">
                            <div class="small text-secondary fw-semibold mb-1">Notes</div>
                            <p class="mb-0"><?php echo htmlspecialchars($order['notes']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 pt-3 mt-3 border-top">
                        <div>
                            <?php if ($order['staff_name']): ?>
                                    <span class="badge text-bg-info">Assigned to <?php echo htmlspecialchars($order['staff_name']); ?></span>
                            <?php else: ?>
                                    <span class="badge text-bg-warning">Waiting for assignment</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($order['delivered_at']): ?>
                                <div class="small fw-semibold text-success">
                                Delivered on <?php echo date('M d, Y g:i A', strtotime($order['delivered_at'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    </div>
                </article>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <span class="badge text-bg-secondary mb-3">No Orders Found</span>
                    <h2 class="h5 fw-bold">No orders found</h2>
                    <p class="text-secondary">You don't have any <?php echo $status_filter !== 'all' ? htmlspecialchars(str_replace('_', ' ', $status_filter)) : ''; ?> orders yet.</p>
                    <?php if ($status_filter === 'all'): ?>
                        <a href="dashboard.php" class="btn btn-primary">Place Your First Order</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../scripts/system_skeleton.js?v=20260527c"></script>
</body>
</html>
