<?php
// Database configuration. Local XAMPP can use config/local.php; deployed
// environments can set DATABASE_URL/MYSQL_URL or the individual DB_* vars.

if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

date_default_timezone_set(getenv('APP_TZ') ?: 'Asia/Ho_Chi_Minh');

$databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');

if ($databaseUrl) {
    $parts = parse_url($databaseUrl);

    define('DB_HOST', $parts['host'] ?? 'localhost');
    define('DB_PORT', (int)($parts['port'] ?? 3306));
    define('DB_USER', isset($parts['user']) ? urldecode($parts['user']) : 'root');
    define('DB_PASS', isset($parts['pass']) ? urldecode($parts['pass']) : '');
    define('DB_NAME', isset($parts['path']) ? ltrim($parts['path'], '/') : 'ewallet');
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: 'ewallet');
}

function renderDatabaseMaintenancePage(Throwable $e): void {
    error_log('[db] connect failed: ' . $e->getMessage());

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection failed. The service is temporarily under maintenance.\n");
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 300');
    }

    require __DIR__ . '/../maintenance.php';
    exit;
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
                ]
            );
        } catch (PDOException $e) {
            renderDatabaseMaintenancePage($e);
        }
    }
    return $pdo;
}
