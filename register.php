<?php
session_start();
require_once 'includes/db_connect.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin': header("Location: pages/admin/dashboard.php"); exit();
        case 'staff': header("Location: pages/staff/dashboard.php"); exit();
        case 'customer': header("Location: pages/customer/dashboard.php"); exit();
    }
}

$error = '';
$success = '';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $location_confirm = isset($_POST['location_confirm']);

    // Validation - CHECK LOCATION FIRST
    if (!$location_confirm) {
        $error = "You must confirm your location is within our delivery area (Basista, Pangasinan and nearby).";
    } elseif (empty($username) || empty($password) || empty($email) || empty($full_name) || empty($phone) || empty($address)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if username exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            // Hash password
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new customer
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, 'customer', 'active')");
            $stmt->bind_param("ssssss", $username, $hash, $email, $full_name, $phone, $address);

            if ($stmt->execute()) {
                $customer_id = $stmt->insert_id;

                // Create loyalty record (skip if already exists)
                $conn->query("INSERT IGNORE INTO loyalty (customer_id, consecutive_orders, total_orders, free_gallons_earned) VALUES ($customer_id, 0, 0, 0)");

                $success = "Account created successfully! You can now log in.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISRAPHIL | Create Account</title>
    <link rel="stylesheet" href="style/register.css?v=20260325">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-visual">
            <div class="auth-copy">
                <span class="auth-badge">Customer Registration</span>
                <h1>Set up your<br>delivery account.</h1>
                <p>Create your customer profile once and manage orders, delivery updates, and loyalty rewards from a modern dashboard built for repeat use.</p>
                <ul class="auth-feature-list">
                    <li>Simple repeat ordering<span>Keep your contact and address details ready for the next request.</span></li>
                    <li>Transparent delivery status<span>Track every order from placement to doorstep.</span></li>
                    <li>Loyalty progress visibility<span>See your free gallon progress and order streak at a glance.</span></li>
                </ul>
            </div>
            <div class="auth-showcase">
                <div class="auth-service-card">
                    <span class="auth-badge">Service Area</span>
                    <strong>Basista, Pangasinan and nearby areas</strong>
                    <p>Please register only if your delivery address is within the supported coverage area.</p>
                </div>
                <img class="auth-illustration" src="image.gif/drink_water.gif" alt="Water station illustration">
            </div>
        </section>

        <section class="auth-panel">
            <div class="box auth-card">
                <span class="auth-mark">Create Account</span>
                <h2>Join ISRAPHIL</h2>
                <p class="auth-subtitle">Fill in your details below to create your customer account and start ordering.</p>

                <?php if ($error): ?>
                    <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="auth-success">
                        <?php echo htmlspecialchars($success); ?><br>
                        <a href="index.php">Go to sign in</a>
                    </div>
                <?php else: ?>

                <form method="POST" action="" class="register-form">
                    <div class="register-grid">
                        <div class="form-group">
                            <label>Username <span class="required">*</span></label>
                            <input type="text" name="username" required autocomplete="username">
                        </div>
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" required>
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" required autocomplete="email">
                        </div>
                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" required placeholder="09123456789">
                        </div>
                        <div class="form-group full-span">
                            <label>Delivery Address <span class="required">*</span></label>
                            <input type="text" name="address" required placeholder="Street, Barangay, City">
                        </div>
                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <input type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="Minimum 6 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" required autocomplete="new-password">
                        </div>
                    </div>

                    <div class="delivery-notice">
                        <h3>Delivery Coverage Notice</h3>
                        <p>We are based in <strong>Basista, Pangasinan</strong> and currently deliver only to nearby areas.</p>
                        <label class="delivery-check">
                            <input type="checkbox" name="location_confirm" required>
                            <span>I confirm my delivery location is in or near <strong>Basista, Pangasinan</strong>.</span>
                        </label>
                        <p class="delivery-note">Providing a false delivery location may lead to account deactivation.</p>
                    </div>

                    <button type="submit">Create My Account</button>
                </form>

                <div class="auth-links">
                    <span>Already registered?</span>
                    <a href="index.php">Sign in here</a>
                </div>

                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
