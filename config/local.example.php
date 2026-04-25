<?php
// Template for local development. Copy to config/local.php and fill in real values.
// config/local.php is gitignored — never commit actual credentials.
// This file is only read during local XAMPP dev; deployed apps use platform env vars/secrets.

// --- URL base path for XAMPP (app lives at http://localhost/finalweb/) ---
putenv('BASE_URL=/finalweb');

// --- Local MySQL ---
putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_NAME=ewallet');
putenv('DB_USER=root');
putenv('DB_PASS=');

// --- Local SMTP (Gmail: enable 2FA, create an App Password) ---
putenv('SMTP_HOST=smtp.gmail.com');
putenv('SMTP_PORT=587');
putenv('SMTP_USERNAME=youraddress@gmail.com');
putenv('SMTP_PASSWORD=your 16-char app password');
putenv('SMTP_SECURE=tls');
putenv('MAIL_FROM=youraddress@gmail.com');
putenv('MAIL_FROM_NAME=E-Wallet');
