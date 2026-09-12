<?php
$previewRole = preview_role();
$previewRoles = ['guest' => 'Guest', 'user' => 'User', 'staff' => 'Staff', 'admin' => 'Admin'];
?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; Mapua University &middot; ITS122P Group 4</p>
        <p class="footer-links">
            <a href="<?= e(url('/found/browse.php')) ?>">Found Items</a>
            <a href="<?= e(url('/lost/report.php')) ?>">Report Lost Item</a>
            <a href="<?= e(url('/login.php')) ?>">Log in</a>
        </p>
    </div>
</footer>

<!-- Front-end preview only: switches the mock user. Remove when real login exists. -->
<div class="preview-bar" role="region" aria-label="Preview role switcher">
    <span class="preview-label">Preview as:</span>
    <?php foreach ($previewRoles as $key => $label): ?>
        <a href="<?= e(url_with(['as' => $key])) ?>" class="<?= $previewRole === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <span class="preview-note">mock data &middot; no database connected</span>
</div>

<script src="<?= e(asset('js/nav.js')) ?>"></script>
<script src="<?= e(asset('js/validation.js')) ?>"></script>
<script src="<?= e(asset('js/image-preview.js')) ?>"></script>
<script src="<?= e(asset('js/table-filter.js')) ?>"></script>
<script src="<?= e(asset('js/confirm.js')) ?>"></script>
</body>
</html>
