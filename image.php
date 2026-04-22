<?php
// Stream an ID card image from the users table.
// Access: the owner themselves, or any admin. Everyone else gets 403.

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$side = $_GET['side'] ?? '';

if ($targetUserId <= 0 || !in_array($side, ['front', 'back'], true)) {
    http_response_code(400);
    exit;
}

$me = getCurrentUser();
$isOwner = $me && (int) $me['id'] === $targetUserId;
$isAdmin = $me && $me['role'] === 'admin';

if (!$isOwner && !$isAdmin) {
    http_response_code(403);
    exit;
}

$dataCol = $side === 'front' ? 'id_card_front_data' : 'id_card_back_data';
$mimeCol = $side === 'front' ? 'id_card_front_mime' : 'id_card_back_mime';

$db = getDB();
$stmt = $db->prepare("SELECT $dataCol AS data, $mimeCol AS mime FROM users WHERE id = ?");
$stmt->execute([$targetUserId]);
$row = $stmt->fetch();

if (!$row || $row['data'] === null) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . ($row['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . strlen($row['data']));
header('Cache-Control: private, max-age=300');
echo $row['data'];
