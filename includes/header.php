<?php
if (!isset($pageTitle)) $pageTitle = 'E-Wallet';
$pageStyles = $pageStyles ?? [];
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$flash = getFlash();

// Notifications header is lazy-loaded via JS after first paint — see the
// fetch in the bottom of this file. Keeps initial HTML render off the
// notifications query path entirely.
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
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/bug_reports.php"><i class="bi bi-bug"></i> Bug Reports</a></li>
                            <?php if ($currentUser['role'] === 'superadmin'): ?>
                                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/superadmin.php"><i class="bi bi-shield-lock"></i> Super Admin</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                    <ul class="navbar-nav">
                        <?php if ($currentUser['role'] === 'user'): ?>
                            <!-- Notification bell: shell only. Hydrated by JS via /notifications_data.php
                                 after first paint, so this nav item costs zero DB queries on render. -->
                            <li class="nav-item dropdown" data-notif-bell>
                                <a class="nav-link dropdown-toggle position-relative" href="#" role="button"
                                   data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                                    <i class="bi bi-bell" data-notif-icon></i>
                                    <span class="position-absolute top-25 start-75 translate-middle badge rounded-pill bg-danger d-none"
                                          style="font-size: 0.65rem;" data-notif-badge>0</span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end p-0 overflow-hidden" style="min-width: 340px;">
                                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
                                        <strong>Notifications</strong>
                                        <a href="<?= BASE_URL ?>/notifications.php?view=unread" class="small text-decoration-none">Unread</a>
                                    </div>
                                    <div data-notif-list>
                                        <div class="px-3 py-4 text-center text-muted small" data-notif-loading>
                                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading...
                                        </div>
                                    </div>
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
                        <?php if ($currentUser['role'] === 'user'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= BASE_URL ?>/report_bug.php" title="Report a bug">
                                    <i class="bi bi-bug"></i> Report Bug
                                </a>
                            </li>
                        <?php endif; ?>
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

    <?php if ($currentUser && $currentUser['role'] === 'user'): ?>
    <script>
    // Lazy-hydrate the notification bell after first paint. Server endpoint
    // reuses the existing 45s session snapshot so this is cheap to call.
    (function () {
        var bell = document.querySelector('[data-notif-bell]');
        if (!bell) return;

        var badge = bell.querySelector('[data-notif-badge]');
        var icon  = bell.querySelector('[data-notif-icon]');
        var list  = bell.querySelector('[data-notif-list]');
        var endpoint = '<?= BASE_URL ?>/notifications_data.php';
        var notifPage = '<?= BASE_URL ?>/notifications.php';

        function escape(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function renderEmpty() {
            list.innerHTML = '<div class="px-3 py-4 text-center text-muted small">No notifications yet.</div>';
        }

        function renderItems(items) {
            if (!items.length) { renderEmpty(); return; }
            var html = '';
            for (var i = 0; i < items.length; i++) {
                var n = items[i];
                html +=
                    '<a href="' + notifPage + '" class="dropdown-item px-3 py-3 border-bottom">' +
                        '<div class="d-flex justify-content-between align-items-start gap-2">' +
                            '<div class="flex-grow-1">' +
                                '<div class="mb-1"><span class="badge bg-' + escape(n.badge) + '">' + escape(n.type_label) + '</span></div>' +
                                '<div class="fw-semibold text-wrap">' + escape(n.title) +
                                    (!n.is_read ? ' <span class="badge bg-primary ms-1">NEW</span>' : '') +
                                '</div>' +
                                '<div class="small text-muted text-wrap">' + escape(n.message) + '</div>' +
                            '</div>' +
                            '<small class="text-muted text-nowrap">' + escape(n.time_ago) + '</small>' +
                        '</div>' +
                    '</a>';
            }
            list.innerHTML = html;
        }

        function applyCount(n) {
            if (n > 0) {
                badge.textContent = n > 99 ? '99+' : String(n);
                badge.classList.remove('d-none');
                icon.classList.remove('bi-bell');
                icon.classList.add('bi-bell-fill');
            } else {
                badge.classList.add('d-none');
                icon.classList.remove('bi-bell-fill');
                icon.classList.add('bi-bell');
            }
        }

        function hydrate() {
            fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    applyCount(data.unread_count || 0);
                    renderItems(data.recent || []);
                })
                .catch(function () { /* silent — bell stays empty, page still works */ });
        }

        // Defer until idle so it never competes with paint / page-critical work.
        if ('requestIdleCallback' in window) {
            requestIdleCallback(hydrate, { timeout: 1500 });
        } else {
            setTimeout(hydrate, 250);
        }
    })();
    </script>
    <?php endif; ?>

    <div class="container mt-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
