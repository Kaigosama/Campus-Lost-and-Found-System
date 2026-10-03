<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * ?type=lost            file a lost report (students / faculty only)     ?type=lost&id=N    edit your own open report
 * ?type=found           log a found item at intake (staff), or post one you found (students / faculty; staff
 *                       approve it before it is public)                ?type=found&id=N   edit a found item (staff)
 * New records post through api/add_item.php, edits through api/update_item.php (both multipart, photo included).
 * Staff log found items but don't file lost reports; administrators do neither.
 */
$user  = current_user();
$type  = ($_GET['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
$id    = (int) ($_GET['id'] ?? 0);
$today = date('Y-m-d');
$row   = null;

require_role(match (true) {
    $type === 'lost' => 'user',
    $id > 0          => 'staff',
    default          => ['staff', 'user'],
});
if ($type === 'found') {
    if ($id) {
        $row = find_found_item($id);
        if (!$row) {
            abort(404, 'Item not found', '', url('/browse.php?manage=1'), 'Back to manage items');
        }
    }
} elseif ($id) {
    $row = find_lost_report($id);
    // Only the owner may edit, and only while the report is still open.
    if (!$row || $row['user_id'] !== $user['user_id'] || $row['status'] !== 'open') {
        abort(404, 'Report not found', 'This report does not exist, is not yours, or is no longer open.', url('/?tab=reports'), 'Back to my reports');
    }
}

$editing    = $row !== null;
$isPost     = $type === 'found' && !$editing && has_role('user');   // a student's post, reviewed before it is public
$formAction = url('/report.php?type=' . $type . ($editing ? '&id=' . $id : ''));
$formMode   = 'data-api="' . ($editing ? 'update_item' : 'add_item') . '" data-type="' . $type . '"';
$pageTitle  = match (true) {
    $type === 'found' && $editing => 'Edit: ' . $row['item_name'],
    $isPost                       => 'Post a found item',
    $type === 'found'             => 'Log a found item',
    $editing                      => 'Edit report',
    default                       => 'Report a lost item',
};

include APP_ROOT . '/templates/layout/header.php';
?>

<?php if ($type === 'found'): ?>
<!-- ====================================================== FOUND ITEM (staff) -->
<div class="breadcrumb">
    <?php if ($isPost): ?>
        <a href="<?= e(url('/')) ?>">Dashboard</a><span>Post a found item</span>
    <?php else: ?>
        <a href="<?= e(url('/browse.php?manage=1')) ?>">Manage found items</a>
        <?php if ($editing): ?><span><a href="<?= e(item_url('found', $id)) ?>"><?= e($row['item_name']) ?></a></span><span>Edit</span>
        <?php else: ?><span>Log found item</span><?php endif; ?>
    <?php endif; ?>
</div>

<div class="page-header">
    <div>
        <?php if ($editing): ?>
            <h1>Edit item #<?= $id ?></h1>
            <p>Logged <?= e(format_datetime($row['created_at'])) ?> &middot; last updated <?= e(format_datetime($row['updated_at'])) ?></p>
        <?php elseif ($isPost): ?>
            <h1>Post a found item</h1>
            <p>Found something on campus? Describe it here. Staff review every post before it appears in the public list.</p>
        <?php else: ?>
            <h1>Log a found item</h1>
            <p>Record an item turned in to the office and where it is stored.</p>
        <?php endif; ?>
    </div>
    <?php if ($editing): ?><a class="btn btn-outline" href="<?= e(item_url('found', $id)) ?>">View item</a><?php endif; ?>
</div>

<div class="grid <?= $editing ? '' : 'grid-sidebar' ?>">
    <div class="card">
        <form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="form" data-validate data-submit-guard <?= $formMode ?>>
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

            <fieldset>
                <legend>Public details</legend>
                <p class="text-sm text-muted">Shown to everyone browsing found items. Keep it general enough that it doesn't give away verification details.</p>

                <div class="form-group">
                    <label for="item_name">Item name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="item_name" name="item_name" required maxlength="150"
                           value="<?= e($row['item_name'] ?? '') ?>" placeholder="e.g. Blue JanSport backpack" <?= $editing ? '' : 'autofocus' ?>>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category <span class="req" aria-hidden="true">*</span></label>
                        <select id="category" name="category" required>
                            <option value="">Select a category…</option>
                            <?= options(CATEGORIES, $row['category'] ?? null, false) ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_found">Date found <span class="req" aria-hidden="true">*</span></label>
                        <input type="date" id="date_found" name="date_found" required max="<?= $today ?>" value="<?= e($row['date_found'] ?? $today) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="location_found">Where was it found? <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="location_found" name="location_found" required maxlength="150" list="campus-locations"
                           value="<?= e($row['location_found'] ?? '') ?>" placeholder="e.g. Gymnasium bleachers">
                </div>

                <div class="form-group">
                    <label for="description">Public description <span class="req" aria-hidden="true">*</span></label>
                    <textarea id="description" name="description" required minlength="15" maxlength="2000"
                              placeholder="Colour, size, brand, general condition."><?= e($row['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="photo">Photo</label>
                    <div class="photo-upload">
                        <div class="photo-preview" id="photoPreview">
                            <?= !empty($row['image_url']) ? photo_tag($row['image_url'], $row['item_name']) : 'No photo selected' ?>
                        </div>
                        <div>
                            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="#photoPreview">
                            <span class="form-hint">JPG, PNG or WEBP, up to <?= MAX_UPLOAD_MB ?> MB. Avoid photographing anything that reveals private details (ID numbers, contents).</span>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="staff-only">
                <legend><?= $isPost ? 'Details only staff see' : 'Staff-only details' ?></legend>
                <p class="text-sm text-muted">Never shown publicly. Used to verify ownership claims<?= $isPost ? '' : ' and to locate the item physically' ?>.</p>

                <?php if (!$isPost): ?>
                <div class="form-group">
                    <label for="storage_location">Storage location <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="storage_location" name="storage_location" required maxlength="150"
                           value="<?= e($row['storage_location'] ?? '') ?>" placeholder="e.g. Cabinet B, Shelf 2">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="private_details">Private / distinguishing details <span class="req" aria-hidden="true">*</span></label>
                    <textarea id="private_details" name="private_details" required minlength="15" maxlength="2000"
                              placeholder="Contents, serial numbers, engravings, scratches, names inside — things a real owner would know."><?= e($row['private_details'] ?? '') ?></textarea>
                </div>

                <?php if ($editing): ?>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status"><?= options(FOUND_STATUSES, $row['status']) ?></select>
                    <span class="form-hint">Use "Returned" from the claim hand-over on the item page so the linked report and other claims are updated automatically.</span>
                </div>
                <?php endif; ?>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg"><?= $editing ? 'Save changes' : ($isPost ? 'Submit for review' : 'Save to storage') ?></button>
                <a class="btn btn-secondary btn-lg" href="<?= e($editing ? item_url('found', $id) : url($isPost ? '/' : '/browse.php?manage=1')) ?>">Cancel</a>
                <span class="form-hint" data-guard-hint hidden>Please complete all required fields before submitting.</span>
            </div>
        </form>
    </div>

    <?php if ($isPost): ?>
    <aside>
        <div class="card card-muted">
            <h3>What happens next</h3>
            <ol class="text-sm list-plain">
                <li>Your post is saved as <?= status_badge('pending') ?> and only you and staff can see it.</li>
                <li>Bring the item to the Lost &amp; Found office (Admin Bldg, Rm 104).</li>
                <li>Staff check it, record where it is stored and approve the post. It then appears in the public list.</li>
            </ol>
            <p class="text-sm mb-0">Follow its status under <a href="<?= e(url('/?tab=posts')) ?>">My Found Posts</a>.</p>
        </div>
    </aside>
    <?php elseif (!$editing): ?>
    <aside>
        <div class="card card-muted">
            <h3>Intake checklist</h3>
            <ol class="text-sm list-plain">
                <li>Tag the item with the ID shown after saving.</li>
                <li>Photograph it without exposing private details.</li>
                <li>Note distinguishing marks in the staff-only box.</li>
                <li>Place it in storage and record the exact location.</li>
                <li>Check <a href="<?= e(url('/browse.php?type=lost')) ?>">open lost reports</a> for a match.</li>
            </ol>
        </div>
    </aside>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ====================================================== LOST REPORT (user) -->
<div class="breadcrumb">
    <a href="<?= e(url('/')) ?>">Dashboard</a>
    <?php if ($editing): ?><span><a href="<?= e(item_url('lost', $id)) ?>"><?= e($row['item_name']) ?></a></span><span>Edit</span>
    <?php else: ?><span>Report lost item</span><?php endif; ?>
</div>

<div class="page-header">
    <div>
        <?php if ($editing): ?>
            <h1>Edit report #<?= $id ?></h1>
            <p>Filed <?= e(format_datetime($row['created_at'])) ?>. Changes are visible to Lost &amp; Found staff.</p>
        <?php else: ?>
            <h1>Report a lost item</h1>
            <p>Tell us what you lost. Staff will match it against items turned in to the office.</p>
        <?php endif; ?>
    </div>
</div>

<?= false_report_notice($user) ?>
<?php if (!$editing && ($wait = report_limit_wait($user['user_id']))): ?>
    <div class="alert alert-warning" role="status"><?= e(report_limit_message($wait)) ?></div>
<?php endif; ?>

<div class="grid grid-sidebar">
    <div class="card">
        <form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="form" data-validate data-submit-guard <?= $formMode ?>>
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php else: ?><input type="hidden" name="confirm_duplicate" value="0"><?php endif; ?>

            <div class="form-group">
                <label for="item_name">What did you lose? <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="item_name" name="item_name" required maxlength="150"
                       value="<?= e($row['item_name'] ?? '') ?>" placeholder="e.g. Black JBL earbuds case" <?= $editing ? '' : 'autofocus' ?>>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category">Category <span class="req" aria-hidden="true">*</span></label>
                    <select id="category" name="category" required>
                        <option value="">Select a category…</option>
                        <?= options(CATEGORIES, $row['category'] ?? null, false) ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_lost">Date lost <span class="req" aria-hidden="true">*</span></label>
                    <input type="date" id="date_lost" name="date_lost" required max="<?= $today ?>" value="<?= e($row['date_lost'] ?? $today) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="location_lost">Where did you last have it? <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="location_lost" name="location_lost" required maxlength="150" list="campus-locations"
                       value="<?= e($row['location_lost'] ?? '') ?>" placeholder="e.g. Library, 3rd floor">
            </div>

            <div class="form-group">
                <label for="description">Description <span class="req" aria-hidden="true">*</span></label>
                <textarea id="description" name="description" required minlength="20" maxlength="2000"
                          placeholder="Brand, colour, size, stickers, contents — anything that helps staff recognise it."><?= e($row['description'] ?? '') ?></textarea>
                <span class="form-hint">At least 20 characters. This is visible to Lost &amp; Found staff only.</span>
            </div>

            <div class="form-group">
                <label for="photo"><?= $editing ? 'Replace photo (optional)' : 'Photo (optional)' ?></label>
                <div class="photo-upload">
                    <div class="photo-preview" id="photoPreview">
                        <?= !empty($row['image_url']) ? photo_tag($row['image_url'], $row['item_name']) : 'No photo selected' ?>
                    </div>
                    <div>
                        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="#photoPreview">
                        <span class="form-hint">
                            <?= $editing ? 'Leave empty to keep the current photo.' : 'JPG, PNG or WEBP, up to ' . MAX_UPLOAD_MB . ' MB. A photo of the same item or model helps a lot.' ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg"><?= $editing ? 'Save changes' : 'Submit report' ?></button>
                <a class="btn btn-secondary btn-lg" href="<?= e($editing ? item_url('lost', $id) : url('/')) ?>">Cancel</a>
                <span class="form-hint" data-guard-hint hidden>Please complete all required fields before submitting.</span>
            </div>
        </form>
    </div>

    <aside>
        <div class="card card-muted">
            <h3>Before you submit</h3>
            <p class="text-sm">Check <a href="<?= e(url('/browse.php')) ?>">found items</a> first — your item may already be in storage.</p>
            <p class="text-sm">You can file up to <?= REPORT_LIMIT_MAX ?> reports every <?= REPORT_LIMIT_WINDOW_MINUTES ?> minutes. Reports confirmed as intentionally false count against your account.</p>
            <h3 class="mt-2">What happens next</h3>
            <ol class="text-sm list-plain">
                <li>Your report is saved with status <?= status_badge('open') ?>.</li>
                <li>Staff compare new intake items against open reports.</li>
                <li>If a match is found you'll see it on your dashboard and can submit a claim.</li>
            </ol>
        </div>
    </aside>
</div>
<?php endif; ?>

<datalist id="campus-locations"><?php foreach (CAMPUS_LOCATIONS as $loc): ?><option value="<?= e($loc) ?>"><?php endforeach; ?></datalist>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
