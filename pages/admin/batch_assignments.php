<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin_page_helpers.php';
require_once '../../includes/admin_order_helpers.php';
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

ensure_delivery_batch_schema($conn);

if (isset($_POST['assign_confirmed_batch'])) {
    $batch_id = (int) ($_POST['batch_id'] ?? 0);
    $staff_id = (int) ($_POST['staff_id'] ?? 0);
    $conn->begin_transaction();
    try {
        $assignment = assign_delivery_batch_to_staff($conn, $batch_id, $staff_id, $admin_id);
        $conn->commit();
        $success = "Assigned " . ($assignment['batch_code'] ?: "batch #$batch_id") . " to " . $assignment['staff_name'] . " with " . (int) $assignment['order_count'] . " order(s).";
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

$staff = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'staff' AND status = 'active' ORDER BY full_name ASC");
$staff_rows = $staff ? $staff->fetch_all(MYSQLI_ASSOC) : [];
$confirmed_batch_rows = fetch_delivery_confirmed_batches($conn);
$confirmed_batch_items = fetch_delivery_confirmed_batch_items_map($conn);
$assigned_batch_rows = fetch_delivery_assigned_batches($conn);
$assigned_batch_items = fetch_delivery_assigned_batch_items_map($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Assignment - ISRAPHIL Admin</title>
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
                <button class="nav-link active border-0 w-100 d-flex align-items-center gap-2 admin-order-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#deliveriesMenu" aria-expanded="true" aria-controls="deliveriesMenu">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Deliveries</span>
                </button>
                <div class="collapse show" id="deliveriesMenu">
                    <div class="admin-order-subnav">
                        <a href="order_batches.php">Order Batches</a>
                        <a href="batch_assignments.php" class="active">Batch Assignment</a>
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
                        <span class="navbar-brand mb-0 h1 fw-bold text-primary">Batch Assignment</span>
                        <div class="small text-secondary">Send confirmed batches to active riders.</div>
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
                    <div class="col-md-4">
                        <a href="order_batches.php" class="card border-0 shadow-sm h-100 text-decoration-none">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Drafting</span>
                                <div class="h3 fw-bold text-primary mt-2 mb-1">Order Batches</div>
                                <div class="small text-secondary">Review queues and confirm drafts.</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Ready</span>
                                <div class="display-6 fw-bold text-primary mt-2"><?php echo count($confirmed_batch_rows); ?></div>
                                <div class="small text-secondary">Confirmed batches waiting.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <a href="#active-batches" class="card border-0 shadow-sm h-100 text-decoration-none">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Active</span>
                                <div class="display-6 fw-bold text-success mt-2"><?php echo count($assigned_batch_rows); ?></div>
                                <div class="small text-secondary">Assigned or in transit.</div>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div>
                                <h2 class="h4 fw-bold mb-2">Ready to Assign</h2>
                                <p class="text-secondary mb-0">Confirmed batches are approved route groups waiting for a rider.</p>
                            </div>
                            <span class="badge rounded-pill text-bg-primary align-self-start"><?php echo count($confirmed_batch_rows); ?> ready</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($confirmed_batch_rows)): ?>
                            <div class="row g-3">
                                <?php foreach ($confirmed_batch_rows as $batch):
                                    $batch_id = (int) ($batch['batch_id'] ?? 0);
                                    $batch_code = (string) ($batch['batch_code'] ?? ('Batch #' . $batch_id));
                                    $batch_zone_code = (string) ($batch['zone_code'] ?? '');
                                    $batch_zone_name = (string) ($batch['zone_name'] ?? '');
                                    $batch_type = (string) ($batch['batch_type'] ?? 'normal');
                                    $batch_date = (string) ($batch['batch_date'] ?? '');
                                    $used_units = (int) ($batch['used_capacity_units'] ?? 0);
                                    $limit_units = (int) ($batch['capacity_limit_units'] ?? $batch_capacity_limit);
                                    $order_count = (int) ($batch['order_count'] ?? 0);
                                    $capacity_class = admin_queue_capacity_badge_class($used_units, $limit_units);
                                    $progress_width = $limit_units > 0 ? min(100, (int) round(($used_units / $limit_units) * 100)) : 0;
                                    $batch_items = $confirmed_batch_items[$batch_id] ?? [];
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
                                                    <div class="small text-secondary mt-1"><?php echo $order_count; ?> order<?php echo $order_count === 1 ? '' : 's'; ?></div>
                                                </div>
                                            </div>
                                            <div class="progress mt-3" role="progressbar" aria-label="Batch capacity" aria-valuenow="<?php echo $used_units; ?>" aria-valuemin="0" aria-valuemax="<?php echo $limit_units; ?>" style="height: 0.55rem;">
                                                <div class="progress-bar bg-<?php echo $used_units >= $limit_units ? 'success' : 'primary'; ?>" style="width: <?php echo $progress_width; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="p-3">
                                            <div class="row g-3">
                                                <div class="col-xl-8">
                                                    <?php if (!empty($batch_items)): ?>
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
                                                                    <?php foreach ($batch_items as $batch_item):
                                                                        $item_order_id = (int) ($batch_item['order_id'] ?? 0);
                                                                        $item_customer_name = (string) ($batch_item['customer_name'] ?? '');
                                                                        $item_customer_phone = (string) ($batch_item['customer_phone'] ?? '');
                                                                        $item_address = (string) ($batch_item['delivery_address'] ?? '');
                                                                        $item_summary = (string) ($batch_item['item_summary'] ?? 'No items listed');
                                                                        $item_units = (int) ($batch_item['capacity_units'] ?? 0);
                                                                        $detail_id = 'confirmedBatchDetail' . $batch_id . '_' . $item_order_id;
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
                                                                    </tr>
                                                                    <tr>
                                                                        <td colspan="5" class="bg-light p-0">
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
                                                </div>
                                                <div class="col-xl-4">
                                                    <div class="border rounded-3 p-3 bg-light h-100">
                                                        <h4 class="h6 fw-bold mb-3">Assign Rider</h4>
                                                        <?php if (!empty($staff_rows)): ?>
                                                            <form method="POST" class="d-grid gap-2" data-admin-confirm="Assign this batch to the selected rider?">
                                                                <input type="hidden" name="batch_id" value="<?php echo $batch_id; ?>">
                                                                <select name="staff_id" class="form-select form-select-sm" required>
                                                                    <option value="">Choose active rider</option>
                                                                    <?php foreach ($staff_rows as $s):
                                                                        $staff_id_option = (int) ($s['user_id'] ?? 0);
                                                                        $staff_name_option = (string) ($s['full_name'] ?? '');
                                                                    ?>
                                                                    <option value="<?php echo $staff_id_option; ?>">
                                                                        <?php echo htmlspecialchars($staff_name_option); ?>
                                                                    </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <button type="submit" name="assign_confirmed_batch" value="1" class="btn btn-primary btn-sm" <?php echo $order_count < 1 ? 'disabled' : ''; ?>>
                                                                    <i class="bi bi-person-check me-1" aria-hidden="true"></i>Assign Batch
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <p class="small text-secondary mb-0">No active riders are available.</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="display-6 text-secondary mb-3"><i class="bi bi-person-check" aria-hidden="true"></i></div>
                                <h3 class="h5 fw-bold">No confirmed batches ready</h3>
                                <p class="text-secondary mb-0">Confirm a draft batch first, then assign it to an active rider here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card border-0 shadow-sm" id="active-batches">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div>
                                <h2 class="h4 fw-bold mb-2">Active Batch Situation</h2>
                                <p class="text-secondary mb-0">Current assigned and in-transit batches, the rider handling each batch, and every order status inside it.</p>
                            </div>
                            <span class="badge rounded-pill text-bg-success align-self-start"><?php echo count($assigned_batch_rows); ?> active</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($assigned_batch_rows)): ?>
                            <div class="row g-3">
                                <?php foreach ($assigned_batch_rows as $batch):
                                    $batch_id = (int) ($batch['batch_id'] ?? 0);
                                    $batch_code = (string) ($batch['batch_code'] ?? ('Batch #' . $batch_id));
                                    $batch_status = (string) ($batch['batch_status'] ?? 'assigned');
                                    $batch_zone_code = (string) ($batch['zone_code'] ?? '');
                                    $batch_zone_name = (string) ($batch['zone_name'] ?? '');
                                    $staff_name = (string) ($batch['staff_name'] ?? 'Unassigned');
                                    $batch_date = (string) ($batch['batch_date'] ?? '');
                                    $assigned_at = (string) ($batch['assigned_at'] ?? '');
                                    $started_at = (string) ($batch['started_at'] ?? '');
                                    $used_units = (int) ($batch['used_capacity_units'] ?? 0);
                                    $limit_units = (int) ($batch['capacity_limit_units'] ?? $batch_capacity_limit);
                                    $order_count = (int) ($batch['order_count'] ?? 0);
                                    $progress_width = $limit_units > 0 ? min(100, (int) round(($used_units / $limit_units) * 100)) : 0;
                                    $batch_items = $assigned_batch_items[$batch_id] ?? [];
                                    $status_class = $batch_status === 'in_transit' ? 'text-bg-primary' : 'text-bg-warning';
                                ?>
                                <div class="col-12">
                                    <article class="border rounded-3 bg-white">
                                        <div class="p-3 border-bottom bg-light">
                                            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                                                <div>
                                                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                                        <h3 class="h5 fw-bold mb-0"><?php echo htmlspecialchars($batch_code); ?></h3>
                                                        <span class="badge rounded-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars(admin_order_status_label($batch_status)); ?></span>
                                                        <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo htmlspecialchars($batch_zone_code ?: 'UNZONED'); ?></span>
                                                    </div>
                                                    <div class="small text-secondary"><?php echo htmlspecialchars($batch_zone_name ?: 'Unzoned delivery area'); ?></div>
                                                    <div class="small text-secondary">Rider: <span class="fw-semibold text-dark"><?php echo htmlspecialchars($staff_name); ?></span></div>
                                                    <div class="small text-secondary">Batch date: <?php echo htmlspecialchars(admin_delivery_date_label($batch_date)); ?></div>
                                                </div>
                                                <div class="text-xl-end">
                                                    <span class="badge rounded-pill text-bg-light border text-secondary"><?php echo $order_count; ?> order<?php echo $order_count === 1 ? '' : 's'; ?></span>
                                                    <span class="badge rounded-pill text-bg-info"><?php echo $used_units; ?>/<?php echo $limit_units; ?> units</span>
                                                    <div class="small text-secondary mt-1">
                                                        <?php echo $batch_status === 'in_transit' && $started_at !== '' ? 'Started ' . htmlspecialchars(admin_format_date($started_at, 'M d, g:i A')) : 'Assigned ' . htmlspecialchars(admin_format_date($assigned_at, 'M d, g:i A')); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="progress mt-3" role="progressbar" aria-label="Batch capacity" aria-valuenow="<?php echo $used_units; ?>" aria-valuemin="0" aria-valuemax="<?php echo $limit_units; ?>" style="height: 0.55rem;">
                                                <div class="progress-bar bg-primary" style="width: <?php echo $progress_width; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="p-3">
                                            <?php if (!empty($batch_items)): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th scope="col">Order</th>
                                                                <th scope="col">Gallon</th>
                                                                <th scope="col">Address</th>
                                                                <th scope="col">Load</th>
                                                                <th scope="col">Status</th>
                                                                <th scope="col">Info</th>
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
                                                                $order_status = (string) ($batch_item['order_status'] ?? '');
                                                                $delivery_status = (string) ($batch_item['delivery_status'] ?? 'assigned');
                                                                $delivery_notes = trim((string) ($batch_item['delivery_notes'] ?? ''));
                                                                $detail_id = 'activeBatchDetail' . $batch_id . '_' . $item_order_id;
                                                            ?>
                                                            <tr>
                                                                <td class="fw-semibold">#<?php echo $item_order_id; ?></td>
                                                                <td class="small"><?php echo htmlspecialchars(admin_excerpt($item_summary, 48)); ?></td>
                                                                <td class="small"><?php echo htmlspecialchars(admin_excerpt($item_address, 54)); ?></td>
                                                                <td><span class="badge rounded-pill text-bg-light border text-secondary"><?php echo $item_units; ?>u</span></td>
                                                                <td>
                                                                    <span class="badge rounded-pill <?php echo admin_delivery_status_badge_class($delivery_status); ?>">
                                                                        <?php echo admin_delivery_status_label($delivery_status); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#<?php echo $detail_id; ?>" aria-expanded="false" aria-controls="<?php echo $detail_id; ?>">
                                                                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="6" class="bg-light p-0">
                                                                    <div class="collapse p-2" id="<?php echo $detail_id; ?>">
                                                                        <div class="row g-2 small">
                                                                            <div class="col-md-3"><span class="text-secondary">Customer:</span> <span class="fw-semibold"><?php echo htmlspecialchars($item_customer_name); ?></span></div>
                                                                            <div class="col-md-3"><span class="text-secondary">Phone:</span> <?php echo htmlspecialchars($item_customer_phone); ?></div>
                                                                            <div class="col-md-3"><span class="text-secondary">Order:</span> <?php echo admin_order_status_label($order_status); ?></div>
                                                                            <div class="col-md-3"><span class="text-secondary">Due:</span> <?php echo htmlspecialchars(admin_delivery_date_label($batch_item['delivery_date'] ?? '')); ?></div>
                                                                            <?php if ($delivery_notes !== ''): ?>
                                                                                <div class="col-12 text-danger"><span class="fw-semibold">Note:</span> <?php echo htmlspecialchars($delivery_notes); ?></div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-secondary mb-0">No active orders are attached to this batch.</p>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="display-6 text-secondary mb-3"><i class="bi bi-truck" aria-hidden="true"></i></div>
                                <h3 class="h5 fw-bold">No active batches</h3>
                                <p class="text-secondary mb-0">Assigned and in-transit batches will appear here with their rider and order status.</p>
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
