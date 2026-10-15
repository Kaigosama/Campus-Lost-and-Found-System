<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: disposed items staff; admins read-only. */ ?>
<div class="page-header">
    <div><h1>Disposed items</h1><p>Items formally removed from inventory under the disposal policy, with who disposed of them, when and why. Not the same as returned items.</p></div>
</div>

    <?php
    $q        = trim((string) ($_GET['q'] ?? ''));
    $category = (string) ($_GET['category'] ?? '');
    $from     = (string) ($_GET['from'] ?? '');
    $to       = (string) ($_GET['to'] ?? '');
    $rows = search_rows(where(all_found_items(), 'status', 'disposed'), $q, ['category' => $category]);
    // Filtered by disposal date; items disposed before disposal records existed have none and show only without a date filter.
    if ($from !== '') $rows = array_values(array_filter($rows, fn ($i) => $i['disposed_at'] && substr($i['disposed_at'], 0, 10) >= $from));
    if ($to !== '')   $rows = array_values(array_filter($rows, fn ($i) => $i['disposed_at'] && substr($i['disposed_at'], 0, 10) <= $to));
    usort($rows, fn ($a, $b) => strcmp((string) $b['disposed_at'], (string) $a['disposed_at']) ?: $b['item_id'] <=> $a['item_id']);
    [$pageRows, $page, $totalPages] = paginate($rows, 10);
    ?>
    <form method="get" action="<?= e(url('/')) ?>" class="filter-bar" role="search">
        <input type="hidden" name="tab" value="disposed">
        <div class="form-group grow">
            <label for="q">Keyword</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Item, description, location…">
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category"><option value="">Any</option><?= options(category_names(), $category, false) ?></select>
        </div>
        <div class="form-group">
            <label for="from">Disposed from</label>
            <input type="date" id="from" name="from" value="<?= e($from) ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
            <label for="to">Disposed to</label>
            <input type="date" id="to" name="to" value="<?= e($to) ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a class="btn btn-secondary" href="<?= e(url('/?tab=disposed')) ?>">Reset</a>
    </form>
    <p class="result-count"><?= count($rows) ?> disposed item<?= count($rows) === 1 ? '' : 's' ?></p>
    <?php if ($pageRows): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th></th><th>Item</th><th>Found</th><th>Disposed</th><th>Reason</th><th>Disposed by</th><th>Claims</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($pageRows as $item): $by = find_user($item['disposed_by']); $onRecord = count(where(all_claims(), 'item_id', $item['item_id'])); ?>
                <tr>
                    <td class="text-muted"><?= $item['item_id'] ?></td>
                    <td><?= photo_tag($item['image_url'], $item['item_name'], 'photo-thumb') ?></td>
                    <td><a class="table-title" href="<?= e(item_url('found', $item['item_id'])) ?>"><?= e($item['item_name']) ?></a>
                        <span class="table-sub"><?= e($item['category']) ?> &middot; <?= e($item['location_found']) ?></span></td>
                    <td class="nowrap"><?= e(format_date($item['date_found'])) ?></td>
                    <td class="nowrap"><?= $item['disposed_at'] ? e(format_datetime($item['disposed_at'])) : '<span class="text-muted">Not recorded</span>' ?></td>
                    <td class="cell-wide"><?= $item['disposal_reason'] ? e(excerpt($item['disposal_reason'], 90)) : '<span class="text-muted">Not recorded</span>' ?></td>
                    <td><?= $by ? e(full_name($by)) : '<span class="text-muted">Not recorded</span>' ?></td>
                    <td><?= $onRecord ? $onRecord . ' on record' : '<span class="text-muted">None</span>' ?></td>
                    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(item_url('found', $item['item_id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination($page, $totalPages) ?>
    <?php else: ?>
        <?= empty_state('No disposed items', $q !== '' || $category !== '' || $from !== '' || $to !== '' ? 'Nothing matches those filters.' : 'Items staff dispose of show up here with the reason and who disposed of them.') ?>
    <?php endif; ?>
