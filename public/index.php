<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Guests      → landing page. Logging in and registering happen on login.php and register.php.
 * Logged in   → dashboard sections (dashboard_tabs()): overview | account   students/faculty: + reports (their own) | my_claims | posts
 *               staff: + reports | items | queue | returned | disposed | moderation | stats
 *               admins: + reports | items | queue | returned | disposed | stats (the staff views, read-only)
 *                       + users (with account activity) | categories | logs
 *               Every management list lives here, once; each section's page is templates/dashboard/<key>.php.
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
    $pageTitle = null;

    $storedCount   = count(public_found_items());
    $returnedCount = count_where(all_found_items(), 'status', 'returned');
    $openReports   = count_where(all_lost_reports(), 'status', 'open');
    $recentItems   = array_slice(newest_first(array_values(public_found_items()), 'date_found'), 0, 5); // CSS trims to one full row
    $reunited      = array_slice(newest_first(where(all_found_items(), 'status', 'returned'), 'updated_at'), 0, 3);

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
        <span class="stat-note">8:00 AM – 5:00 PM, Admin Office</span>
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
    <div class="item-grid item-grid-row">
        <?php foreach ($recentItems as $item) echo item_card($item); ?>
    </div>
</section>
<?php endif; ?>

<?php $heart = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>'; ?>
<?php if ($reunited): ?>
<section class="section reunited" aria-labelledby="reunited-title">
    <div class="reunited-intro">
        <div>
            <h2 id="reunited-title" class="icon-heading"><?= $heart ?> Back with their owners</h2>
            <p class="text-muted">Items that made it home.</p>
            <p class="mb-0">Each one was turned in to the Lost &amp; Found office, claimed by someone who described details only the
                owner would know, and checked by staff against the intake record before it was handed over.</p>
        </div>
        <div class="reunited-count">
            <span class="stat-value"><?= $returnedCount ?></span>
            <span class="stat-note">item<?= $returnedCount === 1 ? '' : 's' ?> returned to <?= $returnedCount === 1 ? 'its owner' : 'their owners' ?></span>
        </div>
    </div>
    <div class="item-grid">
        <?php foreach ($reunited as $item): ?>
            <article class="item-card">
                <div class="item-card-photo"><?= photo_tag($item['image_url'], $item['item_name']) ?></div>
                <div class="item-card-body">
                    <h3 class="item-card-title icon-heading"><?= $heart ?> <?= e($item['item_name']) ?> is back with its owner</h3>
                    <p class="item-card-desc mb-0">Found at <?= e($item['location_found']) ?> on <?= e(format_date($item['date_found'])) ?>
                        and returned on <?= e(format_date($item['returned_at'] ?? $item['updated_at'])) ?> after staff verified the claim.</p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section" aria-labelledby="safety-title">
    <div class="section-title"><h2 id="safety-title">Claiming is easier when everyone plays fair</h2></div>
    <p class="text-muted">A few simple habits keep items going back to the right people.</p>
    <div class="steps">
        <div class="step">
            <span class="tip-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="1"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>
            <h3>Describe what only you know</h3>
            <p>Mention marks, contents or engravings that aren't in the listing. Staff compare them with the private record made at intake.</p>
        </div>
        <div class="step">
            <span class="tip-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.6-1.5 1.7-2 3-2s2.4.5 3 2M14 10h4M14 13h3"/></svg></span>
            <h3>Bring your ID to pick it up</h3>
            <p>Approved items are released only at the Lost &amp; Found desk in the Admin Office, after staff check your ID.</p>
        </div>
        <div class="step">
            <span class="tip-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
            <h3>Only claim what's yours</h3>
            <p>False claims are logged against your account and may be reported to the Office of Student Affairs.</p>
        </div>
    </div>
</section>

    <?php
    include APP_ROOT . '/templates/layout/footer.php';
    exit;
}

/* ------------------------------------------------------------ Dashboard */

if (is_admin()) {
    run_retention();   // keeps the Users list free of sign-ups that never confirmed; drops old ID numbers
}

$allTabs = dashboard_tabs(true);
$tab = $_GET['tab'] ?? 'overview';
if (!isset($allTabs[$tab])) {
    abort(404, 'Page not found', '', url('/'), 'Back to dashboard');
}
if ($allTabs[$tab][1] !== null) {
    require_role($allTabs[$tab][1]);
}
// Remember each list's filters, sort and page so a record's Back link returns to the list as it was.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['id'])) {
    $_SESSION['list_state'][$tab] = array_diff_key($_GET, ['tab' => 1, 'changed' => 1]);
}

$errors = [];
if ($tab === 'account' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = change_password($user, $_POST);
    if (!$errors) {
        header('Location: ' . url('/?tab=account&changed=1'));
        exit;
    }
}

$myReports = newest_first(where(all_lost_reports(), 'reported_by', $user['user_id']));
$reportRows = can_monitor() ? newest_first(array_values(all_lost_reports())) : $myReports;   // the Lost Reports tab
$myClaims  = newest_first(where(all_claims(), 'claimant_id', $user['user_id']));
$storedCount = count(public_found_items());
$pendingPosts = newest_first(where(all_found_items(), 'moderation_status', 'pending'));

$pageTitle = $allTabs[$tab][0];
include APP_ROOT . '/templates/layout/header.php';
?>

<?= dashboard_nav($tab) ?>

<?php include APP_ROOT . '/templates/dashboard/' . $tab . '.php';   // $tab is a dashboard_tabs() key, checked above ?>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
