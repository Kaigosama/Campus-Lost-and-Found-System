<?php
require_once __DIR__ . '/../app/bootstrap.php';

$user = current_user();
$pageTitle = null; // default title

// Landing stats (mock). Later: COUNT(*) queries.
$storedCount   = count(array_filter(mock_found_items(), fn ($i) => $i['status'] === 'stored'));
$returnedCount = count(array_filter(mock_found_items(), fn ($i) => $i['status'] === 'returned'));
$openReports   = count(array_filter(mock_lost_reports(), fn ($r) => $r['status'] === 'open'));

$recentItems = array_slice(
    array_values(array_filter(mock_found_items(), fn ($i) => $i['status'] === 'stored')),
    0,
    3
);

include APP_PATH . '/views/layout/header.php';
?>

<section class="hero">
    <h1>Lost something on campus?<br>Check here before you check the drawer.</h1>
    <p>
        <?= e(APP_FULL_NAME) ?> replaces the Lost &amp; Found office's paper logbook. Browse items that have been
        turned in, file a report for what you lost, and claim your belongings online.
    </p>
    <div class="btn-row">
        <a class="btn btn-accent btn-lg" href="<?= e(url('/found/browse.php')) ?>">Browse found items</a>
        <?php if ($user): ?>
            <a class="btn btn-outline btn-lg" href="<?= e(url('/lost/report.php')) ?>">Report a lost item</a>
        <?php else: ?>
            <a class="btn btn-outline btn-lg" href="<?= e(url('/register.php')) ?>">Create an account</a>
        <?php endif; ?>
    </div>
</section>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Items in storage</span>
        <span class="stat-value"><?= $storedCount ?></span>
        <span class="stat-note">waiting to be claimed</span>
    </div>
    <div class="stat-card success">
        <span class="stat-label">Returned to owners</span>
        <span class="stat-value"><?= $returnedCount ?></span>
        <span class="stat-note">this semester</span>
    </div>
    <div class="stat-card info">
        <span class="stat-label">Open lost reports</span>
        <span class="stat-value"><?= $openReports ?></span>
        <span class="stat-note">from students &amp; faculty</span>
    </div>
    <div class="stat-card accent">
        <span class="stat-label">Office hours</span>
        <span class="stat-value" style="font-size:1.2rem">Mon–Fri</span>
        <span class="stat-note">8:00 AM – 5:00 PM, Admin Bldg Rm 104</span>
    </div>
</div>

<section class="section">
    <div class="section-title"><h2>How it works</h2></div>
    <div class="steps">
        <div class="step">
            <div class="step-num">1</div>
            <h3>Report or browse</h3>
            <p>File a lost-item report with a description and photo, or browse what security and staff have already turned in.</p>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <h3>Claim it</h3>
            <p>Found yours? Submit a claim describing details only the real owner would know. Staff compare it against the intake record.</p>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <h3>Pick it up</h3>
            <p>Once approved, bring your ID to the Lost &amp; Found office. Staff mark it returned and close your report.</p>
        </div>
    </div>
</section>

<?php if ($recentItems): ?>
<section class="section">
    <div class="section-title">
        <h2>Recently turned in</h2>
        <a href="<?= e(url('/found/browse.php')) ?>">See all &rarr;</a>
    </div>
    <div class="item-grid">
        <?php foreach ($recentItems as $item): include APP_PATH . '/views/partials/item-card.php'; endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
