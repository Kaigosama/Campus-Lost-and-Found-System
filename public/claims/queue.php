<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['staff', 'admin']);

$pageTitle = 'Claims queue';

$status = $_GET['status'] ?? 'pending';
$itemFilter = (int) ($_GET['item'] ?? 0);

$claims = array_values(mock_claims());
if ($status !== 'all') {
    $claims = array_values(array_filter($claims, fn ($c) => $c['status'] === $status));
}
if ($itemFilter) {
    $claims = array_values(array_filter($claims, fn ($c) => $c['item_id'] === $itemFilter));
}
// Oldest pending first so nothing gets buried; otherwise newest first.
usort($claims, fn ($a, $b) => $status === 'pending'
    ? strcmp($a['created_at'], $b['created_at'])
    : strcmp($b['created_at'], $a['created_at']));

$counts = ['all' => count(mock_claims())];
foreach (CLAIM_STATUSES as $key => $label) {
    $counts[$key] = count(array_filter(mock_claims(), fn ($c) => $c['status'] === $key));
}

include APP_PATH . '/views/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>Claims queue</h1>
        <p>Compare each claimant's proof with the private details logged at intake.</p>
    </div>
</div>

<nav class="pill-tabs" aria-label="Filter by status">
    <?php foreach (CLAIM_STATUSES as $key => $label): ?>
        <a href="<?= e(url_with(['status' => $key, 'item' => null])) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= e($label) ?> <span class="count"><?= $counts[$key] ?></span></a>
    <?php endforeach; ?>
    <a href="<?= e(url_with(['status' => 'all', 'item' => null])) ?>" class="<?= $status === 'all' ? 'active' : '' ?>">All <span class="count"><?= $counts['all'] ?></span></a>
</nav>

<?php if ($itemFilter && ($fi = mock_found_item($itemFilter))): ?>
    <div class="alert alert-info">
        Showing claims for <strong><?= e($fi['item_name']) ?></strong> only.
        <a href="<?= e(url_with(['item' => null])) ?>">Show all items</a>
    </div>
<?php endif; ?>

<?php if ($claims): ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Claimant</th>
                <th>Proof (excerpt)</th>
                <th>Linked report</th>
                <th>Submitted</th>
                <th>Status</th>
                <th class="actions"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($claims as $claim): $item = mock_found_item($claim['item_id']); $claimant = mock_user($claim['user_id']); ?>
            <tr>
                <td class="text-muted"><?= $claim['claim_id'] ?></td>
                <td>
                    <a class="table-title" href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>"><?= e($item['item_name']) ?></a>
                    <span class="table-sub"><?= e($item['storage_location']) ?></span>
                </td>
                <td><?= e(full_name($claimant)) ?><span class="table-sub"><?= e($claimant['email']) ?></span></td>
                <td style="max-width:280px"><?= e(excerpt($claim['proof_description'], 80)) ?></td>
                <td>
                    <?php if ($claim['lost_report_id']): ?>
                        <a href="<?= e(url('/lost/view.php?id=' . $claim['lost_report_id'])) ?>">#<?= $claim['lost_report_id'] ?></a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="nowrap"><?= e(format_date($claim['created_at'])) ?></td>
                <td><?= status_badge($claim['status']) ?></td>
                <td class="actions">
                    <a class="btn <?= $claim['status'] === 'pending' ? 'btn-primary' : 'btn-outline' ?> btn-sm" href="<?= e(url('/claims/review.php?id=' . $claim['claim_id'])) ?>">
                        <?= $claim['status'] === 'pending' ? 'Review' : 'Open' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <?php
    $emptyTitle = $status === 'pending' ? 'Queue is clear' : 'No ' . ($status === 'all' ? '' : $status . ' ') . 'claims';
    $emptyText  = $status === 'pending' ? 'There are no claims waiting for review right now.' : '';
    include APP_PATH . '/views/partials/empty-state.php';
    ?>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
