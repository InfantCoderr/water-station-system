<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/order_logic.php';
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

$success = '';
$error = '';
$orders = false;
$order_rows = [];
$counts = [
    'pending' => 0,
    'confirmed' => 0,
    'processing' => 0,
    'delivering' => 0,
    'delivered' => 0,
    'total' => 0,
];
$scheduled_order_rows = [];
$batch_capacity_limit = delivery_batch_capacity_limit_units();

ensure_delivery_service_area_schema($conn);
ensure_delivery_batch_schema($conn);
ensure_delivery_cancelled_status($conn);

if (isset($_POST['bulk_confirm_pending'])) {
    $selected_order_ids = array_values(array_unique(array_map('intval', (array) ($_POST['selected_orders'] ?? []))));
    $selected_order_ids = array_values(array_filter($selected_order_ids, function ($order_id) {
        return $order_id > 0;
    }));

    if (empty($selected_order_ids)) {
        $error = "Please select at least one pending order to confirm.";
    } else {
        $confirmed_count = 0;
        $skipped_count = 0;
        $actor_id = (int) ($admin_user['user_id'] ?? 0);

        foreach ($selected_order_ids as $selected_order_id) {
            $conn->begin_transaction();
            try {
                $current_status = '';
                $status_stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ? FOR UPDATE");
                if (!$status_stmt) {
                    throw new Exception("Unable to prepare order status lookup.");
                }
                $status_stmt->bind_param("i", $selected_order_id);
                $status_stmt->execute();
                $status_stmt->bind_result($current_status);
                $status_stmt->fetch();
                $status_stmt->close();

                if ($current_status !== 'pending') {
                    $skipped_count++;
                    $conn->rollback();
                    continue;
                }

                transition_order_status($conn, $selected_order_id, 'confirmed', $actor_id, 'admin');
                $conn->commit();
                $confirmed_count++;
            } catch (Exception $e) {
                $conn->rollback();
                $skipped_count++;
            }
        }

        $success = "Confirmed $confirmed_count pending order" . ($confirmed_count === 1 ? '' : 's') . ".";
        if ($skipped_count > 0) {
            $success .= " Skipped $skipped_count order" . ($skipped_count === 1 ? '' : 's') . " that were no longer pending or could not be updated.";
        }
    }
}

