<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * Landing page of an emailed reset link: ?uid=&expires=&sig= (see password_reset_url()).
 * Shows the new-password form while the link is valid; a successful reset sends the user to log in.
 */

header('Referrer-Policy: no-referrer');   // the URL carries the reset token

$resetUser = user_from_reset_link($_GET);
if (!$resetUser) {
    abort(
        400,
        'This reset link no longer works',
        'Reset links expire after ' . RESET_LINK_MINUTES . ' minutes and can only be used once. Request a new one and use the latest email.',
        url('/forgot_password.php'),
        'Request a new link',
        '&#128274;'
    );
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = new_password_errors($_POST);
    if (!$errors) {
        set_password($resetUser, (string) $_POST['new_password']);
        header('Location: ' . url('/login.php?reset=1'));
        exit;
    }
}
$err = fn (string $key) => isset($errors[$key]) ? '<span class="form-error" id="' . $key . '-error">' . e($errors[$key]) . '</span>' : '';
$inv = fn (string $key) => isset($errors[$key]) ? ' class="is-invalid" aria-invalid="true"' : '';

$pageTitle = 'Set a new password';
include APP_ROOT . '/templates/layout/header.php';
?>

<div class="form-narrow">
    <div class="card">
        <h1>Set a new password</h1>
        <p class="text-muted">For <strong><?= e($resetUser['email']) ?></strong>. You'll be signed out on every device, then you can log in with the new password.</p>

        <form method="post" action="<?= e(url('/reset_password.php?' . http_build_query(['uid' => $_GET['uid'], 'expires' => $_GET['expires'], 'sig' => $_GET['sig']]))) ?>" class="form" data-validate>
            <input type="email" name="username" value="<?= e($resetUser['email']) ?>" autocomplete="username" hidden>
            <div class="form-group">
                <label for="new_password">New password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="new_password" name="new_password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" aria-describedby="new_password-hint new_password-error"<?= $inv('new_password') ?>>
                <span class="form-hint" id="new_password-hint">At least <?= PASSWORD_MIN ?> characters.</span>
                <?= $err('new_password') ?>
            </div>
            <div class="form-group">
                <label for="new_password_confirm">Confirm new password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" data-match="new_password" aria-describedby="new_password_confirm-error"<?= $inv('new_password_confirm') ?>>
                <?= $err('new_password_confirm') ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Save new password</button>
        </form>
    </div>
</div>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
