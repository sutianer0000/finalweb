<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';
requireLogin();

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {
        markAllNotificationsRead($user['id']);
        logActivity('user_marked_all_notifications_read', [
            'target_user_id' => $user['id'],
            'target_email' => $user['email'],
            'entity_type' => 'notification',
        ]);
        setFlash('success', 'All notifications were marked as read.');
        redirect(BASE_URL . '/notifications.php' . (!empty($_GET['view']) ? '?view=' . urlencode($_GET['view']) : ''));
    }

    if ($action === 'mark_read') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            markNotificationRead($notificationId, $user['id']);
            logActivity('user_marked_notification_read', [
                'target_user_id' => $user['id'],
                'target_email' => $user['email'],
                'entity_type' => 'notification',
                'entity_id' => $notificationId,
            ]);
            setFlash('success', 'Notification marked as read.');
        }
        redirect(BASE_URL . '/notifications.php' . (!empty($_GET['view']) ? '?view=' . urlencode($_GET['view']) : ''));
    }
}

$view = ($_GET['view'] ?? 'all') === 'unread' ? 'unread' : 'all';
$onlyUnread = $view === 'unread';
$notifs = getUserNotifications($user['id'], null, $onlyUnread);
$unreadCount = getUnreadNotificationCount($user['id']);

$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h3 class="mb-1"><i class="bi bi-bell"></i> Your Notifications</h3>
        <p class="text-muted mb-0">Track updates from the admin team and manage read status.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/notifications.php" class="btn btn-sm <?= $view === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
            All
        </a>
        <a href="<?= BASE_URL ?>/notifications.php?view=unread" class="btn btn-sm <?= $view === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>">
            Unread<?php if ($unreadCount > 0): ?> (<?= $unreadCount ?>)<?php endif; ?>
        </a>
        <?php if ($unreadCount > 0): ?>
            <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-check2-all"></i> Mark All Read
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($notifs)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox" style="font-size: 3rem; color: #9ca3af;"></i>
            <p class="text-muted mt-3 mb-0"><?= $view === 'unread' ? 'No unread notifications.' : 'No notifications yet.' ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($notifs as $n): ?>
            <?php $typeMeta = getNotificationTypeMeta($n['notification_type']); ?>
            <div class="col-12">
                <div class="card shadow-sm <?= !$n['is_read'] ? 'border-start border-4 border-primary' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                            <div>
                                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                    <span class="badge bg-<?= sanitize($typeMeta['badge']) ?>">
                                        <i class="bi <?= sanitize($typeMeta['icon']) ?>"></i> <?= sanitize($typeMeta['label']) ?>
                                    </span>
                                    <?php if (!$n['is_read']): ?>
                                        <span class="badge bg-primary">NEW</span>
                                    <?php endif; ?>
                                </div>
                                <h5 class="mb-1"><?= sanitize($n['title']) ?></h5>
                                <div class="small text-muted">
                                    From <strong><?= sanitize($n['sender_name']) ?></strong>
                                    <?php if ($n['is_broadcast']): ?>
                                        <span class="badge bg-info text-dark ms-1">Broadcast</span>
                                    <?php endif; ?>
                                    <span class="ms-2"><?= sanitize(formatNotificationRelativeTime($n['created_at'])) ?></span>
                                </div>
                            </div>
                            <div class="text-md-end">
                                <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
                                <?php if ($n['is_read'] && $n['read_at']): ?>
                                    <div class="small text-success">Read at <?= date('d/m/Y H:i', strtotime($n['read_at'])) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3" style="white-space: pre-wrap; line-height: 1.7;"><?= sanitize($n['message']) ?></div>

                        <?php if (!$n['is_read']): ?>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?= (int) $n['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-check2"></i> Mark Read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