if (isset($_POST['update_status'])) {
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if ($order_id > 0 && in_array($new_status, ['pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered', 'cancelled'], true)) {
        $current_status = '';
        $status_stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ?");
        if ($status_stmt) {
            $status_stmt->bind_param("i", $order_id);
            $status_stmt->execute();
            $status_stmt->bind_result($current_status);
            $status_stmt->fetch();
            $status_stmt->close();
        }

        if ($current_status === '' || !in_array($new_status, admin_order_next_statuses($current_status), true)) {
            $error = "That status change is not available from the Orders page.";
        } else {
            $conn->begin_transaction();
            try {
                transition_order_status($conn, $order_id, $new_status, (int) ($admin_user['user_id'] ?? 0), 'admin');
                $conn->commit();
                $success = "Order #$order_id status updated to " . ucfirst(str_replace('_', ' ', $new_status));
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    } else {
        $error = "Please choose a valid order status.";
    }
}

$status_filter = sanitize_status_filter($_GET['status'] ?? 'all', ['all', 'pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered']);
$active_orders_condition = "(o.order_status NOT IN ('pending', 'confirmed') OR o.delivery_date IS NULL OR o.delivery_date = '0000-00-00' OR o.delivery_date <= CURDATE())";
$where_parts = [$active_orders_condition, "o.order_status != 'cancelled'"];
$where_clause = '';

$orders_query = "
    SELECT o.*,
           c.full_name as customer_name, c.phone as customer_phone,
           order_items.item_summary, order_items.total_quantity,
           d.delivery_id, d.delivery_status, d.delivery_notes
    FROM orders o
    JOIN users c ON o.customer_id = c.user_id
    LEFT JOIN (
        SELECT oi.order_id,
               GROUP_CONCAT(CONCAT(i.item_name, ' x', oi.quantity) ORDER BY i.item_name SEPARATOR ', ') as item_summary,
               SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN inventory i ON oi.inventory_id = i.inventory_id
        GROUP BY oi.order_id
    ) order_items ON o.order_id = order_items.order_id
    LEFT JOIN (
        SELECT d1.delivery_id, d1.order_id, d1.delivery_status, d1.delivery_notes
        FROM deliveries d1
        JOIN (
            SELECT order_id, MAX(delivery_id) as latest_delivery_id
            FROM deliveries
            GROUP BY order_id
        ) latest_delivery ON latest_delivery.latest_delivery_id = d1.delivery_id
    ) d ON o.order_id = d.order_id
";
if ($status_filter !== 'all') {
    $where_parts[] = "o.order_status = ?";
}
$where_clause = " WHERE " . implode(" AND ", $where_parts);
$orders_query .= $where_clause . " ORDER BY o.order_date DESC";

$orders_stmt = $conn->prepare($orders_query);
if ($orders_stmt) {
    if ($status_filter !== 'all') {
        $orders_stmt->bind_param("s", $status_filter);
    }
    $orders_stmt->execute();
    $orders = $orders_stmt->get_result();
    $order_rows = $orders ? $orders->fetch_all(MYSQLI_ASSOC) : [];
    $orders_stmt->close();
} else {
    $error = "Unable to load orders.";
}

$counts_result = $conn->query("
    SELECT
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN order_status = 'out_for_delivery' THEN 1 ELSE 0 END) as delivering,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        COUNT(*) as total
    FROM orders
    WHERE order_status != 'cancelled'
        AND (
            order_status NOT IN ('pending', 'confirmed')
            OR delivery_date IS NULL
            OR delivery_date = '0000-00-00'
            OR delivery_date <= CURDATE()
        )
");
if ($counts_result) {
    $counts = array_merge($counts, $counts_result->fetch_assoc() ?: []);
}

$show_scheduled_orders = in_array($status_filter, ['pending', 'confirmed'], true);
$show_bulk_pending_confirm = $status_filter === 'pending';
$scheduled_order_rows = $show_scheduled_orders ? admin_delivery_queue_rows($conn, 'scheduled', [$status_filter]) : [];
$scheduled_order_units = array_sum(array_map(function ($order) {
    return (int) ($order['capacity_units'] ?? 0);
}, $scheduled_order_rows));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - ISRAPHIL Admin</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../style/admin/admin_compact.css?v=20260528f" rel="stylesheet">
    <link rel="stylesheet" href="../../style/system_skeleton.css?v=20260527d">
</head>
<body class="bg-light system-loading skeleton-admin skeleton-admin-orders">
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
                <a href="orders.php" class="nav-link active d-flex align-items-center gap-2" aria-current="page">
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
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-primary d-lg-none admin-mobile-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open admin menu">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <div>
                        <span class="navbar-brand mb-0 h1 fw-bold text-primary">Order Management</span>
                        <div class="small text-secondary">Administrator: <?php echo htmlspecialchars($admin_name); ?></div>
                    </div>
                </div>
            </nav>

            <main class="container-fluid px-3 px-lg-4 py-4">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <section class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl">
                        <a href="orders.php?status=pending" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $status_filter === 'pending' ? 'border-top border-4 border-warning' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Pending</span>
                                <div class="display-6 fw-bold text-warning mt-2"><?php echo (int) ($counts['pending'] ?? 0); ?></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl">
                        <a href="orders.php?status=confirmed" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $status_filter === 'confirmed' ? 'border-top border-4 border-info' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Confirmed</span>
                                <div class="display-6 fw-bold text-info mt-2"><?php echo (int) ($counts['confirmed'] ?? 0); ?></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl">
                        <a href="orders.php?status=processing" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $status_filter === 'processing' ? 'border-top border-4 border-primary' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Processing</span>
                                <div class="display-6 fw-bold text-primary mt-2"><?php echo (int) ($counts['processing'] ?? 0); ?></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl">
                        <a href="orders.php?status=out_for_delivery" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $status_filter === 'out_for_delivery' ? 'border-top border-4 border-primary' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Out for Delivery</span>
                                <div class="display-6 fw-bold text-primary mt-2"><?php echo (int) ($counts['delivering'] ?? 0); ?></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl">
                        <a href="orders.php?status=delivered" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $status_filter === 'delivered' ? 'border-top border-4 border-success' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Delivered</span>
                                <div class="display-6 fw-bold text-success mt-2"><?php echo (int) ($counts['delivered'] ?? 0); ?></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl">
                        <a href="orders.php" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $status_filter === 'all' ? 'border-top border-4 border-primary' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">All</span>
                                <div class="display-6 fw-bold text-primary mt-2"><?php echo (int) ($counts['total'] ?? 0); ?></div>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                            <div>
                                <h2 class="h5 fw-bold mb-1">Delivery Planning</h2>
                                <p class="text-secondary mb-0">Batch creation and rider assignment now have their own focused pages.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="order_batches.php" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2">
                                    <i class="bi bi-boxes" aria-hidden="true"></i>
                                    Order Batches
                                </a>
                                <a href="batch_assignments.php" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2">
                                    <i class="bi bi-person-check" aria-hidden="true"></i>
                                    Batch Assignment
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if ($show_scheduled_orders): ?>
                <section class="card border-0 shadow-sm mb-4" id="scheduled-orders">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div>
                                <h2 class="h4 fw-bold mb-2">Scheduled Orders</h2>
                                <p class="text-secondary mb-0">Future-dated <?php echo htmlspecialchars($status_filter); ?> orders stay here until their delivery day.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <span class="badge rounded-pill text-bg-info"><?php echo count($scheduled_order_rows); ?> scheduled</span>
                                <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo (int) $scheduled_order_units; ?> total units</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($scheduled_order_rows)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">Order</th>
                                            <th scope="col">Customer</th>
                                            <th scope="col">Zone</th>
                                            <th scope="col">Items</th>
                                            <th scope="col">Load</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Delivery Date</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scheduled_order_rows as $scheduled_order):
                                            $scheduled_order_id = (int) ($scheduled_order['order_id'] ?? 0);
                                            $scheduled_customer_name = (string) ($scheduled_order['customer_name'] ?? '');
                                            $scheduled_customer_phone = (string) ($scheduled_order['customer_phone'] ?? '');
                                            $scheduled_address = (string) ($scheduled_order['delivery_address'] ?? '');
                                            $scheduled_items = (string) ($scheduled_order['item_summary'] ?? 'No items listed');
                                            $scheduled_units = (int) ($scheduled_order['capacity_units'] ?? 0);
                                            $scheduled_zone_code = (string) ($scheduled_order['zone_code'] ?? '');
                                            $scheduled_zone_name = (string) ($scheduled_order['zone_name'] ?? '');
                                            $scheduled_status = (string) ($scheduled_order['order_status'] ?? $status_filter);
                                            $scheduled_payment_method = order_payment_method_label($scheduled_order['payment_method'] ?? 'cash_on_delivery');
                                            $scheduled_payment_status = order_payment_status_label($scheduled_order['payment_status'] ?? 'pending');
                                            $scheduled_capacity_class = admin_queue_capacity_badge_class($scheduled_units, $batch_capacity_limit);
                                        ?>
                                        <tr>
                                            <td class="fw-semibold">#<?php echo $scheduled_order_id; ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($scheduled_customer_name); ?></div>
                                                <div class="small text-secondary"><?php echo htmlspecialchars($scheduled_customer_phone); ?></div>
                                                <div class="small text-secondary"><?php echo htmlspecialchars(admin_excerpt($scheduled_address, 44)); ?></div>
                                            </td>
                                            <td>
                                                <?php if ($scheduled_zone_code !== ''): ?>
                                                    <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo htmlspecialchars($scheduled_zone_code); ?></span>
                                                    <div class="small text-secondary mt-1"><?php echo htmlspecialchars(admin_excerpt($scheduled_zone_name, 34)); ?></div>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill text-bg-warning">Unzoned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small">
                                                <div><?php echo htmlspecialchars($scheduled_items); ?></div>
                                                <div class="text-secondary"><?php echo htmlspecialchars($scheduled_payment_method); ?> - <?php echo htmlspecialchars($scheduled_payment_status); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill <?php echo $scheduled_capacity_class; ?>">
                                                    <?php echo $scheduled_units; ?>/<?php echo $batch_capacity_limit; ?> units
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill <?php echo admin_order_status_badge_class($scheduled_status); ?>">
                                                    <?php echo admin_order_status_label($scheduled_status); ?>
                                                </span>
                                            </td>
                                            <td class="small"><?php echo htmlspecialchars(admin_delivery_date_label($scheduled_order['delivery_date'] ?? '')); ?></td>
                                            <td>
                                                <?php if ($scheduled_status === 'pending'): ?>
                                                    <form method="POST" data-admin-confirm="Confirm this scheduled order?">
                                                        <input type="hidden" name="order_id" value="<?php echo $scheduled_order_id; ?>">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <input type="hidden" name="new_status" value="confirmed">
                                                        <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="small text-secondary">Already confirmed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="text-secondary mb-2"><i class="bi bi-calendar-event fs-4" aria-hidden="true"></i></div>
                                <h3 class="h6 fw-bold">No scheduled orders</h3>
                                <p class="text-secondary mb-0">Future-dated <?php echo htmlspecialchars($status_filter); ?> orders will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <section class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <h2 class="h4 fw-bold mb-2">Orders</h2>
                        <p class="text-secondary mb-0">Approve new orders, watch delivery progress, and use the batch pages for rider assignment.</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($order_rows)): ?>
                            <?php if ($show_bulk_pending_confirm): ?>
                            <form id="bulkConfirmPendingForm" method="POST" data-admin-confirm="Confirm selected pending orders?">
                                <input type="hidden" name="bulk_confirm_pending" value="1">
                            </form>
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                                <div class="small text-secondary">Select pending orders, then confirm them together.</div>
                                <button type="submit" form="bulkConfirmPendingForm" name="bulk_confirm_pending" value="1" class="btn btn-success btn-sm">
                                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Confirm Selected
                                </button>
                            </div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <?php if ($show_bulk_pending_confirm): ?>
                                            <th scope="col" class="text-center">
                                                <input type="checkbox" class="form-check-input" id="selectPendingOrders" aria-label="Select pending orders">
                                            </th>
                                            <?php endif; ?>
                                            <th scope="col">Order</th>
                                            <th scope="col">Customer</th>
                                            <th scope="col">Items</th>
                                            <th scope="col">Total</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Date</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_rows as $order):
                                            $order_id = (int) ($order['order_id'] ?? 0);
                                            $customer_name = (string) ($order['customer_name'] ?? '');
                                            $customer_phone = (string) ($order['customer_phone'] ?? '');
                                            $delivery_address = (string) ($order['delivery_address'] ?? '');
                                            $item_summary = (string) ($order['item_summary'] ?? 'No items listed');
                                            $total_quantity = (int) ($order['total_quantity'] ?? 0);
                                            $total_amount = (float) ($order['total_amount'] ?? 0);
                                            $payment_method = order_payment_method_label($order['payment_method'] ?? 'cash_on_delivery');
                                            $payment_status = order_payment_status_label($order['payment_status'] ?? 'pending');
                                            $order_status = (string) ($order['order_status'] ?? 'pending');
                                            $delivery_notes = trim((string) ($order['delivery_notes'] ?? ''));
                                            $available_statuses = admin_order_next_statuses($order_status);
                                            $order_date = (string) ($order['order_date'] ?? '');
                                        ?>
                                        <tr>
                                            <?php if ($show_bulk_pending_confirm): ?>
                                            <td class="text-center">
                                                <?php if ($order_status === 'pending'): ?>
                                                    <input type="checkbox" form="bulkConfirmPendingForm" class="form-check-input pending-order-checkbox" name="selected_orders[]" value="<?php echo $order_id; ?>" aria-label="Select order #<?php echo $order_id; ?>">
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <td class="fw-semibold">#<?php echo $order_id; ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($customer_name); ?></div>
                                                <div class="small text-secondary"><?php echo htmlspecialchars($customer_phone); ?></div>
                                                <div class="small text-secondary"><?php echo htmlspecialchars(admin_excerpt($delivery_address, 30)); ?></div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($item_summary); ?><br>
                                                <small class="text-secondary">Total qty: <?php echo $total_quantity; ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">&#8369;<?php echo number_format($total_amount, 2); ?></div>
                                                <div class="small text-secondary"><?php echo htmlspecialchars($payment_method); ?></div>
                                                <div class="small text-secondary"><?php echo htmlspecialchars($payment_status); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill <?php echo admin_order_status_badge_class($order_status); ?>">
                                                    <?php echo admin_order_status_label($order_status); ?>
                                                </span>
                                                <div class="order-process mt-2" aria-label="Order process">
                                                    <?php foreach (admin_order_workflow_steps() as $step_key => $step_label): ?>
                                                        <span class="badge rounded-pill <?php echo admin_order_step_class($step_key, $order_status); ?>">
                                                            <?php echo htmlspecialchars($step_label); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php if ($delivery_notes !== ''): ?>
                                                    <div class="small text-danger mt-2">
                                                        <span class="fw-semibold">Last failed delivery:</span>
                                                        <?php echo htmlspecialchars(admin_excerpt($delivery_notes, 90)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo admin_format_date($order_date); ?></td>
                                            <td>
                                                <div class="d-grid gap-2">
                                                    <?php if (!empty($available_statuses)): ?>
                                                    <form method="POST" class="d-flex gap-2" data-admin-confirm="Apply this order status update?">
                                                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <select name="new_status" class="form-select form-select-sm" required>
                                                            <option value="">Next status</option>
                                                            <?php foreach ($available_statuses as $next_status): ?>
                                                            <option value="<?php echo htmlspecialchars($next_status); ?>">
                                                                <?php echo admin_order_status_label($next_status); ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                                    </form>
                                                    <?php else: ?>
                                                        <span class="small text-secondary">No status actions available.</span>
                                                    <?php endif; ?>

                                                    <?php if ($order_status === 'confirmed'): ?>
                                                        <div class="small text-secondary">Ready for batching.</div>
                                                    <?php elseif (in_array($order_status, ['processing', 'out_for_delivery'], true)): ?>
                                                        <div class="small text-secondary">Rider updates happen from the assigned batch.</div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="display-6 text-secondary mb-3"><i class="bi bi-inbox" aria-hidden="true"></i></div>
                                <h3 class="h5 fw-bold">No orders found</h3>
                                <p class="text-secondary mb-0">There are no <?php echo $status_filter !== 'all' ? str_replace('_', ' ', $status_filter) : ''; ?> orders at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
        </div>
    </div>

    <div class="modal fade" id="adminConfirmModal" tabindex="-1" aria-labelledby="adminConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="adminConfirmModalLabel">Confirm Action</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="adminConfirmMessage">Continue with this action?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="adminConfirmContinue">
                        <span class="admin-confirm-label">Continue</span>
                        <span class="admin-confirm-progress d-none">
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Working...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const selectPendingOrders = document.getElementById('selectPendingOrders');
        if (selectPendingOrders) {
            selectPendingOrders.addEventListener('change', () => {
                document.querySelectorAll('.pending-order-checkbox').forEach(checkbox => {
                    checkbox.checked = selectPendingOrders.checked;
                });
            });
        }

        const adminConfirmModalEl = document.getElementById('adminConfirmModal');
        const adminConfirmMessage = document.getElementById('adminConfirmMessage');
        const adminConfirmContinue = document.getElementById('adminConfirmContinue');
        const adminConfirmModal = adminConfirmModalEl ? new bootstrap.Modal(adminConfirmModalEl) : null;
        let pendingAdminConfirmForm = null;
        let pendingAdminSubmitter = null;

        document.querySelectorAll('form[data-admin-confirm]').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();
                pendingAdminConfirmForm = form;
                pendingAdminSubmitter = event.submitter || null;

                if (adminConfirmMessage) {
                    adminConfirmMessage.textContent = form.dataset.adminConfirm || 'Continue with this action?';
                }

                if (adminConfirmContinue) {
                    adminConfirmContinue.disabled = false;
                    adminConfirmContinue.querySelector('.admin-confirm-label')?.classList.remove('d-none');
                    adminConfirmContinue.querySelector('.admin-confirm-progress')?.classList.add('d-none');
                }

                adminConfirmModal?.show();
            });
        });

        adminConfirmContinue?.addEventListener('click', () => {
            if (!pendingAdminConfirmForm) {
                return;
            }

            if (pendingAdminSubmitter?.name) {
                const hiddenAction = document.createElement('input');
                hiddenAction.type = 'hidden';
                hiddenAction.name = pendingAdminSubmitter.name;
                hiddenAction.value = pendingAdminSubmitter.value || '1';
                pendingAdminConfirmForm.appendChild(hiddenAction);
            }

            adminConfirmContinue.disabled = true;
            adminConfirmContinue.querySelector('.admin-confirm-label')?.classList.add('d-none');
            adminConfirmContinue.querySelector('.admin-confirm-progress')?.classList.remove('d-none');
            pendingAdminConfirmForm.submit();
        });
    </script>
    <script src="../../scripts/system_skeleton.js?v=20260527"></script>
</body>
</html>
