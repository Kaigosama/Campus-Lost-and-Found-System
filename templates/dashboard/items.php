<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: found items staff; admins read-only. */ ?>
<div class="page-header">
    <div><h1>Found items</h1><p>Items in storage, student posts included. Returned and disposed items are kept under their own sections.<?= is_staff() ? '' : ' Read-only for administrators.' ?></p></div>
    <?php if (is_staff()): ?><a class="btn btn-primary" href="<?= e(url('/report.php?type=found')) ?>">+ Log found item</a><?php endif; ?>
</div>

    <?php
    // Staff pick the order; the choice is remembered for this login so the section link keeps it.
    $manageSorts = [ // key => [label, column, descending]
        'newest'     => ['Newest logged first', 'created_at', true],
        'oldest'     => ['Oldest logged first', 'created_at', false],
        'id_desc'    => ['Item # (high to low)', 'item_id', true],
        'id_asc'     => ['Item # (low to high)', 'item_id', false],
        'found_desc' => ['Date found (newest)', 'date_found', true],
        'found_asc'  => ['Date found (oldest)', 'date_found', false],
        'name'       => ['Name (A–Z)', 'item_name', false],
    ];
    $sort = $_GET['sort'] ?? $_SESSION['manage_sort'] ?? 'newest';
    $sort = isset($manageSorts[$sort]) ? $sort : 'newest';
    $_SESSION['manage_sort'] = $sort;
    [, $sortColumn, $sortDesc] = $manageSorts[$sort];
    $category = (string) ($_GET['category'] ?? '');
    $rows = search_rows(where(all_found_items(), 'status', 'stored'), '', ['category' => $category]);
    usort($rows, function ($a, $b) use ($sortColumn, $sortDesc) {
        $c = strnatcasecmp((string) $a[$sortColumn], (string) $b[$sortColumn]) ?: $a['item_id'] <=> $b['item_id'];
        return $sortDesc ? -$c : $c;
    });
    $pendingByItem = [];
    foreach (array_filter(all_claims(), 'awaiting_review') as $c) {
        $pendingByItem[$c['item_id']] = ($pendingByItem[$c['item_id']] ?? 0) + 1;
    }
    ?>
    <div class="table-tools">
        <input type="search" placeholder="Quick filter this list…" aria-label="Filter table" data-table-filter="#itemsTable">
        <form method="get" action="<?= e(url('/')) ?>" class="flex gap-1 items-center" data-auto-submit>
            <input type="hidden" name="tab" value="items">
            <label for="category" class="sr-only">Category</label>
            <select id="category" name="category" class="inline-select">
                <option value="">All categories</option>
                <?= options(category_names(), $category, false) ?>
            </select>
            <label for="manage-sort" class="sr-only">Sort by</label>
            <select id="manage-sort" name="sort" class="inline-select">
                <?= options(array_map(fn ($s) => $s[0], $manageSorts), $sort) ?>
            </select>
            <noscript><button type="submit" class="btn btn-secondary btn-sm">Apply</button></noscript>
            <?php if ($category !== '' || $sort !== 'newest'): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/?tab=items&sort=newest')) ?>">Clear</a>
            <?php endif; ?>
        </form>
        <span class="text-sm text-muted">Showing <span data-filter-count="#itemsTable"><?= count($rows) ?></span></span>
    </div>

    <?php if ($rows): ?>
    <div class="table-wrap">
        <table class="table table-manage" id="itemsTable">
            <thead><tr><th>#</th><th></th><th>Item</th><th>Found</th><th>Storage</th><th>Claims</th><th>Status</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $item): ?>
                <tr data-remove>
                    <td class="text-muted"><?= $item['item_id'] ?></td>
                    <td><?= photo_tag($item['image_url'], $item['item_name'], 'photo-thumb') ?></td>
                    <td>
                        <a class="table-title" href="<?= e(item_url('found', $item['item_id'])) ?>"><?= e($item['item_name']) ?></a>
                        <span class="table-sub"><?= e($item['category']) ?> &middot; <?= e($item['location_found']) ?></span>
                        <?php if ($item['moderation_status'] !== 'approved'): ?>
                            <span class="table-sub">Student post: <?= status_badge($item['moderation_status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="nowrap"><?= e(format_date($item['date_found'])) ?></td>
                    <td><?= e($item['storage_location']) ?></td>
                    <td>
                        <?php if (!empty($pendingByItem[$item['item_id']])): ?>
                            <a href="<?= e(url('/?tab=queue&item=' . $item['item_id'])) ?>" class="badge badge-pending"><?= $pendingByItem[$item['item_id']] ?> to review</a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td><?= status_badge($item['status']) ?></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="<?= e(item_url('found', $item['item_id'])) ?>">View<span class="sr-only"> <?= e($item['item_name']) ?></span></a>
                        <?php if (is_staff()): ?>
                            <a class="btn btn-outline btn-sm" href="<?= e(url('/report.php?type=found&id=' . $item['item_id'])) ?>">Edit<span class="sr-only"> <?= e($item['item_name']) ?></span></a>
                        <?php endif; ?>
                        <?php if (is_staff() && can_delete_found_item($item)): ?>
                            <form method="post" action="<?= e(url('/?tab=items')) ?>" class="inline-form" data-api="delete_item" data-done="remove"
                                  data-confirm="Delete &quot;<?= e($item['item_name']) ?>&quot;? Use this only for an item logged by mistake. Any pending claims on it are deleted too. This can't be undone.">
                                <input type="hidden" name="id" value="<?= $item['item_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete<span class="sr-only"> <?= e($item['item_name']) ?></span></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="filter-empty" data-filter-empty hidden>No items match that filter.</p>
    <?php else: ?>
        <?= is_staff() ? empty_state('No items in storage', $category !== '' ? 'Nothing in storage in that category.' : 'Items logged at intake show up here.', url('/report.php?type=found'), 'Log a found item')
                       : empty_state('No items in storage', $category !== '' ? 'Nothing in storage in that category.' : 'Items logged at intake show up here.') ?>
    <?php endif; ?>
