<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role('admin');

$pageTitle = 'Statistics';

// ---- Mock aggregates (later: GROUP BY queries) ----
$totalItems    = count(mock_found_items());
$storedCount   = count(array_filter(mock_found_items(), fn ($i) => $i['status'] === 'stored'));
$returnedCount = count(array_filter(mock_found_items(), fn ($i) => $i['status'] === 'returned'));
$disposedCount = count(array_filter(mock_found_items(), fn ($i) => $i['status'] === 'disposed'));
$totalReports  = count(mock_lost_reports());
$openReports   = count(array_filter(mock_lost_reports(), fn ($r) => $r['status'] === 'open'));
$totalClaims   = count(mock_claims());
$approvedClaims = count(array_filter(mock_claims(), fn ($c) => $c['status'] === 'approved'));
$rejectedClaims = count(array_filter(mock_claims(), fn ($c) => $c['status'] === 'rejected'));
$pendingClaims  = count(array_filter(mock_claims(), fn ($c) => $c['status'] === 'pending'));
$returnRate = $totalItems ? round(($returnedCount / $totalItems) * 100) : 0;

$byCategory = [];
foreach (CATEGORIES as $cat) {
    $byCategory[$cat] = count(array_filter(mock_found_items(), fn ($i) => $i['category'] === $cat));
}
arsort($byCategory);
$maxCategory = max(1, max($byCategory));

$byLocation = [];
foreach (mock_found_items() as $i) {
    $byLocation[$i['location_found']] = ($byLocation[$i['location_found']] ?? 0) + 1;
}
arsort($byLocation);
$maxLocation = max(1, max($byLocation));

// Recent activity feed: merge intake, reports and claims, newest first.
$activity = [];
foreach (mock_found_items() as $i) $activity[] = ['at' => $i['created_at'], 'text' => 'Found item logged: ' . $i['item_name'], 'url' => url('/found/view.php?id=' . $i['item_id']), 'badge' => 'stored'];
foreach (mock_lost_reports() as $r) $activity[] = ['at' => $r['created_at'], 'text' => 'Lost report filed: ' . $r['item_name'], 'url' => url('/lost/view.php?id=' . $r['report_id']), 'badge' => 'open'];
foreach (mock_claims() as $c) $activity[] = ['at' => $c['created_at'], 'text' => 'Claim submitted on ' . mock_found_item($c['item_id'])['item_name'], 'url' => url('/claims/review.php?id=' . $c['claim_id']), 'badge' => $c['status']];
usort($activity, fn ($a, $b) => strcmp($b['at'], $a['at']));
$activity = array_slice($activity, 0, 8);

include APP_PATH . '/views/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>Statistics</h1>
        <p>How the Lost &amp; Found office is doing this semester.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/admin/users.php')) ?>">Manage users</a>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Items logged</span>
        <span class="stat-value"><?= $totalItems ?></span>
        <span class="stat-note"><?= $storedCount ?> in storage &middot; <?= $disposedCount ?> disposed</span>
    </div>
    <div class="stat-card success">
        <span class="stat-label">Return rate</span>
        <span class="stat-value"><?= $returnRate ?>%</span>
        <span class="stat-note"><?= $returnedCount ?> of <?= $totalItems ?> items back with owners</span>
    </div>
    <div class="stat-card info">
        <span class="stat-label">Lost reports</span>
        <span class="stat-value"><?= $totalReports ?></span>
        <span class="stat-note"><?= $openReports ?> still open</span>
    </div>
    <div class="stat-card warning">
        <span class="stat-label">Claims</span>
        <span class="stat-value"><?= $totalClaims ?></span>
        <span class="stat-note"><?= $approvedClaims ?> approved &middot; <?= $rejectedClaims ?> rejected &middot; <?= $pendingClaims ?> pending</span>
    </div>
</div>

<div class="grid grid-2 mb-3">
    <div class="card">
        <h2>Found items by category</h2>
        <div class="bars">
            <?php foreach ($byCategory as $cat => $n): ?>
                <div class="bar-row">
                    <span><?= e($cat) ?></span>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= round($n / $maxCategory * 100) ?>%"></div></div>
                    <span class="bar-value"><?= $n ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2>Where items are found</h2>
        <div class="bars">
            <?php foreach ($byLocation as $loc => $n): ?>
                <div class="bar-row">
                    <span><?= e($loc) ?></span>
                    <div class="bar-track"><div class="bar-fill info" style="width:<?= round($n / $maxLocation * 100) ?>%"></div></div>
                    <span class="bar-value"><?= $n ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Claim outcomes</h2>
        <div class="bars">
            <div class="bar-row"><span>Approved</span><div class="bar-track"><div class="bar-fill success" style="width:<?= $totalClaims ? round($approvedClaims / $totalClaims * 100) : 0 ?>%"></div></div><span class="bar-value"><?= $approvedClaims ?></span></div>
            <div class="bar-row"><span>Rejected</span><div class="bar-track"><div class="bar-fill" style="width:<?= $totalClaims ? round($rejectedClaims / $totalClaims * 100) : 0 ?>%"></div></div><span class="bar-value"><?= $rejectedClaims ?></span></div>
            <div class="bar-row"><span>Pending</span><div class="bar-track"><div class="bar-fill accent" style="width:<?= $totalClaims ? round($pendingClaims / $totalClaims * 100) : 0 ?>%"></div></div><span class="bar-value"><?= $pendingClaims ?></span></div>
        </div>
    </div>

    <div class="card">
        <h2>Recent activity</h2>
        <ul class="timeline">
            <?php foreach ($activity as $a): ?>
                <li>
                    <time><?= e(format_date($a['at'])) ?></time>
                    <div><a href="<?= e($a['url']) ?>"><?= e($a['text']) ?></a> <?= status_badge($a['badge']) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
