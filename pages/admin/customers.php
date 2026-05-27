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
$customers = false;
$customer_rows = [];

if (isset($_POST['toggle_status'])) {
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if ($user_id > 0 && in_array($new_status, ['active', 'inactive'], true)) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'customer'");
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            $success = "Customer status updated to " . $new_status;
        } else {
            $error = "Failed to update customer status.";
        }
        $stmt->close();
    } else {
        $error = "Invalid customer status request.";
    }
}

if (isset($_POST['edit_customer'])) {
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($user_id < 1 || empty($full_name)) {
        $error = "Full name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE user_id = ? AND role = 'customer'");
        $stmt->bind_param("sssi", $full_name, $phone, $address, $user_id);
        if ($stmt->execute()) {
            $success = "Customer information updated successfully!";
        } else {
            $error = "Failed to update customer.";
        }
        $stmt->close();
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$search_term = '';
$search_clause = "";
if (!empty($search)) {
    $search_term = "%$search%";
    $search_clause = "AND (username LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
}

$query = "
    SELECT u.*,
           l.total_orders, l.consecutive_orders, l.free_gallons_earned, l.free_gallons_used,
           (SELECT COUNT(*) FROM orders WHERE customer_id = u.user_id) as order_count
    FROM users u
    LEFT JOIN loyalty l ON u.user_id = l.customer_id
    WHERE u.role = 'customer' $search_clause
    ORDER BY u.created_at DESC
";

if (!empty($search)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
        $stmt->execute();
        $customers = $stmt->get_result();
        $customer_rows = $customers ? $customers->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    } else {
        $error = "Unable to prepare the customer search.";
    }
} else {
    $customers = $conn->query($query);
    $customer_rows = $customers ? $customers->fetch_all(MYSQLI_ASSOC) : [];
    if (!$customers) {
        $error = "Unable to load customers.";
    }
}

$total_customers = admin_count_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
$active_customers = admin_count_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND status = 'active'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - ISRAPHIL Admin</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../style/admin/admin_compact.css?v=20260528f" rel="stylesheet">
    <link rel="stylesheet" href="../../style/system_skeleton.css?v=20260527d">
