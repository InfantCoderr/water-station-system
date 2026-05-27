<?php

function sanitize_status_filter($value, $allowed, $default = 'all') {
    return in_array($value, $allowed, true) ? $value : $default;
}

function order_payment_method_label($payment_method) {
    $payment_method = (string) $payment_method;
    if ($payment_method === 'cod' || $payment_method === 'cash_on_delivery') {
        return 'Cash on Delivery';
    }

    return ucwords(str_replace('_', ' ', $payment_method !== '' ? $payment_method : 'cash_on_delivery'));
}

function order_payment_status_label($payment_status) {
    return ucwords(str_replace('_', ' ', (string) ($payment_status !== '' ? $payment_status : 'pending')));
}

function sync_inventory_status($conn, $inventory_id) {
    $stmt = $conn->prepare("UPDATE inventory SET status = CASE WHEN status = 'discontinued' THEN 'discontinued' WHEN stock_quantity <= 0 THEN 'out_of_stock' ELSE 'available' END WHERE inventory_id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $stmt->close();
}

function ensure_loyalty_record($conn, $customer_id) {
    $stmt = $conn->prepare("INSERT INTO loyalty (customer_id, total_orders, consecutive_orders, free_gallons_earned, free_gallons_used) VALUES (?, 0, 0, 0, 0) ON DUPLICATE KEY UPDATE customer_id = customer_id");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stmt->close();
}

function ensure_free_gallon_redemption_usage_column($conn) {
    $column = $conn->query("SHOW COLUMNS FROM free_gallon_redemptions LIKE 'used_order_id'");
    if ($column && $column->num_rows > 0) {
        return;
    }

    $conn->query("ALTER TABLE free_gallon_redemptions ADD COLUMN used_order_id INT(11) NULL DEFAULT NULL AFTER order_id");
    if ($conn->error) {
        error_log('Failed to add used_order_id to free_gallon_redemptions: ' . $conn->error);
    }
}

function expire_free_gallon_redemptions($conn, $customer_id = null) {
    ensure_free_gallon_redemption_usage_column($conn);

    if ($customer_id === null) {
        $conn->query("UPDATE free_gallon_redemptions SET status = 'expired', used_order_id = NULL WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= NOW()");
        return;
    }

    $stmt = $conn->prepare("UPDATE free_gallon_redemptions SET status = 'expired', used_order_id = NULL WHERE customer_id = ? AND status = 'active' AND expires_at IS NOT NULL AND expires_at <= NOW()");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stmt->close();
}

function fetch_available_free_gallon_count($conn, $customer_id) {
    ensure_loyalty_record($conn, $customer_id);
    expire_free_gallon_redemptions($conn, $customer_id);

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM free_gallon_redemptions WHERE customer_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt->close();

    return $count;
}

function consume_free_gallon_redemption($conn, $customer_id, $order_id, $gallons = 1) {
    $gallons = max(0, (int) $gallons);
    if ($gallons < 1) {
        return 0;
    }

    ensure_loyalty_record($conn, $customer_id);
    expire_free_gallon_redemptions($conn, $customer_id);

    $select = $conn->prepare("SELECT redemption_id FROM free_gallon_redemptions WHERE customer_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY redeemed_at ASC, redemption_id ASC LIMIT 1 FOR UPDATE");
    $update = $conn->prepare("UPDATE free_gallon_redemptions SET status = 'used', used_order_id = ? WHERE redemption_id = ?");

    $consumed = 0;
    for ($index = 0; $index < $gallons; $index++) {
        $select->bind_param("i", $customer_id);
        $select->execute();
        $reward = $select->get_result()->fetch_assoc();
        if (!$reward) {
            $select->close();
            $update->close();
            throw new Exception("You do not have an available free gallon to redeem.");
        }

        $redemption_id = (int) $reward['redemption_id'];
        $update->bind_param("ii", $order_id, $redemption_id);
        $update->execute();
        $consumed++;
    }

    $select->close();
    $update->close();

    $stmt = $conn->prepare("UPDATE loyalty SET free_gallons_used = free_gallons_used + ? WHERE customer_id = ?");
    $stmt->bind_param("ii", $consumed, $customer_id);
    $stmt->execute();
    $stmt->close();

    return $consumed;
}

function restore_free_gallon_redemptions_for_order($conn, $order_id) {
    ensure_free_gallon_redemption_usage_column($conn);

    $stmt = $conn->prepare("SELECT redemption_id, customer_id FROM free_gallon_redemptions WHERE used_order_id = ? AND status = 'used' FOR UPDATE");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $rewards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$rewards) {
        return 0;
    }

    $restore = $conn->prepare("UPDATE free_gallon_redemptions SET status = 'active', used_order_id = NULL WHERE redemption_id = ?");
    $customer_restore_counts = [];

    foreach ($rewards as $reward) {
        $redemption_id = (int) $reward['redemption_id'];
        $customer_id = (int) $reward['customer_id'];
        $restore->bind_param("i", $redemption_id);
        $restore->execute();
        $customer_restore_counts[$customer_id] = ($customer_restore_counts[$customer_id] ?? 0) + 1;
    }

    $restore->close();

    $loyalty = $conn->prepare("UPDATE loyalty SET free_gallons_used = GREATEST(free_gallons_used - ?, 0) WHERE customer_id = ?");
    foreach ($customer_restore_counts as $customer_id => $count) {
        ensure_loyalty_record($conn, (int) $customer_id);
        $loyalty->bind_param("ii", $count, $customer_id);
        $loyalty->execute();
    }
    $loyalty->close();

    return count($rewards);
}

