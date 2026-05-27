<?php
session_start();
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_logic.php';
require_once __DIR__ . '/../../includes/address_helpers.php';
require_once __DIR__ . '/../../includes/delivery_batch_helpers.php';
require_once __DIR__ . '/../../includes/customer_navbar.php';

require_active_session($conn, ['customer'], '../../index.php');

ensure_delivery_cancelled_status($conn);

$customer_id = $_SESSION['user_id'];
$error = '';
$success = '';
$result_modal_title = '';
$result_modal_message = '';
$result_modal_variant = 'success';
$delivery_timezone = new DateTimeZone('Asia/Manila');
$minimum_delivery_date = new DateTimeImmutable('today', $delivery_timezone);
$minimum_delivery_date_value = $minimum_delivery_date->format('Y-m-d');

if (empty($_SESSION['order_form_token'])) {
    $_SESSION['order_form_token'] = bin2hex(random_bytes(32));
}

// Get customer info
$customer = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$customer->bind_param("i", $customer_id);
$customer->execute();
$customer_info = $customer->get_result()->fetch_assoc();

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

ensure_delivery_service_area_schema($conn);
ensure_column_exists($conn, 'orders', 'payment_method', "`payment_method` VARCHAR(30) NOT NULL DEFAULT 'cod' AFTER `payment_status`");
seed_main_delivery_address($conn, $customer_id, $customer_info['address'] ?? '');
$delivery_addresses = fetch_customer_delivery_addresses($conn, $customer_id);
$service_areas = fetch_delivery_service_areas($conn);
$delivery_area_json = delivery_area_index_json($service_areas);
ensure_free_gallon_redemption_usage_column($conn);

// Get loyalty info
ensure_loyalty_record($conn, $customer_id);
$loyalty = $conn->prepare("SELECT * FROM loyalty WHERE customer_id = ?");
$loyalty->bind_param("i", $customer_id);
$loyalty->execute();
$loyalty_info = $loyalty->get_result()->fetch_assoc();
$available_free = fetch_available_free_gallon_count($conn, $customer_id);
$requested_free_gallons = max(0, (int) ($_POST['redeem_free_gallons'] ?? 0));
if ($requested_free_gallons < 1 && isset($_POST['redeem_free_gallon']) && (int) $_POST['redeem_free_gallon'] === 1) {
    $requested_free_gallons = 1;
}
$selected_free_gallons = min($requested_free_gallons, $available_free);

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
$selected_item_max_quantity = max_customer_order_units();
$selected_paid_quantity_limit = $selected_item_max_quantity;
$selected_free_gallon_limit = min($available_free, max(0, $selected_item_max_quantity - 1));
if ($selected_inventory_id > 0 && isset($inventory_lookup[$selected_inventory_id])) {
    $selected_item = $inventory_lookup[$selected_inventory_id];
    $selected_item_name = $selected_item['item_name'];
    $selected_item_max_quantity = max_order_quantity_for_item($selected_item);
    $selected_free_gallon_limit = item_allows_free_gallon_redemption($selected_item) ? min($available_free, max(0, $selected_item_max_quantity - 1)) : 0;
    $selected_free_gallons = min($selected_free_gallons, $selected_free_gallon_limit);
    $selected_paid_quantity_limit = max(0, $selected_item_max_quantity - $selected_free_gallons);
    $selected_quantity = min($selected_quantity, max(1, $selected_paid_quantity_limit));
}

