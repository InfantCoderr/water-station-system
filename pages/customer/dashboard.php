<?php
session_start();
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_logic.php';

require_active_session($conn, ['customer'], '../../index.php');

$customer_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get customer info
$customer = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$customer->bind_param("i", $customer_id);
$customer->execute();
$customer_info = $customer->get_result()->fetch_assoc();

function ensure_customer_delivery_addresses_table($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS customer_delivery_addresses (
            address_id INT(11) NOT NULL AUTO_INCREMENT,
            customer_id INT(11) NOT NULL,
            label VARCHAR(80) NOT NULL DEFAULT 'Delivery Address',
            address TEXT NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (address_id),
            KEY idx_customer_delivery_addresses_customer (customer_id),
            CONSTRAINT customer_delivery_addresses_ibfk_1 FOREIGN KEY (customer_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function seed_main_delivery_address($conn, $customer_id, $address) {
    if (trim((string) $address) === '') {
        return;
    }

    $count = $conn->prepare("SELECT COUNT(*) as count FROM customer_delivery_addresses WHERE customer_id = ?");
    $count->bind_param("i", $customer_id);
    $count->execute();
    $has_addresses = (int) $count->get_result()->fetch_assoc()['count'] > 0;
    $count->close();

    if ($has_addresses) {
        return;
    }

    $label = 'Main Delivery Address';
    $is_default = 1;
    $stmt = $conn->prepare("INSERT INTO customer_delivery_addresses (customer_id, label, address, is_default) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $customer_id, $label, $address, $is_default);
    $stmt->execute();
    $stmt->close();
}

function fetch_customer_delivery_addresses($conn, $customer_id) {
    $stmt = $conn->prepare("SELECT * FROM customer_delivery_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at ASC, address_id ASC");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $addresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $addresses;
}

function find_customer_delivery_address($conn, $customer_id, $address_id) {
    $stmt = $conn->prepare("SELECT * FROM customer_delivery_addresses WHERE address_id = ? AND customer_id = ? LIMIT 1");
    $stmt->bind_param("ii", $address_id, $customer_id);
    $stmt->execute();
    $address = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $address ?: null;
}

ensure_customer_delivery_addresses_table($conn);
seed_main_delivery_address($conn, $customer_id, $customer_info['address'] ?? '');
$delivery_addresses = fetch_customer_delivery_addresses($conn, $customer_id);

// Get loyalty info
ensure_loyalty_record($conn, $customer_id);
$loyalty = $conn->prepare("SELECT * FROM loyalty WHERE customer_id = ?");
$loyalty->bind_param("i", $customer_id);
$loyalty->execute();
$loyalty_info = $loyalty->get_result()->fetch_assoc();

// Get available inventory
$inventory_result = $conn->query("SELECT * FROM inventory WHERE status != 'discontinued' AND stock_quantity > 0 ORDER BY item_name ASC");
$inventory_items = $inventory_result ? $inventory_result->fetch_all(MYSQLI_ASSOC) : [];
$inventory_lookup = [];
foreach ($inventory_items as $inventory_item) {
    $inventory_lookup[(int) $inventory_item['inventory_id']] = $inventory_item;
}

$selected_inventory_id = (int) ($_POST['inventory_id'] ?? 0);
$selected_quantity = max(1, (int) ($_POST['quantity'] ?? 1));
$selected_item_name = '';
if ($selected_inventory_id > 0 && isset($inventory_lookup[$selected_inventory_id])) {
    $selected_item_name = $inventory_lookup[$selected_inventory_id]['item_name'];
}

function get_inventory_preview_image($item_name) {
    $normalized_name = strtolower($item_name);

    if (strpos($normalized_name, 'slim') !== false) {
        return '../../image.gif/slim.png';
    }

    if (strpos($normalized_name, 'round') !== false || strpos($normalized_name, 'jug') !== false || strpos($normalized_name, 'gallon') !== false) {
        return '../../image.gif/water%20jug.png';
    }

    return '../../image.gif/water.png';
}

function customer_status_badge_class($status) {
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

function customer_preview_text($text, $limit = 54) {
    $text = trim((string) $text);
    if (strlen($text) <= $limit) {
        return $text;
    }
    return substr($text, 0, $limit) . '...';
}

// Handle new order
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $inventory_id = (int) ($_POST['inventory_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $delivery_address_choice = $_POST['delivery_address_choice'] ?? '';
    $new_delivery_label = trim($_POST['new_delivery_label'] ?? '');
    $new_delivery_address = trim($_POST['new_delivery_address'] ?? '');
    $delivery_address = '';
    $delivery_date = $_POST['delivery_date'] ?? '';
    $contact_number = trim($_POST['contact_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($delivery_address_choice === 'new') {
        $delivery_address = $new_delivery_address;
    } elseif ((int) $delivery_address_choice > 0) {
        $saved_address = find_customer_delivery_address($conn, $customer_id, (int) $delivery_address_choice);
        if ($saved_address) {
            $delivery_address = $saved_address['address'];
        }
    }

    if ($inventory_id < 1 || $quantity < 1 || empty($delivery_address) || empty($delivery_date) || empty($contact_number)) {
        $error = "Please fill in all required fields.";
    } elseif ($delivery_date < date('Y-m-d')) {
        $error = "Delivery date cannot be in the past.";
    } else {
        $conn->begin_transaction();
        try {
            if ($delivery_address_choice === 'new') {
                $address_label = $new_delivery_label !== '' ? $new_delivery_label : 'Delivery Address';
                $is_default = empty($delivery_addresses) ? 1 : 0;
                $address_stmt = $conn->prepare("INSERT INTO customer_delivery_addresses (customer_id, label, address, is_default) VALUES (?, ?, ?, ?)");
                $address_stmt->bind_param("issi", $customer_id, $address_label, $delivery_address, $is_default);
                if (!$address_stmt->execute()) {
                    throw new Exception("Failed to save delivery address.");
                }
                $address_stmt->close();
            }

            $item_data = reserve_inventory_stock($conn, $inventory_id, $quantity);
            $unit_price = (float) $item_data['unit_price'];
            $total_amount = $unit_price * $quantity;

            $order = $conn->prepare("INSERT INTO orders (customer_id, delivery_date, delivery_address, contact_number, total_amount, order_status, payment_status, notes) VALUES (?, ?, ?, ?, ?, 'pending', 'pending', ?)");
            $order->bind_param("isssds", $customer_id, $delivery_date, $delivery_address, $contact_number, $total_amount, $notes);
            if (!$order->execute()) {
                throw new Exception("Failed to place order. Please try again.");
            }
            $order_id = $conn->insert_id;
            $order->close();

            $item = $conn->prepare("INSERT INTO order_items (order_id, inventory_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            $item->bind_param("iiid", $order_id, $inventory_id, $quantity, $unit_price);
            if (!$item->execute()) {
                throw new Exception("Failed to save order items.");
            }
            $item->close();

            $assigned_staff = auto_assign_order_to_staff($conn, $order_id);
            $conn->commit();

            if ($assigned_staff) {
                header("Location: dashboard.php?ordered=1&order_id=$order_id&assigned=1");
            } else {
                header("Location: dashboard.php?ordered=1&order_id=$order_id&no_staff=1");
            }
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Handle cancel order - REVAMPED LOYALTY RULES
if (isset($_POST['cancel_order'])) {
    $order_id = (int) ($_POST['order_id'] ?? 0);

    $conn->begin_transaction();
    try {
        $check = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ? AND customer_id = ? FOR UPDATE");
        $check->bind_param("ii", $order_id, $customer_id);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$result) {
            throw new Exception("Order not found.");
        }

        $had_penalty = in_array($result['order_status'], ['confirmed', 'processing', 'out_for_delivery'], true);
        transition_order_status($conn, $order_id, 'cancelled');
        $conn->commit();

        header("Location: dashboard.php?cancelled=1" . ($had_penalty ? '&penalty=1' : ''));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}


// Check for success messages
if (isset($_GET['ordered']) && isset($_GET['order_id'])) {
    if (isset($_GET['assigned'])) {
        $success = "Order placed successfully! Order #" . $_GET['order_id'] . " has been assigned to a staff member and will be delivered soon.";
    } elseif (isset($_GET['no_staff'])) {
        $success = "Order placed successfully! Order #" . $_GET['order_id'] . " is pending - waiting for admin to assign staff.";
    } else {
        $success = "Order placed successfully! Order ID: #" . $_GET['order_id'];
    }
} elseif (isset($_GET['cancelled'])) {
    if (isset($_GET['penalty'])) {
        // Use alert for penalty - more attention-grabbing
        echo "<script>alert('ORDER CANCELLED\\n\\nYour consecutive orders have been reset to 0.\\nYour loyalty progress was lost.');</script>";
        $success = ""; // Clear so modal doesn't show
    } else {
        $success = "Order cancelled successfully. No penalty since order was still pending.";
    }
}

// Get customer's recent orders
$orders = $conn->prepare("SELECT o.*, i.item_name FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN inventory i ON oi.inventory_id = i.inventory_id WHERE o.customer_id = ? ORDER BY o.order_date DESC LIMIT 5");
$orders->bind_param("i", $customer_id);
$orders->execute();
$recent_orders = $orders->get_result();

// Get free gallon redemptions
$free_gallons = $conn->prepare("SELECT COUNT(*) as count FROM free_gallon_redemptions WHERE customer_id = ? AND status = 'active'");
$free_gallons->bind_param("i", $customer_id);
$free_gallons->execute();
$available_free = $free_gallons->get_result()->fetch_assoc()['count'];

$consecutive = (int) ($loyalty_info['consecutive_orders'] ?? 0);
$progress = ($consecutive % 5) * 20;
$orders_needed = 5 - ($consecutive % 5);
if ($orders_needed === 5 && $consecutive > 0) {
    $orders_needed = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style/customer/dashboard.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg bg-primary navbar-dark shadow-sm customer-topbar">
        <div class="container d-flex flex-column flex-lg-row align-items-lg-center">
            <a href="dashboard.php" class="navbar-brand fw-bold">ISRAPHIL</a>
            <div class="ms-lg-auto d-flex flex-wrap align-items-center justify-content-center justify-content-lg-end gap-2 gap-sm-3 text-white customer-topbar-actions">
                <a href="../../logout.php" class="btn btn-sm rounded-pill customer-logout-btn">Logout</a>
                <a href="profile.php" class="btn btn-light btn-sm rounded-circle customer-profile-link" aria-label="Open profile" title="My Profile">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-person-circle" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
                        <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1"/>
                    </svg>
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
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

        <div class="row g-4 align-items-start mb-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100 customer-dashboard-card">
                    <div class="card-body p-4">
                        <span class="badge text-bg-primary mb-3">Customer Dashboard</span>
                        <h1 class="h2 fw-bold mb-2">Welcome back, <?php echo htmlspecialchars($customer_info['full_name']); ?>!</h1>
                        <p class="text-secondary mb-0">Manage your deliveries, place a new order, and keep track of your loyalty progress in one place.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm customer-dashboard-card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge text-bg-info mb-2">Loyalty Program</span>
                                <h2 class="h5 fw-bold mb-0">Your rewards progress</h2>
                            </div>
                            <span class="badge text-bg-success"><?php echo $available_free; ?> free</span>
                        </div>
                        <div class="row g-3 mb-3 text-center">
                            <div class="col-4">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fs-4 fw-bold"><?php echo $consecutive; ?></div>
                                    <div class="small text-secondary">Consecutive</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fs-4 fw-bold"><?php echo (int) ($loyalty_info['total_orders'] ?? 0); ?></div>
                                    <div class="small text-secondary">Total Orders</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fs-4 fw-bold"><?php echo $available_free; ?></div>
                                    <div class="small text-secondary">Free Gallons</div>
                                </div>
                            </div>
                        </div>
                        <div class="progress mb-3" role="progressbar" aria-label="Loyalty progress" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: <?php echo $progress; ?>%"><?php echo $consecutive % 5; ?>/5</div>
                        </div>
                        <p class="small text-secondary mb-1">
                            <?php if ($orders_needed > 0): ?>
                                Order <?php echo $orders_needed; ?> more time(s) to earn a free gallon.
                            <?php else: ?>
                                You earned a free gallon. Your next reward starts now.
                            <?php endif; ?>
                        </p>
                        <p class="small text-secondary mb-0">Cancelled orders after confirmation reset your streak.</p>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-pills gap-2 mb-4">
            <li class="nav-item"><a href="dashboard.php" class="nav-link active">Place Order</a></li>
            <li class="nav-item"><a href="history.php" class="nav-link">Order History</a></li>
        </ul>

        <section class="card border-0 shadow-sm mb-4 customer-section-card">
            <div class="card-body p-4">
                <div class="mb-4">
                    <span class="badge text-bg-primary mb-3">Place Order</span>
                    <h2 class="h4 fw-bold mb-2">Choose a water container and complete your order</h2>
                    <p class="text-secondary mb-0">Pick an available item first, then confirm the quantity, delivery details, and notes below.</p>
                </div>

                <?php if (!empty($inventory_items)): ?>
                <div class="mb-3">
                    <h3 class="h5 fw-bold mb-1">Available Water Containers</h3>
                    <p class="small text-secondary mb-0">Use the arrows to browse, then select a container.</p>
                </div>

                <div class="d-flex align-items-center gap-2 gap-sm-3 mb-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle flex-shrink-0 product-browse-button" data-product-prev aria-label="Previous product" onclick="moveProductWindow(-1)">&lsaquo;</button>

                    <div class="flex-grow-1 overflow-hidden">
                        <div class="row row-cols-1 row-cols-sm-2 g-2 g-sm-3" id="product_window">
                        <?php $loop_index = 0; ?>
                        <?php foreach ($inventory_items as $item): ?>
                        <?php
                            $item_id = (int) $item['inventory_id'];
                            $item_name = $item['item_name'];
                            $item_price = (float) $item['unit_price'];
                            $item_stock = (int) $item['stock_quantity'];
                            $item_image = get_inventory_preview_image($item_name);
                            $is_selected = $selected_inventory_id === $item_id;
                        ?>
                        <div class="col" data-item-slide data-item-index="<?php echo $loop_index ?? 0; ?>">
                            <article
                                class="card h-100 border-2 <?php echo $is_selected ? 'border-primary shadow' : 'border-light-subtle'; ?>"
                                data-item-card
                                data-inventory-id="<?php echo $item_id; ?>"
                                data-item-name="<?php echo htmlspecialchars($item_name); ?>"
                                data-item-price="<?php echo $item_price; ?>"
                            >
                                <div class="card-body d-flex flex-column p-2 p-sm-3 text-center">
                                    <div class="ratio ratio-1x1 bg-light rounded mb-2">
                                        <img src="<?php echo htmlspecialchars($item_image); ?>" alt="<?php echo htmlspecialchars($item_name); ?>" class="w-100 h-100 object-fit-contain p-2">
                                    </div>
                                    <h3 class="small fw-bold mb-1"><?php echo htmlspecialchars($item_name); ?></h3>
                                    <p class="small text-secondary mb-1">PHP <?php echo number_format($item_price, 2); ?></p>
                                    <p class="small text-secondary mb-2"><?php echo $item_stock; ?> available</p>
                                    <button
                                        type="button"
                                        class="btn btn-sm w-100 <?php echo $is_selected ? 'btn-primary' : 'btn-outline-primary'; ?> mt-auto"
                                        data-item-button
                                        aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
                                        onclick="selectInventoryById(<?php echo $item_id; ?>)"
                                    >
                                        <?php echo $is_selected ? 'Selected' : 'Select'; ?>
                                    </button>
                                </div>
                            </article>
                        </div>
                        <?php $loop_index = ($loop_index ?? 0) + 1; ?>
                        <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle flex-shrink-0 product-browse-button" data-product-next aria-label="Next product" onclick="moveProductWindow(1)">&rsaquo;</button>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="inventory_id" id="inventory_id" value="<?php echo $selected_inventory_id > 0 ? $selected_inventory_id : ''; ?>">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="rounded-4 border bg-light-subtle p-3 p-md-4 h-100">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label for="selected_item_name" class="form-label">Selected Water Type</label>
                                        <input
                                            type="text"
                                            id="selected_item_name"
                                            class="form-control form-control-lg"
                                            value="<?php echo htmlspecialchars($selected_item_name); ?>"
                                            placeholder="Select a round or slim jug above"
                                            readonly
                                        >
                                    </div>
                                    <div class="col-md-4">
                                        <label for="quantity" class="form-label">Quantity</label>
                                        <input type="number" class="form-control form-control-lg" name="quantity" id="quantity" min="1" max="10" value="<?php echo $selected_quantity; ?>" required onchange="updatePrice()">
                                    </div>
                                    <div class="col-12">
                                        <label for="delivery_address_choice" class="form-label">Delivery Address</label>
                                        <select id="delivery_address_choice" class="form-select" name="delivery_address_choice" required onchange="toggleNewDeliveryAddress(); updateSelectedAddressPreview();">
                                            <option value="">Choose delivery address</option>
                                            <?php foreach ($delivery_addresses as $address): ?>
                                            <?php
                                                $address_id = (int) $address['address_id'];
                                                $selected_address_choice = $_POST['delivery_address_choice'] ?? '';
                                                $is_selected_address = (string) $address_id === (string) $selected_address_choice || ($selected_address_choice === '' && (int) $address['is_default'] === 1);
                                            ?>
                                            <option value="<?php echo $address_id; ?>" data-address="<?php echo htmlspecialchars($address['address']); ?>" <?php echo $is_selected_address ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($address['label']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                            <option value="new" <?php echo ($_POST['delivery_address_choice'] ?? '') === 'new' ? 'selected' : ''; ?>>Add new delivery address</option>
                                        </select>
                                        <div id="selected_address_preview" class="small text-secondary border rounded-3 bg-white p-2 mt-2"></div>
                                    </div>
                                    <div class="col-12" id="new_delivery_address_fields">
                                        <div class="rounded-4 border bg-white p-3">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label for="new_delivery_label" class="form-label">Address Label</label>
                                                    <input id="new_delivery_label" class="form-control" type="text" name="new_delivery_label" value="<?php echo htmlspecialchars($_POST['new_delivery_label'] ?? ''); ?>" placeholder="Home, Office">
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="new_delivery_address" class="form-label">New Delivery Address</label>
                                                    <input id="new_delivery_address" class="form-control" type="text" name="new_delivery_address" value="<?php echo htmlspecialchars($_POST['new_delivery_address'] ?? ''); ?>" placeholder="Street, barangay, city">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_date" class="form-label">Preferred Delivery Date</label>
                                        <input id="delivery_date" class="form-control form-control-lg" type="date" name="delivery_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['delivery_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contact_number" class="form-label">Contact Number</label>
                                        <input id="contact_number" class="form-control form-control-lg" type="text" name="contact_number" value="<?php echo htmlspecialchars($_POST['contact_number'] ?? $customer_info['phone']); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="notes" class="form-label">Delivery Notes</label>
                                        <textarea id="notes" class="form-control" name="notes" rows="4" placeholder="e.g., Ring bell twice, leave at gate"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="rounded-4 border bg-white p-3 p-md-4 h-100">
                                <span class="badge text-bg-info mb-3">Order Summary</span>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-secondary">Estimated Value</span>
                                    <strong class="fs-4">PHP <span id="total_price">0.00</span></strong>
                                </div>
                                <p class="small text-secondary mb-4">Review your selected item and make sure your delivery details are correct before placing the order.</p>
                                <div class="d-grid">
                                    <button type="submit" name="place_order" class="btn btn-primary btn-lg">Place Order</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center py-5">
                    <span class="badge text-bg-secondary mb-3">Inventory Update</span>
                    <h3 class="h5 fw-bold">No water containers available</h3>
                    <p class="text-secondary mb-0">Please check back later or contact the station administrator.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card border-0 shadow-sm customer-section-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <span class="badge text-bg-light text-primary border mb-2">Recent Orders</span>
                        <h2 class="h4 fw-bold mb-1">Your latest activity</h2>
                        <p class="text-secondary mb-0">Track the status of your most recent orders and cancel pending requests if needed.</p>
                    </div>
                    <a href="history.php" class="btn btn-outline-primary">View Full Order History</a>
                </div>

                <?php if ($recent_orders->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0 customer-order-table">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Item</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-semibold">#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['item_name']); ?></td>
                                <td>PHP <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="badge <?php echo customer_status_badge_class($order['order_status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                                        </span>
                                        <?php if (in_array($order['order_status'], ['pending', 'confirmed', 'processing'])): ?>
                                        <form method="POST" onsubmit="return confirmCancel('<?php echo $order['order_status']; ?>')">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <button type="submit" name="cancel_order" class="btn btn-outline-danger btn-sm">Cancel</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <span class="badge text-bg-secondary mb-3">No Orders Yet</span>
                    <h3 class="h5 fw-bold">Place your first order</h3>
                    <p class="text-secondary mb-0">Choose a water container above and your first order will appear here.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let productWindowStart = 0;

        function getVisibleProductCount() {
            return window.matchMedia('(max-width: 575.98px)').matches ? 1 : 2;
        }

        function renderProductWindow() {
            const slides = Array.from(document.querySelectorAll('[data-item-slide]'));
            const previousButton = document.querySelector('[data-product-prev]');
            const nextButton = document.querySelector('[data-product-next]');
            const visibleCount = getVisibleProductCount();

            if (slides.length === 0) {
                return;
            }

            if (slides.length <= visibleCount) {
                productWindowStart = 0;
                previousButton?.classList.add('disabled');
                nextButton?.classList.add('disabled');
            } else {
                previousButton?.classList.remove('disabled');
                nextButton?.classList.remove('disabled');
            }

            slides.forEach((slide, index) => {
                const normalizedIndex = (index - productWindowStart + slides.length) % slides.length;
                slide.classList.toggle('d-none', normalizedIndex >= visibleCount);
            });
        }

        function moveProductWindow(direction) {
            const slides = document.querySelectorAll('[data-item-slide]');
            const visibleCount = getVisibleProductCount();

            if (slides.length <= visibleCount) {
                return;
            }

            productWindowStart = (productWindowStart + direction + slides.length) % slides.length;
            renderProductWindow();
        }

        function updatePrice() {
            const quantityInput = document.getElementById('quantity');
            const inventoryInput = document.getElementById('inventory_id');
            const totalPrice = document.getElementById('total_price');

            if (!quantityInput || !inventoryInput || !totalPrice) {
                return;
            }

            const quantity = Number(quantityInput.value || 0);
            const inventoryId = inventoryInput.value;
            const selectedCard = document.querySelector(`[data-item-card][data-inventory-id="${inventoryId}"]`);
            const price = Number(selectedCard?.dataset.itemPrice || 0);
            totalPrice.textContent = (price * quantity).toFixed(2);
        }

        function selectInventoryById(inventoryId) {
            const cards = document.querySelectorAll('[data-item-card]');
            const selectedCard = document.querySelector(`[data-item-card][data-inventory-id="${inventoryId}"]`);
            const hiddenInput = document.getElementById('inventory_id');
            const selectedNameInput = document.getElementById('selected_item_name');

            if (!selectedCard || !hiddenInput || !selectedNameInput) {
                return;
            }

            cards.forEach(card => {
                const button = card.querySelector('[data-item-button]');
                card.classList.remove('border-primary', 'shadow');
                card.classList.add('border-light-subtle');
                if (button) {
                    button.textContent = 'Select';
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-outline-primary');
                    button.setAttribute('aria-pressed', 'false');
                }
            });

            selectedCard.classList.remove('border-light-subtle');
            selectedCard.classList.add('border-primary', 'shadow');
            hiddenInput.value = inventoryId;
            selectedNameInput.value = selectedCard.dataset.itemName || '';

            const selectedButton = selectedCard.querySelector('[data-item-button]');
            if (selectedButton) {
                selectedButton.textContent = 'Selected';
                selectedButton.classList.remove('btn-outline-primary');
                selectedButton.classList.add('btn-primary');
                selectedButton.setAttribute('aria-pressed', 'true');
            }

            updatePrice();
        }

        function showSelectedProductInWindow() {
            const inventoryInput = document.getElementById('inventory_id');
            const slides = Array.from(document.querySelectorAll('[data-item-slide]'));

            if (!inventoryInput?.value || slides.length === 0) {
                renderProductWindow();
                return;
            }

            const selectedSlide = slides.find(slide => {
                const card = slide.querySelector('[data-item-card]');
                return card?.dataset.inventoryId === inventoryInput.value;
            });

            if (selectedSlide) {
                productWindowStart = slides.indexOf(selectedSlide);
            }

            renderProductWindow();
        }

        function toggleNewDeliveryAddress() {
            const addressChoice = document.getElementById('delivery_address_choice');
            const newAddressFields = document.getElementById('new_delivery_address_fields');
            const newAddressInput = document.getElementById('new_delivery_address');

            if (!addressChoice || !newAddressFields || !newAddressInput) {
                return;
            }

            const isNewAddress = addressChoice.value === 'new';
            newAddressFields.classList.toggle('d-none', !isNewAddress);
            newAddressInput.required = isNewAddress;
        }

        function updateSelectedAddressPreview() {
            const addressChoice = document.getElementById('delivery_address_choice');
            const preview = document.getElementById('selected_address_preview');

            if (!addressChoice || !preview) {
                return;
            }

            const selectedOption = addressChoice.options[addressChoice.selectedIndex];
            const address = selectedOption?.dataset.address || '';
            preview.textContent = address;
            preview.classList.toggle('d-none', address === '');
        }

        function confirmCancel(status) {
            if (status === 'pending') {
                return confirm('Cancel this order?\n\nNo penalty. The order is still pending.');
            }

            return confirm('Warning: this order is already confirmed and being prepared for delivery.\n\nCancelling now will reset your loyalty progress to 0.\nYou will lose your current consecutive-order streak.\n\nDo you want to continue?');
        }

        window.addEventListener('resize', renderProductWindow);
        showSelectedProductInWindow();
        toggleNewDeliveryAddress();
        updateSelectedAddressPreview();
        updatePrice();
    </script>
</body>
</html>
