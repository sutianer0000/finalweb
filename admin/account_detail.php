<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

function formatAuditBytes($bytes) {
    if ($bytes === null) {
        return 'Unknown';
    }
    $bytes = (int) $bytes;
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return number_format($bytes / (1024 * 1024), 2) . ' MB';
}

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    setFlash('error', 'Invalid account ID.');
    redirect(BASE_URL . '/admin/accounts.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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

$stmt = $db->prepare("
    SELECT users.id, users.phone_number, users.email, users.full_name, users.date_of_birth, users.address,
           users.balance, users.role, users.status, users.first_login,
           users.id_card_front_mime, users.id_card_back_mime,
           c.front_width, c.front_height, c.front_size_bytes,
           c.back_width, c.back_height, c.back_size_bytes,
           c.front_orig_width, c.front_orig_height,
           c.back_orig_width, c.back_orig_height,
           c.updated_at AS id_card_updated_at,
           users.failed_login_attempts, users.has_abnormal_login, users.locked_until,
           users.permanently_locked, users.permanently_locked_at,
           users.created_at, users.updated_at
    FROM users
    LEFT JOIN user_id_cards c ON c.user_id = users.id
    WHERE users.id = ? AND users.role = 'user'
");
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account) {
    setFlash('error', 'Account not found.');
    redirect(BASE_URL . '/admin/accounts.php');
}

$statusLabels = [
    'pending' => ['label' => 'Pending Verification', 'class' => 'is-pending'],
    'verified' => ['label' => 'Verified', 'class' => 'is-verified'],
    'waiting_for_updates' => ['label' => 'Waiting for Updates', 'class' => 'is-waiting'],
    'disabled' => ['label' => 'Disabled', 'class' => 'is-disabled'],
];
$s = $statusLabels[$account['status']] ?? ['label' => ucfirst($account['status']), 'class' => 'is-info'];

$pageTitle = 'Account #' . $account['id'];
$pageStyles = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">
    <div class="admin-heading">
        <div>
            <h3><i class="bi bi-person-vcard"></i> Account Detail</h3>
            <p>Manual review view for identity data, account state, and admin decisions.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/accounts.php?status=<?= sanitize($account['status']) ?>" class="btn btn-outline-secondary sn-btn-ghost btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Queue
        </a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="admin-panel sn-card">
                <div class="admin-panel-body">
                    <div class="admin-balance-strip">
                        <div class="admin-balance-cell">
                            <h6>Status</h6>
                            <span class="admin-chip sn-chip <?= $s['class'] ?> <?= $account['status'] === 'verified' ? 'sn-chip--verified' : ($account['status'] === 'pending' ? 'sn-chip--pending' : 'sn-chip--warn') ?>"><?= sanitize($s['label']) ?></span>
                        </div>
                        <div class="admin-balance-cell">
                            <h6>Balance</h6>
                            <strong><?= formatMoney($account['balance']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-panel sn-card">
                <div class="admin-panel-header">
                    <h5>Registration Information</h5>
                </div>
                <div class="admin-panel-body">
                    <dl class="admin-kv">
                        <dt>Account ID</dt>
                        <dd class="mono">#<?= (int)$account['id'] ?></dd>

                        <dt>Full Name</dt>
                        <dd><?= sanitize($account['full_name']) ?></dd>

                        <dt>Date of Birth</dt>
                        <dd><?= sanitize(date('d/m/Y', strtotime($account['date_of_birth']))) ?></dd>

                        <dt>Phone Number</dt>
                        <dd class="mono"><?= sanitize($account['phone_number']) ?></dd>

                        <dt>Email</dt>
                        <dd><?= sanitize($account['email']) ?></dd>

                        <dt>Address</dt>
                        <dd><?= sanitize($account['address']) ?></dd>

                        <dt>Registered</dt>
                        <dd class="mono"><?= sanitize(date('d/m/Y H:i', strtotime($account['created_at']))) ?></dd>

                        <dt>Last Updated</dt>
                        <dd class="mono"><?= sanitize(date('d/m/Y H:i', strtotime($account['updated_at']))) ?></dd>

                        <dt>Failed Login Attempts</dt>
                        <dd class="mono"><?= (int)$account['failed_login_attempts'] ?></dd>

                        <dt>Abnormal Login Flag</dt>
                        <dd><?= $account['has_abnormal_login'] ? 'Yes' : 'No' ?></dd>

                        <dt>Temporary Lock Until</dt>
                        <dd><?= $account['locked_until'] ? sanitize(date('d/m/Y H:i', strtotime($account['locked_until']))) : 'Not locked' ?></dd>

                        <dt>Permanent Lock</dt>
                        <dd><?= $account['permanently_locked'] ? 'Yes' : 'No' ?></dd>
                    </dl>
                </div>
                <?php if (!empty($account['id_card_updated_at'])): ?>
                    <div class="small text-muted mt-3">
                        Last updated: <?= sanitize(date('d/m/Y H:i', strtotime($account['id_card_updated_at']))) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="admin-panel sn-card">
                <div class="admin-panel-header">
                    <h5>ID Scanner Window</h5>
                </div>
                <div class="admin-panel-body">
                    <div class="scanner-panel">
                        <div class="scanner-grid">
                            <div class="scanner-card">
                                <label>Front</label>
                                <?php if (!empty($account['id_card_front_mime'])): ?>
                                    <div class="sn-hologram">
                                        <a href="<?= BASE_URL ?>/image.php?user_id=<?= (int)$account['id'] ?>&side=front" target="_blank">
                                            <img src="<?= BASE_URL ?>/image.php?user_id=<?= (int)$account['id'] ?>&side=front"
                                                 alt="ID Front">
                                        </a>
                                    </div>
                                    <div class="small text-muted mt-2">
                                        <div>MIME: <?= sanitize($account['id_card_front_mime']) ?></div>
                                        <div>Original upload: <?= $account['front_orig_width'] && $account['front_orig_height'] ? (int)$account['front_orig_width'] . ' x ' . (int)$account['front_orig_height'] : 'Unknown' ?></div>
                                        <div>Stored size: <?= sanitize(formatAuditBytes($account['front_size_bytes'])) ?></div>
                                        <div>Stored resolution: <?= $account['front_width'] && $account['front_height'] ? (int)$account['front_width'] . ' x ' . (int)$account['front_height'] : 'Unknown' ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="admin-empty">Not uploaded</div>
                                <?php endif; ?>
                            </div>

                            <div class="scanner-card">
                                <label>Back</label>
                                <?php if (!empty($account['id_card_back_mime'])): ?>
                                    <div class="sn-hologram">
                                        <a href="<?= BASE_URL ?>/image.php?user_id=<?= (int)$account['id'] ?>&side=back" target="_blank">
                                            <img src="<?= BASE_URL ?>/image.php?user_id=<?= (int)$account['id'] ?>&side=back"
                                                 alt="ID Back">
                                        </a>
                                    </div>
                                    <div class="small text-muted mt-2">
                                        <div>MIME: <?= sanitize($account['id_card_back_mime']) ?></div>
                                        <div>Original upload: <?= $account['back_orig_width'] && $account['back_orig_height'] ? (int)$account['back_orig_width'] . ' x ' . (int)$account['back_orig_height'] : 'Unknown' ?></div>
                                        <div>Stored size: <?= sanitize(formatAuditBytes($account['back_size_bytes'])) ?></div>
                                        <div>Stored resolution: <?= $account['back_width'] && $account['back_height'] ? (int)$account['back_width'] . ' x ' . (int)$account['back_height'] : 'Unknown' ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="admin-empty">Not uploaded</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-panel sn-card">
                <div class="admin-panel-header">
                    <h5>Admin Actions</h5>
                </div>
                <div class="admin-panel-body">
                    <div class="admin-actions">
                        <form method="POST" onsubmit="return confirm('Verify this account?');">
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="btn admin-action-btn is-verify">
                                <i class="bi bi-patch-check"></i> Verify
                            </button>
                        </form>

                        <form method="POST" onsubmit="return confirm('Request the user to re-upload their ID card?');">
                            <input type="hidden" name="action" value="request_update">
                            <button type="submit" class="btn admin-action-btn is-request">
                                <i class="bi bi-pencil-square"></i> Request Update
                            </button>
                        </form>

                        <form method="POST" onsubmit="return confirm('Disable this account? The user will no longer be able to log in.');">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn admin-action-btn is-disable">
                                <i class="bi bi-x-circle"></i> Disable
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
