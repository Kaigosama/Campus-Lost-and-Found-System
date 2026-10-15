<?php
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * ?type=found&id=N   item details + claim form (users) / intake record, claims with their review history, ID checks,
 *                    hand-over, disposal and the item's activity (staff act; admins see the same, read-only)
 * ?type=lost&id=N    a lost report, for its owner, staff or admins. #claim-<id> anchors a claim in the staff panel.
 * &from=queue        opened from the Claims section: Back returns there instead of the record's own list.
 * Inside the dashboard (staff and admins; lost reports for everyone) the page shows the section bar with its parent
 * section marked, a breadcrumb, and a Back link to that list with the filters it last had.
 */
$type = ($_GET['type'] ?? 'found') === 'lost' ? 'lost' : 'found';
$id   = (int) ($_GET['id'] ?? 0);
$user = current_user();

if ($type === 'lost') {
    require_login();
    $report = find_lost_report($id);
    // Removed (soft-deleted) reports stay visible to staff and admins only, for audits.
    if (!$report || (!can_monitor() && ($report['reported_by'] !== $user['user_id'] || $report['deleted_at'] !== null))) {
        abort(404, 'Report not found', 'This lost report does not exist or you do not have access to it.', url('/?tab=reports'), 'Back to my reports');
    }
    $isOwner      = $report['reported_by'] === $user['user_id'];
    $owner        = find_user($report['reported_by']);
    $linkedClaims = where(all_claims(), 'report_id', $report['report_id']);
    $matchedItem  = $report['matched_item_id'] ? find_found_item((int) $report['matched_item_id']) : null;
    $moderator    = find_user($report['moderated_by']);
    $ownerViolations = can_monitor() ? account_violations($report['reported_by']) : [];
    $pageTitle    = $report['item_name'];
} else {
    $item = find_found_item($id);
    $itemClaims = $item ? where(all_claims(), 'item_id', $item['item_id']) : [];
    $myClaim    = $user ? (where($itemClaims, 'claimant_id', $user['user_id'])[0] ?? null) : null;
    // The public sees approved items in storage; a student also sees their own post while it is reviewed,
    // and a claimant keeps seeing the item after it leaves storage (e.g. returned to them).
    $isPoster = $item && $user && $item['added_by'] === $user['user_id'] && !can_monitor();
    if (!$item || (!can_monitor() && !$isPoster && !$myClaim && !is_public_item($item))) {
        abort(404, 'Item not found', 'This item is no longer listed. It may have been returned to its owner.', can_monitor() ? dashboard_url('items') : url('/browse.php'), 'Back to found items');
    }
    $pendingCount = count(array_filter($itemClaims, 'awaiting_review'));
    $loggedBy     = find_user($item['added_by']);
    $pageTitle    = $item['item_name'];
    // Only students and faculty file claims, never on their own post. Staff review them; admins do neither.
    $canClaim     = is_public_item($item) && !$isPoster && (!$user || has_role('user'));
    if ($user && $canClaim && !$myClaim) {
        $myOpenReports = array_values(array_filter(all_lost_reports(), fn ($r) => $r['reported_by'] === $user['user_id'] && $r['status'] === 'open'));
    }
}

// Dashboard parent section, for the section bar, breadcrumb and Back link: the list the record belongs to, or the
// section the link came from (?from=queue from Claims). Students and faculty see found items on the public pages.
$sections = $user ? dashboard_tabs() : [];
$from     = (string) ($_GET['from'] ?? '');
$parent   = match (true) {
    $type === 'found' && !can_monitor()               => null,
    isset($sections[$from]) && $from !== 'overview'    => $from,
    $type === 'lost'                                   => 'reports',
    in_array($item['status'], ['returned', 'disposed'], true) => $item['status'],
    is_staff() && $item['moderation_status'] === 'pending'    => 'moderation',
    default                                            => 'items',
};

include APP_ROOT . '/templates/layout/header.php';
?>

<?php if ($parent): $parentUrl = dashboard_url($parent, true); ?>
<?= dashboard_nav($parent, [[$sections[$parent][0], $parentUrl], [($type === 'lost' ? 'Report #' : 'Item #') . $id, null]]) ?>
<?= back_button($parentUrl, $sections[$parent][0]) ?>
<?php endif; ?>

