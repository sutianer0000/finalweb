<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mailer.php';

function getNotificationTypeOptions() {
    return [
        'general' => ['label' => 'General', 'badge' => 'secondary', 'icon' => 'bi-chat-square-text'],
        'account_update' => ['label' => 'Account Update', 'badge' => 'primary', 'icon' => 'bi-person-check'],
        'warning' => ['label' => 'Warning', 'badge' => 'warning', 'icon' => 'bi-exclamation-triangle'],
        'security' => ['label' => 'Security', 'badge' => 'danger', 'icon' => 'bi-shield-lock'],
    ];
}

function getNotificationTypeMeta($type) {
    $types = getNotificationTypeOptions();
    return $types[$type] ?? $types['general'];
}

function getNotificationAudienceGroupOptions() {
    return [
        'verified' => 'Verified users',
        'pending' => 'Pending users',
        'waiting_for_updates' => 'Users waiting for updates',
        'disabled' => 'Disabled users',
    ];
}

function getNotificationAudienceLabel($scope, $key = null, $recipientName = null) {
    if ($scope === 'all') {
        return 'All active users';
    }

    if ($scope === 'group') {
        $groups = getNotificationAudienceGroupOptions();
        return $groups[$key] ?? 'User group';
    }

    return $recipientName ?: 'Direct user';
}

