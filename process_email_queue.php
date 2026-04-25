<?php
require_once __DIR__ . '/includes/mailer.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the command line.\n";
    exit;
}

$limit = isset($argv[1]) ? (int) $argv[1] : 20;
$result = processQueuedEmails($limit);

echo "Processed: {$result['processed']}\n";
echo "Sent: {$result['sent']}\n";
echo "Failed: {$result['failed']}\n";
