<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

$statusFilter = $_GET['status'] ?? 'pending';
$allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'pending';
}

$stmt = $db->prepare("
    SELECT t.id, t.transaction_code, t.type, t.amount, t.status, t.created_at,
           u.full_name, u.phone_number
    FROM transactions t
    INNER JOIN users u ON u.id = t.user_id
    WHERE t.status = ?
    ORDER BY t.created_at DESC, t.id DESC
");
$stmt->execute([$statusFilter]);
$transactions = $stmt->fetchAll();

$statusLabels = [
    'pending' => ['label' => 'Pending', 'class' => 'is-pending'],
    'approved' => ['label' => 'Approved', 'class' => 'is-approved'],
    'rejected' => ['label' => 'Rejected', 'class' => 'is-rejected'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'is-disabled'],
    'completed' => ['label' => 'Completed', 'class' => 'is-verified'],
];

$pageTitle = 'Pending Transactions';
$pageStyles = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">
    <div class="admin-heading">
        <div>
            <h3><i class="bi bi-clock-history"></i> Transaction Queue</h3>
            <p>Compact transaction review list for statuses that need monitoring.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="admin-toolbar">
        <div class="admin-toolbar-links">
            <?php foreach ($allowedStatuses as $status): ?>
                <a class="admin-tab <?= $statusFilter === $status ? 'is-active' : '' ?>"
                   href="<?= BASE_URL ?>/admin/pending_transactions.php?status=<?= $status ?>">
                    <?= sanitize($statusLabels[$status]['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="admin-panel sn-card">
        <div class="admin-panel-header">
            <h5><?= sanitize($statusLabels[$statusFilter]['label']) ?> Transactions</h5>
        </div>
        <div class="admin-panel-body">
            <?php if (!$transactions): ?>
                <div class="admin-empty">No transactions found for this status.</div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <div class="table-responsive">
                        <table class="table admin-table align-middle">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>User</th>
                                    <th>Phone</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $txn): ?>
                                    <tr>
                                        <td class="mono"><?= sanitize($txn['transaction_code']) ?></td>
                                        <td class="fw-semibold"><?= sanitize($txn['full_name']) ?></td>
                                        <td class="mono"><?= sanitize($txn['phone_number']) ?></td>
                                        <td><?= sanitize(ucwords(str_replace('_', ' ', $txn['type']))) ?></td>
                                        <td class="mono"><?= formatMoney($txn['amount']) ?></td>
                                        <td>
                                            <span class="admin-chip sn-chip <?= $statusLabels[$txn['status']]['class'] ?> <?= in_array($txn['status'], ['approved', 'completed'], true) ? 'sn-chip--verified' : ($txn['status'] === 'pending' ? 'sn-chip--pending' : 'sn-chip--warn') ?>">
                                                <?= sanitize($statusLabels[$txn['status']]['label']) ?>
                                            </span>
                                        </td>
                                        <td class="mono muted"><?= sanitize(date('d/m/Y H:i', strtotime($txn['created_at']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
