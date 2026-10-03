<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Guests      → landing page. Logging in and registering happen on login.php and register.php;
 *               old /?next= and /?reset=1 links are forwarded to login.php.
 * Logged in   → dashboard tabs: overview | account   students/faculty & staff: + reports | my_claims
 *               students/faculty: + posts   staff: + queue | moderation   admins: + users (with account activity) | stats | logs
 *               POST on ?tab=account changes the password.
 * POST ?action=logout ends the session. It must be a POST so another site can't log visitors out with a link.
 */

if (($_GET['action'] ?? '') === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    logout();
    header('Location: ' . url('/'));
    exit;
}

$user = current_user();

/* ---------------------------------------------------------------- Guest */
if (!$user) {
    if (isset($_GET['next']) || isset($_GET['reset'])) {
        header('Location: ' . url('/login.php') . '?' . http_build_query(array_intersect_key($_GET, ['next' => 1, 'reset' => 1])));
        exit;
    }
    $pageTitle = null;

    $storedCount   = count(public_found_items());
    $returnedCount = count_where(all_found_items(), 'status', 'returned');
    $openReports   = count_where(all_lost_reports(), 'status', 'open');
    $recentItems   = array_slice(newest_first(array_values(public_found_items()), 'date_found'), 0, 3);

    include APP_ROOT . '/templates/layout/header.php';
    ?>

<section class="hero">
    <div class="hero-body">
        <h1>Lost something on campus? Your stuff might already be waiting for you.</h1>
        <p>Browse turned-in items, report lost belongings, and manage your claim online with ease.</p>
        <div class="btn-row">
            <a class="btn btn-accent btn-lg" href="<?= e(url('/browse.php')) ?>">Browse found items</a>
            <a class="btn btn-outline btn-lg" href="<?= e(url('/register.php')) ?>">Create an account</a>
        </div>
    </div>
    <img class="hero-logo" src="<?= e(asset('images/logo.png')) ?>" alt="" width="220" height="220">
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
        <span class="stat-note">since <?= e(APP_NAME) ?> launched</span>
    </div>
    <div class="stat-card info">
        <span class="stat-label">Open lost reports</span>
        <span class="stat-value"><?= $openReports ?></span>
        <span class="stat-note">from students &amp; faculty</span>
    </div>
    <div class="stat-card accent">
        <span class="stat-label">Office hours</span>
        <span class="stat-value stat-value-sm">Mon–Fri</span>
        <span class="stat-note">8:00 AM – 5:00 PM, Admin Bldg Rm 104</span>
        <span class="stat-note" data-next-holiday aria-live="polite">Checking holiday schedule…</span>
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
        <a href="<?= e(url('/browse.php')) ?>">See all found items &rarr;</a>
    </div>
    <div class="item-grid">
        <?php foreach ($recentItems as $item) echo item_card($item); ?>
    </div>
</section>
<?php endif; ?>

    <?php
    include APP_ROOT . '/templates/layout/footer.php';
    exit;
}

/* ------------------------------------------------------------ Dashboard */

if (is_admin()) {
    delete_expired_unverified();   // keeps the Users list and counts free of sign-ups that never confirmed
}

$allTabs = [ // key => [label, required role(s) or null]
    'overview'  => ['Overview', null],
    'reports'   => ['My Lost Reports', ['user', 'staff']],
    'my_claims' => ['My Claims', ['user', 'staff']],
    'posts'     => ['My Found Posts', 'user'],
    'queue'     => ['Claims Queue', REVIEWER_ROLES],
    'moderation' => ['Post Review', REVIEWER_ROLES],
    'users'     => ['Users & Activity', ADMIN_ROLES],
    'stats'     => ['Statistics', ADMIN_ROLES],
    'logs'      => ['Security Logs', ADMIN_ROLES],
    'account'   => ['Account', null],
];
$tab = $_GET['tab'] ?? 'overview';
if (!isset($allTabs[$tab])) {
    abort(404, 'Page not found', '', url('/'), 'Back to dashboard');
}
if ($allTabs[$tab][1] !== null) {
    require_role($allTabs[$tab][1]);
}
$tabs = array_filter($allTabs, fn ($t) => $t[1] === null || has_role($t[1]));