function max_customer_order_units() {
    if (function_exists('delivery_batch_capacity_limit_units')) {
        return delivery_batch_capacity_limit_units();
    }

    return 16;
}

function max_order_quantity_for_capacity_units($capacity_units) {
    $capacity_units = max(1, (int) $capacity_units);
    return max(1, (int) floor(max_customer_order_units() / $capacity_units));
}

function max_order_quantity_for_item($item) {
    return max_order_quantity_for_capacity_units((int) ($item['capacity_units'] ?? 1));
}

function item_allows_free_gallon_redemption($item) {
    $item_name = strtolower((string) ($item['item_name'] ?? ''));
    return strpos($item_name, 'slim') !== false;
}

function validate_customer_order_limit($item, $paid_quantity, $free_quantity = 0) {
    $paid_quantity = max(0, (int) $paid_quantity);
    $free_quantity = max(0, (int) $free_quantity);
    $capacity_units = max(1, (int) ($item['capacity_units'] ?? 1));
    $max_quantity = max_order_quantity_for_capacity_units($capacity_units);
    $total_quantity = $paid_quantity + $free_quantity;

    if ($paid_quantity < 1) {
        throw new Exception("Order must include at least 1 paid gallon.");
    }

    if ($free_quantity > 0 && !item_allows_free_gallon_redemption($item)) {
        throw new Exception("Free gallon rewards can only be redeemed for slim containers.");
    }

    if ($total_quantity > $max_quantity) {
        $paid_limit_with_reward = max(0, $max_quantity - $free_quantity);
        if ($free_quantity > 0) {
            throw new Exception("This item allows only {$max_quantity} gallon(s) per order. With {$free_quantity} free gallon(s) redeemed, you can pay for up to {$paid_limit_with_reward} gallon(s).");
        }

        throw new Exception("This item allows only {$max_quantity} gallon(s) per order.");
    }
}

function ensure_delivery_cancelled_status($conn) {
    $column = $conn->query("SHOW COLUMNS FROM deliveries LIKE 'delivery_status'");
    if (!$column || $column->num_rows < 1) {
        return;
    }

    $definition = $column->fetch_assoc();
    $type = (string) ($definition['Type'] ?? '');
    if (strpos($type, "'cancelled'") !== false) {
        return;
    }

    $conn->query("ALTER TABLE deliveries MODIFY delivery_status ENUM('assigned','in_transit','delivered','failed','returned','cancelled') DEFAULT 'assigned'");
    if ($conn->error) {
        error_log('Failed to add cancelled delivery status: ' . $conn->error);
    }
}

function fetch_order_state_for_update($conn, $order_id) {
    $stmt = $conn->prepare("SELECT o.order_id, o.customer_id, o.order_status, d.delivery_id, d.staff_id, d.delivery_status, d.delivered_at FROM orders o LEFT JOIN deliveries d ON d.order_id = o.order_id WHERE o.order_id = ? FOR UPDATE");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $state = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $state ?: null;
}

