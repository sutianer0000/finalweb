<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();

$db = getDB();
$transactionId = (int) ($_GET['id'] ?? $_POST['transaction_id'] ?? 0);

if ($transactionId <= 0) {
    setFlash('error', 'Invalid transaction ID.');
    redirect(BASE_URL . '/admin/pending_transactions.php');
}

function adminTransactionTypeLabel(string $type): string
{
    return ucwords(str_replace('_', ' ', $type));
}

function adminTransactionStatusClass(string $status): string
{
    return match ($status) {
        'completed', 'approved' => 'is-verified',
        'pending' => 'is-pending',
        'cancelled', 'rejected' => 'is-disabled',
        default => 'is-info',
    };
}

function generateAdminTransferInCode(PDO $db): string
{
    do {
        $code = 'TFI' . date('ymdHis') . random_int(100, 999);
        $stmt = $db->prepare("SELECT 1 FROM transactions WHERE transaction_code = ? LIMIT 1");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() !== false);

    return $code;
}

function loadAdminTransaction(PDO $db, int $transactionId): ?array
{
    $stmt = $db->prepare("
        SELECT t.*,
               owner.full_name AS owner_name, owner.email AS owner_email,
               owner.phone_number AS owner_phone, owner.balance AS owner_balance,
               related.full_name AS related_name, related.email AS related_email,
               related.phone_number AS related_phone, related.balance AS related_balance
        FROM transactions t
        INNER JOIN users owner ON owner.id = t.user_id
        LEFT JOIN users related ON related.id = t.related_user_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch();

    return $transaction ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['approve_transfer', 'cancel_transfer', 'approve_withdraw', 'cancel_withdraw'], true)) {
        setFlash('error', 'Invalid transaction action.');
        redirect(BASE_URL . '/admin/transaction_detail.php?id=' . $transactionId);
    }

    $transaction = loadAdminTransaction($db, $transactionId);
    if (!$transaction || $transaction['status'] !== 'pending') {
        setFlash('error', 'Pending transaction not found.');
        redirect(BASE_URL . '/admin/pending_transactions.php?status=pending');
    }

    $isTransfer = $transaction['type'] === 'transfer_out';
    $isWithdraw = $transaction['type'] === 'withdraw';
    if (
        (!$isTransfer && !$isWithdraw)
        || ($isTransfer && !in_array($action, ['approve_transfer', 'cancel_transfer'], true))
        || ($isWithdraw && !in_array($action, ['approve_withdraw', 'cancel_withdraw'], true))
    ) {
        setFlash('error', 'This transaction cannot use that action.');
        redirect(BASE_URL . '/admin/transaction_detail.php?id=' . $transactionId);
    }

    if (in_array($action, ['cancel_transfer', 'cancel_withdraw'], true)) {
        $db->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?")->execute([$transactionId]);
        logActivity($isTransfer ? 'admin_cancelled_transfer' : 'admin_cancelled_withdraw', [
            'target_user_id' => $transaction['user_id'],
            'target_email' => $transaction['owner_email'],
            'entity_type' => 'transaction',
            'entity_id' => $transactionId,
            'details' => ['transaction_code' => $transaction['transaction_code']],
        ]);
        setFlash('success', 'Transaction has been disagreed and cancelled.');
        redirect(BASE_URL . '/admin/transaction_detail.php?id=' . $transactionId);
    }

    $db->beginTransaction();
    try {
        if ($isTransfer) {
            if (empty($transaction['related_user_id']) || empty($transaction['related_email']) || empty($transaction['related_name'])) {
                throw new RuntimeException('Recipient account is missing for this transfer.');
            }

            $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$transaction['user_id']]);
            $senderBalance = (float) $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$transaction['related_user_id']]);
            $recipientBalance = (float) $stmt->fetchColumn();

            $amount = (int) $transaction['amount'];
            $fee = (int) $transaction['fee'];
            $senderDebit = $transaction['fee_payer'] === 'sender' ? $amount + $fee : $amount;
            $recipientCredit = $transaction['fee_payer'] === 'receiver' ? $amount - $fee : $amount;

            if ($recipientCredit <= 0) {
                throw new RuntimeException('Recipient amount is invalid after fees.');
            }
            if ($senderBalance < $senderDebit) {
                throw new RuntimeException('Sender no longer has enough balance.');
            }

            $senderBalanceAfter = $senderBalance - $senderDebit;
            $recipientBalanceAfter = $recipientBalance + $recipientCredit;

            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")
                ->execute([$senderBalanceAfter, $transaction['user_id']]);
            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")
                ->execute([$recipientBalanceAfter, $transaction['related_user_id']]);

            $db->prepare("UPDATE transactions SET status = 'approved', balance_after = ? WHERE id = ?")
                ->execute([$senderBalanceAfter, $transactionId]);

            $receiverCode = generateAdminTransferInCode($db);
            $stmt = $db->prepare("
                INSERT INTO transactions (
                    transaction_code, user_id, type, amount, fee, total_amount,
                    status, fee_payer, related_user_id, note, balance_after
                ) VALUES (?, ?, 'transfer_in', ?, ?, ?, 'completed', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $receiverCode,
                $transaction['related_user_id'],
                $recipientCredit,
                $fee,
                $recipientCredit,
                $transaction['fee_payer'],
                $transaction['user_id'],
                $transaction['note'],
                $recipientBalanceAfter,
            ]);

            $db->commit();

            sendTransferReceivedEmail(
                $transaction['related_email'],
                $transaction['related_name'],
                $transaction['owner_name'],
                $recipientCredit,
                $recipientBalanceAfter,
                $transaction['note'] ?? ''
            );

            logActivity('admin_approved_transfer', [
                'target_user_id' => $transaction['user_id'],
                'target_email' => $transaction['owner_email'],
                'entity_type' => 'transaction',
                'entity_id' => $transactionId,
                'details' => [
                    'transaction_code' => $transaction['transaction_code'],
                    'recipient_id' => $transaction['related_user_id'],
                    'amount' => $amount,
                    'fee' => $fee,
                ],
            ]);
        } else {
            $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$transaction['user_id']]);
            $userBalance = (float) $stmt->fetchColumn();
            $totalDebit = (float) $transaction['total_amount'];

            if ($totalDebit <= 0) {
                throw new RuntimeException('Withdrawal amount is invalid.');
            }
            if ($userBalance < $totalDebit) {
                throw new RuntimeException('User no longer has enough balance.');
            }

            $balanceAfter = $userBalance - $totalDebit;
            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")
                ->execute([$balanceAfter, $transaction['user_id']]);
            $db->prepare("UPDATE transactions SET status = 'approved', balance_after = ? WHERE id = ?")
                ->execute([$balanceAfter, $transactionId]);

            $db->commit();

            logActivity('admin_approved_withdraw', [
                'target_user_id' => $transaction['user_id'],
                'target_email' => $transaction['owner_email'],
                'entity_type' => 'transaction',
                'entity_id' => $transactionId,
                'details' => [
                    'transaction_code' => $transaction['transaction_code'],
                    'amount' => (float) $transaction['amount'],
                    'fee' => (float) $transaction['fee'],
                ],
            ]);
        }

        setFlash('success', 'Transaction approved and balances updated.');
    } catch (Throwable $e) {
        $db->rollBack();
        setFlash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Could not approve transaction.');
        error_log('[admin transaction approve] failed: ' . $e->getMessage());
    }

    redirect(BASE_URL . '/admin/transaction_detail.php?id=' . $transactionId);
}

