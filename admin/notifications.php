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
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1"><i class="bi bi-megaphone"></i> Notification Manager</h3>
        <p class="text-muted mb-0">Review sent messages, read progress, and launch new announcements.</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/notifications_create.php" class="btn btn-primary">
        <i class="bi bi-send"></i> New Notification
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-2">Messages Sent</div>
                <div class="fs-3 fw-bold"><?= $totalMessages ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-2">Broadcasts</div>
                <div class="fs-3 fw-bold"><?= $broadcastCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-2">Direct Messages</div>
                <div class="fs-3 fw-bold"><?= $directCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-2">Read Progress</div>
                <div class="fs-3 fw-bold"><?= $totalRead ?>/<?= $totalRecipients ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Message History</h5>
            <small class="text-muted">Notification type, audience, and delivery progress.</small>
        </div>
        <small class="text-muted">Showing latest <?= count($sentList) ?> of <?= $totalMessages ?> total</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($sentList)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-inbox text-muted" style="font-size: 2.5rem;"></i>
                <p class="text-muted mt-3 mb-3">No notifications have been sent yet.</p>
                <a href="<?= BASE_URL ?>/admin/notifications_create.php" class="btn btn-outline-primary">
                    <i class="bi bi-send"></i> Send Your First Notification
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
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
                                    <div class="small mt-1" style="max-width: 320px; color: #e3edf2; font-weight: 500;">
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
                                    <div class="small text-muted"><?= (int) $row['total_recipients'] ?> recipient(s)</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $row['read_count'] == $row['total_recipients'] ? 'success' : 'secondary' ?>">
                                        <?= (int) $row['read_count'] ?>/<?= (int) $row['total_recipients'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></div>
                                    <div class="small text-muted">by <?= sanitize($row['sender_name']) ?></div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
