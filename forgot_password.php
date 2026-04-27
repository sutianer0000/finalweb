<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// Logged-in users don't need this page
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    if ($currentUser && in_array($currentUser['role'], ['admin', 'superadmin'], true)) {
        redirect(BASE_URL . '/admin/dashboard.php');
    }
    redirect(BASE_URL . '/dashboard.php');
}

// Allow user to manually restart the flow with ?restart=1
if (isset($_GET['restart'])) {
    unset($_SESSION['forgot']);
    redirect(BASE_URL . '/forgot_password.php');
}

$db = getDB();
$errors = [];
$info   = null;

// Pipeline state is stored in the session between steps.
// Valid stages: request -> verify -> reset
$stage = $_SESSION['forgot']['stage'] ?? 'request';

// ------------------------------------------------------------------
// STEP 1 — request OTP: user enters email or phone, we email an OTP
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'request') {
    requireCsrfToken();

    $identifier = trim($_POST['identifier'] ?? '');

    if ($identifier === '') {
        $errors[] = 'Please enter your email or phone number.';
    } else {
        $stmt = $db->prepare("SELECT id, email, full_name, status FROM users WHERE email = ? OR phone_number = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = 'No account found with that email or phone number.';
        } elseif ($user['status'] === 'disabled') {
            $errors[] = 'This account has been disabled, please contact the hotline 18001008.';
        } else {
            $stmt = $db->prepare("
                SELECT created_at
                FROM otp_codes
                WHERE user_id = ?
                  AND purpose = 'password_reset'
                  AND used = 0
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$user['id']]);

            if ($stmt->fetch()) {
                $errors[] = 'Please wait 1 minute before requesting another OTP.';
            }
        }

        if (empty($errors) && !empty($user)) {
            $db->prepare("
                UPDATE otp_codes
                SET used = 1
                WHERE user_id = ?
                  AND purpose = 'password_reset'
                  AND used = 0
            ")->execute([$user['id']]);

            // Generate 6-digit OTP, store in DB with 10-minute expiry.
            // Use MySQL's clock for expires_at so it matches NOW() used in the verify step.
            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $db->prepare("
                INSERT INTO otp_codes (user_id, otp_code, purpose, expires_at)
                VALUES (?, ?, 'password_reset', DATE_ADD(NOW(), INTERVAL 10 MINUTE))
            ")->execute([$user['id'], $otp]);

            $result = sendPasswordResetOtp($user['email'], $user['full_name'], $otp);

            if (!$result['ok']) {
                $db->prepare("
                    UPDATE otp_codes
                    SET used = 1
                    WHERE user_id = ?
                      AND purpose = 'password_reset'
                      AND otp_code = ?
                      AND used = 0
                ")->execute([$user['id'], $otp]);
                $errors[] = 'Could not send OTP email. Please try again later.';
            } else {
                logActivity('password_reset_otp_requested', [
                    'target_user_id' => $user['id'],
                    'target_email' => $user['email'],
                    'entity_type' => 'auth',
                ]);
                $_SESSION['forgot'] = [
                    'stage'   => 'verify',
                    'user_id' => $user['id'],
                    'email'   => $user['email'],
                    'otp_attempts' => 0,
                ];
                $stage = 'verify';
                $info = 'We sent an OTP code to your registered email address.';
            }
        }
    }
}

// ------------------------------------------------------------------
// STEP 2 — verify OTP
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'verify') {
    requireCsrfToken();

    if (empty($_SESSION['forgot']['user_id'])) {
        $stage = 'request';
        $errors[] = 'Session expired. Please request a new OTP.';
    } else {
        $otp    = trim($_POST['otp'] ?? '');
        $userId = (int)$_SESSION['forgot']['user_id'];

        if ($otp === '') {
            $errors[] = 'Please enter the OTP code.';
            $stage = 'verify';
        } else {
            $stmt = $db->prepare("
                SELECT id FROM otp_codes
                WHERE user_id = ?
                  AND otp_code = ?
                  AND purpose = 'password_reset'
                  AND used = 0
                  AND expires_at > NOW()
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $otp]);
            $row = $stmt->fetch();

            if (!$row) {
                $_SESSION['forgot']['otp_attempts'] = (int) ($_SESSION['forgot']['otp_attempts'] ?? 0) + 1;
                if ($_SESSION['forgot']['otp_attempts'] >= 3) {
                    unset($_SESSION['forgot']);
                    $stage = 'request';
                    $errors[] = 'Too many incorrect OTP attempts. Please request a new OTP.';
                } else {
                    $errors[] = 'Invalid or expired OTP code.';
                    $stage = 'verify';
                }
            } else {
                $db->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$row['id']]);
                logActivity('password_reset_otp_verified', [
                    'target_user_id' => $userId,
                    'target_email' => $_SESSION['forgot']['email'] ?? null,
                    'entity_type' => 'auth',
                ]);
                $_SESSION['forgot']['stage']       = 'reset';
                $_SESSION['forgot']['verified_at'] = time();
                $stage = 'reset';
            }
        }
    }
}

