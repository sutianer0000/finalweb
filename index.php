<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'admin') {
        redirect('/finalweb/admin/dashboard.php');
    }
    if ($user['first_login'] == 1) {
        redirect('/finalweb/first_login_password.php');
    }
    redirect('/finalweb/dashboard.php');
} else {
    redirect('/finalweb/login.php');
}
