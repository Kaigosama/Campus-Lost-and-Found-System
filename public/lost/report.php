<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();

$pageTitle = 'Report a lost item';
$today = date('Y-m-d');

include APP_PATH . '/views/layout/header.php';
?>

<div class="breadcrumb"><a href="<?= e(url('/dashboard.php')) ?>">Dashboard</a><span>Report lost item</span></div>

<div class="page-header">
    <div>
        <h1>Report a lost item</h1>
        <p>Tell us what you lost. Staff will match it against items turned in to the office.</p>
    </div>
</div>

<div class="grid grid-sidebar">
    <div class="card">
        <form method="post" action="<?= e(url('/lost/report.php')) ?>" enctype="multipart/form-data" class="form" data-validate data-mock>

            <div class="form-group">
                <label for="item_name">What did you lose? <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="item_name" name="item_name" required maxlength="150" placeholder="e.g. Black JBL earbuds case" autofocus>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category">Category <span class="req" aria-hidden="true">*</span></label>
                    <select id="category" name="category" required>
                        <option value="">Select a category…</option>
                        <?= options(CATEGORIES, null, false) ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_lost">Date lost <span class="req" aria-hidden="true">*</span></label>
                    <input type="date" id="date_lost" name="date_lost" required max="<?= $today ?>" value="<?= $today ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="location_lost">Where did you last have it? <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="location_lost" name="location_lost" required maxlength="150" list="campus-locations" placeholder="e.g. Library, 3rd floor">
                <datalist id="campus-locations"><?php foreach (CAMPUS_LOCATIONS as $loc): ?><option value="<?= e($loc) ?>"><?php endforeach; ?></datalist>
            </div>

            <div class="form-group">
                <label for="description">Description <span class="req" aria-hidden="true">*</span></label>
                <textarea id="description" name="description" required minlength="20" maxlength="2000"
                          placeholder="Brand, colour, size, stickers, contents — anything that helps staff recognise it."></textarea>
                <span class="form-hint">At least 20 characters. This is visible to Lost &amp; Found staff only.</span>
            </div>

            <div class="form-group">
                <label for="photo">Photo (optional)</label>
                <div class="photo-upload">
                    <div class="photo-preview" id="photoPreview">No photo selected</div>
                    <div>
                        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="#photoPreview">
                        <span class="form-hint">JPG, PNG or WEBP, up to <?= MAX_UPLOAD_MB ?> MB. A photo of the same item or model helps a lot.</span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">Submit report</button>
                <a class="btn btn-secondary" href="<?= e(url('/dashboard.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>

    <aside>
        <div class="card card-muted">
            <h3>Before you submit</h3>
            <p class="text-sm">Check <a href="<?= e(url('/found/browse.php')) ?>">found items</a> first — your item may already be in storage.</p>
            <h3 class="mt-2">What happens next</h3>
            <ol class="text-sm" style="padding-left:1.2rem;margin:0">
                <li>Your report is saved with status <?= status_badge('open') ?>.</li>
                <li>Staff compare new intake items against open reports.</li>
                <li>If a match is found you'll see it on your dashboard and can submit a claim.</li>
            </ol>
        </div>
    </aside>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
