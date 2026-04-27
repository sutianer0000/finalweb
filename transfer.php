<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/mailer.php';
requireVerified();

$user = getCurrentUser();
$db = getDB();
$errors = [];
$info = null;
$success = null;
$pendingApproval = null;
$failedTransfer = null;

function normalizeTransferAmount(string $value): ?int
{
    $normalized = preg_replace('/[,\s]/', '', $value);
    if ($normalized === null || $normalized === '' || !ctype_digit($normalized)) {
        return null;
    }

    $amount = (int) $normalized;
    return $amount > 0 ? $amount : null;
}

function generateTransferTransactionCode(PDO $db, string $prefix = 'TRF'): string
{
    do {
        $code = $prefix . date('ymdHis') . random_int(100, 999);
        $stmt = $db->prepare("SELECT 1 FROM transactions WHERE transaction_code = ? LIMIT 1");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() !== false);

    return $code;
}

function transferFee(int $amount): int
{
    return (int) round($amount * 0.05);
}

function buildTransferFailureReceipt(array $transfer, string $reason): array
{
    $amount = (int) ($transfer['amount'] ?? 0);
    $fee = (int) ($transfer['fee'] ?? transferFee($amount));
    $feePayer = $transfer['fee_payer'] ?? 'sender';

    return [
        'reason' => $reason,
        'recipient_name' => $transfer['recipient_name'] ?? '',
        'recipient_phone' => $transfer['recipient_phone'] ?? '',
        'amount' => $amount,
        'fee' => $fee,
        'total_amount' => $feePayer === 'sender' ? $amount + $fee : $amount,
        'fee_payer' => $feePayer,
        'note' => $transfer['note'] ?? '',
    ];
}

function completeTransfer(PDO $db, array $sender, array $recipient, int $amount, string $feePayer, string $note, ?int $pendingTransactionId = null): array
{
    $fee = transferFee($amount);
    $senderDebit = $feePayer === 'sender' ? $amount + $fee : $amount;
    $recipientCredit = $feePayer === 'receiver' ? $amount - $fee : $amount;

    if ($recipientCredit <= 0) {
        throw new RuntimeException('Transfer amount is too small after fees.');
    }

    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$sender['id']]);
    $senderBalance = (float) $stmt->fetchColumn();

    if ($senderBalance < $senderDebit) {
        throw new RuntimeException('Insufficient balance for this transfer and fee.');
    }

    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$recipient['id']]);
    $recipientBalance = (float) $stmt->fetchColumn();

    $senderBalanceAfter = $senderBalance - $senderDebit;
    $recipientBalanceAfter = $recipientBalance + $recipientCredit;

    $db->prepare("UPDATE users SET balance = ? WHERE id = ?")
        ->execute([$senderBalanceAfter, $sender['id']]);
    $db->prepare("UPDATE users SET balance = ? WHERE id = ?")
        ->execute([$recipientBalanceAfter, $recipient['id']]);

    if ($pendingTransactionId) {
        $stmt = $db->prepare("
            UPDATE transactions
            SET status = 'approved',
                fee = ?,
                total_amount = ?,
                balance_after = ?
            WHERE id = ?
        ");
        $stmt->execute([$fee, $senderDebit, $senderBalanceAfter, $pendingTransactionId]);

        $stmt = $db->prepare("SELECT transaction_code FROM transactions WHERE id = ?");
        $stmt->execute([$pendingTransactionId]);
        $senderCode = (string) $stmt->fetchColumn();
        $senderTransactionId = $pendingTransactionId;
    } else {
        $senderCode = generateTransferTransactionCode($db, 'TFO');
        $stmt = $db->prepare("
            INSERT INTO transactions (
                transaction_code, user_id, type, amount, fee, total_amount,
                status, fee_payer, related_user_id, note, balance_after
            ) VALUES (?, ?, 'transfer_out', ?, ?, ?, 'completed', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $senderCode,
            $sender['id'],
            $amount,
            $fee,
            $senderDebit,
            $feePayer,
            $recipient['id'],
            $note,
            $senderBalanceAfter,
        ]);
        $senderTransactionId = (int) $db->lastInsertId();
    }

    $receiverCode = generateTransferTransactionCode($db, 'TFI');
    $stmt = $db->prepare("
        INSERT INTO transactions (
            transaction_code, user_id, type, amount, fee, total_amount,
            status, fee_payer, related_user_id, note, balance_after
        ) VALUES (?, ?, 'transfer_in', ?, ?, ?, 'completed', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $receiverCode,
        $recipient['id'],
        $recipientCredit,
        $fee,
        $recipientCredit,
        $feePayer,
        $sender['id'],
        $note,
        $recipientBalanceAfter,
    ]);
    $recipientTransactionId = (int) $db->lastInsertId();

    return [
        'sender_transaction_id' => $senderTransactionId,
        'recipient_transaction_id' => $recipientTransactionId,
        'sender_code' => $senderCode,
        'recipient_code' => $receiverCode,
        'fee' => $fee,
        'sender_debit' => $senderDebit,
        'recipient_credit' => $recipientCredit,
        'sender_balance_after' => $senderBalanceAfter,
        'recipient_balance_after' => $recipientBalanceAfter,
    ];
}

