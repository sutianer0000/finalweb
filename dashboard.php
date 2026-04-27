<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
requirePasswordChanged();

$user = getCurrentUser();
$db = getDB();
$isVerified = $user['status'] === 'verified';

$pageTitle = __('dashboard_title');
$pageStyles = ['dashboard.css'];
require_once __DIR__ . '/includes/header.php';

$activityStmt = $db->prepare("
    SELECT created_at, type, amount, fee, total_amount, status, fee_payer
    FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 6
");
$activityStmt->execute([$user['id']]);
$recentActivities = $activityStmt->fetchAll();

$actions = [
    ['Deposit', 'bi-plus-square', 'deposit.php', false],
    ['Withdraw', 'bi-dash-square', 'withdraw.php', false],
    ['Transfer', 'bi-arrow-left-right', 'transfer.php', false],
    ['Phone Card', 'bi-phone', 'phone_card.php', false],
];

function dashboardTypeLabel($type) {
    $labels = [
        'deposit' => __('deposit'),
        'withdraw' => __('withdraw'),
        'transfer_out' => __('transfer_out'),
        'transfer_in' => __('transfer_in'),
        'phone_card' => __('phone_card'),
    ];

    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function dashboardTypeClass($type) {
    $classes = [
        'deposit' => 'is-deposit',
        'withdraw' => 'is-withdraw',
        'transfer_out' => 'is-transfer',
        'transfer_in' => 'is-receive',
        'phone_card' => 'is-phone-card',
    ];

    return $classes[$type] ?? 'is-neutral';
}

function dashboardSignedAmount($type, $amount) {
    $negativeTypes = ['withdraw', 'transfer_out', 'phone_card'];
    $sign = in_array($type, $negativeTypes, true) ? '-' : '+';
    return $sign . formatMoney((float)$amount);
}

function dashboardStatusLabel($status) {
    $labels = [
        'verified' => __('verified'),
        'pending' => __('pending_verification'),
        'waiting_for_updates' => __('waiting_for_updates'),
        'disabled' => __('disabled'),
    ];

    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
}
?>



<div class="dashboard-hud">
    <div class="dashboard-shell">
        <div class="dashboard-greeting">
            <div class="eyebrow"><?= __('command_deck') ?></div>
            <h1><?= sanitize(sprintf(__('welcome_user'), $user['full_name'])) ?></h1>
            <p><?= __('dashboard_intro') ?></p>
        </div>

        <?php if ($user['status'] === 'pending'): ?>
            <div class="alert alert-warning dashboard-alert mb-4">
                <i class="bi bi-exclamation-triangle"></i>
                <?= __('dashboard_pending_notice') ?>
            </div>
        <?php elseif ($user['status'] === 'waiting_for_updates'): ?>
            <div class="alert alert-info dashboard-alert mb-4">
                <i class="bi bi-info-circle"></i>
                <?= __('dashboard_update_notice') ?>
                <a href="<?= BASE_URL ?>/update_id_card.php" class="alert-link"><?= __('reupload_id_here') ?></a>.
            </div>
        <?php endif; ?>

        <div class="row g-4 align-items-stretch">
            <div class="col-lg-8">
                <div class="hud-card sn-card hud-corners">
                    <div class="corners-inner"></div>
                    <div class="hud-main-grid">
                        <div class="hud-balance-pane">
                            <div class="balance-label"><?= __('account_balance') ?></div>
                            <p class="balance-value sn-readout"><?= formatMoney($user['balance']) ?></p>

                            <div class="balance-meta">
                                <span class="status-pill sn-chip <?= $user['status'] === 'verified' ? 'sn-chip--verified' : ($user['status'] === 'pending' ? 'sn-chip--pending' : 'sn-chip--warn') ?> is-<?= sanitize($user['status']) ?>">
                                    <?= strtoupper(dashboardStatusLabel($user['status'])) ?>
                                </span>
                                <a href="<?= BASE_URL ?>/profile.php" class="mini-link"><?= __('profile') ?></a>
                                <a href="<?= BASE_URL ?>/change_password.php" class="mini-link"><?= __('security') ?></a>
                            </div>
                        </div>

                        <div class="hud-actions-pane">
                            <div class="balance-label"><?= __('quick_actions') ?></div>
                            <div class="action-grid">
                                <?php foreach ($actions as $action): ?>
                                    <?php
                                    [$label, $icon, $url, $alwaysAvailable] = $action;
                                    $blocked = !$isVerified && !$alwaysAvailable;
                                    $translationKey = match ($label) {
                                        'Deposit' => 'deposit',
                                        'Withdraw' => 'withdraw',
                                        'Transfer' => 'transfer',
                                        'Phone Card' => 'phone_card',
                                        default => $label,
                                    };
                                    ?>
                                    <?php if ($blocked): ?>
                                        <a href="#"
                                           class="action-tile sn-tile feature-locked is-locked"
                                           aria-disabled="true">
                                            <span class="locked-chip"><?= __('locked') ?></span>
                                            <i class="bi <?= $icon ?>"></i>
                                            <span><?= sanitize(__($translationKey)) ?></span>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/<?= $url ?>" class="action-tile sn-tile">
                                            <i class="bi <?= $icon ?>"></i>
                                            <span><?= sanitize(__($translationKey)) ?></span>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <aside class="activity-panel sn-card">
                    <div class="activity-header">
                        <div class="panel-label"><?= __('recent_activity') ?></div>
                        <p><?= __('sonar_log') ?></p>
                        <div class="sn-sonar-line mt-3"></div>
                    </div>

                    <?php if ($recentActivities): ?>
                        <ul class="activity-list">
                            <?php foreach ($recentActivities as $activity): ?>
                                <?php
                                $isNegative = in_array($activity['type'], ['withdraw', 'transfer_out', 'phone_card'], true);
                                $fee = (float)($activity['fee'] ?? 0);
                                // Only show fee on transactions where the user actually paid it.
                                // For transfer_in: receiver only paid the fee if fee_payer = 'receiver'.
                                $userPaidFee = $fee > 0 && (
                                    $activity['type'] !== 'transfer_in'
                                    || ($activity['fee_payer'] ?? null) === 'receiver'
                                );
                                ?>
                                <li class="activity-row">
                                    <span class="activity-time">
                                        <?= date('H:i', strtotime($activity['created_at'])) ?>
                                    </span>
                                    <span class="activity-type <?= dashboardTypeClass($activity['type']) ?>">
                                        <?= sanitize(dashboardTypeLabel($activity['type'])) ?>
                                    </span>
                                    <div class="activity-amount-cell">
                                        <span class="activity-amount <?= $isNegative ? 'is-negative' : 'is-positive' ?>">
                                            <?= dashboardSignedAmount($activity['type'], $activity['amount']) ?>
                                        </span>
                                        <?php if ($userPaidFee): ?>
                                            <span class="activity-fee">
                                                <?= __('fee') ?>: <?= formatMoney($fee) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="activity-empty">
                            <?= __('no_activity') ?>
                        </div>
                    <?php endif; ?>

                    <div class="px-4 pt-3">
                        <a href="<?= BASE_URL ?>/profile.php" class="mini-link"><?= __('account_details') ?></a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>

<div class="language-switcher-fixed">
    <div class="btn-group btn-group-sm" role="group">
        <a href="?lang=vi" class="btn <?= $lang === 'vi' ? 'btn-primary' : 'btn-outline-primary' ?>">
            VI
        </a>
        <a href="?lang=en" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-primary' ?>">
            EN
        </a>
    </div>
</div>

<script>
document.querySelectorAll('.feature-locked').forEach(el => {
    el.addEventListener('click', function (e) {
        e.preventDefault();
        alert(<?= json_encode(__('feature_verified_only')) ?>);
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
