<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/image_util.php';
require_once __DIR__ . '/includes/lang.php';

// If already logged in, redirect
if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

$errors = [];
$success = false;
$credentials = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();

    // Collect and sanitize inputs
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // --- Validation ---
    // Phone number
    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9]{9,15}$/', $phone)) {
        $errors[] = 'Phone number must be 9-15 digits.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = 'This phone number is already registered.';
        }
    }

    // Email
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    // Full name
    if (empty($fullName)) {
        $errors[] = 'Full name is required.';
    }

    // Date of birth
    if (empty($dob)) {
        $errors[] = 'Date of birth is required.';
    }

    // Address
    if (empty($address)) {
        $errors[] = 'Address is required.';
    }

    // ID card front photo
    if (!isset($_FILES['id_card_front']) || $_FILES['id_card_front']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Front photo of ID card is required.';
    }

    // ID card back photo
    if (!isset($_FILES['id_card_back']) || $_FILES['id_card_back']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Back photo of ID card is required.';
    }

    foreach (['id_card_front', 'id_card_back'] as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            if ($_FILES[$field]['size'] > 3 * 1024 * 1024) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be less than 3MB.';
            }
        }
    }

    // --- If no errors, proceed ---
    if (empty($errors)) {
        // Resize + recompress uploads before storing (typically 10-25x smaller
        // than the raw phone photo — lighter DB, faster transfer).
        try {
            $front = processUploadedIdCardImage($_FILES['id_card_front']['tmp_name'], 'ID card front');
            $back  = processUploadedIdCardImage($_FILES['id_card_back']['tmp_name'], 'ID card back');
        } catch (RuntimeException $e) {
            $errors[] = 'Could not process ID card images: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        // Generate random 6-character password
        $randomPassword = generateRandomString(6);
        $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
        $front = array_merge($front, getPostedOriginalIdCardDimensions('id_card_front', $front));
        $back = array_merge($back, getPostedOriginalIdCardDimensions('id_card_back', $back));

        // Two-table insert in a transaction: users (skinny row, mime flags) +
        // user_id_cards (heavy BLOB row). Rolled back together on any failure.
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO users (phone_number, email, full_name, date_of_birth, address, password, id_card_front_mime, id_card_back_mime, status, first_login, role)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 1, 'user')
            ");
            $stmt->execute([
                $phone, $email, $fullName, $dob, $address, $hashedPassword, $front['mime'], $back['mime']
            ]);
            $newUserId = (int) $db->lastInsertId();

            storeUserIdCardImages($db, $newUserId, $front, $back);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Send credentials via PHPMailer
        $mailResult = sendRegistrationEmail($email, $fullName, $phone, $randomPassword);

        $success = true;
        $credentials = [
            'email' => $email,
            'phone' => $phone,
            'password' => $randomPassword,
            'email_sent' => $mailResult['ok'],
            'mail_error' => $mailResult['error'],
        ];
    }
}

$pageTitle = 'Register';
$pageStyles = ['register.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container d-flex justify-content-center">
    <div class="card sn-login-card register-card">
        <!-- HEADER -->
        <div class="card-header text-white text-center py-4">
            <i class="bi bi-person-plus fs-1 mb-2"></i>
            <h4 class="mb-0 fw-bold"><?= __("create_account") ?></h4>
        </div>

        <div class="card-body p-4">

            <?php if ($success && $credentials): ?>
                <!-- Registration Success -->
                <div class="text-center mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    <h4 class="text-success mt-2"><?= __("registration_successful") ?></h4>
                    <p class="text-muted"><?= __("please_save_credentials") ?></p>
                </div>

                <?php if (!$credentials['email_sent']): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?= __("could_not_send_email") ?>
                        <?php if (!empty($credentials['mail_error'])): ?>
                            <div class="small text-muted mt-1">Mailer: <?= sanitize($credentials['mail_error']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="bi bi-envelope-check"></i> <?= __("email_sent_success") ?>
                    </div>
                <?php endif; ?>

                <!-- Credential display -->
                <div class="credential-display mb-4">
                    <p class="mb-2"><strong>Email (username):</strong></p>
                    <p class="value"><?= sanitize($credentials['email']) ?></p>
                    <hr>
                    <p class="mb-2"><strong>Phone (username):</strong></p>
                    <p class="value"><?= sanitize($credentials['phone']) ?></p>
                    <hr>
                    <p class="mb-2"><strong>Password:</strong></p>
                    <p class="value"><?= sanitize($credentials['password']) ?></p>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> <?= __("account_pending") ?>
                </div>

                <div class="text-center">
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> <?= __("go_to_login") ?>
                    </a>
                </div>

            <?php else: ?>
                <!-- Registration Form -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label for="phone_number" class="form-label"><?= __("phone_number") ?> <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number"
                            placeholder="<?= __("phone_placeholder") ?>"
                            value="<?= sanitize($_POST['phone_number'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label"><?= __("email_address") ?> <span
                                class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email"
                            placeholder="<?= __("email_placeholder") ?>" value="<?= sanitize($_POST['email'] ?? '') ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label"><?= __("full_name") ?> <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                            placeholder="<?= __("full_name_placeholder") ?>"
                            value="<?= sanitize($_POST['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="date_of_birth" class="form-label"><?= __("date_of_birth") ?> <span
                                class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                            value="<?= sanitize($_POST['date_of_birth'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label"><?= __("address") ?> <span
                                class="text-danger">*</span></label>
                        <textarea class="form-control" id="address" name="address" rows="2"
                            placeholder="<?= __("address_placeholder") ?>"
                            required><?= sanitize($_POST['address'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-4" data-id-card-field>
                        <label class="form-label"><?= __("id_card_front") ?> *</label>

                        <div class="upload-box" onclick="document.getElementById('id_card_front').click()">
                            <input type="file" id="id_card_front" name="id_card_front" accept="image/*" hidden required>

                            <div class="upload-content text-center">
                                <i class="bi bi-cloud-arrow-up fs-2"></i>
                                <p class="mb-0"><?= __("drop_or_click") ?? "Drop a photo here or click to choose" ?></p>
                            </div>

                            <img id="preview_front" class="upload-preview d-none">
                        </div>
                        <div class="form-text text-muted">After selecting an image, preview the final 900 x 600 version before submit.</div>
                    </div>

                    <div class="mb-4" data-id-card-field>
                        <label class="form-label"><?= __("id_card_back") ?> *</label>

                        <div class="upload-box" onclick="document.getElementById('id_card_back').click()">
                            <input type="file" id="id_card_back" name="id_card_back" accept="image/*" hidden required>

                            <div class="upload-content text-center">
                                <i class="bi bi-cloud-arrow-up fs-2"></i>
                                <p class="mb-0"><?= __("drop_or_click") ?? "Drop a photo here or click to choose" ?></p>
                            </div>

                            <img id="preview_back" class="upload-preview d-none">
                        </div>
                        <div class="form-text text-muted">After selecting an image, preview the final 900 x 600 version before submit.</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-person-plus"></i> <?= __("register") ?>
                        </button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p><?= __("already_have_account") ?> <a href="<?= BASE_URL ?>/login.php"><?= __("login_here") ?></a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Language Switcher -->
    <div class="language-switcher-fixed">
        <div class="btn-group btn-group-sm" role="group">
            <a href="?lang=vi" class="btn <?= $lang === 'vi' ? 'btn-primary' : 'btn-outline-primary' ?>">
                🇻🇳 VI
            </a>
            <a href="?lang=en" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-primary' ?>">
                🇬🇧 EN
            </a>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/id-card-resize.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
