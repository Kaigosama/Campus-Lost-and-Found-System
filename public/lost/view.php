<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();

$user   = current_user();
$report = mock_lost_report((int) ($_GET['id'] ?? 0));

// Only the owner or staff may view a report.
if (!$report || ($report['user_id'] !== $user['user_id'] && !is_staff())) {
    http_response_code(404);
    $pageTitle = 'Report not found';
    include APP_PATH . '/views/layout/header.php';
    $emptyTitle = 'Report not found';
    $emptyText  = 'This lost report does not exist or you do not have access to it.';
    $emptyActionUrl = url('/lost/my-reports.php');
    $emptyActionLabel = 'Back to my reports';
    include APP_PATH . '/views/partials/empty-state.php';
    include APP_PATH . '/views/layout/footer.php';
    exit;
}

$isOwner  = $report['user_id'] === $user['user_id'];
$editing  = isset($_GET['edit']) && $isOwner && $report['status'] === 'open';
$owner    = mock_user($report['user_id']);
$linkedClaims = array_values(array_filter(mock_claims(), fn ($c) => $c['lost_report_id'] === $report['report_id']));
$pageTitle = $report['item_name'];
$today = date('Y-m-d');

include APP_PATH . '/views/layout/header.php';
?>

<div class="breadcrumb">
    <a href="<?= e(url($isOwner ? '/lost/my-reports.php' : '/lost/all.php')) ?>"><?= $isOwner ? 'My lost reports' : 'All lost reports' ?></a>
    <span><?= e($report['item_name']) ?></span>
</div>

<div class="page-header">
    <div>
        <h1><?= e($report['item_name']) ?> <?= status_badge($report['status']) ?></h1>
        <p>Report #<?= $report['report_id'] ?> &middot; filed <?= e(format_datetime($report['created_at'])) ?></p>
    </div>
    <?php if ($isOwner && $report['status'] === 'open' && !$editing): ?>
        <div class="btn-row">
            <a class="btn btn-outline" href="<?= e(url_with(['edit' => 1])) ?>">Edit</a>
            <form method="post" action="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>" data-confirm="Close this report? Do this if you found the item or no longer need help." style="display:inline">
                <input type="hidden" name="action" value="close">
                <button type="submit" class="btn btn-secondary">Mark as found / close</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-sidebar">
    <div>
    <?php if ($editing): ?>
        <div class="card">
            <h2>Edit report</h2>
            <form method="post" action="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>" enctype="multipart/form-data" class="form" data-validate data-mock>
                <input type="hidden" name="action" value="update">

                <div class="form-group">
                    <label for="item_name">Item name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="item_name" name="item_name" required maxlength="150" value="<?= e($report['item_name']) ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category <span class="req" aria-hidden="true">*</span></label>
                        <select id="category" name="category" required><?= options(CATEGORIES, $report['category'], false) ?></select>
                    </div>
                    <div class="form-group">
                        <label for="date_lost">Date lost <span class="req" aria-hidden="true">*</span></label>
                        <input type="date" id="date_lost" name="date_lost" required max="<?= $today ?>" value="<?= e($report['date_lost']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="location_lost">Location <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="location_lost" name="location_lost" required maxlength="150" list="campus-locations" value="<?= e($report['location_lost']) ?>">
                    <datalist id="campus-locations"><?php foreach (CAMPUS_LOCATIONS as $loc): ?><option value="<?= e($loc) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="form-group">
                    <label for="description">Description <span class="req" aria-hidden="true">*</span></label>
                    <textarea id="description" name="description" required minlength="20" maxlength="2000"><?= e($report['description']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="photo">Replace photo (optional)</label>
                    <div class="photo-upload">
                        <div class="photo-preview" id="photoPreview"><?= $report['photo_path'] ? photo_tag($report['photo_path'], $report['item_name']) : 'No photo' ?></div>
                        <div>
                            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="#photoPreview">
                            <span class="form-hint">Leave empty to keep the current photo.</span>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a class="btn btn-secondary" href="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="grid grid-2">
                <div><?= photo_tag($report['photo_path'], $report['item_name'], 'photo-large') ?></div>
                <dl class="detail-list">
                    <dt>Category</dt><dd><?= e($report['category']) ?></dd>
                    <dt>Date lost</dt><dd><?= e(format_date($report['date_lost'])) ?></dd>
                    <dt>Last seen at</dt><dd><?= e($report['location_lost']) ?></dd>
                    <dt>Status</dt><dd><?= status_badge($report['status']) ?></dd>
                    <?php if (is_staff()): ?>
                        <dt>Reported by</dt><dd><?= e(full_name($owner)) ?><br><small><?= e($owner['email']) ?></small></dd>
                    <?php endif; ?>
                    <dt>Last updated</dt><dd><?= e(format_datetime($report['updated_at'])) ?></dd>
                </dl>
            </div>
            <hr>
            <h3>Description</h3>
            <p class="mb-0" style="white-space:pre-line"><?= e($report['description']) ?></p>
        </div>
    <?php endif; ?>

        <?php if ($linkedClaims): ?>
        <div class="card mt-2">
            <h2>Linked claims</h2>
            <ul class="timeline">
                <?php foreach ($linkedClaims as $claim): $item = mock_found_item($claim['item_id']); ?>
                    <li>
                        <time><?= e(format_date($claim['created_at'])) ?></time>
                        <div>
                            Claim on <a href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>" class="fw-600"><?= e($item['item_name']) ?></a>
                            <?= status_badge($claim['status']) ?>
                            <?php if ($claim['review_note']): ?><div class="text-sm text-muted"><?= e($claim['review_note']) ?></div><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <aside>
        <?php if ($report['status'] === 'open'): ?>
            <div class="card card-muted">
                <h3>Think you've spotted it?</h3>
                <p class="text-sm">Browse the found items list and submit a claim if you see your item.</p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/found/browse.php?category=' . rawurlencode($report['category']))) ?>">Browse <?= e($report['category']) ?></a>
            </div>
        <?php elseif ($report['status'] === 'matched'): ?>
            <div class="alert alert-warning mb-0">
                <strong>Possible match.</strong> Staff matched this report to a found item. Check your <a href="<?= e(url('/claims/my-claims.php')) ?>">claims</a> for next steps.
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">This report is closed. Thanks for letting us know!</div>
        <?php endif; ?>

        <?php if (is_staff() && $report['status'] === 'open'): ?>
            <div class="card card-staff mt-2">
                <h3>Staff actions</h3>
                <p class="text-sm text-muted">Found a match in storage? Link it so the owner is notified.</p>
                <form method="post" action="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>" class="form" data-validate data-mock>
                    <input type="hidden" name="action" value="match">
                    <div class="form-group">
                        <label for="match_item">Matching found item</label>
                        <select id="match_item" name="item_id" required>
                            <option value="">Select an item…</option>
                            <?php foreach (mock_found_items() as $fi): if ($fi['status'] !== 'stored') continue; ?>
                                <option value="<?= $fi['item_id'] ?>">#<?= $fi['item_id'] ?> — <?= e($fi['item_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-accent btn-sm">Mark as matched</button>
                </form>
            </div>
        <?php endif; ?>
    </aside>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
