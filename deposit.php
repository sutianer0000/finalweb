<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
requireVerified();

$user = getCurrentUser();
$db = getDB();
$errors = [];
$success = null;

function normalizeDepositAmount(string $value): ?int
{
    $normalized = preg_replace('/[,\s]/', '', $value);
    if ($normalized === null || $normalized === '' || !ctype_digit($normalized)) {
        return null;
    }

    $amount = (int) $normalized;
    return $amount > 0 ? $amount : null;
}

function generateDepositTransactionCode(PDO $db): string
{
    do {
        $code = 'DEP' . date('ymdHis') . random_int(100, 999);
        $stmt = $db->prepare("SELECT 1 FROM transactions WHERE transaction_code = ? LIMIT 1");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() !== false);

    return $code;
}

function recordDepositFailure(array $user, string $reason, string $cardNumber, ?int $amount): void
{
    logActivity('deposit_failed', [
        'target_user_id' => $user['id'],
        'target_email' => $user['email'],
        'entity_type' => 'transaction',
        'details' => [
            'reason' => $reason,
            'card_number' => $cardNumber,
            'amount' => $amount,
        ],
    ]);
}

$cardNumber = trim($_POST['card_number'] ?? '');
$expirationDate = trim($_POST['expiration_date'] ?? '');
$cvv = trim($_POST['cvv'] ?? '');
$amountInput = trim($_POST['amount'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = normalizeDepositAmount($amountInput);

    if ($cardNumber === '') {
        $errors[] = 'Card number is required.';
    } elseif (!preg_match('/^\d{6}$/', $cardNumber)) {
        $errors[] = 'Card number must contain exactly 6 digits.';
    }

    if ($expirationDate === '') {
        $errors[] = 'Expiration date is required.';
    } elseif (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $expirationDate)) {
        $errors[] = 'Expiration date must use the format MM/DD/YYYY.';
    }

    if ($cvv === '') {
        $errors[] = 'CVV code is required.';
    } elseif (!preg_match('/^\d{3}$/', $cvv)) {
        $errors[] = 'CVV code must contain exactly 3 digits.';
    }

    if ($amount === null) {
        $errors[] = 'Deposit amount must be a positive whole number.';
    }

    $card = null;
    if (empty($errors)) {
        $stmt = $db->prepare("
            SELECT card_number, expiration_date, cvv, max_amount_per_deposit, always_fail
            FROM credit_cards
            WHERE card_number = ?
              AND card_type IN ('deposit', 'both')
            LIMIT 1
        ");
        $stmt->execute([$cardNumber]);
        $card = $stmt->fetch();

        if (!$card) {
            $errors[] = 'this card is not supported';
        } elseif ($expirationDate !== $card['expiration_date']) {
            $errors[] = 'Expiration date is incorrect for this card.';
        } elseif ($cvv !== $card['cvv']) {
            $errors[] = 'CVV code is incorrect for this card.';
        } elseif ((int) $card['always_fail'] === 1) {
            $errors[] = 'card is out of money';
        } elseif ($card['max_amount_per_deposit'] !== null && $amount > (int) $card['max_amount_per_deposit']) {
            $errors[] = 'This card can only deposit up to 1,000,000 VND per time.';
        }
    }

    if (!empty($errors)) {
        recordDepositFailure($user, $errors[0], $cardNumber, $amount ?? null);
    } else {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$user['id']]);
            $currentBalance = (float) $stmt->fetchColumn();
            $newBalance = $currentBalance + $amount;
            $transactionCode = generateDepositTransactionCode($db);

            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")
                ->execute([$newBalance, $user['id']]);

            $stmt = $db->prepare("
                INSERT INTO transactions (
                    transaction_code, user_id, type, amount, fee, total_amount,
                    status, card_number, note, balance_after
                ) VALUES (?, ?, 'deposit', ?, 0, ?, 'completed', ?, ?, ?)
            ");
            $stmt->execute([
                $transactionCode,
                $user['id'],
                $amount,
                $amount,
                $cardNumber,
                'Deposit from simulated credit card',
                $newBalance,
            ]);

            $transactionId = (int) $db->lastInsertId();
            $db->commit();

            logActivity('deposit_completed', [
                'target_user_id' => $user['id'],
                'target_email' => $user['email'],
                'entity_type' => 'transaction',
                'entity_id' => $transactionId,
                'details' => [
                    'transaction_code' => $transactionCode,
                    'amount' => $amount,
                    'card_number' => $cardNumber,
                    'balance_after' => $newBalance,
                ],
            ]);

            $success = [
                'amount' => $amount,
                'balance_after' => $newBalance,
                'transaction_code' => $transactionCode,
            ];
            $user['balance'] = $newBalance;
            $cardNumber = '';
            $expirationDate = '';
            $cvv = '';
            $amountInput = '';
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[deposit] failed: ' . $e->getMessage());
            $errors[] = 'Deposit could not be completed. Please try again.';
        }
    }
}

$recentStmt = $db->prepare("
    SELECT transaction_code, amount, card_number, created_at
    FROM transactions
    WHERE user_id = ? AND type = 'deposit'
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");
$recentStmt->execute([$user['id']]);
$recentDeposits = $recentStmt->fetchAll();

$pageTitle = 'Deposit';
$pageStyles = ['dashboard.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-hud">
    <div class="dashboard-shell">
        <div class="dashboard-greeting">
            <div class="eyebrow">Wallet Dock</div>
            <h1>Deposit</h1>
            <p>Balance: <strong class="sn-readout"><?= formatMoney($user['balance']) ?></strong></p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success dashboard-alert mb-4">
                <i class="bi bi-check-circle"></i>
                Deposit successful. Transaction
                <span class="mono"><?= sanitize($success['transaction_code']) ?></span>
                added <?= formatMoney($success['amount']) ?>.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger dashboard-alert mb-4">
                <?php foreach ($errors as $error): ?>
                    <div><i class="bi bi-exclamation-triangle"></i> <?= sanitize($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <div class="hud-card sn-card">
                    <form method="POST" novalidate>
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
                                           placeholder="1,000,000"
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Deposit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <aside class="activity-panel sn-card">
                    <div class="activity-header">
                        <div class="panel-label">Recent Deposits</div>
                        <p><?= $recentDeposits ? 'Latest completed top-ups.' : 'No deposit history yet.' ?></p>
                        <div class="sn-sonar-line mt-3"></div>
                    </div>

                    <?php if ($recentDeposits): ?>
                        <ul class="activity-list">
                            <?php foreach ($recentDeposits as $deposit): ?>
                                <li class="activity-row">
                                    <span class="activity-time"><?= date('H:i', strtotime($deposit['created_at'])) ?></span>
                                    <span class="activity-type is-deposit"><?= sanitize($deposit['transaction_code']) ?></span>
                                    <span class="activity-amount is-positive"><?= formatMoney($deposit['amount']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="activity-empty">Completed deposits will appear here.</div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/money-format.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
