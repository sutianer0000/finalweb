<?php
// SMTP Configuration template for PHPMailer.
// Copy this file to `config/mail.php` and fill in real credentials there.
// `config/mail.php` is in .gitignore and must NEVER be committed.
//
// For Gmail: enable 2-Step Verification, then create an App Password at
// https://myaccount.google.com/apppasswords and use it as SMTP_PASSWORD.

define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USERNAME',  'your_gmail_address@gmail.com');
define('SMTP_PASSWORD',  'your_16_char_app_password');
define('SMTP_SECURE',    'tls');   // 'tls' for 587, 'ssl' for 465
define('MAIL_FROM',      'your_gmail_address@gmail.com');
define('MAIL_FROM_NAME', 'E-Wallet');
