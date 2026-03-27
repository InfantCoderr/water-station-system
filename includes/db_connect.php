<?php
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS');
$db_password = getenv('DB_PASSWORD');
$db_name = getenv('DB_NAME') ?: 'israphil_db';

if ($db_pass === false && $db_password !== false) {
    $db_pass = $db_password;
}

if ($db_pass === false) {
    $db_pass = '';
}

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}

$conn->set_charset("utf8mb4");
?>
