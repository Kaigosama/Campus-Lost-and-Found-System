<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Landing page of an emailed verification link: ?token= (see send_verification_email()).
 * Opening the link only shows a button; the POST uses the token. Email scanners that pre-open links
 * therefore can't use up the single-use link before the person does. The token is checked again on the POST,
 * so a link that expires while the page is open activates nothing.
 */

// The URL carries the token, so it never goes to other sites. Not no-referrer: that makes the browser send
// "Origin: null" on the confirm POST, which reject_cross_site_post() refuses.
header('Referrer-Policy: same-origin');

$token = (string) ($_GET['token'] ?? '');
[$state, $pending] = verification_token_state($token);
if ($state === 'expired') {
    abort(
        410,
        'This verification link has expired',
        'Your email verification link has expired. Request a new verification email to continue.',
        url('/resend_verification.php'),
        'Request a new verification email',
        '&#9203;'
    );
}
if ($state !== 'valid') {
    abort(
        400,
        'This verification link no longer works',
        'Links work only once, and only the newest link works. If you already confirmed your email, just log in; otherwise request a new link. Accounts left unconfirmed for '
            . UNVERIFIED_GRACE_HOURS . ' hours after their link expires are removed, so if no new link arrives, register again.',
        url('/resend_verification.php'),
        'Send a new link',
        '&#9993;'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mark_email_verified($pending);
    header('Location: ' . url('/login.php?verified=1'));
    exit;
}

$pageTitle = 'Confirm your email';
include APP_ROOT . '/templates/layout/header.php';
?>

<div class="form-narrow">
    <div class="card">
        <h1>Confirm your email</h1>
        <p class="text-muted">Activate the <?= e(APP_NAME) ?> account for <strong><?= e($pending['email']) ?></strong>.</p>
        <form method="post" action="<?= e(url('/verify_email.php?token=' . $token)) ?>" class="form">
            <div class="form-actions compact">
                <button type="submit" class="btn btn-primary">Confirm my email</button>
                <?= cancel_button(url('/login.php'), '') ?>
            </div>
        </form>
    </div>
</div>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
