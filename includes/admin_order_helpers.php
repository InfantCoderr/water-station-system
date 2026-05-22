<?php

function admin_delivery_queue_rows($conn, $queue_type, $statuses = ['confirmed']) {
    $date_condition = $queue_type === 'scheduled'
        ? "o.delivery_date > CURDATE()"
        : "(o.delivery_date IS NULL OR o.delivery_date = '0000-00-00' OR o.delivery_date <= CURDATE())";

    $allowed_statuses = ['pending', 'confirmed'];
    $statuses = array_values(array_intersect($allowed_statuses, (array) $statuses));
    if (empty($statuses)) {
        $statuses = ['confirmed'];
    }
    $status_list = "'" . implode("','", $statuses) . "'";

    $order_direction = $queue_type === 'scheduled' ? 'ASC' : 'ASC';
    $query = "
        SELECT
            o.order_id,
            o.order_status,
            o.order_date,
            o.delivery_date,
            o.delivery_address,
            o.delivery_barangay,
            o.delivery_city,
            o.delivery_province,
            o.total_amount,
            c.full_name AS customer_name,
            c.phone AS customer_phone,
            area.zone_code,
            area.zone_name,
            area.zone_sort_order,
            order_items.item_summary,
            order_items.total_quantity,
            order_items.capacity_units
        FROM orders o
        JOIN users c ON o.customer_id = c.user_id
        LEFT JOIN delivery_service_areas area
            ON area.province = o.delivery_province
            AND area.city = o.delivery_city
            AND area.barangay = o.delivery_barangay
            AND area.is_active = 1
        LEFT JOIN (
            SELECT
                oi.order_id,
                GROUP_CONCAT(CONCAT(i.item_name, ' x', oi.quantity) ORDER BY i.item_name SEPARATOR ', ') AS item_summary,
                SUM(oi.quantity) AS total_quantity,
                SUM(oi.quantity * COALESCE(NULLIF(i.capacity_units, 0), 1)) AS capacity_units
            FROM order_items oi
            JOIN inventory i ON oi.inventory_id = i.inventory_id
            GROUP BY oi.order_id
        ) order_items ON o.order_id = order_items.order_id
        WHERE o.order_status IN ($status_list)
            AND $date_condition
            AND NOT EXISTS (
                SELECT 1
                FROM delivery_batch_items bi
                JOIN delivery_batches b ON b.batch_id = bi.batch_id
                WHERE bi.order_id = o.order_id
                    AND bi.item_status = 'active'
                    AND b.batch_status IN ('draft', 'confirmed', 'assigned', 'in_transit')
            )
            AND NOT EXISTS (
                SELECT 1
                FROM deliveries d
                WHERE d.order_id = o.order_id
                    AND d.delivery_status IN ('assigned', 'in_transit')
            )
        ORDER BY
            CASE WHEN o.delivery_date IS NULL OR o.delivery_date = '0000-00-00' THEN 1 ELSE 0 END ASC,
            o.delivery_date $order_direction,
            COALESCE(area.zone_sort_order, 9999) ASC,
            o.order_date ASC,
            o.order_id ASC
    ";

    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function admin_order_status_badge_class($status) {
    switch ($status) {
        case 'pending':
            return 'text-bg-warning';
        case 'confirmed':
            return 'text-bg-info';
        case 'processing':
        case 'out_for_delivery':
            return 'text-bg-primary';
        case 'delivered':
            return 'text-bg-success';
        case 'cancelled':
        case 'returned':
            return 'text-bg-danger';
        default:
            return 'text-bg-secondary';
    }
}

function admin_delivery_status_badge_class($status) {
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

function admin_delivery_status_label($status) {
    return ucfirst(str_replace('_', ' ', (string) $status));
}

function admin_delivery_date_label($delivery_date) {
    $delivery_date = (string) $delivery_date;
    if ($delivery_date === '' || $delivery_date === '0000-00-00') {
        return 'No date set';
    }

    $timestamp = strtotime($delivery_date);
    return $timestamp ? date('M d, Y', $timestamp) : 'No date set';
}

function admin_queue_capacity_badge_class($capacity_units, $capacity_limit) {
    $capacity_units = (int) $capacity_units;
    $capacity_limit = max(1, (int) $capacity_limit);

    if ($capacity_units > $capacity_limit) {
        return 'text-bg-danger';
    }

    if ($capacity_units === $capacity_limit) {
        return 'text-bg-success';
    }

    if ($capacity_units >= ($capacity_limit * 0.75)) {
        return 'text-bg-primary';
    }

    return 'text-bg-secondary';
}

function admin_order_status_label($status) {
    return ucfirst(str_replace('_', ' ', (string) $status));
}

function admin_order_workflow_steps() {
    return [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
    ];
}

function admin_order_next_statuses($status) {
    switch ($status) {
        case 'pending':
            return ['confirmed', 'cancelled'];
        case 'confirmed':
        case 'processing':
        case 'out_for_delivery':
            return ['cancelled'];
        default:
            return [];
    }
}

function admin_order_step_class($step, $current_status) {
    if ($current_status === 'cancelled') {
        return 'text-bg-light text-secondary border';
    }

    $steps = array_keys(admin_order_workflow_steps());
    $current_index = array_search($current_status, $steps, true);
    $step_index = array_search($step, $steps, true);

    if ($current_index === false || $step_index === false) {
        return 'text-bg-light text-secondary border';
    }

    if ($step_index < $current_index) {
        return 'text-bg-success';
    }

    if ($step_index === $current_index) {
        return admin_order_status_badge_class($current_status);
    }

    return 'text-bg-light text-secondary border';
}