function award_loyalty_for_delivery($conn, $customer_id, $order_id) {
    ensure_loyalty_record($conn, $customer_id);
    $stmt = $conn->prepare("SELECT consecutive_orders FROM loyalty WHERE customer_id = ? FOR UPDATE");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $loyalty = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $next_consecutive = (int) ($loyalty['consecutive_orders'] ?? 0) + 1;
    $stmt = $conn->prepare("UPDATE loyalty SET total_orders = total_orders + 1, consecutive_orders = ?, last_order_date = CURDATE() WHERE customer_id = ?");
    $stmt->bind_param("ii", $next_consecutive, $customer_id);
    $stmt->execute();
    $stmt->close();

    if ($next_consecutive % 5 !== 0) {
        return;
    }

    $stmt = $conn->prepare("SELECT redemption_id FROM free_gallon_redemptions WHERE order_id = ? LIMIT 1 FOR UPDATE");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $existing_reward = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing_reward) {
        return;
    }

    $stmt = $conn->prepare("UPDATE loyalty SET free_gallons_earned = free_gallons_earned + 1 WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO free_gallon_redemptions (customer_id, order_id, gallons_redeemed, status, expires_at) VALUES (?, ?, 1, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY))");
    $stmt->bind_param("ii", $customer_id, $order_id);
    $stmt->execute();
    $stmt->close();
}

function reset_loyalty_progress($conn, $customer_id) {
    ensure_loyalty_record($conn, $customer_id);
    $stmt = $conn->prepare("UPDATE loyalty SET consecutive_orders = 0 WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stmt->close();
}

function return_order_stock($conn, $order_id) {
    $stmt = $conn->prepare("SELECT inventory_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $update = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity + ? WHERE inventory_id = ?");
    foreach ($items as $item) {
        $quantity = (int) $item['quantity'];
        $inventory_id = (int) $item['inventory_id'];
        $update->bind_param("ii", $quantity, $inventory_id);
        $update->execute();
        sync_inventory_status($conn, $inventory_id);
    }
    $update->close();
}

function reserve_inventory_stock($conn, $inventory_id, $quantity) {
    $stmt = $conn->prepare("SELECT inventory_id, item_name, unit_price, stock_quantity, status, capacity_units FROM inventory WHERE inventory_id = ? FOR UPDATE");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        throw new Exception("Invalid item selected.");
    }
    if ($item['status'] === 'discontinued') {
        throw new Exception("This item is no longer available.");
    }
    if ((int) $item['stock_quantity'] < $quantity) {
        throw new Exception("Only " . (int) $item['stock_quantity'] . " gallon(s) are currently in stock.");
    }

    $stmt = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE inventory_id = ?");
    $stmt->bind_param("ii", $quantity, $inventory_id);
    $stmt->execute();
    $stmt->close();
    sync_inventory_status($conn, $inventory_id);

    return $item;
}

function assign_order_to_staff($conn, $order_id, $staff_id, $assigned_by = null) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'staff' AND status = 'active' LIMIT 1");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$staff) {
        throw new Exception("Selected staff member is not active.");
    }

    $state = fetch_order_state_for_update($conn, $order_id);
    if (!$state) {
        throw new Exception("Order not found.");
    }
    if (in_array($state['order_status'], ['delivered', 'cancelled', 'returned'], true)) {
        throw new Exception("This order can no longer be assigned.");
    }

    if ($state['delivery_id']) {
        if ($assigned_by === null) {
            $stmt = $conn->prepare("UPDATE deliveries SET staff_id = ?, assigned_by = NULL, assigned_at = NOW(), delivered_at = NULL, delivery_status = 'assigned', proof_of_delivery = NULL WHERE delivery_id = ?");
            $stmt->bind_param("ii", $staff_id, $state['delivery_id']);
        } else {
            $stmt = $conn->prepare("UPDATE deliveries SET staff_id = ?, assigned_by = ?, assigned_at = NOW(), delivered_at = NULL, delivery_status = 'assigned', proof_of_delivery = NULL WHERE delivery_id = ?");
            $stmt->bind_param("iii", $staff_id, $assigned_by, $state['delivery_id']);
        }
    } else {
        if ($assigned_by === null) {
            $stmt = $conn->prepare("INSERT INTO deliveries (order_id, staff_id, delivery_status) VALUES (?, ?, 'assigned')");
            $stmt->bind_param("ii", $order_id, $staff_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO deliveries (order_id, staff_id, assigned_by, delivery_status) VALUES (?, ?, ?, 'assigned')");
            $stmt->bind_param("iii", $order_id, $staff_id, $assigned_by);
        }
    }
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE orders SET order_status = 'confirmed', payment_status = 'pending' WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();
}

