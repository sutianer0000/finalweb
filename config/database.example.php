<?php
// Database Configuration template.
// Copy this file to `config/database.php` and fill in local MySQL credentials.
// `config/database.php` is in .gitignore and must NEVER be committed.

define('DB_HOST', 'localhost');
define('DB_NAME', 'ewallet');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create PDO connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
