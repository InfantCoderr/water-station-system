<?php

function redirect_to($path) {
    header("Location: $path");
    exit();
}

function logout_and_redirect($path) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    redirect_to($path);
}

function require_active_session($conn, $allowed_roles, $redirect_path) {
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        logout_and_redirect($redirect_path);
    }
    $user_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT user_id, username, full_name, role, status FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user || $user['status'] !== 'active' || !in_array($user['role'], $allowed_roles, true)) {
        logout_and_redirect($redirect_path);
    }
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    return $user;
}
