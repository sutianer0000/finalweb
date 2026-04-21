<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

$pendingCount  = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
$verifiedCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'verified' AND role = 'user'")->fetchColumn();
$waitingCount  = $db->query("SELECT COUNT(*) FROM users WHERE status = 'waiting_for_updates'")->fetchColumn();
$disabledCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'disabled'")->fetchColumn();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4"><i class="bi bi-speedometer2"></i> Admin Dashboard</h3>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <a href="<?= BASE_URL ?>/admin/accounts.php?status=pending" class="card text-decoration-none h-100 border-warning">
            <div class="card-body text-center">
                <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem;"></i>
                <h6 class="text-muted mt-2">Pending Verification</h6>
                <h3 class="fw-bold"><?= (int)$pendingCount ?></h3>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="<?= BASE_URL ?>/admin/accounts.php?status=verified" class="card text-decoration-none h-100 border-success">
            <div class="card-body text-center">
                <i class="bi bi-patch-check-fill text-success" style="font-size: 2rem;"></i>
                <h6 class="text-muted mt-2">Verified</h6>
                <h3 class="fw-bold"><?= (int)$verifiedCount ?></h3>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="<?= BASE_URL ?>/admin/accounts.php?status=waiting_for_updates" class="card text-decoration-none h-100 border-info">
            <div class="card-body text-center">
                <i class="bi bi-pencil-square text-info" style="font-size: 2rem;"></i>
                <h6 class="text-muted mt-2">Waiting for Updates</h6>
                <h3 class="fw-bold"><?= (int)$waitingCount ?></h3>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="<?= BASE_URL ?>/admin/accounts.php?status=disabled" class="card text-decoration-none h-100 border-secondary">
            <div class="card-body text-center">
                <i class="bi bi-slash-circle text-secondary" style="font-size: 2rem;"></i>
                <h6 class="text-muted mt-2">Disabled</h6>
                <h3 class="fw-bold"><?= (int)$disabledCount ?></h3>
            </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Quick Links</h5>
        <a href="<?= BASE_URL ?>/admin/accounts.php?status=pending" class="btn btn-warning">
            <i class="bi bi-hourglass-split"></i> Review Pending Accounts
        </a>
        <a href="<?= BASE_URL ?>/admin/accounts.php" class="btn btn-outline-secondary">
            <i class="bi bi-people"></i> All Accounts
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
