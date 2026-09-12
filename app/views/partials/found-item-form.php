<?php
/**
 * Shared form for found/intake.php (create) and found/edit.php (update).
 * Expects: $formAction (string), $item (array|null for create), $submitLabel (string).
 */
$item  = $item ?? null;
$today = date('Y-m-d');
?>
<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="form" data-validate data-mock>
    <?php if ($item): ?><input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>"><?php endif; ?>

    <fieldset>
        <legend>Public details</legend>
        <p class="text-sm text-muted">Shown to everyone browsing found items. Keep it general enough that it doesn't give away verification details.</p>

        <div class="form-group">
            <label for="item_name">Item name <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="item_name" name="item_name" required maxlength="150"
                   value="<?= e($item['item_name'] ?? '') ?>" placeholder="e.g. Blue JanSport backpack" <?= $item ? '' : 'autofocus' ?>>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category">Category <span class="req" aria-hidden="true">*</span></label>
                <select id="category" name="category" required>
                    <option value="">Select a category…</option>
                    <?= options(CATEGORIES, $item['category'] ?? null, false) ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date_found">Date found <span class="req" aria-hidden="true">*</span></label>
                <input type="date" id="date_found" name="date_found" required max="<?= $today ?>" value="<?= e($item['date_found'] ?? $today) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="location_found">Where was it found? <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="location_found" name="location_found" required maxlength="150" list="campus-locations"
                   value="<?= e($item['location_found'] ?? '') ?>" placeholder="e.g. Gymnasium bleachers">
            <datalist id="campus-locations"><?php foreach (CAMPUS_LOCATIONS as $loc): ?><option value="<?= e($loc) ?>"><?php endforeach; ?></datalist>
        </div>

        <div class="form-group">
            <label for="description">Public description <span class="req" aria-hidden="true">*</span></label>
            <textarea id="description" name="description" required minlength="15" maxlength="2000"
                      placeholder="Colour, size, brand, general condition."><?= e($item['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="photo">Photo</label>
            <div class="photo-upload">
                <div class="photo-preview" id="photoPreview">
                    <?= !empty($item['photo_path']) ? photo_tag($item['photo_path'], $item['item_name']) : 'No photo selected' ?>
                </div>
                <div>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="#photoPreview">
                    <span class="form-hint">JPG, PNG or WEBP, up to <?= MAX_UPLOAD_MB ?> MB. Avoid photographing anything that reveals private details (ID numbers, contents).</span>
                </div>
            </div>
        </div>
    </fieldset>

    <fieldset class="staff-only">
        <legend>Staff-only details</legend>
        <p class="text-sm text-muted">Never shown to users. Used to verify ownership claims and to locate the item physically.</p>

        <div class="form-group">
            <label for="storage_location">Storage location <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="storage_location" name="storage_location" required maxlength="150"
                   value="<?= e($item['storage_location'] ?? '') ?>" placeholder="e.g. Cabinet B, Shelf 2">
        </div>

        <div class="form-group">
            <label for="private_details">Private / distinguishing details <span class="req" aria-hidden="true">*</span></label>
            <textarea id="private_details" name="private_details" required minlength="15" maxlength="2000"
                      placeholder="Contents, serial numbers, engravings, scratches, names inside — things a real owner would know."><?= e($item['private_details'] ?? '') ?></textarea>
        </div>

        <?php if ($item): ?>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status"><?= options(FOUND_STATUSES, $item['status']) ?></select>
            <span class="form-hint">Use "Returned" from the claim review page so the linked report and other claims are updated automatically.</span>
        </div>
        <?php endif; ?>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg"><?= e($submitLabel) ?></button>
        <a class="btn btn-secondary" href="<?= e(url('/found/manage.php')) ?>">Cancel</a>
    </div>
</form>
