<?php
require_once __DIR__ . '/../config/database.php';

function configureSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $lifetime = (int) (getenv('SESSION_LIFETIME') ?: 28800);

    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_set_save_handler(new DatabaseSessionHandler($lifetime), true);
    session_start();
}

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private int $lifetime;
    private bool $tableChecked = false;

    public function __construct(int $lifetime)
    {
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        try {
            $this->ensureTable();
            $stmt = getDB()->prepare("
                SELECT session_data
                FROM app_sessions
                WHERE id = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $data = $stmt->fetchColumn();
            return $data === false ? '' : (string) $data;
        } catch (Throwable $e) {
            error_log('[session] read failed: ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $this->ensureTable();
            $expiresAt = date('Y-m-d H:i:s', time() + $this->lifetime);
            $stmt = getDB()->prepare("
                INSERT INTO app_sessions (id, session_data, expires_at, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    session_data = VALUES(session_data),
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()
            ");
            return $stmt->execute([$id, $data, $expiresAt]);
        } catch (Throwable $e) {
            error_log('[session] write failed: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $this->ensureTable();
            $stmt = getDB()->prepare("DELETE FROM app_sessions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            error_log('[session] destroy failed: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $this->ensureTable();
            return getDB()->exec("DELETE FROM app_sessions WHERE expires_at <= NOW()");
        } catch (Throwable $e) {
            error_log('[session] gc failed: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureTable(): void
    {
        if ($this->tableChecked) {
            return;
        }

        getDB()->exec("
            CREATE TABLE IF NOT EXISTS app_sessions (
                id VARCHAR(128) PRIMARY KEY,
                session_data MEDIUMBLOB NOT NULL,
                expires_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_app_sessions_expires (expires_at)
            ) ENGINE=InnoDB
        ");

        $this->tableChecked = true;
    }
}

configureSession();

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

    // Session points to a user that no longer exists. Do not destroy the whole
    // browser session here; just remove the invalid login marker.
    if ($user === false) {
        unset($_SESSION['user_id'], $_SESSION['header_notifications']);
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
