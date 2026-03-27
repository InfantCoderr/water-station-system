<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/order_logic.php';

require_active_session($conn, ['admin'], '../../index.php');

$success = '';
$error = '';

if (isset($_POST['update_status'])) {
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if ($order_id > 0 && !empty($new_status)) {
        $conn->begin_transaction();
        try {
            transition_order_status($conn, $order_id, $new_status);
            $conn->commit();
            $success = "Order #$order_id status updated to " . ucfirst(str_replace('_', ' ', $new_status));
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

if (isset($_POST['assign_staff'])) {
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $staff_id = (int) ($_POST['staff_id'] ?? 0);

    if ($order_id > 0 && $staff_id > 0) {
        $conn->begin_transaction();
        try {
            assign_order_to_staff($conn, $order_id, $staff_id, (int) $_SESSION['user_id'], 'manual');
            $conn->commit();
            $success = "Staff assigned to order #$order_id successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$status_filter = sanitize_status_filter($_GET['status'] ?? 'all', ['all', 'pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered', 'cancelled']);
$where_clause = '';

$orders_query = "
    SELECT o.*,
           c.full_name as customer_name, c.phone as customer_phone,
           i.item_name, oi.quantity,
           d.delivery_id, d.staff_id, d.delivery_status,
           s.full_name as staff_name
    FROM orders o
    JOIN users c ON o.customer_id = c.user_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN inventory i ON oi.inventory_id = i.inventory_id
    LEFT JOIN deliveries d ON o.order_id = d.order_id
    LEFT JOIN users s ON d.staff_id = s.user_id
    ORDER BY o.order_date DESC
";
if ($status_filter !== 'all') {
    $where_clause = " WHERE o.order_status = ? ";
}

$orders_stmt = $conn->prepare(str_replace("ORDER BY", $where_clause . " ORDER BY", $orders_query));
if ($status_filter !== 'all') {
    $orders_stmt->bind_param("s", $status_filter);
}
$orders_stmt->execute();
$orders = $orders_stmt->get_result();

$staff = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'staff' AND status = 'active'");

$counts = $conn->query("
    SELECT
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN order_status = 'out_for_delivery' THEN 1 ELSE 0 END) as delivering,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        COUNT(*) as total
    FROM orders
")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - ISRAPHIL Admin</title>
    <link rel="stylesheet" href="../../style/admin/orders.css?v=20260325">
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
            <a href="orders.php" class="nav-tab active">All Orders</a>
            <a href="inventory.php" class="nav-tab">Inventory</a>
            <a href="staff.php" class="nav-tab">Staff</a>
            <a href="customers.php" class="nav-tab">Customers</a>
        </div>

        <div class="page-intro">
            <span class="page-intro-kicker">Order Control</span>
            <h2>Manage the full delivery queue from one board</h2>
            <p>Review customer orders, apply status changes deliberately, and route each request to the right delivery staff without leaving the page.</p>
            <div class="page-intro-actions">
                <a href="orders.php?status=pending" class="btn btn-warning">View pending orders</a>
                <a href="orders.php?status=out_for_delivery" class="btn btn-primary">Check live deliveries</a>
            </div>
        </div>

        <div class="filter-bar">
            <a href="orders.php" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                All <span class="count"><?php echo $counts['total']; ?></span>
            </a>
            <a href="orders.php?status=pending" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                Pending <span class="count"><?php echo $counts['pending']; ?></span>
            </a>
            <a href="orders.php?status=confirmed" class="filter-btn <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">
                Confirmed <span class="count"><?php echo $counts['confirmed']; ?></span>
            </a>
            <a href="orders.php?status=out_for_delivery" class="filter-btn <?php echo $status_filter == 'out_for_delivery' ? 'active' : ''; ?>">
                Out for Delivery <span class="count"><?php echo $counts['delivering']; ?></span>
            </a>
            <a href="orders.php?status=delivered" class="filter-btn <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
                Delivered <span class="count"><?php echo $counts['delivered']; ?></span>
            </a>
        </div>

        <div class="section">
            <h2>Order Management</h2>
            <p class="section-copy">Keep approvals, delivery state, and staff assignment aligned so dispatch remains clear for both the admin team and customers.</p>

            <?php if ($orders->num_rows > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Item</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Assigned Staff</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                            <td class="customer-info">
                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                <?php echo htmlspecialchars($order['customer_phone']); ?><br>
                                <small><?php echo htmlspecialchars(substr($order['delivery_address'], 0, 30)) . '...'; ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($order['item_name']); ?><br>
                                <small>Qty: <?php echo $order['quantity']; ?></small>
                            </td>
                            <td><strong>&#8369;<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                            <td>
                                <span class="status status-<?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($order['staff_name']): ?>
                                    <span class="text-success"><?php echo htmlspecialchars($order['staff_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td>
                                <div class="action-stack">
                                    <form method="POST" class="inline-select-form">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="new_status">
                                            <option value="">Update status</option>
                                            <option value="pending">Pending</option>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="out_for_delivery">Out for Delivery</option>
                                            <option value="delivered">Delivered</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                    </form>

                                    <?php if (in_array($order['order_status'], ['pending', 'confirmed', 'processing'])): ?>
                                    <form method="POST" class="inline-select-form">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <input type="hidden" name="assign_staff" value="1">
                                        <select name="staff_id">
                                            <option value="">Assign staff</option>
                                            <?php
                                            $staff->data_seek(0);
                                            while ($s = $staff->fetch_assoc()):
                                            ?>
                                            <option value="<?php echo $s['user_id']; ?>" <?php echo $order['staff_id'] == $s['user_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($s['full_name']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <button type="submit" class="btn btn-success btn-sm">Save</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">OR</div>
                <h3>No orders found</h3>
                <p>There are no <?php echo $status_filter !== 'all' ? $status_filter : ''; ?> orders at the moment.</p>
            </div>
            <?php endif; ?>
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
