<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
requireAdmin();

$admin = getCurrentUser();
$errors = [];
$notificationTypes = getNotificationTypeOptions();
$audienceGroups = getNotificationAudienceGroupOptions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = $_POST['recipient'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $notificationType = $_POST['notification_type'] ?? 'general';
    $sendEmail = isset($_POST['send_email']);

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($message === '') {
        $errors[] = 'Message is required.';
    }
    if (mb_strlen($title) > 200) {
        $errors[] = 'Title must be 200 characters or less.';
    }
    if (!isset($notificationTypes[$notificationType])) {
        $errors[] = 'Please choose a valid notification type.';
    }

    if (empty($errors)) {
        $audience = resolveNotificationAudienceSelection($recipient);

        if (empty($audience['user_ids'])) {
            $errors[] = 'No valid recipient found.';
        } else {
            $result = sendNotification(
                $admin['id'],
                $audience['user_ids'],
                $title,
                $message,
                $sendEmail,
                $notificationType,
                $audience['scope'],
                $audience['key']
            );
            $successMsg = "Delivered to {$result['count']} user"
                . ($result['count'] === 1 ? '' : 's')
                . ($sendEmail ? " - emailed {$result['emailed']}/{$result['count']}" : ' (in-app only)')
                . '.';
            if (!empty($result['email_errors'])) {
                $successMsg .= ' Email errors: ' . count($result['email_errors']) . '.';
            }
            logActivity('admin_sent_notification', [
                'entity_type' => 'notification',
                'details' => [
                    'title' => $title,
                    'notification_type' => $notificationType,
                    'audience_scope' => $audience['scope'],
                    'audience_key' => $audience['key'],
                    'recipient_count' => $result['count'],
                    'email_requested' => $sendEmail,
                    'email_sent_count' => $result['emailed'],
                    'email_error_count' => count($result['email_errors'] ?? []),
                ],
            ]);
            setFlash('success', $successMsg);
            redirect(BASE_URL . '/admin/notifications.php');
        }
    }
}

$users = getDB()->query("
    SELECT id, full_name, email, phone_number, status
    FROM users
    WHERE role = 'user'
    ORDER BY full_name ASC
")->fetchAll();

$defaultSendEmail = $_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['send_email']);

$pageTitle = 'Send Notification';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1"><i class="bi bi-send"></i> Send Notification</h3>
        <p class="text-muted mb-0">Compose a direct message or broadcast announcement for users.</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/notifications.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Manager
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?= sanitize($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-envelope-paper"></i> New Message</h5>
            </div>
            <div class="card-body">
                <form method="POST" novalidate id="sendNotificationForm">
                    <div class="mb-3">
                        <label for="recipient" class="form-label">Recipient <span class="text-danger">*</span></label>
                        <select class="form-select" id="recipient" name="recipient" required>
                            <option value="all" <?= (($_POST['recipient'] ?? 'all') === 'all') ? 'selected' : '' ?>>
                                All users (broadcast)
                            </option>
                            <optgroup label="Recipient Groups">
                                <?php foreach ($audienceGroups as $groupKey => $groupLabel): ?>
                                    <option value="group:<?= sanitize($groupKey) ?>" <?= (($_POST['recipient'] ?? '') === 'group:' . $groupKey) ? 'selected' : '' ?>>
                                        <?= sanitize($groupLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Individual Users">
                            <?php foreach ($users as $u): ?>
                                <option value="user:<?= (int) $u['id'] ?>"
                                    <?= (($_POST['recipient'] ?? '') === 'user:' . $u['id']) ? 'selected' : '' ?>>
                                    <?= sanitize($u['full_name']) ?> - <?= sanitize($u['email']) ?>
                                    <?= $u['status'] === 'disabled' ? ' [disabled]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="notification_type" class="form-label">Notification Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="notification_type" name="notification_type" required>
                            <?php foreach ($notificationTypes as $typeKey => $typeMeta): ?>
                                <option value="<?= sanitize($typeKey) ?>" <?= (($_POST['notification_type'] ?? 'general') === $typeKey) ? 'selected' : '' ?>>
                                    <?= sanitize($typeMeta['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="title"
                               name="title"
                               maxlength="200"
                               required
                               value="<?= sanitize($_POST['title'] ?? '') ?>"
                               placeholder="e.g. Scheduled maintenance notice">
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control"
                                  id="message"
                                  name="message"
                                  rows="8"
                                  required
                                  placeholder="Write the full message. Line breaks are preserved."><?= sanitize($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <div class="form-check mb-4">
                        <input type="checkbox"
                               class="form-check-input"
                               id="send_email"
                               name="send_email"
                               <?= $defaultSendEmail ? 'checked' : '' ?>>
                        <label class="form-check-label" for="send_email">
                            Also send an email to each recipient
                        </label>
                        <div class="form-text">Broadcasts to many users may take several seconds.</div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="<?= BASE_URL ?>/admin/notifications.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="sendNotificationButton">
                            <span class="default-label"><i class="bi bi-send"></i> Send Notification</span>
                            <span class="sending-label d-none"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Sending...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const sendNotificationForm = document.getElementById('sendNotificationForm');
const sendNotificationButton = document.getElementById('sendNotificationButton');

if (sendNotificationForm && sendNotificationButton) {
    sendNotificationForm.addEventListener('submit', function (event) {
        if (sendNotificationButton.disabled) {
            event.preventDefault();
            return;
        }

        sendNotificationButton.disabled = true;
        sendNotificationButton.querySelector('.default-label')?.classList.add('d-none');
        sendNotificationButton.querySelector('.sending-label')?.classList.remove('d-none');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