<?php if ($type === 'lost'): ?>
<!-- ====================================================== LOST REPORT -->
<div class="page-header">
    <div>
        <h1><?= e($report['item_name']) ?> <?= status_badge($report['status'], 'lost-' . $report['report_id']) ?></h1>
        <p>Report #<?= $report['report_id'] ?> &middot; filed <?= e(format_datetime($report['created_at'])) ?></p>
    </div>
    <?php if ($isOwner && $report['status'] === 'open'): ?>
        <div class="btn-row" data-remove>
            <a class="btn btn-outline" href="<?= e(url('/report.php?type=lost&id=' . $report['report_id'])) ?>">Edit</a>
            <form method="post" action="<?= e(item_url('lost', $report['report_id'])) ?>" data-api="update_status" data-type="lost" data-done="remove" data-confirm="Close this report? Do this if you found the item or no longer need help." class="inline-form">
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
                    <dt>Status</dt><dd><?= status_badge($report['status'], 'lost-' . $report['report_id']) ?></dd>
                    <?php if ($matchedItem): ?>
                        <dt>Matched item</dt><dd><a href="<?= e(item_url('found', $matchedItem['item_id'])) ?>">#<?= $matchedItem['item_id'] ?> <?= e($matchedItem['item_name']) ?></a></dd>
                    <?php endif; ?>
                    <?php if (can_monitor()): ?>
                        <dt>Reported by</dt><dd><?= e(full_name($owner)) ?><br><small><?= e($owner['email']) ?></small></dd>
                    <?php endif; ?>
                    <dt>Last updated</dt><dd><?= e(format_datetime($report['updated_at'])) ?></dd>
                </dl>
            </div>
            <hr>
            <h3>Description</h3>
            <p class="mb-0 pre-line"><?= e($report['description']) ?></p>
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
                            <?= claim_badges($claim) ?>
                            <?php if ($latest = latest_claim_review($claim)): ?><div class="text-sm"><?= decision_list(claim_decision_lines($latest)) ?></div><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <aside>
        <?php if ($report['status'] === 'open'): ?>
            <?php if ($isOwner): /* staff match it from the Staff actions card below */ ?>
            <div class="card card-muted">
                <h3>Think you've spotted it?</h3>
                <p class="text-sm">Browse the found items list and submit a claim if you see your item.</p>
                <?php $browseCategory = $report['category_name']; ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/browse.php?category=' . rawurlencode($browseCategory))) ?>">Browse <?= e($browseCategory) ?></a>
            </div>
            <?php endif; ?>
        <?php elseif ($report['status'] === 'matched'): ?>
            <div class="alert alert-warning mb-0">
                <strong>Possible match.</strong>
                <?php if ($matchedItem): ?>
                    Staff matched this report to <a href="<?= e(item_url('found', $matchedItem['item_id'])) ?>"><?= e($matchedItem['item_name']) ?></a>.
                    Open it and submit a claim if it's yours, or check your <a href="<?= e(url('/?tab=my_claims')) ?>">claims</a>.
                <?php else: ?>
                    Staff matched this report to a found item. Check your <a href="<?= e(url('/?tab=my_claims')) ?>">claims</a> for next steps.
                <?php endif; ?>
            </div>
        <?php elseif ($report['status'] === 'closed'): ?>
            <div class="alert alert-info mb-0">This report is closed. Thanks for letting us know!</div>
        <?php else: ?>
            <div class="alert alert-error mb-0" role="status">
                <strong><?= e(status_label($report['status'])) ?>.</strong>
                <?= match ($report['status']) {
                    'false_report' => 'Lost &amp; Found staff determined that this report contains false or misleading information.',
                    'spam'         => 'Lost &amp; Found staff marked this report as spam.',
                    default        => 'Lost &amp; Found staff did not accept this report.',
                } ?>
                <?= decision_list(report_decision_lines($report)) ?>
                <?php if (can_monitor()): ?><small>By <?= e($moderator ? full_name($moderator) : '—') ?> &middot; <?= e(format_datetime($report['moderated_at'])) ?></small><?php endif; ?>
            </div>
            <?php if ($report['deleted_at'] !== null): $deleter = find_user($report['deleted_by']); ?>
                <div class="alert alert-info mt-2 mb-0"><strong>Removed</strong> from every list by <?= e($deleter ? full_name($deleter) : '—') ?>
                    on <?= e(format_datetime($report['deleted_at'])) ?>. Reason: <?= e($report['deletion_reason']) ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (is_staff() && $report['status'] === 'open'): ?>
            <div class="card card-staff mt-2">
                <h3>Staff actions</h3>
                <p class="text-sm text-muted">Found a match in storage? Link it so the owner is notified.</p>
                <form method="post" action="<?= e(item_url('lost', $report['report_id'])) ?>" class="form" data-validate data-api="update_status" data-type="lost" data-done="replace">
                    <input type="hidden" name="id" value="<?= $report['report_id'] ?>">
                    <input type="hidden" name="status" value="matched">
                    <div class="form-group">
                        <label for="match_item">Matching found item</label>
                        <select id="match_item" name="item_id" required>
                            <option value="">Select an item…</option>
                            <?php foreach (public_found_items() as $fi): ?>
                                <option value="<?= $fi['item_id'] ?>">#<?= $fi['item_id'] ?> — <?= e($fi['item_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-accent btn-sm">Mark as matched</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (is_staff() && $report['deleted_at'] === null && in_array($report['status'], ['open', 'matched'], true)):
            $nextViolation = count($ownerViolations) + 1;
            $deactivates   = $nextViolation >= FALSE_REPORTS_TO_DEACTIVATE; ?>
            <div class="card card-staff mt-2" data-replace>
                <h3>Moderate this report</h3>
                <form method="post" action="<?= e(item_url('lost', $report['report_id'])) ?>" class="form" data-validate data-api="moderate_report" data-done="replace" data-submit-guard>
                    <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
                    <fieldset class="form-group decision-choices">
                        <legend>1. Choose an outcome <span class="req" aria-hidden="true">*</span></legend>
                        <label><input type="radio" name="action" value="rejected" required> <span>Reject</span></label>
                        <label><input type="radio" name="action" value="false_report"> <span>False report</span></label>
                        <label><input type="radio" name="action" value="spam"> <span>Spam</span></label>
                    </fieldset>

                    <fieldset class="decision-form" data-show-when="action=rejected" hidden disabled>
                        <legend>2. Rejection details</legend>
                        <p class="text-sm text-muted">Incomplete, already found, an accidental duplicate or otherwise not usable. No penalty for the reporter.</p>
                        <div class="form-group">
                            <label for="rep_reason_rejected">Rejection reason <span class="req" aria-hidden="true">*</span></label>
                            <select id="rep_reason_rejected" name="reason_code" required><option value="">Select a reason…</option><?= options(REPORT_REJECTION_REASONS) ?></select>
                        </div>
                        <div class="form-group">
                            <label for="rep_details_rejected">Rejection details <span class="req" aria-hidden="true">*</span></label>
                            <textarea id="rep_details_rejected" name="details" required minlength="15" maxlength="1000" data-min-words="3"
                                      placeholder="Shown to the reporter: what is missing or wrong, e.g. the date lost is after the date it was reported."></textarea>
                        </div>
                        <fieldset class="form-group">
                            <legend>Can the reporter file a corrected report? <span class="req" aria-hidden="true">*</span></legend>
                            <label class="check"><input type="radio" name="resubmission_allowed" value="yes" required> Yes</label>
                            <label class="check"><input type="radio" name="resubmission_allowed" value="no"> No</label>
                        </fieldset>
                        <div class="form-actions"><button type="submit" class="btn btn-secondary" data-confirm="Reject this report? It doesn't count as a false report.">Confirm rejection</button><span class="form-hint" data-guard-hint hidden>Complete the required fields above to continue.</span></div>
                    </fieldset>

                    <fieldset class="decision-form" data-show-when="action=false_report" hidden disabled>
                        <legend>2. False report: confirmed intentional falsehood</legend>
                        <p class="text-sm text-muted">Only for a report shown to be deliberately false, misleading or abusive. An honest mistake is a rejection.</p>
                        <div class="form-group">
                            <label for="rep_reason_false">False-report reason <span class="req" aria-hidden="true">*</span></label>
                            <select id="rep_reason_false" name="reason_code" required><option value="">Select a reason…</option><?= options(FALSE_REPORT_REASONS) ?></select>
                        </div>
                        <div class="form-group">
                            <label for="rep_details_false">Evidence or basis <span class="req" aria-hidden="true">*</span></label>
                            <textarea id="rep_details_false" name="details" required minlength="30" maxlength="1000" data-min-words="5"
                                      placeholder="What information or evidence confirms the report was intentionally false? Shown to the reporter."></textarea>
                        </div>
                        <div class="alert alert-<?= $deactivates ? 'error' : 'warning' ?> text-sm">
                            <strong>This counts as a false-report violation.</strong>
                            Confirmed violations now: <?= $nextViolation - 1 ?>. After this decision: <?= $nextViolation ?> of <?= FALSE_REPORTS_TO_DEACTIVATE ?>.<br>
                            Result: <?= $deactivates
                                ? "<strong>the reporter's account is deactivated and every active session ends.</strong> Only an administrator can reactivate it."
                                : 'the reporter receives a warning. Violation ' . FALSE_REPORTS_TO_DEACTIVATE . ' deactivates the account.' ?>
                        </div>
                        <div class="form-group">
                            <label class="check"><input type="checkbox" name="confirm_false" value="1" required> I confirm this report has been reviewed and is intentionally false.</label>
                            <?php if ($deactivates): ?>
                                <label class="check"><input type="checkbox" name="confirm_deactivation" value="1" required> I understand this decision deactivates the reporter's account.</label>
                            <?php endif; ?>
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn-danger" data-confirm="<?= e($deactivates
                            ? "Confirm this false report? It is the reporter's violation $nextViolation: their account will be deactivated and logged out."
                            : 'Confirm this false report? The reporter will receive a warning.') ?>">Confirm false report</button><span class="form-hint" data-guard-hint hidden>Complete the required fields above to continue.</span></div>
                    </fieldset>

                    <fieldset class="decision-form" data-show-when="action=spam" hidden disabled>
                        <legend>2. Spam details</legend>
                        <p class="text-sm text-muted">Hides the report from every list. Spam never counts as a false-report violation; to penalise the reporter, classify it as a false report instead.</p>
                        <div class="form-group">
                            <label for="rep_reason_spam">Spam type <span class="req" aria-hidden="true">*</span></label>
                            <select id="rep_reason_spam" name="reason_code" required><option value="">Select a type…</option><?= options(SPAM_TYPES) ?></select>
                        </div>
                        <div class="form-group">
                            <label for="rep_details_spam">Details <span class="text-muted text-sm">(optional)</span></label>
                            <textarea id="rep_details_spam" name="details" maxlength="1000"></textarea>
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn-danger" data-confirm="Mark this report as spam?">Confirm spam</button><span class="form-hint" data-guard-hint hidden>Complete the required fields above to continue.</span></div>
                    </fieldset>
                </form>
            </div>
        <?php elseif (is_staff() && $report['deleted_at'] === null && in_array($report['status'], ['false_report', 'spam'], true)): ?>
            <div class="card card-staff mt-2" data-replace>
                <h3>Remove report</h3>
                <p class="text-sm text-muted">Takes this report out of every list, the reporter's included. It stays on record for audits, with your name and reason.</p>
                <form method="post" action="<?= e(item_url('lost', $report['report_id'])) ?>" class="form" data-validate data-api="moderate_report" data-done="replace">
                    <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <div class="form-group">
                        <label for="del_reason">Reason <span class="req" aria-hidden="true">*</span></label>
                        <textarea id="del_reason" name="details" required minlength="10" maxlength="1000" data-min-words="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove this report from every list?">Remove report</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (can_monitor()): ?>
            <div class="card card-muted mt-2">
                <h3>Reporter's false-report history</h3>
                <?php if ($ownerViolations): ?>
                    <ul class="timeline">
                        <?php foreach ($ownerViolations as $i => $v): ?>
                            <li>
                                <time><?= e(format_date($v['created_at'])) ?></time>
                                <div>
                                    Violation #<?= $i + 1 ?>: report #<?= (int) $v['report_id'] ?> <?= e($v['item_name']) ?>
                                    <span class="badge badge-<?= $v['action_taken'] === 'warning' ? 'pending' : 'rejected' ?>"><?= $v['action_taken'] === 'warning' ? 'Warning' : 'Deactivated' ?></span>
                                    <div class="text-sm text-muted"><?= e(trim(report_reason_label('false_report', $v['reason_code']) . ': ' . $v['reason'], ': ')) ?> &middot; issued by <?= e(trim($v['first_name'] . ' ' . $v['last_name']) ?: '—') ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-sm mb-0">No confirmed false reports.</p>
                <?php endif; ?>
                <?php if (!$owner['is_active']): ?><p class="text-sm mb-0 mt-1"><span class="badge badge-inactive">Account deactivated</span></p><?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>


