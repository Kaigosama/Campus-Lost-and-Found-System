<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$pageTitle = 'Create an account';
include APP_PATH . '/views/layout/header.php';
?>

<div class="form-narrow">
    <div class="card">
        <h1 class="text-center">Create your account</h1>
        <p class="text-center text-muted">Registration is open to Mapua students, faculty and staff.</p>

        <form method="post" action="<?= e(url('/register.php')) ?>" class="form" data-validate data-mock>
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="first_name" name="first_name" required maxlength="100" autocomplete="given-name" autofocus>
                </div>
                <div class="form-group">
                    <label for="last_name">Last name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="last_name" name="last_name" required maxlength="100" autocomplete="family-name">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Mapua email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" required autocomplete="email"
                       placeholder="you@mymail.mapua.edu.ph" data-mapua-email>
                <span class="form-hint">Must end in @mymail.mapua.edu.ph or @mapua.edu.ph.</span>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                <span class="form-hint">At least 8 characters.</span>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8"
                       autocomplete="new-password" data-match="password">
            </div>

            <div class="form-group">
                <label class="check">
                    <input type="checkbox" name="agree" value="1" required>
                    I understand that false ownership claims may be reported to the university.
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Create account</button>
        </form>

        <hr>
        <p class="text-center text-sm mb-0">
            Already registered? <a href="<?= e(url('/login.php')) ?>">Log in</a>
        </p>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
