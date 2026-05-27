<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/order_logic.php';
require_once '../../includes/admin_page_helpers.php';
require_once '../../includes/admin_order_helpers.php';

/** @var mysqli $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is not available.');
}

$admin_user = require_active_session($conn, ['admin'], '../../index.php');
$admin_name = $admin_user['full_name'] ?? ($_SESSION['full_name'] ?? 'Administrator');
$admin_id = (int) ($admin_user['user_id'] ?? ($_SESSION['user_id'] ?? 0));
$success = '';
$error = '';

$type_filter = sanitize_status_filter($_GET['type'] ?? 'all', ['all', 'cancelled', 'failed', 'returned']);

ensure_delivery_cancelled_status($conn);

if (isset($_POST['reassign_exception'])) {
    $order_id = (int) ($_POST['order_id'] ?? 0);

    if ($order_id < 1) {
        $error = "Please choose a valid order to reassign.";
    } else {
        $conn->begin_transaction();
        try {
            transition_order_status($conn, $order_id, 'pending', $admin_id, 'admin');
            $conn->commit();
            $success = "Order #$order_id has been returned to pending for reassignment.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

if (isset($_POST['cancel_exception'])) {
    $order_id = (int) ($_POST['order_id'] ?? 0);

    if ($order_id < 1) {
        $error = "Please choose a valid order to cancel.";
    } else {
        $conn->begin_transaction();
        try {
            transition_order_status($conn, $order_id, 'cancelled', $admin_id, 'admin');
            $conn->commit();
            $success = "Order #$order_id has been cancelled and reserved stock was returned.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$counts = [
    'cancelled' => admin_count_query($conn, "SELECT COUNT(*) AS count FROM orders WHERE order_status = 'cancelled'"),
    'failed' => admin_count_query($conn, "SELECT COUNT(*) AS count FROM deliveries WHERE delivery_status = 'failed'"),
    'returned' => admin_count_query($conn, "SELECT COUNT(*) AS count FROM deliveries WHERE delivery_status = 'returned'"),
];
$counts['all'] = $counts['cancelled'] + $counts['failed'] + $counts['returned'];

function admin_exception_status_badge_class($exception_type) {
    switch ($exception_type) {
        case 'Cancelled Order':
            return 'text-bg-secondary';
        case 'Failed Delivery':
        case 'Returned Delivery':
            return 'text-bg-danger';
        default:
            return 'text-bg-light border text-secondary';
    }
}

function admin_exception_rows($conn, $type_filter) {
    $cancelled_query = "
        SELECT
            o.order_id,
            c.full_name AS customer_name,
            'Cancelled Order' AS exception_type,
            COALESCE(o.updated_at, o.order_date) AS exception_date,
            CASE
                WHEN cancel_actor.role = 'customer' THEN 'Cancelled by customer'
                WHEN cancel_actor.role = 'admin' THEN 'Cancelled by admin'
                WHEN cancel_log.description LIKE '%customer%' THEN 'Cancelled by customer'
                WHEN cancel_log.description LIKE '%admin%' THEN 'Cancelled by admin'
                ELSE 'Cancelled by system'
            END AS remarks,
            o.order_status AS status,
            o.total_amount,
            o.payment_status,
            d.delivery_status,
            d.delivery_notes,
            o.order_status,
            staff.full_name AS staff_name
        FROM orders o
        JOIN users c ON c.user_id = o.customer_id
        LEFT JOIN (
            SELECT d1.*
            FROM deliveries d1
            JOIN (
                SELECT order_id, MAX(delivery_id) AS latest_delivery_id
                FROM deliveries
                GROUP BY order_id
            ) latest_delivery ON latest_delivery.latest_delivery_id = d1.delivery_id
        ) d ON d.order_id = o.order_id
        LEFT JOIN users staff ON staff.user_id = d.staff_id
        LEFT JOIN (
            SELECT l1.*
            FROM activity_logs l1
            JOIN (
                SELECT related_id, MAX(log_id) AS latest_log_id
                FROM activity_logs
                WHERE related_table = 'orders' AND action = 'order_cancelled'
                GROUP BY related_id
            ) latest_log ON latest_log.latest_log_id = l1.log_id
        ) cancel_log ON cancel_log.related_id = o.order_id
        LEFT JOIN users cancel_actor ON cancel_actor.user_id = cancel_log.user_id
        WHERE o.order_status = 'cancelled'
    ";

    $failed_query = "
        SELECT
            o.order_id,
            c.full_name AS customer_name,
            'Failed Delivery' AS exception_type,
            COALESCE(d.assigned_at, o.updated_at, o.order_date) AS exception_date,
            CONCAT('Action by: ', COALESCE(staff.full_name, 'Delivery staff')) AS remarks,
            d.delivery_status AS status,
            o.total_amount,
            o.payment_status,
            d.delivery_status,
            d.delivery_notes,
            o.order_status,
            staff.full_name AS staff_name
        FROM deliveries d
        JOIN orders o ON o.order_id = d.order_id
        JOIN users c ON c.user_id = o.customer_id
        LEFT JOIN users staff ON staff.user_id = d.staff_id
        WHERE d.delivery_status = 'failed'
    ";

    $returned_query = "
        SELECT
            o.order_id,
            c.full_name AS customer_name,
            'Returned Delivery' AS exception_type,
            COALESCE(d.assigned_at, o.updated_at, o.order_date) AS exception_date,
            CONCAT('Action by: ', COALESCE(staff.full_name, 'Delivery staff')) AS remarks,
            d.delivery_status AS status,
            o.total_amount,
            o.payment_status,
            d.delivery_status,
            d.delivery_notes,
            o.order_status,
            staff.full_name AS staff_name
        FROM deliveries d
        JOIN orders o ON o.order_id = d.order_id
        JOIN users c ON c.user_id = o.customer_id
        LEFT JOIN users staff ON staff.user_id = d.staff_id
        WHERE d.delivery_status = 'returned'
    ";

    $queries = [];
    if ($type_filter === 'all' || $type_filter === 'cancelled') {
        $queries[] = $cancelled_query;
    }
    if ($type_filter === 'all' || $type_filter === 'failed') {
        $queries[] = $failed_query;
    }
    if ($type_filter === 'all' || $type_filter === 'returned') {
        $queries[] = $returned_query;
    }

    if (empty($queries)) {
        return [];
    }

    $query = implode(" UNION ALL ", $queries) . " ORDER BY exception_date DESC, order_id DESC LIMIT 100";
    $result = $conn->query($query);
    if (!$result) {
        error_log('Admin exceptions query failed: ' . $conn->error);
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

$exception_rows = admin_exception_rows($conn, $type_filter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exceptions - ISRAPHIL Admin</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../style/admin/admin_compact.css?v=20260527" rel="stylesheet">
    <link rel="stylesheet" href="../../style/system_skeleton.css?v=20260527d">
</head>
<body class="bg-light system-loading skeleton-admin skeleton-admin-exceptions">
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
                <a href="exceptions.php" class="nav-link active d-flex align-items-center gap-2" aria-current="page">
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
                        <span class="navbar-brand mb-0 h1 fw-bold text-primary">Exceptions</span>
                        <div class="small text-secondary">Administrator: <?php echo htmlspecialchars($admin_name); ?></div>
                    </div>
                </div>
            </nav>

            <main class="container-fluid px-3 px-lg-4 py-4">
                <?php if ($success !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <section class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <a href="exceptions.php" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $type_filter === 'all' ? 'border-top border-4 border-primary' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">All Exceptions</span>
                                <div class="display-6 fw-bold text-primary mt-2"><?php echo (int) $counts['all']; ?></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="exceptions.php?type=cancelled" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $type_filter === 'cancelled' ? 'border-top border-4 border-secondary' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Cancelled Orders</span>
                                <div class="display-6 fw-bold text-secondary mt-2"><?php echo (int) $counts['cancelled']; ?></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="exceptions.php?type=failed" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $type_filter === 'failed' ? 'border-top border-4 border-danger' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Failed Deliveries</span>
                                <div class="display-6 fw-bold text-danger mt-2"><?php echo (int) $counts['failed']; ?></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="exceptions.php?type=returned" class="card h-100 border-0 shadow-sm text-decoration-none <?php echo $type_filter === 'returned' ? 'border-top border-4 border-danger' : ''; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Returned Orders</span>
                                <div class="display-6 fw-bold text-danger mt-2"><?php echo (int) $counts['returned']; ?></div>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div>
                                <h2 class="h4 fw-bold mb-2">Exception Log</h2>
                                <p class="text-secondary mb-0">Cancelled orders, failed deliveries, and returned deliveries are tracked here for admin review.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <a href="orders.php?status=cancelled" class="btn btn-outline-secondary btn-sm">Cancelled Orders</a>
                                <a href="order_batches.php" class="btn btn-outline-primary btn-sm">Plan Deliveries</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($exception_rows)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">Order ID</th>
                                            <th scope="col">Customer Name</th>
                                            <th scope="col">Type of Exception</th>
                                            <th scope="col">Date</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($exception_rows as $exception):
                                            $exception_type = (string) ($exception['exception_type'] ?? 'Exception');
                                            $order_id = (int) ($exception['order_id'] ?? 0);
                                            $status = (string) ($exception['status'] ?? '');
                                            $order_status = (string) ($exception['order_status'] ?? '');
                                            $delivery_status = (string) ($exception['delivery_status'] ?? '');
                                            $customer_name = (string) ($exception['customer_name'] ?? 'Customer');
                                            $staff_name = (string) ($exception['staff_name'] ?? 'Unassigned');
                                            $exception_date = admin_format_date($exception['exception_date'] ?? '', 'M d, Y g:i A');
                                            $remarks = (string) ($exception['remarks'] ?? 'Not provided');
                                            $payment_status = admin_order_status_label($exception['payment_status'] ?? 'pending');
                                            $total_amount = number_format((float) ($exception['total_amount'] ?? 0), 2);
                                            $delivery_notes = trim((string) ($exception['delivery_notes'] ?? ''));
                                            $order_status_label = admin_order_status_label($order_status !== '' ? $order_status : $status);
                                            $delivery_status_label = $delivery_status !== '' ? admin_delivery_status_label($delivery_status) : 'None';
                                            $can_reassign = in_array($exception_type, ['Failed Delivery', 'Returned Delivery'], true)
                                                && !in_array($order_status, ['delivered', 'cancelled', 'returned'], true);
                                        ?>
                                        <tr>
                                            <td class="fw-semibold">#<?php echo $order_id; ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($exception['customer_name'] ?? 'Customer'); ?></div>
                                                <?php if (!empty($exception['staff_name'])): ?>
                                                    <div class="small text-secondary">Rider: <?php echo htmlspecialchars($exception['staff_name']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill <?php echo admin_exception_status_badge_class($exception_type); ?>">
                                                    <?php echo htmlspecialchars($exception_type); ?>
                                                </span>
                                            </td>
                                            <td class="small"><?php echo htmlspecialchars(admin_format_date($exception['exception_date'] ?? '', 'M d, Y g:i A')); ?></td>
                                            <td>
                                                <?php if ($delivery_status !== ''): ?>
                                                    <span class="badge rounded-pill <?php echo admin_delivery_status_badge_class($delivery_status); ?>">
                                                        <?php echo admin_delivery_status_label($delivery_status); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill <?php echo admin_order_status_badge_class($status); ?>">
                                                        <?php echo admin_order_status_label($status); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-grid gap-2" style="min-width: 160px;">
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#exceptionReviewModal"
                                                        data-order-id="<?php echo $order_id; ?>"
                                                        data-customer="<?php echo htmlspecialchars($customer_name, ENT_QUOTES); ?>"
                                                        data-exception-type="<?php echo htmlspecialchars($exception_type, ENT_QUOTES); ?>"
                                                        data-date="<?php echo htmlspecialchars($exception_date, ENT_QUOTES); ?>"
                                                        data-remarks="<?php echo htmlspecialchars($remarks, ENT_QUOTES); ?>"
                                                        data-order-status="<?php echo htmlspecialchars($order_status_label, ENT_QUOTES); ?>"
                                                        data-delivery-status="<?php echo htmlspecialchars($delivery_status_label, ENT_QUOTES); ?>"
                                                        data-staff="<?php echo htmlspecialchars($staff_name, ENT_QUOTES); ?>"
                                                        data-payment="<?php echo htmlspecialchars($payment_status, ENT_QUOTES); ?>"
                                                        data-amount="PHP <?php echo htmlspecialchars($total_amount, ENT_QUOTES); ?>"
                                                        data-delivery-notes="<?php echo htmlspecialchars($delivery_notes !== '' ? $delivery_notes : 'No delivery notes recorded.', ENT_QUOTES); ?>"
                                                    >
                                                        Review
                                                    </button>
                                                <?php if ($can_reassign): ?>
                                                    <div class="d-grid gap-2" style="min-width: 160px;">
                                                        <form method="POST" data-admin-confirm="Return order #<?php echo $order_id; ?> to the active delivery queue?">
                                                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                                            <button type="submit" name="reassign_exception" class="btn btn-outline-primary btn-sm w-100">
                                                                Reassign
                                                            </button>
                                                        </form>
                                                        <form method="POST" data-admin-confirm="Cancel order #<?php echo $order_id; ?> and return reserved stock?">
                                                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                                            <button type="submit" name="cancel_exception" class="btn btn-outline-danger btn-sm w-100">
                                                                Cancel
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill text-bg-light border text-secondary">Closed</span>
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
                                <div class="text-secondary mb-2"><i class="bi bi-check-circle fs-3" aria-hidden="true"></i></div>
                                <h3 class="h5 fw-bold">No exceptions found</h3>
                                <p class="text-secondary mb-0">Cancelled, failed, and returned cases will appear here when they need review.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
        </div>
    </div>

    <div class="modal fade" id="exceptionReviewModal" tabindex="-1" aria-labelledby="exceptionReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 fw-bold" id="exceptionReviewModalLabel">Exception Review</h2>
                        <div class="small text-secondary" id="exceptionReviewSubtitle">Order details</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Customer</div>
                                <div class="fw-bold" id="exceptionReviewCustomer">Customer</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Rider</div>
                                <div class="fw-bold" id="exceptionReviewStaff">Unassigned</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Exception Date</div>
                                <div class="fw-bold" id="exceptionReviewDate">N/A</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Order Status</div>
                            <div class="fw-semibold" id="exceptionReviewOrderStatus">N/A</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Delivery Status</div>
                            <div class="fw-semibold" id="exceptionReviewDeliveryStatus">N/A</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Payment</div>
                            <div class="fw-semibold" id="exceptionReviewPayment">N/A</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Amount</div>
                            <div class="fw-bold text-primary" id="exceptionReviewAmount">PHP 0.00</div>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 mb-3">
                        <div class="small text-secondary fw-semibold text-uppercase mb-1">System Remarks</div>
                        <div id="exceptionReviewRemarks">Not provided</div>
                    </div>

                    <div class="border rounded-3 p-3">
                        <div class="small text-secondary fw-semibold text-uppercase mb-1">Delivery Notes</div>
                        <div id="exceptionReviewDeliveryNotes">No delivery notes recorded.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
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
        const exceptionReviewModal = document.getElementById('exceptionReviewModal');

        if (exceptionReviewModal) {
            exceptionReviewModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                const fields = {
                    exceptionReviewCustomer: button.dataset.customer || 'Customer',
                    exceptionReviewStaff: button.dataset.staff || 'Unassigned',
                    exceptionReviewDate: button.dataset.date || 'N/A',
                    exceptionReviewOrderStatus: button.dataset.orderStatus || 'N/A',
                    exceptionReviewDeliveryStatus: button.dataset.deliveryStatus || 'N/A',
                    exceptionReviewPayment: button.dataset.payment || 'N/A',
                    exceptionReviewAmount: button.dataset.amount || 'PHP 0.00',
                    exceptionReviewRemarks: button.dataset.remarks || 'Not provided',
                    exceptionReviewDeliveryNotes: button.dataset.deliveryNotes || 'No delivery notes recorded.',
                };

                Object.entries(fields).forEach(([id, value]) => {
                    const target = document.getElementById(id);
                    if (target) {
                        target.textContent = value;
                    }
                });

                const orderId = button.dataset.orderId || '';
                const exceptionType = button.dataset.exceptionType || 'Exception';
                document.getElementById('exceptionReviewModalLabel').textContent = `${exceptionType} Review`;
                document.getElementById('exceptionReviewSubtitle').textContent = orderId ? `Order #${orderId}` : 'Order details';
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
