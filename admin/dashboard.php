<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/admin_dashboard.php';
requireAdmin();

// Dashboard counts barely change between renders, so we keep the initial page
// render cheap and let the browser refresh the numbers in the background.
$counts = rememberCached('admin_dashboard_counts', 30, function () {
    return getAdminDashboardCounts(getDB());
});

$pendingCount = $counts['pending'];
$verifiedCount = $counts['verified'];
$waitingCount = $counts['waiting'];
$disabledCount = $counts['disabled'];
$lockedCount = $counts['locked'];
$pendingTxnCount = $counts['pending_txn'];
$alertSummary = getAdminDashboardAlertSummary($counts);

$pageTitle = 'Admin Dashboard';
$pageStyles = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';

$stats = [
    [
        'label' => 'Pending Verification',
        'value' => $pendingCount,
        'count_key' => 'pending',
        'icon' => 'bi-hourglass-split',
        'chip' => 'is-pending',
        'url' => BASE_URL . '/admin/accounts.php?status=pending',
    ],
    [
        'label' => 'Verified',
        'value' => $verifiedCount,
        'count_key' => 'verified',
        'icon' => 'bi-patch-check-fill',
        'chip' => 'is-verified',
        'url' => BASE_URL . '/admin/accounts.php?status=verified',
    ],
    [
        'label' => 'Waiting for Updates',
        'value' => $waitingCount,
        'count_key' => 'waiting',
        'icon' => 'bi-pencil-square',
        'chip' => 'is-waiting',
        'url' => BASE_URL . '/admin/accounts.php?status=waiting_for_updates',
    ],
    [
        'label' => 'Disabled',
        'value' => $disabledCount,
        'count_key' => 'disabled',
        'icon' => 'bi-slash-circle',
        'chip' => 'is-disabled',
        'url' => BASE_URL . '/admin/accounts.php?status=disabled',
    ],
    [
        'label' => 'Locked Accounts',
        'value' => $lockedCount,
        'count_key' => 'locked',
        'icon' => 'bi-lock-fill',
        'chip' => 'is-disabled',
        'url' => BASE_URL . '/admin/accounts.php?status=permanently_locked',
    ],
];
?>

<div class="admin-shell">
    <div class="admin-heading">
        <div>
            <h3><i class="bi bi-speedometer2"></i> Admin Dashboard</h3>
            <p>Dense operational overview for account review and transaction queue management.</p>
        </div>
        <a href="#admin-review-queue"
           class="admin-dashboard-alert<?= $alertSummary['total'] > 0 ? ' has-alert' : '' ?>"
           data-admin-alert-link
           aria-label="Admin review alerts">
            <span class="admin-dashboard-alert__icon">
                <i class="bi bi-bell-fill"></i>
            </span>
            <span class="admin-dashboard-alert__body">
                <strong data-admin-alert-total><?= (int) $alertSummary['total'] ?></strong>
                <span>
                    <span data-admin-alert-accounts><?= (int) $alertSummary['accounts'] ?></span> account alerts
                    <span class="mx-1">/</span>
                    <span data-admin-alert-transactions><?= (int) $alertSummary['transactions'] ?></span> pending transactions
                </span>
            </span>
        </a>
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
                <div class="admin-stat-value" data-admin-count="<?= sanitize($stat['count_key']) ?>"><?= (int) $stat['value'] ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="admin-panel sn-card" id="admin-review-queue">
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
                                    <td class="mono" data-admin-count="pending"><?= (int) $pendingCount ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/admin/accounts.php?status=pending" class="btn btn-sm btn-outline-secondary sn-btn-ghost admin-link-btn">
                                            Review
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Requested Updates</td>
                                    <td class="muted">Users asked to re-submit identity data</td>
                                    <td class="mono" data-admin-count="waiting"><?= (int) $waitingCount ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/admin/accounts.php?status=waiting_for_updates" class="btn btn-sm btn-outline-secondary sn-btn-ghost admin-link-btn">
                                            Inspect
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Permanently Locked Accounts</td>
                                    <td class="muted">Users locked after repeated abnormal login failures</td>
                                    <td class="mono" data-admin-count="locked"><?= (int) $lockedCount ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/admin/accounts.php?status=permanently_locked" class="btn btn-sm btn-outline-secondary sn-btn-ghost admin-link-btn">
                                            Unlock Queue
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Pending Transactions</td>
                                    <td class="muted">Transactions still awaiting admin review</td>
                                    <td class="mono" data-admin-count="pending_txn"><?= (int) $pendingTxnCount ?></td>
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
                        <a href="<?= BASE_URL ?>/admin/accounts.php?status=permanently_locked" class="btn btn-outline-secondary sn-btn-ghost admin-link-btn">Locked Accounts</a>
                        <a href="<?= BASE_URL ?>/admin/pending_transactions.php" class="btn btn-outline-secondary sn-btn-ghost admin-link-btn">Pending Transactions</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var endpoint = '<?= BASE_URL ?>/admin/dashboard_counts_data.php';
    var alertLink = document.querySelector('[data-admin-alert-link]');
    if (!alertLink) return;

    var totalEl = alertLink.querySelector('[data-admin-alert-total]');
    var accountEl = alertLink.querySelector('[data-admin-alert-accounts]');
    var txnEl = alertLink.querySelector('[data-admin-alert-transactions]');

    function setText(selector, value) {
        document.querySelectorAll(selector).forEach(function (el) {
            el.textContent = String(value);
        });
    }

    function apply(data) {
        if (!data || !data.ok || !data.counts || !data.alerts) {
            return;
        }

        setText('[data-admin-count="pending"]', data.counts.pending || 0);
        setText('[data-admin-count="verified"]', data.counts.verified || 0);
        setText('[data-admin-count="waiting"]', data.counts.waiting || 0);
        setText('[data-admin-count="disabled"]', data.counts.disabled || 0);
        setText('[data-admin-count="locked"]', data.counts.locked || 0);
        setText('[data-admin-count="pending_txn"]', data.counts.pending_txn || 0);

        totalEl.textContent = String(data.alerts.total || 0);
        accountEl.textContent = String(data.alerts.accounts || 0);
        txnEl.textContent = String(data.alerts.transactions || 0);

        if ((data.alerts.total || 0) > 0) {
            alertLink.classList.add('has-alert');
        } else {
            alertLink.classList.remove('has-alert');
        }
    }

    function refresh() {
        fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(apply)
            .catch(function () { /* keep current numbers if polling fails */ });
    }

    refresh();
    window.setInterval(refresh, 15000);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