$errors = [];
if ($tab === 'account' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = change_password($user, $_POST);
    if (!$errors) {
        header('Location: ' . url('/?tab=account&changed=1'));
        exit;
    }
}

$myReports = newest_first(where(all_lost_reports(), 'user_id', $user['user_id']));
$myClaims  = newest_first(where(all_claims(), 'user_id', $user['user_id']));
$storedCount = count(public_found_items());
$pendingPosts = newest_first(where(all_found_items(), 'moderation_status', 'pending'));

$pageTitle = $tab === 'overview' ? 'Dashboard' : $allTabs[$tab][0];
include APP_ROOT . '/templates/layout/header.php';
?>

<?php if ($tab === 'overview'): ?>
<div class="page-header">
    <div>
        <h1>Hello, <?= e($user['first_name']) ?> <span aria-hidden="true">👋</span></h1>
        <p>
            <?php if (is_admin()): ?>Administrator overview.
            <?php elseif (is_staff()): ?>Here's what needs your attention at the Lost &amp; Found office.
            <?php else: ?>Track your lost-item reports and claims from here.<?php endif; ?>
        </p>
    </div>
    <div class="btn-row">
        <?php if (has_role('staff')): ?>
            <a class="btn btn-primary" href="<?= e(url('/report.php?type=found')) ?>">+ Log found item</a>
        <?php elseif (has_role('user')): ?>
            <a class="btn btn-outline" href="<?= e(url('/report.php?type=found')) ?>">+ I found something</a>
        <?php endif; ?>
        <?php if (!is_admin()): ?>
            <a class="btn <?= is_staff() ? 'btn-outline' : 'btn-primary' ?>" href="<?= e(url('/report.php')) ?>">+ Report lost item</a>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($tab === 'posts'): ?>
<div class="page-header">
    <div><h1>My found posts</h1><p>Items you found and posted. Staff approve each one before it is listed publicly.</p></div>
    <a class="btn btn-primary" href="<?= e(url('/report.php?type=found')) ?>">+ Post a found item</a>
</div>
<?php elseif ($tab === 'moderation'): ?>
<div class="page-header">
    <div><h1>Post review</h1><p>Found-item posts from students and faculty. Approve them once the item is at the office.</p></div>
</div>
<?php elseif ($tab === 'logs'): ?>
<div class="page-header">
    <div><h1>Security logs</h1><p>Log-ins, failures, lockouts and session history. Session tokens are never shown.</p></div>
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
    <div><h1>Users &amp; activity</h1><p>Roles, account status and recent activity. New registrations start as <strong><?= e(ROLES['user']) ?></strong>.
        <?= has_role('master_admin') ? 'As master administrator you can also manage administrator accounts.' : 'Only the master administrator can change administrator accounts.' ?></p></div>
</div>
<?php elseif ($tab === 'account'): ?>
<div class="page-header">
    <div><h1>Account</h1><p>Your profile and password.</p></div>
</div>
<?php else: ?>
<div class="page-header">
    <div><h1>Statistics</h1><p>How the Lost &amp; Found office is doing this semester.</p></div>
    <a class="btn btn-outline" href="<?= e(url('/?tab=users')) ?>">Manage users</a>
</div>
<?php endif; ?>

<nav class="tab-bar" aria-label="Dashboard sections">
    <?php foreach ($tabs as $key => [$label]): ?>
        <a href="<?= e(url($key === 'overview' ? '/' : '/?tab=' . $key)) ?>" <?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php /* ================================================== OVERVIEW */ ?>