function resolveNotificationAudienceSelection($selection) {
    $db = getDB();
    $selection = trim((string) $selection);

    if ($selection === 'all') {
        $stmt = $db->query("SELECT id FROM users WHERE role = 'user' AND status != 'disabled' ORDER BY id ASC");
        return [
            'user_ids' => array_map('intval', array_column($stmt->fetchAll(), 'id')),
            'scope' => 'all',
            'key' => 'active_users',
            'label' => 'All active users',
        ];
    }

    if (strpos($selection, 'group:') === 0) {
        $groupKey = substr($selection, 6);
        $allowedGroups = getNotificationAudienceGroupOptions();
        if (!isset($allowedGroups[$groupKey])) {
            return ['user_ids' => [], 'scope' => 'group', 'key' => $groupKey, 'label' => 'Unknown group'];
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE role = 'user' AND status = ? ORDER BY id ASC");
        $stmt->execute([$groupKey]);

        return [
            'user_ids' => array_map('intval', array_column($stmt->fetchAll(), 'id')),
            'scope' => 'group',
            'key' => $groupKey,
            'label' => $allowedGroups[$groupKey],
        ];
    }

    if (strpos($selection, 'user:') === 0) {
        $uid = (int) substr($selection, 5);
        $stmt = $db->prepare("
            SELECT id, full_name
            FROM users
            WHERE id = ? AND role = 'user'
        ");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        return [
            'user_ids' => $row ? [(int) $row['id']] : [],
            'scope' => 'user',
            'key' => $row ? (string) $row['id'] : null,
            'label' => $row ? $row['full_name'] : 'Direct user',
        ];
    }

    return ['user_ids' => [], 'scope' => 'user', 'key' => null, 'label' => 'Unknown audience'];
}

function sendNotification($senderId, array $userIds, $title, $message, $sendEmail = true, $type = 'general', $audienceScope = 'user', $audienceKey = null) {
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if (empty($userIds)) {
        return ['count' => 0, 'emailed' => 0, 'email_errors' => []];
    }

    $typeMeta = getNotificationTypeMeta($type);
    $type = array_key_exists($type, getNotificationTypeOptions()) ? $type : 'general';

    $db = getDB();
    $isBroadcast = count($userIds) > 1 || $audienceScope !== 'user';
    $broadcastKey = $isBroadcast ? bin2hex(random_bytes(8)) : null;

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (
                user_id, sender_id, title, message, notification_type,
                audience_scope, audience_key, is_broadcast, broadcast_key
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($userIds as $uid) {
            $stmt->execute([
                $uid,
                $senderId,
                $title,
                $message,
                $type,
                $audienceScope,
                $audienceKey,
                $isBroadcast ? 1 : 0,
                $broadcastKey,
            ]);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    $emailed = 0;
    $errors = [];
    if ($sendEmail) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $db->prepare("SELECT email, full_name FROM users WHERE id IN ($placeholders)");
        $stmt->execute($userIds);
        $recipients = $stmt->fetchAll();

        foreach ($recipients as $r) {
            $result = sendNotificationEmail($r['email'], $r['full_name'], $title, $message, $typeMeta['label']);
            if ($result['ok']) {
                $emailed++;
            } else {
                $errors[] = $r['email'] . ': ' . $result['error'];
            }
        }
    }

    return ['count' => count($userIds), 'emailed' => $emailed, 'email_errors' => $errors];
}

function getUnreadNotificationCount($userId) {
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function getUserNotifications($userId, $limit = null, $onlyUnread = false) {
    $sql = "
        SELECT
            n.id,
            n.title,
            n.message,
            n.notification_type,
            n.audience_scope,
            n.audience_key,
            n.is_read,
            n.created_at,
            n.read_at,
            n.is_broadcast,
            u.full_name AS sender_name
        FROM notifications n
        JOIN users u ON u.id = n.sender_id
        WHERE n.user_id = ?
    ";

    $params = [$userId];
    if ($onlyUnread) {
        $sql .= " AND n.is_read = 0";
    }

    $sql .= " ORDER BY n.created_at DESC";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int) $limit;
    }

    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getUserNotificationById($notificationId, $userId) {
    $stmt = getDB()->prepare("
        SELECT
            n.id,
            n.title,
            n.message,
            n.notification_type,
            n.audience_scope,
            n.audience_key,
            n.is_read,
            n.created_at,
            n.read_at,
            n.is_broadcast,
            u.full_name AS sender_name
        FROM notifications n
        JOIN users u ON u.id = n.sender_id
        WHERE n.id = ? AND n.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $notificationId, (int) $userId]);
    return $stmt->fetch();
}

function markAllNotificationsRead($userId) {
    $stmt = getDB()->prepare("
        UPDATE notifications
        SET is_read = 1, read_at = NOW()
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
}

function markNotificationRead($notificationId, $userId) {
    $stmt = getDB()->prepare("
        UPDATE notifications
        SET is_read = 1, read_at = NOW()
        WHERE id = ? AND user_id = ? AND is_read = 0
    ");
    $stmt->execute([(int) $notificationId, (int) $userId]);
}

function getAdminNotificationList() {
    $db = getDB();

    $broadcasts = $db->query("
        SELECT
            MIN(n.id) AS id,
            n.broadcast_key,
            MAX(n.title) AS title,
            MAX(n.message) AS message,
            MAX(n.notification_type) AS notification_type,
            MAX(n.audience_scope) AS audience_scope,
            MAX(n.audience_key) AS audience_key,
            COUNT(*) AS total_recipients,
            SUM(n.is_read) AS read_count,
            MIN(n.created_at) AS created_at,
            1 AS is_broadcast,
            NULL AS recipient_name,
            MAX(sender.full_name) AS sender_name
        FROM notifications n
        JOIN users sender ON sender.id = n.sender_id
        WHERE n.is_broadcast = 1
        GROUP BY n.broadcast_key
    ")->fetchAll();

    $directs = $db->query("
        SELECT
            n.id,
            NULL AS broadcast_key,
            n.title,
            n.message,
            n.notification_type,
            n.audience_scope,
            n.audience_key,
            1 AS total_recipients,
            n.is_read AS read_count,
            n.created_at,
            0 AS is_broadcast,
            recipient.full_name AS recipient_name,
            sender.full_name AS sender_name
        FROM notifications n
        JOIN users recipient ON recipient.id = n.user_id
        JOIN users sender ON sender.id = n.sender_id
        WHERE n.is_broadcast = 0
    ")->fetchAll();

    $combined = array_merge($broadcasts, $directs);
    usort($combined, function ($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });

    return $combined;
}

function getAdminNotificationDetail($notificationId) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, broadcast_key, is_broadcast
        FROM notifications
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $notificationId]);
    $seed = $stmt->fetch();
    if (!$seed) {
        return null;
    }

    if (!empty($seed['is_broadcast']) && !empty($seed['broadcast_key'])) {
        $conditionSql = 'n.broadcast_key = ?';
        $conditionParams = [$seed['broadcast_key']];
    } else {
        $conditionSql = 'n.id = ?';
        $conditionParams = [(int) $seed['id']];
    }

    $overviewStmt = $db->prepare("
        SELECT
            MIN(n.id) AS id,
            MAX(n.title) AS title,
            MAX(n.message) AS message,
            MAX(n.notification_type) AS notification_type,
            MAX(n.audience_scope) AS audience_scope,
            MAX(n.audience_key) AS audience_key,
            COUNT(*) AS total_recipients,
            SUM(n.is_read) AS read_count,
            MIN(n.created_at) AS created_at,
            MAX(sender.full_name) AS sender_name,
            MAX(sender.email) AS sender_email,
            MAX(n.broadcast_key) AS broadcast_key,
            MAX(n.is_broadcast) AS is_broadcast
        FROM notifications n
        JOIN users sender ON sender.id = n.sender_id
        WHERE {$conditionSql}
    ");
    $overviewStmt->execute($conditionParams);
    $overview = $overviewStmt->fetch();

    $recipientStmt = $db->prepare("
        SELECT
            n.id,
            recipient.full_name,
            recipient.email,
            recipient.phone_number,
            recipient.status,
            n.is_read,
            n.read_at,
            n.created_at
        FROM notifications n
        JOIN users recipient ON recipient.id = n.user_id
        WHERE {$conditionSql}
        ORDER BY n.is_read ASC, recipient.full_name ASC
    ");
    $recipientStmt->execute($conditionParams);

    return [
        'overview' => $overview,
        'recipients' => $recipientStmt->fetchAll(),
    ];
}

function formatNotificationRelativeTime($datetime) {
    $time = strtotime($datetime);
    if (!$time) {
        return '';
    }

    $diff = time() - $time;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' hr ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    }
    return date('d/m/Y H:i', $time);
}

function sendNotificationEmail($toEmail, $toName, $title, $message, $typeLabel = 'General') {
    return sendMail(
        $toEmail,
        $toName,
        '[E-Wallet] ' . $title,
        buildNotificationEmailBody($toName, $title, $message, $typeLabel),
        buildNotificationEmailAltBody($toName, $title, $message, $typeLabel)
    );
}

function buildNotificationEmailBody($name, $title, $message, $typeLabel) {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeType = htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $baseUrl = getenv('BASE_URL') ?: '';
    $portalLabel = $baseUrl !== '' ? 'Open E-Wallet' : 'Sign in to E-Wallet';
    $portalHref = $baseUrl !== '' ? $baseUrl . '/login.php' : '#';
    $portalStyle = $baseUrl !== '' ? '' : 'pointer-events:none;opacity:0.75;';

    return <<<HTML
        <div style="margin:0;padding:24px;background-color:#f3f7fb;font-family:Segoe UI,Arial,sans-serif;color:#1f2937;">
            <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #dbe5ef;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
                <div style="padding:24px 32px;background:linear-gradient(135deg,#0b1e3f 0%,#184e77 55%,#2a9d8f 100%);color:#ffffff;">
                    <div style="font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.88;margin-bottom:10px;">E-Wallet Administration</div>
                    <h1 style="margin:0;font-size:24px;line-height:1.3;font-weight:700;">You have a new notification</h1>
                    <p style="margin:10px 0 0 0;font-size:14px;line-height:1.6;opacity:0.92;">A message has been posted to your E-Wallet account. Please review the details below.</p>
                </div>
                <div style="padding:32px;">
                    <p style="margin:0 0 18px 0;font-size:15px;line-height:1.7;">Hello <strong>{$safeName}</strong>,</p>
                    <div style="margin-bottom:24px;padding:20px 22px;border:1px solid #dbe5ef;border-radius:14px;background:#f8fbfd;">
                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;margin-bottom:8px;">Notification Type</div>
                        <div style="display:inline-block;padding:6px 12px;border-radius:999px;background:#e8f1fb;color:#0b1e3f;font-size:13px;font-weight:600;">{$safeType}</div>
                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;margin:18px 0 8px;">Subject</div>
                        <div style="font-size:22px;line-height:1.4;font-weight:700;color:#0f172a;">{$safeTitle}</div>
                    </div>
                    <div style="font-size:15px;line-height:1.8;color:#334155;">{$safeMessage}</div>
                    <div style="margin-top:28px;padding:18px 20px;border-left:4px solid #2a9d8f;background:#f3fbfa;border-radius:10px;">
                        <p style="margin:0;font-size:14px;line-height:1.7;color:#0f172a;">
                            For the latest status and your full message history, please check the Notifications section inside your E-Wallet account.
                        </p>
                    </div>
                    <div style="margin-top:30px;">
                        <a href="{$portalHref}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#0b1e3f;color:#ffffff;text-decoration:none;font-weight:600;{$portalStyle}">{$portalLabel}</a>
                    </div>
                </div>
                <div style="padding:18px 32px;border-top:1px solid #e5edf5;background:#fbfdff;">
                    <p style="margin:0;font-size:12px;line-height:1.7;color:#64748b;">
                        This is an automated service message from E-Wallet. If you were not expecting this email, you can ignore it.
                    </p>
                </div>
            </div>
        </div>
    HTML;
}

function buildNotificationEmailAltBody($name, $title, $message, $typeLabel) {
    return "E-Wallet Notification\n\n"
        . "Hello {$name},\n\n"
        . "You have a new {$typeLabel} notification from the E-Wallet administration team.\n\n"
        . "Subject: {$title}\n\n"
        . "{$message}\n\n"
        . "Please sign in to your E-Wallet account and open Notifications for the latest status and message history.\n";
}
