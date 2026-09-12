<?php
defined('APP_ROOT') || exit;
/** Role-aware site header. Included by header.php; $type comes from the page (see header.php). */
$navUser = current_user();
$navType = $type ?? '';
?>
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
            <?php if (!$navUser): ?>
                <a href="<?= e(url('/')) ?>" class="<?= is_active('/index.php') ?>">Home</a>
                <a href="<?= e(url('/browse.php')) ?>" class="<?= is_active('/browse.php', $navType !== 'lost') ?>">Found Items</a>
                <a href="<?= e(url('/#account')) ?>">Log in</a>
                <a href="<?= e(url('/#account')) ?>" class="btn btn-accent btn-sm nav-cta">Register</a>
            <?php else: ?>
                <a href="<?= e(url('/')) ?>" class="<?= is_active('/index.php') ?>">Dashboard</a>
                <a href="<?= e(url('/browse.php')) ?>" class="<?= is_active('/browse.php', $navType !== 'lost') ?>">Found Items</a>
                <a href="<?= e(url('/report.php')) ?>" class="<?= is_active('/report.php', $navType !== 'found') ?>">Report Lost</a>

                <details class="nav-group">
                    <summary>My Activity</summary>
                    <div class="dropdown">
                        <a href="<?= e(url('/?tab=reports')) ?>">My Lost Reports</a>
                        <a href="<?= e(url('/?tab=my_claims')) ?>">My Claims</a>
                    </div>
                </details>

                <?php if (is_staff()): ?>
                    <details class="nav-group">
                        <summary>Staff</summary>
                        <div class="dropdown">
                            <a href="<?= e(url('/report.php?type=found')) ?>">Log Found Item</a>
                            <a href="<?= e(url('/browse.php?manage=1')) ?>">Manage Found Items</a>
                            <a href="<?= e(url('/?tab=queue')) ?>">Claims Queue</a>
                            <a href="<?= e(url('/browse.php?type=lost')) ?>">All Lost Reports</a>
                        </div>
                    </details>
                <?php endif; ?>

                <?php if (is_admin()): ?>
                    <details class="nav-group">
                        <summary>Admin</summary>
                        <div class="dropdown">
                            <a href="<?= e(url('/?tab=users')) ?>">Manage Users</a>
                            <a href="<?= e(url('/?tab=stats')) ?>">Statistics</a>
                        </div>
                    </details>
                <?php endif; ?>

                <div class="nav-user">
                    <span class="avatar" aria-hidden="true"><?= e(initials($navUser)) ?></span>
                    <span class="nav-user-name">
                        <?= e(full_name($navUser)) ?>
                        <small><?= e(ROLES[$navUser['role']] ?? $navUser['role']) ?></small>
                    </span>
                    <a href="<?= e(url('/?action=logout')) ?>" class="btn btn-ghost btn-sm">Log out</a>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</header>
