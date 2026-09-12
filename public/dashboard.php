<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login();

$user = current_user();
$pageTitle = 'Dashboard';

// ---- Mock aggregates (later: COUNT queries scoped to the user / role) ----
$myReports = array_values(array_filter(mock_lost_reports(), fn ($r) => $r['user_id'] === $user['user_id']));
$myClaims  = array_values(array_filter(mock_claims(), fn ($c) => $c['user_id'] === $user['user_id']));
$myOpenReports    = count(array_filter($myReports, fn ($r) => $r['status'] === 'open'));
$myPendingClaims  = count(array_filter($myClaims, fn ($c) => $c['status'] === 'pending'));
$myApprovedClaims = count(array_filter($myClaims, fn ($c) => $c['status'] === 'approved'));
$storedCount      = count(array_filter(mock_found_items(), fn ($i) => $i['status'] === 'stored'));

$pendingQueue  = count(array_filter(mock_claims(), fn ($c) => $c['status'] === 'pending'));
$openReportsAll = count(array_filter(mock_lost_reports(), fn ($r) => $r['status'] === 'open'));
$returnedMonth = count(array_filter(mock_found_items(), fn ($i) => $i['status'] === 'returned'));
$activeUsers   = count(array_filter(mock_users(), fn ($u) => $u['is_active']));