function log_order_activity($conn, $user_id, $action, $description, $order_id) {
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, description, related_table, related_id, ip_address)
        VALUES (?, ?, ?, 'orders', ?, ?)
    ");
    if (!$stmt) {
        return;
    }

    $user_id = $user_id ? (int) $user_id : null;
    $order_id = (int) $order_id;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->bind_param("issis", $user_id, $action, $description, $order_id, $ip_address);
    $stmt->execute();
    $stmt->close();
}

function transition_order_status($conn, $order_id, $new_status, $actor_id = null, $actor_label = 'system') {
    $allowed_statuses = ['pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered', 'cancelled'];
    if (!in_array($new_status, $allowed_statuses, true)) {
        throw new Exception("Invalid order status.");
    }

    $state = fetch_order_state_for_update($conn, $order_id);
    if (!$state) {
        throw new Exception("Order not found.");
    }

    $previous_order_status = $state['order_status'];
    $previous_delivery_status = $state['delivery_status'];
    $has_active_delivery = $state['delivery_id'] && !in_array($previous_delivery_status, ['failed', 'returned', 'delivered', 'cancelled'], true);

    if (in_array($previous_order_status, ['delivered', 'cancelled', 'returned'], true) && $previous_order_status !== $new_status) {
        throw new Exception("This order is already final and cannot be changed.");
    }

    switch ($new_status) {
        case 'pending':
            if ($has_active_delivery) {
                throw new Exception("Active deliveries must be reassigned or cancelled first.");
            }
            $stmt = $conn->prepare("UPDATE orders SET order_status = 'pending', payment_status = 'pending' WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            break;

        case 'confirmed':
        case 'processing':
            $stmt = $conn->prepare("UPDATE orders SET order_status = ?, payment_status = 'pending' WHERE order_id = ?");
            $stmt->bind_param("si", $new_status, $order_id);
            $stmt->execute();
            $stmt->close();
            if ($state['delivery_id'] && !in_array($previous_delivery_status, ['failed', 'returned', 'delivered', 'cancelled'], true)) {
                $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'assigned' WHERE delivery_id = ?");
                $stmt->bind_param("i", $state['delivery_id']);
                $stmt->execute();
                $stmt->close();
            }
            break;

        case 'out_for_delivery':
            if (!$state['delivery_id']) {
                throw new Exception("Assign a staff member before marking this order out for delivery.");
            }
            if (in_array($previous_delivery_status, ['failed', 'returned', 'cancelled'], true)) {
                throw new Exception("Reassign this order before marking it out for delivery.");
            }
            $stmt = $conn->prepare("UPDATE orders SET order_status = 'out_for_delivery', payment_status = 'pending' WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'in_transit' WHERE delivery_id = ?");
            $stmt->bind_param("i", $state['delivery_id']);
            $stmt->execute();
            $stmt->close();
            break;

        case 'delivered':
            if ($previous_order_status === 'cancelled') {
                throw new Exception("Cancelled orders cannot be marked as delivered.");
            }
            if (!$state['delivery_id']) {
                throw new Exception("Assign a delivery before marking this order as delivered.");
            }
            if (in_array($previous_delivery_status, ['failed', 'returned', 'cancelled'], true)) {
                throw new Exception("Reassign this order before marking it as delivered.");
            }
            $stmt = $conn->prepare("UPDATE orders SET order_status = 'delivered', payment_status = 'paid' WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'delivered', delivered_at = COALESCE(delivered_at, NOW()) WHERE delivery_id = ?");
            $stmt->bind_param("i", $state['delivery_id']);
            $stmt->execute();
            $stmt->close();
            if (function_exists('refresh_delivery_batch_completion_for_delivery')) {
                refresh_delivery_batch_completion_for_delivery($conn, (int) $state['delivery_id']);
            }
            if ($previous_order_status !== 'delivered' && $previous_delivery_status !== 'delivered') {
                award_loyalty_for_delivery($conn, (int) $state['customer_id'], $order_id);
            }
            break;

        case 'cancelled':
            if ($previous_order_status === 'delivered') {
                throw new Exception("Delivered orders cannot be cancelled.");
            }
            $label = strtolower(trim((string) $actor_label));
            if ($label === '') {
                $label = 'system';
            }
            $cancel_description = 'Order cancelled by ' . $label . '.';

            $stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled', payment_status = 'pending' WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            if ($state['delivery_id'] && !in_array($previous_delivery_status, ['delivered', 'cancelled'], true)) {
                $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'cancelled', delivery_notes = COALESCE(NULLIF(delivery_notes, ''), ?), delivered_at = NULL WHERE delivery_id = ?");
                $stmt->bind_param("si", $cancel_description, $state['delivery_id']);
                $stmt->execute();
                $stmt->close();
                if (function_exists('refresh_delivery_batch_completion_for_delivery')) {
                    refresh_delivery_batch_completion_for_delivery($conn, (int) $state['delivery_id']);
                }
            }
            if ($previous_order_status !== 'cancelled') {
                return_order_stock($conn, $order_id);
                restore_free_gallon_redemptions_for_order($conn, $order_id);
                if (in_array($previous_order_status, ['confirmed', 'processing', 'out_for_delivery'], true) || in_array($previous_delivery_status, ['assigned', 'in_transit'], true)) {
                    reset_loyalty_progress($conn, (int) $state['customer_id']);
                }
            }
            if ($previous_order_status !== 'cancelled') {
                log_order_activity($conn, $actor_id, 'order_cancelled', $cancel_description, $order_id);
            }
            break;
    }
}

