<?php
require_once __DIR__ . '/../../app/bootstrap.php';
// Browsing is public; claiming requires login (handled on view/submit pages).

$pageTitle = 'Found items';

$q        = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
$from     = $_GET['from'] ?? '';
$sort     = $_GET['sort'] ?? 'newest';

// Only items still in storage are shown publicly.
$items = mock_filter(array_values(mock_found_items()), $q, ['status' => 'stored', 'category' => $category]);
if ($from) {
    $items = array_values(array_filter($items, fn ($i) => $i['date_found'] >= $from));
}
usort($items, fn ($a, $b) => $sort === 'oldest'
    ? strcmp($a['date_found'], $b['date_found'])
    : strcmp($b['date_found'], $a['date_found']));

[$pageItems, $page, $totalPages] = paginate($items, 8);

include APP_PATH . '/views/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>Found items</h1>
        <p>Items currently held at the Lost &amp; Found office. See something that's yours? Open it and submit a claim.</p>
    </div>
    <?php if (is_staff()): ?>
        <a class="btn btn-primary" href="<?= e(url('/found/intake.php')) ?>">+ Log found item</a>
    <?php endif; ?>
</div>

<form method="get" action="<?= e(url('/found/browse.php')) ?>" class="filter-bar" role="search">
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
    <a class="btn btn-secondary" href="<?= e(url('/found/browse.php')) ?>">Reset</a>
</form>

<p class="result-count">
    <?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> in storage<?= $q ? ' matching "' . e($q) . '"' : '' ?>
</p>

<?php if ($pageItems): ?>
    <div class="item-grid">
        <?php foreach ($pageItems as $item): include APP_PATH . '/views/partials/item-card.php'; endforeach; ?>
    </div>
    <?php include APP_PATH . '/views/partials/pagination.php'; ?>
<?php else: ?>
    <?php
    $emptyTitle = 'No items match your search';
    $emptyText  = "Don't see yours? File a lost report so staff can match it when it's turned in.";
    $emptyActionUrl = url('/lost/report.php');
    $emptyActionLabel = 'Report a lost item';
    include APP_PATH . '/views/partials/empty-state.php';
    ?>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
