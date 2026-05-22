<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin_page_helpers.php';
require_once '../../includes/admin_order_helpers.php';
require_once '../../includes/address_helpers.php';
require_once '../../includes/delivery_batch_helpers.php';

/** @var mysqli $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is not available.');
}

$admin_user = require_active_session($conn, ['admin'], '../../index.php');
$admin_name = $admin_user['full_name'] ?? ($_SESSION['full_name'] ?? 'Administrator');

ensure_delivery_service_area_schema($conn);
ensure_delivery_batch_schema($conn);

$today = date('Y-m-d');
$total_orders = admin_count_query($conn, "SELECT COUNT(*) as count FROM orders");
$pending_orders = admin_count_query($conn, "SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'");
$confirmed_orders = admin_count_query($conn, "SELECT COUNT(*) as count FROM orders WHERE order_status = 'confirmed'");
$processing_orders = admin_count_query($conn, "SELECT COUNT(*) as count FROM orders WHERE order_status = 'processing'");
$delivering_orders = admin_count_query($conn, "SELECT COUNT(*) as count FROM orders WHERE order_status = 'out_for_delivery'");
$delivered_today = admin_count_query($conn, "SELECT COUNT(*) as count FROM deliveries WHERE delivery_status = 'delivered' AND DATE(delivered_at) = CURDATE()");
$today_orders = admin_count_query($conn, "SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) = CURDATE()");
$cancelled_today = admin_count_query($conn, "SELECT COUNT(*) as count FROM orders WHERE order_status = 'cancelled' AND DATE(updated_at) = CURDATE()");
$cancelled_orders = admin_count_query($conn, "SELECT COUNT(*) as count FROM orders WHERE order_status = 'cancelled'");
$failed_deliveries = admin_count_query($conn, "SELECT COUNT(*) as count FROM deliveries WHERE delivery_status = 'failed'");
$returned_deliveries = admin_count_query($conn, "SELECT COUNT(*) as count FROM deliveries WHERE delivery_status = 'returned'");
$failed_today = admin_count_query($conn, "SELECT COUNT(*) as count FROM deliveries WHERE delivery_status = 'failed' AND DATE(COALESCE(delivered_at, assigned_at)) = CURDATE()");
$returned_today = admin_count_query($conn, "SELECT COUNT(*) as count FROM deliveries WHERE delivery_status = 'returned' AND DATE(COALESCE(delivered_at, assigned_at)) = CURDATE()");
$active_deliveries = admin_count_query($conn, "SELECT COUNT(*) as count FROM deliveries WHERE delivery_status IN ('assigned', 'in_transit')");
$due_today = admin_count_query($conn, "
    SELECT COUNT(*) as count
    FROM orders
    WHERE order_status IN ('confirmed', 'processing', 'out_for_delivery')
        AND (delivery_date IS NULL OR delivery_date = '0000-00-00' OR delivery_date <= CURDATE())
");
$today_revenue = (float) admin_scalar_query($conn, "
    SELECT COALESCE(SUM(o.total_amount), 0) AS total
    FROM orders o
    JOIN deliveries d ON d.order_id = o.order_id
    WHERE d.delivery_status = 'delivered' AND DATE(d.delivered_at) = CURDATE()
", 'total', 0);
$total_customers = admin_count_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
$active_staff = admin_count_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND status = 'active'");

$active_queue_rows = admin_delivery_queue_rows($conn, 'active');
$scheduled_queue_rows = admin_delivery_queue_rows($conn, 'scheduled');
$draft_batches = admin_count_query($conn, "SELECT COUNT(*) as count FROM delivery_batches WHERE batch_status = 'draft'");
$confirmed_batches = admin_count_query($conn, "SELECT COUNT(*) as count FROM delivery_batches WHERE batch_status = 'confirmed'");
$active_batches = admin_count_query($conn, "SELECT COUNT(*) as count FROM delivery_batches WHERE batch_status IN ('assigned', 'in_transit')");
$low_stock_count = admin_count_query($conn, "SELECT COUNT(*) as count FROM inventory WHERE stock_quantity <= reorder_level OR status = 'out_of_stock'");
$out_of_stock_count = admin_count_query($conn, "SELECT COUNT(*) as count FROM inventory WHERE status = 'out_of_stock'");
$exception_count = $cancelled_orders + $failed_deliveries + $returned_deliveries;

$low_stock_result = $conn->query("
    SELECT inventory_id, item_name, stock_quantity, reorder_level, status
    FROM inventory
    WHERE stock_quantity <= reorder_level OR status = 'out_of_stock'
    ORDER BY stock_quantity ASC, item_name ASC
    LIMIT 6
");
$low_stock_rows = $low_stock_result ? $low_stock_result->fetch_all(MYSQLI_ASSOC) : [];

$recent_orders_result = $conn->query("
    SELECT o.order_id, o.order_status, o.total_amount, o.order_date, c.full_name AS customer_name
    FROM orders o
    JOIN users c ON c.user_id = o.customer_id
    ORDER BY o.order_date DESC, o.order_id DESC
    LIMIT 8
");
$recent_order_rows = $recent_orders_result ? $recent_orders_result->fetch_all(MYSQLI_ASSOC) : [];

$rider_workload_result = $conn->query("
    SELECT
        u.user_id,
        u.full_name,
        COALESCE(SUM(CASE WHEN d.delivery_status = 'assigned' THEN 1 ELSE 0 END), 0) AS assigned_orders,
        COALESCE(SUM(CASE WHEN d.delivery_status = 'in_transit' THEN 1 ELSE 0 END), 0) AS in_transit_orders,
        COALESCE(batch_counts.active_batches, 0) AS active_batches
    FROM users u
    LEFT JOIN deliveries d ON d.staff_id = u.user_id AND d.delivery_status IN ('assigned', 'in_transit')
    LEFT JOIN (
        SELECT staff_id, COUNT(*) AS active_batches
        FROM delivery_batches
        WHERE batch_status IN ('assigned', 'in_transit')
        GROUP BY staff_id
    ) batch_counts ON batch_counts.staff_id = u.user_id
    WHERE u.role = 'staff' AND u.status = 'active'
    GROUP BY u.user_id, u.full_name, batch_counts.active_batches
    ORDER BY active_batches DESC, in_transit_orders DESC, assigned_orders DESC, u.full_name ASC
    LIMIT 6
");
$rider_workload_rows = $rider_workload_result ? $rider_workload_result->fetch_all(MYSQLI_ASSOC) : [];

$recent_delivery_result = $conn->query("
    SELECT
        d.delivery_id,
        d.delivery_status,
        d.assigned_at,
        d.delivered_at,
        o.order_id,
        c.full_name AS customer_name,
        staff.full_name AS staff_name
    FROM deliveries d
    JOIN orders o ON o.order_id = d.order_id
    JOIN users c ON c.user_id = o.customer_id
    LEFT JOIN users staff ON staff.user_id = d.staff_id
    ORDER BY COALESCE(d.delivered_at, d.assigned_at) DESC, d.delivery_id DESC
    LIMIT 8
");
$recent_delivery_rows = $recent_delivery_result ? $recent_delivery_result->fetch_all(MYSQLI_ASSOC) : [];

$order_status_counts = [
    'Pending' => $pending_orders,
    'Confirmed' => $confirmed_orders,
    'Processing' => $processing_orders,
    'Out for Delivery' => $delivering_orders,
    'Delivered' => admin_count_query($conn, "SELECT COUNT(*) as count FROM orders WHERE order_status = 'delivered'"),
    'Cancelled' => $cancelled_orders,
];

$delivery_outcome_counts = [
    'Delivered' => $delivered_today,
    'Failed' => $failed_today,
    'Returned' => $returned_today,
    'Cancelled' => $cancelled_today,
];

$trend_rows = [];
$trend_result = $conn->query("
    SELECT DATE(order_date) AS report_date, COUNT(*) AS total_orders
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(order_date)
    ORDER BY report_date ASC
");
if ($trend_result) {
    while ($row = $trend_result->fetch_assoc()) {
        $trend_rows[$row['report_date']] = (int) ($row['total_orders'] ?? 0);
    }
}

$trend_labels = [];
$trend_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date_key = date('Y-m-d', strtotime("-$i days"));
    $trend_labels[] = date('M d', strtotime($date_key));
    $trend_values[] = $trend_rows[$date_key] ?? 0;
}

$chart_payload = [
    'orderTrend' => [
        'labels' => $trend_labels,
        'values' => $trend_values,
    ],
    'orderStatus' => [
        'labels' => array_keys($order_status_counts),
        'values' => array_values($order_status_counts),
    ],
    'deliveryOutcomes' => [
        'labels' => array_keys($delivery_outcome_counts),
        'values' => array_values($delivery_outcome_counts),
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../style/admin/admin_compact.css?v=20260516" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-0">
        <div class="row g-0">
        <aside class="admin-floating-sidebar col-lg-3 col-xl-2 text-white bg-israphil-sidebar d-flex flex-column p-3 min-vh-100">
            <a href="dashboard.php" class="d-flex align-items-center gap-2 text-white text-decoration-none fs-4 mb-3">
                <span class="bg-white rounded d-inline-flex align-items-center justify-content-center p-1">
                    <img src="../../image.gif/favicon.png" alt="ISRAPHIL logo" width="32" height="32">
                </span>
                <span class="fw-semibold">ISRAPHIL</span>
            </a>

            <hr class="border-secondary">

            <nav class="nav nav-pills flex-column gap-1 mb-auto">
                <div class="px-3 mt-2 mb-1 small text-uppercase text-white-50 fw-semibold">Main Operations</div>
                <a href="dashboard.php" class="nav-link active d-flex align-items-center gap-2" aria-current="page">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>
                <a href="orders.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    <span>Orders</span>
                </a>
                <button class="nav-link border-0 w-100 d-flex align-items-center gap-2 admin-order-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#deliveriesMenu" aria-expanded="false" aria-controls="deliveriesMenu">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Deliveries</span>
                </button>
                <div class="collapse" id="deliveriesMenu">
                    <div class="admin-order-subnav">
                        <a href="order_batches.php">Order Batches</a>
                        <a href="batch_assignments.php">Batch Assignment</a>
                    </div>
                </div>
                <a href="exceptions.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    <span>Exceptions</span>
                </a>
                <div class="px-3 mt-3 mb-1 small text-uppercase text-white-50 fw-semibold">Management</div>
                <a href="inventory.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <span>Inventory</span>
                </a>
                <a href="staff.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <span>Staff</span>
                </a>
                <a href="customers.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    <span>Customers</span>
                </a>
                <div class="px-3 mt-3 mb-1 small text-uppercase text-white-50 fw-semibold">Reports</div>
                <a href="reports.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart" aria-hidden="true"></i>
                    <span>Reports</span>
                </a>
            </nav>

            <hr class="border-secondary">

            <div class="px-3 mb-2 small text-uppercase text-white-50 fw-semibold">Account / System</div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center gap-2 text-white text-decoration-none dropdown-toggle fw-bold" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="badge rounded-circle text-bg-warning p-2">
                        <?php echo admin_initial($admin_name); ?>
                    </span>
                    <span><?php echo htmlspecialchars($admin_name); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                    <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                </ul>
            </div>
        </aside>

        <div class="admin-floating-content col-lg-9 col-xl-10 min-vh-100">
            <nav class="navbar bg-white border-bottom shadow-sm px-3 px-lg-4">
                <div>
                    <span class="navbar-brand mb-0 h1 fw-bold text-primary">ISRAPHIL Admin Dashboard</span>
                    <div class="small text-secondary">Administrator: <?php echo htmlspecialchars($admin_name); ?></div>
                </div>
            </nav>

            <main class="container-fluid px-3 px-lg-4 py-4">
                <section class="mb-4">
                    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
                        <div>
                            <span class="badge text-bg-primary mb-2">Operations Dashboard</span>
                            <h1 class="h3 fw-bold mb-1">What needs attention right now?</h1>
                            <p class="text-secondary mb-0">A quick view of orders, delivery movement, exceptions, stock risk, and today&apos;s performance.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="orders.php?status=pending" class="btn btn-warning btn-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-receipt" aria-hidden="true"></i>Review Pending
                            </a>
                            <a href="order_batches.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-boxes" aria-hidden="true"></i>Plan Batches
                            </a>
                            <a href="exceptions.php" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Exceptions
                            </a>
                        </div>
                    </div>
                </section>

                <section class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl">
                        <a href="orders.php?status=pending" class="card h-100 border-0 shadow-sm text-decoration-none admin-attention-card <?php echo $pending_orders > 0 ? 'is-warning' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2 align-items-start">
                                    <span class="text-secondary small fw-semibold text-uppercase">Pending Orders</span>
                                    <i class="bi bi-hourglass-split text-warning" aria-hidden="true"></i>
                                </div>
                                <div class="display-6 fw-bold text-warning mt-2"><?php echo $pending_orders; ?></div>
                                <div class="small text-secondary"><?php echo $today_orders; ?> new today</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl">
                        <a href="order_batches.php" class="card h-100 border-0 shadow-sm text-decoration-none admin-attention-card <?php echo count($active_queue_rows) > 0 ? 'is-success' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2 align-items-start">
                                    <span class="text-secondary small fw-semibold text-uppercase">Ready to Batch</span>
                                    <i class="bi bi-boxes text-success" aria-hidden="true"></i>
                                </div>
                                <div class="display-6 fw-bold text-success mt-2"><?php echo count($active_queue_rows); ?></div>
                                <div class="small text-secondary"><?php echo $due_today; ?> due now</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl">
                        <a href="batch_assignments.php" class="card h-100 border-0 shadow-sm text-decoration-none admin-attention-card <?php echo $active_deliveries > 0 ? 'is-primary' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2 align-items-start">
                                    <span class="text-secondary small fw-semibold text-uppercase">Active Deliveries</span>
                                    <i class="bi bi-truck text-primary" aria-hidden="true"></i>
                                </div>
                                <div class="display-6 fw-bold text-primary mt-2"><?php echo $active_deliveries; ?></div>
                                <div class="small text-secondary"><?php echo $confirmed_batches; ?> batches waiting</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl">
                        <a href="exceptions.php" class="card h-100 border-0 shadow-sm text-decoration-none admin-attention-card <?php echo $exception_count > 0 ? 'is-danger' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2 align-items-start">
                                    <span class="text-secondary small fw-semibold text-uppercase">Exceptions</span>
                                    <i class="bi bi-exclamation-triangle text-danger" aria-hidden="true"></i>
                                </div>
                                <div class="display-6 fw-bold text-danger mt-2"><?php echo $exception_count; ?></div>
                                <div class="small text-secondary"><?php echo $failed_deliveries + $returned_deliveries; ?> delivery issues</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl">
                        <a href="inventory.php" class="card h-100 border-0 shadow-sm text-decoration-none admin-attention-card <?php echo $low_stock_count > 0 ? 'is-warning' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2 align-items-start">
                                    <span class="text-secondary small fw-semibold text-uppercase">Low Stock</span>
                                    <i class="bi bi-exclamation-circle text-warning" aria-hidden="true"></i>
                                </div>
                                <div class="display-6 fw-bold text-warning mt-2"><?php echo $low_stock_count; ?></div>
                                <div class="small text-secondary"><?php echo $out_of_stock_count; ?> out of stock</div>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Orders Today</span>
                                <div class="display-6 fw-bold text-primary mt-2"><?php echo $today_orders; ?></div>
                                <div class="small text-secondary"><?php echo $total_orders; ?> total orders</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Delivered Today</span>
                                <div class="display-6 fw-bold text-success mt-2"><?php echo $delivered_today; ?></div>
                                <div class="small text-secondary">&#8369;<?php echo number_format($today_revenue, 2); ?> completed value</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="batch_assignments.php" class="card h-100 border-0 shadow-sm text-decoration-none">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Active Batches</span>
                                <div class="display-6 fw-bold text-info mt-2"><?php echo $active_batches; ?></div>
                                <div class="small text-secondary"><?php echo $active_staff; ?> active riders</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="exceptions.php?type=cancelled" class="card h-100 border-0 shadow-sm text-decoration-none">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Cancelled Today</span>
                                <div class="display-6 fw-bold text-secondary mt-2"><?php echo $cancelled_today; ?></div>
                                <div class="small text-secondary"><?php echo $cancelled_orders; ?> all-time cancelled</div>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="row g-3 mb-4">
                    <div class="col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-start">
                                    <div>
                                        <h2 class="h4 fw-bold mb-1">Orders Last 7 Days</h2>
                                        <p class="text-secondary mb-0">Daily order volume for quick demand monitoring.</p>
                                    </div>
                                    <a href="reports.php" class="btn btn-outline-primary btn-sm">Open reports</a>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="admin-chart-wrap">
                                    <canvas id="orderTrendChart" aria-label="Orders last seven days chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h2 class="h4 fw-bold mb-1">Status Breakdown</h2>
                                <p class="text-secondary mb-0">Current order state and today's delivery outcomes.</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="small fw-semibold text-secondary text-uppercase mb-2">Order Status</div>
                                        <div class="admin-chart-wrap admin-chart-wrap-sm">
                                            <canvas id="orderStatusChart" aria-label="Order status chart"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small fw-semibold text-secondary text-uppercase mb-2">Today Delivery Outcomes</div>
                                        <div class="admin-chart-wrap admin-chart-wrap-sm">
                                            <canvas id="deliveryOutcomeChart" aria-label="Today's delivery outcomes chart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row g-3 mb-4">
                    <div class="col-xl-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                                    <div>
                                        <h2 class="h4 fw-bold mb-1">Operations Queue</h2>
                                        <p class="text-secondary mb-0">Current work that needs admin attention.</p>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="orders.php" class="btn btn-outline-primary btn-sm">Orders</a>
                                        <a href="order_batches.php" class="btn btn-outline-primary btn-sm">Batches</a>
                                        <a href="batch_assignments.php" class="btn btn-outline-primary btn-sm">Assign</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6 col-xl-4">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Confirmed</div>
                                            <div class="h4 fw-bold mb-1"><?php echo $confirmed_orders; ?></div>
                                            <a href="orders.php?status=confirmed" class="small">Open confirmed</a>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Due Today</div>
                                            <div class="h4 fw-bold mb-1"><?php echo $due_today; ?></div>
                                            <a href="order_batches.php" class="small">Plan delivery</a>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Processing</div>
                                            <div class="h4 fw-bold mb-1"><?php echo $processing_orders; ?></div>
                                            <a href="orders.php?status=processing" class="small">Review</a>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Out For Delivery</div>
                                            <div class="h4 fw-bold mb-1"><?php echo $delivering_orders; ?></div>
                                            <a href="orders.php?status=out_for_delivery" class="small">Track</a>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Draft Batches</div>
                                            <div class="h4 fw-bold mb-1"><?php echo $draft_batches; ?></div>
                                            <a href="order_batches.php" class="small">Review drafts</a>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Coverage</div>
                                            <div class="h4 fw-bold mb-1"><?php echo $active_staff; ?></div>
                                            <a href="staff.php" class="small">Active riders</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h2 class="h4 fw-bold mb-1">Low Stock Watch</h2>
                                <p class="text-secondary mb-0">Items at or below reorder level.</p>
                            </div>
                            <div class="card-body p-4">
                                <?php if (!empty($low_stock_rows)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($low_stock_rows as $item):
                                            $inventory_id = (int) ($item['inventory_id'] ?? 0);
                                            $stock_quantity = (int) ($item['stock_quantity'] ?? 0);
                                            $reorder_level = (int) ($item['reorder_level'] ?? 0);
                                        ?>
                                        <a href="inventory.php?edit=<?php echo $inventory_id; ?>" class="list-group-item list-group-item-action px-0 d-flex justify-content-between gap-3">
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($item['item_name'] ?? 'Item'); ?></div>
                                                <div class="small text-secondary">Reorder at <?php echo $reorder_level; ?></div>
                                            </div>
                                            <span class="badge rounded-pill <?php echo $stock_quantity < 1 ? 'text-bg-danger' : 'text-bg-warning'; ?> align-self-center">
                                                <?php echo $stock_quantity; ?> left
                                            </span>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-secondary">Stock levels are above reorder limits.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row g-3">
                    <div class="col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h2 class="h4 fw-bold mb-1">Recent Orders</h2>
                                <p class="text-secondary mb-0">Latest orders entering the system.</p>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($recent_order_rows)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">Order</th>
                                                <th scope="col">Customer</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Total</th>
                                                <th scope="col">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_order_rows as $order): ?>
                                            <tr>
                                                <td class="fw-semibold">#<?php echo (int) ($order['order_id'] ?? 0); ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Customer'); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill <?php echo admin_order_status_badge_class($order['order_status'] ?? 'pending'); ?>">
                                                        <?php echo admin_order_status_label($order['order_status'] ?? 'pending'); ?>
                                                    </span>
                                                </td>
                                                <td>&#8369;<?php echo number_format((float) ($order['total_amount'] ?? 0), 2); ?></td>
                                                <td class="small"><?php echo htmlspecialchars(admin_format_date($order['order_date'] ?? '')); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                    <div class="p-4 text-secondary">No orders yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h2 class="h4 fw-bold mb-1">Rider Workload</h2>
                                <p class="text-secondary mb-0">Active rider assignments and batches.</p>
                            </div>
                            <div class="card-body p-4">
                                <?php if (!empty($rider_workload_rows)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($rider_workload_rows as $rider):
                                            $assigned_orders = (int) ($rider['assigned_orders'] ?? 0);
                                            $in_transit_orders = (int) ($rider['in_transit_orders'] ?? 0);
                                            $rider_batches = (int) ($rider['active_batches'] ?? 0);
                                        ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between gap-3">
                                                <div class="fw-semibold"><?php echo htmlspecialchars($rider['full_name'] ?? 'Rider'); ?></div>
                                                <span class="badge rounded-pill text-bg-primary"><?php echo $rider_batches; ?> batch<?php echo $rider_batches === 1 ? '' : 'es'; ?></span>
                                            </div>
                                            <div class="small text-secondary">
                                                <?php echo $assigned_orders; ?> assigned orders · <?php echo $in_transit_orders; ?> in transit
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-secondary">No active riders found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row g-3 mt-1">
                    <div class="col-xl-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h2 class="h4 fw-bold mb-1">Recent Delivery Activity</h2>
                                <p class="text-secondary mb-0">Latest rider movement and delivery outcomes.</p>
                            </div>
                            <div class="card-body p-4">
                                <?php if (!empty($recent_delivery_rows)): ?>
                                    <div class="row g-3">
                                        <?php foreach ($recent_delivery_rows as $delivery):
                                            $delivery_status = (string) ($delivery['delivery_status'] ?? 'assigned');
                                            $delivery_time = $delivery['delivered_at'] ?: ($delivery['assigned_at'] ?? '');
                                        ?>
                                        <div class="col-md-6 col-xl-3">
                                            <div class="admin-queue-tile">
                                                <div class="d-flex justify-content-between gap-3">
                                                    <div class="fw-semibold">Order #<?php echo (int) ($delivery['order_id'] ?? 0); ?></div>
                                                    <span class="badge rounded-pill <?php echo admin_delivery_status_badge_class($delivery_status); ?>">
                                                        <?php echo admin_delivery_status_label($delivery_status); ?>
                                                    </span>
                                                </div>
                                                <div class="small text-secondary mt-2">
                                                    <?php echo htmlspecialchars($delivery['customer_name'] ?? 'Customer'); ?>
                                                    &middot;
                                                    <?php echo htmlspecialchars($delivery['staff_name'] ?? 'Unassigned'); ?>
                                                </div>
                                                <div class="small text-secondary"><?php echo htmlspecialchars(admin_format_date($delivery_time, 'M d, g:i A')); ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-secondary">No delivery activity found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        window.adminDashboardCharts = <?php echo json_encode($chart_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const dashboardCharts = window.adminDashboardCharts || {};
        const chartColors = ['#0d6efd', '#20c997', '#ffc107', '#0dcaf0', '#198754', '#dc3545'];

        function renderDashboardCharts() {
            if (!window.Chart) {
                return;
            }

            const orderTrendCanvas = document.getElementById('orderTrendChart');
            if (orderTrendCanvas && dashboardCharts.orderTrend) {
                new Chart(orderTrendCanvas, {
                    type: 'bar',
                    data: {
                        labels: dashboardCharts.orderTrend.labels,
                        datasets: [{
                            label: 'Orders',
                            data: dashboardCharts.orderTrend.values,
                            backgroundColor: '#0d6efd',
                            borderRadius: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                            },
                        },
                    },
                });
            }

            const orderStatusCanvas = document.getElementById('orderStatusChart');
            if (orderStatusCanvas && dashboardCharts.orderStatus) {
                new Chart(orderStatusCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: dashboardCharts.orderStatus.labels,
                        datasets: [{
                            data: dashboardCharts.orderStatus.values,
                            backgroundColor: chartColors,
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10 },
                            },
                        },
                    },
                });
            }

            const deliveryOutcomeCanvas = document.getElementById('deliveryOutcomeChart');
            if (deliveryOutcomeCanvas && dashboardCharts.deliveryOutcomes) {
                new Chart(deliveryOutcomeCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: dashboardCharts.deliveryOutcomes.labels,
                        datasets: [{
                            data: dashboardCharts.deliveryOutcomes.values,
                            backgroundColor: ['#198754', '#dc3545', '#fd7e14', '#6c757d'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10 },
                            },
                        },
                    },
                });
            }
        }

        renderDashboardCharts();
    </script>
</body>
</html>
