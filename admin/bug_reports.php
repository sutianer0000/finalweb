<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bug_reports.php';
requireAdmin();

$admin = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    $reportId  = (int) ($_POST['report_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $devNotes  = trim($_POST['dev_notes'] ?? '');

    if ($reportId > 0 && updateBugReportStatus($reportId, $newStatus, (int) $admin['id'], $devNotes !== '' ? $devNotes : null)) {
        if (function_exists('logActivity')) {
            logActivity('admin_bug_report_status_change', [
                'entity_type' => 'bug_report',
                'entity_id'   => $reportId,
                'details'     => ['new_status' => $newStatus],
            ]);
        }
        setFlash('success', 'Bug report #' . $reportId . ' updated.');
    } else {
        setFlash('error', 'Could not update that bug report.');
    }
    redirect(BASE_URL . '/admin/bug_reports.php' . (!empty($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

$statusFilter = $_GET['status'] ?? null;
if ($statusFilter !== null && !array_key_exists($statusFilter, getBugStatusOptions())) {
    $statusFilter = null;
}

$counts  = getBugReportCounts();
$reports = getBugReports($statusFilter);

$pageTitle = 'Bug Reports';
$pageStyles = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">
    <div class="admin-heading">
        <div>
            <h3><i class="bi bi-bug"></i> Bug Reports</h3>
            <p>User-submitted issues routed straight to the dev queue. Triage and close out from here.</p>
        </div>
    </div>

    <div class="admin-stat-grid">
        <a href="<?= BASE_URL ?>/admin/bug_reports.php" class="admin-stat-card text-decoration-none">
            <div class="admin-stat-top">
                <span class="admin-stat-label">Total</span>
                <span class="admin-chip is-info"><i class="bi bi-collection"></i></span>
            </div>
            <div class="admin-stat-value"><?= (int) $counts['total'] ?></div>
        </a>
        <a href="<?= BASE_URL ?>/admin/bug_reports.php?status=open" class="admin-stat-card text-decoration-none">
            <div class="admin-stat-top">
                <span class="admin-stat-label">Open</span>
                <span class="admin-chip is-pending"><i class="bi bi-exclamation-triangle"></i></span>
            </div>
            <div class="admin-stat-value"><?= (int) $counts[BUG_STATUS_OPEN] ?></div>
        </a>
        <a href="<?= BASE_URL ?>/admin/bug_reports.php?status=triaged" class="admin-stat-card text-decoration-none">
            <div class="admin-stat-top">
                <span class="admin-stat-label">Triaged</span>
                <span class="admin-chip is-waiting"><i class="bi bi-clipboard-check"></i></span>
            </div>
            <div class="admin-stat-value"><?= (int) $counts[BUG_STATUS_TRIAGED] ?></div>
        </a>
        <a href="<?= BASE_URL ?>/admin/bug_reports.php?status=resolved" class="admin-stat-card text-decoration-none">
            <div class="admin-stat-top">
                <span class="admin-stat-label">Resolved</span>
                <span class="admin-chip is-verified"><i class="bi bi-check-circle"></i></span>
            </div>
            <div class="admin-stat-value"><?= (int) $counts[BUG_STATUS_RESOLVED] ?></div>
        </a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 admin-toolbar-links">
        <a href="<?= BASE_URL ?>/admin/bug_reports.php"             class="admin-tab <?= $statusFilter === null ? 'is-active' : '' ?>">All</a>
        <a href="<?= BASE_URL ?>/admin/bug_reports.php?status=open"     class="admin-tab <?= $statusFilter === 'open' ? 'is-active' : '' ?>">Open</a>
        <a href="<?= BASE_URL ?>/admin/bug_reports.php?status=triaged"  class="admin-tab <?= $statusFilter === 'triaged' ? 'is-active' : '' ?>">Triaged</a>
        <a href="<?= BASE_URL ?>/admin/bug_reports.php?status=resolved" class="admin-tab <?= $statusFilter === 'resolved' ? 'is-active' : '' ?>">Resolved</a>
        <a href="<?= BASE_URL ?>/admin/bug_reports.php?status=wont_fix" class="admin-tab <?= $statusFilter === 'wont_fix' ? 'is-active' : '' ?>">Won't fix</a>
    </div>

    <?php if (empty($reports)): ?>
        <div class="admin-panel sn-card">
            <div class="admin-panel-body text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 2.5rem;"></i>
                <p class="text-muted mt-3 mb-0">
                    <?= $statusFilter ? 'No bug reports in this state.' : 'No bug reports yet — clear queue.' ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($reports as $r): ?>
            <?php $meta = getBugStatusMeta($r['status']); ?>
            <div class="admin-panel sn-card mb-3">
                <div class="admin-panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span class="badge bg-<?= sanitize($meta['badge']) ?>"><?= sanitize($meta['label']) ?></span>
                        <strong class="ms-2">#<?= (int) $r['id'] ?> · <?= sanitize($r['title']) ?></strong>
                    </div>
                    <small class="text-muted"><?= sanitize(date('d/m/Y H:i', strtotime($r['created_at']))) ?></small>
                </div>
                <div class="admin-panel-body">
                    <dl class="admin-kv mb-3">
                        <dt>Reporter</dt>
                        <dd>
                            <?php if ($r['user_id']): ?>
                                <?= sanitize($r['reporter_name'] ?: ('User #' . $r['user_id'])) ?>
                                <span class="text-muted">&lt;<?= sanitize($r['user_email'] ?: 'no email') ?>&gt;</span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">anonymous</span>
                            <?php endif; ?>
                        </dd>
                        <dt>Page</dt>
                        <dd class="mono" style="word-break: break-all;"><?= sanitize($r['page_url'] ?: '—') ?></dd>
                        <dt>Browser</dt>
                        <dd class="small text-muted" style="word-break: break-all;"><?= sanitize($r['user_agent'] ?: '—') ?></dd>
                        <?php if ($r['resolved_at']): ?>
                        <dt>Closed</dt>
                        <dd><?= sanitize(date('d/m/Y H:i', strtotime($r['resolved_at']))) ?> by <?= sanitize($r['resolver_name'] ?: 'admin') ?></dd>
                        <?php endif; ?>
                    </dl>

                    <div class="mb-3">
                        <label class="text-muted small mb-1 d-block">Description</label>
                        <div class="p-3 rounded" style="background: rgba(255,255,255,0.04); white-space: pre-wrap; line-height: 1.55;"><?= sanitize($r['description']) ?></div>
                    </div>

                    <form method="POST" class="row g-2 align-items-end">
                        <?= csrfField() ?>
                        <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <?php foreach (getBugStatusOptions() as $key => $opt): ?>
                                    <option value="<?= sanitize($key) ?>" <?= $r['status'] === $key ? 'selected' : '' ?>>
                                        <?= sanitize($opt['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-muted">Dev notes (optional)</label>
                            <input type="text" name="dev_notes" class="form-control form-control-sm"
                                   maxlength="500"
                                   placeholder="Triage note, root cause, related ticket…"
                                   value="<?= sanitize($r['dev_notes'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 btn-sm">
                                <i class="bi bi-save"></i> Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
