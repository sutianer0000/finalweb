<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
requireAdmin();

$notificationId = (int) ($_GET['id'] ?? 0);
if ($notificationId <= 0) {
    setFlash('error', 'Invalid notification ID.');
    redirect(BASE_URL . '/admin/notifications.php');
}

$detail = getAdminNotificationDetail($notificationId);
if (!$detail) {
    setFlash('error', 'Notification not found.');
    redirect(BASE_URL . '/admin/notifications.php');
}

$overview = $detail['overview'];
$recipients = $detail['recipients'];
$typeMeta = getNotificationTypeMeta($overview['notification_type']);
$audienceLabel = getNotificationAudienceLabel($overview['audience_scope'], $overview['audience_key']);

$pageTitle = 'Notification Detail';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1"><i class="bi bi-envelope-open"></i> Notification Detail</h3>
        <p class="text-muted mb-0">Full message content, delivery scope, and recipient read status.</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/notifications.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Manager
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-2">Type</div>
                <span class="badge bg-<?= sanitize($typeMeta['badge']) ?>">
                    <i class="bi <?= sanitize($typeMeta['icon']) ?>"></i> <?= sanitize($typeMeta['label']) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-2">Audience</div>
                <div class="fw-semibold"><?= sanitize($audienceLabel) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-2">Read Progress</div>
                <div class="fw-semibold"><?= (int) $overview['read_count'] ?>/<?= (int) $overview['total_recipients'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-2">Sent</div>
                <div class="fw-semibold"><?= date('d/m/Y H:i', strtotime($overview['created_at'])) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-file-text"></i> Message</h5>
            </div>
            <div class="card-body">
                <h4 class="mb-3"><?= sanitize($overview['title']) ?></h4>
                <p class="mb-3 text-muted small">Sent by <?= sanitize($overview['sender_name']) ?> to <?= sanitize($audienceLabel) ?></p>
                <div style="white-space: pre-wrap; line-height: 1.7;"><?= sanitize($overview['message']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people"></i> Recipients</h5>
                <small class="text-muted"><?= count($recipients) ?> total</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Status</th>
                                <th>Read State</th>
                                <th>Read At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recipients as $recipient): ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium"><?= sanitize($recipient['full_name']) ?></div>
                                        <div class="small text-muted"><?= sanitize($recipient['email']) ?></div>
                                        <div class="small text-muted"><?= sanitize($recipient['phone_number']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= sanitize($recipient['status']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $recipient['is_read'] ? 'success' : 'secondary' ?>">
                                            <?= $recipient['is_read'] ? 'Read' : 'Unread' ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= $recipient['read_at'] ? date('d/m/Y H:i', strtotime($recipient['read_at'])) : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
