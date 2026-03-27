<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/order_logic.php';

require_active_session($conn, ['admin'], '../../index.php');

$success = '';
$error = '';

if (isset($_POST['update_stock'])) {
    $inventory_id = (int) ($_POST['inventory_id'] ?? 0);
    $new_stock = intval($_POST['new_stock'] ?? 0);
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $reorder_level = intval($_POST['reorder_level'] ?? 10);

    if ($inventory_id < 1 || $new_stock < 0 || $unit_price <= 0 || $reorder_level < 1) {
        $error = "Please enter valid stock, price, and reorder values.";
    } else {
        $stmt = $conn->prepare("UPDATE inventory SET stock_quantity = ?, unit_price = ?, reorder_level = ?, updated_by = ? WHERE inventory_id = ?");
        $admin_id = (int) $_SESSION['user_id'];
        $stmt->bind_param("idiii", $new_stock, $unit_price, $reorder_level, $admin_id, $inventory_id);
        if ($stmt->execute()) {
            sync_inventory_status($conn, $inventory_id);
            $success = "Inventory updated successfully!";
        } else {
            $error = "Failed to update inventory.";
        }
        $stmt->close();
    }
}

if (isset($_POST['add_item'])) {
    $item_name = trim($_POST['item_name'] ?? '');
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $reorder_level = intval($_POST['reorder_level'] ?? 10);

    if (empty($item_name) || $stock_quantity < 0 || $unit_price <= 0) {
        $error = "Please fill in all fields with valid values.";
    } else {
        $check = $conn->prepare("SELECT inventory_id FROM inventory WHERE item_name = ?");
        $check->bind_param("s", $item_name);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Item with this name already exists.";
        } else {
            $status = $stock_quantity > 0 ? 'available' : 'out_of_stock';
            $stmt = $conn->prepare("INSERT INTO inventory (item_name, stock_quantity, unit_price, reorder_level, status, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $admin_id = (int) $_SESSION['user_id'];
            $stmt->bind_param("sidisi", $item_name, $stock_quantity, $unit_price, $reorder_level, $status, $admin_id);

            if ($stmt->execute()) {
                $success = "New item added to inventory!";
            } else {
                $error = "Failed to add item.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

if (isset($_POST['delete_item'])) {
    $inventory_id = $_POST['inventory_id'] ?? '';

    $check = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE inventory_id = ?");
    $check->bind_param("i", $inventory_id);
    $check->execute();
    $order_count = $check->get_result()->fetch_assoc()['count'];

    if ($order_count > 0) {
        $stmt = $conn->prepare("UPDATE inventory SET status = 'discontinued', updated_by = ? WHERE inventory_id = ?");
        $admin_id = (int) $_SESSION['user_id'];
        $stmt->bind_param("ii", $admin_id, $inventory_id);
        if ($stmt->execute()) {
            $success = "Item marked as discontinued (has order history).";
        } else {
            $error = "Failed to update item status.";
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("DELETE FROM inventory WHERE inventory_id = ?");
        $stmt->bind_param("i", $inventory_id);
        if ($stmt->execute()) {
            $success = "Item deleted successfully.";
        }
        $stmt->close();
    }
    $check->close();
}

if (isset($_POST['toggle_status'])) {
    $inventory_id = (int) ($_POST['inventory_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if ($inventory_id > 0 && in_array($new_status, ['available', 'discontinued'], true)) {
        $stmt = $conn->prepare("UPDATE inventory SET status = ?, updated_by = ? WHERE inventory_id = ?");
        $admin_id = (int) $_SESSION['user_id'];
        $stmt->bind_param("sii", $new_status, $admin_id, $inventory_id);
        if ($stmt->execute()) {
            sync_inventory_status($conn, $inventory_id);
            $success = "Item status updated to " . str_replace('_', ' ', $new_status);
        } else {
            $error = "Failed to update item status.";
        }
        $stmt->close();
    }
}

$inventory = $conn->query("SELECT * FROM inventory ORDER BY inventory_id ASC");
$low_stock = $conn->query("SELECT * FROM inventory WHERE stock_quantity <= reorder_level AND status != 'discontinued'");
$low_stock_count = $low_stock->num_rows;
$total_value = $conn->query("SELECT SUM(stock_quantity * unit_price) as total FROM inventory WHERE status != 'discontinued'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - ISRAPHIL Admin</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link rel="stylesheet" href="../../style/admin/inventory.css?v=20260325">
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
            <a href="inventory.php" class="nav-tab active">Inventory</a>
            <a href="staff.php" class="nav-tab">Staff</a>
            <a href="customers.php" class="nav-tab">Customers</a>
        </div>

        <div class="page-intro">
            <span class="page-intro-kicker">Inventory Control</span>
            <h2>Protect stock availability before orders back up</h2>
            <p>Use this inventory workspace to price gallon items, set reorder thresholds, and keep high-demand stock visible to the team.</p>
            <div class="page-intro-actions">
                <a href="#inventory-form" class="btn btn-success">Add inventory item</a>
                <a href="#inventory-list" class="btn btn-primary">Review inventory list</a>
            </div>
        </div>

        <?php if ($low_stock_count > 0): ?>
        <div class="alert-banner warning">
            <div class="alert-icon">LOW</div>
            <div class="alert-content warning">
                <h3>Low Stock Alert</h3>
                <p><?php echo $low_stock_count; ?> item(s) are at or below reorder level. Restock soon.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats-bar">
            <div class="stat-card">
                <div class="number"><?php echo $inventory->num_rows; ?></div>
                <div class="label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="number <?php echo $low_stock_count > 0 ? 'warning' : ''; ?>"><?php echo $low_stock_count; ?></div>
                <div class="label">Low Stock Items</div>
            </div>
            <div class="stat-card">
                <div class="number">&#8369;<?php echo number_format($total_value, 2); ?></div>
                <div class="label">Total Stock Value</div>
            </div>
        </div>

        <div class="section" id="inventory-form">
            <h2>Add New Item</h2>
            <p class="section-copy">Create inventory records with accurate stock, pricing, and reorder settings before the item becomes available for ordering.</p>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Item Name *</label>
                        <input type="text" name="item_name" placeholder="e.g., 5-Gallon Purified" required>
                    </div>
                    <div class="form-group">
                        <label>Initial Stock *</label>
                        <input type="number" name="stock_quantity" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Unit Price (&#8369;) *</label>
                        <input type="number" name="unit_price" min="0.01" step="0.01" placeholder="25.00" required>
                    </div>
                    <div class="form-group">
                        <label>Reorder Level</label>
                        <input type="number" name="reorder_level" min="1" value="10">
                    </div>
                </div>
                <button type="submit" name="add_item" class="btn btn-success">Add Item</button>
            </form>
        </div>

        <div class="section" id="inventory-list">
            <h2>Inventory List</h2>
            <p class="section-copy">Track every item currently available for ordering, identify low-stock risk quickly, and adjust visibility when products should be hidden from customers.</p>

            <?php if ($inventory->num_rows > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Stock</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $inventory->fetch_assoc()):
                            $is_low = $item['stock_quantity'] <= $item['reorder_level'];
                        ?>
                        <tr>
                            <td><?php echo $item['inventory_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong><br>
                                <small>Reorder at: <?php echo $item['reorder_level']; ?> gallons</small>
                            </td>
                            <td class="<?php echo $is_low ? 'stock-low' : 'stock-ok'; ?>">
                                <?php echo $item['stock_quantity']; ?> gallons
                                <?php if ($is_low): ?>
                                    <br><small class="text-danger">Low stock</small>
                                <?php endif; ?>
                            </td>
                            <td class="price-display">&#8369;<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td>
                                <?php if ($item['status'] === 'available'): ?>
                                    <span class="status-badge status-available">Available</span>
                                <?php elseif ($item['status'] === 'out_of_stock'): ?>
                                    <span class="status-badge status-out_of_stock">Out of Stock</span>
                                <?php else: ?>
                                    <span class="status-badge status-discontinued">Discontinued</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <button class="btn btn-primary btn-sm" onclick="editItem(<?php echo $item['inventory_id']; ?>, '<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>', <?php echo $item['stock_quantity']; ?>, <?php echo $item['unit_price']; ?>, <?php echo $item['reorder_level']; ?>)">Edit</button>

                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $item['status'] === 'discontinued' ? 'available' : 'discontinued'; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $item['status'] === 'discontinued' ? 'btn-success' : 'btn-warning'; ?>">
                                        <?php echo $item['status'] === 'discontinued' ? 'Restore' : 'Hide'; ?>
                                    </button>
                                </form>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this item?');">
                                    <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                    <button type="submit" name="delete_item" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">INV</div>
                <h3>No items in inventory</h3>
                <p>Add your first item above.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Edit Item</h3>
                <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="inventory_id" id="editInventoryId">
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" id="editItemName" disabled style="background: rgba(255, 255, 255, 0.06); color: #9db3c7;">
                    <small>Item name cannot be changed</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Current Stock (gallons)</label>
                        <input type="number" name="new_stock" id="editStock" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Unit Price (&#8369;)</label>
                        <input type="number" name="unit_price" id="editPrice" min="0.01" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Reorder Level</label>
                    <input type="number" name="reorder_level" id="editReorder" min="1" required>
                    <small>Alert when stock falls below this level</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('editModal')" style="background: #6c757d; color: white;">Cancel</button>
                    <button type="submit" name="update_stock" class="btn btn-success">Save Changes</button>
                </div>
            </form>
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

        function editItem(id, name, stock, price, reorder) {
            document.getElementById('editInventoryId').value = id;
            document.getElementById('editItemName').value = name;
            document.getElementById('editStock').value = stock;
            document.getElementById('editPrice').value = price;
            document.getElementById('editReorder').value = reorder;
            document.getElementById('editModal').classList.add('active');
        }
    </script>
</body>
</html>
