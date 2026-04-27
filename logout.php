<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(isLoggedIn() ? BASE_URL . '/dashboard.php' : BASE_URL . '/login.php');
}

requireCsrfToken();

if (isLoggedIn()) {
    logActivity('logout', [
        'target_user_id' => $_SESSION['user_id'],
        'entity_type' => 'auth',
    ]);
}

revokeCurrentRememberMeToken();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    if (PHP_VERSION_ID >= 70300) {
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    } else {
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?: '/',
            $params['domain'] ?? '',
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }
}

session_destroy();

header("Location: " . BASE_URL . "/login.php");
exit;
