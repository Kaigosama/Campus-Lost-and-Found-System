<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Log in. ?next= (a same-site path) is where to go afterwards; require_login() sets it.
 * ?reset=1 confirms a password reset, ?verified=1 an email verification.
 * Checks, in order: CAPTCHA (server-side), per-IP rate limit, account lockout, password, active, verified email.
 */

$next = $_POST['next'] ?? $_GET['next'] ?? '';

if (is_logged_in()) {
    header('Location: ' . safe_redirect($next));
    exit;
}

// Why the last session ended (expired, revoked, …), set by current_user(); shown once.
$notice = $_SESSION['auth_notice'] ?? '';
unset($_SESSION['auth_notice']);

$lockedMessage = 'Too many failed log-in attempts. This account is temporarily locked; try again in ' . LOCKOUT_MINUTES
    . ' minutes or reset your password.';
$error = '';
$unverified = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $ipBucket = 'login-ip:' . client_ip();
    $found    = $email !== '' ? find_user_by_email($email) : null;

    if (!turnstile_passed()) {
        $error = 'Please complete the CAPTCHA check.';
        log_event('captcha_failed', $found, ['form' => 'login'], $email ?: null);
    } elseif (rate_limited($ipBucket, LOGIN_MAX_PER_IP)) {
        http_response_code(429);
        $error = 'Too many log-in attempts from this network. Try again in ' . LIMIT_WINDOW_MINUTES . ' minutes.';
        log_event('login_rate_limited', $found, [], $email ?: null);
    } elseif (!$found) {
        // Unknown emails get the same answers, at the same point, as real accounts, so nobody can probe which exist.
        $emailBucket = 'login-unknown:' . $email;
        record_attempt($ipBucket);
        $error = rate_limited($emailBucket, LOCKOUT_ATTEMPTS - 1) ? $lockedMessage : 'Incorrect email or password.';
        record_attempt($emailBucket);
        log_event('login_failed', null, ['reason' => 'unknown email'], $email ?: null);
    } elseif (account_locked($found)) {
        $error = $lockedMessage;
        log_event('login_blocked_locked', $found);
    } elseif (!password_verify($password, $found['password_hash'])) {
        record_attempt($ipBucket);
        $error = record_failed_login($found) ? $lockedMessage : 'Incorrect email or password.';
    } elseif (!$found['is_active']) {
        $error = 'This account has been deactivated. Contact the Lost & Found office.';
        log_event('login_blocked_inactive', $found);
    } elseif (!$found['email_verified']) {
        $unverified = true;
        $error = 'Confirm your email address before logging in. Check your inbox for the verification link.';
        log_event('login_blocked_unverified', $found);
    } else {
        login_user($found);
        header('Location: ' . safe_redirect($next));
        exit;
    }
}

$pageTitle = 'Log in';
include APP_ROOT . '/templates/layout/header.php';
?>

<div class="form-narrow">
    <div class="card">
        <h1>Log in</h1>
        <p class="text-muted">Use the email and the <?= e(APP_NAME) ?> password you registered with.</p>
        <p class="text-sm text-muted"><?= e(APP_NAME) ?> is a student class project, not an official Mapua University service.
            Never enter your Mapua portal or Microsoft 365 password here.</p>

        <?php if (isset($_GET['reset'])): ?>
            <div class="alert alert-success" role="status">Your password has been changed. Log in with your new password.</div>
        <?php elseif (isset($_GET['verified'])): ?>
            <div class="alert alert-success" role="status">Your email is confirmed. You can log in now.</div>
        <?php elseif ($notice && !$error): ?>
            <div class="alert alert-warning" role="status"><?= e($notice) ?></div>
        <?php elseif ($next && !$error): ?>
            <div class="alert alert-info">Please log in to continue.</div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/login.php')) ?>" class="form" data-validate data-needs-cookies>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <?php if ($error): ?>
                <div class="alert alert-error" role="alert" id="login-error">
                    <?= e($error) ?>
                    <?php if ($unverified): ?><a href="<?= e(url('/resend_verification.php?email=' . rawurlencode($email))) ?>">Send a new link</a><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="email">Email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@mymail.mapua.edu.ph" data-mapua-email data-label="Email"
                       <?= $error ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>
                       value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="password" name="password" required autocomplete="current-password" data-label="Password">
                <a class="form-hint" href="<?= e(url('/forgot_password.php')) ?>">Forgot password?</a>
            </div>
            <?= turnstile_widget() ?>
            <button type="submit" class="btn btn-primary btn-block">Log in</button>
            <p class="form-hint">You'll be logged out after <?= SESSION_IDLE_MINUTES ?> minutes of inactivity.</p>
        </form>
        <p class="text-sm mt-2 mb-0">
            New to <?= e(APP_NAME) ?>?
            <a href="<?= e(url('/register.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>">Create an account</a>
        </p>
    </div>
</div>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
