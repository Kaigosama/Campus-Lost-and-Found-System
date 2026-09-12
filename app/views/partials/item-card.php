<?php
/**
 * Found-item card for the browse grid. Expects $item (found_items row).
 * Only PUBLIC fields are rendered here — never storage_location or private_details.
 */
?>
<article class="item-card">
    <a class="item-card-photo" href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>">
        <?= photo_tag($item['photo_path'], $item['item_name']) ?>
    </a>
    <div class="item-card-body">
        <span class="item-card-category"><?= e($item['category']) ?></span>
        <h3 class="item-card-title">
            <a href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>"><?= e($item['item_name']) ?></a>
        </h3>
        <p class="item-card-desc"><?= e(excerpt($item['description'])) ?></p>
        <dl class="item-card-meta">
            <div><dt>Found</dt><dd><?= e(format_date($item['date_found'])) ?></dd></div>
            <div><dt>Where</dt><dd><?= e($item['location_found']) ?></dd></div>
        </dl>
    </div>
    <div class="item-card-footer">
        <?= status_badge($item['status']) ?>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>">View details</a>
    </div>
</article>
