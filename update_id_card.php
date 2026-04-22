<?php
require_once __DIR__ . '/includes/auth.php';
requirePasswordChanged();

$user = getCurrentUser();

if ($user['status'] !== 'waiting_for_updates') {
    setFlash('info', 'You do not need to re-upload your ID card.');
    redirect(BASE_URL . '/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();

    if (!isset($_FILES['id_card_front']) || $_FILES['id_card_front']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Front photo of ID card is required.';
    }
    if (!isset($_FILES['id_card_back']) || $_FILES['id_card_back']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Back photo of ID card is required.';
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    foreach (['id_card_front', 'id_card_back'] as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            if (!in_array($_FILES[$field]['type'], $allowedTypes)) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be an image (JPEG, PNG, GIF, WEBP).';
            }
            if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be less than 5MB.';
            }
        }
    }

    if (empty($errors)) {
        $frontData = file_get_contents($_FILES['id_card_front']['tmp_name']);
        $backData  = file_get_contents($_FILES['id_card_back']['tmp_name']);
        $frontMime = $_FILES['id_card_front']['type'];
        $backMime  = $_FILES['id_card_back']['type'];

        // Update mime flags on users + upsert BLOBs into user_id_cards, in one
        // transaction. ON DUPLICATE KEY ensures re-uploads replace old bytes
        // and bump updated_at (which drives ETag cache invalidation).
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                UPDATE users
                SET id_card_front_mime = :front_mime,
                    id_card_back_mime  = :back_mime,
                    status = 'pending'
                WHERE id = :id
            ");
            $stmt->bindParam(':front_mime', $frontMime);
            $stmt->bindParam(':back_mime',  $backMime);
            $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
            $stmt->execute();

            $blobStmt = $db->prepare("
                INSERT INTO user_id_cards (user_id, front_data, back_data)
                VALUES (:uid, :front_data, :back_data)
                ON DUPLICATE KEY UPDATE
                    front_data = VALUES(front_data),
                    back_data  = VALUES(back_data)
            ");
            $blobStmt->bindValue(':uid', $user['id'], PDO::PARAM_INT);
            $blobStmt->bindParam(':front_data', $frontData, PDO::PARAM_LOB);
            $blobStmt->bindParam(':back_data',  $backData,  PDO::PARAM_LOB);
            $blobStmt->execute();

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        setFlash('success', 'ID card photos re-uploaded. Your account is pending verification again.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

$pageTitle = 'Re-upload ID Card';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0"><i class="bi bi-card-image"></i> Re-upload ID Card</h4>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    An admin found your previous ID card photos invalid. Please upload clearer photos of both the front and back of your ID card.
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?= sanitize($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label for="id_card_front" class="form-label">ID Card - Front Photo <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="id_card_front" name="id_card_front" accept="image/*" required>
                        <div class="form-text">JPG, PNG, GIF or WEBP. Max 5 MB.</div>
                    </div>

                    <div class="mb-3">
                        <label for="id_card_back" class="form-label">ID Card - Back Photo <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="id_card_back" name="id_card_back" accept="image/*" required>
                        <div class="form-text">JPG, PNG, GIF or WEBP. Max 5 MB.</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-upload"></i> Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
