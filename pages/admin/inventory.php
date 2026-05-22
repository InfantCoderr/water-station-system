<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/order_logic.php';
require_once '../../includes/admin_page_helpers.php';

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
$inventory = false;
$low_stock = false;
$inventory_rows = [];

function ensure_inventory_schema($conn) {
    $column = $conn->query("SHOW COLUMNS FROM inventory LIKE 'item_image'");
    if ($column && $column->num_rows === 0) {
        if (!$conn->query("ALTER TABLE inventory ADD COLUMN item_image VARCHAR(255) DEFAULT NULL AFTER item_name")) {
            error_log('Failed to add inventory item_image column: ' . $conn->error);
        }
    }

    $column = $conn->query("SHOW COLUMNS FROM inventory LIKE 'capacity_units'");
    if ($column && $column->num_rows === 0) {
        if ($conn->query("ALTER TABLE inventory ADD COLUMN capacity_units TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER item_type")) {
            $conn->query("UPDATE inventory SET capacity_units = 2 WHERE LOWER(item_name) LIKE '%round%'");
            $conn->query("UPDATE inventory SET capacity_units = 1 WHERE LOWER(item_name) LIKE '%slim%' OR capacity_units < 1");
        } else {
            error_log('Failed to add inventory capacity_units column: ' . $conn->error);
        }
    }
}

function inventory_upload_image($field_name, &$error, $required = false, $current_path = '') {
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            $error = "Please upload an item image.";
        }
        return $current_path;
    }

    if ($_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        $error = "Image upload failed. Please try again.";
        return $current_path;
    }

    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $mime_type = function_exists('mime_content_type') ? mime_content_type($_FILES[$field_name]['tmp_name']) : false;

    if (!isset($allowed_types[$mime_type])) {
        $error = "Please upload a JPG, PNG, GIF, or WEBP image.";
        return $current_path;
    }

    if ($_FILES[$field_name]['size'] > 2 * 1024 * 1024) {
        $error = "Image must be 2MB or smaller.";
        return $current_path;
    }

    $upload_dir = __DIR__ . '/../../uploads/inventory';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true)) {
        $error = "Unable to prepare the image upload folder.";
        return $current_path;
    }

    $filename = 'inventory_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed_types[$mime_type];
    $target_path = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($_FILES[$field_name]['tmp_name'], $target_path)) {
        $error = "Unable to save the uploaded image.";
        return $current_path;
    }

    return 'uploads/inventory/' . $filename;
}

function inventory_image_src($image_path) {
    if (!empty($image_path)) {
        return '../../' . ltrim($image_path, '/');
    }

    return '../../image.gif/water.png';
}

ensure_inventory_schema($conn);

if (isset($_POST['update_stock'])) {
    $inventory_id = (int) ($_POST['inventory_id'] ?? 0);
    $new_stock = intval($_POST['new_stock'] ?? 0);
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $reorder_level = intval($_POST['reorder_level'] ?? 10);
    $capacity_units = intval($_POST['capacity_units'] ?? 1);
    $item_image = '';

    if ($inventory_id < 1 || $new_stock < 0 || $unit_price <= 0 || $reorder_level < 1 || $capacity_units < 1 || $capacity_units > 16) {
        $error = "Please enter valid stock, price, reorder, and capacity values.";
    } else {
        $current_image = '';
        $image_stmt = $conn->prepare("SELECT item_image FROM inventory WHERE inventory_id = ?");
        if ($image_stmt) {
            $image_stmt->bind_param("i", $inventory_id);
            $image_stmt->execute();
            $image_result = $image_stmt->get_result()->fetch_assoc();
            $current_image = $image_result['item_image'] ?? '';
            $image_stmt->close();

            if (!$image_result) {
                $error = "Inventory item was not found.";
            }
        } else {
            $error = "Unable to prepare inventory lookup.";
        }

        $item_image = inventory_upload_image('item_image', $error, false, $current_image);
    }

    if (empty($error)) {
        $stmt = $conn->prepare("UPDATE inventory SET stock_quantity = ?, unit_price = ?, reorder_level = ?, capacity_units = ?, item_image = ?, updated_by = ? WHERE inventory_id = ?");
        if ($stmt) {
            $stmt->bind_param("idiisii", $new_stock, $unit_price, $reorder_level, $capacity_units, $item_image, $admin_id, $inventory_id);
            if ($stmt->execute()) {
                sync_inventory_status($conn, $inventory_id);
                $success = "Inventory updated successfully!";
            } else {
                $error = "Failed to update inventory.";
            }
            $stmt->close();
        } else {
            $error = "Unable to prepare inventory update.";
        }
    }
}

