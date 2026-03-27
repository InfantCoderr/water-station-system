<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';

require_active_session($conn, ['admin'], '../../index.php');

$success = '';
$error = '';
$edit_mode = false;
$edit_staff = null;

if (isset($_POST['add_staff'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, 'staff', 'active')");
            $stmt->bind_param("ssssss", $username, $hash, $email, $full_name, $phone, $address);

            if ($stmt->execute()) {
                $success = "Staff account created successfully!";
            } else {
                $error = "Failed to create staff account.";
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'staff'");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_staff = $stmt->get_result()->fetch_assoc();
    if ($edit_staff) {
        $edit_mode = true;
    }
}

if (isset($_POST['update_staff'])) {
    $user_id = $_POST['user_id'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (empty($email) || empty($full_name)) {
        $error = "Email and full name are required.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Email already used by another account.";
        } else {
            if (!empty($new_password) && strlen($new_password) >= 6) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET email = ?, full_name = ?, phone = ?, address = ?, password = ? WHERE user_id = ?");
                $stmt->bind_param("sssssi", $email, $full_name, $phone, $address, $hash, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET email = ?, full_name = ?, phone = ?, address = ? WHERE user_id = ?");
                $stmt->bind_param("ssssi", $email, $full_name, $phone, $address, $user_id);
            }

            if ($stmt->execute()) {
                $success = "Staff information updated successfully!";
                $edit_mode = false;
                $edit_staff = null;
            } else {
                $error = "Failed to update staff.";
            }
        }
    }
}

if (isset($_POST['toggle_status'])) {
    $user_id = $_POST['user_id'] ?? '';
    $new_status = $_POST['new_status'] ?? '';

    if (!empty($user_id) && !empty($new_status)) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            $success = "Staff status updated to " . $new_status;
        }
    }
}

$staff_list = $conn->query("SELECT * FROM users WHERE role = 'staff' ORDER BY created_at DESC");
$active_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND status = 'active'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - ISRAPHIL Admin</title>
    <link rel="stylesheet" href="../../style/admin/staff.css?v=20260325">
</head>
<body class="admin-page">
    <?php if (!empty($success)): ?>
    <div class="modal-overlay active" id="successModal">
        <div class="modal-box">
            <div class="modal-icon success">Done</div>
            <h3>Success</h3>
            <p><?php echo htmlspecialchars($success); ?></p>
            <button class="modal-btn btn-success" onclick="closeModal('successModal')">Close</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="modal-overlay active" id="errorModal">
        <div class="modal-box">
            <div class="modal-icon error">Issue</div>
            <h3>Error</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
            <button class="modal-btn btn-danger" onclick="closeModal('errorModal')">Close</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="header">
        <h1>ISRAPHIL Admin</h1>
        <div class="user-info">
            <span>Administrator: <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="nav-tabs">
            <a href="dashboard.php" class="nav-tab">Dashboard</a>
            <a href="orders.php" class="nav-tab">All Orders</a>
            <a href="inventory.php" class="nav-tab">Inventory</a>
            <a href="staff.php" class="nav-tab active">Staff</a>
            <a href="customers.php" class="nav-tab">Customers</a>
        </div>

        <div class="page-intro">
            <span class="page-intro-kicker">Staff Administration</span>
            <h2>Manage active delivery personnel with clearer control</h2>
            <p>Create staff accounts, update delivery-team details, and deactivate access cleanly when someone should no longer receive assignments.</p>
            <div class="page-intro-actions">
                <a href="#staff-form" class="btn btn-success"><?php echo $edit_mode ? 'Continue editing' : 'Create staff account'; ?></a>
                <a href="#staff-list" class="btn btn-primary">Review staff directory</a>
            </div>
        </div>

        <div class="section" id="staff-form">
            <h2><?php echo $edit_mode ? 'Edit Staff' : 'Add New Staff'; ?></h2>
            <p class="section-copy"><?php echo $edit_mode ? 'Adjust account details without losing delivery history or role assignment records.' : 'Register delivery-team accounts with the information needed for operations and communication.'; ?></p>

            <?php if ($edit_mode): ?>
            <div class="edit-form">
            <?php endif; ?>

            <form method="POST" action="">
                <?php if ($edit_mode): ?>
                <input type="hidden" name="user_id" value="<?php echo $edit_staff['user_id']; ?>">
                <?php endif; ?>

                <div class="form-row">
                    <?php if (!$edit_mode): ?>
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password * (min 6 chars)</label>
                        <input type="password" name="password" required minlength="6">
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($edit_staff['username']); ?>" disabled style="background: rgba(255, 255, 255, 0.06); color: #9db3c7;">
                        <small>Username cannot be changed</small>
                    </div>
                    <div class="form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" name="new_password" minlength="6" placeholder="Enter new password">
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo $edit_mode ? htmlspecialchars($edit_staff['full_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo $edit_mode ? htmlspecialchars($edit_staff['email']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo $edit_mode ? htmlspecialchars($edit_staff['phone']) : ''; ?>" placeholder="09123456789">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" value="<?php echo $edit_mode ? htmlspecialchars($edit_staff['address']) : ''; ?>" placeholder="Staff address">
                    </div>
                </div>

                <?php if ($edit_mode): ?>
                <button type="submit" name="update_staff" class="btn btn-success">Update Staff</button>
                <a href="staff.php" class="cancel-link">Cancel</a>
                <?php else: ?>
                <button type="submit" name="add_staff" class="btn btn-primary">Create Staff Account</button>
                <?php endif; ?>
            </form>

            <?php if ($edit_mode): ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="section" id="staff-list">
            <h2>Staff List (<?php echo $active_count; ?> active)</h2>
            <p class="section-copy">This directory keeps the delivery workforce visible while preserving a safe activate and deactivate workflow instead of hard deletes.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = $staff_list->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $s['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['username']); ?></td>
                            <td><?php echo htmlspecialchars($s['email']); ?></td>
                            <td><?php echo htmlspecialchars($s['phone']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $s['status']; ?>">
                                    <?php echo ucfirst($s['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($s['created_at'])); ?></td>
                            <td class="actions">
                                <a href="?edit=<?php echo $s['user_id']; ?>" class="btn btn-warning btn-sm">Edit</a>

                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $s['user_id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $s['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $s['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo $s['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function (event) {
                if (event.target === this) {
                    this.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
