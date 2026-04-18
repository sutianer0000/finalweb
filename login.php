<?php
require_once __DIR__ . '/includes/auth.php';

// Nếu đã đăng nhập thì chuyển hướng
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

// Xử lý ngôn ngữ
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'en') ? 'en' : 'vi';
}
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'vi';
}

$lang = $_SESSION['lang'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = $lang === 'en' 
            ? 'Please enter both username and password.' 
            : 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR phone_number = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = $lang === 'en' 
                ? 'Invalid username or password.' 
                : 'Tên đăng nhập hoặc mật khẩu không đúng.';
        } else {
            if ($user['status'] === 'disabled') {
                $errors[] = $lang === 'en' 
                    ? 'This account has been disabled, please contact the hotline 18001008.' 
                    : 'Tài khoản đã bị vô hiệu hóa, vui lòng liên hệ hotline 18001008.';
            }
            elseif ($user['permanently_locked'] == 1) {
                $errors[] = $lang === 'en' 
                    ? 'Account has been locked due to entering the wrong password many times, please contact the administrator.' 
                    : 'Tài khoản đã bị khóa vĩnh viễn do nhập sai mật khẩu nhiều lần. Vui lòng liên hệ quản trị viên.';
            }
            elseif ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                $errors[] = $lang === 'en' 
                    ? 'Account is currently locked, please try again in 1 minute.' 
                    : 'Tài khoản đang bị tạm khóa, vui lòng thử lại sau 1 phút.';
            } 
            else {
                // Xóa khóa tạm nếu đã hết hạn
                if ($user['locked_until'] !== null && strtotime($user['locked_until']) <= time()) {
                    $db->prepare("UPDATE users SET locked_until = NULL WHERE id = ?")
                       ->execute([$user['id']]);
                }

                if (password_verify($password, $user['password'])) {
                    // Đăng nhập thành công
                    $db->prepare("UPDATE users SET failed_login_attempts = 0, has_abnormal_login = 0, locked_until = NULL WHERE id = ?")
                       ->execute([$user['id']]);

                    $db->prepare("INSERT INTO login_history (user_id, ip_address, status) VALUES (?, ?, 'success')")
                       ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);

                    $_SESSION['user_id'] = $user['id'];

                    if ($user['role'] === 'admin') {
                        redirect('/finalweb/admin/dashboard.php');
                    } elseif ($user['first_login'] == 1) {
                        redirect('/finalweb/first_login_password.php');
                    } else {
                        redirect('/finalweb/dashboard.php');
                    }
                } else {
                    // Sai mật khẩu
                    if ($user['role'] !== 'admin') {
                        $failedAttempts = $user['failed_login_attempts'] + 1;

                        $db->prepare("INSERT INTO login_history (user_id, ip_address, status) VALUES (?, ?, 'failed')")
                           ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);

                        if ($failedAttempts >= 3) {
                            if ($user['has_abnormal_login'] == 1) {
                                $db->prepare("UPDATE users SET permanently_locked = 1, permanently_locked_at = NOW(), failed_login_attempts = ? WHERE id = ?")
                                   ->execute([$failedAttempts, $user['id']]);
                                $errors[] = $lang === 'en' 
                                    ? 'Account has been locked due to entering the wrong password many times.' 
                                    : 'Tài khoản đã bị khóa vĩnh viễn do nhập sai mật khẩu nhiều lần.';
                            } else {
                                $lockedUntil = date('Y-m-d H:i:s', time() + 60);
                                $db->prepare("UPDATE users SET failed_login_attempts = 0, has_abnormal_login = 1, locked_until = ? WHERE id = ?")
                                   ->execute([$lockedUntil, $user['id']]);
                                $errors[] = $lang === 'en' 
                                    ? 'Account is currently locked, please try again in 1 minute.' 
                                    : 'Tài khoản đang bị tạm khóa, vui lòng thử lại sau 1 phút.';
                            }
                        } else {
                            $db->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?")
                               ->execute([$failedAttempts, $user['id']]);
                            $errors[] = $lang === 'en' 
                                ? 'Invalid username or password.' 
                                : 'Tên đăng nhập hoặc mật khẩu không đúng.';
                        }
                    } else {
                        $errors[] = $lang === 'en' 
                            ? 'Invalid username or password.' 
                            : 'Tên đăng nhập hoặc mật khẩu không đúng.';
                    }
                }
            }
        }
    }
}
require_once __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang === 'en' ? 'Login - E-Wallet' : 'Đăng nhập - Ví Điện Tử' ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- CSS riêng -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">
    <div class="login-card">

        <!-- Language Switcher -->
        <div class="language-switcher">
            <div class="btn-group btn-group-sm" role="group">
                <a href="?lang=vi" class="btn <?= $lang === 'vi' ? 'btn-primary' : 'btn-outline-primary' ?>">🇻🇳 VI</a>
                <a href="?lang=en" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-primary' ?>">🇬🇧 EN</a>
            </div>
        </div>

        <div class="card">
            <!-- Header -->
            <div class="card-header bg-primary text-white text-center py-4">
                <i class="bi bi-wallet2 fs-1 mb-2"></i>
                <h3 class="mb-1 fw-bold">
                    <?= $lang === 'en' ? 'E-Wallet' : 'Ví Điện Tử' ?>
                </h3>
                <p class="mb-0 opacity-75">
                    <?= $lang === 'en' ? 'Sign in to continue' : 'Đăng nhập để tiếp tục' ?>
                </p>
            </div>

            <div class="card-body p-5">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-1"><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="mb-4">
                        <label for="username" class="form-label fw-medium">
                            <?= $lang === 'en' ? 'Email or Phone Number' : 'Email hoặc Số điện thoại' ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="username" 
                                   name="username"
                                   placeholder="<?= $lang === 'en' ? 'Enter email or phone' : 'Nhập email hoặc số điện thoại' ?>"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                   required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-medium">
                            <?= $lang === 'en' ? 'Password' : 'Mật khẩu' ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" 
                                   class="form-control form-control-lg" 
                                   id="password" 
                                   name="password"
                                   placeholder="<?= $lang === 'en' ? 'Enter your password' : 'Nhập mật khẩu' ?>" 
                                   required>
                        </div>
                    </div>

                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            <?= $lang === 'en' ? 'Login' : 'Đăng nhập' ?>
                        </button>
                    </div>

                    <div class="d-flex justify-content-between align-items-center small">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                <?= $lang === 'en' ? 'Remember me' : 'Ghi nhớ đăng nhập' ?>
                            </label>
                        </div>
                        <a href="/finalweb/forgot_password.php" class="text-decoration-none">
                            <?= $lang === 'en' ? 'Forgot password?' : 'Quên mật khẩu?' ?>
                        </a>
                    </div>
                </form>

                <div class="text-center mt-5">
                    <p class="text-muted mb-0">
                        <?= $lang === 'en' ? "Don't have an account?" : 'Chưa có tài khoản?' ?> 
                        <a href="/finalweb/register.php" class="text-primary fw-medium text-decoration-none">
                            <?= $lang === 'en' ? 'Register here' : 'Đăng ký ngay' ?>
                        </a>
                    </p>
                </div>
            </div>

            <?php require_once __DIR__ . '/includes/footer.php'; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>