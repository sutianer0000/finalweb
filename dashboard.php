<?php
require_once __DIR__ . '/includes/auth.php';
requirePasswordChanged();

$user = getCurrentUser();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <h3 class="mb-4">Welcome, <?= sanitize($user['full_name']) ?>!</h3>

        <!-- Balance Card -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <h6 class="text-muted">Account Balance</h6>
                <h2 class="text-primary fw-bold"><?= formatMoney($user['balance']) ?></h2>
                <span class="badge bg-<?= $user['status'] === 'verified' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                    <?= ucfirst($user['status']) ?>
                </span>
            </div>
        </div>

        <?php if ($user['status'] !== 'verified'): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                Your account is not yet verified. Most features are only available for verified accounts.
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row g-3">
            <?php
            $actions = [
                ['Deposit', 'bi-plus-circle', 'deposit.php', 'success'],
                ['Withdraw', 'bi-dash-circle', 'withdraw.php', 'danger'],
                ['Transfer', 'bi-arrow-left-right', 'transfer.php', 'primary'],
                ['Phone Card', 'bi-phone', 'phone_card.php', 'info'],
                ['History', 'bi-clock-history', 'transactions.php', 'secondary'],
                ['Profile', 'bi-person', 'profile.php', 'dark'],
            ];
            foreach ($actions as $action):
            ?>
            <div class="col-6 col-md-4">
                <a href="/finalweb/<?= $action[2] ?>" class="card text-decoration-none text-center p-3 h-100 
                    <?= $user['status'] !== 'verified' && !in_array($action[2], ['profile.php']) ? 'opacity-75' : '' ?>">
                    <i class="bi <?= $action[1] ?> text-<?= $action[3] ?>" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0 fw-semibold text-dark"><?= $action[0] ?></p>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
