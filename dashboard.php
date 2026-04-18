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

        <?php if ($user['status'] === 'pending'): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                Your account is <strong>pending verification</strong>. Most features are only available for verified accounts.
            </div>
        <?php elseif ($user['status'] === 'waiting_for_updates'): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                An admin has requested additional information.
                <a href="/finalweb/update_id_card.php" class="alert-link">Re-upload your ID card photos here</a>.
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row g-3">
            <?php
            // [label, icon, url, color, always_available]
            $actions = [
                ['Deposit',    'bi-plus-circle',      'deposit.php',      'success',   false],
                ['Withdraw',   'bi-dash-circle',      'withdraw.php',     'danger',    false],
                ['Transfer',   'bi-arrow-left-right', 'transfer.php',     'primary',   false],
                ['Phone Card', 'bi-phone',            'phone_card.php',   'info',      false],
                ['History',    'bi-clock-history',    'transactions.php', 'secondary', false],
                ['Profile',    'bi-person',           'profile.php',      'dark',      true],
            ];
            $isVerified = $user['status'] === 'verified';
            foreach ($actions as $action):
                [$label, $icon, $url, $color, $alwaysAvailable] = $action;
                $blocked = !$isVerified && !$alwaysAvailable;
            ?>
            <div class="col-6 col-md-4">
                <?php if ($blocked): ?>
                    <a href="#" class="card text-decoration-none text-center p-3 h-100 opacity-75 feature-locked">
                        <i class="bi <?= $icon ?> text-<?= $color ?>" style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0 fw-semibold text-dark"><?= $label ?></p>
                    </a>
                <?php else: ?>
                    <a href="/finalweb/<?= $url ?>" class="card text-decoration-none text-center p-3 h-100">
                        <i class="bi <?= $icon ?> text-<?= $color ?>" style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0 fw-semibold text-dark"><?= $label ?></p>
                    </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <script>
        document.querySelectorAll('.feature-locked').forEach(el => {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                alert('This feature is only available for verified accounts.');
            });
        });
        </script>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
