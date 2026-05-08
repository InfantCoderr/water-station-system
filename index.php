<?php
// index.php - ISRAPHIL Login Page
session_start();

// Connect to database
require_once 'includes/db_connect.php';

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
$prefill_username = '';

// Handle login ONLY if actually submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password, role, full_name, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'active') {
                $error = "Account is " . $user['status'];
            } elseif (password_verify($password, $user['password'])) {
                // SUCCESS - Set session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                // Update last login
                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->bind_param("i", $user['user_id']);
                $updateStmt->execute();

                // Redirect to dashboard
                switch ($user['role']) {
                    case 'admin': header("Location: pages/admin/dashboard.php"); exit();
                    case 'staff': header("Location: pages/staff/dashboard.php"); exit();
                    case 'customer': header("Location: pages/customer/dashboard.php"); exit();
                }
            } else {
                $error = "Wrong password.";
            }
        } else {
            $error = "User not found.";
        }
        $stmt->close();
    }

    // CRITICAL: If login failed, store error in session and redirect to self
    // This prevents form resubmission on refresh!
    if (!empty($error)) {
        $_SESSION['login_error'] = $error;
        $_SESSION['login_username'] = $username;
        header("Location: index.php");
        exit();
    }
}

// Check for stored error from previous failed attempt
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Clear it so refresh won't show again
}

if (isset($_SESSION['login_username'])) {
    $prefill_username = $_SESSION['login_username'];
    unset($_SESSION['login_username']);
}

if (isset($_SESSION['login_success'])) {
    $success = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISRAPHIL | Sign In</title>
    <link rel="icon" type="image/png" href="image.gif/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-info-subtle">
    <main class="container py-4 py-lg-5">
        <section class="card border-0 shadow-lg overflow-hidden">
            <div class="row g-0">
                <section class="col-lg-5 bg-primary text-white p-4 p-lg-5 d-flex align-items-center">
                    <div class="w-100">
                        <div class="mb-4">
                            <span class="badge text-bg-light text-primary mb-3">ISRAPHIL</span>
                            <h1 class="display-6 fw-bold mb-3">Water Station Website</h1>
                            <p class="mb-0 text-white-50">Sign in to manage your account and orders, or explore our services.</p>
                        </div>

                        <div class="card border-0 shadow">
                            <div class="card-body p-4">
                                <span class="badge text-bg-primary mb-3">Sign In</span>
                                <h2 class="h3 fw-bold text-dark mb-2">Welcome back</h2>
                                <p class="text-secondary mb-4">Enter your account details to continue.</p>

                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>

                                <?php if (!empty($success)): ?>
                                    <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="username" class="form-label text-dark">Username</label>
                                        <input id="username" class="form-control" type="text" name="username" value="<?php echo htmlspecialchars($prefill_username); ?>" placeholder="Enter your username" required autocomplete="username">
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label text-dark">Password</label>
                                        <input id="password" class="form-control" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                                    </div>
                                    <div class="text-end mb-3">
                                        <a class="link-primary text-decoration-none" href="forgot_password.php">Forgot password?</a>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Log In</button>
                                    </div>
                                </form>

                                <div class="border-top pt-3 mt-4 d-flex flex-column flex-sm-row justify-content-between gap-2">
                                    <span class="text-secondary">New customer?</span>
                                    <a class="fw-semibold text-decoration-none" href="register.php">Create an account</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="col-lg-7 bg-light p-4 p-lg-5">
                    <div class="mb-4">
                        <span class="badge text-bg-info mb-3">About Us</span>
                        <h2 class="fw-bold text-primary mb-3">Fresh water and reliable service.</h2>
                        <p class="text-secondary">ISRAPHIL Water Station supports homes and businesses with water refilling and delivery, backed by a simple system that keeps service organized.</p>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <article class="card h-100 border-info-subtle">
                                <div class="card-body">
                                    <span class="badge text-bg-light text-primary mb-2">Brand</span>
                                    <h3 class="h5 fw-bold">The brand</h3>
                                    <p class="text-secondary mb-0">Built around trust, cleanliness, reliability, and everyday convenience.</p>
                                </div>
                            </article>
                        </div>
                        <div class="col-md-6">
                            <article class="card h-100 border-info-subtle">
                                <div class="card-body">
                                    <span class="badge text-bg-light text-primary mb-2">Station</span>
                                    <h3 class="h5 fw-bold">The station</h3>
                                    <p class="text-secondary mb-0">An organized workflow for tracking orders, coordinating deliveries, and serving repeat customers.</p>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div class="card border-warning-subtle">
                        <div class="row g-0 align-items-center">
                            <div class="col-md-5 p-3">
                                <img class="img-fluid rounded" src="image.gif/water.png" alt="Water station illustration">
                            </div>
                            <div class="col-md-7">
                                <div class="card-body">
                                    <span class="badge text-bg-info mb-3">What We Offer</span>
                                    <h3 class="h4 fw-bold mb-3">Water service with a cleaner online experience.</h3>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item px-0">Purified water refilling for homes and small businesses</li>
                                        <li class="list-group-item px-0">Delivery support and customer account access</li>
                                        <li class="list-group-item px-0">Dedicated dashboards for admin, staff, and customers</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="bg-white border-top px-4 py-3 d-flex flex-column flex-md-row justify-content-between gap-2 text-secondary small">
                <span>ISRAPHIL Water Station | Basista, Pangasinan</span>
                <span>Clean water, dependable delivery, organized service.</span>
            </footer>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
