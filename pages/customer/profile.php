<?php
session_start();
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/address_helpers.php';
require_once __DIR__ . '/../../includes/customer_navbar.php';

require_active_session($conn, ['customer'], '../../index.php');

$customer_id = $_SESSION['user_id'];
$success = '';
$error = '';
ensure_delivery_service_area_schema($conn);
$service_areas = fetch_delivery_service_areas($conn);
$delivery_area_json = delivery_area_index_json($service_areas);

// Get customer info
$customer = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$customer->bind_param("i", $customer_id);
$customer->execute();
$customer_info = $customer->get_result()->fetch_assoc();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
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
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $full_name, $phone, $email, $customer_id);

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

if (isset($_POST['save_delivery_address'])) {
    $address_id = (int) ($_POST['address_id'] ?? 0);
    $label = trim($_POST['address_label'] ?? '');
    $address_parts = delivery_address_from_post();
    $make_default = isset($_POST['is_default']);
    $full_address = format_delivery_address(
        $address_parts['street_address'],
        $address_parts['barangay'],
        $address_parts['city'],
        $address_parts['province']
    );
    $service_area = delivery_address_is_complete($address_parts)
        ? find_delivery_service_area($conn, $address_parts['province'], $address_parts['city'], $address_parts['barangay'])
        : null;

    if ($label === '') {
        $label = 'Delivery Address';
    }

    if (!delivery_address_is_complete($address_parts)) {
        $error = "Please complete the street, province, city, and barangay.";
    } elseif (!$service_area) {
        $error = "This delivery address is outside our current coverage.";
    } else {
        $conn->begin_transaction();
        try {
            if ($make_default) {
                $clear_default = $conn->prepare("UPDATE customer_delivery_addresses SET is_default = 0 WHERE customer_id = ?");
                $clear_default->bind_param("i", $customer_id);
                $clear_default->execute();
                $clear_default->close();
            }

            $service_area_id = (int) ($service_area['area_id'] ?? 0);
            $is_default = $make_default ? 1 : 0;

            if ($address_id > 0) {
                $existing = customer_delivery_address_find($conn, $customer_id, $address_id);
                if (!$existing) {
                    throw new Exception("Delivery address was not found.");
                }

                if (!$make_default && (int) ($existing['is_default'] ?? 0) === 1) {
                    $is_default = 1;
                }

                $stmt = $conn->prepare("UPDATE customer_delivery_addresses SET label = ?, address = ?, street_address = ?, barangay = ?, city = ?, province = ?, service_area_id = ?, is_default = ? WHERE address_id = ? AND customer_id = ?");
                $stmt->bind_param(
                    "ssssssiiii",
                    $label,
                    $full_address,
                    $address_parts['street_address'],
                    $address_parts['barangay'],
                    $address_parts['city'],
                    $address_parts['province'],
                    $service_area_id,
                    $is_default,
                    $address_id,
                    $customer_id
                );
            } else {
                $existing_count = count(customer_delivery_address_rows($conn, $customer_id));
                if ($existing_count === 0) {
                    $is_default = 1;
                }

                $stmt = $conn->prepare("INSERT INTO customer_delivery_addresses (customer_id, label, address, street_address, barangay, city, province, service_area_id, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "issssssii",
                    $customer_id,
                    $label,
                    $full_address,
                    $address_parts['street_address'],
                    $address_parts['barangay'],
                    $address_parts['city'],
                    $address_parts['province'],
                    $service_area_id,
                    $is_default
                );
            }

            if (!$stmt->execute()) {
                throw new Exception("Failed to save delivery address.");
            }
            $stmt->close();
            sync_customer_default_address($conn, $customer_id);
            $conn->commit();
            $success = $address_id > 0 ? "Delivery address updated." : "Delivery address added.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

if (isset($_POST['delete_delivery_address'])) {
    $address_id = (int) ($_POST['address_id'] ?? 0);
    $addresses = customer_delivery_address_rows($conn, $customer_id);

    if ($address_id < 1) {
        $error = "Invalid delivery address.";
    } elseif (count($addresses) <= 1) {
        $error = "Keep at least one delivery address on your account.";
    } else {
        $existing = customer_delivery_address_find($conn, $customer_id, $address_id);
        if (!$existing) {
            $error = "Delivery address was not found.";
        } else {
            $conn->begin_transaction();
            try {
                $was_default = (int) ($existing['is_default'] ?? 0) === 1;
                $stmt = $conn->prepare("DELETE FROM customer_delivery_addresses WHERE address_id = ? AND customer_id = ?");
                $stmt->bind_param("ii", $address_id, $customer_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to delete delivery address.");
                }
                $stmt->close();

                if ($was_default) {
                    $next_default = $conn->prepare("UPDATE customer_delivery_addresses SET is_default = 1 WHERE customer_id = ? ORDER BY created_at ASC, address_id ASC LIMIT 1");
                    $next_default->bind_param("i", $customer_id);
                    $next_default->execute();
                    $next_default->close();
                }

                sync_customer_default_address($conn, $customer_id);
                $conn->commit();
                $success = "Delivery address deleted.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
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

$customer->execute();
$customer_info = $customer->get_result()->fetch_assoc();
$delivery_addresses = customer_delivery_address_rows($conn, $customer_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ISRAPHIL</title>
    <link rel="icon" type="image/png" href="../../image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style/customer/profile.css">
    <link rel="stylesheet" href="../../style/customer/navbar.css">
</head>
<body class="bg-light">
    <?php if (!empty($success)): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast show align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?php echo htmlspecialchars($success); ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast show align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?php echo htmlspecialchars($error); ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php render_customer_navbar('profile'); ?>

    <main class="container py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
            <div>
                <span class="badge text-bg-primary mb-3">My Profile</span>
                <h1 class="h3 fw-bold mb-2">Account Settings</h1>
                <p class="text-secondary mb-0">Manage your contact details, delivery addresses, and password.</p>
            </div>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-4">
                <section class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <span class="badge text-bg-info mb-3">Profile Overview</span>
                        <h2 class="h5 fw-bold mb-2">Account details</h2>
                        <p class="text-secondary small mb-4">Keep your contact details current so every delivery reaches the right place.</p>

                        <div class="border-bottom py-3">
                            <div class="small text-secondary">Username</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($customer_info['username']); ?></div>
                    </div>
                        <div class="border-bottom py-3">
                            <div class="small text-secondary">Member Since</div>
                            <div class="fw-semibold"><?php echo date('F d, Y', strtotime($customer_info['created_at'])); ?></div>
                    </div>
                        <div class="py-3">
                            <div class="small text-secondary">Account Status</div>
                            <span class="badge <?php echo $customer_info['status'] === 'active' ? 'text-bg-success' : 'text-bg-danger'; ?>">
                                <?php echo strtoupper($customer_info['status']); ?>
                            </span>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-8">
                <section class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <span class="badge text-bg-primary mb-3">Edit Profile</span>
                        <h2 class="h5 fw-bold mb-2">Contact and delivery details</h2>
                        <p class="text-secondary small mb-4">Update your phone number and email whenever anything changes.</p>

                    <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input id="full_name" class="form-control form-control-lg" type="text" name="full_name" value="<?php echo htmlspecialchars($customer_info['full_name']); ?>" required autocomplete="name">
                        </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input id="email" class="form-control form-control-lg" type="email" name="email" value="<?php echo htmlspecialchars($customer_info['email']); ?>" required autocomplete="email">
                        </div>

                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input id="phone" class="form-control form-control-lg" type="tel" name="phone" value="<?php echo htmlspecialchars($customer_info['phone']); ?>" placeholder="09123456789" autocomplete="tel">
                        </div>

                                <div class="col-12">
                                    <button type="submit" name="update_profile" class="btn btn-primary btn-lg">Save Changes</button>
                                </div>
                            </div>
                    </form>
                    </div>
                </section>

                <section class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
                            <div>
                                <span class="badge text-bg-info mb-3">Delivery Addresses</span>
                                <h2 class="h5 fw-bold mb-2">Saved delivery locations</h2>
                                <p class="text-secondary small mb-0">Choose fixed service-area locations, then add only the street, house, or landmark details.</p>
                            </div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addressModal" data-mode="add">
                                Add Address
                            </button>
                        </div>

                        <?php if (!empty($delivery_addresses)): ?>
                        <div class="row g-3">
                            <?php foreach ($delivery_addresses as $address): ?>
                            <?php
                                $address_id = (int) ($address['address_id'] ?? 0);
                                $address_label = (string) ($address['label'] ?? 'Delivery Address');
                                $full_address = (string) ($address['address'] ?? '');
                                $street_address = (string) (($address['street_address'] ?? '') !== '' ? $address['street_address'] : $full_address);
                                $barangay = (string) ($address['barangay'] ?? '');
                                $city = (string) ($address['city'] ?? '');
                                $province = (string) ($address['province'] ?? '');
                                $is_default = (int) ($address['is_default'] ?? 0) === 1;
                                $can_delete = count($delivery_addresses) > 1;
                            ?>
                            <div class="col-12">
                                <article class="border rounded-4 p-3 bg-light-subtle">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                        <div>
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                <h3 class="h6 fw-bold mb-0"><?php echo htmlspecialchars($address_label); ?></h3>
                                                <?php if ($is_default): ?>
                                                    <span class="badge text-bg-success">Default</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-2"><?php echo htmlspecialchars($full_address); ?></p>
                                            <?php if ($barangay !== '' || $city !== '' || $province !== ''): ?>
                                                <p class="small text-secondary mb-0">Coverage: <?php echo htmlspecialchars(implode(', ', array_filter([$barangay, $city, $province]))); ?></p>
                                            <?php else: ?>
                                                <p class="small text-warning mb-0">Edit this address to connect it to a covered barangay.</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-start gap-2">
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#addressModal"
                                                data-mode="edit"
                                                data-address-id="<?php echo $address_id; ?>"
                                                data-label="<?php echo htmlspecialchars($address_label, ENT_QUOTES); ?>"
                                                data-street="<?php echo htmlspecialchars($street_address, ENT_QUOTES); ?>"
                                                data-province="<?php echo htmlspecialchars($province, ENT_QUOTES); ?>"
                                                data-city="<?php echo htmlspecialchars($city, ENT_QUOTES); ?>"
                                                data-barangay="<?php echo htmlspecialchars($barangay, ENT_QUOTES); ?>"
                                                data-default="<?php echo $is_default ? '1' : '0'; ?>"
                                            >
                                                Edit
                                            </button>
                                            <form method="POST" class="delete-address-form" data-address-id="<?php echo $address_id; ?>" data-address-label="<?php echo htmlspecialchars($address_label, ENT_QUOTES); ?>">
                                                <input type="hidden" name="address_id" value="<?php echo $address_id; ?>">
                                                <button type="submit" name="delete_delivery_address" class="btn btn-outline-danger btn-sm" <?php echo $can_delete ? '' : 'disabled'; ?>>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center border rounded-4 p-4 bg-light-subtle">
                            <h3 class="h6 fw-bold">No delivery address saved</h3>
                            <p class="text-secondary mb-0">Add one covered address before placing your next order.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <span class="badge text-bg-light text-primary border mb-3">Security</span>
                        <h2 class="h5 fw-bold mb-2">Change Password</h2>
                        <p class="text-secondary small mb-4">Use a strong password so your order history and account details stay protected.</p>

                    <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input id="current_password" class="form-control form-control-lg" type="password" name="current_password" required autocomplete="current-password">
                        </div>

                                <div class="col-md-6">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input id="new_password" class="form-control form-control-lg" type="password" name="new_password" minlength="6" required autocomplete="new-password">
                                    <div class="form-text">Minimum 6 characters.</div>
                        </div>

                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input id="confirm_password" class="form-control form-control-lg" type="password" name="confirm_password" required autocomplete="new-password">
                        </div>

                                <div class="col-12">
                                    <button type="submit" name="change_password" class="btn btn-success btn-lg">Change Password</button>
                                </div>
                            </div>
                    </form>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <div class="modal fade" id="addressModal" tabindex="-1" aria-labelledby="addressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="addressModalLabel">Add Delivery Address</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="address_id" id="address_id">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="address_label" class="form-label">Address Label</label>
                                <input id="address_label" class="form-control" type="text" name="address_label" placeholder="Home, Office">
                            </div>
                            <div class="col-md-8">
                                <label for="street_address" class="form-label">Street / House Details <span class="text-danger">*</span></label>
                                <textarea id="street_address" class="form-control" name="street_address" rows="2" placeholder="House no., street, purok, landmark" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                                <select id="province" class="form-select" name="province" required>
                                    <option value="">Choose province</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="city" class="form-label">City / Municipality <span class="text-danger">*</span></label>
                                <select id="city" class="form-select" name="city" required>
                                    <option value="">Choose city or municipality</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                                <select id="barangay" class="form-select" name="barangay" required>
                                    <option value="">Choose barangay</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input id="is_default" class="form-check-input" type="checkbox" name="is_default" value="1">
                                    <label for="is_default" class="form-check-label">Use as my default delivery address</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_delivery_address" class="btn btn-primary">Save Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteAddressModal" tabindex="-1" aria-labelledby="deleteAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" id="deleteAddressModalForm">
                    <input type="hidden" name="delete_delivery_address" value="1">
                    <input type="hidden" name="address_id" id="deleteAddressId">
                    <div class="modal-header">
                        <h2 class="modal-title h5 fw-bold" id="deleteAddressModalLabel">Delete delivery address</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" id="deleteAddressMessage">Delete this delivery address?</p>
                        <p class="small text-secondary mb-0">This will remove it from your saved delivery locations.</p>
                        <div class="delete-address-progress d-none text-center mt-3" id="deleteAddressProgress" role="status" aria-live="polite">
                            <div class="spinner-border text-danger mb-2" aria-hidden="true"></div>
                            <div class="small fw-semibold">Deleting address...</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep Address</button>
                        <button type="submit" class="btn btn-danger" id="confirmDeleteAddressButton">Delete Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const deliveryAreas = <?php echo $delivery_area_json ?: '{}'; ?>;
        const addressModal = document.getElementById('addressModal');
        const provinceSelect = document.getElementById('province');
        const citySelect = document.getElementById('city');
        const barangaySelect = document.getElementById('barangay');

        function fillSelect(select, values, placeholder, selectedValue = '') {
            if (!select) {
                return;
            }

            select.innerHTML = '';
            select.add(new Option(placeholder, ''));

            values.forEach(value => {
                const option = new Option(value, value);
                option.selected = value === selectedValue;
                select.add(option);
            });
        }

        function refreshCities(selectedCity = '', selectedBarangay = '') {
            const province = provinceSelect?.value || '';
            const cities = province && deliveryAreas[province] ? Object.keys(deliveryAreas[province]) : [];
            fillSelect(citySelect, cities, 'Choose city or municipality', selectedCity);
            refreshBarangays(selectedBarangay);
        }

        function refreshBarangays(selectedBarangay = '') {
            const province = provinceSelect?.value || '';
            const city = citySelect?.value || '';
            const barangays = province && city && deliveryAreas[province]?.[city] ? deliveryAreas[province][city] : [];
            fillSelect(barangaySelect, barangays, 'Choose barangay', selectedBarangay);
        }

        if (provinceSelect && citySelect && barangaySelect) {
            provinceSelect.addEventListener('change', () => refreshCities());
            citySelect.addEventListener('change', () => refreshBarangays());
        }

        if (addressModal) {
            addressModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const mode = button?.dataset.mode || 'add';
                const selectedProvince = button?.dataset.province || '';
                const selectedCity = button?.dataset.city || '';
                const selectedBarangay = button?.dataset.barangay || '';

                document.getElementById('addressModalLabel').textContent = mode === 'edit' ? 'Edit Delivery Address' : 'Add Delivery Address';
                document.getElementById('address_id').value = mode === 'edit' ? (button?.dataset.addressId || '') : '';
                document.getElementById('address_label').value = mode === 'edit' ? (button?.dataset.label || '') : '';
                document.getElementById('street_address').value = mode === 'edit' ? (button?.dataset.street || '') : '';
                document.getElementById('is_default').checked = mode === 'edit' && button?.dataset.default === '1';

                fillSelect(provinceSelect, Object.keys(deliveryAreas), 'Choose province', selectedProvince);
                refreshCities(selectedCity, selectedBarangay);
            });
        }

        const deleteAddressModalElement = document.getElementById('deleteAddressModal');
        const deleteAddressModal = deleteAddressModalElement ? new bootstrap.Modal(deleteAddressModalElement) : null;
        const deleteAddressIdInput = document.getElementById('deleteAddressId');
        const deleteAddressMessage = document.getElementById('deleteAddressMessage');
        const deleteAddressProgress = document.getElementById('deleteAddressProgress');
        const deleteAddressModalForm = document.getElementById('deleteAddressModalForm');
        const confirmDeleteAddressButton = document.getElementById('confirmDeleteAddressButton');

        document.querySelectorAll('.delete-address-form').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();

                const addressId = form.dataset.addressId || '';
                const addressLabel = form.dataset.addressLabel || 'this delivery address';

                if (deleteAddressIdInput) {
                    deleteAddressIdInput.value = addressId;
                }
                if (deleteAddressMessage) {
                    deleteAddressMessage.textContent = `Delete "${addressLabel}"?`;
                }
                if (deleteAddressProgress) {
                    deleteAddressProgress.classList.add('d-none');
                }
                if (confirmDeleteAddressButton) {
                    confirmDeleteAddressButton.disabled = false;
                    confirmDeleteAddressButton.innerHTML = 'Delete Address';
                }

                deleteAddressModal?.show();
            });
        });

        if (deleteAddressModalForm) {
            deleteAddressModalForm.addEventListener('submit', () => {
                if (deleteAddressProgress) {
                    deleteAddressProgress.classList.remove('d-none');
                }
                if (confirmDeleteAddressButton) {
                    confirmDeleteAddressButton.disabled = true;
                    confirmDeleteAddressButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Deleting...';
                }
            });
        }
    </script>
</body>
</html>
