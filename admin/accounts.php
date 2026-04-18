<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

$allowedStatuses = ['pending', 'verified', 'waiting_for_updates', 'disabled'];
$statusFilter = $_GET['status'] ?? 'pending';
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'pending';
}

$stmt = $db->prepare("
    SELECT id, phone_number, email, full_name, status, created_at
    FROM users
    WHERE role = 'user' AND status = ?
    ORDER BY created_at DESC
");
$stmt->execute([$statusFilter]);
$accounts = $stmt->fetchAll();

$statusLabels = [
    'pending'             => ['label' => 'Pending Verification', 'color' => 'warning'],
    'verified'            => ['label' => 'Verified',             'color' => 'success'],
    'waiting_for_updates' => ['label' => 'Waiting for Updates',  'color' => 'info'],
    'disabled'            => ['label' => 'Disabled',             'color' => 'secondary'],
];

$pageTitle = 'Accounts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi bi-people"></i> Accounts</h3>
    <a href="/finalweb/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- Status filter tabs -->
<ul class="nav nav-pills mb-3">
    <?php foreach ($allowedStatuses as $s):
        $info = $statusLabels[$s];
        $active = $statusFilter === $s ? 'active' : '';
    ?>
        <li class="nav-item">
            <a class="nav-link <?= $active ?>" href="/finalweb/admin/accounts.php?status=<?= $s ?>">
                <?= $info['label'] ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="card-body">
        <?php if (empty($accounts)): ?>
            <p class="text-muted mb-0 fst-italic">No accounts with status "<?= sanitize($statusLabels[$statusFilter]['label']) ?>".</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $a):
                            $info = $statusLabels[$a['status']];
                        ?>
                            <tr>
                                <td>#<?= (int)$a['id'] ?></td>
                                <td class="fw-semibold"><?= sanitize($a['full_name']) ?></td>
                                <td><?= sanitize($a['phone_number']) ?></td>
                                <td><?= sanitize($a['email']) ?></td>
                                <td><span class="badge bg-<?= $info['color'] ?>"><?= $info['label'] ?></span></td>
                                <td><?= sanitize(date('d/m/Y H:i', strtotime($a['created_at']))) ?></td>
                                <td class="text-end">
                                    <a href="/finalweb/admin/account_detail.php?id=<?= (int)$a['id'] ?>"
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
