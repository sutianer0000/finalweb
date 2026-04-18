<?php
// SMTP Configuration template for PHPMailer — using Resend.
// Copy this file to `config/mail.php` and fill in real credentials there.
// `config/mail.php` is in .gitignore and must NEVER be committed.
//
// Setup:
//   1. Sign up at https://resend.com
//   2. Create an API key at https://resend.com/api-keys
//   3. Paste the key (starts with "re_...") into SMTP_PASSWORD below
//   4. For testing: leave MAIL_FROM as 'onboarding@resend.dev' — but note
//      that without a verified domain you can only send to your own
//      Resend account email.
//   5. For production: verify a domain at https://resend.com/domains,
//      then set MAIL_FROM to an address on that domain.

define('SMTP_HOST',      'smtp.resend.com');
define('SMTP_PORT',      587);
define('SMTP_USERNAME',  'resend');                        // literal string "resend"
define('SMTP_PASSWORD',  're_YOUR_RESEND_API_KEY_HERE');   // paste your API key
define('SMTP_SECURE',    'tls');                           // 'tls' for 587, 'ssl' for 465
define('MAIL_FROM',      'onboarding@resend.dev');         // or your verified-domain address
define('MAIL_FROM_NAME', 'E-Wallet');
