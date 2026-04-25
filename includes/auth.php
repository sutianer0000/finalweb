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
            $userId = $this->extractUserId($data);
            $stmt = getDB()->prepare("
                INSERT INTO app_sessions (id, session_data, user_id, expires_at, last_seen_at, updated_at)
                VALUES (?, ?, ?, ?, IF(? IS NULL, NULL, NOW()), NOW())
                ON DUPLICATE KEY UPDATE
                    session_data = VALUES(session_data),
                    user_id = VALUES(user_id),
                    expires_at = VALUES(expires_at),
                    last_seen_at = VALUES(last_seen_at),
                    updated_at = NOW()
            ");
            return $stmt->execute([$id, $data, $userId, $expiresAt, $userId]);
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
                user_id INT DEFAULT NULL,
                expires_at DATETIME NOT NULL,
                last_seen_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_app_sessions_user (user_id),
                INDEX idx_app_sessions_last_seen (last_seen_at),
                INDEX idx_app_sessions_expires (expires_at)
            ) ENGINE=InnoDB
        ");

        $this->addColumnIfMissing('user_id', 'ALTER TABLE app_sessions ADD COLUMN user_id INT DEFAULT NULL AFTER session_data');
        $this->addColumnIfMissing('last_seen_at', 'ALTER TABLE app_sessions ADD COLUMN last_seen_at DATETIME DEFAULT NULL AFTER expires_at');
        $this->addIndexIfMissing('idx_app_sessions_user', 'ALTER TABLE app_sessions ADD INDEX idx_app_sessions_user (user_id)');
        $this->addIndexIfMissing('idx_app_sessions_last_seen', 'ALTER TABLE app_sessions ADD INDEX idx_app_sessions_last_seen (last_seen_at)');

        $this->tableChecked = true;
    }

    private function extractUserId(string $data): ?int
    {
        if (
            preg_match('/(?:^|;)user_id\|i:(\d+);/', $data, $matches) !== 1
            && preg_match('/(?:^|;)user_id\|s:\d+:"(\d+)";/', $data, $matches) !== 1
        ) {
            return null;
        }

        $userId = (int) $matches[1];
        return $userId > 0 ? $userId : null;
    }

    private function addColumnIfMissing(string $columnName, string $sql): void
    {
        $stmt = getDB()->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'app_sessions'
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$columnName]);
        if ($stmt->fetchColumn() === false) {
            getDB()->exec($sql);
        }
    }

    private function addIndexIfMissing(string $indexName, string $sql): void
    {
        $stmt = getDB()->prepare("
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'app_sessions'
              AND INDEX_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$indexName]);
        if ($stmt->fetchColumn() === false) {
            getDB()->exec($sql);
        }
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

// Require that current user is an admin or superadmin
function requireAdmin() {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || !in_array($user['role'], ['admin', 'superadmin'], true)) {
        redirect(BASE_URL . '/login.php');
    }
}

// Require that current user is a superadmin
function requireSuperAdmin() {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'superadmin') {
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

function logActivity(string $action, array $options = []): void {
    try {
        ensureActivityLogTable();

        $actorId = $options['actor_user_id'] ?? ($_SESSION['user_id'] ?? null);
        $actorEmail = $options['actor_email'] ?? null;
        $actorRole = $options['actor_role'] ?? null;
        $targetUserId = $options['target_user_id'] ?? null;
        $targetEmail = $options['target_email'] ?? null;

        if ($actorId && (!$actorEmail || !$actorRole)) {
            $stmt = getDB()->prepare("SELECT email, role FROM users WHERE id = ?");
            $stmt->execute([$actorId]);
            $actor = $stmt->fetch();
            if ($actor) {
                $actorEmail = $actorEmail ?: $actor['email'];
                $actorRole = $actorRole ?: $actor['role'];
            }
        }

        if ($targetUserId && !$targetEmail) {
            $stmt = getDB()->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $targetEmail = $stmt->fetchColumn() ?: null;
        }

        $details = $options['details'] ?? null;
        $detailsJson = $details === null ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stmt = getDB()->prepare("
            INSERT INTO activity_logs (
                actor_user_id, actor_email, actor_role,
                target_user_id, target_email,
                action, entity_type, entity_id, details_json,
                ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $actorId ?: null,
            $actorEmail,
            $actorRole,
            $targetUserId ?: null,
            $targetEmail,
            $action,
            $options['entity_type'] ?? null,
            $options['entity_id'] ?? null,
            $detailsJson,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('[activity] log failed: ' . $e->getMessage());
    }
}

function ensureActivityLogTable(): void {
    static $checked = false;
    if ($checked) {
        return;
    }

    getDB()->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_user_id INT DEFAULT NULL,
            actor_email VARCHAR(255) DEFAULT NULL,
            actor_role VARCHAR(30) DEFAULT NULL,
            target_user_id INT DEFAULT NULL,
            target_email VARCHAR(255) DEFAULT NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(80) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            details_json TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_actor (actor_user_id),
            INDEX idx_activity_target (target_user_id),
            INDEX idx_activity_actor_email (actor_email),
            INDEX idx_activity_target_email (target_email),
            INDEX idx_activity_action (action),
            INDEX idx_activity_created (created_at)
        ) ENGINE=InnoDB
    ");

    $checked = true;
}
