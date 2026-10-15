<?php defined('APP_ROOT') || exit; ?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Academic project for Web Systems and Technologies 2 (ITS122P, Group 4).
            All items, people and reports shown are fictional demonstration data.
        </p>
        <button type="button" class="link-button" data-cookie-settings>Cookie settings</button>
    </div>
</footer>

<?php /* Shown by app.js until the visitor chooses; the choice is kept in this browser's localStorage, not a cookie. */ ?>
<section class="cookie-banner" role="region" aria-labelledby="cookie-title" data-cookie-banner hidden>
    <div class="container cookie-inner">
        <div>
            <h2 id="cookie-title">Cookies on <?= e(APP_NAME) ?></h2>
            <p>
                We use <strong>one necessary cookie</strong>, set only when you log in, to keep you signed in and protect your session.
                If you accept, we also keep <strong>an anonymous visitor ID</strong> while you browse without logging in, so the office can count
                visits and spot repeated failed log-ins. It holds no name, email or IP address, stays in our own database, and is deleted after
                <?= VISITOR_RETENTION_DAYS ?> days without a visit. No advertising or third-party tracking. The CAPTCHA on the log-in and
                registration forms is provided by Cloudflare Turnstile.
            </p>
            <p class="text-sm mb-0">If you reject cookies (now or later, through Cookie settings) any visitor record is deleted. You can still browse found items,
                but you can't log in, because logging in needs the session cookie.</p>
        </div>
        <div class="btn-row">
            <button type="button" class="btn btn-primary" data-cookie-choice="accepted">Accept</button>
            <button type="button" class="btn btn-secondary" data-cookie-choice="rejected">Reject</button>
        </div>
    </div>
</section>

<script type="application/json" id="clafs-config"><?= json_encode(['statusLabels' => status_labels() + ['active' => 'Active', 'inactive' => 'Deactivated']], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('js/api.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