// ------------------------------------------------------------------
// STEP 3 — set new password
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'reset') {
    requireCsrfToken();

    if (empty($_SESSION['forgot']['user_id']) || ($_SESSION['forgot']['stage'] ?? '') !== 'reset') {
        $stage = 'request';
        $errors[] = 'Session expired. Please start over.';
    } else {
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $userId          = (int)$_SESSION['forgot']['user_id'];

        if (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password = ?, first_login = 0 WHERE id = ?")
               ->execute([$hashed, $userId]);
            revokeRememberTokensForUser($userId);
            logActivity('password_reset_completed', [
                'target_user_id' => $userId,
                'target_email' => $_SESSION['forgot']['email'] ?? null,
                'entity_type' => 'auth',
            ]);

            unset($_SESSION['forgot']);
            setFlash('success', 'Password reset successfully. Please log in with your new password.');
            redirect(BASE_URL . '/login.php');
        } else {
            $stage = 'reset';
        }
    }
}

$pageTitle = 'Forgot Password';
require_once __DIR__ . '/includes/header.php';
?>

<div class="login-card">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Forgot Password</h4>
        </div>
        <div class="card-body p-4">

            <!-- Progress steps -->
            <ol class="list-group list-group-numbered list-group-horizontal small mb-4 justify-content-center">
                <li class="list-group-item <?= $stage === 'request' ? 'active' : '' ?>">Identify</li>
                <li class="list-group-item <?= $stage === 'verify'  ? 'active' : '' ?>">Verify OTP</li>
                <li class="list-group-item <?= $stage === 'reset'   ? 'active' : '' ?>">New Password</li>
            </ol>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><?= sanitize($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($info): ?>
                <div class="alert alert-success"><?= sanitize($info) ?></div>
            <?php endif; ?>

            <?php if ($stage === 'request'): ?>
                <p class="text-muted">
                    Enter your registered email or phone number. We'll email you a one-time code to reset your password.
                </p>
                <form method="POST" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="step" value="request">
                    <div class="mb-3">
                        <label for="identifier" class="form-label">Email or Phone Number</label>
                        <input type="text" class="form-control" id="identifier" name="identifier"
                               value="<?= sanitize($_POST['identifier'] ?? '') ?>"
                               placeholder="your@email.com or 0123456789" required autofocus>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-envelope-arrow-up"></i> Send OTP Code
                        </button>
                    </div>
                </form>

            <?php elseif ($stage === 'verify'): ?>
                <p class="text-muted">
                    Enter the 6-digit code sent to
                    <strong><?= sanitize($_SESSION['forgot']['email'] ?? '') ?></strong>.
                </p>
                <form method="POST" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="step" value="verify">
                    <div class="mb-3">
                        <label for="otp" class="form-label">OTP Code</label>
                        <input type="text" class="form-control form-control-lg text-center"
                               id="otp" name="otp"
                               maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                               placeholder="000000" required autofocus
                               style="letter-spacing: 8px; font-size: 1.5rem;">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle"></i> Verify Code
                        </button>
                        <a href="<?= BASE_URL ?>/forgot_password.php?restart=1" class="btn btn-link btn-sm">
                            Didn't get the code? Start over
                        </a>
                    </div>
                </form>

            <?php elseif ($stage === 'reset'): ?>
                <p class="text-muted">Enter a new password for your account.</p>
                <form method="POST" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="step" value="reset">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password"
                               placeholder="At least 6 characters" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                               placeholder="Re-enter new password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-lg"></i> Reset Password
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
