<?php defined('APP_ROOT') || exit; ?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p class="footer-note">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Academic project for Web Systems and Technologies 2 (ITS122P, Group 4), Mapua University.
            All items, people and reports shown are fictional demonstration data.
        </p>
        <p class="footer-links">
            <a href="<?= e(url('/browse.php')) ?>">Found Items</a>
            <a href="<?= e(url('/report.php')) ?>">Report Lost Item</a>
            <?php if (is_logged_in()): ?>
                <a href="<?= e(url('/?action=logout')) ?>">Log out</a>
            <?php else: ?>
                <a href="<?= e(url('/#login')) ?>">Log in</a>
            <?php endif; ?>
        </p>
    </div>
</footer>

<script>window.CLAFS = <?= json_encode(['statusLabels' => LOST_STATUSES + FOUND_STATUSES + CLAIM_STATUSES + ['active' => 'Active', 'inactive' => 'Deactivated']]) ?>;</script>
<script src="<?= e(asset('js/api.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
