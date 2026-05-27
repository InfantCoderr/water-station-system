<?php

function delivery_batch_capacity_limit_units() {
    return 16;
}

function ensure_inventory_capacity_units_column($conn) {
    $column = $conn->query("SHOW COLUMNS FROM inventory LIKE 'capacity_units'");
    if ($column && $column->num_rows > 0) {
        return;
    }

    if ($conn->query("ALTER TABLE inventory ADD COLUMN capacity_units TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER item_type")) {
        $conn->query("UPDATE inventory SET capacity_units = 2 WHERE LOWER(item_name) LIKE '%round%'");
        $conn->query("UPDATE inventory SET capacity_units = 1 WHERE LOWER(item_name) LIKE '%slim%' OR capacity_units < 1");
    } else {
        error_log('Failed to add inventory capacity_units column: ' . $conn->error);
    }
}

function ensure_delivery_batch_schema($conn) {
    ensure_inventory_capacity_units_column($conn);

    $conn->query("
        CREATE TABLE IF NOT EXISTS delivery_batches (
            batch_id INT(11) NOT NULL AUTO_INCREMENT,
            batch_code VARCHAR(40) DEFAULT NULL,
            batch_date DATE NOT NULL,
            zone_code VARCHAR(40) DEFAULT NULL,
            zone_name VARCHAR(160) DEFAULT NULL,
            batch_type ENUM('normal','merged','underfilled') NOT NULL DEFAULT 'normal',
            capacity_limit_units TINYINT UNSIGNED NOT NULL DEFAULT 16,
            used_capacity_units SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            batch_status ENUM('draft','confirmed','assigned','in_transit','completed','cancelled') NOT NULL DEFAULT 'draft',
            staff_id INT(11) DEFAULT NULL,
            created_by INT(11) DEFAULT NULL,
            confirmed_by INT(11) DEFAULT NULL,
            assigned_by INT(11) DEFAULT NULL,
            confirmed_at TIMESTAMP NULL DEFAULT NULL,
            assigned_at TIMESTAMP NULL DEFAULT NULL,
            started_at TIMESTAMP NULL DEFAULT NULL,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            cancelled_at TIMESTAMP NULL DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (batch_id),
            UNIQUE KEY uniq_delivery_batch_code (batch_code),
            KEY idx_delivery_batches_date_status (batch_date, batch_status),
            KEY idx_delivery_batches_zone (zone_code, batch_date),
            KEY idx_delivery_batches_staff (staff_id, batch_status),
            KEY idx_delivery_batches_created_by (created_by),
            KEY idx_delivery_batches_confirmed_by (confirmed_by),
            KEY idx_delivery_batches_assigned_by (assigned_by),
            CONSTRAINT delivery_batches_ibfk_1 FOREIGN KEY (staff_id) REFERENCES users (user_id) ON DELETE SET NULL,
            CONSTRAINT delivery_batches_ibfk_2 FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE SET NULL,
            CONSTRAINT delivery_batches_ibfk_3 FOREIGN KEY (confirmed_by) REFERENCES users (user_id) ON DELETE SET NULL,
            CONSTRAINT delivery_batches_ibfk_4 FOREIGN KEY (assigned_by) REFERENCES users (user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if ($conn->error) {
        error_log('Failed to ensure delivery_batches table: ' . $conn->error);
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS delivery_batch_items (
            batch_item_id INT(11) NOT NULL AUTO_INCREMENT,
            batch_id INT(11) NOT NULL,
            order_id INT(11) NOT NULL,
            capacity_units SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            item_status ENUM('active','removed') NOT NULL DEFAULT 'active',
            sort_order INT(11) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (batch_item_id),
            UNIQUE KEY uniq_delivery_batch_order (batch_id, order_id),
            KEY idx_delivery_batch_items_order (order_id),
            KEY idx_delivery_batch_items_status (item_status),
            KEY idx_delivery_batch_items_sort (batch_id, sort_order),
            CONSTRAINT delivery_batch_items_ibfk_1 FOREIGN KEY (batch_id) REFERENCES delivery_batches (batch_id) ON DELETE CASCADE,
            CONSTRAINT delivery_batch_items_ibfk_2 FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if ($conn->error) {
        error_log('Failed to ensure delivery_batch_items table: ' . $conn->error);
    }
}

function calculate_order_capacity_units($conn, $order_id) {
    $order_id = (int) $order_id;
    if ($order_id < 1) {
        return 0;
    }

    ensure_inventory_capacity_units_column($conn);

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(oi.quantity * COALESCE(NULLIF(i.capacity_units, 0), 1)), 0) AS capacity_units
        FROM order_items oi
        JOIN inventory i ON oi.inventory_id = i.inventory_id
        WHERE oi.order_id = ?
    ");
    if (!$stmt) {
        error_log('Unable to prepare order capacity calculation: ' . $conn->error);
        return 0;
    }

    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['capacity_units'] ?? 0);
}

function fetch_order_capacity_breakdown($conn, $order_id) {
    $order_id = (int) $order_id;
    if ($order_id < 1) {
        return [];
    }

    ensure_inventory_capacity_units_column($conn);

    $stmt = $conn->prepare("
        SELECT
            oi.item_id,
            oi.order_id,
            oi.inventory_id,
            i.item_name,
            oi.quantity,
            COALESCE(NULLIF(i.capacity_units, 0), 1) AS capacity_units,
            oi.quantity * COALESCE(NULLIF(i.capacity_units, 0), 1) AS line_capacity_units
        FROM order_items oi
        JOIN inventory i ON oi.inventory_id = i.inventory_id
        WHERE oi.order_id = ?
        ORDER BY oi.item_id ASC
    ");
    if (!$stmt) {
        error_log('Unable to prepare order capacity breakdown: ' . $conn->error);
        return [];
    }

    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function fetch_order_capacity_units_map($conn, $order_ids) {
    if (!is_array($order_ids) || empty($order_ids)) {
        return [];
    }

    $clean_ids = [];
    foreach ($order_ids as $order_id) {
        $order_id = (int) $order_id;
        if ($order_id > 0) {
            $clean_ids[$order_id] = $order_id;
        }
    }

    if (empty($clean_ids)) {
        return [];
    }

    ensure_inventory_capacity_units_column($conn);

    $map = array_fill_keys(array_values($clean_ids), 0);
    $id_list = implode(',', array_values($clean_ids));
    $result = $conn->query("
        SELECT oi.order_id, COALESCE(SUM(oi.quantity * COALESCE(NULLIF(i.capacity_units, 0), 1)), 0) AS capacity_units
        FROM order_items oi
        JOIN inventory i ON oi.inventory_id = i.inventory_id
        WHERE oi.order_id IN ($id_list)
        GROUP BY oi.order_id
    ");

    if (!$result) {
        error_log('Unable to fetch order capacity unit map: ' . $conn->error);
        return $map;
    }

    while ($row = $result->fetch_assoc()) {
        $map[(int) $row['order_id']] = (int) $row['capacity_units'];
    }

    return $map;
}

function recalculate_delivery_batch_capacity_units($conn, $batch_id) {
    $batch_id = (int) $batch_id;
    if ($batch_id < 1) {
        return 0;
    }

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(capacity_units), 0) AS used_capacity_units
        FROM delivery_batch_items
        WHERE batch_id = ? AND item_status = 'active'
    ");
    if (!$stmt) {
        error_log('Unable to prepare batch capacity recalculation: ' . $conn->error);
        return 0;
    }

    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $used_capacity_units = (int) ($row['used_capacity_units'] ?? 0);
    $update = $conn->prepare("UPDATE delivery_batches SET used_capacity_units = ? WHERE batch_id = ?");
    if ($update) {
        $update->bind_param('ii', $used_capacity_units, $batch_id);
        $update->execute();
        $update->close();
    } else {
        error_log('Unable to update batch capacity units: ' . $conn->error);
    }

    return $used_capacity_units;
}

function order_exceeds_delivery_batch_capacity($conn, $order_id, $capacity_limit_units = null) {
    $capacity_limit_units = $capacity_limit_units === null
        ? delivery_batch_capacity_limit_units()
        : (int) $capacity_limit_units;

    return calculate_order_capacity_units($conn, $order_id) > $capacity_limit_units;
}

function fetch_delivery_batch_eligible_active_orders($conn) {
    $query = "
        SELECT
            o.order_id,
            o.order_date,
            o.delivery_date,
            o.delivery_address,
            o.delivery_barangay,
            o.delivery_city,
            o.delivery_province,
            c.full_name AS customer_name,
            c.phone AS customer_phone,
            area.zone_code,
            area.zone_name,
            area.zone_sort_order,
            order_items.item_summary,
            order_items.total_quantity,
            COALESCE(order_items.capacity_units, 0) AS capacity_units
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
        WHERE o.order_status = 'confirmed'
            AND (o.delivery_date IS NULL OR o.delivery_date = '0000-00-00' OR o.delivery_date <= CURDATE())
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
            COALESCE(area.zone_sort_order, 9999) ASC,
            COALESCE(area.zone_code, 'ZZZ') ASC,
            CASE WHEN o.delivery_date IS NULL OR o.delivery_date = '0000-00-00' THEN 1 ELSE 0 END ASC,
            o.delivery_date ASC,
            o.order_date ASC,
            o.order_id ASC
    ";

    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function delivery_batch_code_for_id($batch_date, $zone_code, $batch_id) {
    $date_part = preg_replace('/[^0-9]/', '', (string) $batch_date);
    $zone_part = strtoupper((string) $zone_code);
    $zone_part = preg_replace('/[^A-Z0-9]+/', '-', $zone_part);
    $zone_part = trim($zone_part, '-');
    if ($zone_part === '') {
        $zone_part = 'UNZONED';
    }

    return sprintf('BAT-%s-%s-%04d', $date_part, $zone_part, (int) $batch_id);
}

function create_delivery_draft_batch($conn, $batch_date, $zone_code, $zone_name, $batch_type, $orders, $created_by, $capacity_limit_units = null) {
    $capacity_limit_units = $capacity_limit_units === null
        ? delivery_batch_capacity_limit_units()
        : (int) $capacity_limit_units;

    if (empty($orders)) {
        return null;
    }

    $used_capacity_units = 0;
    foreach ($orders as $order) {
        $used_capacity_units += (int) ($order['capacity_units'] ?? 0);
    }

    $notes = $batch_type === 'underfilled'
        ? 'System-generated draft below full capacity. Admin may merge or edit before confirming.'
        : 'System-generated draft from active queue.';

    $stmt = $conn->prepare("
        INSERT INTO delivery_batches (
            batch_date,
            zone_code,
            zone_name,
            batch_type,
            capacity_limit_units,
            used_capacity_units,
            batch_status,
            created_by,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)
    ");
    if (!$stmt) {
        throw new Exception('Unable to prepare draft batch creation: ' . $conn->error);
    }

    $created_by = $created_by > 0 ? (int) $created_by : null;
    $stmt->bind_param('ssssiiis', $batch_date, $zone_code, $zone_name, $batch_type, $capacity_limit_units, $used_capacity_units, $created_by, $notes);
    if (!$stmt->execute()) {
        $message = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception('Unable to create draft batch: ' . $message);
    }
    $batch_id = (int) $stmt->insert_id;
    $stmt->close();

    $batch_code = delivery_batch_code_for_id($batch_date, $zone_code, $batch_id);
    $code_stmt = $conn->prepare("UPDATE delivery_batches SET batch_code = ? WHERE batch_id = ?");
    if (!$code_stmt) {
        throw new Exception('Unable to prepare draft batch code update: ' . $conn->error);
    }
    $code_stmt->bind_param('si', $batch_code, $batch_id);
    if (!$code_stmt->execute()) {
        $message = $code_stmt->error ?: $conn->error;
        $code_stmt->close();
        throw new Exception('Unable to update draft batch code: ' . $message);
    }
    $code_stmt->close();

    $item_stmt = $conn->prepare("
        INSERT INTO delivery_batch_items (batch_id, order_id, capacity_units, sort_order)
        VALUES (?, ?, ?, ?)
    ");
    if (!$item_stmt) {
        throw new Exception('Unable to prepare draft batch item creation: ' . $conn->error);
    }

    $sort_order = 1;
    foreach ($orders as $order) {
        $order_id = (int) ($order['order_id'] ?? 0);
        $capacity_units = (int) ($order['capacity_units'] ?? 0);
        $item_stmt->bind_param('iiii', $batch_id, $order_id, $capacity_units, $sort_order);
        if (!$item_stmt->execute()) {
            $message = $item_stmt->error ?: $conn->error;
            $item_stmt->close();
            throw new Exception('Unable to add order #' . $order_id . ' to draft batch: ' . $message);
        }
        $sort_order++;
    }
    $item_stmt->close();

    return [
        'batch_id' => $batch_id,
        'batch_code' => $batch_code,
        'zone_code' => $zone_code,
        'zone_name' => $zone_name,
        'batch_type' => $batch_type,
        'order_count' => count($orders),
        'used_capacity_units' => $used_capacity_units,
    ];
}

function generate_delivery_draft_batches($conn, $created_by, $batch_date = null) {
    $batch_date = $batch_date ?: date('Y-m-d');
    $capacity_limit_units = delivery_batch_capacity_limit_units();
    $orders = fetch_delivery_batch_eligible_active_orders($conn);
    $result = [
        'generated_batches' => 0,
        'batched_orders' => 0,
        'batched_units' => 0,
        'skipped_orders' => [],
        'batches' => [],
    ];

    $current_zone_key = null;
    $current_zone_code = '';
    $current_zone_name = '';
    $current_orders = [];
    $current_units = 0;

    $flush_batch = function () use (&$current_orders, &$current_units, &$current_zone_code, &$current_zone_name, &$result, $conn, $batch_date, $capacity_limit_units, $created_by) {
        if (empty($current_orders)) {
            return;
        }

        $batch_type = $current_units < $capacity_limit_units ? 'underfilled' : 'normal';
        $batch = create_delivery_draft_batch(
            $conn,
            $batch_date,
            $current_zone_code,
            $current_zone_name,
            $batch_type,
            $current_orders,
            (int) $created_by,
            $capacity_limit_units
        );

        if ($batch) {
            $result['generated_batches']++;
            $result['batched_orders'] += count($current_orders);
            $result['batched_units'] += $current_units;
            $result['batches'][] = $batch;
        }

        $current_orders = [];
        $current_units = 0;
    };

    foreach ($orders as $order) {
        $capacity_units = (int) ($order['capacity_units'] ?? 0);
        $order_id = (int) ($order['order_id'] ?? 0);
        $zone_code = (string) ($order['zone_code'] ?? '');
        $zone_name = (string) ($order['zone_name'] ?? '');

        if ($zone_code === '') {
            $zone_code = 'UNZONED';
            $zone_name = 'Unzoned delivery area';
        }
        if ($zone_name === '') {
            $zone_name = $zone_code;
        }

        $zone_key = $zone_code;
        if ($current_zone_key !== null && $zone_key !== $current_zone_key) {
            $flush_batch();
        }

        if ($capacity_units > $capacity_limit_units) {
            $result['skipped_orders'][] = [
                'order_id' => $order_id,
                'capacity_units' => $capacity_units,
                'capacity_limit_units' => $capacity_limit_units,
            ];
            $current_zone_key = $zone_key;
            $current_zone_code = $zone_code;
            $current_zone_name = $zone_name;
            continue;
        }

        if (!empty($current_orders) && ($current_units + $capacity_units) > $capacity_limit_units) {
            $flush_batch();
        }

        $current_zone_key = $zone_key;
        $current_zone_code = $zone_code;
        $current_zone_name = $zone_name;
        $current_orders[] = $order;
        $current_units += $capacity_units;

        if ($current_units === $capacity_limit_units) {
            $flush_batch();
        }
    }

    $flush_batch();

    return $result;
}

function fetch_delivery_draft_batches($conn) {
    $result = $conn->query("
        SELECT
            b.*,
            COUNT(bi.batch_item_id) AS order_count,
            GROUP_CONCAT(CONCAT('#', o.order_id, ' ', c.full_name, ' (', bi.capacity_units, 'u)') ORDER BY bi.sort_order ASC SEPARATOR '; ') AS order_summary
        FROM delivery_batches b
        LEFT JOIN delivery_batch_items bi ON b.batch_id = bi.batch_id AND bi.item_status = 'active'
        LEFT JOIN orders o ON bi.order_id = o.order_id
        LEFT JOIN users c ON o.customer_id = c.user_id
        WHERE b.batch_status = 'draft'
        GROUP BY b.batch_id
        ORDER BY b.batch_date ASC, b.zone_code ASC, b.batch_id ASC
    ");

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_delivery_batches_by_status($conn, $statuses) {
    if (!is_array($statuses) || empty($statuses)) {
        return [];
    }

    $allowed_statuses = ['draft', 'confirmed', 'assigned', 'in_transit', 'completed', 'cancelled'];
    $clean_statuses = [];
    foreach ($statuses as $status) {
        $status = (string) $status;
        if (in_array($status, $allowed_statuses, true)) {
            $clean_statuses[] = "'" . $conn->real_escape_string($status) . "'";
        }
    }

    if (empty($clean_statuses)) {
        return [];
    }

    $status_list = implode(',', $clean_statuses);
    $result = $conn->query("
        SELECT
            b.*,
            s.full_name AS staff_name,
            COUNT(bi.batch_item_id) AS order_count,
            GROUP_CONCAT(CONCAT('#', o.order_id, ' ', c.full_name, ' (', bi.capacity_units, 'u)') ORDER BY bi.sort_order ASC SEPARATOR '; ') AS order_summary
        FROM delivery_batches b
        LEFT JOIN users s ON s.user_id = b.staff_id
        LEFT JOIN delivery_batch_items bi ON b.batch_id = bi.batch_id AND bi.item_status = 'active'
        LEFT JOIN orders o ON bi.order_id = o.order_id
        LEFT JOIN users c ON o.customer_id = c.user_id
        WHERE b.batch_status IN ($status_list)
        GROUP BY b.batch_id
        ORDER BY b.batch_date ASC, b.zone_code ASC, b.batch_id ASC
    ");

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_delivery_confirmed_batches($conn) {
    return fetch_delivery_batches_by_status($conn, ['confirmed']);
}

function fetch_delivery_assigned_batches($conn) {
    return fetch_delivery_batches_by_status($conn, ['assigned', 'in_transit']);
}

function fetch_delivery_batch_items_map_by_status($conn, $statuses) {
    if (!is_array($statuses) || empty($statuses)) {
        return [];
    }

    $allowed_statuses = ['draft', 'confirmed', 'assigned', 'in_transit', 'completed', 'cancelled'];
    $clean_statuses = [];
    foreach ($statuses as $status) {
        $status = (string) $status;
        if (in_array($status, $allowed_statuses, true)) {
            $clean_statuses[] = "'" . $conn->real_escape_string($status) . "'";
        }
    }

    if (empty($clean_statuses)) {
        return [];
    }

    $status_list = implode(',', $clean_statuses);
    $result = $conn->query("
        SELECT
            bi.batch_item_id,
            bi.batch_id,
            bi.order_id,
            bi.capacity_units,
            bi.sort_order,
            o.delivery_date,
            o.delivery_address,
            o.delivery_barangay,
            o.delivery_city,
            o.delivery_province,
            o.order_status,
            c.full_name AS customer_name,
            c.phone AS customer_phone,
            d.delivery_status,
            d.delivered_at,
            d.delivery_notes,
            order_items.item_summary,
            order_items.total_quantity
        FROM delivery_batch_items bi
        JOIN delivery_batches b ON b.batch_id = bi.batch_id
        JOIN orders o ON o.order_id = bi.order_id
        JOIN users c ON c.user_id = o.customer_id
        LEFT JOIN deliveries d ON d.order_id = bi.order_id AND d.staff_id = b.staff_id
        LEFT JOIN (
            SELECT
                oi.order_id,
                GROUP_CONCAT(CONCAT(i.item_name, ' x', oi.quantity) ORDER BY i.item_name SEPARATOR ', ') AS item_summary,
                SUM(oi.quantity) AS total_quantity
            FROM order_items oi
            JOIN inventory i ON oi.inventory_id = i.inventory_id
            GROUP BY oi.order_id
        ) order_items ON o.order_id = order_items.order_id
        WHERE b.batch_status IN ($status_list) AND bi.item_status = 'active'
        ORDER BY bi.batch_id ASC, bi.sort_order ASC, bi.batch_item_id ASC
    ");

    $map = [];
    if (!$result) {
        return $map;
    }

    while ($row = $result->fetch_assoc()) {
        $batch_id = (int) ($row['batch_id'] ?? 0);
        if (!isset($map[$batch_id])) {
            $map[$batch_id] = [];
        }
        $map[$batch_id][] = $row;
    }

    return $map;
}

function fetch_delivery_draft_batch_items_map($conn) {
    return fetch_delivery_batch_items_map_by_status($conn, ['draft']);
}

function fetch_delivery_confirmed_batch_items_map($conn) {
    return fetch_delivery_batch_items_map_by_status($conn, ['confirmed']);
}

function fetch_delivery_assigned_batch_items_map($conn) {
    return fetch_delivery_batch_items_map_by_status($conn, ['assigned', 'in_transit']);
}

function fetch_delivery_draft_batch_for_update($conn, $batch_id) {
    $batch_id = (int) $batch_id;
    if ($batch_id < 1) {
        throw new Exception('Invalid draft batch.');
    }

    $stmt = $conn->prepare("SELECT * FROM delivery_batches WHERE batch_id = ? FOR UPDATE");
    if (!$stmt) {
        throw new Exception('Unable to prepare draft batch lookup: ' . $conn->error);
    }

    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $batch = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$batch) {
        throw new Exception('Draft batch was not found.');
    }

    if (($batch['batch_status'] ?? '') !== 'draft') {
        throw new Exception('Only draft batches can be edited here.');
    }

    return $batch;
}

function fetch_delivery_batch_for_assignment_update($conn, $batch_id) {
    $batch_id = (int) $batch_id;
    if ($batch_id < 1) {
        throw new Exception('Invalid batch.');
    }

    $stmt = $conn->prepare("SELECT * FROM delivery_batches WHERE batch_id = ? FOR UPDATE");
    if (!$stmt) {
        throw new Exception('Unable to prepare batch lookup: ' . $conn->error);
    }

    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $batch = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$batch) {
        throw new Exception('Batch was not found.');
    }

    if (($batch['batch_status'] ?? '') !== 'confirmed') {
        throw new Exception('Only confirmed batches can be assigned.');
    }

    return $batch;
}

function fetch_active_staff_for_update($conn, $staff_id) {
    $staff_id = (int) $staff_id;
    if ($staff_id < 1) {
        throw new Exception('Please choose a rider.');
    }

    $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE user_id = ? AND role = 'staff' AND status = 'active' LIMIT 1 FOR UPDATE");
    if (!$stmt) {
        throw new Exception('Unable to prepare staff lookup: ' . $conn->error);
    }

    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$staff) {
        throw new Exception('Selected rider is not active.');
    }

    return $staff;
}

function count_active_delivery_batch_items($conn, $batch_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS item_count FROM delivery_batch_items WHERE batch_id = ? AND item_status = 'active'");
    if (!$stmt) {
        throw new Exception('Unable to count draft batch orders: ' . $conn->error);
    }

    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['item_count'] ?? 0);
}

function update_delivery_batch_type_from_capacity($conn, $batch_id, $preserve_merged = true) {
    $stmt = $conn->prepare("SELECT batch_type, used_capacity_units, capacity_limit_units FROM delivery_batches WHERE batch_id = ?");
    if (!$stmt) {
        throw new Exception('Unable to prepare draft batch type lookup: ' . $conn->error);
    }

    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $batch = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$batch) {
        throw new Exception('Draft batch was not found.');
    }

    if ($preserve_merged && ($batch['batch_type'] ?? '') === 'merged') {
        return 'merged';
    }

    $used_capacity_units = (int) ($batch['used_capacity_units'] ?? 0);
    $capacity_limit_units = (int) ($batch['capacity_limit_units'] ?? delivery_batch_capacity_limit_units());
    $batch_type = $used_capacity_units < $capacity_limit_units ? 'underfilled' : 'normal';

    $update = $conn->prepare("UPDATE delivery_batches SET batch_type = ? WHERE batch_id = ?");
    if (!$update) {
        throw new Exception('Unable to prepare draft batch type update: ' . $conn->error);
    }

    $update->bind_param('si', $batch_type, $batch_id);
    if (!$update->execute()) {
        $message = $update->error ?: $conn->error;
        $update->close();
        throw new Exception('Unable to update draft batch type: ' . $message);
    }
    $update->close();

    return $batch_type;
}

function confirm_delivery_draft_batch($conn, $batch_id, $admin_id) {
    $batch = fetch_delivery_draft_batch_for_update($conn, $batch_id);
    $batch_id = (int) ($batch['batch_id'] ?? 0);
    $active_items = count_active_delivery_batch_items($conn, $batch_id);

    if ($active_items < 1) {
        throw new Exception('Cannot confirm an empty draft batch.');
    }

    if ((int) ($batch['used_capacity_units'] ?? 0) > (int) ($batch['capacity_limit_units'] ?? delivery_batch_capacity_limit_units())) {
        throw new Exception('Cannot confirm a batch above its capacity limit.');
    }

    $stmt = $conn->prepare("UPDATE delivery_batches SET batch_status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE batch_id = ?");
    if (!$stmt) {
        throw new Exception('Unable to prepare draft batch confirmation: ' . $conn->error);
    }

    $admin_id = (int) $admin_id;
    $stmt->bind_param('ii', $admin_id, $batch_id);
    if (!$stmt->execute()) {
        $message = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception('Unable to confirm draft batch: ' . $message);
    }
    $stmt->close();

    return $batch;
}

function cancel_delivery_draft_batch($conn, $batch_id, $admin_id = null) {
    $batch = fetch_delivery_draft_batch_for_update($conn, $batch_id);
    $batch_id = (int) ($batch['batch_id'] ?? 0);

    $stmt = $conn->prepare("UPDATE delivery_batches SET batch_status = 'cancelled', cancelled_at = NOW(), notes = CONCAT(COALESCE(notes, ''), '\nCancelled during admin review.') WHERE batch_id = ?");
    if (!$stmt) {
        throw new Exception('Unable to prepare draft batch cancellation: ' . $conn->error);
    }

    $stmt->bind_param('i', $batch_id);
    if (!$stmt->execute()) {
        $message = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception('Unable to cancel draft batch: ' . $message);
    }
    $stmt->close();

    return $batch;
}

function remove_order_from_delivery_draft_batch($conn, $batch_id, $order_id) {
    $batch = fetch_delivery_draft_batch_for_update($conn, $batch_id);
    $batch_id = (int) ($batch['batch_id'] ?? 0);
    $order_id = (int) $order_id;
    if ($order_id < 1) {
        throw new Exception('Invalid order.');
    }

    $stmt = $conn->prepare("UPDATE delivery_batch_items SET item_status = 'removed' WHERE batch_id = ? AND order_id = ? AND item_status = 'active'");
    if (!$stmt) {
        throw new Exception('Unable to prepare draft batch item removal: ' . $conn->error);
    }

    $stmt->bind_param('ii', $batch_id, $order_id);
    if (!$stmt->execute()) {
        $message = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception('Unable to remove order from draft batch: ' . $message);
    }
    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    if ($affected_rows < 1) {
        throw new Exception('Order was not active in this draft batch.');
    }

    $used_capacity_units = recalculate_delivery_batch_capacity_units($conn, $batch_id);
    if (count_active_delivery_batch_items($conn, $batch_id) < 1) {
        $cancel = $conn->prepare("UPDATE delivery_batches SET batch_status = 'cancelled', cancelled_at = NOW(), notes = CONCAT(COALESCE(notes, ''), '\nCancelled because all draft orders were removed.') WHERE batch_id = ?");
        if ($cancel) {
            $cancel->bind_param('i', $batch_id);
            $cancel->execute();
            $cancel->close();
        }
        return 0;
    }

    update_delivery_batch_type_from_capacity($conn, $batch_id);
    return $used_capacity_units;
}

function fetch_active_order_for_batch_addition($conn, $order_id) {
    $order_id = (int) $order_id;
    if ($order_id < 1) {
        throw new Exception('Invalid order.');
    }

    $stmt = $conn->prepare("
        SELECT
            o.order_id,
            o.delivery_date,
            o.delivery_address,
            o.delivery_barangay,
            o.delivery_city,
            o.delivery_province,
            area.zone_code,
            area.zone_name,
            order_items.capacity_units
        FROM orders o
        LEFT JOIN delivery_service_areas area
            ON area.province = o.delivery_province
            AND area.city = o.delivery_city
            AND area.barangay = o.delivery_barangay
            AND area.is_active = 1
        LEFT JOIN (
            SELECT oi.order_id, SUM(oi.quantity * COALESCE(NULLIF(i.capacity_units, 0), 1)) AS capacity_units
            FROM order_items oi
            JOIN inventory i ON oi.inventory_id = i.inventory_id
            GROUP BY oi.order_id
        ) order_items ON o.order_id = order_items.order_id
        WHERE o.order_id = ?
            AND o.order_status = 'confirmed'
            AND (o.delivery_date IS NULL OR o.delivery_date = '0000-00-00' OR o.delivery_date <= CURDATE())
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
        LIMIT 1
        FOR UPDATE
    ");
    if (!$stmt) {
        throw new Exception('Unable to prepare active order lookup: ' . $conn->error);
    }

    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception('Order is not available in the active queue.');
    }

    return $order;
}

function add_order_to_delivery_draft_batch($conn, $batch_id, $order_id) {
    $batch = fetch_delivery_draft_batch_for_update($conn, $batch_id);
    $order = fetch_active_order_for_batch_addition($conn, $order_id);

    $batch_id = (int) ($batch['batch_id'] ?? 0);
    $order_id = (int) ($order['order_id'] ?? 0);
    $capacity_units = (int) ($order['capacity_units'] ?? 0);
    $capacity_limit_units = (int) ($batch['capacity_limit_units'] ?? delivery_batch_capacity_limit_units());
    $current_units = (int) ($batch['used_capacity_units'] ?? 0);

    if ($capacity_units < 1) {
        throw new Exception('Order has no capacity units to add.');
    }

    if ($capacity_units > $capacity_limit_units) {
        throw new Exception('Order exceeds the batch capacity limit.');
    }

    if (($current_units + $capacity_units) > $capacity_limit_units) {
        throw new Exception('Order does not fit in the remaining batch capacity.');
    }

    $sort_result = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort_order FROM delivery_batch_items WHERE batch_id = ?");
    if (!$sort_result) {
        throw new Exception('Unable to prepare draft batch sort lookup: ' . $conn->error);
    }
    $sort_result->bind_param('i', $batch_id);
    $sort_result->execute();
    $sort_row = $sort_result->get_result()->fetch_assoc();
    $sort_result->close();
    $sort_order = (int) ($sort_row['next_sort_order'] ?? 1);

    $stmt = $conn->prepare("
        INSERT INTO delivery_batch_items (batch_id, order_id, capacity_units, sort_order, item_status)
        VALUES (?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            capacity_units = VALUES(capacity_units),
            sort_order = VALUES(sort_order),
            item_status = 'active'
    ");
    if (!$stmt) {
        throw new Exception('Unable to prepare draft batch item add: ' . $conn->error);
    }

    $stmt->bind_param('iiii', $batch_id, $order_id, $capacity_units, $sort_order);
    if (!$stmt->execute()) {
        $message = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception('Unable to add order to draft batch: ' . $message);
    }
    $stmt->close();

    $used_capacity_units = recalculate_delivery_batch_capacity_units($conn, $batch_id);
    $batch_zone_code = (string) ($batch['zone_code'] ?? '');
    $order_zone_code = (string) ($order['zone_code'] ?? '');
    if ($order_zone_code !== '' && $batch_zone_code !== '' && $order_zone_code !== $batch_zone_code) {
        $type = 'merged';
        $update = $conn->prepare("UPDATE delivery_batches SET batch_type = ? WHERE batch_id = ?");
        if (!$update) {
            throw new Exception('Unable to prepare draft batch type update: ' . $conn->error);
        }
        $update->bind_param('si', $type, $batch_id);
        if (!$update->execute()) {
            $message = $update->error ?: $conn->error;
            $update->close();
            throw new Exception('Unable to update draft batch type: ' . $message);
        }
        $update->close();
    } else {
        update_delivery_batch_type_from_capacity($conn, $batch_id);
    }

    return $used_capacity_units;
}

function assign_delivery_batch_to_staff($conn, $batch_id, $staff_id, $assigned_by) {
    $batch = fetch_delivery_batch_for_assignment_update($conn, $batch_id);
    $staff = fetch_active_staff_for_update($conn, $staff_id);

    $batch_id = (int) ($batch['batch_id'] ?? 0);
    $staff_id = (int) ($staff['user_id'] ?? 0);
    $assigned_by = (int) $assigned_by;

    $items_stmt = $conn->prepare("
        SELECT
            bi.order_id,
            bi.capacity_units,
            o.order_status,
            d.delivery_id,
            d.delivery_status
        FROM delivery_batch_items bi
        JOIN orders o ON o.order_id = bi.order_id
        LEFT JOIN deliveries d ON d.order_id = bi.order_id
        WHERE bi.batch_id = ? AND bi.item_status = 'active'
        ORDER BY bi.sort_order ASC, bi.batch_item_id ASC
        FOR UPDATE
    ");
    if (!$items_stmt) {
        throw new Exception('Unable to prepare batch order lookup: ' . $conn->error);
    }

    $items_stmt->bind_param('i', $batch_id);
    $items_stmt->execute();
    $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();

    if (empty($items)) {
        throw new Exception('Cannot assign an empty batch.');
    }

    foreach ($items as $item) {
        $order_id = (int) ($item['order_id'] ?? 0);
        $order_status = (string) ($item['order_status'] ?? '');
        $delivery_id = (int) ($item['delivery_id'] ?? 0);
        $delivery_status = (string) ($item['delivery_status'] ?? '');

        if (in_array($order_status, ['delivered', 'cancelled', 'returned'], true)) {
            throw new Exception("Order #$order_id is already final and cannot be assigned.");
        }

        if ($delivery_id > 0 && $delivery_status === 'delivered') {
            throw new Exception("Order #$order_id has a closed delivery record and cannot be assigned in this batch.");
        }
    }

    foreach ($items as $item) {
        $order_id = (int) ($item['order_id'] ?? 0);
        $delivery_id = (int) ($item['delivery_id'] ?? 0);

        if ($delivery_id > 0) {
            $stmt = $conn->prepare("
                UPDATE deliveries
                SET staff_id = ?, assigned_by = ?, assigned_at = NOW(), delivered_at = NULL, delivery_status = 'assigned', proof_of_delivery = NULL
                WHERE delivery_id = ?
            ");
            if (!$stmt) {
                throw new Exception('Unable to prepare delivery reassignment: ' . $conn->error);
            }
            $stmt->bind_param('iii', $staff_id, $assigned_by, $delivery_id);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO deliveries (order_id, staff_id, assigned_by, delivery_status)
                VALUES (?, ?, ?, 'assigned')
            ");
            if (!$stmt) {
                throw new Exception('Unable to prepare delivery assignment: ' . $conn->error);
            }
            $stmt->bind_param('iii', $order_id, $staff_id, $assigned_by);
        }

        if (!$stmt->execute()) {
            $message = $stmt->error ?: $conn->error;
            $stmt->close();
            throw new Exception("Unable to assign order #$order_id: " . $message);
        }
        $stmt->close();

        $order_stmt = $conn->prepare("UPDATE orders SET order_status = 'processing', payment_status = 'pending' WHERE order_id = ? AND order_status = 'confirmed'");
        if (!$order_stmt) {
            throw new Exception('Unable to prepare order processing update: ' . $conn->error);
        }
        $order_stmt->bind_param('i', $order_id);
        if (!$order_stmt->execute()) {
            $message = $order_stmt->error ?: $conn->error;
            $order_stmt->close();
            throw new Exception("Unable to update order #$order_id: " . $message);
        }
        $order_stmt->close();
    }

    $batch_stmt = $conn->prepare("
        UPDATE delivery_batches
        SET batch_status = 'assigned', staff_id = ?, assigned_by = ?, assigned_at = NOW()
        WHERE batch_id = ?
    ");
    if (!$batch_stmt) {
        throw new Exception('Unable to prepare batch assignment update: ' . $conn->error);
    }

    $batch_stmt->bind_param('iii', $staff_id, $assigned_by, $batch_id);
    if (!$batch_stmt->execute()) {
        $message = $batch_stmt->error ?: $conn->error;
        $batch_stmt->close();
        throw new Exception('Unable to assign batch: ' . $message);
    }
    $batch_stmt->close();

    return [
        'batch_id' => $batch_id,
        'batch_code' => (string) ($batch['batch_code'] ?? ''),
        'staff_id' => $staff_id,
        'staff_name' => (string) ($staff['full_name'] ?? ''),
        'order_count' => count($items),
    ];
}

function fetch_delivery_batch_for_staff_update($conn, $batch_id, $staff_id) {
    $batch_id = (int) $batch_id;
    $staff_id = (int) $staff_id;
    if ($batch_id < 1 || $staff_id < 1) {
        throw new Exception('Invalid delivery batch.');
    }

    $stmt = $conn->prepare("SELECT * FROM delivery_batches WHERE batch_id = ? AND staff_id = ? FOR UPDATE");
    if (!$stmt) {
        throw new Exception('Unable to prepare staff batch lookup: ' . $conn->error);
    }

    $stmt->bind_param('ii', $batch_id, $staff_id);
    $stmt->execute();
    $batch = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$batch) {
        throw new Exception('Batch was not found for this rider.');
    }

    return $batch;
}

function start_delivery_batch($conn, $batch_id, $staff_id) {
    $batch = fetch_delivery_batch_for_staff_update($conn, $batch_id, $staff_id);
    $batch_id = (int) ($batch['batch_id'] ?? 0);
    $staff_id = (int) $staff_id;

    if (($batch['batch_status'] ?? '') !== 'assigned') {
        throw new Exception('Only assigned batches can be started.');
    }

    $items_stmt = $conn->prepare("
        SELECT
            bi.order_id,
            d.delivery_id,
            d.delivery_status
        FROM delivery_batch_items bi
        JOIN deliveries d ON d.order_id = bi.order_id
        WHERE bi.batch_id = ?
            AND bi.item_status = 'active'
            AND d.staff_id = ?
        ORDER BY bi.sort_order ASC, bi.batch_item_id ASC
        FOR UPDATE
    ");
    if (!$items_stmt) {
        throw new Exception('Unable to prepare batch start lookup: ' . $conn->error);
    }

    $items_stmt->bind_param('ii', $batch_id, $staff_id);
    $items_stmt->execute();
    $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();

    if (empty($items)) {
        throw new Exception('This batch has no assigned deliveries.');
    }

    if (count($items) !== count_active_delivery_batch_items($conn, $batch_id)) {
        throw new Exception('This batch has orders that are not fully assigned to this rider.');
    }

    foreach ($items as $item) {
        $order_id = (int) ($item['order_id'] ?? 0);
        $delivery_status = (string) ($item['delivery_status'] ?? '');
        if ($delivery_status !== 'assigned') {
            throw new Exception("Order #$order_id is not ready to start.");
        }
    }

    $delivery_stmt = $conn->prepare("UPDATE deliveries SET delivery_status = 'in_transit' WHERE delivery_id = ? AND staff_id = ? AND delivery_status = 'assigned'");
    if (!$delivery_stmt) {
        throw new Exception('Unable to prepare delivery start update: ' . $conn->error);
    }

    $order_stmt = $conn->prepare("UPDATE orders SET order_status = 'out_for_delivery', payment_status = 'pending' WHERE order_id = ? AND order_status NOT IN ('delivered', 'cancelled', 'returned')");
    if (!$order_stmt) {
        $delivery_stmt->close();
        throw new Exception('Unable to prepare order start update: ' . $conn->error);
    }

    foreach ($items as $item) {
        $delivery_id = (int) ($item['delivery_id'] ?? 0);
        $order_id = (int) ($item['order_id'] ?? 0);

        $delivery_stmt->bind_param('ii', $delivery_id, $staff_id);
        if (!$delivery_stmt->execute()) {
            $message = $delivery_stmt->error ?: $conn->error;
            $delivery_stmt->close();
            $order_stmt->close();
            throw new Exception("Unable to start delivery for order #$order_id: " . $message);
        }

        $order_stmt->bind_param('i', $order_id);
        if (!$order_stmt->execute()) {
            $message = $order_stmt->error ?: $conn->error;
            $delivery_stmt->close();
            $order_stmt->close();
            throw new Exception("Unable to mark order #$order_id out for delivery: " . $message);
        }
    }

    $delivery_stmt->close();
    $order_stmt->close();

    $batch_stmt = $conn->prepare("
        UPDATE delivery_batches
        SET batch_status = 'in_transit', started_at = COALESCE(started_at, NOW())
        WHERE batch_id = ? AND staff_id = ? AND batch_status = 'assigned'
    ");
    if (!$batch_stmt) {
        throw new Exception('Unable to prepare batch start update: ' . $conn->error);
    }

    $batch_stmt->bind_param('ii', $batch_id, $staff_id);
    if (!$batch_stmt->execute()) {
        $message = $batch_stmt->error ?: $conn->error;
        $batch_stmt->close();
        throw new Exception('Unable to start batch: ' . $message);
    }
    $batch_stmt->close();

    return [
        'batch_id' => $batch_id,
        'batch_code' => (string) ($batch['batch_code'] ?? ''),
        'order_count' => count($items),
    ];
}

function refresh_delivery_batch_completion($conn, $batch_id) {
    $batch_id = (int) $batch_id;
    if ($batch_id < 1) {
        return null;
    }

    $batch_stmt = $conn->prepare("SELECT batch_id, batch_status FROM delivery_batches WHERE batch_id = ? FOR UPDATE");
    if (!$batch_stmt) {
        throw new Exception('Unable to prepare batch completion lookup: ' . $conn->error);
    }

    $batch_stmt->bind_param('i', $batch_id);
    $batch_stmt->execute();
    $batch = $batch_stmt->get_result()->fetch_assoc();
    $batch_stmt->close();

    if (!$batch || !in_array(($batch['batch_status'] ?? ''), ['assigned', 'in_transit'], true)) {
        return $batch;
    }

    $summary_stmt = $conn->prepare("
        SELECT
            COUNT(*) AS order_count,
            SUM(CASE WHEN d.delivery_status IN ('delivered', 'failed', 'returned', 'cancelled') THEN 1 ELSE 0 END) AS closed_count
        FROM delivery_batch_items bi
        LEFT JOIN deliveries d ON d.order_id = bi.order_id
        WHERE bi.batch_id = ? AND bi.item_status = 'active'
    ");
    if (!$summary_stmt) {
        throw new Exception('Unable to prepare batch completion summary: ' . $conn->error);
    }

    $summary_stmt->bind_param('i', $batch_id);
    $summary_stmt->execute();
    $summary = $summary_stmt->get_result()->fetch_assoc();
    $summary_stmt->close();

    $order_count = (int) ($summary['order_count'] ?? 0);
    $closed_count = (int) ($summary['closed_count'] ?? 0);

    if ($order_count > 0 && $closed_count >= $order_count) {
        $update = $conn->prepare("UPDATE delivery_batches SET batch_status = 'completed', completed_at = COALESCE(completed_at, NOW()) WHERE batch_id = ?");
    } elseif ($closed_count > 0 && ($batch['batch_status'] ?? '') === 'assigned') {
        $update = $conn->prepare("UPDATE delivery_batches SET batch_status = 'in_transit', started_at = COALESCE(started_at, NOW()) WHERE batch_id = ?");
    } else {
        return $batch;
    }

    if (!$update) {
        throw new Exception('Unable to prepare batch completion update: ' . $conn->error);
    }

    $update->bind_param('i', $batch_id);
    if (!$update->execute()) {
        $message = $update->error ?: $conn->error;
        $update->close();
        throw new Exception('Unable to refresh batch completion: ' . $message);
    }
    $update->close();

    return [
        'batch_id' => $batch_id,
        'order_count' => $order_count,
        'closed_count' => $closed_count,
        'batch_status' => $order_count > 0 && $closed_count >= $order_count ? 'completed' : 'in_transit',
    ];
}

function refresh_delivery_batch_completion_for_delivery($conn, $delivery_id) {
    $delivery_id = (int) $delivery_id;
    if ($delivery_id < 1) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT b.batch_id
        FROM deliveries d
        JOIN delivery_batch_items bi ON bi.order_id = d.order_id AND bi.item_status = 'active'
        JOIN delivery_batches b ON b.batch_id = bi.batch_id
        WHERE d.delivery_id = ?
            AND b.batch_status IN ('assigned', 'in_transit')
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Unable to prepare delivery batch lookup: ' . $conn->error);
    }

    $stmt->bind_param('i', $delivery_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return refresh_delivery_batch_completion($conn, (int) ($row['batch_id'] ?? 0));
}

function delivery_batch_status_sql_list($conn, $statuses) {
    $allowed_statuses = ['draft', 'confirmed', 'assigned', 'in_transit', 'completed', 'cancelled'];
    $clean_statuses = [];
    foreach ((array) $statuses as $status) {
        $status = (string) $status;
        if (in_array($status, $allowed_statuses, true)) {
            $clean_statuses[] = "'" . $conn->real_escape_string($status) . "'";
        }
    }

    return implode(',', $clean_statuses);
}

function fetch_staff_delivery_batches($conn, $staff_id, $statuses = ['assigned', 'in_transit']) {
    $staff_id = (int) $staff_id;
    $status_list = delivery_batch_status_sql_list($conn, $statuses);
    if ($staff_id < 1 || $status_list === '') {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT
            b.*,
            COUNT(bi.batch_item_id) AS order_count,
            SUM(CASE WHEN d.delivery_status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN d.delivery_status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
            SUM(CASE WHEN d.delivery_status = 'returned' THEN 1 ELSE 0 END) AS returned_count,
            SUM(CASE WHEN d.delivery_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
            SUM(CASE WHEN d.delivery_status IN ('delivered', 'failed', 'returned', 'cancelled') THEN 1 ELSE 0 END) AS closed_count
        FROM delivery_batches b
        LEFT JOIN delivery_batch_items bi ON bi.batch_id = b.batch_id AND bi.item_status = 'active'
        LEFT JOIN deliveries d ON d.order_id = bi.order_id AND d.staff_id = b.staff_id
        WHERE b.staff_id = ? AND b.batch_status IN ($status_list)
        GROUP BY b.batch_id
        ORDER BY
            CASE b.batch_status WHEN 'in_transit' THEN 1 WHEN 'assigned' THEN 2 ELSE 3 END ASC,
            b.batch_date ASC,
            b.zone_code ASC,
            b.batch_id ASC
    ");
    if (!$stmt) {
        error_log('Unable to prepare staff batch list: ' . $conn->error);
        return [];
    }

    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function fetch_staff_delivery_batch_items_map($conn, $staff_id, $statuses = ['assigned', 'in_transit']) {
    $staff_id = (int) $staff_id;
    $status_list = delivery_batch_status_sql_list($conn, $statuses);
    if ($staff_id < 1 || $status_list === '') {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT
            bi.batch_item_id,
            bi.batch_id,
            bi.order_id,
            bi.capacity_units,
            bi.sort_order,
            o.delivery_date,
            o.delivery_address,
            o.contact_number,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            o.notes AS order_notes,
            o.order_status,
            c.full_name AS customer_name,
            c.phone AS customer_phone,
            d.delivery_id,
            d.delivery_status,
            d.delivered_at,
            d.delivery_notes,
            order_items.item_summary,
            order_items.total_quantity
        FROM delivery_batch_items bi
        JOIN delivery_batches b ON b.batch_id = bi.batch_id
        JOIN orders o ON o.order_id = bi.order_id
        JOIN users c ON c.user_id = o.customer_id
        LEFT JOIN deliveries d ON d.order_id = bi.order_id AND d.staff_id = b.staff_id
        LEFT JOIN (
            SELECT
                oi.order_id,
                GROUP_CONCAT(CONCAT(i.item_name, ' x', oi.quantity) ORDER BY i.item_name SEPARATOR ', ') AS item_summary,
                SUM(oi.quantity) AS total_quantity
            FROM order_items oi
            JOIN inventory i ON oi.inventory_id = i.inventory_id
            GROUP BY oi.order_id
        ) order_items ON o.order_id = order_items.order_id
        WHERE b.staff_id = ?
            AND b.batch_status IN ($status_list)
            AND bi.item_status = 'active'
        ORDER BY bi.batch_id ASC, bi.sort_order ASC, bi.batch_item_id ASC
    ");
    if (!$stmt) {
        error_log('Unable to prepare staff batch items: ' . $conn->error);
        return [];
    }

    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    while ($row = $result->fetch_assoc()) {
        $batch_id = (int) ($row['batch_id'] ?? 0);
        if (!isset($map[$batch_id])) {
            $map[$batch_id] = [];
        }
        $map[$batch_id][] = $row;
    }
    $stmt->close();

    return $map;
}
