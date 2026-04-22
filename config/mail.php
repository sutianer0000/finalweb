<?php
// SMTP configuration — reads credentials from env vars set in config/local.php.
// Required: SMTP_USERNAME, SMTP_PASSWORD, MAIL_FROM.
// Others fall back to Gmail defaults if unset.

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
