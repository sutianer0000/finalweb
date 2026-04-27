<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
requireVerified();

$user = getCurrentUser();
$db = getDB();
$errors = [];
$result = null;

$carriers = [
    'Viettel' => '11111',
    'Mobifone' => '22222',
    'Vinaphone' => '33333',
];
$denominations = [10000, 20000, 50000, 100000];
$fee = 0;

function generatePhoneCardTransactionCode(PDO $db): string
{
    do {
        $code = 'PHC' . date('ymdHis') . random_int(100, 999);
        $stmt = $db->prepare("SELECT 1 FROM transactions WHERE transaction_code = ? LIMIT 1");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() !== false);

    return $code;
}

function generatePhoneScratchCode(string $carrierCode): string
{
    return $carrierCode . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
}

$carrier = $_POST['carrier'] ?? 'Viettel';
$denomination = (int) ($_POST['denomination'] ?? 10000);
$quantity = (int) ($_POST['quantity'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    requireIdempotencyToken('phone_card');

    if (!isset($carriers[$carrier])) {
        $errors[] = 'Please choose a valid carrier.';
    }
    if (!in_array($denomination, $denominations, true)) {
        $errors[] = 'Please choose a valid denomination.';
    }
    if ($quantity < 1 || $quantity > 5) {
        $errors[] = 'You can buy from 1 to 5 card codes in one transaction.';
    }

    $totalAmount = $denomination * $quantity;
    $totalDebit = $totalAmount + $fee;

    if (empty($errors) && (float) $user['balance'] < $totalDebit) {
        $errors[] = 'Insufficient balance to buy these phone cards.';
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$user['id']]);
            $currentBalance = (float) $stmt->fetchColumn();

            if ($currentBalance < $totalDebit) {
                throw new RuntimeException('Insufficient balance to buy these phone cards.');
            }

            $newBalance = $currentBalance - $totalDebit;
            $transactionCode = generatePhoneCardTransactionCode($db);

            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")
                ->execute([$newBalance, $user['id']]);

            $stmt = $db->prepare("
                INSERT INTO transactions (
                    transaction_code, user_id, type, amount, fee, total_amount,
                    status, note, balance_after
                ) VALUES (?, ?, 'phone_card', ?, ?, ?, 'completed', ?, ?)
            ");
            $stmt->execute([
                $transactionCode,
                $user['id'],
                $totalAmount,
                $fee,
                $totalDebit,
                "{$quantity} {$carrier} phone card(s), denomination " . formatMoney($denomination),
                $newBalance,
            ]);
            $transactionId = (int) $db->lastInsertId();

            $codes = [];
            $stmt = $db->prepare("
                INSERT INTO phone_cards (transaction_id, user_id, carrier, carrier_code, denomination, card_code)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            for ($i = 0; $i < $quantity; $i++) {
                $cardCode = generatePhoneScratchCode($carriers[$carrier]);
                $codes[] = $cardCode;
                $stmt->execute([
                    $transactionId,
                    $user['id'],
                    $carrier,
                    $carriers[$carrier],
                    $denomination,
                    $cardCode,
                ]);
            }

            $db->commit();

            logActivity('phone_card_purchased', [
                'target_user_id' => $user['id'],
                'target_email' => $user['email'],
                'entity_type' => 'transaction',
                'entity_id' => $transactionId,
                'details' => [
                    'transaction_code' => $transactionCode,
                    'carrier' => $carrier,
                    'denomination' => $denomination,
                    'quantity' => $quantity,
                    'total_amount' => $totalAmount,
                ],
            ]);

            $user['balance'] = $newBalance;
            $result = [
                'transaction_code' => $transactionCode,
                'carrier' => $carrier,
                'denomination' => $denomination,
                'quantity' => $quantity,
                'fee' => $fee,
                'total_amount' => $totalAmount,
                'balance_after' => $newBalance,
                'codes' => $codes,
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Phone card purchase could not be completed. Please try again.';
            error_log('[phone_card] failed: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Buy Phone Card';
$pageStyles = ['dashboard.css', 'admin.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-hud">
    <div class="dashboard-shell">
        <div class="dashboard-greeting">
            <div class="eyebrow">Service Dock</div>
            <h1>Buy Phone Card</h1>
            <p>Balance: <strong class="sn-readout"><?= formatMoney($user['balance']) ?></strong></p>
        </div>

        <?php if ($result): ?>
            <div class="hud-card sn-card mb-4">
                <div class="admin-panel-header">
                    <h5><i class="bi bi-check-circle"></i> Purchase Successful</h5>
                    <span class="admin-chip is-verified">Completed</span>
                </div>
                <div class="admin-panel-body">
                    <dl class="admin-kv">
                        <dt>Transaction Code</dt>
                        <dd class="mono"><?= sanitize($result['transaction_code']) ?></dd>
                        <dt>Carrier</dt>
                        <dd><?= sanitize($result['carrier']) ?></dd>
                        <dt>Denomination</dt>
                        <dd class="mono"><?= formatMoney($result['denomination']) ?></dd>
                        <dt>Quantity</dt>
                        <dd><?= (int) $result['quantity'] ?></dd>
                        <dt>Fee</dt>
                        <dd class="mono"><?= formatMoney($result['fee']) ?></dd>
                        <dt>Total Deducted</dt>
                        <dd class="mono"><?= formatMoney($result['total_amount'] + $result['fee']) ?></dd>
                        <dt>Balance After</dt>
                        <dd class="mono"><?= formatMoney($result['balance_after']) ?></dd>
                    </dl>

                    <h6 class="mt-4 mb-3">Card Codes</h6>
                    <div class="row g-3">
                        <?php foreach ($result['codes'] as $code): ?>
                            <div class="col-sm-6">
                                <div class="admin-balance-cell">
                                    <h6><?= sanitize($result['carrier']) ?> <?= formatMoney($result['denomination']) ?></h6>
                                    <strong class="mono"><?= sanitize($code) ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-4">
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">
                            <i class="bi bi-house"></i> Back to Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>/phone_card.php" class="btn btn-primary">
                            <i class="bi bi-phone"></i> Buy Another Card
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="hud-card sn-card mb-4">
                <div class="admin-panel-header">
                    <h5><i class="bi bi-x-circle"></i> Purchase Failed</h5>
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
                        <a href="<?= BASE_URL ?>/phone_card.php" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Try Purchase Again
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$result && !(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors))): ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="hud-card sn-card">
                    <form method="POST" novalidate>
                        <?= csrfField() ?>
                        <?= idempotencyField('phone_card') ?>
                        <div class="mb-3">
                            <label for="carrier" class="form-label">Carrier</label>
                            <select class="form-select" id="carrier" name="carrier" required>
                                <?php foreach ($carriers as $carrierName => $carrierCode): ?>
                                    <option value="<?= sanitize($carrierName) ?>" <?= $carrier === $carrierName ? 'selected' : '' ?>>
                                        <?= sanitize($carrierName) ?> - <?= sanitize($carrierCode) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="denomination" class="form-label">Denomination</label>
                            <select class="form-select" id="denomination" name="denomination" required>
                                <?php foreach ($denominations as $value): ?>
                                    <option value="<?= (int) $value ?>" <?= $denomination === $value ? 'selected' : '' ?>>
                                        <?= formatMoney($value) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity"
                                   value="<?= (int) $quantity ?>" min="1" max="5" required>
                            <div class="form-text text-muted">You can buy up to 5 card codes of the same type in one transaction.</div>
                        </div>

                        <div class="admin-balance-strip mb-4">
                            <div class="admin-balance-cell">
                                <h6>Transaction Fee</h6>
                                <strong><?= formatMoney($fee) ?></strong>
                            </div>
                            <div class="admin-balance-cell">
                                <h6>Total</h6>
                                <strong><?= formatMoney($denomination * max(1, min(5, $quantity)) + $fee) ?></strong>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">Back</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-phone"></i> Buy Card</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <aside class="activity-panel sn-card">
                    <div class="activity-header">
                        <div class="panel-label">Carrier Codes</div>
                        <p>Generated scratch codes always begin with the carrier code.</p>
                        <div class="sn-sonar-line mt-3"></div>
                    </div>
                    <ul class="activity-list">
                        <?php foreach ($carriers as $carrierName => $carrierCode): ?>
                            <li class="activity-row">
                                <span class="activity-time"><?= sanitize($carrierCode) ?></span>
                                <span class="activity-type is-phone-card"><?= sanitize($carrierName) ?></span>
                                <span class="activity-amount"><?= formatMoney(0) ?> fee</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
