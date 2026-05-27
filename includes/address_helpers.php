<?php

function delivery_service_area_seed_data() {
    return [
        'Pangasinan' => [
            'Basista' => [
                'Bayoyong' => 'BAS-01',
                'Anambongan' => 'BAS-01',
                'Mapolopolo' => 'BAS-01',
                'Dumpay' => 'BAS-01',
                'Palma' => 'BAS-01',
                'Navatat' => 'BAS-01',
                'Poblacion' => 'BAS-01',
                'Pasibi East' => 'BAS-01',
                'Pasibi West' => 'BAS-01',
                'Bituag' => 'BAS-01',
                'Osmena Sr.' => 'BAS-02',
                'Obong' => 'BAS-02',
                'Malimpec East' => 'BAS-02',
                'Nalneran' => 'BAS-02',
                'Patacbo' => 'BAS-02',
                'Cabeldatan' => 'BAS-02',
            ],
            'Urbiztondo' => [
                'Malaca' => 'URB-01',
                'Batangcaoa' => 'URB-01',
                'Camambugan' => 'URB-01',
                'Poblacion' => 'URB-01',
                'Real' => 'URB-01',
                'Pisuac' => 'URB-01',
                'Angatel' => 'URB-01',
                'Salavante' => 'URB-01',
                'Sawat' => 'URB-01',
            ],
            'San Carlos City' => [
                'Turac' => 'SCC-01',
                'Tarectec' => 'SCC-01',
                'Cobol' => 'SCC-01',
                'Payapa' => 'SCC-01',
                'Bacnar' => 'SCC-02',
                'Calobaoan' => 'SCC-02',
                'Bolosan' => 'SCC-02',
                'Tebag' => 'SCC-02',
                'Abanon' => 'SCC-02',
                'Malacanang' => 'SCC-02',
                'Mestizo Norte' => 'SCC-02',
                'Maliwara' => 'SCC-02',
            ],
        ],
    ];
}

function delivery_zone_seed_data() {
    return [
        'BAS-01' => [
            'zone_name' => 'Bayoyong Core / Fastest Everyday Route',
            'zone_sort_order' => 10,
        ],
        'BAS-02' => [
            'zone_name' => 'East / Southeast Basista Route',
            'zone_sort_order' => 20,
        ],
        'URB-01' => [
            'zone_name' => 'Urbiztondo Side Route',
            'zone_sort_order' => 30,
        ],
        'SCC-01' => [
            'zone_name' => 'San Carlos North Route',
            'zone_sort_order' => 40,
        ],
        'SCC-02' => [
            'zone_name' => 'San Carlos West / Northwest Route',
            'zone_sort_order' => 50,
        ],
    ];
}

function ensure_column_exists($conn, $table, $column, $definition) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($table === '' || $column === '') {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    if (!$conn->query("ALTER TABLE `$table` ADD COLUMN $definition")) {
        error_log("Failed to add $table.$column column: " . $conn->error);
    }
}

function ensure_index_exists($conn, $table, $index, $definition) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $index = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $index);
    if ($table === '' || $index === '') {
        return;
    }

    $result = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    if (!$conn->query("ALTER TABLE `$table` ADD $definition")) {
        error_log("Failed to add $table.$index index: " . $conn->error);
    }
}

