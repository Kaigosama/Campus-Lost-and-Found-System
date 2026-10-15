<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Public card grid of items in storage, for guests, students and faculty.
 * Staff and admins work from the dashboard (index.php?tab=items), so they are sent there.
 */
if (can_monitor()) {
    header('Location: ' . dashboard_url('items'));
    exit;
}

$q        = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
$from     = $_GET['from'] ?? '';
$sort     = $_GET['sort'] ?? 'newest';

$pageTitle = 'Found items';
if ($from !== '') $from = max($from, earliest_item_date());   // items are dated within the past year
$rows = search_rows(array_values(public_found_items()), $q, ['category' => $category]);
if ($from) $rows = array_values(array_filter($rows, fn ($i) => $i['date_found'] >= $from));
usort($rows, fn ($a, $b) => $sort === 'oldest'
    ? strcmp($a['date_found'], $b['date_found'])
    : strcmp($b['date_found'], $a['date_found']));
[$pageRows, $page, $totalPages] = paginate($rows, 10);

include APP_ROOT . '/templates/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>Found items</h1>
        <p>Items currently held at the Lost &amp; Found office. See something that's yours? Open it and submit a claim.</p>
    </div>
    <?php if (has_role('user')): ?>
        <a class="btn btn-outline" href="<?= e(url('/report.php?type=found')) ?>">+ I found something</a>
    <?php endif; ?>
</div>

<!-- With JavaScript, app.js fetches this page as you type and swaps in [data-live-results] (no reload); without it, the form submits normally. -->
<form method="get" action="<?= e(url('/browse.php')) ?>" class="filter-bar" role="search" data-live-search>
    <div class="form-group grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="e.g. calculator, blue bag, ID…" autocomplete="off">
    </div>
    <div class="form-group filter-extra">
        <label for="category">Category</label>
        <select id="category" name="category"><option value="">All categories</option><?= options(category_names(), $category, false) ?></select>
    </div>
    <div class="form-group filter-extra">
        <label for="from">Found since</label>
        <input type="date" id="from" name="from" value="<?= e($from) ?>" min="<?= earliest_item_date() ?>" max="<?= date('Y-m-d') ?>">
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
    <?= empty_state(
        'No items match your search',
        "Don't see yours? File a lost report so staff can match it when it's turned in.",
        is_logged_in() ? url('/report.php') : url('/login.php?next=' . rawurlencode(url('/report.php'))),
        is_logged_in() ? 'Report a lost item' : 'Log in to report a lost item'
    ) ?>
<?php endif; ?>
</div>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
