<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
requireVerified();

$user = getCurrentUser();
$db = getDB();
$errors = [];
$receipt = null;

const WITHDRAW_CARD_NUMBER = '111111';
const WITHDRAW_CARD_EXPIRATION = '10/10/2022';
const WITHDRAW_CARD_CVV = '411';
const WITHDRAW_DAILY_LIMIT = 2;
const WITHDRAW_STEP = 50000;
const WITHDRAW_APPROVAL_LIMIT = 5000000;

function normalizeWithdrawAmount(string $value): ?int
{
    $normalized = preg_replace('/[,\s]/', '', $value);
    if ($normalized === null || $normalized === '' || !ctype_digit($normalized)) {
        return null;
    }

    $amount = (int) $normalized;
    return $amount > 0 ? $amount : null;
}

function generateWithdrawTransactionCode(PDO $db): string
{
    do {
        $code = 'WDR' . date('ymdHis') . random_int(100, 999);
        $stmt = $db->prepare("SELECT 1 FROM transactions WHERE transaction_code = ? LIMIT 1");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() !== false);

    return $code;
}

function withdrawFee(int $amount): int
{
    return (int) round($amount * 0.05);
}

function todayWithdrawCount(PDO $db, int $userId): int
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM transactions
        WHERE user_id = ?
          AND type = 'withdraw'
          AND status NOT IN ('cancelled', 'rejected')
          AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

$cardNumber = trim($_POST['card_number'] ?? '');
$expirationDate = trim($_POST['expiration_date'] ?? '');
$cvv = trim($_POST['cvv'] ?? '');
$amountInput = trim($_POST['amount'] ?? '');
$note = trim($_POST['note'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    requireIdempotencyToken('withdraw');

    $amount = normalizeWithdrawAmount($amountInput);

    if ($cardNumber === '' || $expirationDate === '' || $cvv === '') {
        $errors[] = 'Invalid card information';
    } elseif (!preg_match('/^\d{6}$/', $cardNumber)) {
        $errors[] = 'Invalid card information';
    } elseif ($cardNumber !== WITHDRAW_CARD_NUMBER) {
        $errors[] = 'This card is not supported for withdrawal';
    } elseif ($expirationDate !== WITHDRAW_CARD_EXPIRATION || $cvv !== WITHDRAW_CARD_CVV) {
        $errors[] = 'Invalid card information';
    }

    if ($amount === null) {
        $errors[] = 'Withdrawal amount must be a positive whole number.';
    } elseif ($amount % WITHDRAW_STEP !== 0) {
        $errors[] = 'Withdrawal amount must be a multiple of 50,000 VND.';
    }

    if (mb_strlen($note) > 255) {
        $errors[] = 'Note must be 255 characters or less.';
    }

    if (empty($errors) && todayWithdrawCount($db, (int) $user['id']) >= WITHDRAW_DAILY_LIMIT) {
        $errors[] = 'Only 2 withdrawals can be made per day.';
    }

    $fee = $amount !== null ? withdrawFee($amount) : 0;
    $totalDebit = $amount !== null ? $amount + $fee : 0;

    if (empty($errors) && (float) $user['balance'] < $totalDebit) {
        $errors[] = 'Insufficient balance for this withdrawal and fee.';
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$user['id']]);
            $currentBalance = (float) $stmt->fetchColumn();

            if ($currentBalance < $totalDebit) {
                throw new RuntimeException('Insufficient balance for this withdrawal and fee.');
            }

            if (todayWithdrawCount($db, (int) $user['id']) >= WITHDRAW_DAILY_LIMIT) {
                throw new RuntimeException('Only 2 withdrawals can be made per day.');
            }

            $transactionCode = generateWithdrawTransactionCode($db);
            $isPending = $amount > WITHDRAW_APPROVAL_LIMIT;
            $status = $isPending ? 'pending' : 'completed';
            $balanceAfter = $isPending ? null : $currentBalance - $totalDebit;

            if (!$isPending) {
                $db->prepare("UPDATE users SET balance = ? WHERE id = ?")
                    ->execute([$balanceAfter, $user['id']]);
            }

            $stmt = $db->prepare("
                INSERT INTO transactions (
                    transaction_code, user_id, type, amount, fee, total_amount,
                    status, card_number, note, balance_after
                ) VALUES (?, ?, 'withdraw', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transactionCode,
                $user['id'],
                $amount,
                $fee,
                $totalDebit,
                $status,
                $cardNumber,
                $note,
                $balanceAfter,
            ]);
            $transactionId = (int) $db->lastInsertId();

            $db->commit();

            logActivity($isPending ? 'withdraw_pending_approval' : 'withdraw_completed', [
                'target_user_id' => $user['id'],
                'target_email' => $user['email'],
                'entity_type' => 'transaction',
                'entity_id' => $transactionId,
                'details' => [
                    'transaction_code' => $transactionCode,
                    'amount' => $amount,
                    'fee' => $fee,
                    'total_amount' => $totalDebit,
                    'status' => $status,
                ],
            ]);

            if (!$isPending) {
                $user['balance'] = $balanceAfter;
            }

            $receipt = [
                'transaction_code' => $transactionCode,
                'status' => $status,
                'amount' => $amount,
                'fee' => $fee,
                'total_amount' => $totalDebit,
                'balance_after' => $balanceAfter,
                'card_number' => $cardNumber,
                'note' => $note,
            ];

            $cardNumber = '';
            $expirationDate = '';
            $cvv = '';
            $amountInput = '';
            $note = '';
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[withdraw] failed: ' . $e->getMessage());
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Withdrawal could not be completed. Please try again.';
        }
    }
}

