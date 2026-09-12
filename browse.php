<?php
require_once __DIR__ . '/config/db_connect.php';

/**
 * Campus inventory — search and filter.
 *   ?type=found (default)   items still in storage; public; card grid
 *   ?type=lost              every lost report; staff only; table used to match new intake
 *   ?manage=1               every found item incl. returned/disposed, with inline status + edit; staff only
 */
$manage = !empty($_GET['manage']);
$type   = $manage ? 'found' : ((($_GET['type'] ?? 'found') === 'lost') ? 'lost' : 'found');

if ($manage || $type === 'lost') {
    require_role(['staff', 'admin']);
}

$q        = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
$status   = $_GET['status'] ?? '';
$from     = $_GET['from'] ?? '';
$to       = $_GET['to'] ?? '';
$sort     = $_GET['sort'] ?? 'newest';

if ($type === 'lost') {
    $pageTitle = 'All lost reports';
    $rows = search_rows(array_values(all_lost_reports()), $q, ['status' => $status, 'category' => $category]);
    if ($from) $rows = array_values(array_filter($rows, fn ($r) => $r['date_lost'] >= $from));
    if ($to)   $rows = array_values(array_filter($rows, fn ($r) => $r['date_lost'] <= $to));
    $rows = newest_first($rows);
    [$pageRows, $page, $totalPages] = paginate($rows, 10);
} elseif ($manage) {
    $pageTitle = 'Manage found items';
    $rows = newest_first(search_rows(array_values(all_found_items()), $q, ['status' => $status, 'category' => $category]));
    $counts = ['' => count(all_found_items())];
    foreach (FOUND_STATUSES as $key => $label) {
        $counts[$key] = count_where(all_found_items(), 'status', $key);
    }
    $pendingByItem = [];
    foreach (where(all_claims(), 'status', 'pending') as $c) {
        $pendingByItem[$c['item_id']] = ($pendingByItem[$c['item_id']] ?? 0) + 1;
    }
} else {
    $pageTitle = 'Found items';
    // Only items still in storage are shown publicly.
    $rows = search_rows(array_values(all_found_items()), $q, ['status' => 'stored', 'category' => $category]);
    if ($from) $rows = array_values(array_filter($rows, fn ($i) => $i['date_found'] >= $from));
    usort($rows, fn ($a, $b) => $sort === 'oldest'
        ? strcmp($a['date_found'], $b['date_found'])
        : strcmp($b['date_found'], $a['date_found']));
    [$pageRows, $page, $totalPages] = paginate($rows, 8);
}

include APP_ROOT . '/includes/header.php';
?>

<?php if ($type === 'lost'): ?>
<!-- ====================================================== LOST REPORTS (staff) -->
<div class="page-header">
    <div>
        <h1>All lost reports</h1>
        <p>Reports filed by students and faculty. Use these to match new intake items.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/browse.php?manage=1')) ?>">Manage found items</a>
</div>

<form method="get" action="<?= e(url('/browse.php')) ?>" class="filter-bar" role="search">
    <input type="hidden" name="type" value="lost">
    <div class="form-group grow">
        <label for="q">Keyword</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Item, description, location…">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status"><option value="">Any</option><?= options(LOST_STATUSES, $status) ?></select>
    </div>
    <div class="form-group">
        <label for="category">Category</label>
        <select id="category" name="category"><option value="">Any</option><?= options(CATEGORIES, $category, false) ?></select>
    </div>
    <div class="form-group">
        <label for="from">Lost from</label>
        <input type="date" id="from" name="from" value="<?= e($from) ?>">
    </div>
    <div class="form-group">
        <label for="to">Lost to</label>
        <input type="date" id="to" name="to" value="<?= e($to) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a class="btn btn-secondary" href="<?= e(url('/browse.php?type=lost')) ?>">Reset</a>
</form>

<p class="result-count"><?= count($rows) ?> report<?= count($rows) === 1 ? '' : 's' ?> found</p>

<?php if ($pageRows): ?>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>#</th><th>Item</th><th>Reported by</th><th>Category</th><th>Lost on</th><th>Where</th><th>Status</th><th class="actions"></th></tr></thead>
        <tbody>
        <?php foreach ($pageRows as $report): $owner = find_user($report['user_id']); ?>
            <tr>
                <td class="text-muted"><?= $report['report_id'] ?></td>
                <td>
                    <a class="table-title" href="<?= e(item_url('lost', $report['report_id'])) ?>"><?= e($report['item_name']) ?></a>
                    <span class="table-sub"><?= e(excerpt($report['description'], 60)) ?></span>
                </td>
                <td><?= e(full_name($owner)) ?><span class="table-sub"><?= e($owner['email']) ?></span></td>
                <td><?= e($report['category']) ?></td>
                <td class="nowrap"><?= e(format_date($report['date_lost'])) ?></td>
                <td><?= e($report['location_lost']) ?></td>
                <td><?= status_badge($report['status']) ?></td>
                <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(item_url('lost', $report['report_id'])) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= pagination($page, $totalPages) ?>
<?php else: ?>
    <?= empty_state('No reports match those filters', 'Try a broader keyword or clear the filters.', url('/browse.php?type=lost'), 'Clear filters') ?>
<?php endif; ?>

<?php elseif ($manage): ?>
<!-- ====================================================== MANAGE FOUND ITEMS (staff) -->
<div class="page-header">
    <div>
        <h1>Manage found items</h1>
        <p>Everything logged at intake, including returned and disposed items.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/report.php?type=found')) ?>">+ Log found item</a>
