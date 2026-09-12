<?php
require_once __DIR__ . '/config/db_connect.php';

/**
 * Detail view of a single item.
 *   ?type=found&id=N   public item card + (user) inline ownership-claim form
 *                      + (staff) private intake record, every claim on the item with its decision form, hand-over
 *   ?type=lost&id=N    owner's or staff's view of a lost report, with close / match actions
 * Anchors: #claim-<id> jumps to a specific claim in the staff panel.
 */
$type = ($_GET['type'] ?? 'found') === 'lost' ? 'lost' : 'found';
$id   = (int) ($_GET['id'] ?? 0);
$user = current_user();

if ($type === 'lost') {
    require_login();
    $report = find_lost_report($id);
    // Only the owner or staff may view a report.
    if (!$report || ($report['user_id'] !== $user['user_id'] && !is_staff())) {
        abort(404, 'Report not found', 'This lost report does not exist or you do not have access to it.', url('/?tab=reports'), 'Back to my reports');
    }
    $isOwner      = $report['user_id'] === $user['user_id'];
    $owner        = find_user($report['user_id']);
    $linkedClaims = where(all_claims(), 'report_id', $report['report_id']);
    $pageTitle    = $report['item_name'];
} else {
    $item = find_found_item($id);
    // Non-staff may only see items that are still in storage.
    if (!$item || (!is_staff() && $item['status'] !== 'stored')) {
        abort(404, 'Item not found', 'This item is no longer listed. It may have been returned to its owner.', url('/browse.php'), 'Back to found items');
    }
    $itemClaims   = where(all_claims(), 'item_id', $item['item_id']);
    $pendingCount = count_where($itemClaims, 'status', 'pending');
    $myClaim      = $user ? (where($itemClaims, 'user_id', $user['user_id'])[0] ?? null) : null;
    $loggedBy     = find_user($item['user_id']);
    $pageTitle    = $item['item_name'];
    if ($user && !$myClaim && $item['status'] === 'stored') {
        $myOpenReports = array_values(array_filter(all_lost_reports(), fn ($r) => $r['user_id'] === $user['user_id'] && $r['status'] === 'open'));
    }
}

include APP_ROOT . '/includes/header.php';
?>

<?php if ($type === 'lost'): ?>
<!-- ====================================================== LOST REPORT -->
<div class="breadcrumb">
    <a href="<?= e($isOwner ? url('/?tab=reports') : url('/browse.php?type=lost')) ?>"><?= $isOwner ? 'My lost reports' : 'All lost reports' ?></a>
    <span><?= e($report['item_name']) ?></span>
</div>

<div class="page-header">
    <div>
        <h1><?= e($report['item_name']) ?> <?= status_badge($report['status']) ?></h1>
        <p>Report #<?= $report['report_id'] ?> &middot; filed <?= e(format_datetime($report['created_at'])) ?></p>
    </div>
    <?php if ($isOwner && $report['status'] === 'open'): ?>
        <div class="btn-row">
            <a class="btn btn-outline" href="<?= e(url('/report.php?type=lost&id=' . $report['report_id'])) ?>">Edit</a>
            <!-- Later: POSTs to api/update_status.php {type:'lost', id, status:'closed'} -->
            <form method="post" action="<?= e(item_url('lost', $report['report_id'])) ?>" data-api="update_status" data-type="lost" data-confirm="Close this report? Do this if you found the item or no longer need help." style="display:inline">
                <input type="hidden" name="id" value="<?= $report['report_id'] ?>">
                <input type="hidden" name="status" value="closed">
                <button type="submit" class="btn btn-secondary">Mark as found / close</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-sidebar">
    <div>
        <div class="card">
            <div class="grid grid-2">
                <div><?= photo_tag($report['image_url'], $report['item_name'], 'photo-large') ?></div>
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

        <?php if ($linkedClaims): ?>
        <div class="card mt-2">
            <h2>Linked claims</h2>
            <ul class="timeline">
                <?php foreach ($linkedClaims as $claim): $ci = find_found_item($claim['item_id']); ?>
                    <li>
                        <time><?= e(format_date($claim['date_claimed'])) ?></time>
                        <div>
                            Claim on <a href="<?= e(item_url('found', $ci['item_id'])) ?>" class="fw-600"><?= e($ci['item_name']) ?></a>
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
                <a class="btn btn-primary btn-sm" href="<?= e(url('/browse.php?category=' . rawurlencode($report['category']))) ?>">Browse <?= e($report['category']) ?></a>
            </div>
        <?php elseif ($report['status'] === 'matched'): ?>
            <div class="alert alert-warning mb-0">
                <strong>Possible match.</strong> Staff matched this report to a found item. Check your <a href="<?= e(url('/?tab=my_claims')) ?>">claims</a> for next steps.
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">This report is closed. Thanks for letting us know!</div>
        <?php endif; ?>

        <?php if (is_staff() && $report['status'] === 'open'): ?>
            <div class="card card-staff mt-2">
                <h3>Staff actions</h3>
                <p class="text-sm text-muted">Found a match in storage? Link it so the owner is notified.</p>
                <form method="post" action="<?= e(item_url('lost', $report['report_id'])) ?>" class="form" data-validate data-mock>
                    <input type="hidden" name="action" value="match">
                    <div class="form-group">
                        <label for="match_item">Matching found item</label>
                        <select id="match_item" name="item_id" required>
                            <option value="">Select an item…</option>
                            <?php foreach (where(all_found_items(), 'status', 'stored') as $fi): ?>
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

