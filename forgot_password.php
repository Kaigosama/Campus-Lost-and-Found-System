<?php
require_once __DIR__ . '/config/db_connect.php';

/**
 * "Forgot password" — asks for an email and sends a reset link (see request_password_reset()).
 * The answer is the same whether or not the email has an account. ?sent=1 shows the confirmation.
 */

if (is_logged_in()) {
    header('Location: ' . url('/?tab=account'));
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        request_password_reset($email);
        header('Location: ' . url('/forgot_password.php?sent=1'));
        exit;
    }
}

$pageTitle = 'Forgot password';
include APP_ROOT . '/includes/header.php';
?>

<div class="form-narrow">
    <div class="card">
        <h1>Forgot your password?</h1>
        <?php if (isset($_GET['sent'])): ?>
            <div class="alert alert-success" role="status">
                If that email belongs to a <?= e(APP_NAME) ?> account, we've sent it a link to set a new password.
                The link expires in <?= RESET_LINK_MINUTES ?> minutes.
            </div>
            <p class="text-sm text-muted">Nothing after a few minutes? Check your spam folder, or try again below.</p>
        <?php else: ?>
            <p class="text-muted">Enter the email you signed up with and we'll send you a link to set a new password.</p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/forgot_password.php')) ?>" class="form" data-validate>
            <div class="form-group">
                <label for="email">Email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@mymail.mapua.edu.ph"
                       aria-describedby="email-error"
                       value="<?= e($_POST['email'] ?? '') ?>"<?= isset($errors['email']) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['email'])): ?><span class="form-error" id="email-error"><?= e($errors['email']) ?></span><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
        </form>
        <p class="text-sm mt-2 mb-0"><a href="<?= e(url('/#account')) ?>">&larr; Back to log in</a></p>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
