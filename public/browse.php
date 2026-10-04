<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * ?type=found (default)   items in storage; public card grid
 * ?type=lost              every lost report; staff only
 * ?manage=1               every found item with inline status change; staff only
 */
$manage = !empty($_GET['manage']);
$type   = $manage ? 'found' : ((($_GET['type'] ?? 'found') === 'lost') ? 'lost' : 'found');

if ($manage || $type === 'lost') {
    require_role(REVIEWER_ROLES);
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
    if ($status === '') $rows = array_values(array_filter($rows, fn ($r) => !is_moderated_report($r)));   // rejected / false / spam only when asked for
    if ($from) $rows = array_values(array_filter($rows, fn ($r) => $r['date_lost'] >= $from));
    if ($to)   $rows = array_values(array_filter($rows, fn ($r) => $r['date_lost'] <= $to));
    $rows = newest_first($rows);
    [$pageRows, $page, $totalPages] = paginate($rows, 10);
} elseif ($manage) {
    $pageTitle = 'Manage found items';
    // Staff pick the order; the choice is remembered for this login so the menu link keeps it.
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
    $rows = search_rows(array_values(all_found_items()), $q, ['status' => $status, 'category' => $category]);
    usort($rows, function ($a, $b) use ($sortColumn, $sortDesc) {
        $c = strnatcasecmp((string) $a[$sortColumn], (string) $b[$sortColumn]) ?: $a['item_id'] <=> $b['item_id'];
        return $sortDesc ? -$c : $c;
    });
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
    $rows = search_rows(array_values(public_found_items()), $q, ['category' => $category]);
    if ($from) $rows = array_values(array_filter($rows, fn ($i) => $i['date_found'] >= $from));
    usort($rows, fn ($a, $b) => $sort === 'oldest'
        ? strcmp($a['date_found'], $b['date_found'])
        : strcmp($b['date_found'], $a['date_found']));
    [$pageRows, $page, $totalPages] = paginate($rows, 10);
}

include APP_ROOT . '/templates/layout/header.php';
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
    <div class="form-group filter-extra">
        <label for="status">Status</label>
        <select id="status" name="status"><option value="">Any active</option><?= options(LOST_STATUSES + REPORT_MODERATION_STATUSES, $status) ?></select>
    </div>
    <div class="form-group filter-extra">
        <label for="category">Category</label>
        <select id="category" name="category"><option value="">Any</option><?= options(CATEGORIES, $category, false) ?></select>
    </div>
    <div class="form-group filter-extra">
        <label for="from">Lost from</label>
        <input type="date" id="from" name="from" value="<?= e($from) ?>">
    </div>
    <div class="form-group filter-extra">
        <label for="to">Lost to</label>
        <input type="date" id="to" name="to" value="<?= e($to) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a class="btn btn-secondary" href="<?= e(url('/browse.php?type=lost')) ?>">Reset</a>
    <button type="button" class="btn btn-ghost filter-toggle" aria-expanded="true" data-filter-toggle hidden>More filters</button>
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
    <form method="get" action="<?= e(url('/browse.php')) ?>" class="flex gap-1 items-center" data-auto-submit>
        <input type="hidden" name="manage" value="1">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <label for="category" class="sr-only">Category</label>
        <select id="category" name="category" class="inline-select">
            <option value="">All categories</option>
            <?= options(CATEGORIES, $category, false) ?>
        </select>
        <label for="manage-sort" class="sr-only">Sort by</label>
        <select id="manage-sort" name="sort" class="inline-select">
            <?= options(array_map(fn ($s) => $s[0], $manageSorts), $sort) ?>
        </select>
        <noscript><button type="submit" class="btn btn-secondary btn-sm">Apply</button></noscript>
        <?php if ($category !== '' || $sort !== 'newest'): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/browse.php?manage=1&sort=newest' . ($status ? '&status=' . rawurlencode($status) : ''))) ?>">Clear</a>
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
                        <a href="<?= e(url('/?tab=queue&item=' . $item['item_id'])) ?>" class="badge badge-pending"><?= $pendingByItem[$item['item_id']] ?> pending</a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <form method="post" action="<?= e(url('/browse.php?manage=1')) ?>" class="inline-form" data-api="update_status" data-type="found" data-confirm="Change this item's status?">
                        <input type="hidden" name="id" value="<?= $item['item_id'] ?>">
                        <label for="status-<?= $item['item_id'] ?>" class="sr-only">Status of <?= e($item['item_name']) ?></label>
                        <select id="status-<?= $item['item_id'] ?>" name="status" class="inline-select">
                            <?= options(FOUND_STATUSES, $item['status']) ?>
                        </select>
                        <button type="submit" class="btn btn-secondary btn-sm">Save<span class="sr-only"> status of <?= e($item['item_name']) ?></span></button>
                    </form>
                </td>
                <td class="actions">
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/report.php?type=found&id=' . $item['item_id'])) ?>">Edit</a>
                    <?php if (can_delete_found_item($item)): ?>
                        <form method="post" action="<?= e(url('/browse.php?manage=1')) ?>" class="inline-form" data-api="delete_item" data-done="remove"
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
    <?= empty_state('No items here', 'Nothing has been logged with this status yet.', url('/report.php?type=found'), 'Log a found item') ?>
