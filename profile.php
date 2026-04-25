<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
requireLogin();

$user = getCurrentUser();

$statusInfo = [
    'verified'             => ['label' => __('verified'),             'color' => 'success', 'icon' => 'bi-patch-check-fill'],
    'pending'              => ['label' => __('pending_verification'), 'color' => 'warning', 'icon' => 'bi-hourglass-split'],
    'waiting_for_updates'  => ['label' => __('waiting_for_updates'),  'color' => 'info',    'icon' => 'bi-pencil-square'],
    'disabled'             => ['label' => __('disabled'),             'color' => 'secondary','icon' => 'bi-slash-circle'],
];
$s = $statusInfo[$user['status']] ?? ['label' => ucfirst($user['status']), 'color' => 'secondary', 'icon' => 'bi-question-circle'];

$pageTitle = __('personal_information');
$pageStyles = ['profile.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-record">
    <div class="row">
        <div class="col-xl-10 col-lg-11 mx-auto">
            <div class="profile-heading">
                <div>
                    <div class="profile-eyebrow">Crew Manifest</div>
                    <h3 class="mb-0"><i class="bi bi-person-badge"></i> Crew Identification Record</h3>
                    <p class="mb-0">Filed identity data, account state, and holographic ID captures.</p>
                </div>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> <?= __('back_to_dashboard') ?>
                </a>
            </div>

            <!-- Balance + Status Card -->
            <div class="card profile-panel sn-card mb-4 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center g-3">
                        <div class="col-md-6 profile-balance-cell">
                            <h6 class="text-muted mb-1"><?= __('account_balance') ?></h6>
                            <h2 class="profile-balance-value sn-readout mb-0"><?= formatMoney($user['balance']) ?></h2>
                        </div>
                        <div class="col-md-6 text-md-end mt-3 mt-md-0">
                            <h6 class="text-muted mb-1"><?= __('account_status') ?></h6>
                            <span class="badge bg-<?= $s['color'] ?> fs-6 px-3 py-2">
                                <i class="bi <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($user['status'] === 'pending'): ?>
                <div class="alert alert-warning profile-alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?= __('profile_pending_notice') ?>
                </div>
            <?php elseif ($user['status'] === 'waiting_for_updates'): ?>
                <div class="alert alert-info profile-alert">
                    <i class="bi bi-info-circle"></i>
                    <?= __('profile_update_notice') ?>
                    <a href="<?= BASE_URL ?>/update_id_card.php" class="alert-link"><?= __('reupload_id_here') ?></a>.
                </div>
            <?php elseif ($user['status'] === 'disabled'): ?>
                <div class="alert alert-danger profile-alert">
                    <i class="bi bi-x-circle"></i>
                    <?= __('profile_disabled_notice') ?>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card profile-panel sn-card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-person-vcard"></i> <?= __('basic_information') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="text-muted small mb-1"><?= __('full_name') ?></label>
                                    <div class="fw-semibold"><?= sanitize($user['full_name']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small mb-1"><?= __('date_of_birth') ?></label>
                                    <div class="fw-semibold"><?= sanitize(date('d/m/Y', strtotime($user['date_of_birth']))) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small mb-1"><?= __('phone_number') ?></label>
                                    <div class="fw-semibold"><?= sanitize($user['phone_number']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small mb-1"><?= __('email_address') ?></label>
                                    <div class="fw-semibold"><?= sanitize($user['email']) ?></div>
                                </div>
                                <div class="col-12">
                                    <label class="text-muted small mb-1"><?= __('address') ?></label>
                                    <div class="fw-semibold"><?= sanitize($user['address']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card profile-panel sn-card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-shield-lock"></i> <?= __('account_details') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="text-muted small mb-1"><?= __('account_id') ?></label>
                                    <div class="fw-semibold">#<?= sanitize($user['id']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small mb-1"><?= __('role') ?></label>
                                    <div class="fw-semibold"><?= sanitize(__($user['role'])) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small mb-1"><?= __('member_since') ?></label>
                                    <div class="fw-semibold"><?= sanitize(date('d/m/Y H:i', strtotime($user['created_at']))) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small mb-1"><?= __('last_updated') ?></label>
                                    <div class="fw-semibold"><?= sanitize(date('d/m/Y H:i', strtotime($user['updated_at']))) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card profile-panel sn-card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-badge-hd"></i> Crew Identification Record</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($user['id_card_front_mime']) || !empty($user['id_card_back_mime'])): ?>
                                <div class="row g-3">
                                    <div class="col-md-6 col-lg-12">
                                        <label class="text-muted small mb-2 d-block"><?= __('front') ?></label>
                                        <?php if (!empty($user['id_card_front_mime'])): ?>
                                            <div class="sn-hologram">
                                                <a href="<?= BASE_URL ?>/image.php?user_id=<?= (int)$user['id'] ?>&side=front" target="_blank">
                                                    <img src="<?= BASE_URL ?>/image.php?user_id=<?= (int)$user['id'] ?>&side=front"
                                                         alt="<?= sanitize(__('id_card') . ' ' . __('front')) ?>"
                                                         class="img-fluid">
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted fst-italic"><?= __('not_uploaded') ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 col-lg-12">
                                        <label class="text-muted small mb-2 d-block"><?= __('back') ?></label>
                                        <?php if (!empty($user['id_card_back_mime'])): ?>
                                            <div class="sn-hologram">
                                                <a href="<?= BASE_URL ?>/image.php?user_id=<?= (int)$user['id'] ?>&side=back" target="_blank">
                                                    <img src="<?= BASE_URL ?>/image.php?user_id=<?= (int)$user['id'] ?>&side=back"
                                                         alt="<?= sanitize(__('id_card') . ' ' . __('back')) ?>"
                                                         class="img-fluid">
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted fst-italic"><?= __('not_uploaded') ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-muted fst-italic"><?= __('no_id_uploaded') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/change_password.php" class="btn btn-outline-primary">
                    <i class="bi bi-key"></i> <?= __('change_password') ?>
                </a>
                <a href="<?= BASE_URL ?>/transactions.php" class="btn btn-outline-secondary">
                    <i class="bi bi-clock-history"></i> <?= __('transaction_history') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="language-switcher-fixed">
    <div class="btn-group btn-group-sm" role="group">
        <a href="?lang=vi" class="btn <?= $lang === 'vi' ? 'btn-primary' : 'btn-outline-primary' ?>">
            VI
        </a>
        <a href="?lang=en" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-primary' ?>">
            EN
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