$recentStmt = $db->prepare("
    SELECT transaction_code, amount, fee, total_amount, status, created_at
    FROM transactions
    WHERE user_id = ? AND type = 'withdraw'
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");
$recentStmt->execute([$user['id']]);
$recentWithdrawals = $recentStmt->fetchAll();

$withdrawsToday = todayWithdrawCount($db, (int) $user['id']);

$pageTitle = 'Withdraw';
$pageStyles = ['dashboard.css', 'admin.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-hud">
    <div class="dashboard-shell">
        <div class="dashboard-greeting">
            <div class="eyebrow">Wallet Dock</div>
            <h1>Withdraw</h1>
            <p>Balance: <strong class="sn-readout"><?= formatMoney($user['balance']) ?></strong></p>
        </div>

        <?php if ($receipt): ?>
            <div class="hud-card sn-card mb-4">
                <div class="admin-panel-header">
                    <h5>
                        <i class="bi <?= $receipt['status'] === 'pending' ? 'bi-hourglass-split' : 'bi-check-circle' ?>"></i>
                        <?= $receipt['status'] === 'pending' ? 'Withdrawal Waiting for Approval' : 'Withdrawal Successful' ?>
                    </h5>
                    <span class="admin-chip <?= $receipt['status'] === 'pending' ? 'is-pending' : 'is-verified' ?>">
                        <?= sanitize(ucwords($receipt['status'])) ?>
                    </span>
                </div>
                <div class="admin-panel-body">
                    <dl class="admin-kv">
                        <dt>Transaction Code</dt>
                        <dd class="mono"><?= sanitize($receipt['transaction_code']) ?></dd>
                        <dt>Card Number</dt>
                        <dd class="mono"><?= sanitize($receipt['card_number']) ?></dd>
                        <dt>Amount</dt>
                        <dd class="mono"><?= formatMoney($receipt['amount']) ?></dd>
                        <dt>Fee</dt>
                        <dd class="mono"><?= formatMoney($receipt['fee']) ?></dd>
                        <dt>Total Deducted</dt>
                        <dd class="mono"><?= formatMoney($receipt['total_amount']) ?></dd>
                        <dt>Balance After</dt>
                        <dd class="mono"><?= $receipt['balance_after'] !== null ? formatMoney($receipt['balance_after']) : 'Pending admin approval' ?></dd>
                        <dt>Note</dt>
                        <dd><?= $receipt['note'] !== '' ? nl2br(sanitize($receipt['note'])) : '-' ?></dd>
                    </dl>
                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-4">
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">
                            <i class="bi bi-house"></i> Back to Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>/withdraw.php" class="btn btn-primary">
                            <i class="bi bi-dash-circle"></i> Withdraw Again
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="hud-card sn-card mb-4">
                <div class="admin-panel-header">
                    <h5><i class="bi bi-x-circle"></i> Withdrawal Failed</h5>
                    <span class="admin-chip is-disabled">Failed</span>
                </div>
                <div class="admin-panel-body">
                    <?php foreach ($errors as $error): ?>
                        <div class="mb-2"><i class="bi bi-exclamation-triangle"></i> <?= sanitize($error) ?></div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-4">
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">
                            <i class="bi bi-house"></i> Back to Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>/withdraw.php" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Try Withdrawal Again
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$receipt && !(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($errors))): ?>
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <div class="hud-card sn-card">
                    <form method="POST" novalidate>
                        <?= csrfField() ?>
                        <?= idempotencyField('withdraw') ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="card_number" class="form-label">Card Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-credit-card"></i></span>
                                    <input type="text"
                                           class="form-control"
                                           id="card_number"
                                           name="card_number"
                                           inputmode="numeric"
                                           maxlength="6"
                                           value="<?= sanitize($cardNumber) ?>"
                                           placeholder="111111"
                                           required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="expiration_date" class="form-label">Expiration Date</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar2-week"></i></span>
                                    <input type="text"
                                           class="form-control"
                                           id="expiration_date"
                                           name="expiration_date"
                                           inputmode="numeric"
                                           maxlength="10"
                                           value="<?= sanitize($expirationDate) ?>"
                                           placeholder="10/10/2022"
                                           required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="cvv" class="form-label">CVV</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                    <input type="password"
                                           class="form-control"
                                           id="cvv"
                                           name="cvv"
                                           inputmode="numeric"
                                           maxlength="3"
                                           value="<?= sanitize($cvv) ?>"
                                           placeholder="411"
                                           required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="amount" class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">VND</span>
                                    <input type="text"
                                           class="form-control"
                                           id="amount"
                                           name="amount"
                                           inputmode="numeric"
                                           data-money-input
                                           value="<?= sanitize($amountInput) ?>"
                                           placeholder="500,000"
                                           required>
                                </div>
                                <div class="form-text text-muted">Must be a multiple of 50,000 VND.</div>
                            </div>

                            <div class="col-12">
                                <label for="note" class="form-label">Note</label>
                                <textarea class="form-control" id="note" name="note" rows="3" maxlength="255"><?= sanitize($note) ?></textarea>
                            </div>
                        </div>

                        <div class="admin-balance-strip my-4">
                            <div class="admin-balance-cell">
                                <h6>Withdrawals Today</h6>
                                <strong><?= (int) $withdrawsToday ?> / <?= WITHDRAW_DAILY_LIMIT ?></strong>
                            </div>
                            <div class="admin-balance-cell">
                                <h6>Transaction Fee</h6>
                                <strong>5%</strong>
                            </div>
                            <div class="admin-balance-cell">
                                <h6>Approval Rule</h6>
                                <strong>Over <?= formatMoney(WITHDRAW_APPROVAL_LIMIT) ?></strong>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-dash-circle"></i> Withdraw
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <aside class="activity-panel sn-card">
                    <div class="activity-header">
                        <div class="panel-label">Recent Withdrawals</div>
                        <p><?= $recentWithdrawals ? 'Latest withdrawal requests.' : 'No withdrawal history yet.' ?></p>
                        <div class="sn-sonar-line mt-3"></div>
                    </div>

                    <?php if ($recentWithdrawals): ?>
                        <ul class="activity-list">
                            <?php foreach ($recentWithdrawals as $withdraw): ?>
                                <li class="activity-row">
                                    <span class="activity-time"><?= date('H:i', strtotime($withdraw['created_at'])) ?></span>
                                    <span class="activity-type is-withdraw"><?= sanitize($withdraw['transaction_code']) ?></span>
                                    <span class="activity-amount is-negative"><?= formatMoney($withdraw['total_amount']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="activity-empty">Withdrawal requests will appear here.</div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/money-format.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