</head>
<body class="bg-light system-loading skeleton-admin skeleton-admin-customers">
    <div class="container-fluid p-0">
        <div class="row g-0">
        <aside class="admin-floating-sidebar offcanvas-lg offcanvas-start col-lg-3 col-xl-2 text-white bg-israphil-sidebar d-flex flex-column p-3 min-vh-100" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
            <div class="d-flex d-lg-none justify-content-between align-items-center mb-2">
                <span class="fw-semibold" id="adminSidebarLabel">Admin Menu</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close menu"></button>
            </div>
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
                <a href="staff.php" class="nav-link text-white d-flex align-items-center gap-2">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <span>Staff</span>
                </a>
                <a href="customers.php" class="nav-link active d-flex align-items-center gap-2" aria-current="page">
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
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-primary d-lg-none admin-mobile-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open admin menu">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <div>
                        <span class="navbar-brand mb-0 h1 fw-bold text-primary">Customer Management</span>
                        <div class="small text-secondary">Administrator: <?php echo htmlspecialchars($admin_name); ?></div>
                    </div>
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
                    <span class="badge text-bg-info mb-3">Customer Oversight</span>
                    <div class="row g-4 align-items-end">
                        <div class="col-lg-8">
                            <h1 class="display-6 fw-bold text-primary mb-3">Keep customer accounts, contact details, and loyalty data aligned</h1>
                            <p class="lead text-secondary mb-0">Use this workspace to review active accounts, correct delivery details, and understand repeat-order behavior without switching between screens.</p>
                        </div>
                        <div class="col-lg-4">
                            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                                <a href="#customer-search" class="btn btn-primary">Search customers</a>
                                <a href="#customer-list" class="btn btn-success">Review customer list</a>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row g-3 mb-4">
                    <div class="col-md-4">
                        <article class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Total Customers</span>
                                <div class="display-5 fw-bold text-primary mt-2"><?php echo $total_customers; ?></div>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="card h-100 border-0 shadow-sm border-top border-4 border-success">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Active Customers</span>
                                <div class="display-5 fw-bold text-success mt-2"><?php echo $active_customers; ?></div>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="card h-100 border-0 shadow-sm border-top border-4 border-danger">
                            <div class="card-body">
                                <span class="text-secondary small fw-semibold text-uppercase">Inactive</span>
                                <div class="display-5 fw-bold text-danger mt-2"><?php echo $total_customers - $active_customers; ?></div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="card border-0 shadow-sm mb-4" id="customer-search">
                    <div class="card-body p-4">
                        <h2 class="h4 fw-bold mb-2">Search Customers</h2>
                        <p class="text-secondary mb-4">Filter customer accounts by username, name, email, or phone number when you need to correct information or confirm an order owner quickly.</p>
                        <form method="GET" action="" class="row g-2">
                            <div class="col-lg">
                                <input type="text" class="form-control" name="search" placeholder="Search by username, name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-lg-auto">
                                <button type="submit" class="btn btn-primary w-100">Search</button>
                            </div>
                            <?php if (!empty($search)): ?>
                            <div class="col-lg-auto">
                                <a href="customers.php" class="btn btn-outline-secondary w-100">Clear</a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </section>

                <section class="card border-0 shadow-sm" id="customer-list">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <h2 class="h4 fw-bold mb-2">Customer List</h2>
                        <p class="text-secondary mb-0">Review customer profiles, delivery contact details, and loyalty progress from a single operations-friendly table.</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($customer_rows)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Username</th>
                                            <th scope="col">Customer</th>
                                            <th scope="col">Contact</th>
                                            <th scope="col">Orders</th>
                                            <th scope="col">Loyalty</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Joined</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customer_rows as $c):
                                            $customer_id = (int) ($c['user_id'] ?? 0);
                                            $customer_username = (string) ($c['username'] ?? '');
                                            $customer_name = (string) ($c['full_name'] ?? '');
                                            $customer_email = (string) ($c['email'] ?? '');
                                            $customer_phone = (string) ($c['phone'] ?? '');
                                            $customer_address = (string) ($c['address'] ?? '');
                                            $customer_status = (string) ($c['status'] ?? 'inactive');
                                            $customer_joined = (string) ($c['created_at'] ?? '');
                                            $order_count = (int) ($c['order_count'] ?? 0);
                                            $loyalty_total_orders = (int) ($c['total_orders'] ?? 0);
                                            $consecutive_orders = (int) ($c['consecutive_orders'] ?? 0);
                                            $free_available = (int) ($c['free_gallons_earned'] ?? 0) - (int) ($c['free_gallons_used'] ?? 0);
                                        ?>
                                        <tr>
                                            <td>#<?php echo $customer_id; ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($customer_username); ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($customer_name); ?></div>
                                                <div class="small text-secondary"><?php echo htmlspecialchars($customer_email); ?></div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($customer_phone); ?><br>
                                                <small class="text-secondary"><?php echo htmlspecialchars(admin_excerpt($customer_address, 25)); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo $order_count; ?></strong> orders<br>
                                                <small class="text-secondary">Total: <?php echo $loyalty_total_orders; ?></small>
                                            </td>
                                            <td>
                                                Consecutive: <?php echo $consecutive_orders; ?><br>
                                                <?php if ($free_available > 0): ?>
                                                    <span class="badge text-bg-success"><?php echo $free_available; ?> free gallon(s)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill text-bg-<?php echo $customer_status === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($customer_status); ?>
                                                </span>
                                            </td>
                                            <td><?php echo admin_format_date($customer_joined); ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button
                                                        class="btn btn-primary btn-sm"
                                                        data-user-id="<?php echo $customer_id; ?>"
                                                        data-username="<?php echo htmlspecialchars($customer_username, ENT_QUOTES); ?>"
                                                        data-name="<?php echo htmlspecialchars($customer_name, ENT_QUOTES); ?>"
                                                        data-email="<?php echo htmlspecialchars($customer_email, ENT_QUOTES); ?>"
                                                        data-phone="<?php echo htmlspecialchars($customer_phone, ENT_QUOTES); ?>"
                                                        data-address="<?php echo htmlspecialchars($customer_address, ENT_QUOTES); ?>"
                                                        data-status="<?php echo htmlspecialchars(strtoupper($customer_status), ENT_QUOTES); ?>"
                                                        data-joined="<?php echo htmlspecialchars(admin_format_date($customer_joined, 'F d, Y'), ENT_QUOTES); ?>"
                                                        data-order-count="<?php echo $order_count; ?>"
                                                        data-total-orders="<?php echo $loyalty_total_orders; ?>"
                                                        data-consecutive="<?php echo $consecutive_orders; ?>"
                                                        data-free="<?php echo (int) $free_available; ?>"
                                                        onclick="viewCustomer(this)"
                                                    >View</button>
                                                    <button
                                                        class="btn btn-warning btn-sm"
                                                        data-user-id="<?php echo $customer_id; ?>"
                                                        data-name="<?php echo htmlspecialchars($customer_name, ENT_QUOTES); ?>"
                                                        data-phone="<?php echo htmlspecialchars($customer_phone, ENT_QUOTES); ?>"
                                                        data-address="<?php echo htmlspecialchars($customer_address, ENT_QUOTES); ?>"
                                                        onclick="editCustomer(this)"
                                                    >Edit</button>

                                                    <form method="POST" class="d-inline" data-admin-confirm="<?php echo $customer_status === 'active' ? 'Deactivate this customer account?' : 'Activate this customer account?'; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $customer_id; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $customer_status === 'active' ? 'inactive' : 'active'; ?>">
                                                        <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $customer_status === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                                            <?php echo $customer_status === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="display-6 text-secondary mb-3"><i class="bi bi-person-x" aria-hidden="true"></i></div>
                                <h3 class="h5 fw-bold">No customers found</h3>
                                <p class="text-secondary mb-0"><?php echo !empty($search) ? 'Try a different search term.' : 'No customers registered yet.'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h3 class="modal-title h5" id="editModalLabel">Edit Customer</h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="editUserId">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" id="editFullName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="editPhone">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="editAddress" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_customer" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
            <div class="modal-header">
                    <h3 class="modal-title h5" id="viewModalLabel">Customer Details</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
                <div class="modal-body" id="customerDetails"></div>
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
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
        const adminConfirmModalEl = document.getElementById('adminConfirmModal');
        const adminConfirmMessage = document.getElementById('adminConfirmMessage');
        const adminConfirmContinue = document.getElementById('adminConfirmContinue');
        const adminConfirmModal = adminConfirmModalEl ? new bootstrap.Modal(adminConfirmModalEl) : null;
        let pendingAdminConfirmForm = null;
        let pendingAdminSubmitter = null;

        function editCustomer(button) {
            document.getElementById('editUserId').value = button.dataset.userId;
            document.getElementById('editFullName').value = button.dataset.name;
            document.getElementById('editPhone').value = button.dataset.phone;
            document.getElementById('editAddress').value = button.dataset.address;
            editModal.show();
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (character) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[character];
            });
        }

        function viewCustomer(button) {
            const details = document.getElementById('customerDetails');
            const data = button.dataset;
            const freeGallons = button.dataset.free !== '0'
                ? '<div class="col-md-6"><div class="border rounded-3 p-3 h-100"><span class="small text-secondary text-uppercase fw-bold">Free Gallons Available</span><div class="fw-semibold">' + escapeHtml(data.free) + '</div></div></div>'
                : '';

            details.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Customer ID</span>
                            <div class="fw-semibold">#${escapeHtml(data.userId)}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Username</span>
                            <div class="fw-semibold">${escapeHtml(data.username || 'Not provided')}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Full Name</span>
                            <div class="fw-semibold">${escapeHtml(data.name)}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Email</span>
                            <div class="fw-semibold">${escapeHtml(data.email)}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Phone</span>
                            <div class="fw-semibold">${escapeHtml(data.phone || 'Not provided')}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Account Status</span>
                            <div class="fw-semibold">${escapeHtml(data.status)}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Joined</span>
                            <div class="fw-semibold">${escapeHtml(data.joined)}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Order Records</span>
                            <div class="fw-semibold">${escapeHtml(data.orderCount)}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Loyalty Total Orders</span>
                            <div class="fw-semibold">${escapeHtml(data.totalOrders)}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="small text-secondary text-uppercase fw-bold">Consecutive Orders</span>
                            <div class="fw-semibold">${escapeHtml(data.consecutive)}</div>
                        </div>
                    </div>
                    ${freeGallons}
                </div>
                <div class="border rounded-3 p-3 mt-3">
                    <span class="small text-secondary text-uppercase fw-bold">Delivery Address</span>
                    <p class="mb-0 mt-2">${escapeHtml(data.address || 'No address saved.')}</p>
                </div>
            `;
            viewModal.show();
        }

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
    <script src="../../scripts/system_skeleton.js?v=20260527"></script>
</body>
</html>
