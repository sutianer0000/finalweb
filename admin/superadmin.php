<?php
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();

$db = getDB();
$currentUser = getCurrentUser();
ensureActivityLogTable();

$roleOptions = ['user' => 'User', 'admin' => 'Admin', 'superadmin' => 'Superadmin'];
$statusOptions = [
    'pending' => 'Pending',
    'verified' => 'Verified',
    'waiting_for_updates' => 'Waiting for updates',
    'disabled' => 'Disabled',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_access') {
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$confirmPassword || !password_verify($confirmPassword, $currentUser['password'])) {
            setFlash('error', 'Incorrect password. Action cancelled.');
            $returnQuery = trim($_POST['return_q'] ?? '');
            redirect(BASE_URL . '/admin/superadmin.php' . ($returnQuery !== '' ? '?q=' . urlencode($returnQuery) : ''));
        }

        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? '';
        $newStatus = $_POST['status'] ?? '';

        if ($targetUserId <= 0 || !isset($roleOptions[$newRole]) || !isset($statusOptions[$newStatus])) {
            setFlash('error', 'Invalid account update.');
        } elseif ($targetUserId === (int) $currentUser['id'] && $newRole !== 'superadmin') {
            setFlash('error', 'You cannot remove your own superadmin access.');
        } else {
            $stmt = $db->prepare("SELECT id, email, role, status FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $target = $stmt->fetch();

            if (!$target) {
                setFlash('error', 'Account not found.');
            } else {
                $db->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?")
                    ->execute([$newRole, $newStatus, $targetUserId]);
                revokeRememberTokensForUser($targetUserId);
                if ($targetUserId === (int) $currentUser['id']) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $currentUser['id'];
                }
                logActivity('superadmin_updated_account_access', [
                    'target_user_id' => $targetUserId,
                    'target_email' => $target['email'],
                    'entity_type' => 'user',
                    'entity_id' => $targetUserId,
                    'details' => [
                        'old_role' => $target['role'],
                        'new_role' => $newRole,
                        'old_status' => $target['status'],
                        'new_status' => $newStatus,
                    ],
                ]);
                setFlash('success', 'Account access updated.');
            }
        }

        $returnQuery = trim($_POST['return_q'] ?? '');
        redirect(BASE_URL . '/admin/superadmin.php' . ($returnQuery !== '' ? '?q=' . urlencode($returnQuery) : ''));
    }
}

$query = trim($_GET['q'] ?? '');
$accountWhere = '1 = 1';
$accountParams = [];
$logWhere = '1 = 1';
$logParams = [];

if ($query !== '') {
    $like = '%' . $query . '%';
    $accountWhere = '(u.email LIKE ? OR u.full_name LIKE ? OR u.phone_number LIKE ?)';
    $accountParams = [$like, $like, $like];
    $logWhere = '(l.actor_email LIKE ? OR l.target_email LIKE ? OR actor.email LIKE ? OR target.email LIKE ?)';
    $logParams = [$like, $like, $like, $like];
}