<?php if ($tab === 'overview'): ?>
    <?php if (is_admin()): ?>
        <div class="stat-grid">
            <div class="stat-card success">
                <span class="stat-label">Active users</span>
                <span class="stat-value"><?= count_where(all_users(), 'is_active', 1) ?></span>
                <a href="<?= e(url('/?tab=users')) ?>">Manage users &rarr;</a>
            </div>
            <div class="stat-card">
                <span class="stat-label">Items in storage</span>
                <span class="stat-value"><?= $storedCount ?></span>
                <a href="<?= e(url('/?tab=stats')) ?>">View statistics &rarr;</a>
            </div>
        </div>
        <div class="card">
            <h2>Quick actions</h2>
            <div class="btn-row">
                <a class="btn btn-secondary" href="<?= e(url('/?tab=users')) ?>">Manage users</a>
                <a class="btn btn-secondary" href="<?= e(url('/?tab=logs')) ?>">Security logs</a>
                <a class="btn btn-secondary" href="<?= e(url('/?tab=stats')) ?>">View statistics</a>
            </div>
        </div>

    <?php elseif (is_staff()): ?>
        <?php
        $pendingClaims = newest_first(where(all_claims(), 'status', 'pending'));
        $openReports   = count_where(all_lost_reports(), 'status', 'open');
        ?>
        <?php if ($pendingPosts): ?>
            <div class="alert alert-warning">
                <strong><?= count($pendingPosts) ?> found-item post<?= count($pendingPosts) === 1 ? '' : 's' ?></strong> from students and faculty
                <?= count($pendingPosts) === 1 ? 'is' : 'are' ?> waiting for review. <a href="<?= e(url('/?tab=moderation')) ?>">Review posts</a>
            </div>
        <?php endif; ?>
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
            <div class="stat-card success">
                <span class="stat-label">Returned to owners</span>
                <span class="stat-value"><?= count_where(all_found_items(), 'status', 'returned') ?></span>
                <span class="stat-note">all time</span>
            </div>
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
                    <p class="text-muted mb-0">No pending claims. <span aria-hidden="true">🎉</span></p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Quick actions</h2>
                <div class="grid gap-sm">
                    <a class="btn btn-secondary" href="<?= e(url('/report.php?type=found')) ?>">Log a found item</a>
                    <a class="btn btn-secondary" href="<?= e(url('/?tab=moderation')) ?>">Review student posts</a>
                    <a class="btn btn-secondary" href="<?= e(url('/browse.php?manage=1')) ?>">Manage found items</a>
                    <a class="btn btn-secondary" href="<?= e(url('/browse.php?type=lost')) ?>">Browse lost reports</a>
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
            <article class="card" data-remove>
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
                    <div class="form-actions compact">
                        <form method="post" action="<?= e(url('/?tab=my_claims')) ?>" data-api="withdraw" data-done="remove" data-confirm="Withdraw this claim?">
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
                    <td class="cell-wide"><?= e(excerpt($claim['proof_description'], 80)) ?></td>
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

    // Sessions with authenticated activity inside the idle window count as online.
    $stmt = db()->prepare('SELECT user_id, COUNT(*) FROM user_sessions WHERE status = "active" AND last_activity_at > NOW() - INTERVAL ? MINUTE GROUP BY user_id');
    $stmt->execute([SESSION_IDLE_MINUTES]);
    $online     = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $isMaster   = has_role('master_admin');
    $assignable = array_intersect_key(ROLES, array_flip($isMaster ? ['user', 'staff', 'admin'] : ['user', 'staff']));
    ?>

    <div class="table-tools">
        <input type="search" placeholder="Search name or email…" aria-label="Filter users" data-table-filter="#usersTable">
        <span class="text-sm text-muted">Showing <span data-filter-count="#usersTable"><?= count($rows) ?></span></span>
    </div>

    <div class="table-wrap">
        <table class="table" id="usersTable">
            <thead><tr><th>Name</th><th>Role</th><th>Account</th><th>Last login</th><th>Activity</th><th>Session</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u):
                $isMe     = $u['user_id'] === $user['user_id'];
                // Mirrors api/update_user.php: admins are changed only by the master admin; the master admin not here.
                $canEdit  = !$isMe && $u['role'] !== 'master_admin' && ($isMaster || !in_array($u['role'], ADMIN_ROLES, true));
                $sessions = (int) ($online[$u['user_id']] ?? 0);
                $locked   = account_locked($u);
            ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-1">
                            <span class="avatar" aria-hidden="true"><?= e(initials($u)) ?></span>
                            <span class="table-title"><?= e(full_name($u)) ?><?= $isMe ? ' <small class="text-muted">(you)</small>' : '' ?>
                                <span class="table-sub"><?= e($u['email']) ?></span></span>
                        </div>
                    </td>
                    <td>
                        <?php if ($canEdit): ?>
                        <form method="post" action="<?= e(url('/?tab=users')) ?>" class="inline-form" data-api="update_user" data-confirm="Change this user's role?">
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <label for="role-<?= $u['user_id'] ?>" class="sr-only">Role for <?= e(full_name($u)) ?></label>
                            <select id="role-<?= $u['user_id'] ?>" name="role" class="inline-select">
                                <?= options($assignable, $u['role']) ?>
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm">Save<span class="sr-only"> role for <?= e(full_name($u)) ?></span></button>
                        </form>
                        <?php else: ?>
                            <?= e(ROLES[$u['role']] ?? $u['role']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>" data-status-for="user-<?= $u['user_id'] ?>"><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></span>
                        <?php if (!$u['email_verified']): ?><span class="badge badge-pending">Unverified</span><?php endif; ?>
                        <?php if ($locked): ?><span class="badge badge-rejected" data-locked-for="<?= $u['user_id'] ?>" title="Locked <?= e(format_datetime($u['locked_at'])) ?>">Locked</span><?php endif; ?>
                    </td>
                    <td class="nowrap"><?= e(format_datetime($u['last_login_at'])) ?></td>
                    <td class="nowrap"><?= e(activity_label($u['last_activity_at'], $sessions)) ?></td>
                    <td class="nowrap"><?= $sessions ? '<span class="badge badge-active">Online</span>' . ($sessions > 1 ? ' <small>' . $sessions . ' devices</small>' : '') : '<span class="badge">Offline</span>' ?></td>
                    <td class="actions">
                        <?php if ($canEdit && $locked): ?>
                            <form method="post" action="<?= e(url('/?tab=users')) ?>" class="inline-form" data-api="update_user" data-done="remove" data-confirm="Unlock this account now?">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <input type="hidden" name="unlock" value="1">
                                <button type="submit" class="btn btn-sm btn-outline">Unlock</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canEdit): ?>
                            <form method="post" action="<?= e(url('/?tab=users')) ?>" class="inline-form" data-api="update_user"
                                  data-confirm="<?= $u['is_active'] ? 'Deactivate this account? They will be logged out and no longer able to log in.' : 'Reactivate this account?' ?>">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $u['is_active'] ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-secondary' : 'btn-success' ?>" data-toggle-active><?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="filter-empty" data-filter-empty hidden>No users match that search.</p>
    <p class="text-sm text-muted mt-1">Activity is the last authenticated request. A session counts as online until <?= SESSION_IDLE_MINUTES ?> minutes without one.</p>

