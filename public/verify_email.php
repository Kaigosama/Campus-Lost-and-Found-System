<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Landing page of an emailed verification link: ?token= (see send_verification_email()).
 * Opening the link only shows a button; the POST uses the token. Email scanners that pre-open links
 * therefore can't use up the single-use link before the person does.
 */

// The URL carries the token, so it never goes to other sites. Not no-referrer: that makes the browser send
// "Origin: null" on the confirm POST, which reject_cross_site_post() refuses.
header('Referrer-Policy: same-origin');

$token   = (string) ($_GET['token'] ?? '');
$pending = user_from_verification_token($token);
if (!$pending) {
    abort(
        400,
        'This verification link no longer works',
        'Links expire after ' . VERIFY_LINK_HOURS . ' hours and work only once. If you already confirmed your email, just log in; otherwise request a new link. Accounts left unconfirmed after the link expires are removed, so if no new link arrives, register again.',
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
            <button type="submit" class="btn btn-primary btn-block">Confirm my email</button>
        </form>
    </div>
</div>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
