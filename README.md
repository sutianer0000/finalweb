# E-Wallet — thin-local branch (frontend dev)

A PHP + MySQL web-based e-wallet: registration, admin verification,
deposit / withdraw / transfer, phone-card purchase, transaction history.

> **This branch is for local XAMPP development only.** It has no Docker,
> Fly.io, CI, or migration files — those live on `main`. Clone this branch,
> drop in your credentials, and you're running on localhost in a few minutes.

---

## Requirements

- **XAMPP** — Apache + PHP 8.0+ + MySQL/MariaDB
- **Composer** — https://getcomposer.org/download/
- A **Gmail app password** for sending mail
  (or ask the team lead — they'll share one)

---

## First-time setup

### 1. Clone into XAMPP's `htdocs` on the `thin-local` branch

```bash
cd /path/to/xampp/htdocs
git clone -b thin-local <repo-url> finalweb
cd finalweb
```

### 2. Install PHP dependencies

```bash
composer install
```

Creates the `vendor/` folder (PHPMailer + deps). It's gitignored.

### 3. Create your local credentials file

One file holds everything — DB + SMTP. It's **gitignored** so your secrets
never leave your machine.

```bash
cp config/local.example.php config/local.php
```

Open **`config/local.php`** and set:

```php
// --- Local MySQL (XAMPP defaults shown) ---
putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_NAME=ewallet');
putenv('DB_USER=root');
putenv('DB_PASS=');

// --- Gmail SMTP (team lead will share these) ---
putenv('SMTP_USERNAME=<provided>');
putenv('SMTP_PASSWORD=<provided>');
putenv('MAIL_FROM=<provided>');
```

Leave `BASE_URL=/finalweb` unchanged unless you renamed the folder.

### 4. Import the database

Start XAMPP → Apache + MySQL.
Open http://localhost/phpmyadmin → **Import** → select `database.sql` → **Go**.

The script creates the `ewallet` database, all tables, the admin account,
and three simulated credit cards.

### 5. Open the app

http://localhost/finalweb/

---

## Default login

| Role  | Email              | Password   |
|-------|--------------------|------------|
| Admin | admin@ewallet.com  | `password` |
| User  | register via the UI — a random 6-char password is shown and emailed |

New users must change their password on first login.

---

## Simulated credit cards (for deposit / withdraw testing)

| # | Card number | Expiration | CVV | Behavior                              |
|---|-------------|------------|-----|---------------------------------------|
| 1 | 111111      | 10/10/2022 | 411 | Unlimited deposits, supports withdraw |
| 2 | 222222      | 11/11/2022 | 443 | Max 1,000,000 VND per deposit         |
| 3 | 333333      | 12/12/2022 | 577 | Always fails ("out of money")         |

---

## Project layout

```
finalweb/
├── assets/
│   ├── css/                   Stylesheets
│   └── js/id-card-resize.js   Client-side image crop/resize for uploads
├── config/
│   ├── database.php           Reads env vars (set in local.php)
│   ├── mail.php               Reads env vars (set in local.php)
│   ├── local.example.php      → copy to local.php (gitignored)
│   └── local.php              YOUR CREDENTIALS — never commit
├── includes/                  Shared PHP (auth, mailer, image util, header, footer)
├── admin/                     Admin-only pages
├── image.php                  Streams ID card photos from DB (auth-gated, ETag cached)
├── database.sql               Full schema + seed data
├── register.php / login.php / dashboard.php / ...
└── vendor/                    Composer packages (gitignored)
```

---

## Working on the frontend

- Styles live in `assets/css/`
- Layout shell is in `includes/header.php` + `includes/footer.php` (Bootstrap 5)
- Bootstrap + Bootstrap Icons are loaded from CDN in `header.php`

---

## Troubleshooting

- **"Database connection failed"** — MySQL isn't running, or DB creds in
  `config/local.php` are wrong, or you haven't imported `database.sql`.
- **Email not sending** — check `SMTP_USERNAME` / `SMTP_PASSWORD`. Must be a
  Gmail **App Password**, not your normal password. Port 587 must not be
  blocked by your network.
- **`vendor/` missing / class not found** — run `composer install`.
- **Blank page** — enable error display: add
  `ini_set('display_errors', 1); error_reporting(E_ALL);` at the top of the
  affected PHP file, or check XAMPP's PHP error log.

---

## Team rules

- **Never commit** `config/local.php`, `vendor/`, `uploads/`, or
  `composer.phar` — `.gitignore` blocks them. Run `git status` before every
  commit to double-check.
- Share passwords through a secure channel — not Git, not public chat.
- Keep frontend-only work on this `thin-local` branch. Deploy changes go
  on `main`.