</div>

<?= pill_tabs(['' => 'All'] + FOUND_STATUSES, $counts, $status, 'status') ?>

<div class="table-tools">
    <input type="search" placeholder="Quick filter this list…" aria-label="Filter table" data-table-filter="#itemsTable">
    <form method="get" action="<?= e(url('/browse.php')) ?>" class="flex gap-1 items-center">
        <input type="hidden" name="manage" value="1">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <label for="category" class="sr-only">Category</label>
        <select id="category" name="category" class="inline-select" onchange="this.form.submit()">
            <option value="">All categories</option>
            <?= options(CATEGORIES, $category, false) ?>
        </select>
    </form>
    <span class="text-sm text-muted">Showing <span data-filter-count="#itemsTable"><?= count($rows) ?></span></span>
</div>

<?php if ($rows): ?>
<div class="table-wrap">
    <table class="table" id="itemsTable">
        <thead><tr><th>#</th><th></th><th>Item</th><th>Category</th><th>Found</th><th>Storage</th><th>Claims</th><th>Status</th><th class="actions"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $item): ?>
            <tr>
                <td class="text-muted"><?= $item['item_id'] ?></td>
                <td><?= photo_tag($item['image_url'], $item['item_name'], 'photo-thumb') ?></td>
                <td>
                    <a class="table-title" href="<?= e(item_url('found', $item['item_id'])) ?>"><?= e($item['item_name']) ?></a>
                    <span class="table-sub"><?= e($item['location_found']) ?></span>
                </td>
                <td><?= e($item['category']) ?></td>
                <td class="nowrap"><?= e(format_date($item['date_found'])) ?></td>
                <td class="nowrap"><?= e($item['storage_location']) ?></td>
                <td>
                    <?php if (!empty($pendingByItem[$item['item_id']])): ?>
                        <a href="<?= e(url('/?tab=queue&item=' . $item['item_id'])) ?>" class="badge badge-pending"><?= $pendingByItem[$item['item_id']] ?> pending</a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <!-- Submits through api/update_status.php (see app.js); falls back to a normal POST without JS. -->
                    <form method="post" action="<?= e(url('/browse.php?manage=1')) ?>" data-api="update_status" data-type="found" data-confirm="Change this item's status?">
                        <input type="hidden" name="id" value="<?= $item['item_id'] ?>">
                        <label for="status-<?= $item['item_id'] ?>" class="sr-only">Status</label>
                        <select id="status-<?= $item['item_id'] ?>" name="status" class="inline-select" onchange="this.form.requestSubmit()">
                            <?= options(FOUND_STATUSES, $item['status']) ?>
                        </select>
                    </form>
                </td>
                <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('/report.php?type=found&id=' . $item['item_id'])) ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="filter-empty" data-filter-empty hidden>No items match that filter.</p>
<?php else: ?>
    <?= empty_state('No items here', 'Nothing has been logged with this status yet.', url('/report.php?type=found'), 'Log a found item') ?>
<?php endif; ?>

<?php else: ?>
<!-- ====================================================== FOUND ITEMS (public) -->
<div class="page-header">
    <div>
        <h1>Found items</h1>
        <p>Items currently held at the Lost &amp; Found office. See something that's yours? Open it and submit a claim.</p>
    </div>
    <?php if (is_staff()): ?>
        <div class="btn-row">
            <a class="btn btn-outline" href="<?= e(url('/browse.php?manage=1')) ?>">Manage items</a>
            <a class="btn btn-primary" href="<?= e(url('/report.php?type=found')) ?>">+ Log found item</a>
        </div>
    <?php endif; ?>
</div>

<form method="get" action="<?= e(url('/browse.php')) ?>" class="filter-bar" role="search">
    <div class="form-group grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="e.g. calculator, blue bag, ID…">
    </div>
    <div class="form-group">
        <label for="category">Category</label>
        <select id="category" name="category"><option value="">All categories</option><?= options(CATEGORIES, $category, false) ?></select>
    </div>
    <div class="form-group">
        <label for="from">Found since</label>
        <input type="date" id="from" name="from" value="<?= e($from) ?>">
    </div>
    <div class="form-group">
        <label for="sort">Sort</label>
        <select id="sort" name="sort"><?= options(['newest' => 'Newest first', 'oldest' => 'Oldest first'], $sort) ?></select>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <a class="btn btn-secondary" href="<?= e(url('/browse.php')) ?>">Reset</a>
</form>

<p class="result-count">
    <?= count($rows) ?> item<?= count($rows) === 1 ? '' : 's' ?> in storage<?= $q ? ' matching "' . e($q) . '"' : '' ?>
</p>

<?php if ($pageRows): ?>
    <div class="item-grid">
        <?php foreach ($pageRows as $item) echo item_card($item); ?>
    </div>
    <?= pagination($page, $totalPages) ?>
<?php else: ?>
    <?= empty_state(
        'No items match your search',
        "Don't see yours? File a lost report so staff can match it when it's turned in.",
        is_logged_in() ? url('/report.php') : url('/#account'),
        is_logged_in() ? 'Report a lost item' : 'Log in to report a lost item'
    ) ?>
<?php endif; ?>
<?php endif; ?>

<?php include APP_ROOT . '/includes/footer.php'; ?>
