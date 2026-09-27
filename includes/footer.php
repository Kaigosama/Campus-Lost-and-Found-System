<?php defined('APP_ROOT') || exit; ?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Academic project for Web Systems and Technologies 2 (ITS122P, Group 4), Mapua University.
            All items, people and reports shown are fictional demonstration data.
        </p>
    </div>
</footer>

<script>window.CLAFS = <?= json_encode(['statusLabels' => LOST_STATUSES + FOUND_STATUSES + CLAIM_STATUSES + ['active' => 'Active', 'inactive' => 'Deactivated']]) ?>;</script>
<script src="<?= e(asset('js/api.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
