<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();
$transactionId = (int) ($_GET['id'] ?? 0);

if ($transactionId <= 0) {
    setFlash('error', 'Invalid transaction ID.');
    redirect(BASE_URL . '/transactions.php');
}

$stmt = $db->prepare("
    SELECT t.*, related.full_name AS related_name, related.phone_number AS related_phone, related.email AS related_email
    FROM transactions t
    LEFT JOIN users related ON related.id = t.related_user_id
    WHERE t.id = ? AND t.user_id = ?
    LIMIT 1
");
$stmt->execute([$transactionId, $user['id']]);
$transaction = $stmt->fetch();

if (!$transaction) {
    setFlash('error', 'Transaction not found.');
    redirect(BASE_URL . '/transactions.php');
}

$cardStmt = $db->prepare("
    SELECT carrier, carrier_code, denomination, card_code, created_at
    FROM phone_cards
    WHERE transaction_id = ? AND user_id = ?
    ORDER BY id ASC
");
$cardStmt->execute([$transactionId, $user['id']]);
$phoneCards = $cardStmt->fetchAll();

function detailTypeLabel(string $type): string
{
    return ucwords(str_replace('_', ' ', $type));
}

function detailStatusClass(string $status): string
{
    return match ($status) {
        'completed', 'approved' => 'is-verified',
        'pending' => 'is-pending',
        'cancelled', 'rejected' => 'is-disabled',
        default => 'is-info',
    };
}

$pageTitle = 'Transaction Detail';
$pageStyles = ['dashboard.css', 'admin.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-hud">
    <div class="dashboard-shell">
        <div class="dashboard-greeting">
            <div class="eyebrow">Ledger Detail</div>
            <h1><?= sanitize(detailTypeLabel($transaction['type'])) ?></h1>
            <p class="mono"><?= sanitize($transaction['transaction_code']) ?></p>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="admin-panel sn-card">
                    <div class="admin-panel-header">
                        <h5>Transaction Information</h5>
                    </div>
                    <div class="admin-panel-body">
                        <dl class="admin-kv">
                            <dt>Status</dt>
                            <dd>
                                <span class="admin-chip <?= detailStatusClass($transaction['status']) ?>">
                                    <?= sanitize(ucwords(str_replace('_', ' ', $transaction['status']))) ?>
                                </span>
                            </dd>

                            <dt>Amount</dt>
                            <dd class="mono"><?= formatMoney($transaction['amount']) ?></dd>

                            <dt>Fee</dt>
                            <dd class="mono"><?= formatMoney($transaction['fee']) ?></dd>

                            <dt>Total</dt>
                            <dd class="mono"><?= formatMoney($transaction['total_amount']) ?></dd>

                            <dt>Fee Payer</dt>
                            <dd><?= sanitize($transaction['fee_payer'] ?: '-') ?></dd>

                            <dt>Related Account</dt>
                            <dd>
                                <?php if ($transaction['related_name']): ?>
                                    <?= sanitize($transaction['related_name']) ?>
                                    <div class="small muted"><?= sanitize($transaction['related_phone']) ?> - <?= sanitize($transaction['related_email']) ?></div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </dd>

                            <dt>Note</dt>
                            <dd><?= $transaction['note'] !== null && $transaction['note'] !== '' ? nl2br(sanitize($transaction['note'])) : '-' ?></dd>

                            <dt>Balance After</dt>
                            <dd class="mono"><?= $transaction['balance_after'] !== null ? formatMoney($transaction['balance_after']) : '-' ?></dd>

                            <dt>Created</dt>
                            <dd class="mono"><?= sanitize(date('d/m/Y H:i:s', strtotime($transaction['created_at']))) ?></dd>

                            <dt>Updated</dt>
                            <dd class="mono"><?= sanitize(date('d/m/Y H:i:s', strtotime($transaction['updated_at']))) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="admin-panel sn-card">
                    <div class="admin-panel-header">
                        <h5>Phone Card Codes</h5>
                    </div>
                    <div class="admin-panel-body">
                        <?php if (!$phoneCards): ?>
                            <div class="admin-empty">No phone card codes for this transaction.</div>
                        <?php else: ?>
                            <div class="admin-actions">
                                <?php foreach ($phoneCards as $card): ?>
                                    <div class="admin-balance-cell">
                                        <h6><?= sanitize($card['carrier']) ?> <?= formatMoney($card['denomination']) ?></h6>
                                        <strong class="mono"><?= sanitize($card['card_code']) ?></strong>
                                        <div class="small muted">Carrier code: <?= sanitize($card['carrier_code']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="<?= BASE_URL ?>/transactions.php" class="btn btn-outline-secondary sn-btn-ghost">
                        <i class="bi bi-arrow-left"></i> Back to History
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
