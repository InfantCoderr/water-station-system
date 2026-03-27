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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST['username'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

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
        header("Location: index.php");
        exit();
    }
}

// Check for stored error from previous failed attempt
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Clear it so refresh won't show again
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
    <link rel="stylesheet" href="style/auth.css?v=20260325">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-visual">
            <div class="auth-copy">
                <span class="auth-badge">Water Delivery Workspace</span>
                <h1>Fresh water.<br>Sharper operations.</h1>
                <p>Run customer orders, delivery schedules, inventory updates, and loyalty tracking from one clean workspace designed for a modern water station.</p>
                <ul class="auth-feature-list">
                    <li>Role-based access<span>Separate spaces for admins, staff, and customers keep each workflow focused.</span></li>
                    <li>Live daily control<span>Review orders, assignments, and stock levels without digging through cluttered screens.</span></li>
                    <li>Ready for growth<span>A cleaner experience helps your team work faster and makes the system feel more professional.</span></li>
                </ul>
            </div>
            <div class="auth-showcase">
                <div class="auth-demo-card">
                    <span class="auth-badge">Demo Access</span>
                    <strong>Admin preview account</strong>
                    <p>Use the included demo account to explore the full management dashboard.</p>
                    <code>admin / admin123</code>
                </div>
                <img class="auth-illustration auth-illustration-rounded" src="image.gif\water.png" alt="Water delivery illustration">
            </div>
        </section>

        <section class="auth-panel">
            <div class="box auth-card">
                <span class="auth-mark">Sign In</span>
                <h2>Welcome back</h2>
                <p class="auth-subtitle">Enter your account details to open the ISRAPHIL Water Station workspace.</p>

                <?php if (!empty($error)): ?>
                    <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="auth-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input id="username" type="text" name="username" placeholder="Enter your username" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                    </div>
                    <div class="auth-aux">
                        <a href="forgot_password.php">Forgot your password?</a>
                    </div>
                    <button type="submit">Continue to Dashboard</button>
                </form>

                <div class="auth-links">
                    <span>Need a customer account?</span>
                    <a href="register.php">Create one here</a>
                </div>

                <div class="auth-meta">
                    <span>ISRAPHIL Water Station</span>
                    <a href="register.php">Customer registration</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