if (isset($_POST['add_item'])) {
    $item_name = trim($_POST['item_name'] ?? '');
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $reorder_level = intval($_POST['reorder_level'] ?? 10);
    $capacity_units = intval($_POST['capacity_units'] ?? 1);
    $item_image = '';

    if (empty($item_name) || $stock_quantity < 0 || $unit_price <= 0 || $capacity_units < 1 || $capacity_units > 16) {
        $error = "Please fill in all fields with valid values.";
    } else {
        $check = $conn->prepare("SELECT inventory_id FROM inventory WHERE item_name = ?");
        if ($check) {
            $check->bind_param("s", $item_name);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Item with this name already exists.";
            } else {
                $item_image = inventory_upload_image('item_image', $error, true);
            }
            $check->close();
        } else {
            $error = "Unable to check inventory item name.";
        }

        if (empty($error)) {
            $status = $stock_quantity > 0 ? 'available' : 'out_of_stock';
            $stmt = $conn->prepare("INSERT INTO inventory (item_name, item_image, capacity_units, stock_quantity, unit_price, reorder_level, status, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssiidisi", $item_name, $item_image, $capacity_units, $stock_quantity, $unit_price, $reorder_level, $status, $admin_id);

                if ($stmt->execute()) {
                    $success = "New item added to inventory!";
                } else {
                    $error = "Failed to add item.";
                }
                $stmt->close();
            } else {
                $error = "Unable to prepare inventory item creation.";
            }
        }
    }
}

if (isset($_POST['delete_item'])) {
    $inventory_id = (int) ($_POST['inventory_id'] ?? 0);

    if ($inventory_id < 1) {
        $error = "Invalid inventory item.";
    } else {
        $check = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE inventory_id = ?");
        if ($check) {
            $check->bind_param("i", $inventory_id);
            $check->execute();
            $order_count = (int) ($check->get_result()->fetch_assoc()['count'] ?? 0);

            if ($order_count > 0) {
                $stmt = $conn->prepare("UPDATE inventory SET status = 'discontinued', updated_by = ? WHERE inventory_id = ?");
                if ($stmt) {
                    $stmt->bind_param("ii", $admin_id, $inventory_id);
                    if ($stmt->execute()) {
                        $success = "Item marked as discontinued (has order history).";
                    } else {
                        $error = "Failed to update item status.";
                    }
                    $stmt->close();
                } else {
                    $error = "Unable to prepare item status update.";
                }
            } else {
                $stmt = $conn->prepare("DELETE FROM inventory WHERE inventory_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $inventory_id);
                    if ($stmt->execute()) {
                        $success = "Item deleted successfully.";
                    } else {
                        $error = "Failed to delete item.";
                    }
                    $stmt->close();
                } else {
                    $error = "Unable to prepare item deletion.";
                }
            }
            $check->close();
        } else {
            $error = "Unable to check item order history.";
        }
    }
}

