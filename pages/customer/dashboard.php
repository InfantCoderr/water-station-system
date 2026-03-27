<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/order_logic.php';

require_active_session($conn, ['customer'], '../../index.php');

$customer_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get customer info
$customer = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$customer->bind_param("i", $customer_id);
$customer->execute();
$customer_info = $customer->get_result()->fetch_assoc();

// Get loyalty info
ensure_loyalty_record($conn, $customer_id);
$loyalty = $conn->prepare("SELECT * FROM loyalty WHERE customer_id = ?");
$loyalty->bind_param("i", $customer_id);
$loyalty->execute();
$loyalty_info = $loyalty->get_result()->fetch_assoc();

// Get available inventory
$inventory_result = $conn->query("SELECT * FROM inventory WHERE status != 'discontinued' AND stock_quantity > 0 ORDER BY item_name ASC");
$inventory_items = $inventory_result ? $inventory_result->fetch_all(MYSQLI_ASSOC) : [];
$inventory_lookup = [];
foreach ($inventory_items as $inventory_item) {
    $inventory_lookup[(int) $inventory_item['inventory_id']] = $inventory_item;
}

$selected_inventory_id = (int) ($_POST['inventory_id'] ?? 0);
$selected_quantity = max(1, (int) ($_POST['quantity'] ?? 1));
$selected_item_name = '';
if ($selected_inventory_id > 0 && isset($inventory_lookup[$selected_inventory_id])) {
    $selected_item_name = $inventory_lookup[$selected_inventory_id]['item_name'];
}

function get_inventory_preview_image($item_name) {
    $normalized_name = strtolower($item_name);

    if (strpos($normalized_name, 'slim') !== false) {
        return '../../image.gif/slim.png';
    }

    if (strpos($normalized_name, 'round') !== false || strpos($normalized_name, 'jug') !== false || strpos($normalized_name, 'gallon') !== false) {
        return '../../image.gif/water%20jug.png';
    }

    return '../../image.gif/water.png';
}

