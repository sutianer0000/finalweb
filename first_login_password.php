<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$user = getCurrentUser();

// If not first login, redirect to dashboard
if ($user['first_login'] != 1) {
    redirect(BASE_URL . '/dashboard.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword)) {
        $errors[] = 'New password is required.';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $db = getDB();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ?, first_login = 0 WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);
        logActivity('user_completed_first_login_password', [
            'target_user_id' => $user['id'],
            'target_email' => $user['email'],
            'entity_type' => 'user',
            'entity_id' => $user['id'],
        ]);

        setFlash('success', 'Password changed successfully! You can now use the system.');

        // Redirect based on role
        if (in_array($user['role'], ['admin', 'superadmin'], true)) {
            redirect(BASE_URL . '/admin/dashboard.php');
        } else {
            redirect(BASE_URL . '/dashboard.php');
        }
    }
}

$pageTitle = 'Change Password - First Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="login-card">
    <div class="card">
        <div class="card-header bg-warning text-dark text-center">
            <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Change Your Password</h4>
        </div>
        <div class="card-body p-4">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                This is your first login. You must change your password before you can use the system.
                If you don't want to change your password now, you can <a href="<?= BASE_URL ?>/logout.php">log out</a>.
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= sanitize($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               placeholder="Enter new password (min 6 characters)" required autofocus>
                        <button class="btn btn-outline-secondary" 
                                type="button" 
                                id="toggleNewPassword"
                                title="Show/Hide password">
                            <i class="bi bi-eye" id="toggleNewPasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="Enter new password again" required>
                        <button class="btn btn-outline-secondary" 
                                type="button" 
                                id="toggleConfirmPassword"
                                title="Show/Hide password">
                            <i class="bi bi-eye" id="toggleConfirmPasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-warning btn-lg">
                        <i class="bi bi-check-lg"></i> Change Password
                    </button>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-secondary">
                        <i class="bi bi-box-arrow-right"></i> Log Out Instead
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function togglePasswordField(buttonId, inputId, iconId) {
        const btn   = document.getElementById(buttonId);
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
 
        btn.addEventListener('click', function () {
            const isHidden = input.type === 'password';
            input.type   = isHidden ? 'text' : 'password';
            icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }
 
    togglePasswordField('toggleNewPassword',     'new_password',     'toggleNewPasswordIcon');
    togglePasswordField('toggleConfirmPassword', 'confirm_password', 'toggleConfirmPasswordIcon');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
