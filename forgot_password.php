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
        $stmt = $conn->prepare("SELECT user_id, password, status FROM users WHERE username = ? AND email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = "We could not verify that username and email combination.";
        } elseif ($user['status'] !== 'active') {
            $error = "This account is not active. Please contact the administrator.";
        } elseif (password_verify($password, $user['password'])) {
            $error = "Please choose a new password that is different from your current password.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update->bind_param("si", $hash, $user['user_id']);

            if ($update->execute()) {
                $update->close();
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-info-subtle">
    <main class="container py-4 py-md-5">
        <section class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <span class="badge text-bg-primary mb-3 px-3 py-2">Reset Password</span>
                        <h1 class="h3 fw-bold mb-2">Recover your account</h1>
                        <p class="text-secondary mb-4">Enter your username, registered email, and new password.</p>

                <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div id="validation_summary" class="alert alert-danger d-none" role="alert"></div>

                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input id="username" class="form-control form-control-lg" type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Enter your username" required autocomplete="username">
                                <div class="invalid-feedback">Please enter your username.</div>
                    </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Registered Email</label>
                                <input id="email" class="form-control form-control-lg" type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="Enter your email" required autocomplete="email">
                                <div class="invalid-feedback">Please enter your registered email.</div>
                    </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group input-group-lg">
                                    <input id="password" class="form-control" type="password" name="password" placeholder="Minimum 6 characters" required minlength="6" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="password" aria-label="Show password">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div id="password_hint" class="form-text">Use at least 6 characters.</div>
                    </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group input-group-lg">
                                    <input id="confirm_password" class="form-control" type="password" name="confirm_password" placeholder="Re-enter your new password" required minlength="6" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="confirm_password" aria-label="Show password confirmation">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div id="confirm_password_hint" class="form-text">Re-enter the same password.</div>
                    </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                            </div>
                </form>

                        <div class="border-top pt-3 mt-4 d-flex flex-column flex-sm-row justify-content-between gap-2">
                            <a class="text-decoration-none fw-semibold" href="index.php">Back to sign in</a>
                            <a class="text-decoration-none" href="register.php">Create customer account</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        const passwordHint = document.getElementById('password_hint');
        const confirmPasswordHint = document.getElementById('confirm_password_hint');
        const validationSummary = document.getElementById('validation_summary');

        function setHintState(element, message, state) {
            if (!element) {
                return;
            }

            element.textContent = message;
            element.classList.remove('form-text', 'text-danger', 'text-success');

            if (state === 'error') {
                element.classList.add('text-danger');
            } else if (state === 'success') {
                element.classList.add('text-success');
            } else {
                element.classList.add('form-text');
            }
        }

        function validatePasswordLength() {
            if (!passwordInput) {
                return true;
            }

            if (!passwordInput.value) {
                passwordInput.setCustomValidity('');
                setHintState(passwordHint, 'Use at least 6 characters.', 'neutral');
                return false;
            }

            if (passwordInput.value.length < 6) {
                passwordInput.setCustomValidity('Password must be at least 6 characters.');
                setHintState(passwordHint, `Password is too short. Add ${6 - passwordInput.value.length} more character(s).`, 'error');
                return false;
            }

            passwordInput.setCustomValidity('');
            setHintState(passwordHint, 'Password length looks good.', 'success');
            return true;
        }

        function validatePasswordMatch() {
            if (!passwordInput || !confirmPasswordInput) {
                return;
            }

            if (!confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('');
                setHintState(confirmPasswordHint, 'Re-enter the same password.', 'neutral');
            } else if (passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match.');
                setHintState(confirmPasswordHint, 'Passwords do not match.', 'error');
            } else {
                confirmPasswordInput.setCustomValidity('');
                setHintState(confirmPasswordHint, 'Passwords match.', 'success');
            }
        }

        if (passwordInput && confirmPasswordInput) {
            passwordInput.addEventListener('input', () => {
                validatePasswordLength();
                validatePasswordMatch();
            });
            confirmPasswordInput.addEventListener('input', validatePasswordMatch);
        }

        if (form) {
            form.addEventListener('submit', event => {
                const passwordLengthOk = validatePasswordLength();
                validatePasswordMatch();
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (validationSummary) {
                        validationSummary.textContent = !passwordLengthOk
                            ? 'New password must be at least 6 characters.'
                            : 'Please complete the highlighted fields before resetting your password.';
                        validationSummary.classList.remove('d-none');
                    }
                } else if (validationSummary) {
                    validationSummary.classList.add('d-none');
                }
                form.classList.add('was-validated');
            });
        }
    </script>
</body>
</html>
