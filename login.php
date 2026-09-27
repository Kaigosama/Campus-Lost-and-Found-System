<?php
require_once __DIR__ . '/config/db_connect.php';

/**
 * Log in. ?next= (a same-site path) is where to go afterwards; require_login() sets it.
 * ?reset=1 confirms a password reset (reset_password.php).
 */

$next = $_POST['next'] ?? $_GET['next'] ?? '';

if (is_logged_in()) {
    header('Location: ' . safe_redirect($next));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $found = find_user_by_email($_POST['email'] ?? '');
    if (!$found || !password_verify((string) ($_POST['password'] ?? ''), $found['password_hash'])) {
        $error = 'Incorrect email or password.';
    } elseif (!$found['is_active']) {
        $error = 'This account has been deactivated. Contact the Lost & Found office.';
    } else {
        login_user($found, !empty($_POST['remember']));
        header('Location: ' . safe_redirect($next));
        exit;
    }
}

$pageTitle = 'Log in';
include APP_ROOT . '/includes/header.php';
?>

<div class="form-narrow">
    <div class="card">
        <h1>Log in</h1>
        <p class="text-muted">Use your Mapua email address.</p>

        <?php if (isset($_GET['reset'])): ?>
            <div class="alert alert-success" role="status">Your password has been changed. Log in with your new password.</div>
        <?php elseif ($next && !$error): ?>
            <div class="alert alert-info">Please log in to continue.</div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/login.php')) ?>" class="form" data-validate>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <?php if ($error): ?><div class="alert alert-error" role="alert" id="login-error"><?= e($error) ?></div><?php endif; ?>
            <div class="form-group">
                <label for="email">Email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@mymail.mapua.edu.ph" data-mapua-email
                       <?= $error ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>
                       value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="password" name="password" required autocomplete="current-password" minlength="<?= PASSWORD_MIN ?>">
                <a class="form-hint" href="<?= e(url('/forgot_password.php')) ?>">Forgot password?</a>
            </div>
            <div class="form-group">
                <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in on this device</label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Log in</button>
        </form>
        <p class="text-sm mt-2 mb-0">
            New to <?= e(APP_NAME) ?>?
            <a href="<?= e(url('/register.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>">Create an account</a>
        </p>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