function mark_delivery_as_delivered($conn, $delivery_id, $staff_id) {
    $stmt = $conn->prepare("SELECT d.delivery_id, d.order_id, d.delivery_status, o.customer_id, o.order_status FROM deliveries d JOIN orders o ON o.order_id = d.order_id WHERE d.delivery_id = ? AND d.staff_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $delivery_id, $staff_id);
    $stmt->execute();
    $delivery = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$delivery) {
        throw new Exception("Delivery not found.");
    }
    if (in_array($delivery['delivery_status'], ['delivered', 'failed', 'returned', 'cancelled'], true)) {
        throw new Exception("This delivery is already closed.");
    }

    $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'delivered', delivered_at = COALESCE(delivered_at, NOW()) WHERE delivery_id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE orders SET order_status = 'delivered', payment_status = 'paid' WHERE order_id = ?");
    $stmt->bind_param("i", $delivery['order_id']);
    $stmt->execute();
    $stmt->close();

    if ($delivery['order_status'] !== 'delivered') {
        award_loyalty_for_delivery($conn, (int) $delivery['customer_id'], (int) $delivery['order_id']);
    }

    return (int) $delivery['order_id'];
}

function mark_delivery_as_failed($conn, $delivery_id, $staff_id, $reason) {
    $stmt = $conn->prepare("SELECT d.delivery_id, d.order_id, d.delivery_status FROM deliveries d JOIN orders o ON o.order_id = d.order_id WHERE d.delivery_id = ? AND d.staff_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $delivery_id, $staff_id);
    $stmt->execute();
    $delivery = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$delivery) {
        throw new Exception("Delivery not found.");
    }
    if (in_array($delivery['delivery_status'], ['delivered', 'failed', 'returned', 'cancelled'], true)) {
        throw new Exception("This delivery is already closed.");
    }

    $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'failed', delivery_notes = ?, delivered_at = NOW() WHERE delivery_id = ?");
    $stmt->bind_param("si", $reason, $delivery_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE orders SET order_status = 'pending', payment_status = 'pending' WHERE order_id = ?");
    $stmt->bind_param("i", $delivery['order_id']);
    $stmt->execute();
    $stmt->close();

    return (int) $delivery['order_id'];
}

function mark_delivery_as_returned($conn, $delivery_id, $staff_id, $reason) {
    $stmt = $conn->prepare("SELECT d.delivery_id, d.order_id, d.delivery_status FROM deliveries d JOIN orders o ON o.order_id = d.order_id WHERE d.delivery_id = ? AND d.staff_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $delivery_id, $staff_id);
    $stmt->execute();
    $delivery = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$delivery) {
        throw new Exception("Delivery not found.");
    }
    if (in_array($delivery['delivery_status'], ['delivered', 'failed', 'returned', 'cancelled'], true)) {
        throw new Exception("This delivery is already closed.");
    }

    $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'returned', delivery_notes = ?, delivered_at = NOW() WHERE delivery_id = ?");
    $stmt->bind_param("si", $reason, $delivery_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE orders SET order_status = 'pending', payment_status = 'pending' WHERE order_id = ?");
    $stmt->bind_param("i", $delivery['order_id']);
    $stmt->execute();
    $stmt->close();

    return (int) $delivery['order_id'];
}
