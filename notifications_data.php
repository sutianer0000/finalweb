<?php
// Lightweight JSON endpoint for the header bell.
// Called by includes/header.php's inline script after DOM ready, so the
// initial HTML render doesn't pay for two notification queries on every
// page. Reuses the existing 45s session snapshot.

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'reason' => 'not_logged_in']);
    exit;
}

$user = getCurrentUser();
if (!$user || $user['role'] !== 'user') {
    echo json_encode(['ok' => true, 'unread_count' => 0, 'recent' => []]);
    exit;
}

$snapshot = getHeaderNotificationSnapshot((int) $user['id']);

// Trim each notification to the fields the dropdown actually renders.
$recent = [];
foreach ($snapshot['recent'] as $n) {
    $type = $n['notification_type'] ?? 'general';
    $meta = function_exists('getNotificationTypeMeta') ? getNotificationTypeMeta($type) : ['label' => $type, 'badge' => 'secondary'];
    $recent[] = [
        'id'        => (int) $n['id'],
        'title'     => (string) $n['title'],
        'message'   => mb_strimwidth((string) $n['message'], 0, 90, '...'),
        'is_read'   => (int) $n['is_read'],
        'badge'     => $meta['badge'] ?? 'secondary',
        'type_label'=> $meta['label'] ?? $type,
        'time_ago'  => function_exists('formatNotificationRelativeTime') ? formatNotificationRelativeTime($n['created_at']) : $n['created_at'],
    ];
}

echo json_encode([
    'ok'           => true,
    'unread_count' => (int) $snapshot['unread_count'],
    'recent'       => $recent,
]);
