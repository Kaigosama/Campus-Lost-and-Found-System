<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * Create a Student / Faculty account (see register_user()); staff and admin roles are assigned by an admin.
 * ?next= (a same-site path) is where to go after signing up.
 */

$next = $_POST['next'] ?? $_GET['next'] ?? '';

if (is_logged_in()) {
    header('Location: ' . safe_redirect($next));
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = register_user($_POST);
    if (!$errors) {
        login_user(find_user_by_email($_POST['email']));
        header('Location: ' . safe_redirect($next));
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
        <h1>Create an account</h1>
        <p class="text-muted">Open to Mapua students and faculty. Security, maintenance and office accounts are assigned by an administrator.</p>

        <form method="post" action="<?= e(url('/register.php')) ?>" class="form" data-validate>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="first_name" name="first_name" required maxlength="100" data-name autocomplete="given-name" aria-describedby="first_name-error" value="<?= $old('first_name') ?>"<?= $inv('first_name') ?>>
                    <?= $err('first_name') ?>
                </div>
                <div class="form-group">
                    <label for="last_name">Last name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="last_name" name="last_name" required maxlength="100" data-name autocomplete="family-name" aria-describedby="last_name-error" value="<?= $old('last_name') ?>"<?= $inv('last_name') ?>>
                    <?= $err('last_name') ?>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Mapua email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@mymail.mapua.edu.ph" data-mapua-email aria-describedby="email-hint email-error" value="<?= $old('email') ?>"<?= $inv('email') ?>>
                <span class="form-hint" id="email-hint">Must end in @mymail.mapua.edu.ph or @mapua.edu.ph.</span>
                <?= $err('email') ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" aria-describedby="password-hint password-error"<?= $inv('password') ?>>
                    <span class="form-hint" id="password-hint">At least <?= PASSWORD_MIN ?> characters.</span>
                    <?= $err('password') ?>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" data-match="password" aria-describedby="password_confirm-error"<?= $inv('password_confirm') ?>>
                    <?= $err('password_confirm') ?>
                </div>
            </div>
            <div class="form-group">
                <label class="check">
                    <input type="checkbox" name="agree" value="1" required<?= !empty($_POST['agree']) ? ' checked' : '' ?>>
                    I understand that false ownership claims may be reported to the university.
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>
        <p class="text-sm mt-2 mb-0">
            Already have an account?
            <a href="<?= e(url('/login.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>">Log in</a>
        </p>
    </div>
</div>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
