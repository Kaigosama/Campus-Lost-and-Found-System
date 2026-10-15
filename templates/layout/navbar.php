<?php
defined('APP_ROOT') || exit;
$navUser = current_user();
$navType = $type ?? '';
?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <img class="brand-mark" src="<?= e(asset('images/logo.png')) ?>" alt="" width="40" height="40">
            <span class="brand-text"><?= e(APP_NAME) ?><small><?= e(APP_FULL_NAME) ?></small></span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav" data-nav-toggle>
            <span class="nav-toggle-bar" aria-hidden="true"></span>
            <span class="sr-only">Toggle menu</span>
        </button>

        <nav class="nav" id="main-nav" aria-label="Main navigation">
            <?php if (!$navUser): ?>
                <a href="<?= e(url('/')) ?>"<?= is_active('/index.php') ?>>Home</a>
                <a href="<?= e(url('/browse.php')) ?>"<?= is_active('/browse.php') ?>>Found Items</a>
                <a href="<?= e(url('/login.php')) ?>"<?= is_active('/login.php') ?>>Log in</a>
                <a href="<?= e(url('/register.php')) ?>"<?= is_active('/register.php') ?>>Register</a>
            <?php else: ?>
                <a href="<?= e(url('/')) ?>"<?= is_active('/index.php') ?>>Dashboard</a>
                <?php if (!can_monitor()): /* staff and admins reach every management section from the dashboard's section bar */ ?>
                    <a href="<?= e(url('/browse.php')) ?>"<?= is_active('/browse.php') ?>>Found Items</a>
                    <a href="<?= e(url('/report.php')) ?>"<?= is_active('/report.php', $navType !== 'found') ?>>Report Lost</a>
                    <details class="nav-group">
                        <summary>My Activity</summary>
                        <div class="dropdown">
                            <a href="<?= e(url('/?tab=reports')) ?>">My Lost Reports</a>
                            <a href="<?= e(url('/?tab=my_claims')) ?>">My Claims</a>
                            <a href="<?= e(url('/report.php?type=found')) ?>">Post a Found Item</a>
                            <a href="<?= e(url('/?tab=posts')) ?>">My Found Posts</a>
                            <a href="<?= e(url('/?tab=account')) ?>">Account &amp; Password</a>
                        </div>
                    </details>
                <?php endif; ?>

                <div class="nav-user">
                    <span class="avatar" aria-hidden="true"><?= e(initials($navUser)) ?></span>
                    <span class="nav-user-name">
                        <?= e(full_name($navUser)) ?>
                        <small><?= e(ROLES[$navUser['role']] ?? $navUser['role']) ?></small>
                    </span>
                    <form method="post" action="<?= e(url('/?action=logout')) ?>" class="logout-form">
                        <button type="submit" class="btn btn-ghost btn-sm">Log out</button>
                    </form>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</header>
