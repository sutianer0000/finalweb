<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    setFlash('error', 'Invalid account ID.');
    redirect(BASE_URL . '/admin/accounts.php');
}

// Handle action POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Load current account
    $stmt = $db->prepare("SELECT id, role, status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target || $target['role'] !== 'user') {
        setFlash('error', 'Account not found.');
        redirect(BASE_URL . '/admin/accounts.php');
    }

    if ($action === 'verify') {
        $db->prepare("UPDATE users SET status = 'verified' WHERE id = ?")->execute([$userId]);
        setFlash('success', 'Account has been verified.');
    } elseif ($action === 'cancel') {
        $db->prepare("UPDATE users SET status = 'disabled' WHERE id = ?")->execute([$userId]);
        setFlash('success', 'Account has been disabled.');
    } elseif ($action === 'request_update') {
        $db->prepare("UPDATE users SET status = 'waiting_for_updates' WHERE id = ?")->execute([$userId]);
        setFlash('success', 'Additional information requested. User can now re-upload their ID card.');
    } else {
        setFlash('error', 'Unknown action.');
    }

    redirect(BASE_URL . '/admin/account_detail.php?id=' . $userId);
}

// Load account info for display — exclude BLOB data columns (only mime needed
// to know if an image exists; the bytes themselves are streamed via image.php).
$stmt = $db->prepare("
    SELECT id, phone_number, email, full_name, date_of_birth, address,
           balance, role, status, first_login,
           id_card_front_mime, id_card_back_mime,
           failed_login_attempts, has_abnormal_login, locked_until,
           permanently_locked, permanently_locked_at,
           created_at, updated_at
    FROM users WHERE id = ? AND role = 'user'
");
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account) {
    setFlash('error', 'Account not found.');
    redirect(BASE_URL . '/admin/accounts.php');
}

$statusLabels = [
    'pending'             => ['label' => 'Pending Verification', 'color' => 'warning'],
    'verified'            => ['label' => 'Verified',             'color' => 'success'],
    'waiting_for_updates' => ['label' => 'Waiting for Updates',  'color' => 'info'],
    'disabled'            => ['label' => 'Disabled',             'color' => 'secondary'],
];
$s = $statusLabels[$account['status']] ?? ['label' => ucfirst($account['status']), 'color' => 'secondary'];

$pageTitle = 'Account #' . $account['id'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi bi-person-vcard"></i> Account Details</h3>
    <a href="<?= BASE_URL ?>/admin/accounts.php?status=<?= sanitize($account['status']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-lg-7">
        <!-- Status + Balance -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-6 border-end">
                        <h6 class="text-muted mb-1">Status</h6>
                        <span class="badge bg-<?= $s['color'] ?> fs-6"><?= $s['label'] ?></span>
                    </div>
                    <div class="col-6">
                        <h6 class="text-muted mb-1">Balance</h6>
                        <h4 class="text-primary fw-bold mb-0"><?= formatMoney($account['balance']) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration details -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Registration Information</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted fw-normal">Account ID</dt>
                    <dd class="col-sm-8">#<?= (int)$account['id'] ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Full Name</dt>
                    <dd class="col-sm-8"><?= sanitize($account['full_name']) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Date of Birth</dt>
                    <dd class="col-sm-8"><?= sanitize(date('d/m/Y', strtotime($account['date_of_birth']))) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Phone Number</dt>
                    <dd class="col-sm-8"><?= sanitize($account['phone_number']) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Email</dt>
                    <dd class="col-sm-8"><?= sanitize($account['email']) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Address</dt>
                    <dd class="col-sm-8"><?= sanitize($account['address']) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Registered</dt>
                    <dd class="col-sm-8"><?= sanitize(date('d/m/Y H:i', strtotime($account['created_at']))) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- ID Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-card-image"></i> ID Card</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small mb-1">Front</label>
                    <?php if (!empty($account['id_card_front_mime'])): ?>
                        <a href="<?= BASE_URL ?>/image.php?user_id=<?= (int)$account['id'] ?>&side=front" target="_blank">
                            <img src="<?= BASE_URL ?>/image.php?user_id=<?= (int)$account['id'] ?>&side=front"
                                 alt="ID Front" class="img-fluid rounded border">
                        </a>
                    <?php else: ?>
                        <div class="text-muted fst-italic">Not uploaded</div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="text-muted small mb-1">Back</label>
                    <?php if (!empty($account['id_card_back_mime'])): ?>
                        <a href="<?= BASE_URL ?>/image.php?user_id=<?= (int)$account['id'] ?>&side=back" target="_blank">
                            <img src="<?= BASE_URL ?>/image.php?user_id=<?= (int)$account['id'] ?>&side=back"
                                 alt="ID Back" class="img-fluid rounded border">
                        </a>
                    <?php else: ?>
                        <div class="text-muted fst-italic">Not uploaded</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Admin actions -->
        <?php if (in_array($account['status'], ['pending', 'waiting_for_updates'], true)): ?>
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-shield-check"></i> Admin Actions</h5>
            </div>
            <div class="card-body d-grid gap-2">
                <form method="POST" onsubmit="return confirm('Verify this account?');">
                    <input type="hidden" name="action" value="verify">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-patch-check"></i> Verify
                    </button>
                </form>

                <form method="POST" onsubmit="return confirm('Disable this account? The user will no longer be able to log in.');">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-x-circle"></i> Cancel (Disable Account)
                    </button>
                </form>

                <form method="POST" onsubmit="return confirm('Request the user to re-upload their ID card?');">
                    <input type="hidden" name="action" value="request_update">
                    <button type="submit" class="btn btn-info w-100 text-white">
                        <i class="bi bi-pencil-square"></i> Request Additional Information
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($account['status'] === 'verified'): ?>
        <div class="card shadow-sm">
            <div class="card-body d-grid">
                <form method="POST" onsubmit="return confirm('Disable this account?');">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Disable Account
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($account['status'] === 'disabled'): ?>
        <div class="alert alert-secondary mb-0">
            This account is disabled.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
