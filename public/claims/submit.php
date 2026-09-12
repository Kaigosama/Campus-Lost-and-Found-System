<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();

$user = current_user();
$item = mock_found_item((int) ($_GET['item'] ?? 0));

if (!$item || $item['status'] !== 'stored') {
    http_response_code(404);
    $pageTitle = 'Item unavailable';
    include APP_PATH . '/views/layout/header.php';
    $emptyTitle = 'This item can\'t be claimed';
    $emptyText  = 'It may have already been returned to its owner.';
    $emptyActionUrl = url('/found/browse.php');
    $emptyActionLabel = 'Back to found items';
    include APP_PATH . '/views/partials/empty-state.php';
    include APP_PATH . '/views/layout/footer.php';
    exit;
}

$pageTitle = 'Claim: ' . $item['item_name'];
$myOpenReports = array_values(array_filter(
    mock_lost_reports(),
    fn ($r) => $r['user_id'] === $user['user_id'] && $r['status'] === 'open'
));

include APP_PATH . '/views/layout/header.php';
?>

<div class="breadcrumb">
    <a href="<?= e(url('/found/browse.php')) ?>">Found items</a>
    <span><a href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>"><?= e($item['item_name']) ?></a></span>
    <span>Claim</span>
</div>

<div class="page-header">
    <div>
        <h1>Claim this item</h1>
        <p>Prove it's yours by describing details that aren't visible in the listing.</p>
    </div>
</div>

<div class="grid grid-sidebar">
    <div class="card">
        <form method="post" action="<?= e(url('/claims/submit.php?item=' . $item['item_id'])) ?>" class="form" data-validate data-mock>
            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">

            <div class="form-group">
                <label for="proof_description">How do you know this is yours? <span class="req" aria-hidden="true">*</span></label>
                <textarea id="proof_description" name="proof_description" required minlength="30" maxlength="2000" data-min-words="8" autofocus
                          placeholder="Describe contents, scratches, engravings, serial numbers, stickers, names written inside — anything staff can check against the item."></textarea>
                <span class="form-hint">Be specific. Staff compare this with the details recorded at intake. Vague claims are rejected.</span>
            </div>

            <div class="form-group">
                <label for="lost_report_id">Link a lost report (optional)</label>
                <select id="lost_report_id" name="lost_report_id">
                    <option value="">— None —</option>
                    <?php foreach ($myOpenReports as $r): ?>
                        <option value="<?= $r['report_id'] ?>" <?= $r['category'] === $item['category'] ? 'selected' : '' ?>>
                            #<?= $r['report_id'] ?> — <?= e($r['item_name']) ?> (lost <?= e(format_date($r['date_lost'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">
                    Linking a report helps staff verify faster.
                    <?php if (!$myOpenReports): ?>You have no open reports — <a href="<?= e(url('/lost/report.php')) ?>">file one</a>.<?php endif; ?>
                </span>
            </div>

            <div class="form-group">
                <label class="check">
                    <input type="checkbox" name="confirm_truth" value="1" required>
                    I confirm this item belongs to me and the details above are true.
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">Submit claim</button>
                <a class="btn btn-secondary" href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>">Cancel</a>
            </div>
        </form>
    </div>

    <aside>
        <div class="card">
            <h3>You're claiming</h3>
            <?= photo_tag($item['photo_path'], $item['item_name'], 'photo-large') ?>
            <dl class="detail-list mt-2">
                <dt>Item</dt><dd class="fw-600"><?= e($item['item_name']) ?></dd>
                <dt>Category</dt><dd><?= e($item['category']) ?></dd>
                <dt>Found</dt><dd><?= e(format_date($item['date_found'])) ?> &middot; <?= e($item['location_found']) ?></dd>
            </dl>
        </div>
        <div class="alert alert-warning mt-2 mb-0 text-sm">
            False claims are logged against your account and may be reported to the Office of Student Affairs.
        </div>
    </aside>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
