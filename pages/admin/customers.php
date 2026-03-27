<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';

require_active_session($conn, ['admin'], '../../index.php');

$success = '';
$error = '';

if (isset($_POST['toggle_status'])) {
    $user_id = $_POST['user_id'] ?? '';
    $new_status = $_POST['new_status'] ?? '';

    if (!empty($user_id) && !empty($new_status)) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            $success = "Customer status updated to " . $new_status;
        }
    }
}

if (isset($_POST['edit_customer'])) {
    $user_id = $_POST['user_id'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($full_name)) {
        $error = "Full name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $full_name, $phone, $address, $user_id);
        if ($stmt->execute()) {
            $success = "Customer information updated successfully!";
        } else {
            $error = "Failed to update customer.";
        }
    }
}

$search = $_GET['search'] ?? '';
$search_clause = "";
if (!empty($search)) {
    $search_term = "%$search%";
    $search_clause = "AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
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
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $customers = $stmt->get_result();
} else {
    $customers = $conn->query($query);
}

$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
$active_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND status = 'active'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - ISRAPHIL Admin</title>
    <link rel="stylesheet" href="../../style/admin/customers.css?v=20260325">
</head>
<body class="admin-page">
    <?php if (!empty($success)): ?>
    <div class="modal-overlay active" id="successModal">
        <div class="modal-box" style="text-align: center; max-width: 400px;">
            <div class="modal-icon success">Done</div>
            <h3>Success</h3>
            <p><?php echo htmlspecialchars($success); ?></p>
            <button class="btn btn-success" onclick="closeModal('successModal')" style="margin-top: 15px;">Close</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="modal-overlay active" id="errorModal">
        <div class="modal-box" style="text-align: center; max-width: 400px;">
            <div class="modal-icon error">Issue</div>
            <h3>Error</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
            <button class="btn btn-danger" onclick="closeModal('errorModal')" style="margin-top: 15px;">Close</button>
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
            <a href="staff.php" class="nav-tab">Staff</a>
            <a href="customers.php" class="nav-tab active">Customers</a>
        </div>

        <div class="page-intro">
            <span class="page-intro-kicker">Customer Oversight</span>
            <h2>Keep customer accounts, contact details, and loyalty data aligned</h2>
            <p>Use this workspace to review active accounts, correct delivery details, and understand repeat-order behavior without switching between screens.</p>
            <div class="page-intro-actions">
                <a href="#customer-search" class="btn btn-primary">Search customers</a>
                <a href="#customer-list" class="btn btn-success">Review customer list</a>
            </div>
        </div>

        <div class="stats-bar">
            <div class="stat-card">
                <div class="number"><?php echo $total_customers; ?></div>
                <div class="label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="number success"><?php echo $active_customers; ?></div>
                <div class="label">Active Customers</div>
            </div>
            <div class="stat-card">
                <div class="number danger"><?php echo $total_customers - $active_customers; ?></div>
                <div class="label">Inactive</div>
            </div>
        </div>

        <div class="section" id="customer-search">
            <h2>Search Customers</h2>
            <p class="section-copy">Filter customer accounts by name, email, or phone number when you need to correct information or confirm an order owner quickly.</p>
            <form method="GET" action="" class="search-bar">
                <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
                <?php if (!empty($search)): ?>
                <a href="customers.php" class="btn btn-warning">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="section" id="customer-list">
            <h2>Customer List</h2>
            <p class="section-copy">Review customer profiles, delivery contact details, and loyalty progress from a single operations-friendly table.</p>

            <?php if ($customers->num_rows > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Orders</th>
                            <th>Loyalty</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($c = $customers->fetch_assoc()):
                            $free_available = ($c['free_gallons_earned'] ?? 0) - ($c['free_gallons_used'] ?? 0);
                        ?>
                        <tr>
                            <td><?php echo $c['user_id']; ?></td>
                            <td>
                                <div class="customer-name"><?php echo htmlspecialchars($c['full_name']); ?></div>
                                <div class="customer-email"><?php echo htmlspecialchars($c['email']); ?></div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($c['phone']); ?><br>
                                <small><?php echo htmlspecialchars(substr($c['address'], 0, 25)) . '...'; ?></small>
                            </td>
                            <td>
                                <strong><?php echo $c['order_count']; ?></strong> orders<br>
                                <small>Total: <?php echo $c['total_orders'] ?? 0; ?></small>
                            </td>
                            <td class="loyalty-info">
                                Consecutive: <?php echo $c['consecutive_orders'] ?? 0; ?><br>
                                <?php if ($free_available > 0): ?>
                                <span class="free"><?php echo $free_available; ?> free gallon(s)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $c['status']; ?>">
                                    <?php echo ucfirst($c['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                            <td class="actions">
                                <button
                                    class="btn btn-primary btn-sm"
                                    data-user-id="<?php echo $c['user_id']; ?>"
                                    data-name="<?php echo htmlspecialchars($c['full_name']); ?>"
                                    data-email="<?php echo htmlspecialchars($c['email']); ?>"
                                    data-phone="<?php echo htmlspecialchars($c['phone']); ?>"
                                    data-address="<?php echo htmlspecialchars($c['address']); ?>"
                                    data-status="<?php echo htmlspecialchars(strtoupper($c['status'])); ?>"
                                    data-joined="<?php echo htmlspecialchars(date('F d, Y', strtotime($c['created_at']))); ?>"
                                    data-order-count="<?php echo (int) $c['order_count']; ?>"
                                    data-total-orders="<?php echo (int) ($c['total_orders'] ?? 0); ?>"
                                    data-consecutive="<?php echo (int) ($c['consecutive_orders'] ?? 0); ?>"
                                    data-free="<?php echo (int) $free_available; ?>"
                                    onclick="viewCustomer(this)"
                                >View</button>
                                <button class="btn btn-warning btn-sm" onclick="editCustomer(<?php echo $c['user_id']; ?>, '<?php echo htmlspecialchars($c['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($c['phone'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($c['address'], ENT_QUOTES); ?>')">Edit</button>

                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $c['user_id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $c['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $c['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo $c['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">CU</div>
                <h3>No customers found</h3>
                <p><?php echo !empty($search) ? 'Try a different search term.' : 'No customers registered yet.'; ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Edit Customer</h3>
                <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="editFullName" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="editPhone">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="editAddress" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('editModal')" style="background: #6c757d; color: white;">Cancel</button>
                    <button type="submit" name="edit_customer" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="viewModal">
        <div class="modal-box" style="max-width: 680px;">
            <div class="modal-header">
                <h3>Customer Details</h3>
                <button class="close-btn" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div id="customerDetails"></div>
        </div>
    </div>

    <script>
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function (event) {
                if (event.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        function editCustomer(id, name, phone, address) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editFullName').value = name;
            document.getElementById('editPhone').value = phone;
            document.getElementById('editAddress').value = address;
            document.getElementById('editModal').classList.add('active');
        }

        function viewCustomer(button) {
            const details = document.getElementById('customerDetails');
            const freeGallons = button.dataset.free !== '0'
                ? '<div class="customer-detail-card"><span class="customer-detail-label">Free Gallons Available</span><span class="customer-detail-value">' + button.dataset.free + '</span></div>'
                : '';

            details.innerHTML = `
                <div class="customer-detail-grid">
                    <div class="customer-detail-card">
                        <span class="customer-detail-label">Customer ID</span>
                        <span class="customer-detail-value">#${button.dataset.userId}</span>
                    </div>
                    <div class="customer-detail-card">
                        <span class="customer-detail-label">Full Name</span>
                        <span class="customer-detail-value">${button.dataset.name}</span>
                    </div>
                    <div class="customer-detail-card">
                        <span class="customer-detail-label">Email</span>
                        <span class="customer-detail-value">${button.dataset.email}</span>
                    </div>
                    <div class="customer-detail-card">
                        <span class="customer-detail-label">Phone</span>
                        <span class="customer-detail-value">${button.dataset.phone || 'Not provided'}</span>
                    </div>
                    <div class="customer-detail-card">
                        <span class="customer-detail-label">Account Status</span>
                        <span class="customer-detail-value">${button.dataset.status}</span>
                    </div>
                    <div class="customer-detail-card">
                        <span class="customer-detail-label">Joined</span>
                        <span class="customer-detail-value">${button.dataset.joined}</span>
                    </div>
                    <div class="customer-detail-card">
                        <span class="customer-detail-label">Order Records</span>
                        <span class="customer-detail-value">${button.dataset.orderCount}</span>
                    </div>
                    <div class="customer-detail-card">
                        <span class="customer-detail-label">Loyalty Total Orders</span>
                        <span class="customer-detail-value">${button.dataset.totalOrders}</span>
                    </div>
                    <div class="customer-detail-card">
                        <span class="customer-detail-label">Consecutive Orders</span>
                        <span class="customer-detail-value">${button.dataset.consecutive}</span>
                    </div>
                    ${freeGallons}
                </div>
                <div class="customer-detail-note">
                    <span class="customer-detail-label">Delivery Address</span>
                    <p>${button.dataset.address || 'No address saved.'}</p>
                </div>
            `;
            document.getElementById('viewModal').classList.add('active');
        }
    </script>
</body>
</html>
