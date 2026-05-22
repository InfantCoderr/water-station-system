<?php

function admin_count_query($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        error_log('Admin count query failed: ' . $conn->error);
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int) ($row['count'] ?? 0);
}

function admin_scalar_query($conn, $sql, $column, $default = 0) {
    $result = $conn->query($sql);
    if (!$result) {
        error_log('Admin scalar query failed: ' . $conn->error);
        return $default;
    }

    $row = $result->fetch_assoc();
    return $row[$column] ?? $default;
}

function admin_initial($name) {
    $name = trim((string) $name);
    return strtoupper(substr($name !== '' ? $name : 'A', 0, 1));
}

function admin_excerpt($value, $length = 30) {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Not provided';
    }

    if (strlen($value) <= $length) {
        return $value;
    }

    return substr($value, 0, $length) . '...';
}

function admin_format_date($value, $format = 'M d, Y') {
    $timestamp = strtotime((string) $value);
    if ($timestamp === false || $timestamp <= 0) {
        return 'N/A';
    }

    return date($format, $timestamp);
}
