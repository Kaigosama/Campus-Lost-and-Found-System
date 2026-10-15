<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: returned items staff; admins read-only. */ ?>
<div class="page-header">
    <div><h1>Returned items</h1><p>Every hand-over to an owner, newest first, with the claim, the ID check and the staff involved.</p></div>
</div>

    <?php $returns = successful_returns(); ?>
    <?php if ($returns): ?>
    <div class="table-tools">
        <input type="search" placeholder="Filter by item, claimant, staff…" aria-label="Filter returned items" data-table-filter="#returnsTable">
        <span class="text-sm text-muted">Showing <span data-filter-count="#returnsTable"><?= count($returns) ?></span></span>
    </div>
    <?php endif; ?>
    <?= returns_table($returns) ?>
    <p class="filter-empty" data-filter-empty hidden>No returned items match that filter.</p>
