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
    <link rel="stylesheet" href="style/system_skeleton.css?v=20260527d">
    <style>
        .login-status {
            border: 0;
            border-left: 0.35rem solid currentColor;
            border-radius: 0.65rem;
            box-shadow: 0 0.65rem 1.35rem rgba(33, 37, 41, 0.1);
            display: flex;
            gap: 0.8rem;
            align-items: flex-start;
            padding: 1rem;
        }

        .login-status-mark {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            font-weight: 800;
            line-height: 1;
        }

        .login-status-danger {
            color: #842029;
            background: #fff2f2;
        }

        .login-status-danger .login-status-mark {
            color: #fff;
            background: #dc3545;
        }

        .login-status-success {
            color: #0f5132;
            background: #effaf3;
        }

        .login-status-success .login-status-mark {
            color: #fff;
            background: #198754;
        }

        .login-loading-modal .modal-content {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 1rem 2.5rem rgba(13, 110, 253, 0.2);
        }

        .login-loading-ring {
            width: 5rem;
            height: 5rem;
            border-radius: 50%;
            background:
                radial-gradient(circle, #fff 0 52%, transparent 53%),
                conic-gradient(#0d6efd 0deg, #0dcaf0 110deg, #dbeafe 110deg 360deg);
            animation: login-doughnut-spin 0.9s linear infinite;
            margin: 0 auto;
        }

        .login-loading-ring.is-failed {
            background:
                radial-gradient(circle, #fff 0 52%, transparent 53%),
                conic-gradient(#dc3545 0deg 360deg);
            animation: none;
            position: relative;
        }

        .login-loading-ring.is-failed::after {
            content: "!";
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc3545;
            font-size: 2rem;
            font-weight: 800;
        }

        @keyframes login-doughnut-spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body class="bg-info-subtle system-loading skeleton-auth skeleton-auth-login">
    <main class="container py-4 py-lg-5">
        <section class="card border-0 shadow-lg overflow-hidden mx-auto" style="max-width: 640px;">
            <div class="row g-0">
                <section class="col-12 bg-primary text-white p-4 p-lg-5 d-flex align-items-center">
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
                                    <p class="text-danger mb-4" role="alert" aria-live="assertive"><?php echo htmlspecialchars($error); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($success)): ?>
                                    <div class="login-status login-status-success mb-4" role="status" aria-live="polite">
                                        <span class="login-status-mark" aria-hidden="true">&#10003;</span>
                                        <div>
                                            <div class="fw-bold mb-1">Ready</div>
                                            <div><?php echo htmlspecialchars($success); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="" id="loginForm">
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
            </div>

            <footer class="bg-white border-top px-4 py-3 d-flex flex-column flex-md-row justify-content-between gap-2 text-secondary small">
                <span>ISRAPHIL Water Station | Basista, Pangasinan</span>
                <span>Clean water, dependable delivery, organized service.</span>
            </footer>
        </section>
    </main>

    <div class="modal fade login-loading-modal" id="loginLoadingModal" tabindex="-1" aria-labelledby="loginLoadingTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" data-login-error="<?php echo htmlspecialchars($error, ENT_QUOTES); ?>">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4 p-md-5">
                    <div class="login-loading-ring mb-4" id="loginLoadingRing" aria-hidden="true"></div>
                    <h2 class="h5 fw-bold mb-2" id="loginLoadingTitle">Signing you in</h2>
                    <p class="text-secondary mb-4" id="loginLoadingMessage">Please wait while we verify your account.</p>
                    <button type="button" class="btn btn-danger d-none" id="loginLoadingClose" data-bs-dismiss="modal">Try Again</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const loginForm = document.getElementById('loginForm');
        const loginLoadingModalElement = document.getElementById('loginLoadingModal');
        const loginLoadingModal = loginLoadingModalElement ? new bootstrap.Modal(loginLoadingModalElement) : null;
        const loginLoadingRing = document.getElementById('loginLoadingRing');
        const loginLoadingTitle = document.getElementById('loginLoadingTitle');
        const loginLoadingMessage = document.getElementById('loginLoadingMessage');
        const loginLoadingClose = document.getElementById('loginLoadingClose');
        const loginError = loginLoadingModalElement?.dataset.loginError || '';

        if (loginError && loginLoadingModal) {
            loginLoadingRing?.classList.add('is-failed');
            if (loginLoadingTitle) {
                loginLoadingTitle.textContent = 'Sign in failed';
                loginLoadingTitle.classList.add('text-danger');
            }
            if (loginLoadingMessage) {
                loginLoadingMessage.textContent = loginError;
            }
            loginLoadingClose?.classList.remove('d-none');
            loginLoadingModal.show();
        }

        loginForm?.addEventListener('submit', () => {
            if (!loginForm.checkValidity()) {
                return;
            }

            loginLoadingRing?.classList.remove('is-failed');
            loginLoadingTitle?.classList.remove('text-danger');
            if (loginLoadingTitle) {
                loginLoadingTitle.textContent = 'Signing you in';
            }
            if (loginLoadingMessage) {
                loginLoadingMessage.textContent = 'Please wait while we verify your account.';
            }
            loginLoadingClose?.classList.add('d-none');
            loginLoadingModal?.show();
        });
    </script>
    <script src="scripts/system_skeleton.js?v=20260527"></script>
</body>
</html>
