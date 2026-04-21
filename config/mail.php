<?php
// SMTP configuration — reads from environment variables.
// Required env vars (set on Railway dashboard, or in config/local.php for XAMPP):
//   SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_SECURE, MAIL_FROM, MAIL_FROM_NAME

if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

define('SMTP_HOST',      getenv('SMTP_HOST')      ?: 'smtp.gmail.com');
define('SMTP_PORT',      (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME',  getenv('SMTP_USERNAME')  ?: '');
define('SMTP_PASSWORD',  getenv('SMTP_PASSWORD')  ?: '');
define('SMTP_SECURE',    getenv('SMTP_SECURE')    ?: 'tls');
define('MAIL_FROM',      getenv('MAIL_FROM')      ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'E-Wallet');
