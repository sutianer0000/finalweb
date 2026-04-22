<?php
// Stream an ID card image from user_id_cards.
// Access: the owner themselves, or any admin. Everyone else gets 403.
//
// Efficiency plan:
//   1. Auth check (cheap session read).
//   2. Metadata query — mime from users (exists flag) + updated_at from
//      user_id_cards. No BLOB fetched yet.
//   3. Build ETag from (user_id, side, updated_at). If the browser sends
//      If-None-Match matching, return 304 with no body. This is the common
//      case on any page re-render — zero BLOB transfer.
//   4. Only on a cache miss do we SELECT the actual blob column.

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

$db = getDB();

$mimeCol = $side === 'front' ? 'id_card_front_mime' : 'id_card_back_mime';
$dataCol = $side === 'front' ? 'front_data' : 'back_data';

// Metadata-only query — no BLOB.
$stmt = $db->prepare("
    SELECT u.$mimeCol AS mime, c.updated_at
    FROM users u
    LEFT JOIN user_id_cards c ON c.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$targetUserId]);
$meta = $stmt->fetch();

if (!$meta || empty($meta['mime']) || empty($meta['updated_at'])) {
    http_response_code(404);
    exit;
}

$etag = '"' . md5($targetUserId . ':' . $side . ':' . $meta['updated_at']) . '"';
$lastModified = gmdate('D, d M Y H:i:s', strtotime($meta['updated_at'])) . ' GMT';

$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
$ifModSince  = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
if ($ifNoneMatch === $etag || $ifModSince === $lastModified) {
    header('ETag: ' . $etag);
    header('Last-Modified: ' . $lastModified);
    header('Cache-Control: private, max-age=3600');
    http_response_code(304);
    exit;
}

// Cache miss — now fetch the actual bytes.
$stmt = $db->prepare("SELECT $dataCol AS data FROM user_id_cards WHERE user_id = ?");
$stmt->execute([$targetUserId]);
$row = $stmt->fetch();

if (!$row || $row['data'] === null) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $meta['mime']);
header('Content-Length: ' . strlen($row['data']));
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastModified);
header('Cache-Control: private, max-age=3600');
echo $row['data'];
