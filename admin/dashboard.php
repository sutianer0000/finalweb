<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

$pendingCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending' AND role = 'user'")->fetchColumn();
$verifiedCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'verified' AND role = 'user'")->fetchColumn();
$waitingCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'waiting_for_updates' AND role = 'user'")->fetchColumn();
$disabledCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'disabled' AND role = 'user'")->fetchColumn();
$pendingTxnCount = $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn();

$pageTitle = 'Admin Dashboard';
$pageStyles = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';

$stats = [
    [
        'label' => 'Pending Verification',
        'value' => $pendingCount,
        'icon' => 'bi-hourglass-split',
        'chip' => 'is-pending',
        'url' => BASE_URL . '/admin/accounts.php?status=pending',
    ],
    [
        'label' => 'Verified',
        'value' => $verifiedCount,
        'icon' => 'bi-patch-check-fill',
        'chip' => 'is-verified',
        'url' => BASE_URL . '/admin/accounts.php?status=verified',
    ],
    [
        'label' => 'Waiting for Updates',
        'value' => $waitingCount,
        'icon' => 'bi-pencil-square',
        'chip' => 'is-waiting',
        'url' => BASE_URL . '/admin/accounts.php?status=waiting_for_updates',
    ],
    [
        'label' => 'Disabled',
        'value' => $disabledCount,
        'icon' => 'bi-slash-circle',
        'chip' => 'is-disabled',
        'url' => BASE_URL . '/admin/accounts.php?status=disabled',
    ],
];
?>

<div class="admin-shell">
    <div class="admin-heading">
        <div>
            <h3><i class="bi bi-speedometer2"></i> Admin Dashboard</h3>
            <p>Dense operational overview for account review and transaction queue management.</p>
        </div>
    </div>

    <div class="admin-stat-grid">
        <?php foreach ($stats as $stat): ?>
            <a href="<?= $stat['url'] ?>" class="admin-stat-card text-decoration-none">
                <div class="admin-stat-top">
                    <span class="admin-stat-label"><?= sanitize($stat['label']) ?></span>
                    <span class="admin-chip <?= $stat['chip'] ?>">
                        <i class="bi <?= $stat['icon'] ?>"></i>
                    </span>
                </div>
                <div class="admin-stat-value"><?= (int)$stat['value'] ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="admin-panel sn-card">
                <div class="admin-panel-header">
                    <h5>Review Queue</h5>
                </div>
                <div class="admin-panel-body">
                    <div class="admin-table-wrap">
                        <table class="table admin-table align-middle">
                            <thead>
                                <tr>
                                    <th>Queue</th>
                                    <th>Scope</th>
                                    <th>Count</th>
                                    <th class="text-end">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-semibold">Account Verification</td>
                                    <td class="muted">Users waiting for manual approval</td>
                                    <td class="mono"><?= (int)$pendingCount ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/admin/accounts.php?status=pending" class="btn btn-sm btn-outline-secondary sn-btn-ghost admin-link-btn">
                                            Review
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Requested Updates</td>
                                    <td class="muted">Users asked to re-submit identity data</td>
                                    <td class="mono"><?= (int)$waitingCount ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/admin/accounts.php?status=waiting_for_updates" class="btn btn-sm btn-outline-secondary sn-btn-ghost admin-link-btn">
                                            Inspect
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Pending Transactions</td>
                                    <td class="muted">Transactions still awaiting admin review</td>
                                    <td class="mono"><?= (int)$pendingTxnCount ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/admin/pending_transactions.php" class="btn btn-sm btn-outline-secondary sn-btn-ghost admin-link-btn">
                                            Open Queue
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-panel sn-card">
                <div class="admin-panel-header">
                    <h5>Quick Access</h5>
                </div>
                <div class="admin-panel-body">
                    <div class="admin-actions">
                        <a href="<?= BASE_URL ?>/admin/accounts.php" class="btn btn-outline-secondary sn-btn-ghost admin-link-btn">All Accounts</a>
                        <a href="<?= BASE_URL ?>/admin/accounts.php?status=verified" class="btn btn-outline-secondary sn-btn-ghost admin-link-btn">Verified Accounts</a>
                        <a href="<?= BASE_URL ?>/admin/pending_transactions.php" class="btn btn-outline-secondary sn-btn-ghost admin-link-btn">Pending Transactions</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
