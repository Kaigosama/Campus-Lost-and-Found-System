<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: statistics staff. */ ?>
<div class="page-header">
    <div><h1>Statistics</h1><p>How the Lost &amp; Found office is doing this semester.</p></div>
</div>

    <?php
    $items   = all_found_items();
    $reports = all_lost_reports();
    $claims  = all_claims();

    $totalItems     = count($items);
    $returnedCount  = count_where($items, 'status', 'returned');
    $approvedClaims = count(array_filter($claims, fn ($c) => $c['status'] !== 'pending'));   // approved, verified or returned
    $rejectedClaims = count(array_filter($claims, 'is_rejected_claim'));
    $pendingCount   = count(array_filter($claims, 'awaiting_review'));
    $returnRate     = $totalItems ? round(($returnedCount / $totalItems) * 100) : 0;

    $byCategory = array_fill_keys(category_names(), 0);
    foreach ($items as $i) {
        $byCategory[$i['category_name']]++;   // typed "Other" details count as Other
    }
    arsort($byCategory);
    $maxCategory = max(1, max($byCategory));

    $byLocation = [];
    foreach ($items as $i) {
        $loc = listed($i['location_found'], CAMPUS_LOCATIONS);
        $byLocation[$loc] = ($byLocation[$loc] ?? 0) + 1;
    }
    arsort($byLocation);
    $maxLocation = max([1, ...array_values($byLocation)]);   // max() of an empty array throws when nothing is logged yet

    $activity = [];
    foreach ($items as $i)   $activity[] = ['at' => $i['created_at'], 'text' => 'Found item logged: ' . $i['item_name'], 'url' => item_url('found', $i['item_id']), 'badge' => 'stored'];
    foreach ($reports as $r) $activity[] = ['at' => $r['created_at'], 'text' => 'Lost report filed: ' . $r['item_name'], 'url' => item_url('lost', $r['report_id']), 'badge' => 'open'];
    foreach ($claims as $c)  $activity[] = ['at' => $c['created_at'], 'text' => 'Claim submitted on ' . find_found_item($c['item_id'])['item_name'], 'url' => item_url('found', $c['item_id']) . '#claim-' . $c['claim_id'], 'badge' => $c['status']];
    $activity = array_slice(newest_first($activity, 'at'), 0, 8);

    // The count is shown next to each bar, so screen readers skip the bar itself.
    $bar = fn (int $n, int $max, string $cls = '') => '<progress class="bar ' . $cls . '" value="' . $n . '" max="' . max(1, $max) . '" aria-hidden="true"></progress>';
    ?>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-label">Items logged</span>
            <span class="stat-value"><?= $totalItems ?></span>
            <span class="stat-note"><?= $storedCount ?> in storage &middot; <?= count_where($items, 'status', 'disposed') ?> disposed</span>
        </div>
        <div class="stat-card success">
            <span class="stat-label">Return rate</span>
            <span class="stat-value"><?= $returnRate ?>%</span>
            <span class="stat-note"><?= $returnedCount ?> of <?= $totalItems ?> items back with owners</span>
        </div>
        <div class="stat-card info">
            <span class="stat-label">Lost reports</span>
            <span class="stat-value"><?= count($reports) ?></span>
            <span class="stat-note"><?= count_where($reports, 'status', 'open') ?> still open</span>
        </div>
        <div class="stat-card warning">
            <span class="stat-label">Claims</span>
            <span class="stat-value"><?= count($claims) ?></span>
            <span class="stat-note"><?= $approvedClaims ?> approved &middot; <?= $rejectedClaims ?> rejected (still pending) &middot; <?= $pendingCount ?> awaiting review</span>
        </div>
    </div>

    <div class="grid grid-2 mb-3">
        <div class="card">
            <h2>Found items by category</h2>
            <div class="bars">
                <?php foreach ($byCategory as $cat => $n): ?>
                    <div class="bar-row"><span><?= e($cat) ?></span><?= $bar($n, $maxCategory) ?><span class="bar-value"><?= $n ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <h2>Where items are found</h2>
            <div class="bars">
                <?php foreach ($byLocation as $loc => $n): ?>
                    <div class="bar-row"><span><?= e($loc) ?></span><?= $bar($n, $maxLocation, 'info') ?><span class="bar-value"><?= $n ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <h2>Claim outcomes</h2>
            <div class="bars">
                <div class="bar-row"><span>Approved</span><?= $bar($approvedClaims, count($claims), 'success') ?><span class="bar-value"><?= $approvedClaims ?></span></div>
                <div class="bar-row"><span>Rejected (pending)</span><?= $bar($rejectedClaims, count($claims)) ?><span class="bar-value"><?= $rejectedClaims ?></span></div>
                <div class="bar-row"><span>Awaiting review</span><?= $bar($pendingCount, count($claims), 'accent') ?><span class="bar-value"><?= $pendingCount ?></span></div>
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
