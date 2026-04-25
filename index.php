<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    if (in_array($user['role'], ['admin', 'superadmin'], true)) {
        redirect(BASE_URL . '/admin/dashboard.php');
    }
    if ($user['first_login'] == 1) {
        redirect(BASE_URL . '/first_login_password.php');
    }
    redirect(BASE_URL . '/dashboard.php');
} else {
    redirect(BASE_URL . '/login.php');
}
