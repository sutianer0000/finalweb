# E-Wallet — Web Programming Final Project (503073)

A PHP + MySQL web-based e-wallet with registration, admin verification,
deposit/withdraw/transfer, phone-card purchase, and transaction history.

---

## Requirements

- **XAMPP** (Apache + PHP 7.4+ + MySQL/MariaDB)
- **Composer** — https://getcomposer.org/download/
- A Gmail account with an **App Password** (for sending emails)
  - Enable 2-Step Verification, then create one at
    https://myaccount.google.com/apppasswords

---

## Setup (first time)

### 1. Clone into XAMPP's `htdocs`

```bash
cd /path/to/xampp/htdocs
git clone https://github.com/sutianer0000/finalweb.git
cd finalweb
```

### 2. Install PHP dependencies

```bash
composer install
```

This creates the `vendor/` folder (PHPMailer, etc.).

### 3. Create local config files

Two config files are **gitignored** because they hold secrets. Copy the
templates and fill in your own values.

```bash
cp config/database.example.php config/database.php
cp config/mail.example.php     config/mail.php
```

Open **`config/database.php`** and set:

```php
define('DB_USER', 'root');      // your local MySQL user
define('DB_PASS', '');          // your local MySQL password
```

Open **`config/mail.php`** and set:

```php
define('SMTP_USERNAME', 'your.gmail@gmail.com');
define('SMTP_PASSWORD', 'your_16_char_app_password');
define('MAIL_FROM',     'your.gmail@gmail.com');
```

> Ask the team lead for the shared SMTP app password for a shared test gmail.

### 4. Import the database

Start XAMPP → Apache + MySQL.
Open http://localhost/phpmyadmin → **Import** → select `database.sql` → **Go**.

The script creates the `ewallet` database, all tables, the admin account,
and three simulated credit cards.

### 5. Run the site

Open http://localhost/finalweb/

---

## Default login

| Role   | Email               | Password   |
|--------|---------------------|------------|
| Admin  | admin@ewallet.com   | `password` |
| User   | *register a new one via the UI* | |

A newly registered user gets a random 6-char password shown on screen (and
emailed if SMTP is configured). The user must change it on first login.

---

## Simulated credit cards (for deposit/withdraw testing)

| # | Card number | Expiration   | CVV | Behavior                                       |
|---|-------------|--------------|-----|------------------------------------------------|
| 1 | 111111      | 10/10/2022   | 411 | Unlimited deposits / used for withdrawals      |
| 2 | 222222      | 11/11/2022   | 443 | Max 1,000,000 VND per deposit                  |
| 3 | 333333      | 12/12/2022   | 577 | Always fails: "card is out of money"           |

---

## Project layout

```
finalweb/
├── assets/css/          Stylesheets
├── config/
│   ├── database.example.php   → copy to database.php (gitignored)
│   └── mail.example.php       → copy to mail.php     (gitignored)
├── includes/            Shared PHP (auth, mailer, header, footer)
├── uploads/id_cards/    User-uploaded ID photos (gitignored)
├── vendor/              Composer packages (auto-installed)
├── database.sql         Full schema + seed data
├── register.php         User registration
├── login.php            Login
├── first_login_password.php  Force password change on first login
├── dashboard.php        Main user page
└── logout.php
```

---

## Deploying to public hosting

1. Upload everything **except** `vendor/`, `config/mail.php`,
   `config/database.php`, and `uploads/`
2. On the server, run `composer install` (or upload `vendor/` manually if
   the host doesn't support Composer)
3. Create `config/mail.php` and `config/database.php` on the server with
   the production credentials (via the host's file manager / FTP)
4. Import `database.sql` into the hosting provider's MySQL

---

## Troubleshooting

- **"Database connection failed"** — check `config/database.php` credentials
  and that MySQL is running in XAMPP.
- **Email not sending** — the registration success page shows the exact
  PHPMailer error. Usually: wrong app password, 2FA not enabled on Gmail,
  or port 587 blocked.
- **`vendor/` missing / class not found** — run `composer install`.
- **`config/*.php` missing** — you skipped step 3 of setup.

---

## Team rules

- **Never commit** `config/mail.php`, `config/database.php`, `vendor/`,
  `uploads/`, or `composer.phar` — `.gitignore` blocks them.
- **Always** run `git status` before `git commit` to double-check.
- Share SMTP / DB passwords through a secure channel (not Git, not
  public chat).