function get_inventory_preview_image($item) {
    $item_image = is_array($item) ? trim((string) ($item['item_image'] ?? '')) : '';
    if ($item_image !== '') {
        return '../../' . ltrim($item_image, '/');
    }

    $item_name = is_array($item) ? (string) ($item['item_name'] ?? '') : (string) $item;
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

function parse_customer_delivery_date($value, DateTimeZone $timezone) {
    $value = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    $has_errors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

    if (!$date || $has_errors || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date;
}

// Handle new order
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $submitted_order_token = (string) ($_POST['order_form_token'] ?? '');
    $active_order_token = (string) ($_SESSION['order_form_token'] ?? '');
    $inventory_id = (int) ($_POST['inventory_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $redeem_free_gallons = max(0, (int) ($_POST['redeem_free_gallons'] ?? 0));
    if ($redeem_free_gallons < 1 && isset($_POST['redeem_free_gallon']) && (int) ($_POST['redeem_free_gallon'] ?? 0) === 1) {
        $redeem_free_gallons = 1;
    }
    $redeemed_free_gallons = 0;
    $delivery_address_choice = $_POST['delivery_address_choice'] ?? '';
    $new_delivery_label = trim($_POST['new_delivery_label'] ?? '');
    $new_delivery_parts = delivery_address_from_post('new_delivery_');
    $delivery_address = '';
    $delivery_street = '';
    $delivery_barangay = '';
    $delivery_city = '';
    $delivery_province = '';
    $service_area_id = 0;
    $saved_address_needs_update = false;
    $delivery_date = $_POST['delivery_date'] ?? '';
    $parsed_delivery_date = parse_customer_delivery_date($delivery_date, $delivery_timezone);
    $contact_number = trim($_POST['contact_number'] ?? '');
    $payment_method = trim((string) ($_POST['payment_method'] ?? 'cod'));
    $notes = trim($_POST['notes'] ?? '');
    $selected_item_for_order = $inventory_lookup[$inventory_id] ?? null;

    if ($submitted_order_token === '' || $active_order_token === '' || !hash_equals($active_order_token, $submitted_order_token)) {
        $error = "This order was already submitted or the form expired. Please review your order and try again.";
        $_SESSION['order_form_token'] = bin2hex(random_bytes(32));
    } elseif ($delivery_address_choice === 'new') {
        $delivery_address = format_delivery_address(
            $new_delivery_parts['street_address'],
            $new_delivery_parts['barangay'],
            $new_delivery_parts['city'],
            $new_delivery_parts['province']
        );
        $delivery_street = $new_delivery_parts['street_address'];
        $delivery_barangay = $new_delivery_parts['barangay'];
        $delivery_city = $new_delivery_parts['city'];
        $delivery_province = $new_delivery_parts['province'];
    } elseif ((int) $delivery_address_choice > 0) {
        $saved_address = find_customer_delivery_address($conn, $customer_id, (int) $delivery_address_choice);
        if ($saved_address) {
            $delivery_address = $saved_address['address'];
            $delivery_street = (string) ($saved_address['street_address'] ?? '');
            $delivery_barangay = (string) ($saved_address['barangay'] ?? '');
            $delivery_city = (string) ($saved_address['city'] ?? '');
            $delivery_province = (string) ($saved_address['province'] ?? '');
            $service_area_id = (int) ($saved_address['service_area_id'] ?? 0);
            $saved_address_parts = [
                'street_address' => $delivery_street,
                'barangay' => $delivery_barangay,
                'city' => $delivery_city,
                'province' => $delivery_province,
            ];
            $saved_service_area = delivery_address_is_complete($saved_address_parts)
                ? find_delivery_service_area($conn, $delivery_province, $delivery_city, $delivery_barangay)
                : null;
            if (!$saved_service_area) {
                $saved_address_needs_update = true;
            } else {
                $service_area_id = (int) ($saved_service_area['area_id'] ?? $service_area_id);
            }
        }
    }

    if (empty($error) && ($inventory_id < 1 || $quantity < 1 || empty($delivery_address) || empty($delivery_date) || empty($contact_number))) {
        $error = "Please fill in all required fields.";
    } elseif (empty($error) && $payment_method !== 'cod') {
        $error = "Cash on delivery is the only available payment method right now.";
    } elseif (empty($error) && !$selected_item_for_order) {
        $error = "Please select a valid water type.";
    } elseif (empty($error) && $saved_address_needs_update) {
        $error = "Please update the selected delivery address in your profile so it includes a covered province, city, and barangay.";
    } elseif (empty($error) && $redeem_free_gallons > $available_free) {
        $error = "You only have {$available_free} free gallon(s) available to redeem.";
    } elseif (empty($error) && $delivery_address_choice === 'new' && !delivery_address_is_complete($new_delivery_parts)) {
        $error = "Please complete the street, province, city, and barangay for the new delivery address.";
    } elseif (empty($error) && $delivery_address_choice === 'new' && !($service_area = find_delivery_service_area($conn, $delivery_province, $delivery_city, $delivery_barangay))) {
        $error = "This delivery address is outside our current coverage. Please choose a listed Pangasinan province, city, and barangay combination.";
    } elseif (empty($error) && !$parsed_delivery_date) {
        $error = "Please choose a valid delivery date.";
    } elseif (empty($error) && $parsed_delivery_date < $minimum_delivery_date) {
        $error = "Delivery date cannot be in the past.";
    } elseif (empty($error)) {
        try {
            validate_customer_order_limit($selected_item_for_order, $quantity, $redeem_free_gallons);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if (empty($error)) {
        $_SESSION['order_form_token'] = bin2hex(random_bytes(32));
        $conn->begin_transaction();
        try {
            if ($delivery_address_choice === 'new') {
                $address_label = $new_delivery_label !== '' ? $new_delivery_label : 'Delivery Address';
                $is_default = empty($delivery_addresses) ? 1 : 0;
                $service_area_id = (int) ($service_area['area_id'] ?? 0);
                $address_stmt = $conn->prepare("INSERT INTO customer_delivery_addresses (customer_id, label, address, street_address, barangay, city, province, service_area_id, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $address_stmt->bind_param(
                    "issssssii",
                    $customer_id,
                    $address_label,
                    $delivery_address,
                    $delivery_street,
                    $delivery_barangay,
                    $delivery_city,
                    $delivery_province,
                    $service_area_id,
                    $is_default
                );
                if (!$address_stmt->execute()) {
                    throw new Exception("Failed to save delivery address.");
                }
                $address_stmt->close();
            }

            $redeemed_free_gallons = $redeem_free_gallons;
            $reserved_quantity = $quantity + $redeemed_free_gallons;
            $item_data = reserve_inventory_stock($conn, $inventory_id, $reserved_quantity);
            $unit_price = (float) $item_data['unit_price'];
            $total_amount = $unit_price * $quantity;

            $order = $conn->prepare("INSERT INTO orders (customer_id, delivery_date, delivery_address, delivery_street, delivery_barangay, delivery_city, delivery_province, contact_number, total_amount, order_status, payment_status, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?)");
            $order->bind_param("isssssssdss", $customer_id, $delivery_date, $delivery_address, $delivery_street, $delivery_barangay, $delivery_city, $delivery_province, $contact_number, $total_amount, $payment_method, $notes);
            if (!$order->execute()) {
                throw new Exception("Failed to place order. Please try again.");
            }
            $order_id = $conn->insert_id;
            $order->close();

            if ($redeemed_free_gallons > 0) {
                consume_free_gallon_redemption($conn, $customer_id, $order_id, $redeemed_free_gallons);
            }

            $item = $conn->prepare("INSERT INTO order_items (order_id, inventory_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            $item->bind_param("iiid", $order_id, $inventory_id, $reserved_quantity, $unit_price);
            if (!$item->execute()) {
                throw new Exception("Failed to save order items.");
            }
            $item->close();

            $conn->commit();

            header("Location: dashboard.php?ordered=1&order_id=$order_id" . ($redeemed_free_gallons > 0 ? '&reward=1' : ''));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
            $_SESSION['order_form_token'] = bin2hex(random_bytes(32));
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
        transition_order_status($conn, $order_id, 'cancelled', $customer_id, 'customer');
        $conn->commit();

        header("Location: dashboard.php?cancelled=1" . ($had_penalty ? '&penalty=1' : ''));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}


// Check for success messages
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ordered']) && isset($_GET['order_id'])) {
    $result_modal_title = "Order placed successfully";
    $result_modal_message = "Order #" . (int) $_GET['order_id'] . " is pending admin review and staff assignment.";
    if (isset($_GET['reward'])) {
        $result_modal_message .= " Your free gallon reward was added to this order.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['cancelled'])) {
    if (isset($_GET['penalty'])) {
        $result_modal_title = "Order cancelled";
        $result_modal_message = "Your consecutive-order streak has been reset because this order was already being prepared.";
        $result_modal_variant = 'warning';
    } else {
        $result_modal_title = "Order cancelled successfully";
        $result_modal_message = "No penalty was applied because the order was still pending.";
    }
}

// Get customer's recent orders
$orders = $conn->prepare("
    SELECT o.*, i.item_name, oi.quantity, COALESCE(fr.redeemed_free_gallons, 0) AS redeemed_free_gallons
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN inventory i ON oi.inventory_id = i.inventory_id
    LEFT JOIN (
        SELECT used_order_id, COUNT(*) AS redeemed_free_gallons
        FROM free_gallon_redemptions
        WHERE status = 'used' AND used_order_id IS NOT NULL
        GROUP BY used_order_id
    ) fr ON fr.used_order_id = o.order_id
    WHERE o.customer_id = ?
    ORDER BY o.order_date DESC
    LIMIT 5
");
$orders->bind_param("i", $customer_id);
$orders->execute();
$recent_orders = $orders->get_result();

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
    <link rel="stylesheet" href="../../style/customer/navbar.css">
    <link rel="stylesheet" href="../../style/system_skeleton.css?v=20260527d">
</head>
<body class="bg-light system-loading skeleton-customer skeleton-customer-dashboard">
    <?php render_customer_navbar('order'); ?>

    <main class="container py-4">
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
                            $item_capacity_units = max(1, (int) ($item['capacity_units'] ?? 1));
                            $item_order_limit = max_order_quantity_for_item($item);
                            $item_allows_free = item_allows_free_gallon_redemption($item);
                            $item_image = get_inventory_preview_image($item);
                            $is_selected = $selected_inventory_id === $item_id;
                        ?>
                        <div class="col" data-item-slide data-item-index="<?php echo $loop_index ?? 0; ?>">
                            <article
                                class="card h-100 border-2 <?php echo $is_selected ? 'border-primary shadow' : 'border-light-subtle'; ?>"
                                data-item-card
                                data-inventory-id="<?php echo $item_id; ?>"
                                data-item-name="<?php echo htmlspecialchars($item_name); ?>"
                                data-item-price="<?php echo $item_price; ?>"
                                data-item-stock="<?php echo $item_stock; ?>"
                                data-item-capacity-units="<?php echo $item_capacity_units; ?>"
                                data-item-order-limit="<?php echo $item_order_limit; ?>"
                                data-item-allows-free="<?php echo $item_allows_free ? '1' : '0'; ?>"
                            >
                                <div class="card-body d-flex flex-column p-2 p-sm-3 text-center">
                                    <div class="ratio ratio-1x1 bg-light rounded mb-2">
                                        <img src="<?php echo htmlspecialchars($item_image); ?>" alt="<?php echo htmlspecialchars($item_name); ?>" class="w-100 h-100 object-fit-contain p-2">
                                    </div>
                                    <h3 class="small fw-bold mb-1"><?php echo htmlspecialchars($item_name); ?></h3>
                                    <p class="small text-secondary mb-1">PHP <?php echo number_format($item_price, 2); ?></p>
                                    <p class="small text-secondary mb-2"><?php echo $item_stock; ?> available</p>
                                    <p class="small text-secondary mb-2">Order limit: <?php echo $item_order_limit; ?> per order</p>
                                    <?php if ($item_allows_free): ?>
                                    <p class="small text-success mb-2">Free gallon eligible</p>
                                    <?php endif; ?>
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

                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="order_form_token" value="<?php echo htmlspecialchars($_SESSION['order_form_token']); ?>">
                    <input type="hidden" name="place_order" value="1">
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
                                        <input type="number" class="form-control form-control-lg" name="quantity" id="quantity" min="1" max="<?php echo max(1, $selected_paid_quantity_limit); ?>" value="<?php echo $selected_quantity; ?>" required onblur="updatePrice()">
                                        <div id="quantity_limit_hint" class="form-text">
                                            This item allows up to <?php echo $selected_item_max_quantity; ?> gallon(s) per order based on unit size.
                                        </div>
                                    </div>
                                    <?php if ($available_free > 0): ?>
                                    <div class="col-12">
                                        <div class="rounded-4 border bg-white p-3">
                                            <div class="row g-3 align-items-end">
                                                <div class="col-md-5">
                                                    <label class="form-label fw-semibold" for="redeem_free_gallons">Free gallons to redeem</label>
                                                    <input
                                                        class="form-control"
                                                        type="number"
                                                        name="redeem_free_gallons"
                                                        id="redeem_free_gallons"
                                                        min="0"
                                                        max="<?php echo max(0, $selected_free_gallon_limit); ?>"
                                                        value="<?php echo max(0, $selected_free_gallons); ?>"
                                                        onblur="updatePrice()"
                                                    >
                                                </div>
                                                <div class="col-md-7">
                                                    <p class="small text-secondary mb-0">
                                                        You have <?php echo $available_free; ?> free gallon(s) available. Redeemed gallons are added to this delivery without increasing the amount due. Each redemption order still needs at least 1 paid slim gallon.
                                                    </p>
                                                </div>
                                            </div>
                                            <div id="free_gallon_item_hint" class="small text-secondary mt-2">
                                                Free gallon rewards are for slim containers only.
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
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
                                            <option
                                                value="<?php echo $address_id; ?>"
                                                data-address="<?php echo htmlspecialchars($address['address']); ?>"
                                                data-barangay="<?php echo htmlspecialchars($address['barangay'] ?? ''); ?>"
                                                data-city="<?php echo htmlspecialchars($address['city'] ?? ''); ?>"
                                                data-province="<?php echo htmlspecialchars($address['province'] ?? ''); ?>"
                                                <?php echo $is_selected_address ? 'selected' : ''; ?>
                                            >
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
                                                    <label for="new_delivery_street_address" class="form-label">Street / House Details</label>
                                                    <textarea id="new_delivery_street_address" class="form-control" name="new_delivery_street_address" rows="2" placeholder="House no., street, purok, landmark"><?php echo htmlspecialchars($_POST['new_delivery_street_address'] ?? ''); ?></textarea>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="new_delivery_province" class="form-label">Province</label>
                                                    <select id="new_delivery_province" class="form-select" name="new_delivery_province" data-current="<?php echo htmlspecialchars($_POST['new_delivery_province'] ?? ''); ?>">
                                                        <option value="">Choose province</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="new_delivery_city" class="form-label">City / Municipality</label>
                                                    <select id="new_delivery_city" class="form-select" name="new_delivery_city" data-current="<?php echo htmlspecialchars($_POST['new_delivery_city'] ?? ''); ?>">
                                                        <option value="">Choose city or municipality</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="new_delivery_barangay" class="form-label">Barangay</label>
                                                    <select id="new_delivery_barangay" class="form-select" name="new_delivery_barangay" data-current="<?php echo htmlspecialchars($_POST['new_delivery_barangay'] ?? ''); ?>">
                                                        <option value="">Choose barangay</option>
                                                    </select>
                                                    <div class="form-text">Coverage is checked by barangay before the order is saved.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_date" class="form-label">Preferred Delivery Date</label>
                                        <input id="delivery_date" class="form-control form-control-lg" type="date" name="delivery_date" min="<?php echo htmlspecialchars($minimum_delivery_date_value); ?>" value="<?php echo htmlspecialchars($_POST['delivery_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contact_number" class="form-label">Contact Number</label>
                                        <input id="contact_number" class="form-control form-control-lg" type="text" name="contact_number" value="<?php echo htmlspecialchars($_POST['contact_number'] ?? $customer_info['phone']); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Payment Method</label>
                                        <div class="rounded-4 border bg-white p-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" id="payment_method_cod" value="cod" checked required>
                                                <label class="form-check-label fw-semibold" for="payment_method_cod">
                                                    Cash on Delivery (COD)
                                                </label>
                                            </div>
                                            <div class="form-text">Payment is collected by the rider when your order is delivered.</div>
                                        </div>
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
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-secondary">Paid Gallons</span>
                                    <strong><span id="summary_paid_quantity"><?php echo $selected_quantity; ?></span></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-secondary">Free Gallons</span>
                                    <strong><span id="summary_free_quantity"><?php echo $selected_free_gallons; ?></span></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-secondary">Total Gallons Delivered</span>
                                    <strong><span id="summary_total_quantity"><?php echo $selected_quantity + $selected_free_gallons; ?></span></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-secondary">Amount Due</span>
                                    <strong class="fs-4">PHP <span id="total_price">0.00</span></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-secondary">Payment</span>
                                    <strong>COD</strong>
                                </div>
                                <p class="small text-secondary mb-4">Review your selected item and make sure your delivery details are correct before placing the order.</p>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg" data-place-order-button>Place Order</button>
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
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-semibold">#<?php echo $order['order_id']; ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($order['item_name']); ?></div>
                                    <div class="small text-secondary">
                                        <?php echo (int) ($order['quantity'] ?? 0); ?> gallon(s)
                                        <?php if ((int) ($order['redeemed_free_gallons'] ?? 0) > 0): ?>
                                            <span class="badge text-bg-success ms-1">+<?php echo (int) $order['redeemed_free_gallons']; ?> free</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>PHP <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <div class="fw-semibold">
                                        <?php echo strtoupper(str_replace('_', ' ', $order['payment_method'] ?? 'cod')); ?>
                                    </div>
                                    <div class="small text-secondary">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['payment_status'] ?? 'pending')); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="badge <?php echo customer_status_badge_class($order['order_status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                                        </span>
                                        <?php if (in_array($order['order_status'], ['pending', 'confirmed', 'processing'])): ?>
                                        <form method="POST" action="dashboard.php" class="cancel-order-form" data-order-id="<?php echo (int) $order['order_id']; ?>" data-order-status="<?php echo htmlspecialchars($order['order_status']); ?>">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
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

    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="dashboard.php" id="cancelOrderModalForm">
                    <input type="hidden" name="cancel_order" value="1">
                    <div class="modal-header">
                        <h2 class="modal-title h5 fw-bold" id="cancelOrderModalLabel">Cancel order</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="cancelOrderId">
                        <p class="mb-2" id="cancelOrderModalMessage">Cancel this order?</p>
                        <div class="alert alert-warning d-none mb-0" id="cancelOrderPenaltyWarning">
                            Cancelling this order now will reset your consecutive-order streak.
                        </div>
                        <div class="cancel-progress d-none text-center mt-3" id="cancelOrderProgress" role="status" aria-live="polite">
                            <div class="spinner-border text-danger mb-2" aria-hidden="true"></div>
                            <div class="small fw-semibold">Cancelling order...</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep Order</button>
                        <button type="submit" class="btn btn-danger" id="confirmCancelOrderButton">Cancel Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($result_modal_title !== ''): ?>
    <div class="modal fade" id="resultStatusModal" tabindex="-1" aria-labelledby="resultStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body text-center p-4">
                    <div class="result-status-icon result-status-<?php echo htmlspecialchars($result_modal_variant); ?> mx-auto mb-3">
                        <?php echo $result_modal_variant === 'warning' ? '!' : '&#10003;'; ?>
                    </div>
                    <h2 class="h5 fw-bold mb-2" id="resultStatusModalLabel"><?php echo htmlspecialchars($result_modal_title); ?></h2>
                    <p class="text-secondary mb-4"><?php echo htmlspecialchars($result_modal_message); ?></p>
                    <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const deliveryAreas = <?php echo $delivery_area_json ?: '{}'; ?>;
        let productWindowStart = 0;

        function fillSelect(select, values, placeholder, selectedValue = '') {
            if (!select) {
                return;
            }

            select.innerHTML = '';
            select.add(new Option(placeholder, ''));

            values.forEach(value => {
                const option = new Option(value, value);
                option.selected = value === selectedValue;
                select.add(option);
            });
        }

        function initializeAddressAreaSelects(prefix) {
            const provinceSelect = document.getElementById(`${prefix}province`);
            const citySelect = document.getElementById(`${prefix}city`);
            const barangaySelect = document.getElementById(`${prefix}barangay`);

            if (!provinceSelect || !citySelect || !barangaySelect) {
                return;
            }

            function refreshCities() {
                const province = provinceSelect.value || '';
                const selectedCity = citySelect.dataset.current || '';
                const cities = province && deliveryAreas[province] ? Object.keys(deliveryAreas[province]) : [];
                fillSelect(citySelect, cities, 'Choose city or municipality', selectedCity);
                citySelect.dataset.current = '';
                refreshBarangays();
            }

            function refreshBarangays() {
                const province = provinceSelect.value || '';
                const city = citySelect.value || '';
                const selectedBarangay = barangaySelect.dataset.current || '';
                const barangays = province && city && deliveryAreas[province]?.[city] ? deliveryAreas[province][city] : [];
                fillSelect(barangaySelect, barangays, 'Choose barangay', selectedBarangay);
                barangaySelect.dataset.current = '';
            }

            fillSelect(provinceSelect, Object.keys(deliveryAreas), 'Choose province', provinceSelect.dataset.current || '');
            refreshCities();
            provinceSelect.addEventListener('change', refreshCities);
            citySelect.addEventListener('change', refreshBarangays);
        }

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
            const redeemInput = document.getElementById('redeem_free_gallons');
            const paidQuantity = document.getElementById('summary_paid_quantity');
            const freeQuantity = document.getElementById('summary_free_quantity');
            const totalQuantity = document.getElementById('summary_total_quantity');
            const limitHint = document.getElementById('quantity_limit_hint');
            const freeItemHint = document.getElementById('free_gallon_item_hint');

            if (!quantityInput || !inventoryInput || !totalPrice) {
                return;
            }

            const inventoryId = inventoryInput.value;
            const selectedCard = document.querySelector(`[data-item-card][data-inventory-id="${inventoryId}"]`);
            const baseOrderLimit = Number(selectedCard?.dataset.itemOrderLimit || <?php echo max_customer_order_units(); ?>);
            const stockLimit = Number(selectedCard?.dataset.itemStock || baseOrderLimit);
            const selectedAllowsFree = selectedCard?.dataset.itemAllowsFree === '1';
            const effectiveOrderLimit = Math.max(1, Math.min(baseOrderLimit, stockLimit));
            const availableFreeGallons = <?php echo (int) $available_free; ?>;
            const freeLimit = selectedAllowsFree ? Math.max(0, Math.min(availableFreeGallons, effectiveOrderLimit - 1)) : 0;

            if (redeemInput) {
                const canRedeem = freeLimit > 0;
                redeemInput.disabled = !canRedeem;
                redeemInput.max = String(freeLimit);
                if (!canRedeem) {
                    redeemInput.value = '0';
                }
            }

            let redeemedFreeGallons = Number(redeemInput?.value || 0);
            if (redeemedFreeGallons < 0) {
                redeemedFreeGallons = 0;
            }
            if (redeemedFreeGallons > freeLimit) {
                redeemedFreeGallons = freeLimit;
                if (redeemInput) {
                    redeemInput.value = String(freeLimit);
                }
            }

            const paidLimit = Math.max(1, effectiveOrderLimit - redeemedFreeGallons);
            quantityInput.min = '1';
            quantityInput.max = paidLimit;

            let quantity = Number(quantityInput.value || 0);
            if (quantity < 1) {
                quantity = 1;
                quantityInput.value = '1';
            }
            if (quantity > paidLimit) {
                quantity = paidLimit;
                quantityInput.value = String(paidLimit);
            }

            const price = Number(selectedCard?.dataset.itemPrice || 0);

            if (limitHint) {
                if (redeemedFreeGallons > 0) {
                    limitHint.textContent = `This item allows up to ${effectiveOrderLimit} gallon(s) per order. With ${redeemedFreeGallons} free gallon(s) redeemed, you can pay for up to ${paidLimit} gallon(s).`;
                } else {
                    limitHint.textContent = `This item allows up to ${effectiveOrderLimit} gallon(s) per order based on unit size.`;
                }
            }

            if (freeItemHint) {
                freeItemHint.textContent = selectedAllowsFree
                    ? `Free gallon rewards are for slim containers only. You can redeem up to ${freeLimit} on this selected item.`
                    : 'Free gallon rewards are for slim containers only. Select a slim container to redeem them.';
            }

            if (paidQuantity) {
                paidQuantity.textContent = quantity;
            }
            if (freeQuantity) {
                freeQuantity.textContent = redeemedFreeGallons;
            }
            if (totalQuantity) {
                totalQuantity.textContent = quantity + redeemedFreeGallons;
            }

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
            const newAddressInputs = [
                document.getElementById('new_delivery_street_address'),
                document.getElementById('new_delivery_province'),
                document.getElementById('new_delivery_city'),
                document.getElementById('new_delivery_barangay')
            ];

            if (!addressChoice || !newAddressFields) {
                return;
            }

            const isNewAddress = addressChoice.value === 'new';
            newAddressFields.classList.toggle('d-none', !isNewAddress);
            newAddressInputs.forEach(input => {
                if (input) {
                    input.required = isNewAddress;
                }
            });
        }

        function updateSelectedAddressPreview() {
            const addressChoice = document.getElementById('delivery_address_choice');
            const preview = document.getElementById('selected_address_preview');

            if (!addressChoice || !preview) {
                return;
            }

            const selectedOption = addressChoice.options[addressChoice.selectedIndex];
            const address = selectedOption?.dataset.address || '';
            const coverage = [selectedOption?.dataset.barangay, selectedOption?.dataset.city, selectedOption?.dataset.province].filter(Boolean).join(', ');
            preview.textContent = coverage ? `${address} (${coverage})` : address;
            preview.classList.toggle('d-none', address === '');
        }

        window.addEventListener('resize', renderProductWindow);
        const orderForm = document.querySelector('form [data-place-order-button]')?.closest('form');
        if (orderForm) {
            orderForm.addEventListener('submit', () => {
                if (!orderForm.checkValidity()) {
                    return;
                }

                const submitButton = orderForm.querySelector('[data-place-order-button]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Placing Order...';
                }
            });
        }

        const cancelOrderModalElement = document.getElementById('cancelOrderModal');
        const cancelOrderModal = cancelOrderModalElement ? new bootstrap.Modal(cancelOrderModalElement) : null;
        const cancelOrderIdInput = document.getElementById('cancelOrderId');
        const cancelOrderModalMessage = document.getElementById('cancelOrderModalMessage');
        const cancelOrderPenaltyWarning = document.getElementById('cancelOrderPenaltyWarning');
        const cancelOrderModalForm = document.getElementById('cancelOrderModalForm');
        const cancelOrderProgress = document.getElementById('cancelOrderProgress');
        const confirmCancelOrderButton = document.getElementById('confirmCancelOrderButton');
        const resultStatusModalElement = document.getElementById('resultStatusModal');
        if (resultStatusModalElement) {
            new bootstrap.Modal(resultStatusModalElement).show();
        }

        document.querySelectorAll('.cancel-order-form').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();

                const orderId = form.dataset.orderId || '';
                const status = form.dataset.orderStatus || 'pending';
                const hasPenalty = status !== 'pending';

                if (cancelOrderIdInput) {
                    cancelOrderIdInput.value = orderId;
                }
                if (cancelOrderModalMessage) {
                    cancelOrderModalMessage.textContent = hasPenalty
                        ? `Order #${orderId} is already being prepared. Do you still want to cancel it?`
                        : `Order #${orderId} is still pending. Do you want to cancel it?`;
                }
                if (cancelOrderPenaltyWarning) {
                    cancelOrderPenaltyWarning.classList.toggle('d-none', !hasPenalty);
                }
                if (cancelOrderProgress) {
                    cancelOrderProgress.classList.add('d-none');
                }
                if (confirmCancelOrderButton) {
                    confirmCancelOrderButton.disabled = false;
                    confirmCancelOrderButton.innerHTML = 'Cancel Order';
                }

                cancelOrderModal?.show();
            });
        });

        if (cancelOrderModalForm) {
            cancelOrderModalForm.addEventListener('submit', () => {
                if (cancelOrderProgress) {
                    cancelOrderProgress.classList.remove('d-none');
                }
                if (confirmCancelOrderButton) {
                    confirmCancelOrderButton.disabled = true;
                    confirmCancelOrderButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Cancelling...';
                }
            });
        }

        showSelectedProductInWindow();
        initializeAddressAreaSelects('new_delivery_');
        toggleNewDeliveryAddress();
        updateSelectedAddressPreview();
        updatePrice();
    </script>
    <script src="../../scripts/system_skeleton.js?v=20260527c"></script>
</body>
</html>
