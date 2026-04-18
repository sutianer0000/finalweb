<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current logged-in user data
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Redirect helper
function redirect($url) {
    header("Location: $url");
    exit;
}

// Require login — redirects to login page if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/finalweb/login.php');
    }
}

// Require that user has changed first-login password
// If first_login = 1, redirect to change password page
function requirePasswordChanged() {
    requireLogin();
    $user = getCurrentUser();
    if ($user && $user['first_login'] == 1) {
        redirect('/finalweb/first_login_password.php');
    }
}

// Require that user is verified. If not, flash a message and redirect to dashboard.
function requireVerified() {
    requirePasswordChanged();
    $user = getCurrentUser();
    if ($user && $user['status'] !== 'verified') {
        setFlash('warning', 'This feature is only available for verified accounts.');
        redirect('/finalweb/dashboard.php');
    }
}

// Require that current user is an admin
function requireAdmin() {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        redirect('/finalweb/login.php');
    }
}

// Flash message helpers
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Format currency
function formatMoney($amount) {
    return number_format($amount, 0, ',', ',') . ' VND';
}

// Generate random string
function generateRandomString($length = 6) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $str;
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