// Handle new order
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $inventory_id = (int) ($_POST['inventory_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $delivery_date = $_POST['delivery_date'] ?? '';
    $contact_number = trim($_POST['contact_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($inventory_id < 1 || $quantity < 1 || empty($delivery_address) || empty($delivery_date) || empty($contact_number)) {
        $error = "Please fill in all required fields.";
    } elseif ($delivery_date < date('Y-m-d')) {
        $error = "Delivery date cannot be in the past.";
    } else {
        $conn->begin_transaction();
        try {
            $item_data = reserve_inventory_stock($conn, $inventory_id, $quantity);
            $unit_price = (float) $item_data['unit_price'];
            $total_amount = $unit_price * $quantity;

            $order = $conn->prepare("INSERT INTO orders (customer_id, delivery_date, delivery_address, contact_number, total_amount, order_status, payment_status, notes) VALUES (?, ?, ?, ?, ?, 'pending', 'pending', ?)");
            $order->bind_param("isssds", $customer_id, $delivery_date, $delivery_address, $contact_number, $total_amount, $notes);
            if (!$order->execute()) {
                throw new Exception("Failed to place order. Please try again.");
            }
            $order_id = $conn->insert_id;
            $order->close();

            $item = $conn->prepare("INSERT INTO order_items (order_id, inventory_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            $item->bind_param("iiid", $order_id, $inventory_id, $quantity, $unit_price);
            if (!$item->execute()) {
                throw new Exception("Failed to save order items.");
            }
            $item->close();

            $assigned_staff = auto_assign_order_to_staff($conn, $order_id);
            $conn->commit();

            if ($assigned_staff) {
                header("Location: dashboard.php?ordered=1&order_id=$order_id&assigned=1");
            } else {
                header("Location: dashboard.php?ordered=1&order_id=$order_id&no_staff=1");
            }
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Handle cancel order - REVAMPED LOYALTY RULES
if (isset($_POST['cancel_order'])) {
    $order_id = (int) ($_POST['order_id'] ?? 0);

    $conn->begin_transaction();
    try {
        $check = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ? AND customer_id = ? FOR UPDATE");
        $check->bind_param("ii", $order_id, $customer_id);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$result) {
            throw new Exception("Order not found.");
        }

        $had_penalty = in_array($result['order_status'], ['confirmed', 'processing', 'out_for_delivery'], true);
        transition_order_status($conn, $order_id, 'cancelled');
        $conn->commit();

        header("Location: dashboard.php?cancelled=1" . ($had_penalty ? '&penalty=1' : ''));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}


// Check for success messages
if (isset($_GET['ordered']) && isset($_GET['order_id'])) {
    if (isset($_GET['assigned'])) {
        $success = "Order placed successfully! Order #" . $_GET['order_id'] . " has been assigned to a staff member and will be delivered soon.";
    } elseif (isset($_GET['no_staff'])) {
        $success = "Order placed successfully! Order #" . $_GET['order_id'] . " is pending - waiting for admin to assign staff.";
    } else {
        $success = "Order placed successfully! Order ID: #" . $_GET['order_id'];
    }
} elseif (isset($_GET['cancelled'])) {
    if (isset($_GET['penalty'])) {
        // Use alert for penalty - more attention-grabbing
        echo "<script>alert('ORDER CANCELLED\\n\\nYour consecutive orders have been reset to 0.\\nYour loyalty progress was lost.');</script>";
        $success = ""; // Clear so modal doesn't show
    } else {
        $success = "Order cancelled successfully. No penalty since order was still pending.";
    }
}

// Get customer's recent orders
$orders = $conn->prepare("SELECT o.*, i.item_name FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN inventory i ON oi.inventory_id = i.inventory_id WHERE o.customer_id = ? ORDER BY o.order_date DESC LIMIT 5");
$orders->bind_param("i", $customer_id);
$orders->execute();
$recent_orders = $orders->get_result();

// Get free gallon redemptions
$free_gallons = $conn->prepare("SELECT COUNT(*) as count FROM free_gallon_redemptions WHERE customer_id = ? AND status = 'active'");
$free_gallons->bind_param("i", $customer_id);
$free_gallons->execute();
$available_free = $free_gallons->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link rel="stylesheet" href="../../style/customer/dashboard.css?v=20260325">
</head>
<body>
            <!-- Success Popup Modal -->
        <?php if (!empty($success)): ?>
        <div class="modal-overlay active" id="successModal" style="display: flex;">
            <div class="modal-box">
                <div class="modal-icon success">OK</div>
                <h3>Success!</h3>
                <p><?php echo htmlspecialchars($success); ?></p>
                <button class="modal-btn success" onclick="document.getElementById('successModal').style.display='none'">Awesome!</button>
            </div>
        </div>
        <?php endif; ?>

    <!-- Error Popup Modal -->
    <?php if (!empty($error)): ?>
    <div class="modal-overlay active" id="errorModal">
        <div class="modal-box">
            <div class="modal-icon error">ERR</div>
            <h3>Oops!</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
            <button class="modal-btn error" onclick="closeModal('errorModal')">Try Again</button>
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
        <!-- Welcome -->
        <div class="welcome">
            <h2>Welcome back, <?php echo htmlspecialchars($customer_info['full_name']); ?>!</h2>
            <p style="color: #666;">Manage your water deliveries and track your orders.</p>
        </div>

        <!-- Loyalty Card -->
        <div class="loyalty-card">
            <h3>Loyalty Program</h3>
            <div class="loyalty-stats">
                <div class="loyalty-stat">
                    <div class="number"><?php echo $loyalty_info['consecutive_orders'] ?? 0; ?></div>
                    <div class="label">Consecutive Orders</div>
                </div>
                <div class="loyalty-stat">
                    <div class="number"><?php echo $loyalty_info['total_orders'] ?? 0; ?></div>
                    <div class="label">Total Orders</div>
                </div>
                <div class="loyalty-stat">
                    <div class="number"><?php echo $available_free; ?></div>
                    <div class="label">Free Gallons Available</div>
                </div>
            </div>
            <div class="progress-bar">
                <?php 
                $consecutive = $loyalty_info['consecutive_orders'] ?? 0;
                $progress = ($consecutive % 5) * 20;
                $orders_needed = 5 - ($consecutive % 5);
                if ($orders_needed == 5) $orders_needed = 0;
                ?>
                <div class="progress-fill" style="width: <?php echo $progress; ?>%">
                    <?php echo $consecutive % 5; ?>/5
                </div>
            </div>
            <p style="margin-top: 10px; font-size: 14px;">
                <?php if ($orders_needed > 0): ?>
                    Order <?php echo $orders_needed; ?> more times to earn a FREE gallon!
                <?php else: ?>
                    You earned a free gallon! Next one in 5 orders.
                <?php endif; ?>
            </p>
            <p style="margin-top: 5px; font-size: 12px; opacity: 0.8;">
                *Cancel after confirmation resets your progress
            </p>
        </div>

        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="dashboard.php" class="nav-tab active">Place Order</a>
            <a href="history.php" class="nav-tab">Order History</a>
            <a href="profile.php" class="nav-tab">My Profile</a>
        </div>

        <!-- Place Order -->
        <div class="section section-place-order">
            <div class="order-builder-header">
                <span class="order-builder-kicker">Place Order</span>
                <h2>Choose a water container and complete the delivery form</h2>
                <p class="order-builder-copy">Select the round or slim jug first, review the estimated value, and then enter the delivery details below.</p>
            </div>
            <?php if (!empty($inventory_items)): ?>
            <div class="place-order-shell">
                <div class="product-selector-shell">
                    <button type="button" class="selector-arrow" onclick="scrollInventory(-1)" aria-label="Show previous water containers">&lsaquo;</button>
                    <div class="product-selector-track" id="inventory_selector_track">
                    <?php foreach ($inventory_items as $item): ?>
                    <?php
                        $item_id = (int) $item['inventory_id'];
                        $item_name = $item['item_name'];
                        $item_price = (float) $item['unit_price'];
                        $item_stock = (int) $item['stock_quantity'];
                        $item_image = get_inventory_preview_image($item_name);
                        $is_selected = $selected_inventory_id === $item_id;
                    ?>
                    <article
                        class="product-choice-card<?php echo $is_selected ? ' active' : ''; ?>"
                        data-item-card
                        data-inventory-id="<?php echo $item_id; ?>"
                        data-item-name="<?php echo htmlspecialchars($item_name); ?>"
                        data-item-price="<?php echo $item_price; ?>"
                        onclick="selectInventoryById(<?php echo $item_id; ?>)"
                    >
                        <div class="product-choice-frame">
                            <div class="product-choice-media">
                                <img src="<?php echo htmlspecialchars($item_image); ?>" alt="<?php echo htmlspecialchars($item_name); ?>">
                            </div>
                            <button
                                type="button"
                                class="product-choice-button"
                                data-item-button
                                aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
                                onclick="event.stopPropagation(); selectInventoryById(<?php echo $item_id; ?>)"
                            >
                                <?php echo $is_selected ? 'Selected' : 'Select'; ?>
                            </button>
                        </div>
                        <div class="product-choice-copy">
                            <span class="product-choice-label">Water Container</span>
                            <strong><?php echo htmlspecialchars($item_name); ?></strong>
                            <span>&#8369;<?php echo number_format($item_price, 2); ?> each</span>
                            <small><?php echo $item_stock; ?> gallon(s) available</small>
                        </div>
                    </article>
                    <?php endforeach; ?>
                    </div>
                    <button type="button" class="selector-arrow" onclick="scrollInventory(1)" aria-label="Show next water containers">&rsaquo;</button>
                </div>

                <form method="POST" action="" class="place-order-form">
                    <input type="hidden" name="inventory_id" id="inventory_id" value="<?php echo $selected_inventory_id > 0 ? $selected_inventory_id : ''; ?>">
                    <div class="order-form-grid">
                        <div class="two-col order-form-top-row">
                            <div class="form-group">
                                <label>Selected Water Type</label>
                                <input
                                    type="text"
                                    id="selected_item_name"
                                    class="selected-item-input"
                                    value="<?php echo htmlspecialchars($selected_item_name); ?>"
                                    placeholder="Select a round or slim jug above"
                                    readonly
                                >
                            </div>

                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" name="quantity" id="quantity" min="1" max="10" value="<?php echo $selected_quantity; ?>" required onchange="updatePrice()">
                            </div>
                        </div>

                        <div class="price-display">
                            <span class="price-display-label">Estimated Value</span>
                            <strong>&#8369;<span id="total_price">0.00</span></strong>
                        </div>

                        <div class="form-group">
                            <label>Delivery Address</label>
                            <input type="text" name="delivery_address" value="<?php echo htmlspecialchars($_POST['delivery_address'] ?? $customer_info['address']); ?>" required>
                        </div>

                        <div class="two-col order-form-details-row">
                            <div class="form-group">
                                <label>Preferred Delivery Date</label>
                                <input type="date" name="delivery_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['delivery_date'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="contact_number" value="<?php echo htmlspecialchars($_POST['contact_number'] ?? $customer_info['phone']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Delivery Notes (Optional)</label>
                            <textarea name="notes" rows="4" placeholder="e.g., Ring bell twice, leave at gate"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" name="place_order" class="place-order-submit">Place Order</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">WG</div>
                <h3>No water containers available</h3>
                <p>Please check back later or contact the station administrator.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Orders -->
        <div class="section">
            <h2>Recent Orders</h2>
            <?php if ($recent_orders->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Item</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $recent_orders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $order['order_id']; ?></td>
                        <td><?php echo htmlspecialchars($order['item_name']); ?></td>
                        <td>&#8369;<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <span class="status status-<?php echo $order['order_status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                            </span>
                            <?php if (in_array($order['order_status'], ['pending', 'confirmed', 'processing'])): ?>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirmCancel('<?php echo $order['order_status']; ?>')">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <button type="submit" name="cancel_order" class="btn-cancel">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div style="margin-top: 15px; text-align: center;">
                <a href="history.php" style="color: #667eea; text-decoration: none;">View Full Order History</a>
            </div>
            <?php else: ?>
            <p style="color: #666; text-align: center; padding: 20px;">No orders yet. Place your first order above!</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updatePrice() {
            const quantityInput = document.getElementById('quantity');
            const inventoryInput = document.getElementById('inventory_id');
            const totalPrice = document.getElementById('total_price');

            if (!quantityInput || !inventoryInput || !totalPrice) {
                return;
            }

            const quantity = Number(quantityInput.value || 0);
            const inventoryId = inventoryInput.value;
            const selectedCard = document.querySelector(`[data-item-card][data-inventory-id="${inventoryId}"]`);
            const price = Number(selectedCard?.dataset.itemPrice || 0);
            const total = price * quantity;

            totalPrice.textContent = total.toFixed(2);
        }

        function selectInventoryById(inventoryId) {
            const cards = document.querySelectorAll('[data-item-card]');
            const selectedCard = document.querySelector(`[data-item-card][data-inventory-id="${inventoryId}"]`);
            const hiddenInput = document.getElementById('inventory_id');
            const selectedNameInput = document.getElementById('selected_item_name');

            if (!selectedCard || !hiddenInput || !selectedNameInput) {
                return;
            }

            cards.forEach(card => {
                const button = card.querySelector('[data-item-button]');
                card.classList.remove('active');
                if (button) {
                    button.textContent = 'Select';
                    button.setAttribute('aria-pressed', 'false');
                }
            });

            selectedCard.classList.add('active');
            hiddenInput.value = inventoryId;
            selectedNameInput.value = selectedCard.dataset.itemName || '';

            const selectedButton = selectedCard.querySelector('[data-item-button]');
            if (selectedButton) {
                selectedButton.textContent = 'Selected';
                selectedButton.setAttribute('aria-pressed', 'true');
            }

            selectedCard.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            updatePrice();
        }

        function scrollInventory(direction) {
            const track = document.getElementById('inventory_selector_track');
            if (!track) {
                return;
            }

            const scrollAmount = Math.max(track.clientWidth * 0.75, 220);
            track.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        
       function confirmCancel(status) {
    if (status === 'pending') {
        return confirm('Cancel this order?\n\nNo penalty. The order is still pending.');
    } else {
        return confirm('Warning: this order is already confirmed and being prepared for delivery.\n\nCancelling now will reset your loyalty progress to 0.\nYou will lose your current consecutive-order streak.\n\nDo you want to continue?');
    }
    }

    updatePrice();
    </script>
</body>
</html>
