<?php
// Database configuration — reads from environment variables.
// On Railway: set DATABASE_URL to ${{MySQL.MYSQL_URL}} (or individual DB_* vars).
// Locally (XAMPP): optionally create config/local.php with putenv() calls; see config/local.example.php.

if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

$databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');

if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    define('DB_HOST', $parts['host'] ?? 'localhost');
    define('DB_PORT', $parts['port'] ?? 3306);
    define('DB_USER', $parts['user'] ?? 'root');
    define('DB_PASS', isset($parts['pass']) ? urldecode($parts['pass']) : '');
    define('DB_NAME', isset($parts['path']) ? ltrim($parts['path'], '/') : 'ewallet');
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: 'ewallet');
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
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed.");
        }
    }
    return $pdo;
}
