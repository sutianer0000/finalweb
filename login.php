<?php
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect appropriately
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'admin') {
        redirect('/finalweb/admin/dashboard.php');
    }
    if ($user['first_login'] == 1) {
        redirect('/finalweb/first_login_password.php');
    }
    redirect('/finalweb/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'Please enter both username and password.';
    } else {
        // Find user by email or phone
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR phone_number = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = 'Invalid username or password.';
        } else {
            // Check if account is disabled
            if ($user['status'] === 'disabled') {
                $errors[] = 'This account has been disabled, please contact the hotline 18001008.';
            }
            // Check if permanently locked
            elseif ($user['permanently_locked'] == 1) {
                $errors[] = 'Account has been locked due to entering the wrong password many times, please contact the administrator for support.';
            }
            // Check if temporarily locked (1 minute)
            elseif ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                $errors[] = 'Account is currently locked, please try again in 1 minute.';
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
                        redirect('/finalweb/admin/dashboard.php');
                    } elseif ($user['first_login'] == 1) {
                        redirect('/finalweb/first_login_password.php');
                    } else {
                        redirect('/finalweb/dashboard.php');
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
                                $errors[] = 'Account has been locked due to entering the wrong password many times, please contact the administrator for support.';
                            } else {
                                // First time reaching 3 fails — temp lock 1 minute, set abnormal flag
                                $lockedUntil = date('Y-m-d H:i:s', time() + 60);
                                $db->prepare("UPDATE users SET failed_login_attempts = 0, has_abnormal_login = 1, locked_until = ? WHERE id = ?")
                                   ->execute([$lockedUntil, $user['id']]);
                                $errors[] = 'Account is currently locked, please try again in 1 minute.';
                            }
                        } else {
                            // Increment failed attempts
                            $db->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?")
                               ->execute([$failedAttempts, $user['id']]);
                            $errors[] = 'Invalid username or password.';
                        }
                    } else {
                        // Admin — no lock, just show error
                        $errors[] = 'Invalid username or password.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="login-card">
    <div class="card">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0"><i class="bi bi-box-arrow-in-right"></i> Login to E-Wallet</h4>
        </div>
        <div class="card-body p-4">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><?= sanitize($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Email or Phone Number</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Enter your email or phone number"
                           value="<?= sanitize($_POST['username'] ?? '') ?>" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </button>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="/finalweb/forgot_password.php">Forgot your password?</a>
            </div>
            <div class="text-center mt-2">
                <p>Don't have an account? <a href="/finalweb/register.php">Register here</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