if (isset($_POST['toggle_status'])) {
    $inventory_id = (int) ($_POST['inventory_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if ($inventory_id > 0 && in_array($new_status, ['available', 'discontinued'], true)) {
        $stmt = $conn->prepare("UPDATE inventory SET status = ?, updated_by = ? WHERE inventory_id = ?");
        if ($stmt) {
            $stmt->bind_param("sii", $new_status, $admin_id, $inventory_id);
            if ($stmt->execute()) {
                sync_inventory_status($conn, $inventory_id);
                $success = "Item status updated to " . str_replace('_', ' ', $new_status);
            } else {
                $error = "Failed to update item status.";
            }
            $stmt->close();
        } else {
            $error = "Unable to prepare item status update.";
        }
    } else {
        $error = "Invalid inventory status request.";
    }
}

$inventory = $conn->query("SELECT * FROM inventory ORDER BY inventory_id ASC");
$low_stock = $conn->query("SELECT * FROM inventory WHERE stock_quantity <= reorder_level AND status != 'discontinued'");
$low_stock_count = $low_stock ? $low_stock->num_rows : 0;
$total_value = admin_scalar_query($conn, "SELECT SUM(stock_quantity * unit_price) as total FROM inventory WHERE status != 'discontinued'", 'total', 0);
$inventory_rows = $inventory ? $inventory->fetch_all(MYSQLI_ASSOC) : [];
$inventory_count = count($inventory_rows);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - ISRAPHIL Admin</title>
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
                <a href="inventory.php" class="nav-link active d-flex align-items-center gap-2" aria-current="page">
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
                    <span class="navbar-brand mb-0 h1 fw-bold text-primary">Inventory Management</span>
                    <div class="small text-secondary">Administrator: <?php echo htmlspecialchars($admin_name); ?></div>
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

                <section class="bg-white border rounded-4 shadow-sm p-4 p-lg-5 mb-4">
                    <span class="badge text-bg-info mb-3">Inventory Control</span>
                    <div class="row g-4 align-items-end">
                        <div class="col-lg-8">
                            <h1 class="display-6 fw-bold text-primary mb-3">Protect stock availability before orders back up</h1>
                            <p class="lead text-secondary mb-0">Use this inventory workspace to price gallon items, set reorder thresholds, and keep high-demand stock visible to the team.</p>
                        </div>
                        <div class="col-lg-4">
                            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                                <a href="#inventory-form" class="btn btn-success">Add inventory item</a>
                                <a href="#inventory-list" class="btn btn-primary">Review inventory list</a>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if ($low_stock_count > 0): ?>
                    <div class="alert alert-warning d-flex align-items-start gap-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-4" aria-hidden="true"></i>
                        <div>
                            <h2 class="h5 alert-heading mb-1">Low Stock Alert</h2>
                            <p class="mb-0"><?php echo $low_stock_count; ?> item(s) are at or below reorder level. Restock soon.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <section class="row g-3 mb-4">
                    <div class="col-md-4">
                        <article class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Total Items</span>
                                <div class="display-5 fw-bold text-primary mt-2"><?php echo $inventory_count; ?></div>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="card h-100 border-0 shadow-sm border-top border-4 <?php echo $low_stock_count > 0 ? 'border-warning' : 'border-success'; ?>">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Low Stock Items</span>
                                <div class="display-5 fw-bold <?php echo $low_stock_count > 0 ? 'text-warning' : 'text-success'; ?> mt-2"><?php echo $low_stock_count; ?></div>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="card h-100 border-0 shadow-sm border-top border-4 border-info">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Total Stock Value</span>
                                <div class="display-6 fw-bold text-info mt-2">&#8369;<?php echo number_format($total_value, 2); ?></div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="card border-0 shadow-sm mb-4" id="inventory-form">
                    <div class="card-body p-4">
                        <h2 class="h4 fw-bold mb-2">Add New Item</h2>
                        <p class="text-secondary mb-4">Create inventory records with accurate stock, pricing, and reorder settings before the item becomes available for ordering.</p>
                        <form method="POST" action="" class="row g-3" enctype="multipart/form-data">
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="item_name" placeholder="e.g., 5-Gallon Purified" required>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label">Initial Stock <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="stock_quantity" min="0" value="0" required>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label">Unit Price (&#8369;) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="unit_price" min="0.01" step="0.01" placeholder="25.00" required>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label">Capacity Units</label>
                                <input type="number" class="form-control" name="capacity_units" min="1" max="16" value="1" required>
                                <div class="form-text">Slim = 1, round = 2.</div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" class="form-control" name="reorder_level" min="1" value="10">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Item Image <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="item_image" accept="image/jpeg,image/png,image/gif,image/webp" required>
                                <div class="form-text">Upload a JPG, PNG, GIF, or WEBP image up to 2MB. This image appears in the customer item choices.</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_item" class="btn btn-success">Add Item</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="card border-0 shadow-sm" id="inventory-list">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <h2 class="h4 fw-bold mb-2">Inventory List</h2>
                        <p class="text-secondary mb-0">Track every item currently available for ordering, identify low-stock risk quickly, and adjust visibility when products should be hidden from customers.</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($inventory_rows)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Image</th>
                                            <th scope="col">Item Name</th>
                                            <th scope="col">Stock</th>
                                            <th scope="col">Capacity</th>
                                            <th scope="col">Price</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inventory_rows as $item):
                                            $inventory_id = (int) ($item['inventory_id'] ?? 0);
                                            $item_name = (string) ($item['item_name'] ?? '');
                                            $item_image = (string) ($item['item_image'] ?? '');
                                            $stock_quantity = (int) ($item['stock_quantity'] ?? 0);
                                            $unit_price = (float) ($item['unit_price'] ?? 0);
                                            $reorder_level = (int) ($item['reorder_level'] ?? 10);
                                            $capacity_units = (int) ($item['capacity_units'] ?? 1);
                                            $item_status = (string) ($item['status'] ?? 'out_of_stock');
                                            $is_low = $stock_quantity <= $reorder_level;
                                            $status_class = $item_status === 'available' ? 'success' : ($item_status === 'out_of_stock' ? 'secondary' : 'warning');
                                            $item_image_src = inventory_image_src($item_image);
                                        ?>
                                        <tr>
                                            <td>#<?php echo $inventory_id; ?></td>
                                            <td>
                                                <img src="<?php echo htmlspecialchars($item_image_src); ?>" alt="<?php echo htmlspecialchars($item_name); ?>" width="64" height="64" class="rounded border object-fit-cover bg-light">
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($item_name); ?></div>
                                                <div class="small text-secondary">Reorder at: <?php echo $reorder_level; ?> gallons</div>
                                            </td>
                                            <td>
                                                <span class="<?php echo $is_low ? 'text-danger' : 'text-success'; ?> fw-semibold">
                                                    <?php echo $stock_quantity; ?> gallons
                                                </span>
                                                <?php if ($is_low): ?>
                                                    <br><small class="text-danger">Low stock</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill text-bg-info"><?php echo $capacity_units; ?> unit<?php echo $capacity_units === 1 ? '' : 's'; ?></span>
                                            </td>
                                            <td class="fw-semibold">&#8369;<?php echo number_format($unit_price, 2); ?></td>
                                            <td>
                                                <span class="badge rounded-pill text-bg-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $item_status)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button
                                                        class="btn btn-primary btn-sm"
                                                        data-inventory-id="<?php echo $inventory_id; ?>"
                                                        data-name="<?php echo htmlspecialchars($item_name, ENT_QUOTES); ?>"
                                                        data-stock="<?php echo $stock_quantity; ?>"
                                                        data-price="<?php echo $unit_price; ?>"
                                                        data-reorder="<?php echo $reorder_level; ?>"
                                                        data-capacity="<?php echo $capacity_units; ?>"
                                                        data-image="<?php echo htmlspecialchars($item_image_src, ENT_QUOTES); ?>"
                                                        onclick="editItem(this)"
                                                    >Edit</button>

                                                    <form method="POST" class="d-inline" data-admin-confirm="<?php echo $item_status === 'discontinued' ? 'Restore this inventory item?' : 'Hide this inventory item from customer ordering?'; ?>">
                                                        <input type="hidden" name="inventory_id" value="<?php echo $inventory_id; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $item_status === 'discontinued' ? 'available' : 'discontinued'; ?>">
                                                        <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $item_status === 'discontinued' ? 'btn-success' : 'btn-warning'; ?>">
                                                            <?php echo $item_status === 'discontinued' ? 'Restore' : 'Hide'; ?>
                                                        </button>
                                                    </form>

                                                    <form method="POST" class="d-inline" data-admin-confirm="Delete this item?">
                                                        <input type="hidden" name="inventory_id" value="<?php echo $inventory_id; ?>">
                                                        <button type="submit" name="delete_item" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="display-6 text-secondary mb-3"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
                                <h3 class="h5 fw-bold">No items in inventory</h3>
                                <p class="text-secondary mb-0">Add your first item above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h3 class="modal-title h5" id="editModalLabel">Edit Item</h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="inventory_id" id="editInventoryId">
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="editItemName" disabled>
                            <div class="form-text">Item name cannot be changed.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Image</label>
                            <div>
                                <img id="editImagePreview" src="../../image.gif/water.png" alt="Current item image" width="96" height="96" class="rounded border object-fit-cover bg-light">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Current Stock (gallons)</label>
                                <input type="number" class="form-control" name="new_stock" id="editStock" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unit Price (&#8369;)</label>
                                <input type="number" class="form-control" name="unit_price" id="editPrice" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" class="form-control" name="reorder_level" id="editReorder" min="1" required>
                            <div class="form-text">Alert when stock falls below this level.</div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Capacity Units</label>
                            <input type="number" class="form-control" name="capacity_units" id="editCapacityUnits" min="1" max="16" required>
                            <div class="form-text">Used later for delivery batching. Slim = 1, round = 2.</div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Replace Item Image</label>
                            <input type="file" class="form-control" name="item_image" accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">Leave blank to keep the current image.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_stock" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
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
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));

        function editItem(button) {
            document.getElementById('editInventoryId').value = button.dataset.inventoryId;
            document.getElementById('editItemName').value = button.dataset.name;
            document.getElementById('editStock').value = button.dataset.stock;
            document.getElementById('editPrice').value = button.dataset.price;
            document.getElementById('editReorder').value = button.dataset.reorder;
            document.getElementById('editCapacityUnits').value = button.dataset.capacity;
            document.getElementById('editImagePreview').src = button.dataset.image;
            editModal.show();
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
</body>
</html>
