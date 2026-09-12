<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = current_user();
$item = mock_found_item((int) ($_GET['id'] ?? 0));

// Non-staff may only see items that are still in storage.
if (!$item || (!is_staff() && $item['status'] !== 'stored')) {
    http_response_code(404);
    $pageTitle = 'Item not found';
    include APP_PATH . '/views/layout/header.php';
    $emptyTitle = 'Item not found';
    $emptyText  = 'This item is no longer listed. It may have been returned to its owner.';
    $emptyActionUrl = url('/found/browse.php');
    $emptyActionLabel = 'Back to found items';
    include APP_PATH . '/views/partials/empty-state.php';
    include APP_PATH . '/views/layout/footer.php';
    exit;
}

$pageTitle = $item['item_name'];
$itemClaims = array_values(array_filter(mock_claims(), fn ($c) => $c['item_id'] === $item['item_id']));
$myClaim = null;
if ($user) {
    foreach ($itemClaims as $c) {
        if ($c['user_id'] === $user['user_id']) { $myClaim = $c; break; }
    }
}
$pendingCount = count(array_filter($itemClaims, fn ($c) => $c['status'] === 'pending'));
$loggedBy = mock_user($item['user_id']);

include APP_PATH . '/views/layout/header.php';
?>

<div class="breadcrumb"><a href="<?= e(url('/found/browse.php')) ?>">Found items</a><span><?= e($item['item_name']) ?></span></div>

<div class="page-header">
    <div>
        <span class="item-card-category"><?= e($item['category']) ?></span>
        <h1><?= e($item['item_name']) ?> <?= status_badge($item['status']) ?></h1>
        <p>Item #<?= $item['item_id'] ?> &middot; turned in <?= e(format_date($item['date_found'])) ?></p>
    </div>
    <?php if (is_staff()): ?>
        <div class="btn-row">
            <a class="btn btn-outline" href="<?= e(url('/found/edit.php?id=' . $item['item_id'])) ?>">Edit</a>
            <a class="btn btn-secondary" href="<?= e(url('/found/manage.php')) ?>">Manage items</a>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-sidebar">
    <div>
        <div class="card">
            <div class="grid grid-2">
                <div><?= photo_tag($item['photo_path'], $item['item_name'], 'photo-large') ?></div>
                <dl class="detail-list">
                    <dt>Category</dt><dd><?= e($item['category']) ?></dd>
                    <dt>Date found</dt><dd><?= e(format_date($item['date_found'])) ?></dd>
                    <dt>Found at</dt><dd><?= e($item['location_found']) ?></dd>
                    <dt>Status</dt><dd><?= status_badge($item['status']) ?></dd>
                    <dt>Pickup</dt><dd>Lost &amp; Found office<br><small>Admin Bldg, Rm 104 · Mon–Fri 8 AM–5 PM</small></dd>
                </dl>
            </div>
            <hr>
            <h3>Description</h3>
            <p class="mb-0" style="white-space:pre-line"><?= e($item['description']) ?></p>
        </div>

        <?php if (is_staff()): ?>
        <!-- Staff-only panel. The backend must not send these fields to non-staff at all. -->
        <div class="card card-staff mt-2">
            <div class="card-header">
                <h2>Staff details</h2>
                <span class="badge badge-role-staff">Not visible to users</span>
            </div>
            <dl class="detail-list">
                <dt>Storage location</dt><dd class="fw-600"><?= e($item['storage_location']) ?></dd>
                <dt>Private details</dt><dd><div class="quote staff"><?= e($item['private_details']) ?></div></dd>
                <dt>Logged by</dt><dd><?= e(full_name($loggedBy)) ?> &middot; <?= e(format_datetime($item['created_at'])) ?></dd>
                <?php if ($item['returned_at']): ?><dt>Returned</dt><dd><?= e(format_datetime($item['returned_at'])) ?></dd><?php endif; ?>
            </dl>

            <?php if ($itemClaims): ?>
                <hr>
                <h3>Claims on this item (<?= count($itemClaims) ?>)</h3>
                <ul class="timeline">
                    <?php foreach ($itemClaims as $claim): $claimant = mock_user($claim['user_id']); ?>
                        <li>
                            <time><?= e(format_date($claim['created_at'])) ?></time>
                            <div style="flex:1">
                                <span class="fw-600"><?= e(full_name($claimant)) ?></span> <?= status_badge($claim['status']) ?>
                                <div class="text-sm text-muted"><?= e(excerpt($claim['proof_description'], 90)) ?></div>
                            </div>
                            <a class="btn btn-outline btn-sm" href="<?= e(url('/claims/review.php?id=' . $claim['claim_id'])) ?>">Review</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <aside>
        <?php if (!$user): ?>
            <div class="card">
                <h3>Is this yours?</h3>
                <p class="text-sm">Log in with your Mapua account to submit an ownership claim.</p>
                <a class="btn btn-primary btn-block" href="<?= e(url('/login.php?next=' . rawurlencode(url('/found/view.php?id=' . $item['item_id'])))) ?>">Log in to claim</a>
            </div>
        <?php elseif ($myClaim): ?>
            <div class="card">
                <h3>Your claim</h3>
                <p><?= status_badge($myClaim['status']) ?> <small>submitted <?= e(format_date($myClaim['created_at'])) ?></small></p>
                <?php if ($myClaim['status'] === 'pending'): ?>
                    <p class="text-sm text-muted">Staff are reviewing your claim. You'll see the result here and on <a href="<?= e(url('/claims/my-claims.php')) ?>">My Claims</a>.</p>
                <?php elseif ($myClaim['status'] === 'approved'): ?>
                    <div class="alert alert-success text-sm mb-0"><?= e($myClaim['review_note']) ?></div>
                <?php else: ?>
                    <div class="alert alert-error text-sm mb-0"><?= e($myClaim['review_note']) ?></div>
                <?php endif; ?>
            </div>
        <?php elseif ($item['status'] === 'stored'): ?>
            <div class="card">
                <h3>Is this yours?</h3>
                <p class="text-sm">Submit a claim describing details only the owner would know. Staff will compare it against the intake record.</p>
                <a class="btn btn-primary btn-block" href="<?= e(url('/claims/submit.php?item=' . $item['item_id'])) ?>">Claim this item</a>
                <?php if ($pendingCount): ?>
                    <p class="text-sm text-muted mt-1 mb-0"><?= $pendingCount ?> other claim<?= $pendingCount === 1 ? ' is' : 's are' ?> pending review.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card card-muted mt-2">
            <h3>Tips for a successful claim</h3>
            <ul class="text-sm" style="padding-left:1.1rem;margin:0">
                <li>Mention contents, scratches, stickers, engravings.</li>
                <li>Include brand and model if you know them.</li>
                <li>Link your lost report if you filed one.</li>
            </ul>
        </div>
    </aside>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
