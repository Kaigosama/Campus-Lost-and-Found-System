<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['staff', 'admin']);

$pageTitle = 'Manage found items';

$status   = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';

$items = mock_filter(array_values(mock_found_items()), '', ['status' => $status, 'category' => $category]);
usort($items, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

$counts = ['' => count(mock_found_items())];
foreach (FOUND_STATUSES as $key => $label) {
    $counts[$key] = count(array_filter(mock_found_items(), fn ($i) => $i['status'] === $key));
}
$pendingByItem = [];
foreach (mock_claims() as $c) {
    if ($c['status'] === 'pending') {
        $pendingByItem[$c['item_id']] = ($pendingByItem[$c['item_id']] ?? 0) + 1;
    }
}

include APP_PATH . '/views/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>Manage found items</h1>
        <p>Everything logged at intake, including returned and disposed items.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/found/intake.php')) ?>">+ Log found item</a>
</div>

<nav class="pill-tabs" aria-label="Filter by status">
    <a href="<?= e(url_with(['status' => null])) ?>" class="<?= $status === '' ? 'active' : '' ?>">All <span class="count"><?= $counts[''] ?></span></a>
    <?php foreach (FOUND_STATUSES as $key => $label): ?>
        <a href="<?= e(url_with(['status' => $key])) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= e($label) ?> <span class="count"><?= $counts[$key] ?></span></a>
    <?php endforeach; ?>
</nav>

<div class="table-tools">
    <input type="search" placeholder="Quick filter this list…" aria-label="Filter table" data-table-filter="#itemsTable">
    <form method="get" action="<?= e(url('/found/manage.php')) ?>" class="flex gap-1 items-center">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <label for="category" class="sr-only">Category</label>
        <select id="category" name="category" class="inline-select" onchange="this.form.submit()">
            <option value="">All categories</option>
            <?= options(CATEGORIES, $category, false) ?>
        </select>
    </form>
    <span class="text-sm text-muted">Showing <span data-filter-count="#itemsTable"><?= count($items) ?></span></span>
</div>

<?php if ($items): ?>
<div class="table-wrap">
    <table class="table" id="itemsTable">
        <thead>
            <tr>
                <th>#</th>
                <th></th>
                <th>Item</th>
                <th>Category</th>
                <th>Found</th>
                <th>Storage</th>
                <th>Claims</th>
                <th>Status</th>
                <th class="actions"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td class="text-muted"><?= $item['item_id'] ?></td>
                <td><?= photo_tag($item['photo_path'], $item['item_name'], 'photo-thumb') ?></td>
                <td>
                    <a class="table-title" href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>"><?= e($item['item_name']) ?></a>
                    <span class="table-sub"><?= e($item['location_found']) ?></span>
                </td>
                <td><?= e($item['category']) ?></td>
                <td class="nowrap"><?= e(format_date($item['date_found'])) ?></td>
                <td class="nowrap"><?= e($item['storage_location']) ?></td>
                <td>
                    <?php if (!empty($pendingByItem[$item['item_id']])): ?>
                        <a href="<?= e(url('/claims/queue.php?item=' . $item['item_id'])) ?>" class="badge badge-pending"><?= $pendingByItem[$item['item_id']] ?> pending</a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <form method="post" action="<?= e(url('/found/manage.php')) ?>" data-confirm="Change this item's status?">
                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                        <label for="status-<?= $item['item_id'] ?>" class="sr-only">Status</label>
                        <select id="status-<?= $item['item_id'] ?>" name="status" class="inline-select" onchange="this.form.requestSubmit()">
                            <?= options(FOUND_STATUSES, $item['status']) ?>
                        </select>
                    </form>
                </td>
                <td class="actions">
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/found/edit.php?id=' . $item['item_id'])) ?>">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="filter-empty" data-filter-empty hidden>No items match that filter.</p>
<?php else: ?>
    <?php
    $emptyTitle = 'No items here';
    $emptyText  = 'Nothing has been logged with this status yet.';
    $emptyActionUrl = url('/found/intake.php');
    $emptyActionLabel = 'Log a found item';
    include APP_PATH . '/views/partials/empty-state.php';
    ?>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
