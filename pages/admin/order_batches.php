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
$admin_id = (int) ($admin_user['user_id'] ?? ($_SESSION['user_id'] ?? 0));
$admin_name = $admin_user['full_name'] ?? ($_SESSION['full_name'] ?? 'Administrator');
$success = '';
$error = '';
$batch_capacity_limit = delivery_batch_capacity_limit_units();

ensure_delivery_service_area_schema($conn);
ensure_delivery_batch_schema($conn);

if (isset($_POST['generate_draft_batches'])) {
    $conn->begin_transaction();
    try {
        $generation = generate_delivery_draft_batches($conn, $admin_id);
        $conn->commit();

        if ((int) $generation['generated_batches'] > 0) {
            $success = "Generated " . (int) $generation['generated_batches'] . " draft batch(es) with " . (int) $generation['batched_orders'] . " order(s) and " . (int) $generation['batched_units'] . " unit(s).";
        } else {
            $success = "No eligible active queue orders were available for draft batching.";
        }

        if (!empty($generation['skipped_orders'])) {
            $success .= " Skipped " . count($generation['skipped_orders']) . " oversized order(s) above the " . $batch_capacity_limit . "-unit limit.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

if (isset($_POST['confirm_draft_batch'])) {
    $batch_id = (int) ($_POST['batch_id'] ?? 0);
    $conn->begin_transaction();
    try {
        $batch = confirm_delivery_draft_batch($conn, $batch_id, $admin_id);
        $conn->commit();
        $success = "Confirmed " . (($batch['batch_code'] ?? '') ?: "batch #$batch_id") . ". It is now ready for rider assignment.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

if (isset($_POST['cancel_draft_batch'])) {
    $batch_id = (int) ($_POST['batch_id'] ?? 0);
    $conn->begin_transaction();
    try {
        $batch = cancel_delivery_draft_batch($conn, $batch_id, $admin_id);
        $conn->commit();
        $success = "Cancelled " . (($batch['batch_code'] ?? '') ?: "batch #$batch_id") . ". Its orders returned to the active queue.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

if (isset($_POST['remove_batch_order'])) {
    $batch_id = (int) ($_POST['batch_id'] ?? 0);
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $conn->begin_transaction();
    try {
        remove_order_from_delivery_draft_batch($conn, $batch_id, $order_id);
        $conn->commit();
        $success = "Removed order #$order_id from the draft batch.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

if (isset($_POST['add_order_to_draft_batch'])) {
    $batch_id = (int) ($_POST['batch_id'] ?? 0);
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $conn->begin_transaction();
    try {
        add_order_to_delivery_draft_batch($conn, $batch_id, $order_id);
        $conn->commit();
        $success = "Added order #$order_id to the draft batch.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

$active_queue_rows = admin_delivery_queue_rows($conn, 'active');
$active_queue_units = array_sum(array_map(function ($order) {
    return (int) ($order['capacity_units'] ?? 0);
}, $active_queue_rows));
$draft_batch_rows = fetch_delivery_draft_batches($conn);
$draft_batch_items = fetch_delivery_draft_batch_items_map($conn);
$confirmed_batch_rows = fetch_delivery_confirmed_batches($conn);
$delivery_queue_sections = [
    [
        'title' => 'Active Orders',
        'badge' => 'Ready for batching',
        'theme' => 'success',
        'icon' => 'bi-truck',
        'description' => 'Confirmed orders due today, overdue, or missing a delivery date.',
        'rows' => $active_queue_rows,
        'units' => $active_queue_units,
        'empty' => 'No confirmed orders are due for active delivery planning.',
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Batches - ISRAPHIL Admin</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../style/admin/admin_compact.css?v=20260512b" rel="stylesheet">
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
                <a href="dashboard.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>
                <a href="orders.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    <span>Orders</span>
                </a>
                <button class="nav-link active border-0 w-100 d-flex align-items-center gap-2 admin-order-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#deliveriesMenu" aria-expanded="true" aria-controls="deliveriesMenu">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Deliveries</span>
                </button>
                <div class="collapse show" id="deliveriesMenu">
                    <div class="admin-order-subnav">
                        <a href="order_batches.php" class="active">Order Batches</a>
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
                    <span class="navbar-brand mb-0 h1 fw-bold text-primary">Order Batches</span>
                    <div class="small text-secondary">Build delivery groups from the active queue.</div>
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
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Active Orders</span>
                                <div class="display-6 fw-bold text-success mt-2"><?php echo count($active_queue_rows); ?></div>
                                <div class="small text-secondary"><?php echo $active_queue_units; ?> total units</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Draft Batches</span>
                                <div class="display-6 fw-bold text-warning mt-2"><?php echo count($draft_batch_rows); ?></div>
                                <div class="small text-secondary"><?php echo $batch_capacity_limit; ?> units per batch</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <a href="batch_assignments.php" class="card border-0 shadow-sm h-100 text-decoration-none">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Ready to Assign</span>
                                <div class="display-6 fw-bold text-primary mt-2"><?php echo count($confirmed_batch_rows); ?></div>
                                <div class="small text-secondary">Confirmed batches</div>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                            <div>
                                <h2 class="h5 fw-bold mb-1">Generate Draft Batches</h2>
                                <p class="text-secondary mb-0">The system groups active orders by zone and capacity. Drafts stay editable until confirmed.</p>
                            </div>
                            <form method="POST" data-admin-confirm="Generate draft batches from the active delivery queue?">
                                <button type="submit" name="generate_draft_batches" value="1" class="btn btn-success" <?php echo empty($active_queue_rows) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-boxes me-1" aria-hidden="true"></i>
                                    Generate Drafts
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="row g-4 mb-4">
                    <?php foreach ($delivery_queue_sections as $queue_section):
                        $queue_rows = $queue_section['rows'];
                        $queue_theme = $queue_section['theme'];
                    ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <h2 class="h5 fw-bold mb-1">
                                            <i class="bi <?php echo htmlspecialchars($queue_section['icon']); ?> text-<?php echo htmlspecialchars($queue_theme); ?> me-1" aria-hidden="true"></i>
                                            <?php echo htmlspecialchars($queue_section['title']); ?>
                                        </h2>
                                        <p class="small text-secondary mb-0"><?php echo htmlspecialchars($queue_section['description']); ?></p>
                                    </div>
                                    <span class="badge rounded-pill text-bg-<?php echo htmlspecialchars($queue_theme); ?>"><?php echo count($queue_rows); ?> orders</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo (int) $queue_section['units']; ?> total units</span>
                                    <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo htmlspecialchars($queue_section['badge']); ?></span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($queue_rows)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th scope="col">Order</th>
                                                    <th scope="col">Gallon</th>
                                                    <th scope="col">Address</th>
                                                    <th scope="col">Load</th>
                                                    <th scope="col">Info</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($queue_rows as $queue_order):
                                                    $queue_order_id = (int) ($queue_order['order_id'] ?? 0);
                                                    $queue_customer_name = (string) ($queue_order['customer_name'] ?? '');
                                                    $queue_address = (string) ($queue_order['delivery_address'] ?? '');
                                                    $queue_items = (string) ($queue_order['item_summary'] ?? 'No items listed');
                                                    $queue_units = (int) ($queue_order['capacity_units'] ?? 0);
                                                    $queue_zone_code = (string) ($queue_order['zone_code'] ?? '');
                                                    $queue_zone_name = (string) ($queue_order['zone_name'] ?? '');
                                                    $queue_capacity_class = admin_queue_capacity_badge_class($queue_units, $batch_capacity_limit);
                                                    $detail_id = 'queueOrderDetail' . $queue_order_id;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold">#<?php echo $queue_order_id; ?></div>
                                                        <?php if ($queue_zone_code !== ''): ?>
                                                            <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo htmlspecialchars($queue_zone_code); ?></span>
                                                        <?php else: ?>
                                                            <span class="badge rounded-pill text-bg-warning">Unzoned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="small"><?php echo htmlspecialchars(admin_excerpt($queue_items, 48)); ?></td>
                                                    <td class="small"><?php echo htmlspecialchars(admin_excerpt($queue_address, 54)); ?></td>
                                                    <td>
                                                        <span class="badge rounded-pill <?php echo $queue_capacity_class; ?>">
                                                            <?php echo $queue_units; ?>/<?php echo $batch_capacity_limit; ?> units
                                                        </span>
                                                        <?php if ($queue_units > $batch_capacity_limit): ?>
                                                            <div class="small text-danger mt-1">Oversized</div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#<?php echo $detail_id; ?>" aria-expanded="false" aria-controls="<?php echo $detail_id; ?>">
                                                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="5" class="bg-light p-0">
                                                        <div class="collapse p-2" id="<?php echo $detail_id; ?>">
                                                            <div class="row g-2 small">
                                                                <div class="col-md-4"><span class="text-secondary">Customer:</span> <span class="fw-semibold"><?php echo htmlspecialchars($queue_customer_name); ?></span></div>
                                                                <div class="col-md-4"><span class="text-secondary">Due:</span> <?php echo htmlspecialchars(admin_delivery_date_label($queue_order['delivery_date'] ?? '')); ?></div>
                                                                <div class="col-md-4"><span class="text-secondary">Zone:</span> <?php echo htmlspecialchars($queue_zone_name ?: ($queue_zone_code ?: 'Unzoned')); ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5 px-3">
                                        <div class="display-6 text-secondary mb-2"><i class="bi bi-inbox" aria-hidden="true"></i></div>
                                        <p class="text-secondary mb-0"><?php echo htmlspecialchars($queue_section['empty']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </section>

                <section class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div>
                                <h2 class="h4 fw-bold mb-2">Draft Batches</h2>
                                <p class="text-secondary mb-0">Review, adjust, and confirm delivery groups before assignment.</p>
                            </div>
                            <span class="badge rounded-pill text-bg-warning align-self-start"><?php echo count($draft_batch_rows); ?> drafts</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($draft_batch_rows)): ?>
                            <div class="row g-3">
                                <?php foreach ($draft_batch_rows as $batch):
                                    $batch_id = (int) ($batch['batch_id'] ?? 0);
                                    $batch_code = (string) ($batch['batch_code'] ?? ('Batch #' . $batch_id));
                                    $batch_zone_code = (string) ($batch['zone_code'] ?? '');
                                    $batch_zone_name = (string) ($batch['zone_name'] ?? '');
                                    $batch_type = (string) ($batch['batch_type'] ?? 'normal');
                                    $batch_date = (string) ($batch['batch_date'] ?? '');
                                    $used_units = (int) ($batch['used_capacity_units'] ?? 0);
                                    $limit_units = (int) ($batch['capacity_limit_units'] ?? $batch_capacity_limit);
                                    $remaining_units = max(0, $limit_units - $used_units);
                                    $order_count = (int) ($batch['order_count'] ?? 0);
                                    $capacity_class = admin_queue_capacity_badge_class($used_units, $limit_units);
                                    $progress_width = $limit_units > 0 ? min(100, (int) round(($used_units / $limit_units) * 100)) : 0;
                                    $batch_items = $draft_batch_items[$batch_id] ?? [];
                                ?>
                                <div class="col-12">
                                    <article class="border rounded-3 bg-white">
                                        <div class="p-3 border-bottom bg-light">
                                            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                                                <div>
                                                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                                        <h3 class="h5 fw-bold mb-0"><?php echo htmlspecialchars($batch_code); ?></h3>
                                                        <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo htmlspecialchars($batch_zone_code ?: 'UNZONED'); ?></span>
                                                        <span class="badge rounded-pill text-bg-<?php echo $batch_type === 'underfilled' ? 'warning' : ($batch_type === 'merged' ? 'info' : 'success'); ?>">
                                                            <?php echo htmlspecialchars(ucfirst($batch_type)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small text-secondary"><?php echo htmlspecialchars($batch_zone_name ?: 'Unzoned delivery area'); ?></div>
                                                    <div class="small text-secondary">Batch date: <?php echo htmlspecialchars(admin_delivery_date_label($batch_date)); ?></div>
                                                </div>
                                                <div class="text-xl-end">
                                                    <span class="badge rounded-pill <?php echo $capacity_class; ?>"><?php echo $used_units; ?>/<?php echo $limit_units; ?> units</span>
                                                    <div class="small text-secondary mt-1"><?php echo $remaining_units; ?> units remaining</div>
                                                </div>
                                            </div>
                                            <div class="progress mt-3" role="progressbar" aria-label="Batch capacity" aria-valuenow="<?php echo $used_units; ?>" aria-valuemin="0" aria-valuemax="<?php echo $limit_units; ?>" style="height: 0.55rem;">
                                                <div class="progress-bar bg-<?php echo $used_units >= $limit_units ? 'success' : 'primary'; ?>" style="width: <?php echo $progress_width; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="p-3">
                                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                                                <div>
                                                    <p class="fw-semibold mb-1"><?php echo $order_count; ?> order<?php echo $order_count === 1 ? '' : 's'; ?> in this draft</p>
                                                    <p class="small text-secondary mb-0">Adjust the load, then confirm when it is ready for assignment.</p>
                                                </div>
                                                <div class="d-flex flex-wrap gap-2 align-items-start">
                                                    <form method="POST" data-admin-confirm="Confirm this draft batch?">
                                                        <input type="hidden" name="batch_id" value="<?php echo $batch_id; ?>">
                                                        <button type="submit" name="confirm_draft_batch" value="1" class="btn btn-success btn-sm" <?php echo $order_count < 1 ? 'disabled' : ''; ?>>
                                                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Confirm
                                                        </button>
                                                    </form>
                                                    <form method="POST" data-admin-confirm="Cancel this draft batch and return its orders to the active queue?">
                                                        <input type="hidden" name="batch_id" value="<?php echo $batch_id; ?>">
                                                        <button type="submit" name="cancel_draft_batch" value="1" class="btn btn-outline-danger btn-sm">
                                                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancel Draft
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            <?php if (!empty($batch_items)): ?>
                                                <div class="table-responsive mb-3">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th scope="col">Order</th>
                                                                <th scope="col">Gallon</th>
                                                                <th scope="col">Address</th>
                                                                <th scope="col">Load</th>
                                                                <th scope="col">Info</th>
                                                                <th scope="col">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($batch_items as $batch_item):
                                                                $item_order_id = (int) ($batch_item['order_id'] ?? 0);
                                                                $item_customer_name = (string) ($batch_item['customer_name'] ?? '');
                                                                $item_customer_phone = (string) ($batch_item['customer_phone'] ?? '');
                                                                $item_address = (string) ($batch_item['delivery_address'] ?? '');
                                                                $item_summary = (string) ($batch_item['item_summary'] ?? 'No items listed');
                                                                $item_units = (int) ($batch_item['capacity_units'] ?? 0);
                                                                $detail_id = 'draftBatchDetail' . $batch_id . '_' . $item_order_id;
                                                            ?>
                                                            <tr>
                                                                <td class="fw-semibold">#<?php echo $item_order_id; ?></td>
                                                                <td class="small"><?php echo htmlspecialchars(admin_excerpt($item_summary, 48)); ?></td>
                                                                <td class="small"><?php echo htmlspecialchars(admin_excerpt($item_address, 54)); ?></td>
                                                                <td><span class="badge rounded-pill text-bg-light border text-secondary"><?php echo $item_units; ?>u</span></td>
                                                                <td>
                                                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#<?php echo $detail_id; ?>" aria-expanded="false" aria-controls="<?php echo $detail_id; ?>">
                                                                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                                                                    </button>
                                                                </td>
                                                                <td>
                                                                    <form method="POST" data-admin-confirm="Remove order #<?php echo $item_order_id; ?> from this draft?">
                                                                        <input type="hidden" name="batch_id" value="<?php echo $batch_id; ?>">
                                                                        <input type="hidden" name="order_id" value="<?php echo $item_order_id; ?>">
                                                                        <button type="submit" name="remove_batch_order" value="1" class="btn btn-outline-danger btn-sm">Remove</button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="6" class="bg-light p-0">
                                                                    <div class="collapse p-2" id="<?php echo $detail_id; ?>">
                                                                        <div class="row g-2 small">
                                                                            <div class="col-md-4"><span class="text-secondary">Customer:</span> <span class="fw-semibold"><?php echo htmlspecialchars($item_customer_name); ?></span></div>
                                                                            <div class="col-md-4"><span class="text-secondary">Phone:</span> <?php echo htmlspecialchars($item_customer_phone); ?></div>
                                                                            <div class="col-md-4"><span class="text-secondary">Due:</span> <?php echo htmlspecialchars(admin_delivery_date_label($batch_item['delivery_date'] ?? '')); ?></div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>

                                            <form method="POST" class="row g-2 align-items-end border-top pt-3" data-admin-confirm="Add this order to the draft batch?">
                                                <input type="hidden" name="batch_id" value="<?php echo $batch_id; ?>">
                                                <div class="col-lg">
                                                    <label class="form-label small fw-semibold">Add active queue order</label>
                                                    <select name="order_id" class="form-select form-select-sm" required <?php echo empty($active_queue_rows) || $remaining_units < 1 ? 'disabled' : ''; ?>>
                                                        <option value="">Choose eligible order</option>
                                                        <?php foreach ($active_queue_rows as $queue_order):
                                                            $queue_order_id = (int) ($queue_order['order_id'] ?? 0);
                                                            $queue_units = (int) ($queue_order['capacity_units'] ?? 0);
                                                            $queue_customer_name = (string) ($queue_order['customer_name'] ?? '');
                                                            $queue_zone_code = (string) ($queue_order['zone_code'] ?? 'UNZONED');
                                                            $does_fit = $queue_units <= $remaining_units && $queue_units <= $limit_units;
                                                        ?>
                                                        <option value="<?php echo $queue_order_id; ?>" <?php echo $does_fit ? '' : 'disabled'; ?>>
                                                            #<?php echo $queue_order_id; ?> - <?php echo htmlspecialchars($queue_customer_name); ?> - <?php echo htmlspecialchars($queue_zone_code ?: 'UNZONED'); ?> - <?php echo $queue_units; ?>u<?php echo $does_fit ? '' : ' (does not fit)'; ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-lg-auto">
                                                    <button type="submit" name="add_order_to_draft_batch" value="1" class="btn btn-primary btn-sm" <?php echo empty($active_queue_rows) || $remaining_units < 1 ? 'disabled' : ''; ?>>
                                                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Add Order
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </article>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="display-6 text-secondary mb-3"><i class="bi bi-box" aria-hidden="true"></i></div>
                                <h3 class="h5 fw-bold">No draft batches yet</h3>
                                <p class="text-secondary mb-0">Generate drafts from the active queue when confirmed orders are ready for delivery planning.</p>
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
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title h5 fw-bold" id="adminConfirmModalLabel">Confirm action</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="adminConfirmMessage">Continue with this action?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Go Back</button>
                    <button type="button" class="btn btn-primary" id="adminConfirmButton">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const adminConfirmModalElement = document.getElementById('adminConfirmModal');
        const adminConfirmModal = adminConfirmModalElement ? new bootstrap.Modal(adminConfirmModalElement) : null;
        const adminConfirmMessage = document.getElementById('adminConfirmMessage');
        const adminConfirmButton = document.getElementById('adminConfirmButton');
        let pendingAdminConfirmForm = null;
        let pendingAdminConfirmSubmitter = null;

        document.querySelectorAll('form[data-admin-confirm]').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();
                pendingAdminConfirmForm = form;
                pendingAdminConfirmSubmitter = event.submitter || null;
                if (adminConfirmMessage) {
                    adminConfirmMessage.textContent = form.dataset.adminConfirm || 'Continue with this action?';
                }
                if (adminConfirmButton) {
                    adminConfirmButton.disabled = false;
                    adminConfirmButton.innerHTML = 'Continue';
                }
                adminConfirmModal?.show();
            });
        });

        adminConfirmButton?.addEventListener('click', () => {
            if (!pendingAdminConfirmForm) {
                return;
            }
            if (pendingAdminConfirmSubmitter?.name) {
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = pendingAdminConfirmSubmitter.name;
                actionInput.value = pendingAdminConfirmSubmitter.value || '1';
                pendingAdminConfirmForm.appendChild(actionInput);
            }
            adminConfirmButton.disabled = true;
            adminConfirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Working...';
            pendingAdminConfirmForm.submit();
        });
    </script>
</body>
</html>
