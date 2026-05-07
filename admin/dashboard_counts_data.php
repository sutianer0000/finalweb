<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_dashboard.php';
requireAdmin();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$counts = getAdminDashboardCounts(getDB());
$alerts = getAdminDashboardAlertSummary($counts);

echo json_encode([
    'ok' => true,
    'counts' => $counts,
    'alerts' => $alerts,
]);
