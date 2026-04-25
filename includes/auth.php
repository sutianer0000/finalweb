<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Base URL prefix — empty on Railway (app at /), "/finalweb" for local XAMPP.
// Set via BASE_URL env var (config/local.php for local, Railway dashboard for prod).
if (!defined('BASE_URL')) {
    $__baseUrl = getenv('BASE_URL');
    define('BASE_URL', $__baseUrl !== false ? $__baseUrl : '');
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current logged-in user data
// Explicit column list — excludes the id_card *_data BLOBs so we don't drag
// ~MBs into every page load. Fetch BLOBs only via image.php.
function getCurrentUser() {
    static $loaded = false;
    static $cachedUser = null;

    if ($loaded) {
        return $cachedUser;
    }

    $loaded = true;
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, phone_number, email, full_name, date_of_birth, address,
               password, balance, role, status, first_login,
               id_card_front_mime, id_card_back_mime,
               failed_login_attempts, has_abnormal_login, locked_until,
               permanently_locked, permanently_locked_at,
               created_at, updated_at
        FROM users WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Session points to a user that no longer exists (e.g. DB was re-imported
    // while the browser still had the cookie). Wipe the session and force a
    // fresh login instead of letting callers dereference `false`.
    if ($user === false) {
        session_unset();
        session_destroy();
        $cachedUser = null;
        return null;
    }

    $cachedUser = $user;
    return $cachedUser;
}

// Redirect helper
function redirect($url) {
    header("Location: $url");
    exit;
}

// Require login — redirects to login page if not authenticated.
// Also catches the "session points to a deleted user" case: getCurrentUser()
// wipes the session in that scenario, and we redirect instead of letting
// callers get a null user.
function requireLogin() {
    if (!isLoggedIn() || getCurrentUser() === null) {
        redirect(BASE_URL . '/login.php');
    }
}

// Require that user has changed first-login password
// If first_login = 1, redirect to change password page
function requirePasswordChanged() {
    requireLogin();
    $user = getCurrentUser();
    if ($user['first_login'] == 1) {
        redirect(BASE_URL . '/first_login_password.php');
    }
}

// Require that user is verified. If not, flash a message and redirect to dashboard.
function requireVerified() {
    requirePasswordChanged();
    $user = getCurrentUser();
    if ($user && $user['status'] !== 'verified') {
        setFlash('warning', 'This feature is only available for verified accounts.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

// Require that current user is an admin
function requireAdmin() {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        redirect(BASE_URL . '/login.php');
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
