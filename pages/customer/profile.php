<?php
session_start();
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

require_active_session($conn, ['customer'], '../../index.php');

$customer_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get customer info
$customer = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$customer->bind_param("i", $customer_id);
$customer->execute();
$customer_info = $customer->get_result()->fetch_assoc();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($full_name) || empty($email)) {
        $error = "Full name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check email unique (except for this user)
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->bind_param("si", $email, $customer_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Email already used by another account.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, email = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $full_name, $phone, $address, $email, $customer_id);

            if ($stmt->execute()) {
                // Update session name
                $_SESSION['full_name'] = $full_name;
                $success = "Profile updated successfully!";

                // Refresh data
                $customer->execute();
                $customer_info = $customer->get_result()->fetch_assoc();
            } else {
                $error = "Failed to update profile.";
            }
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        // Verify current password
        if (password_verify($current_password, $customer_info['password'])) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hash, $customer_id);

            if ($stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style/customer/profile.css">
</head>
<body class="bg-light">
    <?php if (!empty($success)): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast show align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?php echo htmlspecialchars($success); ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast show align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?php echo htmlspecialchars($error); ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
            <div>
                <span class="badge text-bg-primary mb-3">My Profile</span>
                <h1 class="h3 fw-bold mb-2">Account Settings</h1>
                <p class="text-secondary mb-0">Manage your contact details, main delivery address, and password.</p>
            </div>
        </div>

        <ul class="nav nav-pills gap-2 mb-4">
            <li class="nav-item"><a href="dashboard.php" class="nav-link">Place Order</a></li>
            <li class="nav-item"><a href="history.php" class="nav-link">Order History</a></li>
        </ul>

        <div class="row g-4 align-items-start">
            <div class="col-lg-4">
                <section class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <span class="badge text-bg-info mb-3">Profile Overview</span>
                        <h2 class="h5 fw-bold mb-2">Account details</h2>
                        <p class="text-secondary small mb-4">Keep your contact details current so every delivery reaches the right place.</p>

                        <div class="border-bottom py-3">
                            <div class="small text-secondary">Username</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($customer_info['username']); ?></div>
                    </div>
                        <div class="border-bottom py-3">
                            <div class="small text-secondary">Member Since</div>
                            <div class="fw-semibold"><?php echo date('F d, Y', strtotime($customer_info['created_at'])); ?></div>
                    </div>
                        <div class="py-3">
                            <div class="small text-secondary">Account Status</div>
                            <span class="badge <?php echo $customer_info['status'] === 'active' ? 'text-bg-success' : 'text-bg-danger'; ?>">
                                <?php echo strtoupper($customer_info['status']); ?>
                            </span>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-8">
                <section class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <span class="badge text-bg-primary mb-3">Edit Profile</span>
                        <h2 class="h5 fw-bold mb-2">Contact and delivery details</h2>
                        <p class="text-secondary small mb-4">Update your main delivery address, phone number, and email whenever anything changes.</p>

                    <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input id="full_name" class="form-control form-control-lg" type="text" name="full_name" value="<?php echo htmlspecialchars($customer_info['full_name']); ?>" required autocomplete="name">
                        </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input id="email" class="form-control form-control-lg" type="email" name="email" value="<?php echo htmlspecialchars($customer_info['email']); ?>" required autocomplete="email">
                        </div>

                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input id="phone" class="form-control form-control-lg" type="tel" name="phone" value="<?php echo htmlspecialchars($customer_info['phone']); ?>" placeholder="09123456789" autocomplete="tel">
                        </div>

                                <div class="col-12">
                                    <label for="address" class="form-label">Main Delivery Address</label>
                                    <textarea id="address" class="form-control" name="address" rows="3" placeholder="Street, Barangay, City"><?php echo htmlspecialchars($customer_info['address']); ?></textarea>
                                    <div class="form-text">You can choose or add other delivery addresses when placing an order.</div>
                        </div>

                                <div class="col-12">
                                    <button type="submit" name="update_profile" class="btn btn-primary btn-lg">Save Changes</button>
                                </div>
                            </div>
                    </form>
                    </div>
                </section>

                <section class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <span class="badge text-bg-light text-primary border mb-3">Security</span>
                        <h2 class="h5 fw-bold mb-2">Change Password</h2>
                        <p class="text-secondary small mb-4">Use a strong password so your order history and account details stay protected.</p>

                    <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input id="current_password" class="form-control form-control-lg" type="password" name="current_password" required autocomplete="current-password">
                        </div>

                                <div class="col-md-6">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input id="new_password" class="form-control form-control-lg" type="password" name="new_password" minlength="6" required autocomplete="new-password">
                                    <div class="form-text">Minimum 6 characters.</div>
                        </div>

                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input id="confirm_password" class="form-control form-control-lg" type="password" name="confirm_password" required autocomplete="new-password">
                        </div>

                                <div class="col-12">
                                    <button type="submit" name="change_password" class="btn btn-success btn-lg">Change Password</button>
                                </div>
                            </div>
                    </form>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
