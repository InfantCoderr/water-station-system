<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin_page_helpers.php';

/** @var mysqli $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is not available.');
}

$admin_user = require_active_session($conn, ['admin'], '../../index.php');
$admin_name = $admin_user['full_name'] ?? ($_SESSION['full_name'] ?? 'Administrator');

$success = '';
$error = '';
$edit_mode = false;
$edit_staff = null;
$staff_list = false;
$staff_rows = [];

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
        if ($check) {
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Username or email already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, 'staff', 'active')");
                if ($stmt) {
                    $stmt->bind_param("ssssss", $username, $hash, $email, $full_name, $phone, $address);

                    if ($stmt->execute()) {
                        $success = "Staff account created successfully!";
                    } else {
                        $error = "Failed to create staff account.";
                    }
                    $stmt->close();
                } else {
                    $error = "Unable to prepare staff account creation.";
                }
            }
            $check->close();
        } else {
            $error = "Unable to check staff username or email.";
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'staff'");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $edit_staff = $stmt->get_result()->fetch_assoc();
        if ($edit_staff) {
            $edit_mode = true;
        }
        $stmt->close();
    } else {
        $error = "Unable to load staff account for editing.";
    }
}

if (isset($_POST['update_staff'])) {
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if ($user_id < 1 || empty($email) || empty($full_name)) {
        $error = "Email and full name are required.";
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        if ($check) {
            $check->bind_param("si", $email, $user_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Email already used by another account.";
            } else {
                if (!empty($new_password) && strlen($new_password) >= 6) {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET email = ?, full_name = ?, phone = ?, address = ?, password = ? WHERE user_id = ? AND role = 'staff'");
                    if ($stmt) {
                        $stmt->bind_param("sssssi", $email, $full_name, $phone, $address, $hash, $user_id);
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE users SET email = ?, full_name = ?, phone = ?, address = ? WHERE user_id = ? AND role = 'staff'");
                    if ($stmt) {
                        $stmt->bind_param("ssssi", $email, $full_name, $phone, $address, $user_id);
                    }
                }

                if ($stmt && $stmt->execute()) {
                    $success = "Staff information updated successfully!";
                    $edit_mode = false;
                    $edit_staff = null;
                } else {
                    $error = "Failed to update staff.";
                }
                if ($stmt) {
                    $stmt->close();
                }
            }
            $check->close();
        } else {
            $error = "Unable to check staff email.";
        }
    }
}

if (isset($_POST['toggle_status'])) {
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if ($user_id > 0 && in_array($new_status, ['active', 'inactive'], true)) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'staff'");
        if ($stmt) {
            $stmt->bind_param("si", $new_status, $user_id);
            if ($stmt->execute()) {
                $success = "Staff status updated to " . $new_status;
            } else {
                $error = "Failed to update staff status.";
            }
            $stmt->close();
        } else {
            $error = "Unable to prepare staff status update.";
        }
    } else {
        $error = "Invalid staff status request.";
    }
}

$staff_list = $conn->query("SELECT * FROM users WHERE role = 'staff' ORDER BY created_at DESC");
$active_count = admin_count_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND status = 'active'");
$staff_rows = $staff_list ? $staff_list->fetch_all(MYSQLI_ASSOC) : [];
$total_staff = count($staff_rows);

$edit_staff_id = (int) ($edit_staff['user_id'] ?? 0);
$edit_username = (string) ($edit_staff['username'] ?? '');
$edit_full_name = (string) ($edit_staff['full_name'] ?? '');
$edit_email = (string) ($edit_staff['email'] ?? '');
$edit_phone = (string) ($edit_staff['phone'] ?? '');
$edit_address = (string) ($edit_staff['address'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - ISRAPHIL Admin</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../style/admin/admin_compact.css?v=20260512b" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-0">
        <div class="row g-0">
        <aside class="admin-floating-sidebar col-lg-3 col-xl-2 text-white bg-israphil-sidebar d-flex flex-column p-3 min-vh-100">
            <a href="dashboard.php" class="d-flex align-items-center gap-2 text-white text-decoration-none fs-4 mb-3">
                <span class="bg-white rounded d-inline-flex align-items-center justify-content-center p-1">
                    <img src="../../image.gif/favicon.png" alt="ISRAPHIL logo" width="32" height="32">
                </span>
                <span class="fw-semibold">ISRAPHIL</span>
            </a>

            <hr class="border-secondary">

            <nav class="nav nav-pills flex-column gap-1 mb-auto">
                <div class="px-3 mt-2 mb-1 small text-uppercase text-white-50 fw-semibold">Main Operations</div>
                <a href="dashboard.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>
                <a href="orders.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    <span>Orders</span>
                </a>
                <button class="nav-link border-0 w-100 d-flex align-items-center gap-2 admin-order-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#deliveriesMenu" aria-expanded="false" aria-controls="deliveriesMenu">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Deliveries</span>
                </button>
                <div class="collapse" id="deliveriesMenu">
                    <div class="admin-order-subnav">
                        <a href="order_batches.php">Order Batches</a>
                        <a href="batch_assignments.php">Batch Assignment</a>
                    </div>
                </div>
                <a href="exceptions.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    <span>Exceptions</span>
                </a>
                <div class="px-3 mt-3 mb-1 small text-uppercase text-white-50 fw-semibold">Management</div>
                <a href="inventory.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <span>Inventory</span>
                </a>
                <a href="staff.php" class="nav-link active d-flex align-items-center gap-2" aria-current="page">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <span>Staff</span>
                </a>
                <a href="customers.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    <span>Customers</span>
                </a>
                <div class="px-3 mt-3 mb-1 small text-uppercase text-white-50 fw-semibold">Reports</div>
                <a href="reports.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart" aria-hidden="true"></i>
                    <span>Reports</span>
                </a>
            </nav>

            <hr class="border-secondary">

            <div class="px-3 mb-2 small text-uppercase text-white-50 fw-semibold">Account / System</div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center gap-2 text-white text-decoration-none dropdown-toggle fw-bold" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="badge rounded-circle text-bg-warning p-2">
                        <?php echo admin_initial($admin_name); ?>
                    </span>
                    <span><?php echo htmlspecialchars($admin_name); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                    <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                </ul>
            </div>
        </aside>

        <div class="admin-floating-content col-lg-9 col-xl-10 min-vh-100">
            <nav class="navbar bg-white border-bottom shadow-sm px-3 px-lg-4">
                <div>
                    <span class="navbar-brand mb-0 h1 fw-bold text-primary">Staff Management</span>
                    <div class="small text-secondary">Administrator: <?php echo htmlspecialchars($admin_name); ?></div>
                </div>
            </nav>

            <main class="container-fluid px-3 px-lg-4 py-4">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <section class="bg-white border rounded-4 shadow-sm p-4 p-lg-5 mb-4">
                    <span class="badge text-bg-info mb-3">Staff Administration</span>
                    <div class="row g-4 align-items-end">
                        <div class="col-lg-8">
                            <h1 class="display-6 fw-bold text-primary mb-3">Manage active delivery personnel with clearer control</h1>
                            <p class="lead text-secondary mb-0">Create staff accounts, update delivery-team details, and deactivate access cleanly when someone should no longer receive assignments.</p>
                        </div>
                        <div class="col-lg-4">
                            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                                <a href="#staff-form" class="btn btn-success"><?php echo $edit_mode ? 'Continue editing' : 'Create staff account'; ?></a>
                                <a href="#staff-list" class="btn btn-primary">Review staff directory</a>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row g-3 mb-4">
                    <div class="col-md-4">
                        <article class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Total Staff</span>
                                <div class="display-5 fw-bold text-primary mt-2"><?php echo $total_staff; ?></div>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="card h-100 border-0 shadow-sm border-top border-4 border-success">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Active Staff</span>
                                <div class="display-5 fw-bold text-success mt-2"><?php echo $active_count; ?></div>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="card h-100 border-0 shadow-sm border-top border-4 border-danger">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Inactive</span>
                                <div class="display-5 fw-bold text-danger mt-2"><?php echo $total_staff - $active_count; ?></div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="card border-0 shadow-sm mb-4" id="staff-form">
                    <div class="card-body p-4">
                        <h2 class="h4 fw-bold mb-2"><?php echo $edit_mode ? 'Edit Staff' : 'Add New Staff'; ?></h2>
                        <p class="text-secondary mb-4"><?php echo $edit_mode ? 'Adjust account details without losing delivery history or role assignment records.' : 'Register delivery-team accounts with the information needed for operations and communication.'; ?></p>

                        <form method="POST" action="" class="row g-3">
                            <?php if ($edit_mode): ?>
                                <input type="hidden" name="user_id" value="<?php echo $edit_staff_id; ?>">
                            <?php endif; ?>

                            <?php if (!$edit_mode): ?>
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" required minlength="6">
                                <div class="form-text">Minimum 6 characters.</div>
                            </div>
                            <?php else: ?>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_username); ?>" disabled>
                                <div class="form-text">Username cannot be changed.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" minlength="6" placeholder="Leave blank to keep current">
                            </div>
                            <?php endif; ?>

                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo $edit_mode ? htmlspecialchars($edit_full_name) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?php echo $edit_mode ? htmlspecialchars($edit_email) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo $edit_mode ? htmlspecialchars($edit_phone) : ''; ?>" placeholder="09123456789">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" value="<?php echo $edit_mode ? htmlspecialchars($edit_address) : ''; ?>" placeholder="Staff address">
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <?php if ($edit_mode): ?>
                                    <button type="submit" name="update_staff" class="btn btn-success">Update Staff</button>
                                    <a href="staff.php" class="btn btn-outline-secondary">Cancel</a>
                                <?php else: ?>
                                    <button type="submit" name="add_staff" class="btn btn-primary">Create Staff Account</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="card border-0 shadow-sm" id="staff-list">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <h2 class="h4 fw-bold mb-2">Staff List (<?php echo $active_count; ?> active)</h2>
                        <p class="text-secondary mb-0">This directory keeps the delivery workforce visible while preserving a safe activate and deactivate workflow instead of hard deletes.</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Username</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Phone</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Created</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($staff_rows as $s):
                                        $staff_id = (int) ($s['user_id'] ?? 0);
                                        $staff_name = (string) ($s['full_name'] ?? '');
                                        $staff_username = (string) ($s['username'] ?? '');
                                        $staff_email = (string) ($s['email'] ?? '');
                                        $staff_phone = (string) ($s['phone'] ?? '');
                                        $staff_status = (string) ($s['status'] ?? 'inactive');
                                        $staff_created = (string) ($s['created_at'] ?? '');
                                    ?>
                                    <tr>
                                        <td>#<?php echo $staff_id; ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($staff_name); ?></td>
                                        <td><?php echo htmlspecialchars($staff_username); ?></td>
                                        <td><?php echo htmlspecialchars($staff_email); ?></td>
                                        <td><?php echo htmlspecialchars($staff_phone); ?></td>
                                        <td>
                                            <span class="badge rounded-pill text-bg-<?php echo $staff_status === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($staff_status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo admin_format_date($staff_created); ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="?edit=<?php echo $staff_id; ?>#staff-form" class="btn btn-warning btn-sm">Edit</a>
                                                <form method="POST" class="d-inline" data-admin-confirm="<?php echo $staff_status === 'active' ? 'Deactivate this staff account?' : 'Activate this staff account?'; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $staff_id; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $staff_status === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $staff_status === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                                        <?php echo $staff_status === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </main>
        </div>
        </div>
    </div>

    <div class="modal fade" id="adminConfirmModal" tabindex="-1" aria-labelledby="adminConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="adminConfirmModalLabel">Confirm Action</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="adminConfirmMessage">Continue with this action?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="adminConfirmContinue">
                        <span class="admin-confirm-label">Continue</span>
                        <span class="admin-confirm-progress d-none">
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Working...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const adminConfirmModalEl = document.getElementById('adminConfirmModal');
        const adminConfirmMessage = document.getElementById('adminConfirmMessage');
        const adminConfirmContinue = document.getElementById('adminConfirmContinue');
        const adminConfirmModal = adminConfirmModalEl ? new bootstrap.Modal(adminConfirmModalEl) : null;
        let pendingAdminConfirmForm = null;
        let pendingAdminSubmitter = null;

        document.querySelectorAll('form[data-admin-confirm]').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();
                pendingAdminConfirmForm = form;
                pendingAdminSubmitter = event.submitter || null;

                if (adminConfirmMessage) {
                    adminConfirmMessage.textContent = form.dataset.adminConfirm || 'Continue with this action?';
                }

                if (adminConfirmContinue) {
                    adminConfirmContinue.disabled = false;
                    adminConfirmContinue.querySelector('.admin-confirm-label')?.classList.remove('d-none');
                    adminConfirmContinue.querySelector('.admin-confirm-progress')?.classList.add('d-none');
                }

                adminConfirmModal?.show();
            });
        });

        adminConfirmContinue?.addEventListener('click', () => {
            if (!pendingAdminConfirmForm) {
                return;
            }

            if (pendingAdminSubmitter?.name) {
                const hiddenAction = document.createElement('input');
                hiddenAction.type = 'hidden';
                hiddenAction.name = pendingAdminSubmitter.name;
                hiddenAction.value = pendingAdminSubmitter.value || '1';
                pendingAdminConfirmForm.appendChild(hiddenAction);
            }

            adminConfirmContinue.disabled = true;
            adminConfirmContinue.querySelector('.admin-confirm-label')?.classList.add('d-none');
            adminConfirmContinue.querySelector('.admin-confirm-progress')?.classList.remove('d-none');
            pendingAdminConfirmForm.submit();
        });
    </script>
</body>
</html>
