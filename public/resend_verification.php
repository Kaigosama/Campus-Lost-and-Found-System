<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Request a new email-verification link (see resend_verification()). Like forgot_password.php, the answer is
 * the same whether or not the email has an unverified account. ?sent=1 shows the confirmation.
 */

if (is_logged_in()) {
    header('Location: ' . url('/'));
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $ipBucket = 'verify-ip:' . client_ip();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (rate_limited($ipBucket, RESET_MAX_PER_IP)) {
        http_response_code(429);
        $errors['email'] = 'Too many requests. Try again in ' . LIMIT_WINDOW_MINUTES . ' minutes.';
    } else {
        record_attempt($ipBucket);
        resend_verification($email);
        header('Location: ' . url('/resend_verification.php?sent=1'));
        exit;
    }
}

$pageTitle = 'Resend verification email';
include APP_ROOT . '/templates/layout/header.php';
?>

<div class="form-narrow">
    <div class="card">
        <h1>Resend verification email</h1>
        <?php if (isset($_GET['sent'])): ?>
            <div class="alert alert-success" role="status">
                If that email belongs to an account that still needs confirming, we've sent it a new link.
                It expires in <?= VERIFY_LINK_HOURS ?> hours, and earlier links no longer work.
            </div>
        <?php else: ?>
            <p class="text-muted">Enter the email you signed up with and we'll send a new confirmation link.</p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/resend_verification.php')) ?>" class="form" data-validate>
            <div class="form-group">
                <label for="email">Email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@mymail.mapua.edu.ph" data-label="Email"
                       value="<?= e($_POST['email'] ?? $_GET['email'] ?? '') ?>"<?= isset($errors['email']) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['email'])): ?><span class="form-error" id="email-error"><?= e($errors['email']) ?></span><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Send new link</button>
        </form>
        <p class="text-sm mt-2 mb-0"><a href="<?= e(url('/login.php')) ?>">&larr; Back to log in</a></p>
    </div>
</div>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
