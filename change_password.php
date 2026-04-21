<?php
require_once __DIR__ . '/includes/auth.php';
requirePasswordChanged();

$user = getCurrentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword     = $_POST['old_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($oldPassword)) {
        $errors[] = 'Please enter your current password.';
    } elseif (!password_verify($oldPassword, $user['password'])) {
        $errors[] = 'Current password is incorrect.';
    }

    if (empty($newPassword)) {
        $errors[] = 'New password is required.';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors) && $oldPassword === $newPassword) {
        $errors[] = 'New password must be different from the current password.';
    }

    if (empty($errors)) {
        $db = getDB();
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")
           ->execute([$hashed, $user['id']]);

        // Keep user logged in — session already holds user_id, nothing to reset.
        setFlash('success', 'Password changed successfully.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

$pageTitle = 'Change Password';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-md-7 col-lg-6 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-key"></i> Change Password</h4>
            </div>
            <div class="card-body p-4">

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
                        <label for="old_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="old_password" name="old_password"
                               placeholder="Enter your current password" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password"
                               placeholder="At least 6 characters" required>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                               placeholder="Re-enter new password" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-lg"></i> Update Password
                        </button>
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
