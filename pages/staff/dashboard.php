<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/order_logic.php';

require_active_session($conn, ['staff'], '../../index.php');

$staff_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle mark as delivered
if (isset($_POST['mark_delivered'])) {
    $delivery_id = (int) ($_POST['delivery_id'] ?? 0);
    if ($delivery_id > 0) {
        $conn->begin_transaction();
        try {
            $order_id = mark_delivery_as_delivered($conn, $delivery_id, $staff_id);
            $conn->commit();
            $success = "Order #$order_id marked as delivered.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Handle mark as failed/returned
if (isset($_POST['mark_failed'])) {
    $delivery_id = intval($_POST['delivery_id'] ?? 0);
    $reason = trim($_POST['failure_reason'] ?? 'Delivery failed');
    if ($delivery_id > 0) {
        $conn->begin_transaction();
    
        try {
            $order_id = mark_delivery_as_failed($conn, $delivery_id, $staff_id, $reason);
            $conn->commit();
            $success = "Delivery failed for order #$order_id. The order is pending reassignment.";
        // Keep the failed delivery record for history.
        
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
// Get filter
$status_filter = sanitize_status_filter($_GET['status'] ?? 'assigned', ['all', 'assigned', 'picked_up', 'in_transit', 'delivered', 'failed', 'returned'], 'assigned');
$where_clause = "AND d.delivery_status = ?";
if ($status_filter === 'all') {
    $where_clause = "";
}

// Get assigned deliveries
$deliveries_query = "
    SELECT d.*, o.order_id, o.customer_id, o.delivery_address, o.contact_number, 
           o.delivery_date, o.total_amount, o.notes as order_notes,
           i.item_name, oi.quantity,
           c.full_name as customer_name, c.phone as customer_phone
    FROM deliveries d
    JOIN orders o ON d.order_id = o.order_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN inventory i ON oi.inventory_id = i.inventory_id
    JOIN users c ON o.customer_id = c.user_id
    WHERE d.staff_id = ? $where_clause
    ORDER BY o.delivery_date ASC, d.assigned_at ASC
";

$stmt = $conn->prepare($deliveries_query);
if ($status_filter === 'all') {
    $stmt->bind_param("i", $staff_id);
} else {
    $stmt->bind_param("is", $staff_id, $status_filter);
}
$stmt->execute();
$deliveries = $stmt->get_result();

// Get counts
$counts = $conn->query("
    SELECT 
        SUM(CASE WHEN delivery_status = 'assigned' THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN delivery_status = 'in_transit' THEN 1 ELSE 0 END) in_transit,
        SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) as failed,
        COUNT(*) as total
    FROM deliveries WHERE staff_id = $staff_id
")->fetch_assoc();

// Get today's deliveries
$today = date('Y-m-d');
$today_count = $conn->query("
    SELECT COUNT(*) as count FROM deliveries d
    JOIN orders o ON d.order_id = o.order_id
    WHERE d.staff_id = $staff_id 
    AND (DATE(o.delivery_date) = '$today' OR d.delivery_status = 'assigned')
    AND d.delivery_status != 'delivered'
")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link rel="stylesheet" href="../../style/staff/dashboard.css?v=20260325">
</head>
<body>
    <!-- Success/Error Modals -->
    <?php if (!empty($success)): ?>
    <div class="modal-overlay active" id="successModal">
        <div class="modal-box" style="text-align: center;">
            <div class="modal-icon success">OK</div>
            <h3>Success!</h3>
            <p><?php echo htmlspecialchars($success); ?></p>
            <button class="btn btn-success" onclick="closeModal('successModal')" style="margin-top: 15px;">OK</button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
    <div class="modal-overlay active" id="errorModal">
        <div class="modal-box" style="text-align: center;">
            <div class="modal-icon error">ERR</div>
            <h3>Error</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
            <button class="btn btn-danger" onclick="closeModal('errorModal')" style="margin-top: 15px;">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Failure Reason Modal -->
    <div class="modal-overlay" id="failModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Mark as Failed</h3>
                <button class="close-btn" onclick="closeModal('failModal')">&times;</button>
            </div>
            <p style="margin-bottom: 15px;">Please provide a reason for delivery failure:</p>
            <form method="POST" id="failForm">
                <input type="hidden" name="delivery_id" id="failDeliveryId">
                <textarea name="failure_reason" placeholder="e.g., Customer not home, Wrong address, etc." required></textarea>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="closeModal('failModal')" style="background: #6c757d;">Cancel</button>
                    <button type="submit" name="mark_failed" class="btn btn-danger">Confirm Failed</button>
                </div>
            </form>
        </div>
    </div>

    <div class="header">
        <h1>ISRAPHIL Delivery Team</h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Today's Alert -->
        <?php if ($today_count > 0): ?>
        <div class="alert-today">
            <div class="alert-icon">TODAY</div>
            <div class="alert-content">
                <h3>You have <?php echo $today_count; ?> delivery(s) today!</h3>
                <p>Check your assigned orders below.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="number warning"><?php echo $counts['assigned']; ?></div>
                <div class="label">Assigned</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $counts['in_transit']; ?></div>
                <div class="label">In Transit</div>
            </div>
            <div class="stat-card">
                <div class="number success"><?php echo $counts['delivered']; ?></div>
                <div class="label">Delivered</div>
            </div>
            <div class="stat-card">
                <div class="number danger"><?php echo $counts['failed']; ?></div>
                <div class="label">Failed</div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <a href="?status=all" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                All <span class="count"><?php echo $counts['total']; ?></span>
            </a>
            <a href="?status=assigned" class="filter-btn <?php echo $status_filter == 'assigned' ? 'active' : ''; ?>">
                Assigned <span class="count"><?php echo $counts['assigned']; ?></span>
            </a>
            <a href="?status=in_transit" class="filter-btn <?php echo $status_filter == 'in_transit' ? 'active' : ''; ?>">
                In Transit <span class="count"><?php echo $counts['in_transit']; ?></span>
            </a>
            <a href="?status=delivered" class="filter-btn <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
                Delivered <span class="count"><?php echo $counts['delivered']; ?></span>
            </a>
            <a href="?status=failed" class="filter-btn <?php echo $status_filter == 'failed' ? 'active' : ''; ?>">
                Failed <span class="count"><?php echo $counts['failed']; ?></span>
            </a>
        </div>

        <!-- Deliveries List -->
        <div class="section">
            <div class="section-header">
                <div>
                    <h2>My Deliveries</h2>
                    <p class="section-copy">Track assigned drops, customer details, and completion updates from one delivery board.</p>
                </div>
            </div>
            
            <?php if ($deliveries->num_rows > 0): ?>
                <?php while ($d = $deliveries->fetch_assoc()): 
                    $is_today = ($d['delivery_date'] == date('Y-m-d')) && $d['delivery_status'] == 'assigned';
                ?>
                <div class="delivery-card <?php echo $is_today ? 'urgent' : ''; ?>">
                    <div class="delivery-header">
                        <div>
                            <div class="order-id">Order #<?php echo $d['order_id']; ?></div>
                            <div class="delivery-date">
                                <?php if ($d['delivery_date']): ?>
                                    Scheduled: <?php echo date('M d, Y', strtotime($d['delivery_date'])); ?>
                                    <?php if ($is_today): ?> <strong style="color: #ffc107;">(TODAY)</strong><?php endif; ?>
                                <?php else: ?>
                                    No preferred date
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status status-<?php echo $d['delivery_status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $d['delivery_status'])); ?>
                        </span>
                    </div>
                    
                    <div class="customer-info">
                        <strong><?php echo htmlspecialchars($d['customer_name']); ?></strong><br>
                        <?php echo htmlspecialchars($d['customer_phone']); ?>
                    </div>
                    
                    <div class="delivery-details">
                        <div class="detail-group">
                            <span class="detail-label">Item</span>
                            <span class="detail-value"><?php echo htmlspecialchars($d['item_name']); ?> (<?php echo $d['quantity']; ?> gal)</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Total</span>
                            <span class="detail-value price">&#8369;<?php echo number_format($d['total_amount'], 2); ?></span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Payment Method</span>
                            <span class="detail-value">Cash on Delivery</span>
                        </div>
                    </div>
                    
                    <div class="detail-group" style="margin-bottom: 10px;">
                        <span class="detail-label">Delivery Address</span>
                        <span class="detail-value"><?php echo htmlspecialchars($d['delivery_address']); ?></span>
                    </div>
                    
                    <?php if ($d['order_notes']): ?>
                    <div class="note-card warning">
                        <span class="detail-label">Delivery Note</span>
                        <p><?php echo htmlspecialchars($d['order_notes']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($d['delivery_status'] !== 'delivered' && $d['delivery_status'] !== 'failed'): ?>
                    <div class="actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="delivery_id" value="<?php echo $d['delivery_id']; ?>">
                            <button type="submit" name="mark_delivered" class="btn btn-success">Mark Delivered</button>
                        </form>
                        
                        <button type="button" class="btn btn-warning" onclick="openFailModal(<?php echo $d['delivery_id']; ?>, <?php echo $d['order_id']; ?>)">Mark Failed</button>
                    </div>
                    <?php elseif ($d['delivered_at']): ?>
                    <div class="delivery-complete">
                        Delivered on <?php echo date('M d, Y g:i A', strtotime($d['delivered_at'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">DL</div>
                    <h3>No deliveries found</h3>
                    <p>You don't have any <?php echo $status_filter !== 'all' ? $status_filter : ''; ?> deliveries.</p>
                </div>
            <?php endif; ?>
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
        
        function openFailModal(deliveryId) {
            document.getElementById('failDeliveryId').value = deliveryId;
            document.getElementById('failModal').classList.add('active');
        }
    </script>
</body>
</html>
