<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/order_logic.php';
require_once '../../includes/delivery_batch_helpers.php';

/** @var mysqli $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is not available.');
}

$staff_user = require_active_session($conn, ['staff'], '../../index.php');
$staff_id = (int) ($staff_user['user_id'] ?? ($_SESSION['user_id'] ?? 0));
$staff_name = $staff_user['full_name'] ?? ($_SESSION['full_name'] ?? 'Delivery Staff');
$success = '';
$error = '';
$result_modal_title = '';
$result_modal_message = '';

ensure_delivery_batch_schema($conn);

// Handle start batch
if (isset($_POST['start_batch'])) {
    $batch_id = (int) ($_POST['batch_id'] ?? 0);
    if ($batch_id > 0) {
        $conn->begin_transaction();
        try {
            $batch = start_delivery_batch($conn, $batch_id, $staff_id);
            $conn->commit();
            $batch_label = ($batch['batch_code'] ?? '') !== '' ? $batch['batch_code'] : 'Batch #' . $batch_id;
            $result_modal_title = 'Batch started';
            $result_modal_message = $batch_label . ' is now in transit.';
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Handle mark as delivered
if (isset($_POST['mark_delivered'])) {
    $delivery_id = (int) ($_POST['delivery_id'] ?? 0);
    if ($delivery_id > 0) {
        $conn->begin_transaction();
        try {
            $order_id = mark_delivery_as_delivered($conn, $delivery_id, $staff_id);
            refresh_delivery_batch_completion_for_delivery($conn, $delivery_id);
            $conn->commit();
            $result_modal_title = 'Order delivered';
            $result_modal_message = "Order #$order_id marked as delivered.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Handle delivery exceptions that should return to the assignment cycle.
if (isset($_POST['mark_failed'])) {
    $delivery_id = intval($_POST['delivery_id'] ?? 0);
    $reason = trim($_POST['failure_reason'] ?? 'Delivery failed');
    if ($delivery_id > 0) {
        $conn->begin_transaction();

        try {
            $order_id = mark_delivery_as_failed($conn, $delivery_id, $staff_id, $reason);
            refresh_delivery_batch_completion_for_delivery($conn, $delivery_id);
            $conn->commit();
            $result_modal_title = 'Delivery marked failed';
            $result_modal_message = "Order #$order_id is pending reassignment.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

if (isset($_POST['mark_returned'])) {
    $delivery_id = intval($_POST['delivery_id'] ?? 0);
    $reason = trim($_POST['return_reason'] ?? 'Delivery returned');
    if ($delivery_id > 0) {
        $conn->begin_transaction();

        try {
            $order_id = mark_delivery_as_returned($conn, $delivery_id, $staff_id, $reason);
            refresh_delivery_batch_completion_for_delivery($conn, $delivery_id);
            $conn->commit();
            $result_modal_title = 'Delivery marked returned';
            $result_modal_message = "Order #$order_id is pending admin review.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
// Get filter
$status_filter = sanitize_status_filter($_GET['status'] ?? 'assigned', ['all', 'assigned', 'in_transit', 'delivered', 'failed', 'returned', 'cancelled'], 'assigned');
$show_staff_batches = in_array($status_filter, ['all', 'assigned', 'in_transit'], true);
$staff_batch_statuses = [];
if ($show_staff_batches) {
    $staff_batch_statuses = $status_filter === 'all' ? ['assigned', 'in_transit'] : [$status_filter];
}
$staff_batch_rows = $show_staff_batches ? fetch_staff_delivery_batches($conn, $staff_id, $staff_batch_statuses) : [];
$staff_batch_items = $show_staff_batches ? fetch_staff_delivery_batch_items_map($conn, $staff_id, $staff_batch_statuses) : [];
$where_clause = "AND d.delivery_status = ?";
if ($status_filter === 'all') {
    $where_clause = "";
}

$order_clause = "ORDER BY o.delivery_date ASC, d.assigned_at ASC";
if (in_array($status_filter, ['delivered', 'failed', 'returned', 'cancelled'], true)) {
    $order_clause = "ORDER BY COALESCE(d.delivered_at, d.assigned_at, o.order_date) DESC";
} elseif ($status_filter === 'all') {
    $order_clause = "
        ORDER BY
            CASE WHEN d.delivery_status IN ('delivered', 'failed', 'returned', 'cancelled') THEN 1 ELSE 0 END ASC,
            CASE WHEN d.delivery_status IN ('delivered', 'failed', 'returned', 'cancelled') THEN COALESCE(d.delivered_at, d.assigned_at, o.order_date) END DESC,
            o.delivery_date ASC,
            d.assigned_at ASC
    ";
}

// Get assigned deliveries
$deliveries_query = "
    SELECT d.*, o.order_id, o.customer_id, o.delivery_address, o.contact_number,
           o.delivery_date, o.total_amount, o.notes as order_notes,
           order_items.item_summary, order_items.total_quantity,
           c.full_name as customer_name, c.phone as customer_phone
    FROM deliveries d
    JOIN orders o ON d.order_id = o.order_id
    LEFT JOIN (
        SELECT oi.order_id,
               GROUP_CONCAT(CONCAT(i.item_name, ' x', oi.quantity) ORDER BY i.item_name SEPARATOR ', ') as item_summary,
               SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN inventory i ON oi.inventory_id = i.inventory_id
        GROUP BY oi.order_id
    ) order_items ON o.order_id = order_items.order_id
    JOIN users c ON o.customer_id = c.user_id
    WHERE d.staff_id = ? $where_clause
    AND NOT EXISTS (
        SELECT 1
        FROM delivery_batch_items bi
        JOIN delivery_batches b ON b.batch_id = bi.batch_id
        WHERE bi.order_id = d.order_id
            AND bi.item_status = 'active'
            AND b.batch_status IN ('assigned', 'in_transit')
    )
    $order_clause
";

$stmt = $conn->prepare($deliveries_query);
$delivery_rows = [];
if ($stmt) {
    if ($status_filter === 'all') {
        $stmt->bind_param("i", $staff_id);
    } else {
        $stmt->bind_param("is", $staff_id, $status_filter);
    }
    $stmt->execute();
    $deliveries = $stmt->get_result();
    $delivery_rows = $deliveries ? $deliveries->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $error = "Unable to load your deliveries.";
}

// Get counts
$counts = [
    'assigned' => 0,
    'in_transit' => 0,
    'delivered' => 0,
    'failed' => 0,
    'returned' => 0,
    'cancelled' => 0,
    'total' => 0,
];
$counts_result = $conn->query("
    SELECT
        SUM(CASE WHEN delivery_status = 'assigned' THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN delivery_status = 'in_transit' THEN 1 ELSE 0 END) as in_transit,
        SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN delivery_status = 'returned' THEN 1 ELSE 0 END) as returned,
        SUM(CASE WHEN delivery_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        COUNT(*) as total
    FROM deliveries WHERE staff_id = $staff_id
");
if ($counts_result) {
    $counts = array_merge($counts, $counts_result->fetch_assoc() ?: []);
}

$batch_counts = [
    'assigned' => 0,
    'in_transit' => 0,
    'completed' => 0,
    'total' => 0,
];
$batch_counts_result = $conn->query("
    SELECT
        SUM(CASE WHEN batch_status = 'assigned' THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN batch_status = 'in_transit' THEN 1 ELSE 0 END) as in_transit,
        SUM(CASE WHEN batch_status = 'completed' THEN 1 ELSE 0 END) as completed,
        COUNT(*) as total
    FROM delivery_batches WHERE staff_id = $staff_id
");
if ($batch_counts_result) {
    $batch_counts = array_merge($batch_counts, $batch_counts_result->fetch_assoc() ?: []);
}

// Get today's deliveries
$today = date('Y-m-d');
$today_result = $conn->query("
    SELECT COUNT(*) as count FROM deliveries d
    JOIN orders o ON d.order_id = o.order_id
    WHERE d.staff_id = $staff_id
    AND (DATE(o.delivery_date) = '$today' OR d.delivery_status = 'assigned')
    AND d.delivery_status NOT IN ('delivered', 'failed', 'returned', 'cancelled')
");
$today_count = $today_result ? (int) ($today_result->fetch_assoc()['count'] ?? 0) : 0;
$queue_filter_labels = [
    'all' => 'All',
    'assigned' => 'Assigned',
    'in_transit' => 'In Transit',
    'delivered' => 'Delivered',
    'failed' => 'Failed',
    'returned' => 'Returned',
    'cancelled' => 'Cancelled',
];

function staff_delivery_status_badge_class($status) {
    switch ($status) {
        case 'assigned':
            return 'text-bg-warning';
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

function staff_delivery_status_label($status) {
    return ucfirst(str_replace('_', ' ', (string) $status));
}

function staff_batch_status_badge_class($status) {
    switch ($status) {
        case 'assigned':
            return 'text-bg-warning';
        case 'in_transit':
            return 'text-bg-primary';
        case 'completed':
            return 'text-bg-success';
        case 'cancelled':
            return 'text-bg-secondary';
        default:
            return 'text-bg-light border text-secondary';
    }
}

function staff_format_date($value, $format = 'M d, Y') {
    $timestamp = strtotime((string) $value);
    if ($timestamp === false || $timestamp <= 0) {
        return 'No preferred date';
    }

    return date($format, $timestamp);
}

function staff_excerpt($value, $length = 88) {
    $value = trim((string) $value);
    if ($value === '' || strlen($value) <= $length) {
        return $value;
    }

    return substr($value, 0, max(0, $length - 3)) . '...';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../style/staff/dashboard.css?v=20260522d" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm staff-topbar">
        <div class="container staff-shell d-flex flex-row gap-2 align-items-center justify-content-between py-2">
            <a href="dashboard.php" class="navbar-brand fw-bold d-flex align-items-center gap-2">
                <span class="bg-white rounded d-inline-flex align-items-center justify-content-center p-1">
                    <img src="../../image.gif/favicon.png" alt="ISRAPHIL logo" width="28" height="28">
                </span>
                ISRAPHIL Delivery
            </a>
            <div class="staff-topbar-actions d-flex flex-nowrap align-items-center justify-content-end gap-2 text-white">
                <span class="small text-white-50 staff-signed-label">Signed in as</span>
                <span class="fw-semibold staff-signed-name"><?php echo htmlspecialchars($staff_name); ?></span>
                <a href="../../logout.php" class="btn btn-light btn-sm rounded-pill">
                    <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="container staff-shell py-4">
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <section class="row g-4 align-items-stretch mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100 staff-card">
                    <div class="card-body p-4">
                        <span class="badge text-bg-primary mb-3">Staff Dashboard</span>
                        <h1 class="h2 fw-bold mb-0">Hello, <?php echo htmlspecialchars($staff_name); ?></h1>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100 staff-card staff-due-today-card <?php echo $today_count > 0 ? 'border-start border-4 border-warning' : ''; ?>">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <div class="rounded-circle text-bg-warning d-inline-flex align-items-center justify-content-center flex-shrink-0 staff-icon-circle">
                            <i class="bi bi-calendar-check" aria-hidden="true"></i>
                        </div>
                        <div>
                            <span class="text-secondary small fw-semibold text-uppercase">Due today</span>
                            <div class="display-6 fw-bold mb-1"><?php echo $today_count; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-2 mb-3 staff-stat-strip">
            <div class="col-3">
                <a href="?status=assigned" class="card h-100 border-0 shadow-sm text-decoration-none stat-card <?php echo $status_filter === 'assigned' ? 'border-top border-4 border-warning' : ''; ?>">
                    <div class="card-body d-flex align-items-center justify-content-between gap-2">
                        <span class="text-secondary small fw-semibold text-uppercase">Assigned</span>
                        <div class="staff-stat-count fw-bold text-warning"><?php echo (int) ($counts['assigned'] ?? 0); ?></div>
                    </div>
                </a>
            </div>
            <div class="col-3">
                <a href="?status=in_transit" class="card h-100 border-0 shadow-sm text-decoration-none stat-card <?php echo $status_filter === 'in_transit' ? 'border-top border-4 border-primary' : ''; ?>">
                    <div class="card-body d-flex align-items-center justify-content-between gap-2">
                        <span class="text-secondary small fw-semibold text-uppercase">In Transit</span>
                        <div class="staff-stat-count fw-bold text-primary"><?php echo (int) ($counts['in_transit'] ?? 0); ?></div>
                    </div>
                </a>
            </div>
            <div class="col-3">
                <a href="?status=delivered" class="card h-100 border-0 shadow-sm text-decoration-none stat-card <?php echo $status_filter === 'delivered' ? 'border-top border-4 border-success' : ''; ?>">
                    <div class="card-body d-flex align-items-center justify-content-between gap-2">
                        <span class="text-secondary small fw-semibold text-uppercase">Delivered</span>
                        <div class="staff-stat-count fw-bold text-success"><?php echo (int) ($counts['delivered'] ?? 0); ?></div>
                    </div>
                </a>
            </div>
            <div class="col-3">
                <a href="?status=failed" class="card h-100 border-0 shadow-sm text-decoration-none stat-card <?php echo $status_filter === 'failed' ? 'border-top border-4 border-danger' : ''; ?>">
                    <div class="card-body d-flex align-items-center justify-content-between gap-2">
                        <span class="text-secondary small fw-semibold text-uppercase">Failed</span>
                        <div class="staff-stat-count fw-bold text-danger"><?php echo (int) ($counts['failed'] ?? 0); ?></div>
                    </div>
                </a>
            </div>
        </section>

        <section class="card border-0 shadow-sm mb-4 staff-card">
            <div class="card-body p-3 p-sm-4">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <div>
                        <h2 class="h5 fw-bold mb-0">Delivery</h2>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-filter" aria-hidden="true"></i>
                            <?php echo htmlspecialchars($queue_filter_labels[$status_filter] ?? 'Assigned'); ?>
                            <span class="badge text-bg-light text-primary">
                                <?php echo (int) ($status_filter === 'all' ? ($counts['total'] ?? 0) : ($counts[$status_filter] ?? 0)); ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <?php foreach ($queue_filter_labels as $filter_key => $filter_label):
                                $filter_count = $filter_key === 'all' ? (int) ($counts['total'] ?? 0) : (int) ($counts[$filter_key] ?? 0);
                            ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center justify-content-between gap-3 <?php echo $status_filter === $filter_key ? 'active' : ''; ?>" href="?status=<?php echo htmlspecialchars($filter_key); ?>">
                                    <span><?php echo htmlspecialchars($filter_label); ?></span>
                                    <span class="badge <?php echo $status_filter === $filter_key ? 'text-bg-light text-primary' : 'text-bg-secondary'; ?>"><?php echo $filter_count; ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($show_staff_batches): ?>
        <section class="d-grid gap-3 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-end">
                <div>
                    <h2 class="h4 fw-bold mb-0">My Delivery Batches</h2>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge rounded-pill text-bg-warning"><?php echo (int) ($batch_counts['assigned'] ?? 0); ?> assigned</span>
                    <span class="badge rounded-pill text-bg-primary"><?php echo (int) ($batch_counts['in_transit'] ?? 0); ?> in transit</span>
                    <span class="badge rounded-pill text-bg-success"><?php echo (int) ($batch_counts['completed'] ?? 0); ?> completed</span>
                </div>
            </div>

            <?php if (!empty($staff_batch_rows)): ?>
                <?php foreach ($staff_batch_rows as $batch):
                    $batch_id = (int) ($batch['batch_id'] ?? 0);
                    $batch_code = (string) ($batch['batch_code'] ?? ('Batch #' . $batch_id));
                    $batch_status = (string) ($batch['batch_status'] ?? 'assigned');
                    $batch_date = (string) ($batch['batch_date'] ?? '');
                    $zone_code = (string) ($batch['zone_code'] ?? '');
                    $zone_name = (string) ($batch['zone_name'] ?? '');
                    $batch_type = (string) ($batch['batch_type'] ?? 'normal');
                    $used_units = (int) ($batch['used_capacity_units'] ?? 0);
                    $limit_units = (int) ($batch['capacity_limit_units'] ?? delivery_batch_capacity_limit_units());
                    $order_count = (int) ($batch['order_count'] ?? 0);
                    $closed_count = (int) ($batch['closed_count'] ?? 0);
                    $delivered_count = (int) ($batch['delivered_count'] ?? 0);
                    $failed_count = (int) ($batch['failed_count'] ?? 0) + (int) ($batch['returned_count'] ?? 0);
                    $progress_width = $order_count > 0 ? min(100, (int) round(($closed_count / $order_count) * 100)) : 0;
                    $capacity_width = $limit_units > 0 ? min(100, (int) round(($used_units / $limit_units) * 100)) : 0;
                    $batch_orders = $staff_batch_items[$batch_id] ?? [];
                    $batch_panel_id = 'batchPanel' . $batch_id;
                ?>
                <article class="card border-0 shadow-sm batch-card">
                    <div class="card-body p-3 p-sm-4">
                        <div class="d-flex flex-column flex-xl-row gap-3 justify-content-xl-between align-items-xl-start">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <h3 class="h5 fw-bold mb-0"><?php echo htmlspecialchars($batch_code); ?></h3>
                                    <span class="badge rounded-pill <?php echo staff_batch_status_badge_class($batch_status); ?>"><?php echo staff_delivery_status_label($batch_status); ?></span>
                                    <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo htmlspecialchars($zone_code ?: 'UNZONED'); ?></span>
                                    <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo $closed_count; ?>/<?php echo $order_count; ?> done</span>
                                    <span class="badge rounded-pill text-bg-info"><?php echo $used_units; ?>/<?php echo $limit_units; ?>u</span>
                                </div>
                                <div class="text-secondary small"><?php echo htmlspecialchars($zone_name ?: 'Unzoned delivery area'); ?></div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-start justify-content-xl-end">
                                <button type="button" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="collapse" data-bs-target="#<?php echo $batch_panel_id; ?>" aria-expanded="false" aria-controls="<?php echo $batch_panel_id; ?>">
                                    <i class="bi bi-list-ul" aria-hidden="true"></i>Open
                                </button>
                                <?php if ($batch_status === 'assigned'): ?>
                                <form method="POST" class="d-grid d-sm-block start-batch-form" data-batch-id="<?php echo $batch_id; ?>" data-batch-code="<?php echo htmlspecialchars($batch_code, ENT_QUOTES); ?>">
                                    <input type="hidden" name="batch_id" value="<?php echo $batch_id; ?>">
                                    <button type="submit" name="start_batch" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2">
                                        <i class="bi bi-truck" aria-hidden="true"></i>Start Batch
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="collapse" id="<?php echo $batch_panel_id; ?>">
                        <div class="batch-summary-grid mt-3 mb-3">
                            <div class="batch-summary-tile">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Batch Date</div>
                                <div class="fw-semibold"><?php echo htmlspecialchars(staff_format_date($batch_date)); ?></div>
                            </div>
                            <div class="batch-summary-tile">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Orders</div>
                                <div class="fw-semibold"><?php echo $closed_count; ?>/<?php echo $order_count; ?> done</div>
                            </div>
                            <div class="batch-summary-tile">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Load</div>
                                <div class="fw-semibold"><?php echo $used_units; ?>/<?php echo $limit_units; ?> units</div>
                            </div>
                            <div class="batch-summary-tile">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Outcome</div>
                                <div class="fw-semibold text-success"><?php echo $delivered_count; ?> delivered</div>
                                <?php if ($failed_count > 0): ?>
                                    <div class="small text-danger"><?php echo $failed_count; ?> failed</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="batch-progress-grid mb-3">
                            <div>
                                <div class="d-flex justify-content-between small text-secondary mb-1">
                                    <span>Batch progress</span>
                                    <span><?php echo $progress_width; ?>%</span>
                                </div>
                                <div class="progress staff-progress-slim" role="progressbar" aria-label="Batch progress" aria-valuenow="<?php echo $closed_count; ?>" aria-valuemin="0" aria-valuemax="<?php echo $order_count; ?>">
                                    <div class="progress-bar bg-success staff-progress-bar" style="--staff-progress-width: <?php echo $progress_width; ?>%;"></div>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between small text-secondary mb-1">
                                    <span>Capacity</span>
                                    <span><?php echo $used_units; ?>/<?php echo $limit_units; ?> units</span>
                                </div>
                                <div class="progress staff-progress-slim" role="progressbar" aria-label="Batch capacity" aria-valuenow="<?php echo $used_units; ?>" aria-valuemin="0" aria-valuemax="<?php echo $limit_units; ?>">
                                    <div class="progress-bar bg-primary staff-progress-bar" style="--staff-progress-width: <?php echo $capacity_width; ?>%;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="batch-stops">
                            <?php if (!empty($batch_orders)): ?>
                            <?php foreach ($batch_orders as $batch_order):
                                $delivery_id = (int) ($batch_order['delivery_id'] ?? 0);
                                $order_id = (int) ($batch_order['order_id'] ?? 0);
                                $delivery_status = (string) ($batch_order['delivery_status'] ?? 'assigned');
                                $delivery_date = (string) ($batch_order['delivery_date'] ?? '');
                                $customer_name = (string) ($batch_order['customer_name'] ?? '');
                                $customer_phone = (string) ($batch_order['customer_phone'] ?? ($batch_order['contact_number'] ?? ''));
                                $delivery_address = (string) ($batch_order['delivery_address'] ?? '');
                                $item_summary = (string) ($batch_order['item_summary'] ?? 'No items listed');
                                $total_quantity = (int) ($batch_order['total_quantity'] ?? 0);
                                $item_units = (int) ($batch_order['capacity_units'] ?? 0);
                                $total_amount = (float) ($batch_order['total_amount'] ?? 0);
                                $order_notes = trim((string) ($batch_order['order_notes'] ?? ''));
                                $delivery_notes = trim((string) ($batch_order['delivery_notes'] ?? ''));
                                $delivered_at = (string) ($batch_order['delivered_at'] ?? '');
                                $can_update = $delivery_id > 0 && !in_array($delivery_status, ['delivered', 'failed', 'returned', 'cancelled'], true);
                                $details_id = 'batchStop' . $batch_id . '_' . $order_id;
                            ?>
                            <div class="batch-stop">
                                <div class="batch-stop-header">
                                    <div class="batch-stop-main">
                                        <div class="batch-stop-title mb-2">
                                            <h4 class="h6 fw-bold mb-0">Order #<?php echo $order_id; ?></h4>
                                            <span class="badge rounded-pill <?php echo staff_delivery_status_badge_class($delivery_status); ?>"><?php echo staff_delivery_status_label($delivery_status); ?></span>
                                            <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo $item_units; ?>u</span>
                                            <span class="small text-secondary"><?php echo htmlspecialchars(staff_format_date($delivery_date)); ?></span>
                                        </div>
                                        <div class="batch-stop-address"><?php echo htmlspecialchars(staff_excerpt($delivery_address)); ?></div>
                                    </div>

                                    <div class="batch-stop-side">
                                        <div class="fw-bold text-success mb-2">&#8369;<?php echo number_format($total_amount, 2); ?></div>
                                        <div class="batch-stop-actions justify-content-lg-end">
                                            <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="collapse" data-bs-target="#<?php echo $details_id; ?>" aria-expanded="false" aria-controls="<?php echo $details_id; ?>">
                                                <i class="bi bi-list-ul" aria-hidden="true"></i>Details
                                            </button>
                                            <?php if (!$can_update && $delivery_status === 'delivered' && $delivered_at !== ''): ?>
                                            <span class="small text-success fw-semibold">Delivered <?php echo htmlspecialchars(staff_format_date($delivered_at, 'M d, g:i A')); ?></span>
                                            <?php elseif ($delivery_id < 1): ?>
                                            <span class="small text-secondary fw-semibold">Delivery record not ready</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="collapse" id="<?php echo $details_id; ?>">
                                    <div class="batch-stop-details">
                                        <div class="batch-detail-block">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Contact</div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($customer_name); ?></div>
                                            <a href="tel:<?php echo htmlspecialchars($customer_phone); ?>" class="link-primary text-decoration-none">
                                                <i class="bi bi-telephone me-1" aria-hidden="true"></i><?php echo htmlspecialchars($customer_phone); ?>
                                            </a>
                                            <div class="small text-secondary mt-1">Cash on Delivery</div>
                                        </div>
                                        <div class="batch-detail-block">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Items</div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($item_summary); ?></div>
                                            <div class="small text-secondary">Total qty: <?php echo $total_quantity; ?></div>
                                        </div>
                                        <div class="batch-detail-block">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Address</div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($delivery_address); ?></div>
                                        </div>
                                        <div class="batch-detail-block">
                                            <div class="small text-secondary fw-semibold text-uppercase mb-1">Notes</div>
                                            <?php if ($order_notes !== ''): ?>
                                                <div class="text-warning-emphasis"><?php echo htmlspecialchars($order_notes); ?></div>
                                            <?php endif; ?>
                                            <?php if ($delivery_notes !== '' && in_array($delivery_status, ['failed', 'returned', 'cancelled'], true)): ?>
                                                <div class="text-danger <?php echo $order_notes !== '' ? 'mt-2' : ''; ?>"><?php echo htmlspecialchars($delivery_notes); ?></div>
                                            <?php endif; ?>
                                            <?php if ($order_notes === '' && !($delivery_notes !== '' && in_array($delivery_status, ['failed', 'returned', 'cancelled'], true))): ?>
                                                <div class="text-secondary">No notes</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($can_update): ?>
                                    <div class="batch-stop-actions mt-3 pt-3 border-top">
                                        <form method="POST" class="deliver-order-form" data-delivery-id="<?php echo $delivery_id; ?>" data-order-id="<?php echo $order_id; ?>">
                                            <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                                            <button type="submit" name="mark_delivered" class="btn btn-success btn-sm d-inline-flex align-items-center justify-content-center gap-2">
                                                <i class="bi bi-check-circle" aria-hidden="true"></i>Delivered
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#failModal" data-delivery-id="<?php echo $delivery_id; ?>" data-order-id="<?php echo $order_id; ?>">
                                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Failed
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-sm d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#returnModal" data-delivery-id="<?php echo $delivery_id; ?>" data-order-id="<?php echo $order_id; ?>">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Returned
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-secondary py-4">No orders are attached to this batch.</div>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card border-0 shadow-sm staff-card">
                    <div class="card-body text-center py-5">
                        <div class="display-6 text-secondary mb-3"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
                        <h3 class="h5 fw-bold">No active batches assigned</h3>
                        <p class="text-secondary mb-0">Confirmed batches assigned by admin will appear here with their route and order list.</p>
                    </div>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (!empty($delivery_rows) || in_array($status_filter, ['delivered', 'failed', 'returned', 'cancelled'], true)): ?>
        <section class="mb-3">
            <h2 class="h4 fw-bold mb-0"><?php echo in_array($status_filter, ['delivered', 'failed', 'returned', 'cancelled'], true) ? htmlspecialchars(staff_delivery_status_label($status_filter)) . ' Deliveries' : 'Other Deliveries'; ?></h2>
        </section>

        <section class="d-grid gap-3">
            <?php if (!empty($delivery_rows)): ?>
                <?php foreach ($delivery_rows as $d):
                    $delivery_id = (int) ($d['delivery_id'] ?? 0);
                    $order_id = (int) ($d['order_id'] ?? 0);
                    $delivery_status = (string) ($d['delivery_status'] ?? 'assigned');
                    $delivery_date = (string) ($d['delivery_date'] ?? '');
                    $is_today = $delivery_date === date('Y-m-d') && $delivery_status === 'assigned';
                    $item_summary = (string) ($d['item_summary'] ?? 'No items listed');
                    $total_quantity = (int) ($d['total_quantity'] ?? 0);
                    $customer_name = (string) ($d['customer_name'] ?? '');
                    $customer_phone = (string) ($d['customer_phone'] ?? ($d['contact_number'] ?? ''));
                    $delivery_address = (string) ($d['delivery_address'] ?? '');
                    $total_amount = (float) ($d['total_amount'] ?? 0);
                    $order_notes = trim((string) ($d['order_notes'] ?? ''));
                    $delivered_at = (string) ($d['delivered_at'] ?? '');
                    $delivery_notes = trim((string) ($d['delivery_notes'] ?? ''));
                    $can_update = !in_array($delivery_status, ['delivered', 'failed', 'returned', 'cancelled'], true);
                    $details_id = 'looseDelivery' . $delivery_id;
                ?>
                <article class="card border shadow-sm delivery-card <?php echo $is_today ? 'is-today' : ''; ?>">
                    <div class="card-body p-3 p-sm-4">
                        <div class="d-flex flex-column flex-md-row gap-3 justify-content-md-between align-items-md-start">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <h3 class="h5 fw-bold mb-0">Order #<?php echo $order_id; ?></h3>
                                    <?php if ($is_today): ?>
                                        <span class="badge text-bg-warning">Today</span>
                                    <?php endif; ?>
                                    <span class="badge rounded-pill <?php echo staff_delivery_status_badge_class($delivery_status); ?>">
                                        <?php echo staff_delivery_status_label($delivery_status); ?>
                                    </span>
                                </div>
                                <div class="text-secondary small"><?php echo htmlspecialchars(staff_excerpt($delivery_address)); ?></div>
                                <div class="small text-secondary"><?php echo htmlspecialchars(staff_format_date($delivery_date)); ?> - &#8369;<?php echo number_format($total_amount, 2); ?></div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="collapse" data-bs-target="#<?php echo $details_id; ?>" aria-expanded="false" aria-controls="<?php echo $details_id; ?>">
                                <i class="bi bi-list-ul" aria-hidden="true"></i>Details
                            </button>
                        </div>

                        <div class="collapse mt-3" id="<?php echo $details_id; ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <div class="small text-secondary fw-semibold text-uppercase mb-1">Customer</div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($customer_name); ?></div>
                                    <a href="tel:<?php echo htmlspecialchars($customer_phone); ?>" class="link-primary text-decoration-none small">
                                        <i class="bi bi-telephone me-1" aria-hidden="true"></i><?php echo htmlspecialchars($customer_phone); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-secondary fw-semibold text-uppercase mb-1">Delivery Address</div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($delivery_address); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="delivery-meta mb-3">
                            <div class="border rounded-3 p-3">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Items</div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($item_summary); ?></div>
                                <div class="small text-secondary">Total qty: <?php echo $total_quantity; ?></div>
                            </div>
                            <div class="border rounded-3 p-3">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Total</div>
                                <div class="fw-bold text-success">&#8369;<?php echo number_format($total_amount, 2); ?></div>
                            </div>
                            <div class="border rounded-3 p-3">
                                <div class="small text-secondary fw-semibold text-uppercase mb-1">Payment</div>
                                <div class="fw-semibold">Cash on Delivery</div>
                            </div>
                        </div>

                        <?php if ($order_notes !== ''): ?>
                        <div class="alert alert-warning py-2 mb-3">
                            <div class="small text-uppercase fw-semibold mb-1">Delivery Note</div>
                            <div><?php echo htmlspecialchars($order_notes); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($delivery_notes !== '' && in_array($delivery_status, ['failed', 'returned', 'cancelled'], true)): ?>
                        <div class="alert alert-danger py-2 mb-3">
                            <div class="small text-uppercase fw-semibold mb-1">
                                <?php echo $delivery_status === 'returned' ? 'Return Reason' : ($delivery_status === 'cancelled' ? 'Cancellation Note' : 'Failure Reason'); ?>
                            </div>
                            <div><?php echo htmlspecialchars($delivery_notes); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($can_update): ?>
                        <div class="delivery-action-bar pt-3 border-top">
                            <form method="POST" class="deliver-order-form" data-delivery-id="<?php echo $delivery_id; ?>" data-order-id="<?php echo $order_id; ?>">
                                <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                                <button type="submit" name="mark_delivered" class="btn btn-success d-inline-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle" aria-hidden="true"></i>Mark Delivered
                                </button>
                            </form>
                            <button type="button" class="btn btn-outline-danger d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#failModal" data-delivery-id="<?php echo $delivery_id; ?>" data-order-id="<?php echo $order_id; ?>">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Mark Failed
                            </button>
                            <button type="button" class="btn btn-outline-warning d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#returnModal" data-delivery-id="<?php echo $delivery_id; ?>" data-order-id="<?php echo $order_id; ?>">
                                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Mark Returned
                            </button>
                        </div>
                        <?php elseif ($delivery_status === 'delivered' && $delivered_at !== ''): ?>
                        <div class="text-success fw-semibold pt-3 border-top">
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                            Delivered on <?php echo htmlspecialchars(staff_format_date($delivered_at, 'M d, Y g:i A')); ?>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card border-0 shadow-sm staff-card">
                    <div class="card-body text-center py-5">
                        <div class="display-6 text-secondary mb-3"><i class="bi bi-inbox" aria-hidden="true"></i></div>
                        <h3 class="h5 fw-bold">No individual deliveries found</h3>
                        <p class="text-secondary mb-0">You do not have any loose <?php echo $status_filter !== 'all' ? htmlspecialchars(str_replace('_', ' ', $status_filter)) : ''; ?> deliveries right now.</p>
                    </div>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>

    <div class="modal fade" id="startBatchModal" tabindex="-1" aria-labelledby="startBatchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" id="startBatchModalForm">
                    <input type="hidden" name="start_batch" value="1">
                    <input type="hidden" name="batch_id" id="startBatchId">
                    <div class="modal-header">
                        <h2 class="modal-title h5 fw-bold" id="startBatchModalLabel">Start batch</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" id="startBatchMessage">Start this batch and mark all orders in transit?</p>
                        <div class="staff-action-progress d-none text-center mt-3" id="startBatchProgress" role="status" aria-live="polite">
                            <div class="spinner-border text-primary mb-2" aria-hidden="true"></div>
                            <div class="small fw-semibold">Starting batch...</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep Assigned</button>
                        <button type="submit" class="btn btn-primary" id="confirmStartBatchButton">Start Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deliverOrderModal" tabindex="-1" aria-labelledby="deliverOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" id="deliverOrderModalForm">
                    <input type="hidden" name="mark_delivered" value="1">
                    <input type="hidden" name="delivery_id" id="deliverDeliveryId">
                    <div class="modal-header">
                        <h2 class="modal-title h5 fw-bold" id="deliverOrderModalLabel">Mark delivered</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" id="deliverOrderMessage">Mark this order as delivered?</p>
                        <div class="staff-action-progress d-none text-center mt-3" id="deliverOrderProgress" role="status" aria-live="polite">
                            <div class="spinner-border text-success mb-2" aria-hidden="true"></div>
                            <div class="small fw-semibold">Saving delivery...</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="confirmDeliverOrderButton">Mark Delivered</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="failModal" tabindex="-1" aria-labelledby="failModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="failDeliveryForm">
                    <input type="hidden" name="mark_failed" value="1">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="failModalLabel">Mark Delivery Failed</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="delivery_id" id="failDeliveryId">
                        <p class="text-secondary" id="failOrderText">Please provide a reason for the delivery failure.</p>
                        <label for="failureReason" class="form-label fw-semibold">Failure reason</label>
                        <textarea class="form-control" id="failureReason" name="failure_reason" rows="4" placeholder="Customer not home, wrong address, unreachable contact number..." required></textarea>
                        <div class="staff-action-progress d-none text-center mt-3" id="failDeliveryProgress" role="status" aria-live="polite">
                            <div class="spinner-border text-danger mb-2" aria-hidden="true"></div>
                            <div class="small fw-semibold">Saving failed delivery...</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="confirmFailDeliveryButton">Confirm Failed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="returnModal" tabindex="-1" aria-labelledby="returnModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="returnDeliveryForm">
                    <input type="hidden" name="mark_returned" value="1">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="returnModalLabel">Mark Delivery Returned</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="delivery_id" id="returnDeliveryId">
                        <p class="text-secondary" id="returnOrderText">Please provide a reason for the returned delivery.</p>
                        <label for="returnReason" class="form-label fw-semibold">Return reason</label>
                        <textarea class="form-control" id="returnReason" name="return_reason" rows="4" placeholder="Broken gallon, wrong item, customer requested return..." required></textarea>
                        <div class="staff-action-progress d-none text-center mt-3" id="returnDeliveryProgress" role="status" aria-live="polite">
                            <div class="spinner-border text-warning mb-2" aria-hidden="true"></div>
                            <div class="small fw-semibold">Saving returned delivery...</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" id="confirmReturnDeliveryButton">Confirm Returned</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($result_modal_title !== ''): ?>
    <div class="modal fade" id="staffResultModal" tabindex="-1" aria-labelledby="staffResultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body text-center p-4">
                    <div class="staff-result-icon mx-auto mb-3">&#10003;</div>
                    <h2 class="h5 fw-bold mb-2" id="staffResultModalLabel"><?php echo htmlspecialchars($result_modal_title); ?></h2>
                    <p class="text-secondary mb-4"><?php echo htmlspecialchars($result_modal_message); ?></p>
                    <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetActionButton(button, label) {
            if (button) {
                button.disabled = false;
                button.innerHTML = label;
            }
        }

        function showActionProgress(progress, button, loadingLabel) {
            if (progress) {
                progress.classList.remove('d-none');
            }
            if (button) {
                button.disabled = true;
                button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${loadingLabel}`;
            }
        }

        const staffResultModalElement = document.getElementById('staffResultModal');
        if (staffResultModalElement) {
            new bootstrap.Modal(staffResultModalElement).show();
        }

        const startBatchModalElement = document.getElementById('startBatchModal');
        const startBatchModal = startBatchModalElement ? new bootstrap.Modal(startBatchModalElement) : null;
        const startBatchIdInput = document.getElementById('startBatchId');
        const startBatchMessage = document.getElementById('startBatchMessage');
        const startBatchProgress = document.getElementById('startBatchProgress');
        const startBatchForm = document.getElementById('startBatchModalForm');
        const confirmStartBatchButton = document.getElementById('confirmStartBatchButton');

        document.querySelectorAll('.start-batch-form').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();
                const batchId = form.dataset.batchId || '';
                const batchCode = form.dataset.batchCode || `Batch #${batchId}`;
                if (startBatchIdInput) {
                    startBatchIdInput.value = batchId;
                }
                if (startBatchMessage) {
                    startBatchMessage.textContent = `Start ${batchCode} and mark all orders in transit?`;
                }
                if (startBatchProgress) {
                    startBatchProgress.classList.add('d-none');
                }
                resetActionButton(confirmStartBatchButton, 'Start Batch');
                startBatchModal?.show();
            });
        });

        if (startBatchForm) {
            startBatchForm.addEventListener('submit', () => {
                showActionProgress(startBatchProgress, confirmStartBatchButton, 'Starting...');
            });
        }

        const deliverOrderModalElement = document.getElementById('deliverOrderModal');
        const deliverOrderModal = deliverOrderModalElement ? new bootstrap.Modal(deliverOrderModalElement) : null;
        const deliverDeliveryIdInput = document.getElementById('deliverDeliveryId');
        const deliverOrderMessage = document.getElementById('deliverOrderMessage');
        const deliverOrderProgress = document.getElementById('deliverOrderProgress');
        const deliverOrderForm = document.getElementById('deliverOrderModalForm');
        const confirmDeliverOrderButton = document.getElementById('confirmDeliverOrderButton');

        document.querySelectorAll('.deliver-order-form').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();
                const deliveryId = form.dataset.deliveryId || '';
                const orderId = form.dataset.orderId || '';
                if (deliverDeliveryIdInput) {
                    deliverDeliveryIdInput.value = deliveryId;
                }
                if (deliverOrderMessage) {
                    deliverOrderMessage.textContent = orderId ? `Mark order #${orderId} as delivered?` : 'Mark this order as delivered?';
                }
                if (deliverOrderProgress) {
                    deliverOrderProgress.classList.add('d-none');
                }
                resetActionButton(confirmDeliverOrderButton, 'Mark Delivered');
                deliverOrderModal?.show();
            });
        });

        if (deliverOrderForm) {
            deliverOrderForm.addEventListener('submit', () => {
                showActionProgress(deliverOrderProgress, confirmDeliverOrderButton, 'Saving...');
            });
        }

        const failModal = document.getElementById('failModal');
        const returnModal = document.getElementById('returnModal');
        const failDeliveryProgress = document.getElementById('failDeliveryProgress');
        const returnDeliveryProgress = document.getElementById('returnDeliveryProgress');
        const failDeliveryForm = document.getElementById('failDeliveryForm');
        const returnDeliveryForm = document.getElementById('returnDeliveryForm');
        const confirmFailDeliveryButton = document.getElementById('confirmFailDeliveryButton');
        const confirmReturnDeliveryButton = document.getElementById('confirmReturnDeliveryButton');

        if (failModal) {
            failModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const deliveryId = button?.getAttribute('data-delivery-id') || '';
                const orderId = button?.getAttribute('data-order-id') || '';

                document.getElementById('failDeliveryId').value = deliveryId;
                document.getElementById('failOrderText').textContent = orderId
                    ? `Please provide a reason for failing order #${orderId}.`
                    : 'Please provide a reason for the delivery failure.';
                if (failDeliveryProgress) {
                    failDeliveryProgress.classList.add('d-none');
                }
                resetActionButton(confirmFailDeliveryButton, 'Confirm Failed');
            });
        }

        if (returnModal) {
            returnModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const deliveryId = button?.getAttribute('data-delivery-id') || '';
                const orderId = button?.getAttribute('data-order-id') || '';

                document.getElementById('returnDeliveryId').value = deliveryId;
                document.getElementById('returnOrderText').textContent = orderId
                    ? `Please provide a reason for returning order #${orderId}.`
                    : 'Please provide a reason for the returned delivery.';
                if (returnDeliveryProgress) {
                    returnDeliveryProgress.classList.add('d-none');
                }
                resetActionButton(confirmReturnDeliveryButton, 'Confirm Returned');
            });
        }

        if (failDeliveryForm) {
            failDeliveryForm.addEventListener('submit', () => {
                if (!failDeliveryForm.checkValidity()) {
                    return;
                }
                showActionProgress(failDeliveryProgress, confirmFailDeliveryButton, 'Saving...');
            });
        }

        if (returnDeliveryForm) {
            returnDeliveryForm.addEventListener('submit', () => {
                if (!returnDeliveryForm.checkValidity()) {
                    return;
                }
                showActionProgress(returnDeliveryProgress, confirmReturnDeliveryButton, 'Saving...');
            });
        }
    </script>
</body>
</html>
