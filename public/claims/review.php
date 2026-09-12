<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['staff', 'admin']);

$claim = mock_claim((int) ($_GET['id'] ?? 0));
if (!$claim) {
    http_response_code(404);
    $pageTitle = 'Claim not found';
    include APP_PATH . '/views/layout/header.php';
    $emptyTitle = 'Claim not found';
    $emptyActionUrl = url('/claims/queue.php');
    $emptyActionLabel = 'Back to queue';
    include APP_PATH . '/views/partials/empty-state.php';
    include APP_PATH . '/views/layout/footer.php';
    exit;
}

$item     = mock_found_item($claim['item_id']);
$claimant = mock_user($claim['user_id']);
$reviewer = mock_user($claim['reviewed_by']);
$report   = $claim['lost_report_id'] ? mock_lost_report($claim['lost_report_id']) : null;
$otherClaims = array_values(array_filter(
    mock_claims(),
    fn ($c) => $c['item_id'] === $claim['item_id'] && $c['claim_id'] !== $claim['claim_id']
));
$claimantHistory = array_values(array_filter(mock_claims(), fn ($c) => $c['user_id'] === $claim['user_id'] && $c['claim_id'] !== $claim['claim_id']));
$pageTitle = 'Review claim #' . $claim['claim_id'];

include APP_PATH . '/views/layout/header.php';
?>

<div class="breadcrumb"><a href="<?= e(url('/claims/queue.php')) ?>">Claims queue</a><span>Claim #<?= $claim['claim_id'] ?></span></div>

<div class="page-header">
    <div>
        <h1>Claim #<?= $claim['claim_id'] ?> <?= status_badge($claim['status']) ?></h1>
        <p>
            <strong><?= e(full_name($claimant)) ?></strong> is claiming
            <a href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>"><?= e($item['item_name']) ?></a>
            &middot; submitted <?= e(format_datetime($claim['created_at'])) ?>
        </p>
    </div>
    <a class="btn btn-secondary" href="<?= e(url('/claims/queue.php')) ?>">&larr; Back to queue</a>
</div>

<div class="compare mb-3">
    <div class="card">
        <h3><span class="badge badge-pending">Claimant says</span></h3>
        <div class="quote claimant"><?= e($claim['proof_description']) ?></div>
        <dl class="detail-list mt-2">
            <dt>Claimant</dt><dd><?= e(full_name($claimant)) ?><br><small><?= e($claimant['email']) ?></small></dd>
            <dt>Past claims</dt>
            <dd>
                <?php if ($claimantHistory): ?>
                    <?php foreach ($claimantHistory as $h): ?><?= status_badge($h['status']) ?> <?php endforeach; ?>
                <?php else: ?><span class="text-muted">None</span><?php endif; ?>
            </dd>
            <?php if ($report): ?>
                <dt>Linked report</dt>
                <dd>
                    <a href="<?= e(url('/lost/view.php?id=' . $report['report_id'])) ?>">#<?= $report['report_id'] ?> <?= e($report['item_name']) ?></a>
                    <small class="text-muted">lost <?= e(format_date($report['date_lost'])) ?> at <?= e($report['location_lost']) ?></small>
                    <div class="text-sm mt-1"><?= e($report['description']) ?></div>
                </dd>
            <?php endif; ?>
        </dl>
    </div>

    <div class="card card-staff">
        <h3><span class="badge badge-role-staff">Intake record</span></h3>
        <div class="quote staff"><?= e($item['private_details']) ?></div>
        <dl class="detail-list mt-2">
            <dt>Item</dt><dd><?= e($item['item_name']) ?> <small>(<?= e($item['category']) ?>)</small></dd>
            <dt>Public desc.</dt><dd class="text-sm"><?= e($item['description']) ?></dd>
            <dt>Found</dt><dd><?= e(format_date($item['date_found'])) ?> &middot; <?= e($item['location_found']) ?></dd>
            <dt>Storage</dt><dd class="fw-600"><?= e($item['storage_location']) ?></dd>
            <dt>Item status</dt><dd><?= status_badge($item['status']) ?></dd>
        </dl>
        <?php if ($otherClaims): ?>
            <div class="alert alert-warning text-sm mt-2 mb-0">
                <strong><?= count($otherClaims) ?> other claim<?= count($otherClaims) === 1 ? '' : 's' ?></strong> on this item:
                <?php foreach ($otherClaims as $oc): ?>
                    <a href="<?= e(url('/claims/review.php?id=' . $oc['claim_id'])) ?>">#<?= $oc['claim_id'] ?></a> <?= status_badge($oc['status']) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($claim['status'] === 'pending'): ?>
<div class="card">
    <h2>Decision</h2>
    <form method="post" action="<?= e(url('/claims/review.php?id=' . $claim['claim_id'])) ?>" class="form" data-validate data-mock>
        <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
        <div class="form-group">
            <label for="review_note">Note to claimant <span class="req" aria-hidden="true">*</span></label>
            <textarea id="review_note" name="review_note" required minlength="10" maxlength="1000"
                      placeholder="If approving: pickup instructions (e.g. bring a valid ID to Admin Bldg Rm 104). If rejecting: the reason."></textarea>
            <span class="form-hint">This message is shown to the claimant.</span>
        </div>
        <div class="form-actions">
            <button type="submit" name="decision" value="approve" class="btn btn-success" data-confirm="Approve this claim? The claimant will be told to pick the item up.">Approve claim</button>
            <button type="submit" name="decision" value="reject" class="btn btn-danger" data-confirm="Reject this claim?">Reject claim</button>
            <span class="spacer"></span>
            <a class="btn btn-secondary" href="<?= e(url('/claims/queue.php')) ?>">Decide later</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="card">
    <div class="card-header">
        <h2>Decision</h2>
        <?= status_badge($claim['status']) ?>
    </div>
    <dl class="detail-list">
        <dt>Reviewed by</dt><dd><?= e($reviewer ? full_name($reviewer) : '—') ?> &middot; <?= e(format_datetime($claim['reviewed_at'])) ?></dd>
        <dt>Note</dt><dd class="prose"><?= e($claim['review_note']) ?></dd>
    </dl>

    <?php if ($claim['status'] === 'approved' && $item['status'] === 'stored'): ?>
        <hr>
        <h3>Hand-over</h3>
        <p class="text-sm text-muted">When the claimant collects the item, mark it returned. This sets the item to <strong>Returned</strong>, closes the linked lost report, and rejects any other pending claims on it.</p>
        <form method="post" action="<?= e(url('/claims/review.php?id=' . $claim['claim_id'])) ?>" class="form" data-validate data-mock data-confirm="Confirm the item has been handed to the claimant?">
            <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
            <input type="hidden" name="decision" value="returned">
            <div class="form-group">
                <label class="check"><input type="checkbox" name="id_verified" value="1" required> I checked the claimant's ID against the claim.</label>
            </div>
            <button type="submit" class="btn btn-primary">Mark item as returned</button>
        </form>
    <?php elseif ($item['status'] === 'returned'): ?>
        <div class="alert alert-success mb-0 mt-2">Item returned on <?= e(format_datetime($item['returned_at'])) ?>.</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
