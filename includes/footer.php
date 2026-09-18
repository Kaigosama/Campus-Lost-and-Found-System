<?php defined('APP_ROOT') || exit; ?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; Mapua University &middot; ITS122P Group 4</p>
        <p class="footer-links">
            <a href="<?= e(url('/browse.php')) ?>">Found Items</a>
            <a href="<?= e(url('/report.php')) ?>">Report Lost Item</a>
            <?php if (is_logged_in()): ?>
                <a href="<?= e(url('/?action=logout')) ?>">Log out</a>
            <?php else: ?>
                <a href="<?= e(url('/#account')) ?>">Log in</a>
            <?php endif; ?>
        </p>
    </div>
</footer>

<script src="<?= e(asset('js/api.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
