<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();

$statusInfo = [
    'verified'             => ['label' => 'Verified',             'color' => 'success', 'icon' => 'bi-patch-check-fill'],
    'pending'              => ['label' => 'Pending Verification', 'color' => 'warning', 'icon' => 'bi-hourglass-split'],
    'waiting_for_updates'  => ['label' => 'Waiting for Updates',  'color' => 'info',    'icon' => 'bi-pencil-square'],
    'disabled'             => ['label' => 'Disabled',             'color' => 'secondary','icon' => 'bi-slash-circle'],
];
$s = $statusInfo[$user['status']] ?? ['label' => ucfirst($user['status']), 'color' => 'secondary', 'icon' => 'bi-question-circle'];

$pageTitle = 'Personal Information';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-md-10 col-lg-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="bi bi-person-circle"></i> Personal Information</h3>
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Balance + Status Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6 border-end">
                        <h6 class="text-muted mb-1">Account Balance</h6>
                        <h2 class="text-primary fw-bold mb-0"><?= formatMoney($user['balance']) ?></h2>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <h6 class="text-muted mb-1">Account Status</h6>
                        <span class="badge bg-<?= $s['color'] ?> fs-6 px-3 py-2">
                            <i class="bi <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($user['status'] === 'pending'): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                Your account is pending verification. Most wallet features are restricted until an admin verifies your account.
            </div>
        <?php elseif ($user['status'] === 'waiting_for_updates'): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                An admin has requested updates to your information.
                <a href="<?= BASE_URL ?>/update_id_card.php" class="alert-link">Re-upload your ID card photos here</a>.
            </div>
        <?php elseif ($user['status'] === 'disabled'): ?>
            <div class="alert alert-danger">
                <i class="bi bi-x-circle"></i>
                Your account has been disabled. Please contact support.
            </div>
        <?php endif; ?>

        <!-- Basic Info -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-person-vcard"></i> Basic Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Full Name</label>
                        <div class="fw-semibold"><?= sanitize($user['full_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Date of Birth</label>
                        <div class="fw-semibold"><?= sanitize(date('d/m/Y', strtotime($user['date_of_birth']))) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Phone Number</label>
                        <div class="fw-semibold"><?= sanitize($user['phone_number']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Email</label>
                        <div class="fw-semibold"><?= sanitize($user['email']) ?></div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small mb-1">Address</label>
                        <div class="fw-semibold"><?= sanitize($user['address']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Details -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Account Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Account ID</label>
                        <div class="fw-semibold">#<?= sanitize($user['id']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Role</label>
                        <div class="fw-semibold"><?= sanitize(ucfirst($user['role'])) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Member Since</label>
                        <div class="fw-semibold"><?= sanitize(date('d/m/Y H:i', strtotime($user['created_at']))) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Last Updated</label>
                        <div class="fw-semibold"><?= sanitize(date('d/m/Y H:i', strtotime($user['updated_at']))) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ID Card Section -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-card-image"></i> ID Card</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($user['id_card_front_mime']) || !empty($user['id_card_back_mime'])): ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small mb-1">Front</label>
                            <?php if (!empty($user['id_card_front_mime'])): ?>
                                <a href="<?= BASE_URL ?>/image.php?user_id=<?= (int)$user['id'] ?>&side=front" target="_blank">
                                    <img src="<?= BASE_URL ?>/image.php?user_id=<?= (int)$user['id'] ?>&side=front"
                                         alt="ID Card Front"
                                         class="img-fluid rounded border">
                                </a>
                            <?php else: ?>
                                <div class="text-muted fst-italic">Not uploaded</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small mb-1">Back</label>
                            <?php if (!empty($user['id_card_back_mime'])): ?>
                                <a href="<?= BASE_URL ?>/image.php?user_id=<?= (int)$user['id'] ?>&side=back" target="_blank">
                                    <img src="<?= BASE_URL ?>/image.php?user_id=<?= (int)$user['id'] ?>&side=back"
                                         alt="ID Card Back"
                                         class="img-fluid rounded border">
                                </a>
                            <?php else: ?>
                                <div class="text-muted fst-italic">Not uploaded</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-muted fst-italic">No ID card images uploaded.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/change_password.php" class="btn btn-outline-primary">
                <i class="bi bi-key"></i> Change Password
            </a>
            <a href="<?= BASE_URL ?>/transactions.php" class="btn btn-outline-secondary">
                <i class="bi bi-clock-history"></i> Transaction History
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