<?php else: ?>
<!-- ====================================================== FOUND ITEM -->
<div class="breadcrumb"><a href="<?= e(url('/browse.php')) ?>">Found items</a><span><?= e($item['item_name']) ?></span></div>

<div class="page-header">
    <div>
        <span class="item-card-category"><?= e($item['category']) ?></span>
        <h1><?= e($item['item_name']) ?> <?= status_badge($item['status']) ?></h1>
        <p>Item #<?= $item['item_id'] ?> &middot; turned in <?= e(format_date($item['date_found'])) ?></p>
    </div>
    <?php if (is_staff()): ?>
        <div class="btn-row">
            <a class="btn btn-outline" href="<?= e(url('/report.php?type=found&id=' . $item['item_id'])) ?>">Edit</a>
            <a class="btn btn-secondary" href="<?= e(url('/browse.php?manage=1')) ?>">Manage items</a>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-sidebar">
    <div>
        <div class="card">
            <div class="grid grid-2">
                <div><?= photo_tag($item['image_url'], $item['item_name'], 'photo-large') ?></div>
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
                <h2>Intake record</h2>
                <span class="badge badge-role-staff">Not visible to users</span>
            </div>
            <dl class="detail-list">
                <dt>Storage location</dt><dd class="fw-600"><?= e($item['storage_location']) ?></dd>
                <dt>Private details</dt><dd><div class="quote staff"><?= e($item['private_details']) ?></div></dd>
                <dt>Logged by</dt><dd><?= e(full_name($loggedBy)) ?> &middot; <?= e(format_datetime($item['created_at'])) ?></dd>
                <?php if ($item['returned_at']): ?><dt>Returned</dt><dd><?= e(format_datetime($item['returned_at'])) ?></dd><?php endif; ?>
            </dl>
        </div>

        <?php if ($itemClaims): ?>
        <h2 class="mt-3">Claims on this item (<?= count($itemClaims) ?>)</h2>
        <p class="text-sm text-muted">Compare each claimant's proof with the private details above. Pending claims are listed first.</p>
        <?php
        usort($itemClaims, fn ($a, $b) => [$a['status'] !== 'pending', $a['created_at']] <=> [$b['status'] !== 'pending', $b['created_at']]);
        foreach ($itemClaims as $claim):
            $claimant = find_user($claim['user_id']);
            $reviewer = find_user($claim['reviewed_by']);
            $linked   = $claim['report_id'] ? find_lost_report($claim['report_id']) : null;
            $history  = array_values(array_filter(all_claims(), fn ($c) => $c['user_id'] === $claim['user_id'] && $c['claim_id'] !== $claim['claim_id']));
        ?>
        <article class="card mt-2" id="claim-<?= $claim['claim_id'] ?>">
            <div class="card-header">
                <h3 class="mb-0">Claim #<?= $claim['claim_id'] ?> &middot; <?= e(full_name($claimant)) ?></h3>
                <?= status_badge($claim['status']) ?>
            </div>

            <div class="grid grid-2">
                <div>
                    <h4 class="text-sm text-muted"><span class="badge badge-pending">Claimant says</span></h4>
                    <div class="quote claimant"><?= e($claim['proof_description']) ?></div>
                </div>
                <dl class="detail-list">
                    <dt>Claimant</dt><dd><?= e(full_name($claimant)) ?><br><small><?= e($claimant['email']) ?></small></dd>
                    <dt>Submitted</dt><dd><?= e(format_datetime($claim['created_at'])) ?></dd>
                    <dt>Past claims</dt>
                    <dd>
                        <?php if ($history): ?>
                            <?php foreach ($history as $h): ?><?= status_badge($h['status']) ?> <?php endforeach; ?>
                        <?php else: ?><span class="text-muted">None</span><?php endif; ?>
                    </dd>
                    <?php if ($linked): ?>
                        <dt>Linked report</dt>
                        <dd>
                            <a href="<?= e(item_url('lost', $linked['report_id'])) ?>">#<?= $linked['report_id'] ?> <?= e($linked['item_name']) ?></a>
                            <small class="text-muted">lost <?= e(format_date($linked['date_lost'])) ?> at <?= e($linked['location_lost']) ?></small>
                            <div class="text-sm mt-1"><?= e($linked['description']) ?></div>
                        </dd>
                    <?php endif; ?>
                </dl>
            </div>

            <?php if ($claim['status'] === 'pending'): ?>
                <hr>
                <form method="post" action="<?= e(item_url('found', $item['item_id']) . '#claim-' . $claim['claim_id']) ?>" class="form" data-validate data-mock>
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                    <div class="form-group">
                        <label for="review_note_<?= $claim['claim_id'] ?>">Note to claimant <span class="req" aria-hidden="true">*</span></label>
                        <textarea id="review_note_<?= $claim['claim_id'] ?>" name="review_note" required minlength="10" maxlength="1000"
                                  placeholder="If approving: pickup instructions (e.g. bring a valid ID to Admin Bldg Rm 104). If rejecting: the reason."></textarea>
                        <span class="form-hint">This message is shown to the claimant.</span>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="decision" value="approve" class="btn btn-success" data-confirm="Approve this claim? The claimant will be told to pick the item up.">Approve claim</button>
                        <button type="submit" name="decision" value="reject" class="btn btn-danger" data-confirm="Reject this claim?">Reject claim</button>
                    </div>
                </form>
            <?php else: ?>
                <hr>
                <dl class="detail-list">
                    <dt>Reviewed by</dt><dd><?= e($reviewer ? full_name($reviewer) : '—') ?> &middot; <?= e(format_datetime($claim['reviewed_at'])) ?></dd>
                    <dt>Note</dt><dd class="prose"><?= e($claim['review_note']) ?></dd>
                </dl>

                <?php if ($claim['status'] === 'approved' && $item['status'] === 'stored'): ?>
                    <hr>
                    <h4>Hand-over</h4>
                    <p class="text-sm text-muted">When the claimant collects the item, mark it returned. This sets the item to <strong>Returned</strong>, closes the linked lost report, and rejects any other pending claims on it.</p>
                    <form method="post" action="<?= e(item_url('found', $item['item_id']) . '#claim-' . $claim['claim_id']) ?>" class="form" data-validate data-mock data-confirm="Confirm the item has been handed to the claimant?">
                        <input type="hidden" name="action" value="returned">
                        <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                        <div class="form-group">
                            <label class="check"><input type="checkbox" name="id_verified" value="1" required> I checked the claimant's ID against the claim.</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Mark item as returned</button>
                    </form>
                <?php elseif ($claim['status'] === 'approved' && $item['status'] === 'returned'): ?>
                    <div class="alert alert-success mb-0 mt-2">Item returned on <?= e(format_datetime($item['returned_at'])) ?>.</div>
                <?php endif; ?>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <aside>
        <?php if (!$user): ?>
            <div class="card">
                <h3>Is this yours?</h3>
                <p class="text-sm">Log in with your Mapua account to submit an ownership claim.</p>
                <a class="btn btn-primary btn-block" href="<?= e(url('/?next=' . rawurlencode(item_url('found', $item['item_id'])) . '#account')) ?>">Log in to claim</a>
            </div>
        <?php elseif ($myClaim): ?>
            <div class="card">
                <h3>Your claim</h3>
                <p><?= status_badge($myClaim['status']) ?> <small>submitted <?= e(format_date($myClaim['date_claimed'])) ?></small></p>
                <?php if ($myClaim['status'] === 'pending'): ?>
                    <p class="text-sm text-muted">Staff are reviewing your claim. You'll see the result here and on <a href="<?= e(url('/?tab=my_claims')) ?>">My Claims</a>.</p>
                <?php else: ?>
                    <div class="alert alert-<?= $myClaim['status'] === 'approved' ? 'success' : 'error' ?> text-sm mb-0"><?= e($myClaim['review_note']) ?></div>
                <?php endif; ?>
            </div>
        <?php elseif ($item['status'] === 'stored'): ?>
            <div class="card" id="claim">
                <h3>Is this yours?</h3>
                <p class="text-sm">Prove it by describing details that aren't visible in the listing. Staff compare this with the record made at intake — vague claims are rejected.</p>
                <form method="post" action="<?= e(item_url('found', $item['item_id']) . '#claim') ?>" class="form" data-validate data-mock>
                    <input type="hidden" name="action" value="claim">
                    <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">

                    <div class="form-group">
                        <label for="proof_description">How do you know this is yours? <span class="req" aria-hidden="true">*</span></label>
                        <textarea id="proof_description" name="proof_description" required minlength="30" maxlength="2000" data-min-words="8"
                                  placeholder="Contents, scratches, engravings, serial numbers, stickers, names written inside…"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="report_id">Link a lost report (optional)</label>
                        <select id="report_id" name="report_id">
                            <option value="">— None —</option>
                            <?php foreach ($myOpenReports as $r): ?>
                                <option value="<?= $r['report_id'] ?>" <?= $r['category'] === $item['category'] ? 'selected' : '' ?>>
                                    #<?= $r['report_id'] ?> — <?= e($r['item_name']) ?> (lost <?= e(format_date($r['date_lost'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-hint">
                            Linking a report helps staff verify faster.
                            <?php if (!$myOpenReports): ?>You have no open reports — <a href="<?= e(url('/report.php')) ?>">file one</a>.<?php endif; ?>
                        </span>
                    </div>

                    <div class="form-group">
                        <label class="check">
                            <input type="checkbox" name="confirm_truth" value="1" required>
                            I confirm this item belongs to me and the details above are true.
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Submit claim</button>
                </form>
                <?php if ($pendingCount): ?>
                    <p class="text-sm text-muted mt-1 mb-0"><?= $pendingCount ?> other claim<?= $pendingCount === 1 ? ' is' : 's are' ?> pending review.</p>
                <?php endif; ?>
            </div>
            <div class="alert alert-warning mt-2 mb-0 text-sm">
                False claims are logged against your account and may be reported to the Office of Student Affairs.
            </div>
        <?php endif; ?>

        <?php if (!$myClaim): ?>
        <div class="card card-muted mt-2">
            <h3>Tips for a successful claim</h3>
            <ul class="text-sm" style="padding-left:1.1rem;margin:0">
                <li>Mention contents, scratches, stickers, engravings.</li>
                <li>Include brand and model if you know them.</li>
                <li>Link your lost report if you filed one.</li>
            </ul>
        </div>
        <?php endif; ?>
    </aside>
</div>
<?php endif; ?>

<?php include APP_ROOT . '/includes/footer.php'; ?>
