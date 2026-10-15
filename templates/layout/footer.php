<?php defined('APP_ROOT') || exit; ?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Academic project for Web Systems and Technologies 2 (ITS122P, Group 4).
            All items, people and reports shown are fictional demonstration data.
        </p>
        <p>Cookies: one necessary cookie, set only when you log in, keeps you signed in. No advertising or tracking.
            The CAPTCHA on log-in and registration is Cloudflare Turnstile.</p>
    </div>
</footer>

<script type="application/json" id="clafs-config"><?= json_encode([
    'statusLabels' => status_labels() + ['active' => 'Active', 'inactive' => 'Deactivated'],
    'emailTypos'   => ['domains' => EMAIL_DOMAIN_TYPOS, 'only' => EMAIL_ONLY_DOMAINS, 'tlds' => EMAIL_TLD_TYPOS],
], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('js/api.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
