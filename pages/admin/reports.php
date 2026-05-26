<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin_page_helpers.php';
require_once '../../includes/admin_order_helpers.php';

/** @var mysqli $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is not available.');
}

$admin_user = require_active_session($conn, ['admin'], '../../index.php');
$admin_name = $admin_user['full_name'] ?? ($_SESSION['full_name'] ?? 'Administrator');

$today = date('Y-m-d');
$report_date = trim((string) ($_GET['date'] ?? $today));
$date_parts = explode('-', $report_date);
if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date) ||
    count($date_parts) !== 3 ||
    !checkdate((int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0])
) {
    $report_date = $today;
}

function admin_report_single_row($conn, $sql, $date_value) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Admin report prepare failed: ' . $conn->error);
        return [];
    }

    $stmt->bind_param('s', $date_value);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: [];
}

$order_summary = admin_report_single_row($conn, "
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
        SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
        COALESCE(SUM(total_amount), 0) AS gross_orders
    FROM orders
    WHERE DATE(order_date) = ?
", $report_date);

$delivery_summary = admin_report_single_row($conn, "
    SELECT
        COUNT(*) AS delivery_activity,
        SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) AS completed_deliveries,
        SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) AS failed_deliveries,
        SUM(CASE WHEN delivery_status = 'returned' THEN 1 ELSE 0 END) AS returned_deliveries
    FROM deliveries
    WHERE DATE(COALESCE(delivered_at, assigned_at)) = ?
", $report_date);

$exception_summary = [
    'cancelled_orders' => admin_count_query($conn, "SELECT COUNT(*) AS count FROM orders WHERE order_status = 'cancelled' AND DATE(updated_at) = '" . $conn->real_escape_string($report_date) . "'"),
    'failed_deliveries' => admin_count_query($conn, "SELECT COUNT(*) AS count FROM deliveries WHERE delivery_status = 'failed' AND DATE(assigned_at) = '" . $conn->real_escape_string($report_date) . "'"),
    'returned_deliveries' => admin_count_query($conn, "SELECT COUNT(*) AS count FROM deliveries WHERE delivery_status = 'returned' AND DATE(assigned_at) = '" . $conn->real_escape_string($report_date) . "'"),
];

$today_revenue = (float) admin_scalar_query($conn, "
    SELECT COALESCE(SUM(o.total_amount), 0) AS total
    FROM orders o
    JOIN deliveries d ON d.order_id = o.order_id
    WHERE d.delivery_status = 'delivered'
        AND DATE(d.delivered_at) = '" . $conn->real_escape_string($report_date) . "'
", 'total', 0);

$low_stock_result = $conn->query("
    SELECT item_name, stock_quantity, reorder_level, unit_price, status
    FROM inventory
    WHERE stock_quantity <= reorder_level OR status = 'out_of_stock'
    ORDER BY stock_quantity ASC, item_name ASC
    LIMIT 8
");
$low_stock_rows = $low_stock_result ? $low_stock_result->fetch_all(MYSQLI_ASSOC) : [];

$inventory_summary_result = $conn->query("
    SELECT
        COUNT(*) AS total_items,
        SUM(CASE WHEN stock_quantity <= reorder_level THEN 1 ELSE 0 END) AS low_stock_items,
        SUM(CASE WHEN status = 'out_of_stock' THEN 1 ELSE 0 END) AS out_of_stock_items,
        COALESCE(SUM(stock_quantity * unit_price), 0) AS total_stock_value
    FROM inventory
");
$inventory_summary = $inventory_summary_result ? ($inventory_summary_result->fetch_assoc() ?: []) : [];

$trend_result = $conn->query("
    SELECT
        DATE(order_date) AS report_date,
        COUNT(*) AS total_orders,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
        SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
        COALESCE(SUM(total_amount), 0) AS gross_orders
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(order_date)
    ORDER BY report_date DESC
");
$trend_rows = $trend_result ? $trend_result->fetch_all(MYSQLI_ASSOC) : [];

$total_exceptions = (int) ($exception_summary['cancelled_orders'] ?? 0)
    + (int) ($exception_summary['failed_deliveries'] ?? 0)
    + (int) ($exception_summary['returned_deliveries'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ISRAPHIL Admin</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../style/admin/admin_compact.css?v=20260527" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-0">
        <div class="row g-0">
        <aside class="admin-floating-sidebar offcanvas-lg offcanvas-start col-lg-3 col-xl-2 text-white bg-israphil-sidebar d-flex flex-column p-3 min-vh-100" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
            <div class="d-flex d-lg-none justify-content-between align-items-center mb-2">
                <span class="fw-semibold" id="adminSidebarLabel">Admin Menu</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close menu"></button>
            </div>
            <a href="dashboard.php" class="d-flex align-items-center gap-2 text-white text-decoration-none fs-4 mb-3">
                <span class="bg-white rounded d-inline-flex align-items-center justify-content-center p-1">
                    <img src="../../image.gif/favicon.png" alt="ISRAPHIL logo" width="32" height="32">
                </span>
                <span class="fw-semibold">ISRAPHIL</span>
            </a>

            <hr class="border-secondary">

            <nav class="nav nav-pills flex-column gap-1 mb-auto">
                <div class="px-3 mt-2 mb-1 small text-uppercase text-white-50 fw-semibold">Main Operations</div>
                <a href="dashboard.php" class="nav-link text-white d-flex align-items-center gap-2">
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
                <a href="reports.php" class="nav-link active d-flex align-items-center gap-2" aria-current="page">
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
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-primary d-lg-none admin-mobile-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open admin menu">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <div>
                        <span class="navbar-brand mb-0 h1 fw-bold text-primary">Reports</span>
                        <div class="small text-secondary">Administrator: <?php echo htmlspecialchars($admin_name); ?></div>
                    </div>
                </div>
            </nav>

            <main class="container-fluid px-3 px-lg-4 py-4">
                <section class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <form method="GET" class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
                            <div>
                                <h1 class="h4 fw-bold mb-1">Daily Operations Summary</h1>
                                <p class="text-secondary mb-0">Simple reporting for orders, deliveries, exceptions, and stock watch.</p>
                            </div>
                            <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-end">
                                <div>
                                    <label for="report_date" class="form-label small text-secondary mb-1">Report date</label>
                                    <input type="date" class="form-control form-control-sm" id="report_date" name="date" value="<?php echo htmlspecialchars($report_date); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Total Orders</span>
                                <div class="display-6 fw-bold text-primary mt-2"><?php echo (int) ($order_summary['total_orders'] ?? 0); ?></div>
                                <div class="small text-secondary">&#8369;<?php echo number_format((float) ($order_summary['gross_orders'] ?? 0), 2); ?> ordered</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Delivered</span>
                                <div class="display-6 fw-bold text-success mt-2"><?php echo (int) ($delivery_summary['completed_deliveries'] ?? 0); ?></div>
                                <div class="small text-secondary">&#8369;<?php echo number_format($today_revenue, 2); ?> completed value</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="exceptions.php" class="card h-100 border-0 shadow-sm text-decoration-none">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Exceptions</span>
                                <div class="display-6 fw-bold text-danger mt-2"><?php echo $total_exceptions; ?></div>
                                <div class="small text-secondary">cancelled, failed, returned</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="inventory.php" class="card h-100 border-0 shadow-sm text-decoration-none">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Low Stock</span>
                                <div class="display-6 fw-bold text-warning mt-2"><?php echo (int) ($inventory_summary['low_stock_items'] ?? 0); ?></div>
                                <div class="small text-secondary"><?php echo (int) ($inventory_summary['out_of_stock_items'] ?? 0); ?> out of stock</div>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="row g-3 mb-4">
                    <div class="col-xl-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h2 class="h4 fw-bold mb-1">Order Summary</h2>
                                <p class="text-secondary mb-0">Orders created on <?php echo htmlspecialchars(admin_format_date($report_date)); ?>.</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <tbody>
                                            <tr><th scope="row">Pending</th><td><?php echo (int) ($order_summary['pending_orders'] ?? 0); ?></td></tr>
                                            <tr><th scope="row">Confirmed</th><td><?php echo (int) ($order_summary['confirmed_orders'] ?? 0); ?></td></tr>
                                            <tr><th scope="row">Delivered</th><td><?php echo (int) ($order_summary['delivered_orders'] ?? 0); ?></td></tr>
                                            <tr><th scope="row">Cancelled</th><td><?php echo (int) ($order_summary['cancelled_orders'] ?? 0); ?></td></tr>
                                            <tr><th scope="row">Total Amount</th><td>&#8369;<?php echo number_format((float) ($order_summary['gross_orders'] ?? 0), 2); ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h2 class="h4 fw-bold mb-1">Delivery Completion</h2>
                                <p class="text-secondary mb-0">Completed and exception delivery records on the selected date.</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <tbody>
                                            <tr><th scope="row">Delivered</th><td><?php echo (int) ($delivery_summary['completed_deliveries'] ?? 0); ?></td></tr>
                                            <tr><th scope="row">Failed</th><td><?php echo (int) ($delivery_summary['failed_deliveries'] ?? 0); ?></td></tr>
                                            <tr><th scope="row">Returned</th><td><?php echo (int) ($delivery_summary['returned_deliveries'] ?? 0); ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row g-3">
                    <div class="col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h2 class="h4 fw-bold mb-1">Recent Daily Orders</h2>
                                <p class="text-secondary mb-0">A compact seven-day order summary.</p>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($trend_rows)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th scope="col">Date</th>
                                                    <th scope="col">Orders</th>
                                                    <th scope="col">Delivered</th>
                                                    <th scope="col">Cancelled</th>
                                                    <th scope="col">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($trend_rows as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(admin_format_date($row['report_date'] ?? '')); ?></td>
                                                    <td><?php echo (int) ($row['total_orders'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($row['delivered_orders'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($row['cancelled_orders'] ?? 0); ?></td>
                                                    <td>&#8369;<?php echo number_format((float) ($row['gross_orders'] ?? 0), 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 text-secondary">No recent order data found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h2 class="h4 fw-bold mb-1">Inventory Low Stock</h2>
                                <p class="text-secondary mb-0">&#8369;<?php echo number_format((float) ($inventory_summary['total_stock_value'] ?? 0), 2); ?> current stock value.</p>
                            </div>
                            <div class="card-body p-4">
                                <?php if (!empty($low_stock_rows)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($low_stock_rows as $item):
                                            $stock_quantity = (int) ($item['stock_quantity'] ?? 0);
                                            $reorder_level = (int) ($item['reorder_level'] ?? 0);
                                        ?>
                                        <a href="inventory.php" class="list-group-item list-group-item-action px-0 d-flex justify-content-between gap-3">
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($item['item_name'] ?? 'Item'); ?></div>
                                                <div class="small text-secondary">Reorder level: <?php echo $reorder_level; ?></div>
                                            </div>
                                            <span class="badge rounded-pill <?php echo $stock_quantity < 1 ? 'text-bg-danger' : 'text-bg-warning'; ?> align-self-center">
                                                <?php echo $stock_quantity; ?> left
                                            </span>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-secondary">No low-stock items found.</div>
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
</body>
</html>
