<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();

$user = current_user();
$pageTitle = 'My lost reports';

$statusFilter = $_GET['status'] ?? '';
$reports = array_values(array_filter(mock_lost_reports(), fn ($r) => $r['user_id'] === $user['user_id']));
usort($reports, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

$counts = ['' => count($reports)];
foreach (LOST_STATUSES as $key => $label) {
    $counts[$key] = count(array_filter($reports, fn ($r) => $r['status'] === $key));
}
if ($statusFilter !== '') {
    $reports = array_values(array_filter($reports, fn ($r) => $r['status'] === $statusFilter));
}

include APP_PATH . '/views/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>My lost reports</h1>
        <p>Everything you've reported, newest first.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/lost/report.php')) ?>">+ New report</a>
</div>

<nav class="pill-tabs" aria-label="Filter by status">
    <a href="<?= e(url_with(['status' => null])) ?>" class="<?= $statusFilter === '' ? 'active' : '' ?>">All <span class="count"><?= $counts[''] ?></span></a>
    <?php foreach (LOST_STATUSES as $key => $label): ?>
        <a href="<?= e(url_with(['status' => $key])) ?>" class="<?= $statusFilter === $key ? 'active' : '' ?>"><?= e($label) ?> <span class="count"><?= $counts[$key] ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($reports): ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th></th>
                <th>Item</th>
                <th>Category</th>
                <th>Lost on</th>
                <th>Where</th>
                <th>Status</th>
                <th class="actions"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reports as $report): ?>
            <tr>
                <td><?= photo_tag($report['photo_path'], $report['item_name'], 'photo-thumb') ?></td>
                <td>
                    <a class="table-title" href="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>"><?= e($report['item_name']) ?></a>
                    <span class="table-sub"><?= e(excerpt($report['description'], 70)) ?></span>
                </td>
                <td><?= e($report['category']) ?></td>
                <td class="nowrap"><?= e(format_date($report['date_lost'])) ?></td>
                <td><?= e($report['location_lost']) ?></td>
                <td><?= status_badge($report['status']) ?></td>
                <td class="actions">
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>">View</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <?php
    $emptyTitle = $statusFilter ? 'No ' . strtolower(status_label($statusFilter)) . ' reports' : 'No lost reports yet';
    $emptyText  = 'When you report a lost item it will show up here with its current status.';
    $emptyActionUrl = url('/lost/report.php');
    $emptyActionLabel = 'Report a lost item';
    include APP_PATH . '/views/partials/empty-state.php';
    ?>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
