<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Create a Student / Faculty account from the school's records (see lookup_school_record() and register_user()).
 *   Step 1 (action=lookup)    choose Student or Faculty / Employee and enter the school number. The server checks
 *                             its format, finds the record, checks it may register and isn't already used.
 *   Step 2 (action=register)  the record's name is shown for confirmation; the user adds an email and a password.
 *                             The number is looked up again on submit, so the name and type always come from the
 *                             school's records. "Back" returns to step 1 with the number kept.
 * Staff and admin roles are assigned by an administrator, never here. The account stays unverified until the
 * emailed link is used. ?sent=1 shows the "check your email" step. Every rule is enforced on the server.
 */

if (is_logged_in()) {
    header('Location: ' . url('/'));
    exit;
}

$action  = (string) ($_POST['action'] ?? '');
$type    = isset(SCHOOL_ID_TYPES[$_POST['account_type'] ?? '']) ? $_POST['account_type'] : 'student';
$id      = strtoupper(trim((string) ($_POST['school_id'] ?? '')));
$record  = null;
$errors  = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'lookup') {
    $ipBucket = 'lookup-ip:' . client_ip();
    if (rate_limited($ipBucket, LOOKUP_MAX_PER_IP)) {
        http_response_code(429);
        $errors['school_id'] = 'Too many look-ups from this network. Try again in ' . LIMIT_WINDOW_MINUTES . ' minutes.';
    } else {
        record_attempt($ipBucket);
        [$record, $idError] = lookup_school_record($type, $id);
        if ($idError) {
            $errors['school_id'] = $idError;
            log_event('register_lookup_failed', null, ['account_type' => $type]);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    $errors = register_user($_POST, turnstile_passed());
    if (!$errors) {
        header('Location: ' . url('/register.php?sent=1'));
        exit;
    }
    if (!isset($errors['school_id'])) {
        [$record] = lookup_school_record($type, $id);   // back to step 2 with the field errors
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
            The link expires in <?= e(verification_lifetime()) ?>.
        </div>
        <p class="text-sm text-muted">Nothing after a few minutes, or the link expired? Check your spam folder, or
            <a href="<?= e(url('/resend_verification.php')) ?>">send a new link</a>.</p>
        <p class="text-sm mb-0"><a href="<?= e(url('/login.php')) ?>">&larr; Back to log in</a></p>

    <?php elseif ($record): ?>
        <h1>Create an account</h1>
        <p class="text-muted">Step 2 of 2 &middot; Confirm it's you, then choose how you'll log in.</p>

        <div class="card card-muted record-card">
            <h2 class="text-sm text-muted mb-1">From the school's records</h2>
            <dl class="detail-list mb-0">
                <dt>Name</dt><dd class="fw-600"><?= e($record['first_name'] . ' ' . $record['last_name']) ?></dd>
                <dt><?= e($record['type_label']) ?> number</dt><dd><?= e($record['school_id']) ?></dd>
                <?php if ($record['affiliation'] !== ''): ?><dt><?= $type === 'student' ? 'Program' : 'Department' ?></dt><dd><?= e($record['affiliation']) ?></dd><?php endif; ?>
                <dt>Record status</dt><dd><?= e(ucfirst($record['status'])) ?></dd>
            </dl>
            <p class="form-hint mb-0">Not you, or something's wrong? Go back and check the number, or contact <?= e(RECORDS_OFFICE) ?>.</p>
        </div>

        <p class="text-sm text-muted"><?= e(APP_NAME) ?> is a student class project, not an official Mapua University service.
            Create a new password just for <?= e(APP_NAME) ?>. Never reuse your Mapua portal or Microsoft 365 password.</p>

        <form method="post" action="<?= e(url('/register.php')) ?>" class="form" data-validate data-unsaved>
            <?php /* "action" is a hidden field because the CAPTCHA script submits the form itself; "Back" overrides it. */ ?>
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="account_type" value="<?= e($type) ?>">
            <input type="hidden" name="school_id" value="<?= e($record['school_id']) ?>">
            <div class="form-group">
                <label for="email">Email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" required maxlength="190" autocomplete="email" placeholder="you@example.com" data-label="Email" data-email-typos aria-required="true" value="<?= $old('email') ?>"<?= $inv('email') ?>>
                <span class="form-hint">We send a link to confirm it. This is how you log in.</span>
                <?= $err('email') ?>
            </div>
            <div class="form-group">
                <label for="password">Password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" data-label="Password" data-password-policy
                       data-person-names="<?= e($record['first_name'] . '|' . $record['last_name']) ?>" data-birthday-checked aria-required="true"<?= $inv('password') ?>>
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
                    <span>I confirm this is my school record, and I understand that false ownership claims may be reported to the university. <span class="req" aria-hidden="true">*</span></span>
                </label>
                <?= $err('agree') ?>
            </div>
            <?= turnstile_widget() ?>
            <?php if (isset($errors['captcha'])): ?><div class="alert alert-error" role="alert"><?= e($errors['captcha']) ?></div><?php endif; ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create account</button>
                <button type="submit" name="action" value="back" class="btn btn-ghost" formnovalidate><span aria-hidden="true">&larr;</span> Back</button>
                <?= cancel_button(url('/login.php'), '') ?>
            </div>
        </form>

    <?php else: ?>
        <h1>Create an account</h1>
        <p class="text-muted">Step 1 of 2 &middot; Open to Mapua students and faculty. We check your school number against the school's records,
            and your name comes from there. Security, maintenance and office accounts are assigned by an administrator.</p>

        <form method="post" action="<?= e(url('/register.php')) ?>" class="form" data-validate>
            <fieldset>
                <legend>I am a…</legend>
                <?php foreach (SCHOOL_ID_TYPES as $key => [$label]): ?>
                    <label class="check"><input type="radio" name="account_type" value="<?= $key ?>" <?= $type === $key ? 'checked' : '' ?>> <?= e($label) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="form-group">
                <label for="school_id">School number <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="school_id" name="school_id" required maxlength="20" autocomplete="off" spellcheck="false" data-label="School number"
                       aria-required="true" value="<?= e($id) ?>"<?= $inv('school_id') ?>>
                <span class="form-hint">Student number (e.g. <?= SCHOOL_ID_TYPES['student'][4] ?>) or employee number (e.g. <?= SCHOOL_ID_TYPES['employee'][4] ?>), as printed on your school ID.</span>
                <?= $err('school_id') ?>
            </div>
            <div class="form-actions">
                <button type="submit" name="action" value="lookup" class="btn btn-primary">Find my record</button>
                <?= cancel_button(url('/'), '') ?>
            </div>
        </form>
        <p class="text-sm mt-2 mb-0">
            Already have an account?
            <a href="<?= e(url('/login.php')) ?>">Log in</a>
        </p>
    <?php endif; ?>
    </div>
</div>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
