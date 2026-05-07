<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cache.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'unlock' && $userId > 0) {
        $stmt = $db->prepare("
            SELECT id, email, role, permanently_locked
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $target = $stmt->fetch();

        if (!$target || $target['role'] !== 'user') {
            setFlash('error', 'Account not found.');
        } elseif ((int) $target['permanently_locked'] !== 1) {
            setFlash('warning', 'This account is not currently permanently locked.');
        } else {
            unlockUserSecurityLock($userId);
            revokeRememberTokensForUser($userId);
            logActivity('admin_unlocked_user', [
                'target_user_id' => $userId,
                'target_email' => $target['email'],
                'entity_type' => 'user',
                'entity_id' => $userId,
                'details' => ['source' => 'accounts_queue'],
            ]);
            forgetCached('admin_dashboard_counts');
            setFlash('success', 'Account has been unlocked and abnormal login counters were reset.');
        }

        $returnStatus = $_POST['return_status'] ?? 'permanently_locked';
        $returnQuery = trim($_POST['return_query'] ?? '');
        $redirectUrl = BASE_URL . '/admin/accounts.php?status=' . urlencode($returnStatus);
        if ($returnQuery !== '') {
            $redirectUrl .= '&q=' . urlencode($returnQuery);
        }
        redirect($redirectUrl);
    }

    setFlash('error', 'Unknown action.');
    redirect(BASE_URL . '/admin/accounts.php');
}

$allowedStatuses = ['pending', 'verified', 'waiting_for_updates', 'disabled', 'permanently_locked'];
$statusFilter = $_GET['status'] ?? 'pending';
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'pending';
}

$query = trim($_GET['q'] ?? '');

$statusLabels = [
    'pending' => ['label' => 'Pending Verification', 'class' => 'is-pending'],
    'verified' => ['label' => 'Verified', 'class' => 'is-verified'],
    'waiting_for_updates' => ['label' => 'Waiting for Updates', 'class' => 'is-waiting'],
    'disabled' => ['label' => 'Disabled', 'class' => 'is-disabled'],
    'permanently_locked' => ['label' => 'Locked Accounts', 'class' => 'is-disabled'],
];

$sql = "
    SELECT id, phone_number, email, full_name, status, created_at,
           permanently_locked_at
    FROM users
    WHERE role = 'user'
";

$params = [];
if ($statusFilter === 'permanently_locked') {
    $sql .= " AND permanently_locked = 1";
} else {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

if ($query !== '') {
    $sql .= " AND (full_name LIKE ? OR phone_number LIKE ? OR email LIKE ? OR CAST(id AS CHAR) LIKE ?)";
    $term = '%' . $query . '%';
    array_push($params, $term, $term, $term, $term);
}

$sql .= $statusFilter === 'permanently_locked'
    ? " ORDER BY permanently_locked_at DESC, created_at DESC"
    : ($statusFilter === 'pending'
        ? " ORDER BY updated_at DESC, created_at DESC"
        : " ORDER BY created_at DESC");

$stmt = $db->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

$pageTitle = 'Accounts';
$pageStyles = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">
    <div class="admin-heading">
        <div>
            <h3><i class="bi bi-people"></i> Accounts</h3>
            <p>Filtered account roster for identity review and account status control.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary sn-btn-ghost btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="admin-toolbar">
        <form method="GET" class="admin-toolbar-grid">
            <div class="admin-toolbar-links">
                <?php foreach ($allowedStatuses as $s): ?>
                    <?php $info = $statusLabels[$s]; ?>
                    <a class="admin-tab <?= $statusFilter === $s ? 'is-active' : '' ?>"
                       href="<?= BASE_URL ?>/admin/accounts.php?status=<?= $s ?><?= $query !== '' ? '&q=' . urlencode($query) : '' ?>">
                        <?= sanitize($info['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
            <input type="text"
                   class="form-control sn-input"
                   name="q"
                   placeholder="Search name, phone, email, or ID"
                   value="<?= sanitize($query) ?>">
            <button type="submit" class="btn sn-btn-primary">Search</button>
        </form>
    </div>

    <div class="admin-panel sn-card">
        <div class="admin-panel-header">
            <h5><?= sanitize($statusLabels[$statusFilter]['label']) ?> Queue</h5>
        </div>
        <div class="admin-panel-body">
            <?php if (empty($accounts)): ?>
                <div class="admin-empty">
                    No accounts found for the current filter.
                </div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <div class="table-responsive">
                        <table class="table admin-table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th><?= $statusFilter === 'permanently_locked' ? 'Locked At' : 'Registered' ?></th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $a): ?>
                                    <?php
                                        $info = $statusFilter === 'permanently_locked'
                                            ? $statusLabels['permanently_locked']
                                            : $statusLabels[$a['status']];
                                    ?>
                                    <tr>
                                        <td class="mono">#<?= (int)$a['id'] ?></td>
                                        <td class="fw-semibold"><?= sanitize($a['full_name']) ?></td>
                                        <td class="mono"><?= sanitize($a['phone_number']) ?></td>
                                        <td><?= sanitize($a['email']) ?></td>
                                        <td>
                                            <span class="admin-chip sn-chip <?= $info['class'] ?> <?= $a['status'] === 'verified' ? 'sn-chip--verified' : ($a['status'] === 'pending' ? 'sn-chip--pending' : 'sn-chip--warn') ?>">
                                                <?= sanitize($info['label']) ?>
                                            </span>
                                        </td>
                                        <td class="mono muted">
                                            <?=
                                                sanitize(
                                                    date(
                                                        'd/m/Y H:i',
                                                        strtotime($statusFilter === 'permanently_locked'
                                                            ? ($a['permanently_locked_at'] ?: $a['created_at'])
                                                            : $a['created_at'])
                                                    )
                                                )
                                            ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="admin-inline-actions justify-content-end">
                                                <a href="<?= BASE_URL ?>/admin/account_detail.php?id=<?= (int)$a['id'] ?>"
                                                   class="btn btn-sm btn-outline-secondary sn-btn-ghost admin-link-btn">
                                                    View
                                                </a>
                                                <?php if ($statusFilter === 'permanently_locked'): ?>
                                                    <form method="POST" onsubmit="return confirm('Unlock this account and reset abnormal login counters?');">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="unlock">
                                                        <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
                                                        <input type="hidden" name="return_status" value="<?= sanitize($statusFilter) ?>">
                                                        <input type="hidden" name="return_query" value="<?= sanitize($query) ?>">
                                                        <button type="submit" class="btn btn-sm admin-link-btn is-unlock-btn">
                                                            Unlock
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