$stmt = $db->prepare("
    SELECT u.id, u.full_name, u.email, u.phone_number, u.role, u.status, u.first_login, u.created_at, u.updated_at,
           session_summary.last_seen_at
    FROM users u
    LEFT JOIN (
        SELECT user_id,
               MAX(last_seen_at) AS last_seen_at
        FROM app_sessions
        WHERE user_id IS NOT NULL
        GROUP BY user_id
    ) session_summary ON session_summary.user_id = u.id
    WHERE {$accountWhere}
    ORDER BY FIELD(u.role, 'superadmin', 'admin', 'user'), u.created_at DESC
    LIMIT 200
");
$stmt->execute($accountParams);
$accounts = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT l.*,
           actor.full_name AS actor_name,
           target.full_name AS target_name
    FROM activity_logs l
    LEFT JOIN users actor ON actor.id = l.actor_user_id
    LEFT JOIN users target ON target.id = l.target_user_id
    WHERE {$logWhere}
    ORDER BY l.created_at DESC
    LIMIT 150
");
$stmt->execute($logParams);
$activityLogs = $stmt->fetchAll();

function formatSuperadminTime($value) {
    return $value ? date('Y-m-d H:i:s', strtotime($value)) : null;
}

function compactActivityDetails($json) {
    if (!$json) return '';
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $json = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return mb_strimwidth((string) $json, 0, 140, '...');
}

$pageTitle = 'Super Admin';
$pageStyles = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Danger confirmation modal -->
<div class="modal fade" id="dangerModal" tabindex="-1" aria-labelledby="dangerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="dangerModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Dangerous Action
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-3">
                    <strong><i class="bi bi-exclamation-octagon-fill me-1"></i> Warning:</strong>
                    This feature is intended for <strong>debugging purposes during the deployment process only</strong>.
                    Modifying account roles or statuses can break user access and affect system integrity.
                    Do not use this in production unless you fully understand the consequences.
                </div>
                <div class="mb-3">
                    <label for="modalPassword" class="form-label fw-semibold">Enter your password to confirm</label>
                    <input type="password" class="form-control" id="modalPassword" placeholder="Your current password" autocomplete="current-password">
                    <div class="invalid-feedback">Please enter your password.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="dangerConfirmBtn">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> I understand, proceed
                </button>
            </div>
        </div>
    </div>
</div>

<div class="admin-shell">
    <div class="admin-heading">
        <div>
            <h3><i class="bi bi-shield-lock"></i> Super Admin</h3>
            <p>Full account visibility, last activity tracking, and searchable activity history.</p>
        </div>
    </div>

    <div class="admin-toolbar">
        <form method="GET" class="admin-toolbar-grid">
            <div class="admin-toolbar-links">
                <span class="admin-tab is-active">All Accounts</span>
                <span class="admin-tab">Activity Logs</span>
            </div>
            <input type="search"
                   class="form-control"
                   name="q"
                   value="<?= sanitize($query) ?>"
                   placeholder="Search email, name, phone, activity">
            <button type="submit" class="btn admin-link-btn"><i class="bi bi-search"></i> Search</button>
        </form>
    </div>

    <div class="admin-panel sn-card">
        <div class="admin-panel-header">
            <h5>Accounts</h5>
        </div>
        <div class="admin-panel-body">
            <?php if (empty($accounts)): ?>
                <div class="admin-empty">No accounts matched this search.</div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="table admin-table align-middle">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>
                                    Last Activity
                                    <span class="text-muted small fw-normal d-block" style="font-size:0.7rem;">
                                        as of page load — reload to update
                                    </span>
                                </th>
                                <th>Access <span class="text-danger small">⚠</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accounts as $account): ?>
                                <?php $lastSeenIso = formatSuperadminTime($account['last_seen_at']); ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= sanitize($account['full_name']) ?></div>
                                        <div class="small muted"><?= sanitize($account['email']) ?></div>
                                        <div class="small mono"><?= sanitize($account['phone_number']) ?></div>
                                    </td>
                                    <td><span class="admin-chip is-info"><?= sanitize($roleOptions[$account['role']] ?? $account['role']) ?></span></td>
                                    <td><span class="admin-chip <?= $account['status'] === 'verified' ? 'is-verified' : ($account['status'] === 'disabled' ? 'is-disabled' : ($account['status'] === 'waiting_for_updates' ? 'is-waiting' : 'is-pending')) ?>"><?= sanitize($statusOptions[$account['status']] ?? $account['status']) ?></span></td>
                                    <td class="mono small">
                                        <?php if ($lastSeenIso): ?>
                                            <span class="last-seen-rel" data-ts="<?= $lastSeenIso ?>">
                                                <?= sanitize($lastSeenIso) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="superadmin-access-form">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="update_access">
                                            <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                            <input type="hidden" name="return_q" value="<?= sanitize($query) ?>">
                                            <input type="hidden" name="confirm_password" class="confirm-password-field" value="">
                                            <select class="form-select form-select-sm" name="role" aria-label="Role">
                                                <?php foreach ($roleOptions as $roleKey => $roleLabel): ?>
                                                    <option value="<?= sanitize($roleKey) ?>" <?= $account['role'] === $roleKey ? 'selected' : '' ?>>
                                                        <?= sanitize($roleLabel) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="form-select form-select-sm" name="status" aria-label="Status">
                                                <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                                                    <option value="<?= sanitize($statusKey) ?>" <?= $account['status'] === $statusKey ? 'selected' : '' ?>>
                                                        <?= sanitize($statusLabel) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-danger superadmin-save-btn">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Save
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-panel sn-card">
        <div class="admin-panel-header">
            <h5>Activity Logs</h5>
        </div>
        <div class="admin-panel-body">
            <?php if (empty($activityLogs)): ?>
                <div class="admin-empty">No activity logs matched this search.</div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="table admin-table align-middle">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Actor</th>
                                <th>Target</th>
                                <th>IP</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activityLogs as $log): ?>
                                <tr>
                                    <td class="mono"><?= sanitize(date('d/m/Y H:i:s', strtotime($log['created_at']))) ?></td>
                                    <td><span class="admin-chip is-info"><?= sanitize(str_replace('_', ' ', $log['action'])) ?></span></td>
                                    <td>
                                        <div><?= sanitize($log['actor_name'] ?: 'System/Public') ?></div>
                                        <div class="small muted"><?= sanitize($log['actor_email'] ?: '-') ?></div>
                                    </td>
                                    <td>
                                        <div><?= sanitize($log['target_name'] ?: '-') ?></div>
                                        <div class="small muted"><?= sanitize($log['target_email'] ?: '-') ?></div>
                                    </td>
                                    <td class="mono"><?= sanitize($log['ip_address'] ?: '-') ?></td>
                                    <td class="small"><?= sanitize(compactActivityDetails($log['details_json'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Relative time for last activity
function relativeTime(isoStr) {
    const d = new Date(isoStr.replace(' ', 'T'));
    const diffSec = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diffSec < 60)  return 'Just now';
    if (diffSec < 3600) {
        const m = Math.floor(diffSec / 60);
        return m + ' min' + (m > 1 ? 's' : '') + ' ago';
    }
    if (diffSec < 86400) {
        const h = Math.floor(diffSec / 3600);
        return h + ' hour' + (h > 1 ? 's' : '') + ' ago';
    }
    const days = Math.floor(diffSec / 86400);
    return days + ' day' + (days > 1 ? 's' : '') + ' ago';
}

document.querySelectorAll('.last-seen-rel').forEach(el => {
    const ts = el.dataset.ts;
    if (ts) el.textContent = relativeTime(ts);
});

// Danger modal + password injection
let pendingForm = null;
const dangerModal = new bootstrap.Modal(document.getElementById('dangerModal'));
const modalPwInput = document.getElementById('modalPassword');

document.querySelectorAll('.superadmin-access-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        pendingForm = this;
        modalPwInput.value = '';
        modalPwInput.classList.remove('is-invalid');
        dangerModal.show();
    });
});

document.getElementById('dangerModal').addEventListener('shown.bs.modal', () => {
    modalPwInput.focus();
});

document.getElementById('dangerConfirmBtn').addEventListener('click', function() {
    if (!pendingForm) return;
    const pw = modalPwInput.value.trim();
    if (!pw) {
        modalPwInput.classList.add('is-invalid');
        return;
    }
    pendingForm.querySelector('.confirm-password-field').value = pw;
    dangerModal.hide();
    pendingForm.submit();
});

modalPwInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') document.getElementById('dangerConfirmBtn').click();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
