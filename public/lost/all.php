<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['staff', 'admin']);

$pageTitle = 'All lost reports';

$q        = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$from     = $_GET['from'] ?? '';
$to       = $_GET['to'] ?? '';

$reports = mock_filter(array_values(mock_lost_reports()), $q, ['status' => $status, 'category' => $category]);
if ($from) $reports = array_values(array_filter($reports, fn ($r) => $r['date_lost'] >= $from));
if ($to)   $reports = array_values(array_filter($reports, fn ($r) => $r['date_lost'] <= $to));
usort($reports, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

[$pageItems, $page, $totalPages] = paginate($reports, 10);

include APP_PATH . '/views/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>All lost reports</h1>
        <p>Reports filed by students and faculty. Use these to match new intake items.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/found/manage.php')) ?>">Manage found items</a>
</div>

<form method="get" action="<?= e(url('/lost/all.php')) ?>" class="filter-bar" role="search">
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
    <a class="btn btn-secondary" href="<?= e(url('/lost/all.php')) ?>">Reset</a>
</form>

<p class="result-count"><?= count($reports) ?> report<?= count($reports) === 1 ? '' : 's' ?> found</p>

<?php if ($pageItems): ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Reported by</th>
                <th>Category</th>
                <th>Lost on</th>
                <th>Where</th>
                <th>Status</th>
                <th class="actions"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pageItems as $report): $owner = mock_user($report['user_id']); ?>
            <tr>
                <td class="text-muted"><?= $report['report_id'] ?></td>
                <td>
                    <a class="table-title" href="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>"><?= e($report['item_name']) ?></a>
                    <span class="table-sub"><?= e(excerpt($report['description'], 60)) ?></span>
                </td>
                <td><?= e(full_name($owner)) ?><span class="table-sub"><?= e($owner['email']) ?></span></td>
                <td><?= e($report['category']) ?></td>
                <td class="nowrap"><?= e(format_date($report['date_lost'])) ?></td>
                <td><?= e($report['location_lost']) ?></td>
                <td><?= status_badge($report['status']) ?></td>
                <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include APP_PATH . '/views/partials/pagination.php'; ?>
<?php else: ?>
    <?php
    $emptyTitle = 'No reports match those filters';
    $emptyText  = 'Try a broader keyword or clear the filters.';
    $emptyActionUrl = url('/lost/all.php');
    $emptyActionLabel = 'Clear filters';
    include APP_PATH . '/views/partials/empty-state.php';
    ?>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