usort($myClaims, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
usort($myReports, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

include APP_PATH . '/views/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>Hello, <?= e($user['first_name']) ?> 👋</h1>
        <p>
            <?php if (is_admin()): ?>Administrator overview.
            <?php elseif (is_staff()): ?>Here's what needs your attention at the Lost &amp; Found office.
            <?php else: ?>Track your lost-item reports and claims from here.<?php endif; ?>
        </p>
    </div>
    <div class="btn-row">
        <?php if (is_staff()): ?>
            <a class="btn btn-primary" href="<?= e(url('/found/intake.php')) ?>">+ Log found item</a>
        <?php endif; ?>
        <a class="btn <?= is_staff() ? 'btn-outline' : 'btn-primary' ?>" href="<?= e(url('/lost/report.php')) ?>">+ Report lost item</a>
    </div>
</div>

<?php if (is_staff()): ?>
<!-- ===== Staff / Admin overview ===== -->
<div class="stat-grid">
    <div class="stat-card warning">
        <span class="stat-label">Pending claims</span>
        <span class="stat-value"><?= $pendingQueue ?></span>
        <a href="<?= e(url('/claims/queue.php')) ?>">Review queue &rarr;</a>
    </div>
    <div class="stat-card">
        <span class="stat-label">Items in storage</span>
        <span class="stat-value"><?= $storedCount ?></span>
        <a href="<?= e(url('/found/manage.php')) ?>">Manage items &rarr;</a>
    </div>
    <div class="stat-card info">
        <span class="stat-label">Open lost reports</span>
        <span class="stat-value"><?= $openReportsAll ?></span>
        <a href="<?= e(url('/lost/all.php')) ?>">Find matches &rarr;</a>
    </div>
    <?php if (is_admin()): ?>
    <div class="stat-card success">
        <span class="stat-label">Active users</span>
        <span class="stat-value"><?= $activeUsers ?></span>
        <a href="<?= e(url('/admin/users.php')) ?>">Manage users &rarr;</a>
    </div>
    <?php else: ?>
    <div class="stat-card success">
        <span class="stat-label">Returned this month</span>
        <span class="stat-value"><?= $returnedMonth ?></span>
        <span class="stat-note">items back with owners</span>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-sidebar">
    <div class="card">
        <div class="card-header">
            <h2>Claims awaiting review</h2>
            <a href="<?= e(url('/claims/queue.php')) ?>" class="btn btn-outline btn-sm">Open queue</a>
        </div>
        <?php $pending = array_filter(mock_claims(), fn ($c) => $c['status'] === 'pending'); ?>
        <?php if ($pending): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Item</th><th>Claimant</th><th>Submitted</th><th class="actions"></th></tr></thead>
                <tbody>
                <?php foreach ($pending as $claim): $item = mock_found_item($claim['item_id']); $claimant = mock_user($claim['user_id']); ?>
                    <tr>
                        <td><span class="table-title"><?= e($item['item_name']) ?></span><span class="table-sub"><?= e($item['category']) ?></span></td>
                        <td><?= e(full_name($claimant)) ?></td>
                        <td class="nowrap"><?= e(format_date($claim['created_at'])) ?></td>
                        <td class="actions"><a class="btn btn-primary btn-sm" href="<?= e(url('/claims/review.php?id=' . $claim['claim_id'])) ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted mb-0">No pending claims. 🎉</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Quick actions</h2>
        <div class="grid" style="gap:.5rem">
            <a class="btn btn-secondary" href="<?= e(url('/found/intake.php')) ?>">Log a found item</a>
            <a class="btn btn-secondary" href="<?= e(url('/found/manage.php')) ?>">Manage found items</a>
            <a class="btn btn-secondary" href="<?= e(url('/lost/all.php')) ?>">Browse lost reports</a>
            <?php if (is_admin()): ?>
                <a class="btn btn-secondary" href="<?= e(url('/admin/stats.php')) ?>">View statistics</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===== Regular user overview ===== -->
<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">My open reports</span>
        <span class="stat-value"><?= $myOpenReports ?></span>
        <a href="<?= e(url('/lost/my-reports.php')) ?>">View reports &rarr;</a>
    </div>
    <div class="stat-card warning">
        <span class="stat-label">Pending claims</span>
        <span class="stat-value"><?= $myPendingClaims ?></span>
        <a href="<?= e(url('/claims/my-claims.php')) ?>">View claims &rarr;</a>
    </div>
    <div class="stat-card success">
        <span class="stat-label">Approved — ready for pickup</span>
        <span class="stat-value"><?= $myApprovedClaims ?></span>
        <span class="stat-note">bring your ID to Admin Bldg Rm 104</span>
    </div>
    <div class="stat-card info">
        <span class="stat-label">Items in storage</span>
        <span class="stat-value"><?= $storedCount ?></span>
        <a href="<?= e(url('/found/browse.php')) ?>">Browse now &rarr;</a>
    </div>
</div>

<?php if ($myApprovedClaims): ?>
    <div class="alert alert-success">
        <strong>Good news!</strong> One of your claims was approved. Check <a href="<?= e(url('/claims/my-claims.php')) ?>">My Claims</a> for pickup instructions.
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Recent lost reports</h2>
            <a href="<?= e(url('/lost/my-reports.php')) ?>" class="text-sm">See all</a>
        </div>
        <?php if ($myReports): ?>
            <ul class="timeline">
                <?php foreach (array_slice($myReports, 0, 4) as $report): ?>
                    <li>
                        <time><?= e(format_date($report['date_lost'])) ?></time>
                        <div>
                            <a href="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>" class="fw-600"><?= e($report['item_name']) ?></a>
                            <div><?= status_badge($report['status']) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">You haven't reported anything yet.</p>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/lost/report.php')) ?>">Report a lost item</a>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Recent claims</h2>
            <a href="<?= e(url('/claims/my-claims.php')) ?>" class="text-sm">See all</a>
        </div>
        <?php if ($myClaims): ?>
            <ul class="timeline">
                <?php foreach (array_slice($myClaims, 0, 4) as $claim): $item = mock_found_item($claim['item_id']); ?>
                    <li>
                        <time><?= e(format_date($claim['created_at'])) ?></time>
                        <div>
                            <a href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>" class="fw-600"><?= e($item['item_name']) ?></a>
                            <div><?= status_badge($claim['status']) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">No claims yet. Spot your item in the found list? Claim it there.</p>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/found/browse.php')) ?>">Browse found items</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
