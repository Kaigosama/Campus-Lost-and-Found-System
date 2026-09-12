<?php
/**
 * Expects $emptyTitle, $emptyText; optional $emptyActionUrl + $emptyActionLabel.
 */
?>
<div class="empty-state">
    <div class="empty-icon" aria-hidden="true">&#128269;</div>
    <h2><?= e($emptyTitle ?? 'Nothing here yet') ?></h2>
    <?php if (!empty($emptyText)): ?><p><?= e($emptyText) ?></p><?php endif; ?>
    <?php if (!empty($emptyActionUrl)): ?>
        <p><a class="btn btn-primary" href="<?= e($emptyActionUrl) ?>"><?= e($emptyActionLabel ?? 'Continue') ?></a></p>
    <?php endif; ?>
</div>
