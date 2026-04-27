<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bug_reports.php';
require_once __DIR__ . '/includes/lang.php';
requireLogin();

$user = getCurrentUser();

$errors = [];
$submittedId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $pageUrl     = trim($_POST['page_url'] ?? '');
    $userAgent   = trim($_POST['user_agent'] ?? '') ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');

    if ($title === '')        $errors[] = 'A short title is required.';
    if ($description === '')  $errors[] = 'Please describe what happened.';
    if (mb_strlen($title) > 200)         $errors[] = 'Title must be 200 characters or less.';
    if (mb_strlen($description) > 5000)  $errors[] = 'Description is too long (5000 characters max).';

    if (empty($errors)) {
        $result = submitBugReport(
            (int) $user['id'],
            $user['email'] ?? null,
            $title,
            $description,
            $pageUrl ?: null,
            $userAgent ?: null
        );
        $submittedId = $result['id'];
        // Reset POSTed values so the form is empty if they want to file another.
        $_POST = [];
    }
}

$pageTitle = 'Report a bug';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card sn-card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-bug"></i> Report a bug</h4>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
            <div class="card-body p-4">

                <?php if ($submittedId): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        Thanks! Your report (#<?= (int) $submittedId ?>) is in the dev queue.
                        We'll look at it as soon as we can.
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary">Back to dashboard</a>
                        <a href="<?= BASE_URL ?>/report_bug.php" class="btn btn-outline-secondary">Report another</a>
                    </div>

                <?php else: ?>
                    <p class="text-muted">
                        Found something broken or confusing? Tell us what you saw and we'll
                        get it fixed. Include the steps you took if you remember them.
                    </p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="title" class="form-label">Short title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title"
                                   maxlength="200" required
                                   placeholder="e.g. Transfer fails with 'invalid amount' even when amount is valid"
                                   value="<?= sanitize($_POST['title'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">What happened? <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description"
                                      rows="7" maxlength="5000" required
                                      placeholder="What were you doing? What did you expect? What actually happened?"><?= sanitize($_POST['description'] ?? '') ?></textarea>
                            <div class="form-text">Be specific. Include steps to reproduce, error messages, and what you saw on screen.</div>
                        </div>

                        <!-- Auto-captured by JS so the dev knows where the user was. -->
                        <input type="hidden" name="page_url" id="page_url" value="">
                        <input type="hidden" name="user_agent" id="user_agent" value="">

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send"></i> Send to dev team
                            </button>
                        </div>
                    </form>

                    <script>
                    // Auto-fill where the user came from (if they navigated here from
                    // the page with the bug) plus their browser string. Both help
                    // reproduction without making the user paste anything.
                    (function () {
                        var pageUrl = document.referrer || window.location.href;
                        document.getElementById('page_url').value = pageUrl;
                        document.getElementById('user_agent').value = navigator.userAgent || '';
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
