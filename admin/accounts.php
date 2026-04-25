<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

$allowedStatuses = ['pending', 'verified', 'waiting_for_updates', 'disabled'];
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
];

$sql = "
    SELECT id, phone_number, email, full_name, status, created_at
    FROM users
    WHERE role = 'user' AND status = ?
";
$params = [$statusFilter];

if ($query !== '') {
    $sql .= " AND (full_name LIKE ? OR phone_number LIKE ? OR email LIKE ? OR CAST(id AS CHAR) LIKE ?)";
    $term = '%' . $query . '%';
    array_push($params, $term, $term, $term, $term);
}

$sql .= " ORDER BY created_at DESC";

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
                                    <th>Registered</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $a): ?>
                                    <?php $info = $statusLabels[$a['status']]; ?>
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
                                        <td class="mono muted"><?= sanitize(date('d/m/Y H:i', strtotime($a['created_at']))) ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>/admin/account_detail.php?id=<?= (int)$a['id'] ?>"
                                               class="btn btn-sm btn-outline-secondary sn-btn-ghost admin-link-btn">
                                                View
                                            </a>
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
