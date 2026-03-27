<?php

function sanitize_status_filter($value, $allowed, $default = 'all') {
    return in_array($value, $allowed, true) ? $value : $default;
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

function fetch_order_state_for_update($conn, $order_id) {
    $stmt = $conn->prepare("SELECT o.order_id, o.customer_id, o.order_status, d.delivery_id, d.staff_id, d.delivery_status, d.picked_up_at, d.delivered_at FROM orders o LEFT JOIN deliveries d ON d.order_id = o.order_id WHERE o.order_id = ? FOR UPDATE");
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
    $stmt = $conn->prepare("SELECT inventory_id, item_name, unit_price, stock_quantity, status FROM inventory WHERE inventory_id = ? FOR UPDATE");
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

function find_best_available_staff($conn) {
    $result = $conn->query("SELECT u.user_id, SUM(CASE WHEN d.delivery_status IN ('assigned', 'picked_up', 'in_transit') THEN 1 ELSE 0 END) AS active_deliveries FROM users u LEFT JOIN deliveries d ON d.staff_id = u.user_id WHERE u.role = 'staff' AND u.status = 'active' GROUP BY u.user_id ORDER BY active_deliveries ASC, u.user_id ASC LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        return null;
    }
    return $result->fetch_assoc();
}

function assign_order_to_staff($conn, $order_id, $staff_id, $assigned_by = null, $assignment_type = 'manual') {
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
            $stmt = $conn->prepare("UPDATE deliveries SET staff_id = ?, assigned_by = NULL, assignment_type = ?, assigned_at = NOW(), picked_up_at = NULL, delivered_at = NULL, delivery_status = 'assigned', proof_of_delivery = NULL WHERE delivery_id = ?");
            $stmt->bind_param("isi", $staff_id, $assignment_type, $state['delivery_id']);
        } else {
            $stmt = $conn->prepare("UPDATE deliveries SET staff_id = ?, assigned_by = ?, assignment_type = ?, assigned_at = NOW(), picked_up_at = NULL, delivered_at = NULL, delivery_status = 'assigned', proof_of_delivery = NULL WHERE delivery_id = ?");
            $stmt->bind_param("iisi", $staff_id, $assigned_by, $assignment_type, $state['delivery_id']);
        }
    } else {
        if ($assigned_by === null) {
            $stmt = $conn->prepare("INSERT INTO deliveries (order_id, staff_id, assignment_type, delivery_status) VALUES (?, ?, ?, 'assigned')");
            $stmt->bind_param("iis", $order_id, $staff_id, $assignment_type);
        } else {
            $stmt = $conn->prepare("INSERT INTO deliveries (order_id, staff_id, assigned_by, assignment_type, delivery_status) VALUES (?, ?, ?, ?, 'assigned')");
            $stmt->bind_param("iiis", $order_id, $staff_id, $assigned_by, $assignment_type);
        }
    }
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE orders SET order_status = 'confirmed' WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();
}

function auto_assign_order_to_staff($conn, $order_id) {
    $staff = find_best_available_staff($conn);
    if (!$staff) {
        return null;
    }
    assign_order_to_staff($conn, $order_id, (int) $staff['user_id'], null, 'automatic');
    return (int) $staff['user_id'];
}

function transition_order_status($conn, $order_id, $new_status) {
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
    $has_active_delivery = $state['delivery_id'] && !in_array($previous_delivery_status, ['failed', 'returned', 'delivered'], true);

    if (in_array($previous_order_status, ['delivered', 'cancelled', 'returned'], true) && $previous_order_status !== $new_status) {
        throw new Exception("This order is already final and cannot be changed.");
    }

    switch ($new_status) {
        case 'pending':
            if ($has_active_delivery) {
                throw new Exception("Active deliveries must be reassigned or cancelled first.");
            }
            $stmt = $conn->prepare("UPDATE orders SET order_status = 'pending' WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            break;

        case 'confirmed':
        case 'processing':
            $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
            $stmt->bind_param("si", $new_status, $order_id);
            $stmt->execute();
            $stmt->close();
            if ($state['delivery_id']) {
                $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'assigned', picked_up_at = NULL WHERE delivery_id = ?");
                $stmt->bind_param("i", $state['delivery_id']);
                $stmt->execute();
                $stmt->close();
            }
            break;

        case 'out_for_delivery':
            if (!$state['delivery_id']) {
                throw new Exception("Assign a staff member before marking this order out for delivery.");
            }
            if (in_array($previous_delivery_status, ['failed', 'returned'], true)) {
                throw new Exception("Reassign this order before marking it out for delivery.");
            }
            $stmt = $conn->prepare("UPDATE orders SET order_status = 'out_for_delivery' WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'in_transit', picked_up_at = COALESCE(picked_up_at, NOW()) WHERE delivery_id = ?");
            $stmt->bind_param("i", $state['delivery_id']);
            $stmt->execute();
            $stmt->close();
            break;

        case 'delivered':
            if ($previous_order_status === 'cancelled') {
                throw new Exception("Cancelled orders cannot be marked as delivered.");
            }
            $stmt = $conn->prepare("UPDATE orders SET order_status = 'delivered' WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            if ($state['delivery_id']) {
                $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'delivered', delivered_at = COALESCE(delivered_at, NOW()) WHERE delivery_id = ?");
                $stmt->bind_param("i", $state['delivery_id']);
                $stmt->execute();
                $stmt->close();
            }
            if ($previous_order_status !== 'delivered' && $previous_delivery_status !== 'delivered') {
                award_loyalty_for_delivery($conn, (int) $state['customer_id'], $order_id);
            }
            break;

        case 'cancelled':
            if ($previous_order_status === 'delivered') {
                throw new Exception("Delivered orders cannot be cancelled.");
            }
            $stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            if ($state['delivery_id'] && !in_array($previous_delivery_status, ['delivered', 'returned'], true)) {
                $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'returned' WHERE delivery_id = ?");
                $stmt->bind_param("i", $state['delivery_id']);
                $stmt->execute();
                $stmt->close();
            }
            if ($previous_order_status !== 'cancelled') {
                return_order_stock($conn, $order_id);
                if (in_array($previous_order_status, ['confirmed', 'processing', 'out_for_delivery'], true) || in_array($previous_delivery_status, ['assigned', 'picked_up', 'in_transit'], true)) {
                    reset_loyalty_progress($conn, (int) $state['customer_id']);
                }
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
    if (in_array($delivery['delivery_status'], ['delivered', 'failed', 'returned'], true)) {
        throw new Exception("This delivery is already closed.");
    }

    $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'delivered', delivered_at = COALESCE(delivered_at, NOW()) WHERE delivery_id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE orders SET order_status = 'delivered' WHERE order_id = ?");
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
    if (in_array($delivery['delivery_status'], ['delivered', 'failed', 'returned'], true)) {
        throw new Exception("This delivery is already closed.");
    }

    $stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'failed', delivery_notes = ?, delivered_at = NULL WHERE delivery_id = ?");
    $stmt->bind_param("si", $reason, $delivery_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE orders SET order_status = 'pending' WHERE order_id = ?");
    $stmt->bind_param("i", $delivery['order_id']);
    $stmt->execute();
    $stmt->close();

    return (int) $delivery['order_id'];
}