<?php else: ?>
<!-- ====================================================== FOUND ITEM -->
<?= match (true) {
    can_monitor() => '',   // the dashboard Back link above
    $isPoster     => back_button(url('/?tab=posts'), 'My found posts'),
    $myClaim !== null && !is_public_item($item) => back_button(url('/?tab=my_claims'), 'My claims'),
    default       => back_button(url('/browse.php'), 'Found items'),
} ?>

<div class="page-header">
    <div>
        <span class="item-card-category"><?= e($item['category']) ?></span>
        <h1><?= e($item['item_name']) ?> <?= status_badge($item['status'], 'found-' . $item['item_id']) ?></h1>
        <p>Item #<?= $item['item_id'] ?> &middot; turned in <?= e(format_date($item['date_found'])) ?></p>
    </div>
    <?php if ($item['moderation_status'] !== 'approved' && ($isPoster || can_monitor())): ?>
        <div class="alert alert-<?= $item['moderation_status'] === 'pending' ? 'warning' : 'error' ?> mb-0" role="status">
            <?php if ($item['moderation_status'] === 'pending'): ?>
                <strong>Pending review.</strong> This post is not public yet<?= $isPoster ? '. Bring the item to the Lost &amp; Found office so staff can approve it.' : '.' ?>
            <?php else: ?>
                <strong>Not approved.</strong> This post is not public.
                <?= decision_list(post_decision_lines($item, false)) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (is_staff()): ?>
        <div class="btn-row">
            <?php if (!is_final_item($item)): /* returned and disposed records are final: no Edit */ ?>
                <a class="btn btn-outline" href="<?= e(url('/report.php?type=found&id=' . $item['item_id'])) ?>">Edit</a>
            <?php endif; ?>
            <?php if (can_delete_found_item($item)): ?>
                <form method="post" action="<?= e(item_url('found', $item['item_id'])) ?>" class="inline-form" data-api="delete_item" data-redirect="<?= e(dashboard_url('items', true)) ?>"
                      data-confirm="Delete this item? Use this only for an item logged by mistake. Any claims on it are deleted too. This can't be undone.">
                    <input type="hidden" name="id" value="<?= $item['item_id'] ?>">
                    <button type="submit" class="btn btn-danger">Delete item</button>
                </form>
            <?php endif; ?>
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
                    <dt>Status</dt><dd><?= status_badge($item['status'], 'found-' . $item['item_id']) ?></dd>
                    <?php if ($item['status'] === 'returned'): ?>
                        <dt>Returned</dt><dd><?= e(format_datetime($item['returned_at'])) ?></dd>
                    <?php elseif ($item['status'] === 'disposed'): ?>
                        <dt>Disposed</dt><dd><?= $item['disposed_at'] ? e(format_datetime($item['disposed_at'])) : '<span class="text-muted">Date not recorded</span>' ?></dd>
                    <?php else: ?>
                    <dt>Pickup</dt>
                    <dd>
                        Lost &amp; Found desk<br><small>Admin Office, Mapúa Makati · Mon–Fri 8 AM–5 PM</small>
                        <br><small data-next-holiday>Checking holiday schedule…</small>
                    </dd>
                    <?php endif; ?>
                </dl>
            </div>
            <hr>
            <h3>Description</h3>
            <p class="mb-0 pre-line"><?= e($item['description']) ?></p>
        </div>

        <?php if (can_monitor()): ?>
        <div class="card card-staff mt-2">
            <div class="card-header">
                <h2>Intake record</h2>
                <span class="badge badge-role-staff">Not visible to users</span>
            </div>
            <dl class="detail-list">
                <dt>Storage location</dt><dd class="fw-600"><?= e($item['storage_location']) ?></dd>
                <dt>Private details</dt><dd><div class="quote staff"><?= e($item['private_details']) ?></div></dd>
                <dt>Logged by</dt><dd><?= e(full_name($loggedBy)) ?> &middot; <?= e(format_datetime($item['created_at'])) ?></dd>
                <?php if ($item['moderated_at'] && $item['moderation_status'] !== 'pending'): $postReviewer = find_user($item['moderated_by']); ?>
                    <dt>Post review</dt>
                    <dd><?= status_badge($item['moderation_status']) ?> by <?= e($postReviewer ? full_name($postReviewer) : '—') ?> &middot; <?= e(format_datetime($item['moderated_at'])) ?>
                        <?= decision_list(post_decision_lines($item, true)) ?></dd>
                <?php endif; ?>
                <?php if ($item['status'] === 'returned'): $returnedBy = find_user($item['returned_by']); ?>
                    <dt>Handed over</dt>
                    <dd><?= $returnedBy ? 'by ' . e(full_name($returnedBy)) : '<span class="text-muted">Staff member not recorded</span>' ?>
                        <?= $item['return_claim_id'] ? ' &middot; <a href="#claim-' . (int) $item['return_claim_id'] . '">claim #' . (int) $item['return_claim_id'] . '</a>' : '' ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($item['status'] === 'disposed'): $disposedBy = find_user($item['disposed_by']); ?>
        <div class="card card-staff mt-2">
            <div class="card-header">
                <h2>Disposal details</h2>
                <?= status_badge('disposed') ?>
            </div>
            <dl class="detail-list">
                <dt>Disposed on</dt><dd><?= $item['disposed_at'] ? e(format_datetime($item['disposed_at'])) : '<span class="text-muted">Not recorded</span>' ?></dd>
                <dt>Disposed by</dt><dd><?= $disposedBy ? e(full_name($disposedBy)) : '<span class="text-muted">Not recorded (disposed before disposal records)</span>' ?></dd>
                <dt>Reason</dt><dd class="pre-line"><?= $item['disposal_reason'] ? e($item['disposal_reason']) : '<span class="text-muted">Not recorded</span>' ?></dd>
                <dt>Notes</dt><dd class="pre-line"><?= $item['disposal_notes'] ? e($item['disposal_notes']) : '<span class="text-muted">None</span>' ?></dd>
            </dl>
        </div>
        <?php endif; ?>

        <?php if (is_staff() && $item['moderation_status'] === 'pending'): ?>
        <div class="card card-staff mt-2" data-replace>
            <h2>Review this post</h2>
            <p class="text-sm text-muted">Posted by a student or faculty member. Approve it once the item is at the office; it then appears in the public list.</p>
            <form method="post" action="<?= e(item_url('found', $item['item_id'])) ?>" class="form" data-validate data-api="moderate" data-done="reload" data-submit-guard>
                <input type="hidden" name="id" value="<?= $item['item_id'] ?>">
                <fieldset class="form-group decision-choices">
                    <legend>1. Choose a decision <span class="req" aria-hidden="true">*</span></legend>
                    <label><input type="radio" name="moderation" value="approved" required> <span>Approve post</span></label>
                    <label><input type="radio" name="moderation" value="rejected"> <span>Reject post</span></label>
                </fieldset>

                <fieldset class="decision-form" data-show-when="moderation=approved" hidden disabled>
                    <legend>2. Intake details</legend>
                    <fieldset class="form-group">
                        <legend>Has the office received the physical item? <span class="req" aria-hidden="true">*</span></legend>
                        <label class="check"><input type="radio" name="received_at_office" value="yes" required data-required-value="yes"
                                                      data-required-message="Approve a post only once the item is at the office."> Yes, it is at the office</label>
                        <label class="check"><input type="radio" name="received_at_office" value="no"> No, not yet</label>
                    </fieldset>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mod_storage">Storage location <span class="req" aria-hidden="true">*</span></label>
                            <select id="mod_storage" name="storage_location" required>
                                <option value="">Select a storage location…</option>
                                <?= options(STORAGE_LOCATIONS, $item['storage_location'], false) ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="mod_condition">Item condition <span class="req" aria-hidden="true">*</span></label>
                            <select id="mod_condition" name="item_condition" required><option value="">Select…</option><?= options(ITEM_CONDITIONS) ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="mod_internal">Internal notes <span class="text-muted text-sm">(optional)</span></label>
                        <textarea id="mod_internal" name="internal_note" maxlength="1000" placeholder="Staff only, e.g. received from the guard at the main gate."></textarea>
                        <span class="form-hint">Only staff and administrators see this. The poster is told: "Your found-item post has been approved and is now visible in the public listing."</span>
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-success" data-confirm="Approve and publish this post?">Confirm approval</button><span class="form-hint" data-guard-hint hidden>Complete the required fields above to continue.</span></div>
                </fieldset>

                <fieldset class="decision-form" data-show-when="moderation=rejected" hidden disabled>
                    <legend>2. Rejection details</legend>
                    <div class="form-group">
                        <label for="mod_reason">Rejection reason <span class="req" aria-hidden="true">*</span></label>
                        <select id="mod_reason" name="reason_code" required><option value="">Select a reason…</option><?= options(POST_REJECTION_REASONS) ?></select>
                    </div>
                    <div class="form-group">
                        <label for="mod_details">Rejection details <span class="req" aria-hidden="true">*</span></label>
                        <textarea id="mod_details" name="details" required minlength="15" maxlength="1000" data-min-words="3"
                                  placeholder="Shown to the poster: what needs to be corrected before the item can be posted again."></textarea>
                    </div>
                    <fieldset class="form-group">
                        <legend>Can the poster post the item again? <span class="req" aria-hidden="true">*</span></legend>
                        <label class="check"><input type="radio" name="resubmission_allowed" value="yes" required> Yes</label>
                        <label class="check"><input type="radio" name="resubmission_allowed" value="no"> No</label>
                    </fieldset>
                    <div class="form-group" data-show-when="resubmission_allowed=yes" hidden>
                        <label for="mod_corrections">Required corrections <span class="req" aria-hidden="true">*</span></label>
                        <textarea id="mod_corrections" name="required_corrections" required minlength="10" maxlength="1000" data-min-words="2" disabled
                                  placeholder="e.g. Bring the item to the office first, then post it again with a photo."></textarea>
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-danger" data-confirm="Reject this post?">Confirm rejection</button><span class="form-hint" data-guard-hint hidden>Complete the required fields above to continue.</span></div>
                </fieldset>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($itemClaims): ?>
        <?php if (is_final_item($item)): ?>
        <h2 class="mt-3" id="claims">Claim history (<?= count($itemClaims) ?>)</h2>
        <p class="text-sm text-muted">Kept for the record. This item was <?= e(strtolower(status_label($item['status']))) ?>, so these claims are closed and it can't be claimed again.</p>
        <?php else: ?>
        <h2 class="mt-3" id="claims">Claims on this item (<?= count($itemClaims) ?>)</h2>
        <p class="text-sm text-muted">Each claim is its own record. Compare each claimant's proof with the private details above. Claims awaiting review are listed first.
            A rejected claim stays <strong>Pending</strong>; every decision is kept in its review history.</p>
        <?php endif; ?>
        <?php
        $order = fn (array $c) => match (true) { awaiting_review($c) => 0, in_array($c['status'], ['approved', 'verified'], true) => 1, $c['status'] === 'pending' => 2, default => 3 };
        usort($itemClaims, fn ($a, $b) => [$order($a), $a['created_at']] <=> [$order($b), $b['created_at']]);
        foreach ($itemClaims as $claim):
            $claimant = find_user($claim['claimant_id']);
            $linked   = $claim['report_id'] ? find_lost_report($claim['report_id']) : null;
            $history  = array_values(array_filter(all_claims(), fn ($c) => $c['claimant_id'] === $claim['claimant_id'] && $c['claim_id'] !== $claim['claim_id']));
            $reviews  = claim_reviews_of($claim['claim_id']);
            $checks   = claim_verifications_of($claim['claim_id']);
            $rejects  = count(where($reviews, 'outcome', 'rejected'));
            $cid      = $claim['claim_id'];
        ?>
        <article class="card mt-2" id="claim-<?= $cid ?>">
            <div class="card-header">
                <h3 class="mb-0">Claim #<?= $cid ?> &middot; <?= e(full_name($claimant)) ?></h3>
                <span><?= claim_badges($claim, 'claim-' . $cid) ?></span>
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
                            <?php foreach ($history as $h): ?><?= claim_badges($h) ?> <?php endforeach; ?>
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

            <?php if ($reviews): ?>
                <details class="history mt-2" open>
                    <summary>Review history: <?= count($reviews) ?> decision<?= count($reviews) === 1 ? '' : 's' ?>, <?= $rejects ?> rejection<?= $rejects === 1 ? '' : 's' ?></summary>
                    <ul class="timeline mt-1">
                        <?php foreach (array_reverse($reviews) as $r): $reviewer = find_user($r['reviewer_id']); ?>
                            <li>
                                <time><?= e(format_datetime($r['created_at'])) ?></time>
                                <div>
                                    <?= status_badge($r['outcome']) ?> by <?= e($reviewer ? full_name($reviewer) : 'a former staff member') ?>
                                    <?= $r['previous_outcome'] ? '<small class="text-muted">(was ' . e(strtolower(status_label($r['previous_outcome']))) . ')</small>' : '' ?>
                                    <div class="text-sm"><?= decision_list(claim_decision_lines($r)) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

            <?php if ($checks): ?>
                <h4 class="text-sm text-muted mt-2">Identity checks at pickup</h4>
                <ul class="timeline">
                    <?php foreach (array_reverse($checks) as $v): $validator = find_user($v['verified_by']); ?>
                        <li>
                            <time><?= e(format_datetime($v['verified_at'])) ?></time>
                            <div>
                                <span class="badge badge-<?= $v['result'] === 'verified' ? 'approved' : 'rejected' ?>"><?= $v['result'] === 'verified' ? 'ID verified' : 'ID check failed' ?></span>
                                <?= e(ID_TYPES[$v['id_type']] ?? $v['id_type']) ?> <span class="text-muted"><?= e($v['id_reference'] ?? '(number cleared after ' . ID_RECORD_RETENTION_DAYS . ' days)') ?></span>
                                <div class="text-sm">Validated by <?= e($validator ? full_name($validator) : '—') ?><?= $v['notes'] ? ' &middot; ' . e($v['notes']) : '' ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($claim['status'] === 'returned'): ?>
                <div class="alert alert-success mb-0 mt-2">Returned to the claimant on <?= e(format_datetime($item['returned_at'])) ?>.</div>

            <?php elseif (is_staff() && $claim['status'] === 'pending' && $item['status'] === 'stored'): ?>
                <hr>
                <h4><?= is_rejected_claim($claim) ? 'Review again' : 'Decision' ?></h4>
                <?php if (is_rejected_claim($claim)): ?><p class="text-sm text-muted">This claim was rejected. A new decision is added to its history; earlier ones stay.</p><?php endif; ?>
                <form method="post" action="<?= e(item_url('found', $item['item_id']) . '#claim-' . $cid) ?>" class="form" data-validate data-api="review" data-done="reload" data-submit-guard>
                    <input type="hidden" name="claim_id" value="<?= $cid ?>">
                    <fieldset class="form-group decision-choices">
                        <legend>1. Choose a decision <span class="req" aria-hidden="true">*</span></legend>
                        <label><input type="radio" name="decision" value="approve" required> <span>Approve for collection</span></label>
                        <label><input type="radio" name="decision" value="reject"> <span>Reject claim</span></label>
                    </fieldset>

                    <fieldset class="decision-form" data-show-when="decision=approve" hidden disabled>
                        <legend>2. Collection details (shown to the claimant)</legend>
                        <div class="form-group">
                            <label for="pickup_location_<?= $cid ?>">Pickup location <span class="req" aria-hidden="true">*</span></label>
                            <select id="pickup_location_<?= $cid ?>" name="pickup_location" required><option value="">Select a location…</option><?= options(PICKUP_LOCATIONS, null, false) ?></select>
                        </div>
                        <div class="form-group">
                            <label for="pickup_instructions_<?= $cid ?>">Pickup instructions <span class="req" aria-hidden="true">*</span></label>
                            <textarea id="pickup_instructions_<?= $cid ?>" name="pickup_instructions" required minlength="20" maxlength="1000" data-min-words="4"
                                      placeholder="Bring a valid school ID and present claim #<?= $cid ?> at the Lost &amp; Found desk, Mon–Fri 8 AM–5 PM."></textarea>
                        </div>
                        <fieldset class="form-group">
                            <legend>Required identification <span class="req" aria-hidden="true">*</span></legend>
                            <?php foreach (ID_TYPES as $idKey => $idLabel): ?>
                                <label class="check"><input type="checkbox" name="required_ids[]" value="<?= e($idKey) ?>" data-one-of> <?= e($idLabel) ?></label>
                            <?php endforeach; ?>
                        </fieldset>
                        <div class="form-group">
                            <label for="approve_notes_<?= $cid ?>">Additional notes <span class="text-muted text-sm">(optional)</span></label>
                            <textarea id="approve_notes_<?= $cid ?>" name="details" maxlength="1000"></textarea>
                        </div>
                        <p class="text-sm text-muted">Approving does not hand the item over: the ID check and hand-over come next, at the desk.</p>
                        <div class="form-actions"><button type="submit" class="btn btn-success" data-confirm="Approve this claim for collection?">Confirm approval</button><span class="form-hint" data-guard-hint hidden>Complete the required fields above to continue.</span></div>
                    </fieldset>

                    <fieldset class="decision-form" data-show-when="decision=reject" hidden disabled>
                        <legend>2. Rejection details (shown to the claimant)</legend>
                        <div class="form-group">
                            <label for="reject_reason_<?= $cid ?>">Rejection reason <span class="req" aria-hidden="true">*</span></label>
                            <select id="reject_reason_<?= $cid ?>" name="reason_code" required><option value="">Select a reason…</option><?= options(CLAIM_REJECTION_REASONS) ?></select>
                        </div>
                        <div class="form-group">
                            <label for="reject_details_<?= $cid ?>">Rejection details <span class="req" aria-hidden="true">*</span></label>
                            <textarea id="reject_details_<?= $cid ?>" name="details" required minlength="15" maxlength="1000" data-min-words="3"
                                      placeholder="Which ownership details did not match, or what information is still needed."></textarea>
                        </div>
                        <fieldset class="form-group">
                            <legend>Can the claimant provide more information and try again? <span class="req" aria-hidden="true">*</span></legend>
                            <label class="check"><input type="radio" name="resubmission_allowed" value="yes" required> Yes</label>
                            <label class="check"><input type="radio" name="resubmission_allowed" value="no"> No</label>
                        </fieldset>
                        <div class="form-group" data-show-when="resubmission_allowed=yes" hidden>
                            <label for="followup_<?= $cid ?>">What information is needed? <span class="req" aria-hidden="true">*</span></label>
                            <textarea id="followup_<?= $cid ?>" name="required_followup" required minlength="10" maxlength="1000" data-min-words="2" disabled
                                      placeholder="e.g. A photo of the item or a receipt showing the serial number."></textarea>
                        </div>
                        <p class="text-sm text-muted">The claim stays <strong>Pending</strong>; this rejection is added to its review history.</p>
                        <div class="form-actions"><button type="submit" class="btn btn-danger" data-confirm="Reject this claim? It stays pending, with this rejection in its history.">Confirm rejection</button><span class="form-hint" data-guard-hint hidden>Complete the required fields above to continue.</span></div>
                    </fieldset>
                </form>

            <?php elseif (is_staff() && $claim['status'] === 'approved' && $item['status'] === 'stored'): ?>
                <hr>
                <h4>Verify the claimant's ID at pickup</h4>
                <p class="text-sm text-muted">Check the ID in person against <?= e(full_name($claimant)) ?>. Only the ID type and the last four characters of its number are stored.
                    The time and your name are recorded automatically.</p>
                <form method="post" action="<?= e(item_url('found', $item['item_id']) . '#claim-' . $cid) ?>" class="form" data-validate data-api="verify" data-done="reload">
                    <input type="hidden" name="claim_id" value="<?= $cid ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_type_<?= $cid ?>">ID presented <span class="req" aria-hidden="true">*</span></label>
                            <select id="id_type_<?= $cid ?>" name="id_type" required><option value="">Select…</option><?= options(ID_TYPES) ?></select>
                        </div>
                        <div class="form-group">
                            <label for="id_last4_<?= $cid ?>">Last 4 characters of the ID number <span class="req" aria-hidden="true">*</span></label>
                            <input type="text" id="id_last4_<?= $cid ?>" name="id_last4" required minlength="4" maxlength="4" autocomplete="off" spellcheck="false">
                        </div>
                    </div>
                    <fieldset>
                        <legend>Result <span class="req" aria-hidden="true">*</span></legend>
                        <label class="check"><input type="radio" name="id_result" value="verified" required> The ID matches the claimant</label>
                        <label class="check"><input type="radio" name="id_result" value="failed"> The ID does not match, or none was shown</label>
                    </fieldset>
                    <div class="form-group">
                        <label for="id_notes_<?= $cid ?>">Notes <span class="text-muted text-sm">(required for a failed check or "Other" ID, at least 10 characters)</span></label>
                        <textarea id="id_notes_<?= $cid ?>" name="id_notes" maxlength="1000" placeholder="e.g. Photo and name match the school ID."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-confirm="Record this ID check?">Record ID check</button>
                        <button type="reset" class="btn btn-ghost" data-confirm="Clear this ID check without saving?">Cancel</button>
                    </div>
                </form>

            <?php elseif (is_staff() && $claim['status'] === 'verified' && $item['status'] === 'stored'): ?>
                <hr>
                <h4>Hand-over</h4>
                <p class="text-sm text-muted">Mark the item returned once it is in the claimant's hands. This records the time and your name, sets the item to
                    <strong>Returned</strong>, closes the linked lost report, and rejects any other open claims on it (they stay pending with that rejection in their history).</p>
                <form method="post" action="<?= e(item_url('found', $item['item_id']) . '#claim-' . $cid) ?>" class="form" data-validate data-api="return_item" data-done="reload">
                    <input type="hidden" name="claim_id" value="<?= $cid ?>">
                    <div class="form-group">
                        <label class="check"><input type="checkbox" name="confirm_handover" value="1" required> The claimant has received the item.</label>
                    </div>
                    <button type="submit" class="btn btn-primary" data-confirm="Confirm the item has been handed to the claimant? This can't be undone.">Mark item as returned</button>
                </form>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (is_staff() && $item['status'] === 'stored' && $item['moderation_status'] === 'approved'): $blocker = disposal_blocker($item); ?>
        <div class="card card-staff mt-3" id="dispose">
            <h2>Dispose of this item</h2>
            <?php if ($blocker): ?>
                <p class="text-sm mb-0"><span class="badge badge-closed">Not eligible yet</span> <?= e($blocker) ?></p>
            <?php else: ?>
                <p class="text-sm text-muted">For items the office removes from inventory under the school's disposal policy (held at least <?= DISPOSAL_HOLD_DAYS ?> days,
                    no claim approved or awaiting review). Disposed items leave the public list and can no longer be claimed or edited; the record and its claims are kept.</p>
                <form method="post" action="<?= e(item_url('found', $item['item_id']) . '#dispose') ?>" class="form" data-validate data-api="dispose" data-done="reload">
                    <input type="hidden" name="id" value="<?= $item['item_id'] ?>">
                    <div class="form-group">
                        <label for="disposal_reason">Reason <span class="req" aria-hidden="true">*</span></label>
                        <textarea id="disposal_reason" name="disposal_reason" required minlength="10" maxlength="1000" placeholder="e.g. Unclaimed after the holding period; donated per office policy."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="disposal_notes">Notes <span class="text-muted text-sm">(optional)</span></label>
                        <textarea id="disposal_notes" name="disposal_notes" maxlength="1000" placeholder="e.g. Authorized by the office head; recipient organisation."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="check"><input type="checkbox" name="confirm_disposal" value="1" required> This item is eligible for disposal under the school's policy.</label>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger" data-confirm="Dispose of this item? It will be marked Disposed and can't be claimed or edited again.">Dispose of item</button>
                        <button type="reset" class="btn btn-ghost" data-confirm="Clear this form without saving?">Cancel</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <h2 class="mt-3">Item history</h2>
        <?= activity_table(activity_events('', null, 50, $item['item_id']), 'itemActivity') ?>
        <?php endif; ?>
    </div>

    <aside>
        <?php if (!$canClaim && !$myClaim): ?>
            <?php if (can_monitor()): ?>
                <div class="card card-muted">
                    <h3>Record status</h3>
                    <p class="text-sm mb-0">
                        <?php if (is_final_item($item)): ?>
                            <?= $item['status'] === 'disposed' ? 'Disposed of' : 'Returned to its owner' ?>. This is a closed record, kept for audits: it can't be claimed or edited.
                        <?php elseif ($pendingCount): ?>
                            <a href="#claims"><?= $pendingCount ?> claim<?= $pendingCount === 1 ? '' : 's' ?> awaiting review</a>.
                        <?php else: ?>
                            In storage. No claims awaiting review.
                        <?php endif; ?>
                        <?= is_staff() ? '' : '<br>Administrators can view these records but not change them.' ?>
                    </p>
                </div>
            <?php elseif ($isPoster): ?>
                <div class="card card-muted">
                    <h3>Your post</h3>
                    <p class="text-sm mb-0">Status: <?= status_badge($item['moderation_status']) ?>. See all your posts under <a href="<?= e(url('/?tab=posts')) ?>">My Found Posts</a>.</p>
                </div>
            <?php endif; ?>
        <?php elseif (!$user): ?>
            <div class="card">
                <h3>Is this yours?</h3>
                <p class="text-sm">Log in with your Mapua account to submit an ownership claim.</p>
                <a class="btn btn-primary btn-block" href="<?= e(url('/login.php?next=' . rawurlencode(item_url('found', $item['item_id'])))) ?>">Log in to claim</a>
            </div>
        <?php elseif ($myClaim): ?>
            <div class="card">
                <h3>Your claim</h3>
                <p><?= claim_badges($myClaim) ?> <small>submitted <?= e(format_date($myClaim['date_claimed'])) ?></small></p>
                <?php if ($myClaim['status'] === 'returned'): ?>
                    <div class="alert alert-success text-sm mb-0">Returned to you on <?= e(format_datetime($item['returned_at'])) ?>.</div>
                <?php elseif (awaiting_review($myClaim)): ?>
                    <p class="text-sm text-muted">Staff are reviewing your claim. You'll see the result here and on <a href="<?= e(url('/?tab=my_claims')) ?>">My Claims</a>.</p>
                <?php elseif (is_rejected_claim($myClaim)): ?>
                    <div class="alert alert-error text-sm mb-0"><strong>Not approved.</strong> <?= decision_list(claim_decision_lines(latest_claim_review($myClaim))) ?></div>
                <?php else: ?>
                    <div class="alert alert-success text-sm mb-0"><strong><?= $myClaim['status'] === 'verified' ? 'ID verified.' : 'Approved for collection.' ?></strong> <?= decision_list(claim_decision_lines(latest_claim_review($myClaim))) ?></div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card" id="claim" data-replace>
                <h3>Is this yours?</h3>
                <p class="text-sm">Prove it by describing details that aren't visible in the listing. Staff compare this with the record made at intake — vague claims are rejected.</p>
                <form method="post" action="<?= e(item_url('found', $item['item_id']) . '#claim') ?>" class="form" data-validate data-api="claim" data-done="replace">
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
                                <option value="<?= $r['report_id'] ?>" <?= $r['category_id'] === $item['category_id'] ? 'selected' : '' ?>>
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
                    <p class="text-sm text-muted mt-1 mb-0"><?= $pendingCount ?> other claim<?= $pendingCount === 1 ? ' is' : 's are' ?> awaiting review.</p>
                <?php endif; ?>
            </div>
            <div class="alert alert-warning mt-2 mb-0 text-sm">
                False claims are logged against your account and may be reported to the Office of Student Affairs.
            </div>
        <?php endif; ?>

        <?php if ($canClaim && !$myClaim): ?>
        <div class="card card-muted mt-2">
            <h3>Tips for a successful claim</h3>
            <ul class="text-sm list-plain">
                <li>Mention contents, scratches, stickers, engravings.</li>
                <li>Include brand and model if you know them.</li>
                <li>Link your lost report if you filed one.</li>
            </ul>
        </div>
        <?php endif; ?>
    </aside>
</div>
<?php endif; ?>

<?php include APP_ROOT . '/templates/layout/footer.php'; ?>
