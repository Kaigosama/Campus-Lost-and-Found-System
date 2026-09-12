<?php
/**
 * Page header + role-aware navigation. Pages may set before including:
 *   $pageTitle  string       — used in <title>
 *   $flash      array|null   — ['type' => 'success|error|info|warning', 'message' => '...']
 */
$user  = current_user();
$title = !empty($pageTitle) ? $pageTitle . ' · ' . APP_NAME : APP_NAME . ' · ' . APP_FULL_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand-mark" aria-hidden="true">LF</span>
            <span class="brand-text"><?= e(APP_NAME) ?><small><?= e(APP_FULL_NAME) ?></small></span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav" data-nav-toggle>
            <span class="nav-toggle-bar" aria-hidden="true"></span>
            <span class="sr-only">Toggle menu</span>
        </button>

        <nav class="nav" id="main-nav" aria-label="Main navigation">
            <?php if (!$user): ?>
                <a href="<?= e(url('/')) ?>" class="<?= is_active('/index.php') ?>">Home</a>
                <a href="<?= e(url('/found/browse.php')) ?>" class="<?= is_active('/found/browse.php') ?>">Found Items</a>
                <a href="<?= e(url('/login.php')) ?>" class="<?= is_active('/login.php') ?>">Log in</a>
                <a href="<?= e(url('/register.php')) ?>" class="btn btn-accent btn-sm nav-cta">Register</a>
            <?php else: ?>
                <a href="<?= e(url('/dashboard.php')) ?>" class="<?= is_active('/dashboard.php') ?>">Dashboard</a>
                <a href="<?= e(url('/found/browse.php')) ?>" class="<?= is_active('/found/browse.php') ?>">Found Items</a>
                <a href="<?= e(url('/lost/report.php')) ?>" class="<?= is_active('/lost/report.php') ?>">Report Lost</a>

                <details class="nav-group">
                    <summary>My Activity</summary>
                    <div class="dropdown">
                        <a href="<?= e(url('/lost/my-reports.php')) ?>">My Lost Reports</a>
                        <a href="<?= e(url('/claims/my-claims.php')) ?>">My Claims</a>
                    </div>
                </details>

                <?php if (is_staff()): ?>
                    <details class="nav-group">
                        <summary>Staff</summary>
                        <div class="dropdown">
                            <a href="<?= e(url('/found/intake.php')) ?>">Log Found Item</a>
                            <a href="<?= e(url('/found/manage.php')) ?>">Manage Found Items</a>
                            <a href="<?= e(url('/claims/queue.php')) ?>">Claims Queue</a>
                            <a href="<?= e(url('/lost/all.php')) ?>">All Lost Reports</a>
                        </div>
                    </details>
                <?php endif; ?>

                <?php if (is_admin()): ?>
                    <details class="nav-group">
                        <summary>Admin</summary>
                        <div class="dropdown">
                            <a href="<?= e(url('/admin/users.php')) ?>">Manage Users</a>
                            <a href="<?= e(url('/admin/stats.php')) ?>">Statistics</a>
                        </div>
                    </details>
                <?php endif; ?>

                <div class="nav-user">
                    <span class="avatar" aria-hidden="true"><?= e(initials($user)) ?></span>
                    <span class="nav-user-name">
                        <?= e(full_name($user)) ?>
                        <small><?= e(ROLES[$user['role']] ?? $user['role']) ?></small>
                    </span>
                    <a href="<?= e(url('/logout.php')) ?>" class="btn btn-ghost btn-sm">Log out</a>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="main" class="container page">
<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>
