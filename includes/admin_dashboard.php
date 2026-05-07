<?php

function getAdminDashboardCounts(PDO $db): array
{
    $userRows = $db->query("
        SELECT status, COUNT(*) AS n
        FROM users
        WHERE role = 'user'
        GROUP BY status
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'pending' => (int) ($userRows['pending'] ?? 0),
        'verified' => (int) ($userRows['verified'] ?? 0),
        'waiting' => (int) ($userRows['waiting_for_updates'] ?? 0),
        'disabled' => (int) ($userRows['disabled'] ?? 0),
        'locked' => (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND permanently_locked = 1")->fetchColumn(),
        'pending_txn' => (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn(),
    ];
}

function getAdminDashboardAlertSummary(array $counts): array
{
    $pendingAccounts = (int) ($counts['pending'] ?? 0);
    $waitingAccounts = (int) ($counts['waiting'] ?? 0);
    $lockedAccounts = (int) ($counts['locked'] ?? 0);
    $pendingTransactions = (int) ($counts['pending_txn'] ?? 0);

    $accountAlerts = $pendingAccounts + $waitingAccounts + $lockedAccounts;
    $totalAlerts = $accountAlerts + $pendingTransactions;

    return [
        'accounts' => $accountAlerts,
        'transactions' => $pendingTransactions,
        'total' => $totalAlerts,
    ];
}
