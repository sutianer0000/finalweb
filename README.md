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
- **A modern browser with JavaScript enabled** — the ID card upload form
  resizes / crops images in the browser before sending (no server GD
  extension needed)
- A **Gmail app password** for sending mail — ask the team lead, they'll
  share a shared test-account password

---

## First-time setup

### 1. Clone into XAMPP's `htdocs` on the `thin-local` branch

```bash
cd /path/to/xampp/htdocs
git clone -b thin-local <repo-url> finalweb
cd finalweb
```

Folder name **must be `finalweb`** (or you'll need to change `BASE_URL` —
see step 3).

### 2. Install PHP dependencies

```bash
composer install
```

Creates the `vendor/` folder (PHPMailer + deps). It's gitignored.

### 3. Create your local credentials file

One file holds everything — DB + SMTP + URL prefix. It's **gitignored** so
your secrets never leave your machine.

```bash
cp config/local.example.php config/local.php
```

Open **`config/local.php`** and set:

```php
// --- URL prefix. Leave as-is unless you cloned into a different folder. ---
putenv('BASE_URL=/finalweb');

// --- Local MySQL (XAMPP defaults shown) ---
putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_NAME=ewallet');
putenv('DB_USER=root');      // XAMPP default
putenv('DB_PASS=');          // XAMPP default is empty

// --- Gmail SMTP (team lead will share these three) ---
putenv('SMTP_USERNAME=<provided>');
putenv('SMTP_PASSWORD=<provided 16-char app password>');
putenv('MAIL_FROM=<provided>');
```

You don't need your own Gmail — the team lead's shared test account is fine.

### 4. Import the database

1. Start XAMPP → turn on Apache + MySQL.
2. Open http://localhost/phpmyadmin
3. Click **Import** in the top menu.
4. Choose file → `database.sql` (from this repo).
5. Click **Go**.

The script creates the `ewallet` database, all tables, the admin account,
and three simulated credit cards. No need to pre-create the DB.

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

## Language (VI / EN)

The app ships with Vietnamese + English translations. Users toggle via
`🇻🇳 VI` / `🇬🇧 EN` buttons on the login and register pages. The choice
is stored in the PHP session, so it persists as you click around.

Translation strings live in `includes/lang.php` (one array per locale).
Use them in templates with `<?= __("key") ?>`.

---

## Project layout

```
finalweb/
├── assets/
│   ├── css/                   Stylesheets
│   └── js/id-card-resize.js   Browser-side image crop/resize for uploads
├── config/
│   ├── database.php           Reads env vars (set in local.php)
│   ├── mail.php               Reads env vars (set in local.php)
│   ├── local.example.php      → copy to local.php (gitignored)
│   └── local.php              YOUR CREDENTIALS — never commit
├── includes/
│   ├── auth.php               Session + guards (requireLogin / requireAdmin / ...)
│   ├── mailer.php             PHPMailer wrapper
│   ├── image_util.php         Upload validator (no GD needed)
│   ├── lang.php               VI / EN translation table + __() helper
│   ├── header.php             Shell (navbar, Bootstrap CDN)
│   └── footer.php             Shell close
├── admin/                     Admin-only pages
├── image.php                  Streams ID card photos from DB (auth-gated, ETag cached)
├── database.sql               Full schema + seed data (self-creating)
├── register.php / login.php / dashboard.php / ...
└── vendor/                    Composer packages (gitignored)
```

---

## Working on the frontend

- **Styles**: `assets/css/style.css`
- **Shared shell** for most pages: `includes/header.php` + `footer.php`
  (Bootstrap 5 + Bootstrap Icons loaded from CDN)
- **`login.php` is standalone** — it has its own `<!DOCTYPE>` and Bootstrap
  link and doesn't use `header.php`. Navbar edits won't affect the login
  page; if you want the big centered card with gradient header on other
  pages, crib its layout from `login.php`.
- **Translations**: add new keys to both `'vi'` and `'en'` arrays in
  `includes/lang.php`, then `<?= __("your_key") ?>` in the template.
- **URLs**: always use `<?= BASE_URL ?>/something.php`, never hardcode
  `/finalweb/` — the path prefix is configurable.

---

## Troubleshooting

- **"Database connection failed. Check config/local.php..."** — MySQL
  isn't running in XAMPP, or DB creds in `config/local.php` are wrong, or
  you haven't imported `database.sql` yet.
- **"Unknown column 'id_card_front_mime' in 'field list'"** — your
  `ewallet` database was imported from an older `database.sql`. Drop the
  database in phpMyAdmin and re-import the current `database.sql`, OR run
  the columns / table migration manually (ask the team lead).
- **Stale session after re-importing the DB** — if you were logged in
  before dropping the DB, the app now auto-detects this, kills the
  session, and redirects you to login. Just log in again.
- **Email not sending** — `SMTP_USERNAME` / `SMTP_PASSWORD` wrong, or a
  typo, or your network blocks port 587. Registration form shows the
  PHPMailer error right there.
- **`vendor/` missing / class not found** — run `composer install`.
- **Image upload error "Uploaded file is not a valid image"** — the file
  isn't a JPG/PNG/GIF/WEBP, or it's corrupt. Max file size is 3 MB before
  the browser-side resize.
- **Blank page** — PHP error. Enable display: add
  `ini_set('display_errors', 1); error_reporting(E_ALL);` at the top of
  the affected file, or check `e:\XAMPP\apache\logs\error.log`.

---

## Team rules

- **Never commit** `config/local.php`, `vendor/`, `uploads/`,
  `composer.phar`, editor backup files (`*.bak`, `*~`), or OS junk
  (`Thumbs.db`, `.DS_Store`). `.gitignore` blocks them — still run
  `git status` before every commit to double-check.
- Share passwords through a secure channel — not Git, not public chat.
- Keep frontend-only work on this `thin-local` branch. Backend / deploy
  changes go on `main`.
- When adding a new text string to the UI, add its translation in both
  `vi` and `en` inside `includes/lang.php`.
