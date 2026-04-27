<?php
if (!isset($pageTitle)) $pageTitle = 'E-Wallet';
$pageStyles = $pageStyles ?? [];
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$flash = getFlash();

// Load notification helper only for logged-in users — keeps cost off the
// login / register / public pages.
$unreadNotifCount = 0;
$recentNotifications = [];
if ($currentUser && $currentUser['role'] === 'user') {
    require_once __DIR__ . '/notifications.php';
    $notificationSnapshot = getHeaderNotificationSnapshot($currentUser['id']);
    $unreadNotifCount = $notificationSnapshot['unread_count'];
    $recentNotifications = $notificationSnapshot['recent'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - E-Wallet</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/common.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/header-footer.css" rel="stylesheet">
    <?php foreach ($pageStyles as $style): ?>
        <link href="<?= BASE_URL ?>/assets/css/<?= sanitize($style) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sn-nav">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/index.php">
                <i class="bi bi-wallet2"></i> E-Wallet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if ($currentUser): ?>
                    <ul class="navbar-nav me-auto">
                        <?php if ($currentUser['role'] === 'user'):
                            $navIsVerified = $currentUser['status'] === 'verified';
                            $lockClass = $navIsVerified ? '' : ' nav-feature-locked';
                        ?>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/profile.php">Profile</a></li>
                            <li class="nav-item"><a class="nav-link<?= $lockClass ?>" href="<?= BASE_URL ?>/deposit.php">Deposit</a></li>
                            <li class="nav-item"><a class="nav-link<?= $lockClass ?>" href="<?= BASE_URL ?>/withdraw.php">Withdraw</a></li>
                            <li class="nav-item"><a class="nav-link<?= $lockClass ?>" href="<?= BASE_URL ?>/transfer.php">Transfer</a></li>
                            <li class="nav-item"><a class="nav-link<?= $lockClass ?>" href="<?= BASE_URL ?>/phone_card.php">Phone Card</a></li>
                            <li class="nav-item"><a class="nav-link<?= $lockClass ?>" href="<?= BASE_URL ?>/transactions.php">History</a></li>
                        <?php elseif (in_array($currentUser['role'], ['admin', 'superadmin'], true)): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/accounts.php">Accounts</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/pending_transactions.php">Pending Transactions</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/notifications.php"><i class="bi bi-megaphone"></i> Notifications</a></li>
                            <?php if ($currentUser['role'] === 'superadmin'): ?>
                                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/superadmin.php"><i class="bi bi-shield-lock"></i> Super Admin</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                    <ul class="navbar-nav">
                        <?php if ($currentUser['role'] === 'user'): ?>
                            <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle position-relative" href="#" role="button"
                                       data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                                        <i class="bi bi-bell<?= $unreadNotifCount > 0 ? '-fill' : '' ?>"></i>
                                        <?php if ($unreadNotifCount > 0): ?>
                                            <span class="position-absolute top-25 start-75 translate-middle badge rounded-pill bg-danger"
                                                  style="font-size: 0.65rem;">
                                                <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end p-0 overflow-hidden" style="min-width: 340px;">
                                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
                                            <strong>Notifications</strong>
                                            <a href="<?= BASE_URL ?>/notifications.php?view=unread" class="small text-decoration-none">Unread</a>
                                        </div>
                                        <?php if (empty($recentNotifications)): ?>
                                            <div class="px-3 py-4 text-center text-muted small">
                                                No notifications yet.
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($recentNotifications as $notif): ?>
                                                <?php $notifTypeMeta = getNotificationTypeMeta($notif['notification_type']); ?>
                                                <a href="<?= BASE_URL ?>/notifications.php"
                                                   class="dropdown-item px-3 py-3 border-bottom">
                                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                                        <div class="flex-grow-1">
                                                            <div class="mb-1">
                                                                <span class="badge bg-<?= sanitize($notifTypeMeta['badge']) ?>">
                                                                    <?= sanitize($notifTypeMeta['label']) ?>
                                                                </span>
                                                            </div>
                                                            <div class="fw-semibold text-wrap">
                                                                <?= sanitize($notif['title']) ?>
                                                                <?php if (!$notif['is_read']): ?>
                                                                    <span class="badge bg-primary ms-1">NEW</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="small text-muted text-wrap">
                                                                <?= sanitize(mb_strimwidth($notif['message'], 0, 90, '...')) ?>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted text-nowrap">
                                                            <?= sanitize(formatNotificationRelativeTime($notif['created_at'])) ?>
                                                        </small>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <div class="px-3 py-2 bg-light border-top text-end">
                                            <a href="<?= BASE_URL ?>/notifications.php" class="small text-decoration-none">View all notifications</a>
                                        </div>
                                    </div>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <span class="nav-link text-light">
                                <i class="bi bi-person-circle"></i> <?= sanitize($currentUser['full_name']) ?>
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/change_password.php"><i class="bi bi-key"></i> Change Password</a>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="<?= BASE_URL ?>/logout.php" class="d-inline">
                                <?= csrfField() ?>
                                <button type="submit" class="nav-link btn btn-link border-0">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                <?php else: ?>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/register.php">Register</a></li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
