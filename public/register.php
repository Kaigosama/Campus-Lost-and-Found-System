<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Create a Student / Faculty account (see register_user()); staff and admin roles are assigned by an admin.
 * The account stays unverified until the emailed link is used. ?sent=1 shows the "check your email" step.
 * Every rule here is enforced again on the server; app.js only gives earlier feedback.
 */

if (is_logged_in()) {
    header('Location: ' . url('/'));
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = register_user($_POST, turnstile_passed());
    if (!$errors) {
        header('Location: ' . url('/register.php?sent=1'));
        exit;
    }
}
$old = fn (string $key) => e($_POST[$key] ?? '');
$err = fn (string $key) => isset($errors[$key]) ? '<span class="form-error" id="' . $key . '-error">' . e($errors[$key]) . '</span>' : '';
$inv = fn (string $key) => isset($errors[$key]) ? ' class="is-invalid" aria-invalid="true"' : '';

$pageTitle = 'Create an account';
include APP_ROOT . '/templates/layout/header.php';
?>

<div class="form-narrow">
    <div class="card">
    <?php if (isset($_GET['sent'])): ?>
        <h1>Check your email</h1>
        <div class="alert alert-success" role="status">
            We've sent a confirmation link to the address you entered. Open it to activate your account, then log in.
            The link expires in <?= VERIFY_LINK_HOURS ?> hours.
        </div>
        <p class="text-sm text-muted">Nothing after a few minutes? Check your spam folder, or
            <a href="<?= e(url('/resend_verification.php')) ?>">send a new link</a>.</p>
        <p class="text-sm mb-0"><a href="<?= e(url('/login.php')) ?>">&larr; Back to log in</a></p>
    <?php else: ?>
        <h1>Create an account</h1>
        <p class="text-muted">Open to Mapua students and faculty. Security, maintenance and office accounts are assigned by an administrator.</p>
        <p class="text-sm text-muted"><?= e(APP_NAME) ?> is a student class project, not an official Mapua University service.
            Create a new password just for <?= e(APP_NAME) ?>. Never reuse your Mapua portal or Microsoft 365 password.</p>
        <p class="text-sm text-muted">Fields marked <span class="req-inline" aria-hidden="true">*</span><span class="sr-only">with an asterisk</span> are required.</p>

        <form method="post" action="<?= e(url('/register.php')) ?>" class="form" data-validate>
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="first_name" name="first_name" required minlength="<?= NAME_MIN ?>" maxlength="100" data-name data-label="First name" autocomplete="given-name" aria-required="true" value="<?= $old('first_name') ?>"<?= $inv('first_name') ?>>
                    <?= $err('first_name') ?>
                </div>
                <div class="form-group">
                    <label for="last_name">Last name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="last_name" name="last_name" required maxlength="100" data-name data-label="Last name" autocomplete="family-name" aria-required="true" value="<?= $old('last_name') ?>"<?= $inv('last_name') ?>>
                    <?= $err('last_name') ?>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" required maxlength="190" autocomplete="email" placeholder="you@example.com" data-label="Email" aria-required="true" value="<?= $old('email') ?>"<?= $inv('email') ?>>
                <span class="form-hint">We send a link to confirm it. This is how you log in.</span>
                <?= $err('email') ?>
            </div>
            <div class="form-group">
                <label for="password">Password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" data-label="Password" data-password-policy aria-required="true"<?= $inv('password') ?>>
                <?= $err('password') ?>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" data-match="password" data-label="Confirm password" data-no-paste aria-required="true"<?= $inv('password_confirm') ?>>
                <span class="form-hint">Type your password again. Pasting is turned off for this field.</span>
                <?= $err('password_confirm') ?>
            </div>
            <div class="form-group">
                <label class="check">
                    <input type="checkbox" name="agree" value="1" required aria-required="true"<?= !empty($_POST['agree']) ? ' checked' : '' ?><?= $inv('agree') ?>>
                    <span>I understand that false ownership claims may be reported to the university. <span class="req" aria-hidden="true">*</span></span>
                </label>
                <?= $err('agree') ?>
            </div>
            <?= turnstile_widget() ?>
            <?php if (isset($errors['captcha'])): ?><div class="alert alert-error" role="alert"><?= e($errors['captcha']) ?></div><?php endif; ?>
            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>
        <p class="text-sm mt-2 mb-0">
            Already have an account?
            <a href="<?= e(url('/login.php')) ?>">Log in</a>
        </p>
    <?php endif; ?>
    </div>
</div>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
