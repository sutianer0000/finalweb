<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cache.php';
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
    requireCsrfToken();

    $action = $_POST['action'] ?? '';

    $stmt = $db->prepare("SELECT id, email, role, status, permanently_locked FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target || $target['role'] !== 'user') {
        setFlash('error', 'Account not found.');
        redirect(BASE_URL . '/admin/accounts.php');
    }

    if ($action === 'verify') {
        $db->prepare("UPDATE users SET status = 'verified' WHERE id = ?")->execute([$userId]);
        revokeRememberTokensForUser($userId);
        logActivity('admin_verified_user', [
            'target_user_id' => $userId,
            'target_email' => $target['email'],
            'entity_type' => 'user',
            'entity_id' => $userId,
            'details' => ['old_status' => $target['status'], 'new_status' => 'verified'],
        ]);
        setFlash('success', 'Account has been verified.');
    } elseif ($action === 'cancel') {
        $db->prepare("UPDATE users SET status = 'disabled' WHERE id = ?")->execute([$userId]);
        revokeRememberTokensForUser($userId);
        logActivity('admin_disabled_user', [
            'target_user_id' => $userId,
            'target_email' => $target['email'],
            'entity_type' => 'user',
            'entity_id' => $userId,
            'details' => ['old_status' => $target['status'], 'new_status' => 'disabled'],
        ]);
        setFlash('success', 'Account has been disabled.');
    } elseif ($action === 'request_update') {
        $db->prepare("UPDATE users SET status = 'waiting_for_updates' WHERE id = ?")->execute([$userId]);
        revokeRememberTokensForUser($userId);
        logActivity('admin_requested_user_update', [
            'target_user_id' => $userId,
            'target_email' => $target['email'],
            'entity_type' => 'user',
            'entity_id' => $userId,
            'details' => ['old_status' => $target['status'], 'new_status' => 'waiting_for_updates'],
        ]);
        setFlash('success', 'Additional information requested. User can now re-upload their ID card.');
    } elseif ($action === 'unlock') {
        if ((int) $target['permanently_locked'] !== 1) {
            setFlash('warning', 'This account is not currently permanently locked.');
        } else {
            unlockUserSecurityLock($userId);
            revokeRememberTokensForUser($userId);
            logActivity('admin_unlocked_user', [
                'target_user_id' => $userId,
                'target_email' => $target['email'],
                'entity_type' => 'user',
                'entity_id' => $userId,
                'details' => ['source' => 'account_detail'],
            ]);
            setFlash('success', 'Account has been unlocked and abnormal login counters were reset.');
        }
    }

    // Any status change above invalidates the dashboard count cache so the
    // next admin page load reflects the new totals immediately.
    if (in_array($action, ['verify', 'cancel', 'request_update', 'unlock'], true)) {
        forgetCached('admin_dashboard_counts');
    }

    if (!in_array($action, ['verify', 'cancel', 'request_update', 'unlock'], true)) {
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

$transactionCountStmt = $db->prepare("
    SELECT COUNT(*)
    FROM transactions
    WHERE user_id = ?
");
$transactionCountStmt->execute([$userId]);
$totalTransactionCount = (int) $transactionCountStmt->fetchColumn();

$currentMonthTransactions = [];
if ($totalTransactionCount > 0) {
    $transactionStmt = $db->prepare("
        SELECT t.id, t.transaction_code, t.type, t.amount, t.fee, t.total_amount,
               t.status, t.note, t.created_at,
               related.full_name AS related_name
        FROM transactions t
        LEFT JOIN users related ON related.id = t.related_user_id
        WHERE t.user_id = ?
          AND t.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
          AND t.created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
        ORDER BY t.created_at DESC, t.id DESC
    ");
    $transactionStmt->execute([$userId]);
    $currentMonthTransactions = $transactionStmt->fetchAll();
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
        <a href="<?= BASE_URL ?>/admin/accounts.php?status=<?= sanitize($account['permanently_locked'] ? 'permanently_locked' : $account['status']) ?>" class="btn btn-outline-secondary sn-btn-ghost btn-sm">
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
                        <dd>
                            <div class="admin-inline-actions">
                                <span><?= $account['permanently_locked'] ? 'Yes' : 'No' ?></span>
                                <?php if ($account['permanently_locked']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Unlock this account and reset abnormal login counters?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="unlock">
                                        <button type="submit" class="btn btn-sm admin-link-btn is-unlock-btn">
                                            Unlock
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </dd>

                        <dt>Locked At</dt>
                        <dd><?= $account['permanently_locked_at'] ? sanitize(date('d/m/Y H:i', strtotime($account['permanently_locked_at']))) : 'Not locked' ?></dd>
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
                        <?php if ($account['status'] === 'pending'): ?>
                            <form method="POST" onsubmit="return confirm('Verify this account?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="verify">
                                <button type="submit" class="btn admin-action-btn is-verify">
                                    <i class="bi bi-patch-check"></i> Verify
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Request the user to re-upload their ID card?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="request_update">
                                <button type="submit" class="btn admin-action-btn is-request">
                                    <i class="bi bi-pencil-square"></i> Request Update
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Disable this account? The user will no longer be able to log in.');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="btn admin-action-btn is-disable">
                                    <i class="bi bi-x-circle"></i> Disable
                                </button>
                            </form>
                        <?php elseif (empty($account['permanently_locked']) && empty($account['status'] === 'disabled')): ?>
                            <div class="admin-empty">No activation actions available for this account status.</div>
                        <?php endif; ?>

                        <?php if ($account['permanently_locked']): ?>
                            <form method="POST" onsubmit="return confirm('Unlock this account and reset abnormal login counters?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="unlock">
                                <button type="submit" class="btn admin-action-btn is-unlock">
                                    <i class="bi bi-unlock"></i> Unlock Account
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($totalTransactionCount > 0): ?>
        <div class="admin-panel sn-card mt-3">
            <div class="admin-panel-header">
                <h5>Current Month Transaction History</h5>
                <span class="small text-muted"><?= date('F Y') ?></span>
            </div>
            <div class="admin-panel-body">
                <?php if (empty($currentMonthTransactions)): ?>
                    <div class="admin-empty">
                        This account has transaction history, but there are no transactions in the current month.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table admin-table align-middle">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Related</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentMonthTransactions as $txn): ?>
                                    <?php
                                        $txnStatusClass = match ($txn['status']) {
                                            'completed', 'approved' => 'is-verified',
                                            'pending' => 'is-pending',
                                            'cancelled', 'rejected' => 'is-disabled',
                                            default => 'is-info',
                                        };
                                        $txnTypeLabel = ucwords(str_replace('_', ' ', $txn['type']));
                                    ?>
                                    <tr>
                                        <td class="mono"><?= sanitize(date('d/m/Y H:i', strtotime($txn['created_at']))) ?></td>
                                        <td class="mono"><?= sanitize($txn['transaction_code']) ?></td>
                                        <td><?= sanitize($txnTypeLabel) ?></td>
                                        <td class="mono"><?= formatMoney($txn['total_amount']) ?></td>
                                        <td>
                                            <span class="admin-chip <?= $txnStatusClass ?>">
                                                <?= sanitize(ucwords($txn['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= $txn['related_name'] ? sanitize($txn['related_name']) : '-' ?></td>
                                        <td><?= $txn['note'] !== null && $txn['note'] !== '' ? sanitize(mb_strimwidth($txn['note'], 0, 60, '...')) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
