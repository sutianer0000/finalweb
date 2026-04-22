<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';

// If already logged in, redirect appropriately
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'admin') {
        redirect(BASE_URL . '/admin/dashboard.php');
    }
    if ($user['first_login'] == 1) {
        redirect(BASE_URL . '/first_login_password.php');
    }
    redirect(BASE_URL . '/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = __("please_enter_both");
    } else {
        // Find user by email or phone
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR phone_number = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = __("invalid_username_or_password");
        } else {
            // Check if account is disabled
            if ($user['status'] === 'disabled') {
                $errors[] = __("account_disabled");
            }
            // Check if permanently locked
            elseif ($user['permanently_locked'] == 1) {
                $errors[] = __("account_permanently_locked");
            }
            // Check if temporarily locked (1 minute)
            elseif ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                $errors[] = __("account_temporarily_locked");
            }
            else {
                // Clear temporary lock if expired
                if ($user['locked_until'] !== null && strtotime($user['locked_until']) <= time()) {
                    $db->prepare("UPDATE users SET locked_until = NULL WHERE id = ?")->execute([$user['id']]);
                }

                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Successful login — reset failed attempts and abnormal login flag
                    $db->prepare("UPDATE users SET failed_login_attempts = 0, has_abnormal_login = 0, locked_until = NULL WHERE id = ?")
                       ->execute([$user['id']]);

                    // Record successful login
                    $db->prepare("INSERT INTO login_history (user_id, ip_address, status) VALUES (?, ?, 'success')")
                       ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);

                    // Set session
                    $_SESSION['user_id'] = $user['id'];

                    // Redirect based on role and first_login
                    if ($user['role'] === 'admin') {
                        redirect(BASE_URL . '/admin/dashboard.php');
                    } elseif ($user['first_login'] == 1) {
                        redirect(BASE_URL . '/first_login_password.php');
                    } else {
                        redirect(BASE_URL . '/dashboard.php');
                    }
                } else {
                    // Wrong password — only apply lock logic for non-admin
                    if ($user['role'] !== 'admin') {
                        $failedAttempts = $user['failed_login_attempts'] + 1;

                        // Record failed login
                        $db->prepare("INSERT INTO login_history (user_id, ip_address, status) VALUES (?, ?, 'failed')")
                           ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);

                        if ($failedAttempts >= 3) {
                            if ($user['has_abnormal_login'] == 1) {
                                // Already had abnormal login before — permanently lock
                                $db->prepare("UPDATE users SET permanently_locked = 1, permanently_locked_at = NOW(), failed_login_attempts = ? WHERE id = ?")
                                   ->execute([$failedAttempts, $user['id']]);
                                $errors[] = __("account_permanently_locked");
                            } else {
                                // First time reaching 3 fails — temp lock 1 minute, set abnormal flag
                                $lockedUntil = date('Y-m-d H:i:s', time() + 60);
                                $db->prepare("UPDATE users SET failed_login_attempts = 0, has_abnormal_login = 1, locked_until = ? WHERE id = ?")
                                   ->execute([$lockedUntil, $user['id']]);
                                $errors[] = __("account_temporarily_locked");
                            }
                        } else {
                            // Increment failed attempts
                            $db->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?")
                               ->execute([$failedAttempts, $user['id']]);
                            $errors[] = __("invalid_username_or_password");
                        }
                    } else {
                        // Admin — no lock, just show error
                        $errors[] = __("invalid_username_or_password");
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= sanitize($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __("login_title") ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
    <div class="login-card">

        <div class="card mx-auto" style="max-width: 420px;">
            <div class="card-header bg-primary text-white text-center py-4">
                <i class="bi bi-wallet2 fs-1 mb-2"></i>
                <h3 class="mb-1 fw-bold"><?= __("e_wallet") ?></h3>
                <p class="mb-0 opacity-75"><?= __("sign_in_to_continue") ?></p>
            </div>

            <div class="card-body p-5">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-1"><?= sanitize($error) ?></p>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="mb-4">
                        <label for="username" class="form-label fw-medium"><?= __("email_or_phone") ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="username"
                                   name="username"
                                   placeholder="<?= __("enter_email_or_phone") ?>"
                                   value="<?= sanitize($_POST['username'] ?? '') ?>"
                                   required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-medium"><?= __("password") ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password"
                                   class="form-control form-control-lg"
                                   id="password"
                                   name="password"
                                   placeholder="<?= __("enter_password") ?>"
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    id="togglePassword"
                                    title="Show/Hide password">
                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            <?= __("login") ?>
                        </button>
                    </div>

                    <div class="d-flex justify-content-between align-items-center small">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember"><?= __("remember_me") ?></label>
                        </div>
                        <a href="<?= BASE_URL ?>/forgot_password.php" class="text-decoration-none">
                            <?= __("forgot_password") ?>
                        </a>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <p class="text-muted mb-0">
                        <?= __("dont_have_account") ?>
                        <a href="<?= BASE_URL ?>/register.php" class="text-primary fw-medium text-decoration-none">
                            <?= __("register_here") ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="language-switcher-fixed">
        <div class="btn-group btn-group-sm" role="group">
            <a href="?lang=vi" class="btn <?= $lang === 'vi' ? 'btn-primary' : 'btn-outline-primary' ?>">🇻🇳 VI</a>
            <a href="?lang=en" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-primary' ?>">🇬🇧 EN</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput  = document.getElementById('password');
    const toggleIcon     = document.getElementById('togglePasswordIcon');

    togglePassword.addEventListener('click', function () {
        const isHidden = passwordInput.type === 'password';
        passwordInput.type  = isHidden ? 'text' : 'password';
        toggleIcon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>
</body>
</html>
