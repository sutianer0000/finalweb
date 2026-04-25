<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

$stmt = $db->prepare("
    SELECT t.id, t.transaction_code, t.type, t.amount, t.fee, t.total_amount,
           t.status, t.created_at, related.full_name AS related_name
    FROM transactions t
    LEFT JOIN users related ON related.id = t.related_user_id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC, t.id DESC
");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll();

function transactionTypeLabel(string $type): string
{
    $labels = [
        'deposit' => 'Deposit',
        'withdraw' => 'Withdraw',
        'transfer_out' => 'Transfer Out',
        'transfer_in' => 'Transfer In',
        'phone_card' => 'Phone Card',
    ];

    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function transactionIcon(string $type): string
{
    return match ($type) {
        'deposit' => 'bi-plus-circle',
        'withdraw' => 'bi-dash-circle',
        'transfer_out' => 'bi-arrow-up-right-circle',
        'transfer_in' => 'bi-arrow-down-left-circle',
        'phone_card' => 'bi-phone',
        default => 'bi-receipt',
    };
}

function transactionAmountClass(string $type): string
{
    return in_array($type, ['deposit', 'transfer_in'], true) ? 'is-positive' : 'is-negative';
}

function transactionSignedAmount(array $transaction): string
{
    $sign = in_array($transaction['type'], ['deposit', 'transfer_in'], true) ? '+' : '-';
    $value = in_array($transaction['type'], ['transfer_out', 'withdraw', 'phone_card'], true)
        ? $transaction['total_amount']
        : $transaction['amount'];

    return $sign . formatMoney($value);
}

function transactionStatusClass(string $status): string
{
    return match ($status) {
        'completed', 'approved' => 'is-verified',
        'pending' => 'is-pending',
        'cancelled', 'rejected' => 'is-disabled',
        default => 'is-info',
    };
}

$pageTitle = 'Transaction History';
$pageStyles = ['dashboard.css', 'admin.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-hud">
    <div class="dashboard-shell">
        <div class="dashboard-greeting">
            <div class="eyebrow">Ledger</div>
            <h1>Transaction History</h1>
            <p>All wallet movements are listed newest first.</p>
        </div>

        <div class="admin-panel sn-card">
            <div class="admin-panel-header">
                <h5>Transactions</h5>
            </div>
            <div class="admin-panel-body">
                <?php if (!$transactions): ?>
                    <div class="admin-empty">No transactions yet.</div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <div class="table-responsive">
                            <table class="table admin-table align-middle">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Code</th>
                                        <th>Related</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                        <th class="text-end">Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <i class="bi <?= transactionIcon($transaction['type']) ?>"></i>
                                                <?= sanitize(transactionTypeLabel($transaction['type'])) ?>
                                            </td>
                                            <td class="mono"><?= sanitize($transaction['transaction_code']) ?></td>
                                            <td><?= sanitize($transaction['related_name'] ?: '-') ?></td>
                                            <td class="mono <?= transactionAmountClass($transaction['type']) ?>">
                                                <?= sanitize(transactionSignedAmount($transaction)) ?>
                                            </td>
                                            <td>
                                                <span class="admin-chip <?= transactionStatusClass($transaction['status']) ?>">
                                                    <?= sanitize(ucwords(str_replace('_', ' ', $transaction['status']))) ?>
                                                </span>
                                            </td>
                                            <td class="mono muted"><?= sanitize(date('d/m/Y H:i', strtotime($transaction['created_at']))) ?></td>
                                            <td class="text-end">
                                                <a href="<?= BASE_URL ?>/transaction_detail.php?id=<?= (int) $transaction['id'] ?>"
                                                   class="btn btn-sm admin-link-btn">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
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
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
