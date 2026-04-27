<?php
// Bug-reports helper — user-submitted issues + dev triage queue.
// Storage: bug_reports table. Optional email relay to BUG_REPORT_EMAIL
// (falls back to MAIL_FROM if not configured).

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mailer.php';

const BUG_STATUS_OPEN     = 'open';
const BUG_STATUS_TRIAGED  = 'triaged';
const BUG_STATUS_RESOLVED = 'resolved';
const BUG_STATUS_WONTFIX  = 'wont_fix';

function getBugStatusOptions(): array {
    return [
        BUG_STATUS_OPEN     => ['label' => 'Open',      'badge' => 'warning'],
        BUG_STATUS_TRIAGED  => ['label' => 'Triaged',   'badge' => 'info'],
        BUG_STATUS_RESOLVED => ['label' => 'Resolved',  'badge' => 'success'],
        BUG_STATUS_WONTFIX  => ["label" => "Won't fix", 'badge' => 'secondary'],
    ];
}

function getBugStatusMeta(string $status): array {
    $opts = getBugStatusOptions();
    return $opts[$status] ?? ['label' => ucfirst($status), 'badge' => 'secondary'];
}

/**
 * Persist a new bug report and (best-effort) email the dev mailbox.
 *
 * @return array ['id' => int, 'emailed' => bool, 'email_error' => ?string]
 */
function submitBugReport(?int $userId, ?string $userEmail, string $title, string $description, ?string $pageUrl, ?string $userAgent): array {
    $title       = trim($title);
    $description = trim($description);
    $pageUrl     = $pageUrl    !== null ? mb_substr(trim($pageUrl),    0, 500) : null;
    $userAgent   = $userAgent  !== null ? mb_substr(trim($userAgent),  0, 500) : null;
    $userEmail   = $userEmail  !== null ? mb_substr(trim($userEmail),  0, 255) : null;

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO bug_reports
            (user_id, user_email, title, description, page_url, user_agent, status)
        VALUES (?, ?, ?, ?, ?, ?, '" . BUG_STATUS_OPEN . "')
    ");
    $stmt->execute([$userId, $userEmail, $title, $description, $pageUrl, $userAgent]);
    $newId = (int) $db->lastInsertId();

    $emailResult = sendBugReportEmailToDevs($newId, $userId, $userEmail, $title, $description, $pageUrl, $userAgent);

    return [
        'id'          => $newId,
        'emailed'     => $emailResult['ok'],
        'email_error' => $emailResult['error'] ?? null,
    ];
}

/**
 * Email the dev mailbox. Failures are non-fatal — the report is already
 * stored, so the worst case is "dev didn't get a ping".
 */
function sendBugReportEmailToDevs(int $reportId, ?int $userId, ?string $userEmail, string $title, string $description, ?string $pageUrl, ?string $userAgent): array {
    $devTo = getenv('BUG_REPORT_EMAIL');
    if (!$devTo) {
        // Fallback to MAIL_FROM so reports land somewhere reviewable.
        $devTo = defined('MAIL_FROM') ? MAIL_FROM : (getenv('MAIL_FROM') ?: '');
    }
    if (!$devTo) {
        return ['ok' => false, 'error' => 'no dev email configured'];
    }

    $reporterLabel = $userId
        ? '#' . $userId . ' (' . ($userEmail ?: 'no email on file') . ')'
        : ($userEmail ?: 'anonymous');

    $safeTitle    = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeDesc     = nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8'));
    $safeReporter = htmlspecialchars($reporterLabel, ENT_QUOTES, 'UTF-8');
    $safePage     = htmlspecialchars($pageUrl ?: '—', ENT_QUOTES, 'UTF-8');
    $safeAgent    = htmlspecialchars($userAgent ?: '—', ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 620px; margin: 0 auto;">
            <div style="background: #c14a30; color: #fff; padding: 16px 20px; border-radius: 8px 8px 0 0;">
                <strong>New bug report #{$reportId}</strong>
            </div>
            <div style="border: 1px solid #e5e7eb; border-top: none; padding: 20px; border-radius: 0 0 8px 8px;">
                <h2 style="color: #0B1E3F; margin: 0 0 12px 0;">{$safeTitle}</h2>
                <div style="color: #333; line-height: 1.55; margin-bottom: 18px;">{$safeDesc}</div>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; color: #333;">
                    <tr><td style="padding: 6px 8px; background: #f4f6fa;"><strong>Reporter</strong></td><td style="padding: 6px 8px;">{$safeReporter}</td></tr>
                    <tr><td style="padding: 6px 8px; background: #f4f6fa;"><strong>Page</strong></td><td style="padding: 6px 8px; word-break: break-all;">{$safePage}</td></tr>
                    <tr><td style="padding: 6px 8px; background: #f4f6fa;"><strong>User agent</strong></td><td style="padding: 6px 8px; word-break: break-all;">{$safeAgent}</td></tr>
                </table>
                <p style="margin-top: 16px; color: #6b7280; font-size: 12px;">
                    Triage at <code>/admin/bug_reports.php</code>.
                </p>
            </div>
        </div>
    HTML;

    return sendMail($devTo, 'E-Wallet Dev', '[E-Wallet bug] ' . $title, $html);
}

/**
 * Admin queue listing.
 *
 * @param string|null $statusFilter one of the BUG_STATUS_* constants, or null for all
 */
function getBugReports(?string $statusFilter = null, int $limit = 100): array {
    $db = getDB();
    $sql = "
        SELECT
            br.id, br.user_id, br.user_email, br.title, br.description,
            br.page_url, br.user_agent, br.status, br.dev_notes,
            br.resolved_at, br.created_at, br.updated_at,
            reporter.full_name AS reporter_name,
            resolver.full_name AS resolver_name
        FROM bug_reports br
        LEFT JOIN users reporter ON reporter.id = br.user_id
        LEFT JOIN users resolver ON resolver.id = br.resolved_by
    ";
    $params = [];
    if ($statusFilter !== null) {
        $sql .= " WHERE br.status = ?";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY br.created_at DESC LIMIT " . max(1, min(500, $limit));

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getBugReportCounts(): array {
    $rows = getDB()->query("
        SELECT status, COUNT(*) AS n
        FROM bug_reports
        GROUP BY status
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $counts = ['total' => 0];
    foreach (getBugStatusOptions() as $key => $_) {
        $counts[$key] = (int) ($rows[$key] ?? 0);
        $counts['total'] += $counts[$key];
    }
    return $counts;
}

function updateBugReportStatus(int $reportId, string $newStatus, int $adminId, ?string $devNotes = null): bool {
    if (!array_key_exists($newStatus, getBugStatusOptions())) {
        return false;
    }
    $db = getDB();

    $resolvedClause = '';
    $params = [$newStatus];

    if ($devNotes !== null) {
        $resolvedClause .= ', dev_notes = ?';
        $params[] = $devNotes;
    }

    if (in_array($newStatus, [BUG_STATUS_RESOLVED, BUG_STATUS_WONTFIX], true)) {
        $resolvedClause .= ', resolved_at = NOW(), resolved_by = ?';
        $params[] = $adminId;
    } else {
        $resolvedClause .= ', resolved_at = NULL, resolved_by = NULL';
    }

    $params[] = $reportId;

    $stmt = $db->prepare("UPDATE bug_reports SET status = ? {$resolvedClause} WHERE id = ?");
    return $stmt->execute($params);
}