<?php /* ================================================== MY FOUND POSTS (students / faculty) */ ?>
<?php elseif ($tab === 'posts'): ?>
    <?php $myPosts = newest_first(where(all_found_items(), 'user_id', $user['user_id'])); ?>
    <?php if ($myPosts): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th>Item</th><th>Found on</th><th>Review</th><th>Item status</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($myPosts as $post): ?>
                <tr>
                    <td><?= photo_tag($post['image_url'], $post['item_name'], 'photo-thumb') ?></td>
                    <td>
                        <a class="table-title" href="<?= e(item_url('found', $post['item_id'])) ?>"><?= e($post['item_name']) ?></a>
                        <?php if ($post['moderation_status'] === 'rejected' && $post['moderation_note']): ?><span class="table-sub"><?= e($post['moderation_note']) ?></span><?php endif; ?>
                    </td>
                    <td class="nowrap"><?= e(format_date($post['date_found'])) ?></td>
                    <td><?= status_badge($post['moderation_status']) ?></td>
                    <td><?= $post['moderation_status'] === 'approved' ? status_badge($post['status']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(item_url('found', $post['item_id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state('No posts yet', 'Found something on campus? Post it and bring it to the Lost & Found office.', url('/report.php?type=found'), 'Post a found item') ?>
    <?php endif; ?>

<?php /* ================================================== POST REVIEW (staff) */ ?>
<?php elseif ($tab === 'moderation'): ?>
    <?php if ($pendingPosts): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Item</th><th>Posted by</th><th>Found</th><th>Submitted</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($pendingPosts) as $post): $poster = find_user($post['user_id']); ?>
                <tr>
                    <td class="text-muted"><?= $post['item_id'] ?></td>
                    <td>
                        <a class="table-title" href="<?= e(item_url('found', $post['item_id'])) ?>"><?= e($post['item_name']) ?></a>
                        <span class="table-sub"><?= e($post['category']) ?></span>
                    </td>
                    <td><?= e(full_name($poster)) ?><span class="table-sub"><?= e($poster['email']) ?></span></td>
                    <td><?= e($post['location_found']) ?><span class="table-sub"><?= e(format_date($post['date_found'])) ?></span></td>
                    <td class="nowrap"><?= e(format_datetime($post['created_at'])) ?></td>
                    <td class="actions"><a class="btn btn-primary btn-sm" href="<?= e(item_url('found', $post['item_id'])) ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state('Nothing to review', 'There are no student or faculty posts waiting for approval.') ?>
    <?php endif; ?>

<?php /* ================================================== SECURITY LOGS (admin) */ ?>
<?php elseif ($tab === 'logs'): ?>
    <?php
    $events = db()->query('SELECT e.*, u.first_name, u.last_name, u.role FROM security_events e LEFT JOIN users u ON u.user_id = e.user_id ORDER BY e.event_id DESC LIMIT 300')->fetchAll();
    $sessionRows = db()->query('SELECT s.session_id, s.user_id, s.status, s.ip_address, s.user_agent, s.created_at, s.last_activity_at, s.expires_at, s.ended_at,
                                       u.first_name, u.last_name, u.email, u.role
                                FROM user_sessions s JOIN users u ON u.user_id = s.user_id ORDER BY s.session_id DESC LIMIT 150')->fetchAll();
    $sessionBadge = ['active' => 'active', 'logged_out' => 'closed', 'expired' => 'pending', 'revoked' => 'rejected'];
    ?>
    <h2>Login sessions</h2>
    <div class="table-wrap mb-3">
        <table class="table">
            <thead><tr><th>#</th><th>Account</th><th>Status</th><th>Logged in</th><th>Last activity</th><th>Ended / expires</th><th>IP</th><th>Device</th></tr></thead>
            <tbody>
            <?php foreach ($sessionRows as $s): ?>
                <tr>
                    <td class="text-muted"><?= $s['session_id'] ?></td>
                    <td><?= e(full_name($s)) ?><span class="table-sub"><?= e($s['email']) ?> &middot; <?= e(ROLES[$s['role']] ?? $s['role']) ?></span></td>
                    <td><span class="badge badge-<?= $sessionBadge[$s['status']] ?>"><?= e(ucwords(str_replace('_', ' ', $s['status']))) ?></span></td>
                    <td class="nowrap"><?= e(format_datetime($s['created_at'])) ?></td>
                    <td class="nowrap"><?= e(format_datetime($s['last_activity_at'])) ?></td>
                    <td class="nowrap"><?= e($s['ended_at'] ? format_datetime($s['ended_at']) : 'expires ' . date('g:i A', strtotime($s['expires_at']))) ?></td>
                    <td class="nowrap"><?= e($s['ip_address'] ?? '—') ?></td>
                    <td class="cell-wide text-sm" title="<?= e($s['user_agent']) ?>"><?= e(excerpt((string) $s['user_agent'], 60)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Security events</h2>
    <div class="table-tools">
        <input type="search" placeholder="Filter events, accounts, IPs…" aria-label="Filter security events" data-table-filter="#eventsTable">
        <span class="text-sm text-muted">Showing <span data-filter-count="#eventsTable"><?= count($events) ?></span> (latest 300)</span>
    </div>
    <div class="table-wrap">
        <table class="table" id="eventsTable">
            <thead><tr><th>When</th><th>Event</th><th>Account</th><th>Details</th><th>IP</th><th>Device</th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev): $meta = json_decode((string) $ev['metadata'], true) ?: []; ?>
                <tr>
                    <td class="nowrap"><?= e(format_datetime($ev['created_at'])) ?></td>
                    <td class="nowrap"><span class="badge badge-<?= e(event_badge($ev['event_type'])) ?>"><?= e(str_replace('_', ' ', $ev['event_type'])) ?></span></td>
                    <td>
                        <?= $ev['user_id'] ? e(full_name($ev)) : '<span class="text-muted">Unknown account</span>' ?>
                        <span class="table-sub"><?= e($ev['email'] ?? '') ?><?= $ev['role'] ? ' &middot; ' . e(ROLES[$ev['role']] ?? $ev['role']) : '' ?></span>
                    </td>
                    <td class="text-sm"><?php foreach ($meta as $k => $v): ?><?= e(str_replace('_', ' ', (string) $k)) ?>: <?= e(is_scalar($v) ? (string) $v : json_encode($v)) ?><br><?php endforeach; ?><?= $ev['session_id'] ? '<span class="text-muted">session #' . (int) $ev['session_id'] . '</span>' : '' ?></td>
                    <td class="nowrap"><?= e($ev['ip_address'] ?? '—') ?></td>
                    <td class="cell-wide text-sm" title="<?= e($ev['user_agent']) ?>"><?= e(excerpt((string) $ev['user_agent'], 50)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="filter-empty" data-filter-empty hidden>No events match that filter.</p>

<?php /* ================================================== ACCOUNT */ ?>
<?php elseif ($tab === 'account'): ?>
    <?php
    $err = fn (string $key) => isset($errors[$key]) ? '<span class="form-error" id="' . $key . '-error">' . e($errors[$key]) . '</span>' : '';
    $inv = fn (string $key) => isset($errors[$key]) ? ' class="is-invalid" aria-invalid="true"' : '';
    ?>
    <?php if (isset($_GET['changed'])): ?>
        <div class="alert alert-success" role="status">Password changed. Any other device signed in to this account has been logged out.</div>
    <?php endif; ?>

    <div class="grid grid-2">
        <div class="card">
            <h2>Profile</h2>
            <div class="flex items-center gap-2 mb-3">
                <span class="avatar" aria-hidden="true"><?= e(initials($user)) ?></span>
                <div>
                    <div class="fw-600"><?= e(full_name($user)) ?></div>
                    <div class="text-sm text-muted"><?= e($user['email']) ?></div>
                </div>
            </div>
            <dl class="item-card-meta">
                <div><dt>Role</dt><dd><?= e(ROLES[$user['role']] ?? $user['role']) ?></dd></div>
                <div><dt>Member since</dt><dd><?= e(format_date($user['created_at'])) ?></dd></div>
            </dl>
            <p class="text-sm text-muted mb-0">To change your name or role, contact the Lost &amp; Found office.</p>
        </div>

        <div class="card">
            <h2>Change password</h2>
            <form method="post" action="<?= e(url('/?tab=account')) ?>" class="form" data-validate>
                <input type="email" name="username" value="<?= e($user['email']) ?>" autocomplete="username" hidden>
                <div class="form-group">
                    <label for="current_password">Current password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password" aria-describedby="current_password-error"<?= $inv('current_password') ?>>
                    <?= $err('current_password') ?>
                </div>
                <div class="form-group">
                    <label for="new_password">New password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="new_password" name="new_password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" data-label="New password" data-password-policy<?= $inv('new_password') ?>>
                    <?= $err('new_password') ?>
                </div>
                <div class="form-group">
                    <label for="new_password_confirm">Confirm new password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" required autocomplete="new-password" data-match="new_password" data-label="Confirm new password" data-no-paste<?= $inv('new_password_confirm') ?>>
                    <?= $err('new_password_confirm') ?>
                </div>
                <button type="submit" class="btn btn-primary">Change password</button>
            </form>
        </div>
    </div>

<?php /* ================================================== STATISTICS (admin) */ ?>
<?php else: ?>
    <?php
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

    $activity = [];
    // Plain text, not links: admins (the only viewers of this tab) don't open item, report or claim records.
    foreach ($items as $i)   $activity[] = ['at' => $i['created_at'], 'text' => 'Found item logged: ' . $i['item_name'], 'badge' => 'stored'];
    foreach ($reports as $r) $activity[] = ['at' => $r['created_at'], 'text' => 'Lost report filed: ' . $r['item_name'], 'badge' => 'open'];
    foreach ($claims as $c)  $activity[] = ['at' => $c['created_at'], 'text' => 'Claim submitted on ' . find_found_item($c['item_id'])['item_name'], 'badge' => $c['status']];
    $activity = array_slice(newest_first($activity, 'at'), 0, 8);

    $bar = fn (int $n, int $max, string $cls = '') => '<div class="bar-track"><div class="bar-fill ' . $cls . '" data-width="' . round($n / max(1, $max) * 100) . '"></div></div>';
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
                        <div><?= e($a['text']) ?> <?= status_badge($a['badge']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