if (isset($_GET['lookup_recipient'])) {
    header('Content-Type: application/json; charset=utf-8');

    $phone = trim($_GET['phone'] ?? '');
    if ($phone === '') {
        echo json_encode(['ok' => false, 'message' => 'Enter a phone number.']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT id, full_name, phone_number, status, role
        FROM users
        WHERE phone_number = ?
        LIMIT 1
    ");
    $stmt->execute([$phone]);
    $recipient = $stmt->fetch();

    if (!$recipient || $recipient['role'] !== 'user') {
        echo json_encode(['ok' => false, 'message' => 'Recipient account was not found.']);
        exit;
    }
    if ((int) $recipient['id'] === (int) $user['id']) {
        echo json_encode(['ok' => false, 'message' => 'You cannot transfer money to your own account.']);
        exit;
    }
    if ($recipient['status'] !== 'verified') {
        echo json_encode([
            'ok' => false,
            'name' => $recipient['full_name'],
            'phone' => $recipient['phone_number'],
            'message' => 'Recipient account is not verified.',
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'name' => $recipient['full_name'],
        'phone' => $recipient['phone_number'],
        'message' => 'Recipient found.',
    ]);
    exit;
}

if (isset($_GET['restart'])) {
    unset($_SESSION['transfer']);
    redirect(BASE_URL . '/transfer.php');
}

$step = $_SESSION['transfer']['stage'] ?? 'prepare';
$recipientPhone = trim($_POST['recipient_phone'] ?? '');
$amountInput = trim($_POST['amount'] ?? '');
$note = trim($_POST['note'] ?? '');
$feePayer = $_POST['fee_payer'] ?? 'sender';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'prepare') {
    requireCsrfToken();
    requireIdempotencyToken('transfer_prepare');

    $amount = normalizeTransferAmount($amountInput);

    if ($recipientPhone === '') {
        $errors[] = 'Recipient phone number is required.';
    }
    if ($amount === null) {
        $errors[] = 'Transfer amount must be a positive whole number.';
    }
    if (!in_array($feePayer, ['sender', 'receiver'], true)) {
        $errors[] = 'Please choose who pays the transfer fee.';
    }
    if ($note === '') {
        $errors[] = 'Transfer note is required.';
    } elseif (mb_strlen($note) > 255) {
        $errors[] = 'Transfer note must be 255 characters or less.';
    }

    $recipient = null;
    if (empty($errors)) {
        $stmt = $db->prepare("
            SELECT id, full_name, email, phone_number, balance, status, role
            FROM users
            WHERE phone_number = ?
            LIMIT 1
        ");
        $stmt->execute([$recipientPhone]);
        $recipient = $stmt->fetch();

        if (!$recipient || $recipient['role'] !== 'user') {
            $errors[] = 'Recipient account was not found.';
        } elseif ((int) $recipient['id'] === (int) $user['id']) {
            $errors[] = 'You cannot transfer money to your own account.';
        } elseif ($recipient['status'] !== 'verified') {
            $errors[] = 'Recipient account is not verified.';
        }
    }

    if (empty($errors)) {
        $fee = transferFee($amount);
        $requiredBalance = $feePayer === 'sender' ? $amount + $fee : $amount;
        if ((float) $user['balance'] < $requiredBalance) {
            $errors[] = 'Insufficient balance for this transfer and fee.';
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare("
            SELECT created_at
            FROM otp_codes
            WHERE user_id = ?
              AND purpose = 'transfer_verification'
              AND used = 0
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        if ($stmt->fetch()) {
            $errors[] = 'Please wait 1 minute before requesting another OTP.';
        }
    }

    if (empty($errors)) {
        $db->prepare("
            UPDATE otp_codes
            SET used = 1
            WHERE user_id = ?
              AND purpose = 'transfer_verification'
              AND used = 0
        ")->execute([$user['id']]);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $db->prepare("
            INSERT INTO otp_codes (user_id, otp_code, purpose, expires_at)
            VALUES (?, ?, 'transfer_verification', DATE_ADD(NOW(), INTERVAL 1 MINUTE))
        ")->execute([$user['id'], $otp]);

        $mailResult = sendTransferOtpEmail($user['email'], $user['full_name'], $otp, $recipient['full_name'], $amount);
        if (!$mailResult['ok']) {
            $db->prepare("
                UPDATE otp_codes
                SET used = 1
                WHERE user_id = ?
                  AND purpose = 'transfer_verification'
                  AND otp_code = ?
                  AND used = 0
            ")->execute([$user['id'], $otp]);
            $errors[] = 'Could not send OTP email. Please try again later.';
        } else {
            $_SESSION['transfer'] = [
                'stage' => 'verify',
                'recipient_id' => (int) $recipient['id'],
                'recipient_name' => $recipient['full_name'],
                'recipient_email' => $recipient['email'],
                'recipient_phone' => $recipient['phone_number'],
                'amount' => $amount,
                'fee' => transferFee($amount),
                'fee_payer' => $feePayer,
                'note' => $note,
                'created_at' => time(),
                'otp_attempts' => 0,
            ];
            $step = 'verify';
            $info = 'OTP sent to your registered email. It expires in 1 minute.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'verify') {
    requireCsrfToken();
    requireIdempotencyToken('transfer_verify');

    if (empty($_SESSION['transfer']) || ($_SESSION['transfer']['stage'] ?? '') !== 'verify') {
        $errors[] = 'Transfer failed because the verification session expired. Please start a new transfer.';
        $step = 'done';
        unset($_SESSION['transfer']);
    } else {
        $otp = trim($_POST['otp'] ?? '');
        $transfer = $_SESSION['transfer'];
        $otpExpiresAt = (int) ($transfer['created_at'] ?? 0) + 60;

        if ($otpExpiresAt <= time()) {
            $errors[] = 'Transfer failed because the OTP expired after 1 minute. Please start a new transfer.';
            $failedTransfer = buildTransferFailureReceipt($transfer, $errors[0]);
            $step = 'done';
            unset($_SESSION['transfer']);
        } elseif (!preg_match('/^\d{6}$/', $otp)) {
            $errors[] = 'OTP must contain exactly 6 digits.';
            $step = 'verify';
        } else {
            $stmt = $db->prepare("
                SELECT id
                FROM otp_codes
                WHERE user_id = ?
                  AND otp_code = ?
                  AND purpose = 'transfer_verification'
                  AND used = 0
                  AND expires_at > NOW()
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$user['id'], $otp]);
            $otpRow = $stmt->fetch();

            if (!$otpRow) {
                $_SESSION['transfer']['otp_attempts'] = (int) ($_SESSION['transfer']['otp_attempts'] ?? 0) + 1;
                if ($_SESSION['transfer']['otp_attempts'] >= 3) {
                    $errors[] = 'Transfer failed because the OTP was entered incorrectly too many times. Please start a new transfer.';
                    $failedTransfer = buildTransferFailureReceipt($transfer, $errors[0]);
                    $step = 'done';
                    unset($_SESSION['transfer']);
                } else {
                    $errors[] = 'OTP is incorrect. Please try again before it expires.';
                    $step = 'verify';
                }
            } else {
                $stmt = $db->prepare("SELECT id, full_name, email, phone_number, balance, status FROM users WHERE id = ?");
                $stmt->execute([$transfer['recipient_id']]);
                $recipient = $stmt->fetch();

                if (!$recipient || $recipient['status'] !== 'verified') {
                    $errors[] = 'Transfer failed because the recipient account is no longer available.';
                    $failedTransfer = buildTransferFailureReceipt($transfer, $errors[0]);
                    $step = 'done';
                    unset($_SESSION['transfer']);
                } else {
                    $db->beginTransaction();
                    try {
                        $db->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$otpRow['id']]);

                        if ((int) $transfer['amount'] > 5000000) {
                            $code = generateTransferTransactionCode($db, 'TFO');
                            $senderDebit = $transfer['fee_payer'] === 'sender'
                                ? (int) $transfer['amount'] + (int) $transfer['fee']
                                : (int) $transfer['amount'];
                            $stmt = $db->prepare("
                                INSERT INTO transactions (
                                    transaction_code, user_id, type, amount, fee, total_amount,
                                    status, fee_payer, related_user_id, note, balance_after
                                ) VALUES (?, ?, 'transfer_out', ?, ?, ?, 'pending', ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $code,
                                $user['id'],
                                $transfer['amount'],
                                $transfer['fee'],
                                $senderDebit,
                                $transfer['fee_payer'],
                                $recipient['id'],
                                $transfer['note'],
                                $user['balance'],
                            ]);
                            $pendingId = (int) $db->lastInsertId();
                            $db->commit();

                            logActivity('transfer_pending_approval', [
                                'target_user_id' => $user['id'],
                                'target_email' => $user['email'],
                                'entity_type' => 'transaction',
                                'entity_id' => $pendingId,
                                'details' => ['recipient_id' => $recipient['id'], 'amount' => $transfer['amount']],
                            ]);

                            $pendingApproval = [
                                'transaction_code' => $code,
                                'recipient_name' => $recipient['full_name'],
                                'recipient_phone' => $recipient['phone_number'],
                                'amount' => (int) $transfer['amount'],
                                'fee' => (int) $transfer['fee'],
                                'total_amount' => $senderDebit,
                                'fee_payer' => $transfer['fee_payer'],
                                'note' => $transfer['note'],
                            ];
                        } else {
                            $result = completeTransfer($db, $user, $recipient, (int) $transfer['amount'], $transfer['fee_payer'], $transfer['note']);
                            $db->commit();

                            sendTransferReceivedEmail(
                                $recipient['email'],
                                $recipient['full_name'],
                                $user['full_name'],
                                $result['recipient_credit'],
                                $result['recipient_balance_after'],
                                $transfer['note']
                            );

                            logActivity('transfer_completed', [
                                'target_user_id' => $user['id'],
                                'target_email' => $user['email'],
                                'entity_type' => 'transaction',
                                'entity_id' => $result['sender_transaction_id'],
                                'details' => [
                                    'recipient_id' => $recipient['id'],
                                    'amount' => $transfer['amount'],
                                    'fee' => $result['fee'],
                                ],
                            ]);

                            $success = $result + [
                                'recipient_name' => $recipient['full_name'],
                                'recipient_phone' => $recipient['phone_number'],
                                'amount' => (int) $transfer['amount'],
                                'fee_payer' => $transfer['fee_payer'],
                                'note' => $transfer['note'],
                            ];
                            $user['balance'] = $result['sender_balance_after'];
                        }

                        unset($_SESSION['transfer']);
                        $step = 'done';
                    } catch (Throwable $e) {
                        $db->rollBack();
                        error_log('[transfer] failed: ' . $e->getMessage());
                        $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Transfer could not be completed. Please try again.';
                        $failedTransfer = buildTransferFailureReceipt($transfer, $errors[0]);
                        $step = 'done';
                        unset($_SESSION['transfer']);
                    }
                }
            }
        }
    }
}

$activeTransfer = $_SESSION['transfer'] ?? null;
if (
    $activeTransfer
    && ($activeTransfer['stage'] ?? '') === 'verify'
    && ((int) ($activeTransfer['created_at'] ?? 0) + 60) <= time()
) {
    $errors[] = 'Transfer failed because the OTP expired after 1 minute. Please start a new transfer.';
    $failedTransfer = buildTransferFailureReceipt($activeTransfer, $errors[0]);
    unset($_SESSION['transfer']);
    $activeTransfer = null;
    $step = 'done';
}
$hasRetryableVerifyError = !empty($errors) && $step === 'verify' && $activeTransfer;
$hasFinalFailure = !empty($errors)
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && !$hasRetryableVerifyError;
$pageTitle = 'Transfer';
$pageStyles = ['dashboard.css', 'admin.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-hud">
    <div class="dashboard-shell">
        <div class="dashboard-greeting">
            <div class="eyebrow">Transfer Console</div>
            <h1>Transfer Money</h1>
            <p>Balance: <strong class="sn-readout"><?= formatMoney($user['balance']) ?></strong></p>
        </div>

        <?php if ($info): ?>
            <div class="alert alert-info dashboard-alert mb-4"><i class="bi bi-info-circle"></i> <?= sanitize($info) ?></div>
        <?php endif; ?>
        <?php if ($hasRetryableVerifyError): ?>
            <div class="alert alert-danger dashboard-alert mb-4">
                <?php foreach ($errors as $error): ?>
                    <div><i class="bi bi-exclamation-triangle"></i> <?= sanitize($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="hud-card sn-card mb-4">
                <div class="admin-panel-header">
                    <h5><i class="bi bi-check-circle"></i> Transfer Successful</h5>
                    <span class="admin-chip is-verified">Completed</span>
                </div>
                <div class="admin-panel-body">
                    <dl class="admin-kv">
                        <dt>Transaction Code</dt>
                        <dd class="mono"><?= sanitize($success['sender_code']) ?></dd>
                        <dt>Recipient</dt>
                        <dd><?= sanitize($success['recipient_name']) ?> <span class="small muted mono"><?= sanitize($success['recipient_phone']) ?></span></dd>
                        <dt>Amount</dt>
                        <dd class="mono"><?= formatMoney($success['amount']) ?></dd>
                        <dt>Fee</dt>
                        <dd class="mono"><?= formatMoney($success['fee']) ?> paid by <?= sanitize($success['fee_payer']) ?></dd>
                        <dt>Total Deducted</dt>
                        <dd class="mono"><?= formatMoney($success['sender_debit']) ?></dd>
                        <dt>Balance After</dt>
                        <dd class="mono"><?= formatMoney($success['sender_balance_after']) ?></dd>
                        <dt>Note</dt>
                        <dd><?= $success['note'] !== '' ? nl2br(sanitize($success['note'])) : '-' ?></dd>
                    </dl>
                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-4">
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">
                            <i class="bi bi-house"></i> Back to Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>/transfer.php?restart=1" class="btn btn-primary">
                            <i class="bi bi-send"></i> Transfer Again
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($pendingApproval): ?>
            <div class="hud-card sn-card mb-4">
                <div class="admin-panel-header">
                    <h5><i class="bi bi-hourglass-split"></i> Transfer Waiting for Approval</h5>
                    <span class="admin-chip is-pending">Pending</span>
                </div>
                <div class="admin-panel-body">
                    <dl class="admin-kv">
                        <dt>Transaction Code</dt>
                        <dd class="mono"><?= sanitize($pendingApproval['transaction_code']) ?></dd>
                        <dt>Recipient</dt>
                        <dd><?= sanitize($pendingApproval['recipient_name']) ?> <span class="small muted mono"><?= sanitize($pendingApproval['recipient_phone']) ?></span></dd>
                        <dt>Amount</dt>
                        <dd class="mono"><?= formatMoney($pendingApproval['amount']) ?></dd>
                        <dt>Fee</dt>
                        <dd class="mono"><?= formatMoney($pendingApproval['fee']) ?> paid by <?= sanitize($pendingApproval['fee_payer']) ?></dd>
                        <dt>Total When Approved</dt>
                        <dd class="mono"><?= formatMoney($pendingApproval['total_amount']) ?></dd>
                        <dt>Note</dt>
                        <dd><?= $pendingApproval['note'] !== '' ? nl2br(sanitize($pendingApproval['note'])) : '-' ?></dd>
                    </dl>
                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-4">
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">
                            <i class="bi bi-house"></i> Back to Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>/transfer.php?restart=1" class="btn btn-primary">
                            <i class="bi bi-send"></i> Transfer Again
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($hasFinalFailure): ?>
            <div class="hud-card sn-card mb-4">
                <div class="admin-panel-header">
                    <h5><i class="bi bi-x-circle"></i> Transfer Failed</h5>
                    <span class="admin-chip is-disabled">Failed</span>
                </div>
                <div class="admin-panel-body">
                    <?php foreach ($errors as $error): ?>
                        <div class="mb-2"><i class="bi bi-exclamation-triangle"></i> <?= sanitize($error) ?></div>
                    <?php endforeach; ?>
                    <?php if ($failedTransfer): ?>
                        <dl class="admin-kv mt-4">
                            <dt>Recipient</dt>
                            <dd>
                                <?= sanitize($failedTransfer['recipient_name'] ?: '-') ?>
                                <?php if ($failedTransfer['recipient_phone'] !== ''): ?>
                                    <span class="small muted mono"><?= sanitize($failedTransfer['recipient_phone']) ?></span>
                                <?php endif; ?>
                            </dd>
                            <dt>Amount</dt>
                            <dd class="mono"><?= formatMoney($failedTransfer['amount']) ?></dd>
                            <dt>Fee</dt>
                            <dd class="mono"><?= formatMoney($failedTransfer['fee']) ?> paid by <?= sanitize($failedTransfer['fee_payer']) ?></dd>
                            <dt>Total</dt>
                            <dd class="mono"><?= formatMoney($failedTransfer['total_amount']) ?></dd>
                            <dt>Note</dt>
                            <dd><?= $failedTransfer['note'] !== '' ? nl2br(sanitize($failedTransfer['note'])) : '-' ?></dd>
                        </dl>
                    <?php endif; ?>
                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-4">
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">
                            <i class="bi bi-house"></i> Back to Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>/transfer.php?restart=1" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Try Transfer Again
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$success && !$pendingApproval && !$hasFinalFailure): ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="hud-card sn-card">
                    <?php if ($step === 'verify' && $activeTransfer): ?>
                        <h5 class="mb-3">Confirm Transfer</h5>
                        <dl class="admin-kv">
                            <dt>Recipient</dt>
                            <dd><?= sanitize($activeTransfer['recipient_name']) ?> - <?= sanitize($activeTransfer['recipient_phone']) ?></dd>
                            <dt>Amount</dt>
                            <dd><?= formatMoney($activeTransfer['amount']) ?></dd>
                            <dt>Fee</dt>
                            <dd><?= formatMoney($activeTransfer['fee']) ?> paid by <?= sanitize($activeTransfer['fee_payer']) ?></dd>
                            <dt>Note</dt>
                            <dd><?= sanitize($activeTransfer['note']) ?></dd>
                        </dl>
                        <div class="alert alert-info dashboard-alert py-2"
                             id="otpTimer"
                             data-expires-at="<?= (int) $activeTransfer['created_at'] + 60 ?>">
                            <i class="bi bi-clock"></i>
                            OTP expires in <strong id="otpSeconds">60</strong> seconds.
                        </div>
                        <div class="alert alert-danger dashboard-alert py-2 d-none" id="otpExpiredNotice">
                            <i class="bi bi-x-circle"></i>
                            Transfer failed because the OTP expired after 1 minute. Please start a new transfer.
                        </div>
                        <form method="POST" class="mt-4" novalidate>
                            <?= csrfField() ?>
                            <?= idempotencyField('transfer_verify') ?>
                            <input type="hidden" name="step" value="verify">
                            <label for="otp" class="form-label">OTP Code</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                                <input type="text" class="form-control" id="otp" name="otp" inputmode="numeric" maxlength="6" required autofocus>
                            </div>
                            <div class="d-flex justify-content-between gap-2 mt-4">
                                <a href="<?= BASE_URL ?>/transfer.php?restart=1" class="btn btn-outline-secondary sn-btn-ghost">Cancel</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Confirm</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="POST" novalidate>
                            <?= csrfField() ?>
                            <?= idempotencyField('transfer_prepare') ?>
                            <input type="hidden" name="step" value="prepare">
                            <div class="mb-3">
                                <label for="recipient_phone" class="form-label">Recipient Phone Number</label>
                                <input type="text" class="form-control" id="recipient_phone" name="recipient_phone"
                                       value="<?= sanitize($recipientPhone) ?>" placeholder="0901234567" required>
                                <div id="recipientLookup" class="mt-2 small" aria-live="polite"></div>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">VND</span>
                                    <input type="text" class="form-control" id="amount" name="amount"
                                           inputmode="numeric" data-money-input
                                           value="<?= sanitize($amountInput) ?>" placeholder="1,000,000" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Transfer Fee</label>
                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="fee_payer" id="fee_sender" value="sender"
                                            <?= $feePayer === 'sender' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="fee_sender">Sender pays 5%</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="fee_payer" id="fee_receiver" value="receiver"
                                            <?= $feePayer === 'receiver' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="fee_receiver">Receiver pays 5%</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="note" class="form-label">Note</label>
                                <textarea class="form-control" id="note" name="note" rows="4" maxlength="255" required><?= sanitize($note) ?></textarea>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost">Back</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Send OTP</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <aside class="activity-panel sn-card">
                    <div class="activity-header">
                        <div class="panel-label">Rules</div>
                        <p>Transfers above 5,000,000 VND are submitted for admin approval after OTP verification.</p>
                        <div class="sn-sonar-line mt-3"></div>
                    </div>
                    <div class="activity-empty">
                        Fee is 5% of transfer amount. Recipient information is shown before OTP confirmation.
                    </div>
                </aside>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const recipientPhoneInput = document.getElementById('recipient_phone');
const recipientLookup = document.getElementById('recipientLookup');
const otpTimer = document.getElementById('otpTimer');
const otpSeconds = document.getElementById('otpSeconds');
const otpExpiredNotice = document.getElementById('otpExpiredNotice');
let recipientLookupTimer = null;
let recipientLookupController = null;

function setRecipientLookup(state, message) {
    if (!recipientLookup) return;
    const classes = {
        idle: 'text-muted',
        loading: 'text-muted',
        ok: 'text-success',
        error: 'text-warning'
    };
    recipientLookup.className = `mt-2 small ${classes[state] || 'text-muted'}`;
    recipientLookup.textContent = message || '';
}

function lookupRecipient(phone) {
    const normalized = phone.trim();
    if (normalized.length === 0) {
        setRecipientLookup('idle', '');
        return;
    }
    if (normalized.length < 6) {
        setRecipientLookup('idle', 'Enter the recipient phone number to search.');
        return;
    }

    if (recipientLookupController) {
        recipientLookupController.abort();
    }
    recipientLookupController = new AbortController();
    setRecipientLookup('loading', 'Searching recipient...');

    fetch(`<?= BASE_URL ?>/transfer.php?lookup_recipient=1&phone=${encodeURIComponent(normalized)}`, {
        headers: { 'Accept': 'application/json' },
        signal: recipientLookupController.signal
    })
        .then(response => response.ok ? response.json() : Promise.reject(new Error('Lookup failed')))
        .then(data => {
            if (data.ok) {
                setRecipientLookup('ok', `${data.name} - ${data.phone}`);
            } else if (data.name) {
                setRecipientLookup('error', `${data.name} - ${data.message}`);
            } else {
                setRecipientLookup('error', data.message || 'Recipient account was not found.');
            }
        })
        .catch(error => {
            if (error.name !== 'AbortError') {
                setRecipientLookup('error', 'Could not search recipient right now.');
            }
        });
}

if (recipientPhoneInput && recipientLookup) {
    recipientPhoneInput.addEventListener('input', () => {
        clearTimeout(recipientLookupTimer);
        recipientLookupTimer = setTimeout(() => lookupRecipient(recipientPhoneInput.value), 300);
    });
    if (recipientPhoneInput.value.trim() !== '') {
        lookupRecipient(recipientPhoneInput.value);
    }
}

if (otpTimer && otpSeconds) {
    const expiresAt = Number(otpTimer.dataset.expiresAt || 0);
    const otpInput = document.getElementById('otp');
    const otpSubmit = otpInput?.closest('form')?.querySelector('button[type="submit"]');

    const updateOtpTimer = () => {
        const remaining = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
        otpSeconds.textContent = String(remaining);

        if (remaining <= 0) {
            otpTimer.classList.add('d-none');
            otpExpiredNotice?.classList.remove('d-none');
            if (otpInput) {
                otpInput.disabled = true;
            }
            if (otpSubmit) {
                otpSubmit.disabled = true;
                otpSubmit.classList.add('is-submit-locked');
            }
            return false;
        }
        return true;
    };

    if (updateOtpTimer()) {
        const timer = setInterval(() => {
            if (!updateOtpTimer()) {
                clearInterval(timer);
            }
        }, 1000);
    }
}
</script>

<script src="<?= BASE_URL ?>/assets/js/money-format.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
