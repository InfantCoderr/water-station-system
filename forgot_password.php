<?php
session_start();
require_once 'includes/db_connect.php';

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: pages/admin/dashboard.php");
            exit();
        case 'staff':
            header("Location: pages/staff/dashboard.php");
            exit();
        case 'customer':
            header("Location: pages/customer/dashboard.php");
            exit();
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($username === '' || $email === '' || $password === '' || $confirm_password === '') {
        $error = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, status FROM users WHERE username = ? AND email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = "No account matched that username and email.";
        } elseif ($user['status'] !== 'active') {
            $error = "This account is not active. Please contact the administrator.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update->bind_param("si", $hash, $user['user_id']);

            if ($update->execute()) {
                $_SESSION['login_success'] = "Password reset successful. Sign in with your new password.";
                header("Location: index.php");
                exit();
            }

            $error = "We could not reset your password right now. Please try again.";
            $update->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISRAPHIL | Reset Password</title>
    <link rel="icon" type="image/png" href="image.gif/favicon.png">
    <link rel="stylesheet" href="style/auth.css?v=20260325">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-visual">
            <div class="auth-copy">
                <span class="auth-badge">Account Recovery</span>
                <h1>Reset access.<br>Get back in fast.</h1>
                <p>Recover your account by confirming your username and registered email, then set a new password for the ISRAPHIL Water Station workspace.</p>
                <ul class="auth-feature-list">
                    <li>Quick verification<span>Use the same username and email stored in your account profile.</span></li>
                    <li>Safe password update<span>Your password is replaced immediately after the account details are confirmed.</span></li>
                    <li>Ready to sign in<span>Once saved, you can return straight to the login page and continue working.</span></li>
                </ul>
            </div>
            <div class="auth-showcase">
                <div class="auth-service-card">
                    <span class="auth-badge">Need Help?</span>
                    <strong>Use your registered email</strong>
                    <p>If your account email is no longer accessible or your account is inactive, ask the administrator for assistance.</p>
                </div>
                <img class="auth-illustration" src="image.gif/drink_water.gif" alt="Water delivery illustration">
            </div>
        </section>

        <section class="auth-panel">
            <div class="box auth-card">
                <span class="auth-mark">Reset Password</span>
                <h2>Recover your account</h2>
                <p class="auth-subtitle">Enter your username, registered email, and new password below.</p>

                <?php if (!empty($error)): ?>
                    <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="auth-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input id="username" type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Enter your username" required autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="email">Registered Email</label>
                        <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="Enter your email" required autocomplete="email">
                    </div>

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input id="password" type="password" name="password" placeholder="Minimum 6 characters" required minlength="6" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input id="confirm_password" type="password" name="confirm_password" placeholder="Re-enter your new password" required minlength="6" autocomplete="new-password">
                    </div>

                    <button type="submit">Reset Password</button>
                </form>

                <div class="auth-links">
                    <span>Remembered your password?</span>
                    <a href="index.php">Back to sign in</a>
                </div>

                <div class="auth-meta">
                    <span>ISRAPHIL Water Station</span>
                    <a href="register.php">Create customer account</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
