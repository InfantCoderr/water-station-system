<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';

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
    <link rel="stylesheet" href="../../style/customer/profile.css?v=20260325">
</head>
<body>
    <?php if (!empty($success)): ?>
    <div class="modal-overlay active" id="successModal">
        <div class="modal-box">
            <div class="modal-icon success">OK</div>
            <h3>Success!</h3>
            <p><?php echo htmlspecialchars($success); ?></p>
            <button class="btn btn-success" onclick="closeModal('successModal')" style="margin-top: 15px;">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="modal-overlay active" id="errorModal">
        <div class="modal-box">
            <div class="modal-icon error">ERR</div>
            <h3>Error</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
            <button class="btn btn-danger" onclick="closeModal('errorModal')" style="margin-top: 15px;">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="header">
        <h1>ISRAPHIL Water Station</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>

        <div class="nav-tabs">
            <a href="dashboard.php" class="nav-tab">Place Order</a>
            <a href="history.php" class="nav-tab">Order History</a>
            <a href="profile.php" class="nav-tab active">My Profile</a>
        </div>

        <div class="profile-grid">
            <div class="profile-summary">
                <div class="section">
                    <h2>Profile Overview</h2>
                    <p class="section-copy">Keep your contact details current so every order, update, and delivery reaches the right place.</p>

                    <div class="info-row">
                        <div class="info-label">Username</div>
                        <div class="info-value"><?php echo htmlspecialchars($customer_info['username']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Member Since</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($customer_info['created_at'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Account Status</div>
                        <div class="info-value">
                            <span class="account-chip <?php echo $customer_info['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo strtoupper($customer_info['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="section form-panel">
                    <h2>Edit Profile</h2>
                    <p class="section-copy">Update your delivery address, phone number, and email whenever anything changes.</p>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($customer_info['full_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($customer_info['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($customer_info['phone']); ?>" placeholder="09123456789">
                        </div>

                        <div class="form-group">
                            <label>Delivery Address</label>
                            <textarea name="address" placeholder="Street, Barangay, City"><?php echo htmlspecialchars($customer_info['address']); ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>

                <div class="section">
                    <h2>Change Password</h2>
                    <p class="section-copy">Use a strong password so your order history and account details stay protected.</p>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label>New Password (min 6 characters)</label>
                            <input type="password" name="new_password" minlength="6" required>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-success">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });
    </script>
</body>
</html>
