<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$pageTitle = 'Log in';
$next = $_GET['next'] ?? '';

include APP_PATH . '/views/layout/header.php';
?>

<div class="form-narrow">
    <div class="card">
        <h1 class="text-center">Welcome back</h1>
        <p class="text-center text-muted">Log in with your Mapua email address.</p>

        <?php if ($next): ?>
            <div class="alert alert-info">Please log in to continue.</div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/login.php')) ?>" class="form" data-validate data-mock>
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <div class="form-group">
                <label for="email">Email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" required autocomplete="email"
                       placeholder="you@mymail.mapua.edu.ph" data-mapua-email autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="password" name="password" required autocomplete="current-password" minlength="8">
            </div>

            <div class="form-group">
                <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in on this device</label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Log in</button>
        </form>

        <hr>
        <p class="text-center text-sm mb-0">
            New here? <a href="<?= e(url('/register.php')) ?>">Create an account</a>
        </p>
    </div>

    <p class="text-center text-sm text-muted mt-2">
        Preview tip: use the <strong>Preview as</strong> bar at the bottom to switch roles until real login exists.
    </p>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
