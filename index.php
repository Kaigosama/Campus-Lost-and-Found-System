<?php
require_once __DIR__ . '/config/db_connect.php';

/**
 * Homepage / Dashboard.
 *
 *   Guests      → landing page + log-in / register cards (#account). ?next= is kept for after login.
 *   Logged in   → dashboard with tabs:  ?tab=overview (default) | reports | my_claims
 *                 staff: + queue        admin: + users | stats
 *   ?action=logout → clears the (preview) session and returns here as a guest.
 */

if (($_GET['action'] ?? '') === 'logout') {
    logout();
    header('Location: ' . url('/?as=guest'));
    exit;
}

$user = current_user();

/* ---------------------------------------------------------------- Guest */
if (!$user) {
    $pageTitle = null;
    $next      = $_GET['next'] ?? '';

    $storedCount   = count_where(all_found_items(), 'status', 'stored');
    $returnedCount = count_where(all_found_items(), 'status', 'returned');
    $openReports   = count_where(all_lost_reports(), 'status', 'open');
    $recentItems   = array_slice(newest_first(where(all_found_items(), 'status', 'stored'), 'date_found'), 0, 3);

    include APP_ROOT . '/includes/header.php';
    ?>

<section class="hero">
    <h1>Lost something on campus?<br>Check here before you check the drawer.</h1>
    <p>
        <?= e(APP_FULL_NAME) ?> replaces the Lost &amp; Found office's paper logbook. Browse items that have been
        turned in, file a report for what you lost, and claim your belongings online.
    </p>
    <div class="btn-row">
        <a class="btn btn-accent btn-lg" href="<?= e(url('/browse.php')) ?>">Browse found items</a>
        <a class="btn btn-outline btn-lg" href="#account">Log in or register</a>
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
        <a href="<?= e(url('/browse.php')) ?>">See all &rarr;</a>
    </div>
    <div class="item-grid">
        <?php foreach ($recentItems as $item) echo item_card($item); ?>
    </div>
</section>
<?php endif; ?>

<section class="section" id="account">
    <div class="section-title"><h2>Your account</h2></div>
    <?php if ($next): ?>
        <div class="alert alert-info">Please log in to continue.</div>
    <?php endif; ?>
    <div class="grid grid-2">
        <div class="card">
            <h3>Log in</h3>
            <p class="text-muted text-sm">Use your Mapua email address.</p>
            <form method="post" action="<?= e(url('/#account')) ?>" class="form" data-validate data-mock>
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="form-group">
                    <label for="login_email">Email <span class="req" aria-hidden="true">*</span></label>
                    <input type="email" id="login_email" name="email" required autocomplete="email" placeholder="you@mymail.mapua.edu.ph" data-mapua-email>
                </div>
                <div class="form-group">
                    <label for="login_password">Password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="login_password" name="password" required autocomplete="current-password" minlength="8">
                </div>
                <div class="form-group">
                    <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in on this device</label>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Log in</button>
            </form>
            <p class="text-sm text-muted mt-2 mb-0">
                Preview tip: use the <strong>Preview as</strong> bar at the bottom to switch roles until real login exists.
            </p>
        </div>

        <div class="card">
            <h3>Create an account</h3>
            <p class="text-muted text-sm">Open to Mapua students, faculty and staff.</p>
            <form method="post" action="<?= e(url('/#account')) ?>" class="form" data-validate data-mock>
                <input type="hidden" name="action" value="register">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First name <span class="req" aria-hidden="true">*</span></label>
                        <input type="text" id="first_name" name="first_name" required maxlength="100" autocomplete="given-name">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last name <span class="req" aria-hidden="true">*</span></label>
                        <input type="text" id="last_name" name="last_name" required maxlength="100" autocomplete="family-name">
                    </div>
                </div>
                <div class="form-group">
                    <label for="reg_email">Mapua email <span class="req" aria-hidden="true">*</span></label>
                    <input type="email" id="reg_email" name="email" required autocomplete="email" placeholder="you@mymail.mapua.edu.ph" data-mapua-email>
                    <span class="form-hint">Must end in @mymail.mapua.edu.ph or @mapua.edu.ph.</span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="reg_password">Password <span class="req" aria-hidden="true">*</span></label>
                        <input type="password" id="reg_password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirm <span class="req" aria-hidden="true">*</span></label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password" data-match="password">
                    </div>
                </div>
                <div class="form-group">
                    <label class="check">
                        <input type="checkbox" name="agree" value="1" required>
                        I understand that false ownership claims may be reported to the university.
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </form>
        </div>
    </div>
</section>

    <?php
    include APP_ROOT . '/includes/footer.php';
    exit;
}