$transaction = loadAdminTransaction($db, $transactionId);
if (!$transaction) {
    setFlash('error', 'Transaction not found.');
    redirect(BASE_URL . '/admin/pending_transactions.php');
}

$canDecideTransfer = $transaction['status'] === 'pending' && $transaction['type'] === 'transfer_out';
$canDecideWithdraw = $transaction['status'] === 'pending' && $transaction['type'] === 'withdraw';
$isOverApprovalLimit = (float) $transaction['amount'] > 5000000;

$pageTitle = 'Transaction #' . $transaction['id'];
$pageStyles = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">
    <div class="admin-heading">
        <div>
            <h3><i class="bi bi-receipt"></i> Transaction Detail</h3>
            <p>Full review information before the admin approves or disagrees.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/pending_transactions.php?status=<?= sanitize($transaction['status']) ?>" class="btn btn-outline-secondary sn-btn-ghost btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Queue
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="admin-panel sn-card">
                <div class="admin-panel-header">
                    <h5><?= sanitize(adminTransactionTypeLabel($transaction['type'])) ?></h5>
                    <span class="admin-chip <?= adminTransactionStatusClass($transaction['status']) ?>">
                        <?= sanitize(ucwords(str_replace('_', ' ', $transaction['status']))) ?>
                    </span>
                </div>
                <div class="admin-panel-body">
                    <dl class="admin-kv">
                        <dt>Transaction Code</dt>
                        <dd class="mono"><?= sanitize($transaction['transaction_code']) ?></dd>

                        <dt>Amount</dt>
                        <dd class="mono"><?= formatMoney($transaction['amount']) ?></dd>

                        <dt>Fee</dt>
                        <dd class="mono"><?= formatMoney($transaction['fee']) ?></dd>

                        <dt>Total</dt>
                        <dd class="mono"><?= formatMoney($transaction['total_amount']) ?></dd>

                        <dt>Fee Payer</dt>
                        <dd><?= sanitize($transaction['fee_payer'] ?: '-') ?></dd>

                        <dt>Approval Rule</dt>
                        <dd><?= $isOverApprovalLimit ? 'Over 5,000,000 VND - admin approval required' : 'Not over approval limit' ?></dd>

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
                    <h5>Accounts</h5>
                </div>
                <div class="admin-panel-body">
                    <div class="admin-actions">
                        <div class="admin-balance-cell">
                            <h6>Owner</h6>
                            <strong><?= sanitize($transaction['owner_name']) ?></strong>
                            <div class="small muted"><?= sanitize($transaction['owner_email']) ?></div>
                            <div class="small mono"><?= sanitize($transaction['owner_phone']) ?></div>
                            <div class="mono mt-2"><?= formatMoney($transaction['owner_balance']) ?></div>
                        </div>

                        <div class="admin-balance-cell">
                            <h6>Related Account</h6>
                            <?php if ($transaction['related_name']): ?>
                                <strong><?= sanitize($transaction['related_name']) ?></strong>
                                <div class="small muted"><?= sanitize($transaction['related_email']) ?></div>
                                <div class="small mono"><?= sanitize($transaction['related_phone']) ?></div>
                                <div class="mono mt-2"><?= formatMoney($transaction['related_balance']) ?></div>
                            <?php else: ?>
                                <div class="admin-empty">No related account.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($canDecideTransfer || $canDecideWithdraw): ?>
                <div class="admin-panel sn-card mt-3">
                    <div class="admin-panel-header">
                        <h5>Admin Decision</h5>
                    </div>
                    <div class="admin-panel-body">
                        <div class="d-flex gap-2 flex-wrap justify-content-end">
                            <form method="POST" onsubmit="return confirm('Approve this transaction and update account balances?');">
                                <input type="hidden" name="transaction_id" value="<?= (int) $transaction['id'] ?>">
                                <input type="hidden" name="action" value="<?= $canDecideTransfer ? 'approve_transfer' : 'approve_withdraw' ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check2-circle"></i> Agree
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Disagree with this transaction? Its status will be cancelled.');">
                                <input type="hidden" name="transaction_id" value="<?= (int) $transaction['id'] ?>">
                                <input type="hidden" name="action" value="<?= $canDecideTransfer ? 'cancel_transfer' : 'cancel_withdraw' ?>">
                                <button type="submit" class="btn btn-outline-secondary sn-btn-ghost">
                                    <i class="bi bi-x-circle"></i> Disagree
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
