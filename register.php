<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/address_helpers.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin': header("Location: pages/admin/dashboard.php"); exit();
        case 'staff': header("Location: pages/staff/dashboard.php"); exit();
        case 'customer': header("Location: pages/customer/dashboard.php"); exit();
    }
}

$error = '';
$success = '';
$service_areas = fetch_delivery_service_areas($conn);
$delivery_area_json = delivery_area_index_json($service_areas);

// Handle registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $structured_address = delivery_address_from_post();
    $address = format_delivery_address(
        $structured_address['street_address'],
        $structured_address['barangay'],
        $structured_address['city'],
        $structured_address['province']
    );
    $service_area = delivery_address_is_complete($structured_address)
        ? find_delivery_service_area($conn, $structured_address['province'], $structured_address['city'], $structured_address['barangay'])
        : null;

    // Validate location first so unsupported addresses are rejected before account creation.
    if (empty($username) || empty($password) || empty($email) || empty($full_name) || empty($phone) || !delivery_address_is_complete($structured_address)) {
        $error = "All fields are required.";
    } elseif (!$service_area) {
        $error = "This delivery address is outside our current coverage. Please choose a listed Pangasinan province, city, and barangay combination.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if username exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            // Hash password
            $hash = password_hash($password, PASSWORD_DEFAULT);

            ensure_delivery_service_area_schema($conn);

            $conn->begin_transaction();
            try {
                // Insert new customer
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, 'customer', 'active')");
                $stmt->bind_param("ssssss", $username, $hash, $email, $full_name, $phone, $address);
                if (!$stmt->execute()) {
                    throw new Exception("Registration failed. Please try again.");
                }
                $customer_id = $stmt->insert_id;
                $stmt->close();

                // Create loyalty record (skip if the database trigger already created it)
                $loyalty = $conn->prepare("INSERT IGNORE INTO loyalty (customer_id, consecutive_orders, total_orders, free_gallons_earned) VALUES (?, 0, 0, 0)");
                $loyalty->bind_param("i", $customer_id);
                if (!$loyalty->execute()) {
                    throw new Exception("Registration failed. Please try again.");
                }
                $loyalty->close();

                $address_label = 'Main Delivery Address';
                $is_default = 1;
                $service_area_id = (int) ($service_area['area_id'] ?? 0);
                $delivery_address = $conn->prepare("INSERT INTO customer_delivery_addresses (customer_id, label, address, street_address, barangay, city, province, service_area_id, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $delivery_address->bind_param(
                    "issssssii",
                    $customer_id,
                    $address_label,
                    $address,
                    $structured_address['street_address'],
                    $structured_address['barangay'],
                    $structured_address['city'],
                    $structured_address['province'],
                    $service_area_id,
                    $is_default
                );
                if (!$delivery_address->execute()) {
                    throw new Exception("Registration failed. Please try again.");
                }
                $delivery_address->close();

                $conn->commit();
                $success = "Account created successfully! You can now log in.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISRAPHIL | Create Account</title>
    <link rel="icon" type="image/png" href="image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-info-subtle">
    <main class="container py-3 py-md-4 py-lg-5">
        <section class="card border-0 shadow-lg overflow-hidden rounded-4">
            <div class="row g-0">
                <section class="col-lg-5 order-2 order-lg-1 bg-primary bg-gradient text-white p-3 p-md-4 p-lg-5 d-flex flex-column gap-4">
                    <div>
                        <span class="badge text-bg-light text-primary mb-3 px-3 py-2 small">Customer Registration</span>
                        <h1 class="display-6 fw-bold lh-sm mb-3">Set up your delivery account.</h1>
                        <p class="text-white-50 mb-4">Create your customer profile once and manage orders, delivery updates, and loyalty rewards from one account.</p>

                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body p-3 p-lg-4 text-dark">
                                <h2 class="h6 fw-bold mb-3">Why create an account</h2>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item px-0 py-2">Keep your contact details and delivery address ready for your next request.</li>
                                    <li class="list-group-item px-0 py-2">Track your orders and delivery updates from one place.</li>
                                    <li class="list-group-item px-0 py-2">See your loyalty progress and free gallon rewards more easily.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="card border-0 shadow-sm rounded-4 border-top border-4 border-warning-subtle mb-3">
                            <div class="card-body p-3 p-lg-4 text-dark">
                                <span class="badge text-bg-info mb-2 px-3 py-2 small">Who Can Register</span>
                                <p class="fw-semibold mb-2">Basista, Pangasinan and nearby areas</p>
                                <p class="text-secondary mb-0">This account is for customers within the station's supported delivery coverage.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="col-lg-7 order-1 order-lg-2 bg-white p-3 p-md-4 p-lg-5">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-3 mb-4">
                        <div>
                            <span class="badge text-bg-primary mb-3 px-3 py-2 small">Create Account</span>
                            <h2 class="h3 fw-bold text-dark mb-2">Join ISRAPHIL</h2>
                            <p class="text-secondary mb-0">Fill in your details below to create your customer account and start ordering.</p>
                        </div>
                        <a class="btn btn-outline-primary btn-sm align-self-start d-none d-sm-inline-flex" href="index.php">Sign in</a>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="border border-success-subtle bg-success-subtle rounded-4 p-4 p-md-5 text-center" role="status" aria-live="polite">
                            <span class="badge text-bg-success mb-3 px-3 py-2 small">Account Ready</span>
                            <h3 class="h4 fw-bold text-success-emphasis mb-3">Registration complete</h3>
                            <p class="text-secondary mb-4"><?php echo htmlspecialchars($success); ?></p>
                            <a class="btn btn-success btn-lg px-4" href="index.php">Go to sign in</a>
                        </div>
                    <?php else: ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="rounded-4 border bg-light-subtle p-3 p-md-4 mb-4">
                            <div class="mb-3">
                                <span class="badge text-bg-light text-primary border px-3 py-2 small">Account Details</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input id="username" class="form-control form-control-lg" type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autocomplete="username">
                                    <div class="form-text">Use a short username you can remember easily.</div>
                                    <div class="invalid-feedback">Please enter a username.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input id="full_name" class="form-control form-control-lg" type="text" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required autocomplete="name">
                                    <div class="form-text">Use the name we should show on orders and deliveries.</div>
                                    <div class="invalid-feedback">Please enter your full name.</div>
                                </div>
                                <div class="col-12">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input id="email" class="form-control form-control-lg" type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email">
                                    <div class="form-text">We'll use this for account recovery and updates.</div>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-4 border bg-light-subtle p-3 p-md-4 mb-4">
                            <div class="mb-3">
                                <span class="badge text-bg-light text-primary border px-3 py-2 small">Delivery Details</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input id="phone" class="form-control form-control-lg" type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required placeholder="09123456789" autocomplete="tel" inputmode="numeric">
                                    <div class="form-text">Use an active number the station can call or text.</div>
                                    <div class="invalid-feedback">Please enter your phone number.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="street_address" class="form-label">Street / House Details <span class="text-danger">*</span></label>
                                    <textarea id="street_address" class="form-control form-control-lg" name="street_address" rows="2" required placeholder="House no., street, landmark" autocomplete="street-address"><?php echo htmlspecialchars($_POST['street_address'] ?? ''); ?></textarea>
                                    <div class="form-text">Add the exact house, street, purok, or landmark for the rider.</div>
                                    <div class="invalid-feedback">Please enter your street or house details.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                                    <select id="province" class="form-select form-select-lg" name="province" required data-current="<?php echo htmlspecialchars($_POST['province'] ?? ''); ?>">
                                        <option value="">Choose province</option>
                                    </select>
                                    <div class="form-text">Coverage is checked against the service-area database.</div>
                                    <div class="invalid-feedback">Please choose a supported province.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City / Municipality <span class="text-danger">*</span></label>
                                    <select id="city" class="form-select form-select-lg" name="city" required data-current="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                        <option value="">Choose city or municipality</option>
                                    </select>
                                    <div class="invalid-feedback">Please choose a supported city or municipality.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                                    <select id="barangay" class="form-select form-select-lg" name="barangay" required data-current="<?php echo htmlspecialchars($_POST['barangay'] ?? ''); ?>">
                                        <option value="">Choose barangay</option>
                                    </select>
                                    <div class="form-text text-primary">Barangay is used for delivery routing and coverage checks.</div>
                                    <div class="invalid-feedback">Please choose a supported barangay.</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-4 border bg-light-subtle p-3 p-md-4 mb-4">
                            <div class="mb-3">
                                <span class="badge text-bg-light text-primary border px-3 py-2 small">Security</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-lg">
                                        <input id="password" class="form-control" type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="Minimum 6 characters">
                                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="password" aria-label="Show password">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Use at least 6 characters.</div>
                                    <div class="invalid-feedback">Please create a password with at least 6 characters.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-lg">
                                        <input id="confirm_password" class="form-control" type="password" name="confirm_password" required autocomplete="new-password">
                                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="confirm_password" aria-label="Show password confirmation">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Re-enter the same password to confirm it.</div>
                                    <div class="invalid-feedback">Please confirm your password so it matches.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Create My Account</button>
                        </div>
                    </form>

                    <div class="border-top pt-3 mt-4 d-flex d-sm-none flex-column gap-2">
                        <span class="text-secondary">Already registered?</span>
                        <a class="fw-semibold text-decoration-none" href="index.php">Sign in here</a>
                    </div>

                    <?php endif; ?>
                </section>
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const deliveryAreas = <?php echo $delivery_area_json ?: '{}'; ?>;
        const provinceSelect = document.getElementById('province');
        const citySelect = document.getElementById('city');
        const barangaySelect = document.getElementById('barangay');

        function fillSelect(select, values, placeholder, selectedValue = '') {
            if (!select) {
                return;
            }

            select.innerHTML = '';
            const placeholderOption = new Option(placeholder, '');
            select.add(placeholderOption);

            values.forEach(value => {
                const option = new Option(value, value);
                option.selected = value === selectedValue;
                select.add(option);
            });
        }

        function refreshCities() {
            const province = provinceSelect?.value || '';
            const selectedCity = citySelect?.dataset.current || '';
            const cities = province && deliveryAreas[province] ? Object.keys(deliveryAreas[province]) : [];
            fillSelect(citySelect, cities, 'Choose city or municipality', selectedCity);
            citySelect.dataset.current = '';
            refreshBarangays();
        }

        function refreshBarangays() {
            const province = provinceSelect?.value || '';
            const city = citySelect?.value || '';
            const selectedBarangay = barangaySelect?.dataset.current || '';
            const barangays = province && city && deliveryAreas[province]?.[city] ? deliveryAreas[province][city] : [];
            fillSelect(barangaySelect, barangays, 'Choose barangay', selectedBarangay);
            barangaySelect.dataset.current = '';
        }

        if (provinceSelect && citySelect && barangaySelect) {
            fillSelect(provinceSelect, Object.keys(deliveryAreas), 'Choose province', provinceSelect.dataset.current || '');
            refreshCities();
            provinceSelect.addEventListener('change', refreshCities);
            citySelect.addEventListener('change', refreshBarangays);
        }

        document.querySelectorAll('[data-password-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                const icon = button.querySelector('i');
                const showPassword = input.type === 'password';
                input.type = showPassword ? 'text' : 'password';
                button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
                icon?.classList.toggle('bi-eye', !showPassword);
                icon?.classList.toggle('bi-eye-slash', showPassword);
            });
        });

        const form = document.querySelector('.needs-validation');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        function validatePasswordMatch() {
            if (!passwordInput || !confirmPasswordInput) {
                return;
            }

            if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match.');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        }

        if (passwordInput && confirmPasswordInput) {
            passwordInput.addEventListener('input', validatePasswordMatch);
            confirmPasswordInput.addEventListener('input', validatePasswordMatch);
        }

        if (form) {
            form.addEventListener('submit', event => {
                validatePasswordMatch();
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        }
    </script>
</body>
</html>
