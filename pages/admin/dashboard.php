<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';

require_active_session($conn, ['admin'], '../../index.php');

$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'")->fetch_assoc()['count'];
$delivering_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'out_for_delivery'")->fetch_assoc()['count'];
$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
$total_staff = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'staff'")->fetch_assoc()['count'];
$inventory = $conn->query("SELECT * FROM inventory");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link rel="stylesheet" href="../../style/admin/dashboard.css?v=20260325">
</head>
<body class="admin-page">
    <div class="header">
        <h1>ISRAPHIL Admin</h1>
        <div class="user-info">
            <span>Administrator: <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="nav-tabs">
            <a href="dashboard.php" class="nav-tab active">Dashboard</a>
            <a href="orders.php" class="nav-tab">All Orders</a>
            <a href="inventory.php" class="nav-tab">Inventory</a>
            <a href="staff.php" class="nav-tab">Staff</a>
            <a href="customers.php" class="nav-tab">Customers</a>
        </div>

        <div class="page-intro">
            <span class="page-intro-kicker">Admin Operations</span>
            <h2>Operations overview for today's workload</h2>
            <p>Monitor live order volume, staffing coverage, and stock readiness from one control surface built for day-to-day station decisions.</p>
            <div class="page-intro-actions">
                <a href="orders.php?status=pending" class="btn btn-warning">Review pending queue</a>
                <a href="staff.php" class="btn btn-success">Manage staff</a>
                <a href="inventory.php" class="btn btn-primary">Open inventory control</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="number"><?php echo $total_orders; ?></div>
            </div>
            <div class="stat-card pending">
                <h3>Pending Orders</h3>
                <div class="number warning"><?php echo $pending_orders; ?></div>
            </div>
            <div class="stat-card delivering">
                <h3>Out for Delivery</h3>
                <div class="number"><?php echo $delivering_orders; ?></div>
            </div>
            <div class="stat-card customers">
                <h3>Customers</h3>
                <div class="number success"><?php echo $total_customers; ?></div>
            </div>
            <div class="stat-card staff">
                <h3>Staff</h3>
                <div class="number danger"><?php echo $total_staff; ?></div>
            </div>
        </div>

        <div class="section">
            <h2>Inventory Status</h2>
            <p class="section-copy">Review gallon availability, selling price, and stock health before the next delivery wave starts.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Stock</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $inventory->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td class="<?php echo $item['stock_quantity'] < $item['reorder_level'] ? 'stock-low' : 'stock-ok'; ?>">
                                <?php echo $item['stock_quantity']; ?> gallons
                            </td>
                            <td>&#8369;<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td>
                                <span class="status status-<?php echo $item['status']; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="inventory.php?edit=<?php echo $item['inventory_id']; ?>" class="btn btn-primary">Edit Stock</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <h2>Operations Shortcuts</h2>
            <p class="section-copy">Jump directly into the admin actions that affect dispatch, staffing, and stock readiness the most.</p>
            <div class="page-intro-actions">
                <a href="orders.php?status=pending" class="btn btn-warning">Open pending orders</a>
                <a href="staff.php" class="btn btn-success">Add or update staff</a>
                <a href="inventory.php" class="btn btn-primary">Adjust inventory</a>
            </div>
        </div>
    </div>
</body>
</html>