/* ------------------------------------------------------------ Dashboard */

$allTabs = [ // key => [label, required role(s) or null]
    'overview'  => ['Overview', null],
    'reports'   => ['My Lost Reports', null],
    'my_claims' => ['My Claims', null],
    'queue'     => ['Claims Queue', ['staff', 'admin']],
    'users'     => ['Users', 'admin'],
    'stats'     => ['Statistics', 'admin'],
];
$tab = $_GET['tab'] ?? 'overview';
if (!isset($allTabs[$tab])) {
    abort(404, 'Page not found', '', url('/'), 'Back to dashboard');
}
if ($allTabs[$tab][1] !== null) {
    require_role($allTabs[$tab][1]);
}
$tabs = array_filter($allTabs, fn ($t) => $t[1] === null || has_role($t[1]));

$myReports = newest_first(where(all_lost_reports(), 'user_id', $user['user_id']));
$myClaims  = newest_first(where(all_claims(), 'user_id', $user['user_id']));
$storedCount = count_where(all_found_items(), 'status', 'stored');

$pageTitle = $tab === 'overview' ? 'Dashboard' : $allTabs[$tab][0];
include APP_ROOT . '/includes/header.php';
?>

<?php if ($tab === 'overview'): ?>
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
            <a class="btn btn-primary" href="<?= e(url('/report.php?type=found')) ?>">+ Log found item</a>
        <?php endif; ?>
        <a class="btn <?= is_staff() ? 'btn-outline' : 'btn-primary' ?>" href="<?= e(url('/report.php')) ?>">+ Report lost item</a>
    </div>
</div>
<?php elseif ($tab === 'reports'): ?>
<div class="page-header">
    <div><h1>My lost reports</h1><p>Everything you've reported, newest first.</p></div>
    <a class="btn btn-primary" href="<?= e(url('/report.php')) ?>">+ New report</a>
</div>
<?php elseif ($tab === 'my_claims'): ?>
<div class="page-header">
    <div><h1>My claims</h1><p>Ownership claims you've submitted and their review status.</p></div>
    <a class="btn btn-outline" href="<?= e(url('/browse.php')) ?>">Browse found items</a>
</div>
<?php elseif ($tab === 'queue'): ?>
<div class="page-header">
    <div><h1>Claims queue</h1><p>Compare each claimant's proof with the private details logged at intake.</p></div>
</div>
<?php elseif ($tab === 'users'): ?>
<div class="page-header">
    <div><h1>Manage users</h1><p>Promote staff, deactivate accounts. New registrations always start as <strong>User</strong>.</p></div>
</div>
<?php else: ?>
<div class="page-header">
    <div><h1>Statistics</h1><p>How the Lost &amp; Found office is doing this semester.</p></div>
    <a class="btn btn-outline" href="<?= e(url('/?tab=users')) ?>">Manage users</a>
</div>
<?php endif; ?>

<nav class="tab-bar" aria-label="Dashboard sections">
    <?php foreach ($tabs as $key => [$label]): ?>
        <a href="<?= e(url($key === 'overview' ? '/' : '/?tab=' . $key)) ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php /* ================================================== OVERVIEW */ ?>
