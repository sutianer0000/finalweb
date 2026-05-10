<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
requireAdmin();

$stats = getAdminNotificationStats();
$sentList = getAdminNotificationList(50);

$totalMessages = $stats['total_messages'];
$broadcastCount = $stats['broadcast_count'];
$directCount = $stats['direct_count'];
$totalRecipients = $stats['total_recipients'];
$totalRead = $stats['total_read'];

$pageTitle = 'Notification Manager';
$pageStyles = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">
<div class="admin-heading">
    <div>
        <h3><i class="bi bi-megaphone"></i> Notification Manager</h3>
        <p>Review sent messages, read progress, and launch new announcements.</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/notifications_create.php" class="btn btn-primary">
        <i class="bi bi-send"></i> New Notification
    </a>
</div>

<div class="admin-stat-grid mb-4">
    <div class="admin-stat-card">
        <div class="admin-stat-label">Messages Sent</div>
        <div class="admin-stat-value"><?= $totalMessages ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Broadcasts</div>
        <div class="admin-stat-value"><?= $broadcastCount ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Direct Messages</div>
        <div class="admin-stat-value"><?= $directCount ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Read Progress</div>
        <div class="admin-stat-value"><?= $totalRead ?>/<?= $totalRecipients ?></div>
    </div>
</div>

<div class="admin-panel sn-card">
    <div class="admin-panel-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Message History</h5>
            <small class="muted">Notification type, audience, and delivery progress.</small>
        </div>
        <small class="muted">Showing latest <?= count($sentList) ?> of <?= $totalMessages ?> total</small>
    </div>
    <div class="admin-panel-body p-0">
        <?php if (empty($sentList)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-inbox muted" style="font-size: 2.5rem;"></i>
                <p class="muted mt-3 mb-3">No notifications have been sent yet.</p>
                <a href="<?= BASE_URL ?>/admin/notifications_create.php" class="btn btn-outline-primary">
                    <i class="bi bi-send"></i> Send Your First Notification
                </a>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="table table-hover admin-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 30%">Message</th>
                            <th>Type</th>
                            <th>Audience</th>
                            <th class="text-center">Read</th>
                            <th>Sent</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sentList as $row): ?>
                            <?php $typeMeta = getNotificationTypeMeta($row['notification_type']); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= sanitize($row['title']) ?></div>
                                    <div class="small muted mt-1" style="max-width: 320px;">
                                        <?= sanitize(mb_substr($row['message'], 0, 120)) ?><?= mb_strlen($row['message']) > 120 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= sanitize($typeMeta['badge']) ?>">
                                        <i class="bi <?= sanitize($typeMeta['icon']) ?>"></i> <?= sanitize($typeMeta['label']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= sanitize(getNotificationAudienceLabel($row['audience_scope'], $row['audience_key'], $row['recipient_name'])) ?></div>
                                    <div class="small muted"><?= (int) $row['total_recipients'] ?> recipient(s)</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $row['read_count'] == $row['total_recipients'] ? 'success' : 'secondary' ?>">
                                        <?= (int) $row['read_count'] ?>/<?= (int) $row['total_recipients'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small muted"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></div>
                                    <div class="small muted">by <?= sanitize($row['sender_name']) ?></div>
                                </td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>/admin/notification_detail.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