function ensure_delivery_service_area_schema($conn, $force_seed = false) {
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

    $conn->query("
        CREATE TABLE IF NOT EXISTS delivery_service_areas (
            area_id INT(11) NOT NULL AUTO_INCREMENT,
            province VARCHAR(120) NOT NULL,
            city VARCHAR(120) NOT NULL,
            barangay VARCHAR(120) NOT NULL,
            zone_code VARCHAR(40) DEFAULT NULL,
            zone_name VARCHAR(160) DEFAULT NULL,
            zone_sort_order INT(11) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (area_id),
            UNIQUE KEY uniq_delivery_service_area (province, city, barangay),
            KEY idx_delivery_service_area_city (province, city),
            KEY idx_delivery_service_area_barangay (barangay),
            KEY idx_delivery_service_area_zone (zone_code, zone_sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    ensure_column_exists($conn, 'delivery_service_areas', 'zone_code', "`zone_code` VARCHAR(40) DEFAULT NULL AFTER `barangay`");
    ensure_column_exists($conn, 'delivery_service_areas', 'zone_name', "`zone_name` VARCHAR(160) DEFAULT NULL AFTER `zone_code`");
    ensure_column_exists($conn, 'delivery_service_areas', 'zone_sort_order', "`zone_sort_order` INT(11) NOT NULL DEFAULT 0 AFTER `zone_name`");
    ensure_index_exists($conn, 'delivery_service_areas', 'idx_delivery_service_area_zone', "KEY `idx_delivery_service_area_zone` (`zone_code`, `zone_sort_order`)");

    ensure_column_exists($conn, 'customer_delivery_addresses', 'street_address', "`street_address` TEXT DEFAULT NULL AFTER `address`");
    ensure_column_exists($conn, 'customer_delivery_addresses', 'barangay', "`barangay` VARCHAR(120) DEFAULT NULL AFTER `street_address`");
    ensure_column_exists($conn, 'customer_delivery_addresses', 'city', "`city` VARCHAR(120) DEFAULT NULL AFTER `barangay`");
    ensure_column_exists($conn, 'customer_delivery_addresses', 'province', "`province` VARCHAR(120) DEFAULT NULL AFTER `city`");
    ensure_column_exists($conn, 'customer_delivery_addresses', 'service_area_id', "`service_area_id` INT(11) DEFAULT NULL AFTER `province`");

    ensure_column_exists($conn, 'orders', 'delivery_street', "`delivery_street` TEXT DEFAULT NULL AFTER `delivery_address`");
    ensure_column_exists($conn, 'orders', 'delivery_barangay', "`delivery_barangay` VARCHAR(120) DEFAULT NULL AFTER `delivery_street`");
    ensure_column_exists($conn, 'orders', 'delivery_city', "`delivery_city` VARCHAR(120) DEFAULT NULL AFTER `delivery_barangay`");
    ensure_column_exists($conn, 'orders', 'delivery_province', "`delivery_province` VARCHAR(120) DEFAULT NULL AFTER `delivery_city`");

    if ($force_seed || delivery_service_area_count($conn) === 0) {
        seed_delivery_service_areas($conn);
    }
}

function delivery_service_area_count($conn) {
    $result = $conn->query("SELECT COUNT(*) AS count FROM delivery_service_areas");
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int) ($row['count'] ?? 0);
}

function seed_delivery_service_areas($conn) {
    $stmt = $conn->prepare("
        INSERT INTO delivery_service_areas (province, city, barangay, zone_code, zone_name, zone_sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            zone_code = VALUES(zone_code),
            zone_name = VALUES(zone_name),
            zone_sort_order = VALUES(zone_sort_order),
            is_active = 1
    ");
    if (!$stmt) {
        error_log('Unable to prepare delivery service area seed: ' . $conn->error);
        return;
    }

    $zones = delivery_zone_seed_data();
    foreach (delivery_service_area_seed_data() as $province => $cities) {
        foreach ($cities as $city => $barangays) {
            foreach ($barangays as $barangay => $zone_code) {
                $zone = $zones[$zone_code] ?? [];
                $zone_name = (string) ($zone['zone_name'] ?? $zone_code);
                $zone_sort_order = (int) ($zone['zone_sort_order'] ?? 0);
                $stmt->bind_param('sssssi', $province, $city, $barangay, $zone_code, $zone_name, $zone_sort_order);
                $stmt->execute();
            }
        }
    }

    $stmt->close();
}

function fetch_delivery_service_areas($conn) {
    $result = $conn->query("SELECT area_id, province, city, barangay, zone_code, zone_name, zone_sort_order FROM delivery_service_areas WHERE is_active = 1 ORDER BY province ASC, city ASC, zone_sort_order ASC, barangay ASC");
    if (!$result) {
        ensure_delivery_service_area_schema($conn, true);
        $result = $conn->query("SELECT area_id, province, city, barangay, zone_code, zone_name, zone_sort_order FROM delivery_service_areas WHERE is_active = 1 ORDER BY province ASC, city ASC, zone_sort_order ASC, barangay ASC");
    }

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function find_delivery_service_area($conn, $province, $city, $barangay) {
    $stmt = $conn->prepare("SELECT area_id, province, city, barangay, zone_code, zone_name, zone_sort_order FROM delivery_service_areas WHERE province = ? AND city = ? AND barangay = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('sss', $province, $city, $barangay);
    $stmt->execute();
    $area = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $area ?: null;
}

function delivery_address_from_post($prefix = '') {
    return [
        'street_address' => trim($_POST[$prefix . 'street_address'] ?? ''),
        'barangay' => trim($_POST[$prefix . 'barangay'] ?? ''),
        'city' => trim($_POST[$prefix . 'city'] ?? ''),
        'province' => trim($_POST[$prefix . 'province'] ?? ''),
    ];
}

function format_delivery_address($street_address, $barangay, $city, $province) {
    return implode(', ', array_filter([
        trim((string) $street_address),
        trim((string) $barangay),
        trim((string) $city),
        trim((string) $province),
    ], function ($part) {
        return $part !== '';
    }));
}

function delivery_address_is_complete($address) {
    return trim((string) ($address['street_address'] ?? '')) !== ''
        && trim((string) ($address['barangay'] ?? '')) !== ''
        && trim((string) ($address['city'] ?? '')) !== ''
        && trim((string) ($address['province'] ?? '')) !== '';
}

function delivery_area_index_json($areas) {
    $index = [];
    foreach ($areas as $area) {
        $province = (string) ($area['province'] ?? '');
        $city = (string) ($area['city'] ?? '');
        $barangay = (string) ($area['barangay'] ?? '');
        if ($province === '' || $city === '' || $barangay === '') {
            continue;
        }

        if (!isset($index[$province])) {
            $index[$province] = [];
        }
        if (!isset($index[$province][$city])) {
            $index[$province][$city] = [];
        }

        $index[$province][$city][] = $barangay;
    }

    return json_encode($index, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function customer_delivery_address_rows($conn, $customer_id) {
    $stmt = $conn->prepare("SELECT * FROM customer_delivery_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at ASC, address_id ASC");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function customer_delivery_address_find($conn, $customer_id, $address_id) {
    $stmt = $conn->prepare("SELECT * FROM customer_delivery_addresses WHERE address_id = ? AND customer_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $address_id, $customer_id);
    $stmt->execute();
    $address = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $address ?: null;
}

function sync_customer_default_address($conn, $customer_id) {
    $addresses = customer_delivery_address_rows($conn, $customer_id);
    if (empty($addresses)) {
        return;
    }

    $default_address = $addresses[0];
    foreach ($addresses as $address) {
        if ((int) ($address['is_default'] ?? 0) === 1) {
            $default_address = $address;
            break;
        }
    }

    $full_address = (string) ($default_address['address'] ?? '');
    $stmt = $conn->prepare("UPDATE users SET address = ? WHERE user_id = ? AND role = 'customer'");
    if ($stmt) {
        $stmt->bind_param('si', $full_address, $customer_id);
        $stmt->execute();
        $stmt->close();
    }
}