<?php endif; ?>

<?php else: ?>
<!-- ====================================================== FOUND ITEMS (public) -->
<div class="page-header">
    <div>
        <h1>Found items</h1>
        <p>Items currently held at the Lost &amp; Found office. See something that's yours? Open it and submit a claim.</p>
    </div>
    <div class="btn-row">
        <?php if (is_staff()): ?>
            <a class="btn btn-outline" href="<?= e(url('/browse.php?manage=1')) ?>">Manage items</a>
            <a class="btn btn-primary" href="<?= e(url('/report.php?type=found')) ?>">+ Log found item</a>
        <?php elseif (has_role('user')): ?>
            <a class="btn btn-outline" href="<?= e(url('/report.php?type=found')) ?>">+ I found something</a>
        <?php endif; ?>
    </div>
</div>

<!-- With JavaScript, results are fetched from api/get_items.php as you type (no page reload); without it, the form submits normally. -->
<form method="get" action="<?= e(url('/browse.php')) ?>" class="filter-bar" role="search" data-live-search>
    <div class="form-group grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="e.g. calculator, blue bag, ID…" autocomplete="off">
    </div>
    <div class="form-group filter-extra">
        <label for="category">Category</label>
        <select id="category" name="category"><option value="">All categories</option><?= options(CATEGORIES, $category, false) ?></select>
    </div>
    <div class="form-group filter-extra">
        <label for="from">Found since</label>
        <input type="date" id="from" name="from" value="<?= e($from) ?>">
    </div>
    <div class="form-group filter-extra">
        <label for="sort">Sort</label>
        <select id="sort" name="sort"><?= options(['newest' => 'Newest first', 'oldest' => 'Oldest first'], $sort) ?></select>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <a class="btn btn-secondary" href="<?= e(url('/browse.php')) ?>">Reset</a>
    <button type="button" class="btn btn-ghost filter-toggle" aria-expanded="true" data-filter-toggle hidden>More filters</button>
</form>

<p class="result-count" data-live-count aria-live="polite">
    <?= count($rows) ?> item<?= count($rows) === 1 ? '' : 's' ?> in storage<?= $q ? ' matching "' . e($q) . '"' : '' ?>
</p>

<div data-live-results>
<?php if ($pageRows): ?>
    <div class="item-grid">
        <?php foreach ($pageRows as $item) echo item_card($item); ?>
    </div>
    <?= pagination($page, $totalPages) ?>
<?php else: ?>
    <?= is_logged_in() && !has_role('user') ? empty_state('No items match your search', 'Try a broader keyword or clear the filters.') : empty_state(
        'No items match your search',
        "Don't see yours? File a lost report so staff can match it when it's turned in.",
        is_logged_in() ? url('/report.php') : url('/login.php?next=' . rawurlencode(url('/report.php'))),
        is_logged_in() ? 'Report a lost item' : 'Log in to report a lost item'
    ) ?>
<?php endif; ?>
</div>
<?php endif; ?>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