<?php if ($tab === 'overview'): ?>
    <?php if (is_staff()): ?>
        <?php
        $pendingClaims = newest_first(where(all_claims(), 'status', 'pending'));
        $openReports   = count_where(all_lost_reports(), 'status', 'open');
        ?>
        <div class="stat-grid">
            <div class="stat-card warning">
                <span class="stat-label">Pending claims</span>
                <span class="stat-value"><?= count($pendingClaims) ?></span>
                <a href="<?= e(url('/?tab=queue')) ?>">Review queue &rarr;</a>
            </div>
            <div class="stat-card">
                <span class="stat-label">Items in storage</span>
                <span class="stat-value"><?= $storedCount ?></span>
                <a href="<?= e(url('/browse.php?manage=1')) ?>">Manage items &rarr;</a>
            </div>
            <div class="stat-card info">
                <span class="stat-label">Open lost reports</span>
                <span class="stat-value"><?= $openReports ?></span>
                <a href="<?= e(url('/browse.php?type=lost')) ?>">Find matches &rarr;</a>
            </div>
            <?php if (is_admin()): ?>
            <div class="stat-card success">
                <span class="stat-label">Active users</span>
                <span class="stat-value"><?= count_where(all_users(), 'is_active', 1) ?></span>
                <a href="<?= e(url('/?tab=users')) ?>">Manage users &rarr;</a>
            </div>
            <?php else: ?>
            <div class="stat-card success">
                <span class="stat-label">Returned this month</span>
                <span class="stat-value"><?= count_where(all_found_items(), 'status', 'returned') ?></span>
                <span class="stat-note">items back with owners</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-sidebar">
            <div class="card">
                <div class="card-header">
                    <h2>Claims awaiting review</h2>
                    <a href="<?= e(url('/?tab=queue')) ?>" class="btn btn-outline btn-sm">Open queue</a>
                </div>
                <?php if ($pendingClaims): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Item</th><th>Claimant</th><th>Submitted</th><th class="actions"></th></tr></thead>
                        <tbody>
                        <?php foreach ($pendingClaims as $claim): $item = find_found_item($claim['item_id']); $claimant = find_user($claim['user_id']); ?>
                            <tr>
                                <td><span class="table-title"><?= e($item['item_name']) ?></span><span class="table-sub"><?= e($item['category']) ?></span></td>
                                <td><?= e(full_name($claimant)) ?></td>
                                <td class="nowrap"><?= e(format_date($claim['date_claimed'])) ?></td>
                                <td class="actions"><a class="btn btn-primary btn-sm" href="<?= e(item_url('found', $item['item_id']) . '#claim-' . $claim['claim_id']) ?>">Review</a></td>
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
                    <a class="btn btn-secondary" href="<?= e(url('/report.php?type=found')) ?>">Log a found item</a>
                    <a class="btn btn-secondary" href="<?= e(url('/browse.php?manage=1')) ?>">Manage found items</a>
                    <a class="btn btn-secondary" href="<?= e(url('/browse.php?type=lost')) ?>">Browse lost reports</a>
                    <?php if (is_admin()): ?>
                        <a class="btn btn-secondary" href="<?= e(url('/?tab=stats')) ?>">View statistics</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php else: ?>
        <?php $myApprovedClaims = count_where($myClaims, 'status', 'approved'); ?>
        <div class="stat-grid">
            <div class="stat-card">
                <span class="stat-label">My open reports</span>
                <span class="stat-value"><?= count_where($myReports, 'status', 'open') ?></span>
                <a href="<?= e(url('/?tab=reports')) ?>">View reports &rarr;</a>
            </div>
            <div class="stat-card warning">
                <span class="stat-label">Pending claims</span>
                <span class="stat-value"><?= count_where($myClaims, 'status', 'pending') ?></span>
                <a href="<?= e(url('/?tab=my_claims')) ?>">View claims &rarr;</a>
            </div>
            <div class="stat-card success">
                <span class="stat-label">Approved — ready for pickup</span>
                <span class="stat-value"><?= $myApprovedClaims ?></span>
                <span class="stat-note">bring your ID to Admin Bldg Rm 104</span>
            </div>
            <div class="stat-card info">
                <span class="stat-label">Items in storage</span>
                <span class="stat-value"><?= $storedCount ?></span>
                <a href="<?= e(url('/browse.php')) ?>">Browse now &rarr;</a>
            </div>
        </div>

        <?php if ($myApprovedClaims): ?>
            <div class="alert alert-success">
                <strong>Good news!</strong> One of your claims was approved. Check <a href="<?= e(url('/?tab=my_claims')) ?>">My Claims</a> for pickup instructions.
            </div>
        <?php endif; ?>

        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">
                    <h2>Recent lost reports</h2>
                    <a href="<?= e(url('/?tab=reports')) ?>" class="text-sm">See all</a>
                </div>
                <?php if ($myReports): ?>
                    <ul class="timeline">
                        <?php foreach (array_slice($myReports, 0, 4) as $report): ?>
                            <li>
                                <time><?= e(format_date($report['date_lost'])) ?></time>
                                <div>
                                    <a href="<?= e(item_url('lost', $report['report_id'])) ?>" class="fw-600"><?= e($report['item_name']) ?></a>
                                    <div><?= status_badge($report['status']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">You haven't reported anything yet.</p>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/report.php')) ?>">Report a lost item</a>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Recent claims</h2>
                    <a href="<?= e(url('/?tab=my_claims')) ?>" class="text-sm">See all</a>
                </div>
                <?php if ($myClaims): ?>
                    <ul class="timeline">
                        <?php foreach (array_slice($myClaims, 0, 4) as $claim): $item = find_found_item($claim['item_id']); ?>
                            <li>
                                <time><?= e(format_date($claim['date_claimed'])) ?></time>
                                <div>
                                    <a href="<?= e(item_url('found', $item['item_id'])) ?>" class="fw-600"><?= e($item['item_name']) ?></a>
                                    <div><?= status_badge($claim['status']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No claims yet. Spot your item in the found list? Claim it there.</p>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/browse.php')) ?>">Browse found items</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<?php /* ================================================== MY LOST REPORTS */ ?>
<?php elseif ($tab === 'reports'): ?>
    <?php
    $status = $_GET['status'] ?? '';
    $counts = ['' => count($myReports)];
    foreach (LOST_STATUSES as $key => $label) {
        $counts[$key] = count_where($myReports, 'status', $key);
    }
    $rows = $status === '' ? $myReports : where($myReports, 'status', $status);
    echo pill_tabs(['' => 'All'] + LOST_STATUSES, $counts, $status, 'status');
    ?>

    <?php if ($rows): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th>Item</th><th>Category</th><th>Lost on</th><th>Where</th><th>Status</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $report): ?>
                <tr>
                    <td><?= photo_tag($report['image_url'], $report['item_name'], 'photo-thumb') ?></td>
                    <td>
                        <a class="table-title" href="<?= e(item_url('lost', $report['report_id'])) ?>"><?= e($report['item_name']) ?></a>
                        <span class="table-sub"><?= e(excerpt($report['description'], 70)) ?></span>
                    </td>
                    <td><?= e($report['category']) ?></td>
                    <td class="nowrap"><?= e(format_date($report['date_lost'])) ?></td>
                    <td><?= e($report['location_lost']) ?></td>
                    <td><?= status_badge($report['status']) ?></td>
                    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(item_url('lost', $report['report_id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state(
            $status ? 'No ' . strtolower(status_label($status)) . ' reports' : 'No lost reports yet',
            'When you report a lost item it will show up here with its current status.',
            url('/report.php'),
            'Report a lost item'
        ) ?>
    <?php endif; ?>

<?php /* ================================================== MY CLAIMS */ ?>
<?php elseif ($tab === 'my_claims'): ?>
    <?php if ($myClaims): ?>
    <div class="grid">
        <?php foreach ($myClaims as $claim): $item = find_found_item($claim['item_id']); $reviewer = find_user($claim['reviewed_by']); ?>
            <article class="card">
                <div class="card-header">
                    <div class="flex items-center gap-2">
                        <?= photo_tag($item['image_url'], $item['item_name'], 'photo-thumb') ?>
                        <div>
                            <h2 class="mb-0"><a href="<?= e(item_url('found', $item['item_id'])) ?>"><?= e($item['item_name']) ?></a></h2>
                            <small>Claim #<?= $claim['claim_id'] ?> &middot; submitted <?= e(format_datetime($claim['created_at'])) ?></small>
                        </div>
                    </div>
                    <?= status_badge($claim['status']) ?>
                </div>

                <div class="grid grid-2">
                    <div>
                        <h3 class="text-sm text-muted">What you told us</h3>
                        <div class="quote claimant text-sm"><?= e($claim['proof_description']) ?></div>
                        <?php if ($claim['report_id']): $r = find_lost_report($claim['report_id']); ?>
                            <p class="text-sm mt-1 mb-0">Linked report: <a href="<?= e(item_url('lost', $r['report_id'])) ?>">#<?= $r['report_id'] ?> <?= e($r['item_name']) ?></a></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-sm text-muted">Staff response</h3>
                        <?php if ($claim['status'] === 'pending'): ?>
                            <p class="text-sm text-muted">Awaiting review. Most claims are reviewed within one working day.</p>
                        <?php else: ?>
                            <div class="alert alert-<?= $claim['status'] === 'approved' ? 'success' : 'error' ?> text-sm">
                                <strong><?= $claim['status'] === 'approved' ? 'Approved.' : 'Not approved.' ?></strong> <?= e($claim['review_note']) ?>
                            </div>
                            <p class="text-sm mb-0">Reviewed by <?= e(full_name($reviewer)) ?> on <?= e(format_datetime($claim['reviewed_at'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($claim['status'] === 'pending'): ?>
                    <div class="form-actions" style="margin-top:1rem;padding-top:.75rem">
                        <form method="post" action="<?= e(url('/?tab=my_claims')) ?>" data-confirm="Withdraw this claim?">
                            <input type="hidden" name="action" value="withdraw">
                            <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Withdraw claim</button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <?= empty_state('No claims yet', 'When you spot your item in the found list, open it and click "Claim this item".', url('/browse.php'), 'Browse found items') ?>
    <?php endif; ?>

<?php /* ================================================== CLAIMS QUEUE (staff) */ ?>
<?php elseif ($tab === 'queue'): ?>
    <?php
    $status     = $_GET['status'] ?? 'pending';
    $itemFilter = (int) ($_GET['item'] ?? 0);

    $rows = array_values(all_claims());
    if ($status !== 'all') {
        $rows = where($rows, 'status', $status);
    }
    if ($itemFilter) {
        $rows = where($rows, 'item_id', $itemFilter);
    }
    // Oldest pending first so nothing gets buried; otherwise newest first.
    usort($rows, fn ($a, $b) => $status === 'pending'
        ? strcmp($a['created_at'], $b['created_at'])
        : strcmp($b['created_at'], $a['created_at']));

    $counts = ['all' => count(all_claims())];
    foreach (CLAIM_STATUSES as $key => $label) {
        $counts[$key] = count_where(all_claims(), 'status', $key);
    }
    echo pill_tabs(CLAIM_STATUSES + ['all' => 'All'], $counts, $status, 'status');
    ?>

    <?php if ($itemFilter && ($fi = find_found_item($itemFilter))): ?>
        <div class="alert alert-info">
            Showing claims for <strong><?= e($fi['item_name']) ?></strong> only.
            <a href="<?= e(url_with(['item' => null])) ?>">Show all items</a>
        </div>
    <?php endif; ?>

    <?php if ($rows): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Item</th><th>Claimant</th><th>Proof (excerpt)</th><th>Linked report</th><th>Submitted</th><th>Status</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $claim): $item = find_found_item($claim['item_id']); $claimant = find_user($claim['user_id']); ?>
                <tr>
                    <td class="text-muted"><?= $claim['claim_id'] ?></td>
                    <td>
                        <a class="table-title" href="<?= e(item_url('found', $item['item_id'])) ?>"><?= e($item['item_name']) ?></a>
                        <span class="table-sub"><?= e($item['storage_location']) ?></span>
                    </td>
                    <td><?= e(full_name($claimant)) ?><span class="table-sub"><?= e($claimant['email']) ?></span></td>
                    <td style="max-width:280px"><?= e(excerpt($claim['proof_description'], 80)) ?></td>
                    <td>
                        <?php if ($claim['report_id']): ?>
                            <a href="<?= e(item_url('lost', $claim['report_id'])) ?>">#<?= $claim['report_id'] ?></a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="nowrap"><?= e(format_date($claim['date_claimed'])) ?></td>
                    <td><?= status_badge($claim['status']) ?></td>
                    <td class="actions">
                        <a class="btn <?= $claim['status'] === 'pending' ? 'btn-primary' : 'btn-outline' ?> btn-sm" href="<?= e(item_url('found', $item['item_id']) . '#claim-' . $claim['claim_id']) ?>">
                            <?= $claim['status'] === 'pending' ? 'Review' : 'Open' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state(
            $status === 'pending' ? 'Queue is clear' : 'No ' . ($status === 'all' ? '' : $status . ' ') . 'claims',
            $status === 'pending' ? 'There are no claims waiting for review right now.' : ''
        ) ?>
    <?php endif; ?>

<?php /* ================================================== USERS (admin) */ ?>
<?php elseif ($tab === 'users'): ?>
    <?php
    $roleFilter = $_GET['role'] ?? '';
    $rows = newest_first($roleFilter === '' ? array_values(all_users()) : where(all_users(), 'role', $roleFilter));
    $counts = ['' => count(all_users())];
    foreach (ROLES as $key => $label) {
        $counts[$key] = count_where(all_users(), 'role', $key);
    }
    echo pill_tabs(['' => 'All'] + ROLES, $counts, $roleFilter, 'role');
    ?>

    <div class="table-tools">
        <input type="search" placeholder="Search name or email…" aria-label="Filter users" data-table-filter="#usersTable">
        <span class="text-sm text-muted">Showing <span data-filter-count="#usersTable"><?= count($rows) ?></span></span>
    </div>

    <div class="table-wrap">
        <table class="table" id="usersTable">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u): $isMe = $u['user_id'] === $user['user_id']; ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-1">
                            <span class="avatar" style="background:var(--primary-light)" aria-hidden="true"><?= e(initials($u)) ?></span>
                            <span class="table-title"><?= e(full_name($u)) ?><?= $isMe ? ' <small class="text-muted">(you)</small>' : '' ?></span>
                        </div>
                    </td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('/?tab=users')) ?>" data-confirm="Change this user's role?">
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <label for="role-<?= $u['user_id'] ?>" class="sr-only">Role</label>
                            <select id="role-<?= $u['user_id'] ?>" name="role" class="inline-select" onchange="this.form.requestSubmit()" <?= $isMe ? 'disabled title="You cannot change your own role"' : '' ?>>
                                <?= options(ROLES, $u['role']) ?>
                            </select>
                        </form>
                    </td>
                    <td><span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></span></td>
                    <td class="nowrap"><?= e(format_date($u['created_at'])) ?></td>
                    <td class="actions">
                        <?php if (!$isMe): ?>
                            <form method="post" action="<?= e(url('/?tab=users')) ?>" style="display:inline"
                                  data-confirm="<?= $u['is_active'] ? 'Deactivate this account? They will no longer be able to log in.' : 'Reactivate this account?' ?>">
                                <input type="hidden" name="action" value="<?= $u['is_active'] ? 'deactivate' : 'activate' ?>">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-secondary' : 'btn-success' ?>"><?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="filter-empty" data-filter-empty hidden>No users match that search.</p>

<?php /* ================================================== STATISTICS (admin) */ ?>
<?php else: ?>
    <?php
    // ---- Mock aggregates (later: GROUP BY queries) ----
    $items   = all_found_items();
    $reports = all_lost_reports();
    $claims  = all_claims();

    $totalItems     = count($items);
    $returnedCount  = count_where($items, 'status', 'returned');
    $approvedClaims = count_where($claims, 'status', 'approved');
    $rejectedClaims = count_where($claims, 'status', 'rejected');
    $pendingCount   = count_where($claims, 'status', 'pending');
    $returnRate     = $totalItems ? round(($returnedCount / $totalItems) * 100) : 0;

    $byCategory = [];
    foreach (CATEGORIES as $cat) {
        $byCategory[$cat] = count_where($items, 'category', $cat);
    }
    arsort($byCategory);
    $maxCategory = max(1, max($byCategory));

    $byLocation = [];
    foreach ($items as $i) {
        $byLocation[$i['location_found']] = ($byLocation[$i['location_found']] ?? 0) + 1;
    }
    arsort($byLocation);
    $maxLocation = max(1, max($byLocation));

    // Recent activity feed: merge intake, reports and claims, newest first.
    $activity = [];
    foreach ($items as $i)   $activity[] = ['at' => $i['created_at'], 'text' => 'Found item logged: ' . $i['item_name'], 'url' => item_url('found', $i['item_id']), 'badge' => 'stored'];
    foreach ($reports as $r) $activity[] = ['at' => $r['created_at'], 'text' => 'Lost report filed: ' . $r['item_name'], 'url' => item_url('lost', $r['report_id']), 'badge' => 'open'];
    foreach ($claims as $c)  $activity[] = ['at' => $c['created_at'], 'text' => 'Claim submitted on ' . find_found_item($c['item_id'])['item_name'], 'url' => item_url('found', $c['item_id']) . '#claim-' . $c['claim_id'], 'badge' => $c['status']];
    $activity = array_slice(newest_first($activity, 'at'), 0, 8);

    $bar = fn (int $n, int $max, string $cls = '') => '<div class="bar-track"><div class="bar-fill ' . $cls . '" style="width:' . round($n / max(1, $max) * 100) . '%"></div></div>';
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
            <span class="stat-note"><?= $approvedClaims ?> approved &middot; <?= $rejectedClaims ?> rejected &middot; <?= $pendingCount ?> pending</span>
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
                <div class="bar-row"><span>Rejected</span><?= $bar($rejectedClaims, count($claims)) ?><span class="bar-value"><?= $rejectedClaims ?></span></div>
                <div class="bar-row"><span>Pending</span><?= $bar($pendingCount, count($claims), 'accent') ?><span class="bar-value"><?= $pendingCount ?></span></div>
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
<?php endif; ?>

<?php include APP_ROOT . '/includes/footer.php'; ?>
