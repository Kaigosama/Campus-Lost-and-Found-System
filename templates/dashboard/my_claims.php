<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: my claims. */ ?>
<div class="page-header">
    <div><h1>My claims</h1><p>Ownership claims you've submitted and their review status.</p></div>
    <a class="btn btn-outline" href="<?= e(url('/browse.php')) ?>">Browse found items</a>
</div>

    <?php if ($myClaims): ?>
    <div class="grid">
        <?php foreach ($myClaims as $claim): $item = find_found_item($claim['item_id']); $reviewer = find_user($claim['reviewed_by']); ?>
            <article class="card" data-remove>
                <div class="card-header">
                    <div class="flex items-center gap-2">
                        <?= photo_tag($item['image_url'], $item['item_name'], 'photo-thumb') ?>
                        <div>
                            <h2 class="mb-0"><a href="<?= e(item_url('found', $item['item_id'])) ?>"><?= e($item['item_name']) ?></a></h2>
                            <small>Claim #<?= $claim['claim_id'] ?> &middot; submitted <?= e(format_datetime($claim['created_at'])) ?></small>
                        </div>
                    </div>
                    <span><?= claim_badges($claim) ?></span>
                </div>

                <div class="grid grid-2">
                    <div>
                        <h3 class="text-sm text-muted">What you told us</h3>
                        <div class="quote claimant text-sm"><?= e($claim['proof_description']) ?></div>
                        <?php if ($claim['report_id']): $r = find_lost_report($claim['report_id']); ?>
                            <p class="text-sm mt-1 mb-0">Linked report: <a href="<?= e(item_url('lost', $r['report_id'])) ?>">#<?= $r['report_id'] ?> <?= e($r['item_name']) ?></a></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-sm text-muted">Staff response</h3>
                        <?php if (awaiting_review($claim)): ?>
                            <p class="text-sm text-muted">Awaiting review. Most claims are reviewed within one working day.</p>
                        <?php else: ?>
                            <div class="alert alert-<?= is_rejected_claim($claim) ? 'error' : 'success' ?> text-sm">
                                <strong><?= match ($claim['status']) {
                                    'approved' => 'Approved for collection.', 'verified' => 'ID verified.', 'returned' => 'Returned to you.', default => 'Not approved.',
                                } ?></strong> <?= decision_list(claim_decision_lines(latest_claim_review($claim))) ?>
                            </div>
                            <p class="text-sm mb-0">Reviewed by <?= e($reviewer ? full_name($reviewer) : 'Lost & Found staff') ?> on <?= e(format_datetime($claim['reviewed_at'])) ?></p>
                            <?php if (is_rejected_claim($claim)): ?>
                                <p class="text-sm mb-0">Your claim stays on record as pending. Staff may review it again; visit <?= e(OFFICE_INFO) ?> if you have more proof.</p>
                            <?php elseif ($claim['status'] === 'approved'): ?>
                                <p class="text-sm mb-0">Bring a valid ID to <?= e(OFFICE_INFO) ?>. Staff check it before handing the item over.</p>
                            <?php elseif ($claim['status'] === 'returned'): ?>
                                <p class="text-sm mb-0">Collected on <?= e(format_datetime($item['returned_at'])) ?>.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (awaiting_review($claim)): ?>
                    <div class="form-actions compact">
                        <form method="post" action="<?= e(url('/?tab=my_claims')) ?>" data-api="withdraw" data-done="remove" data-confirm="Withdraw this claim?">
                            <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Withdraw claim</button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <?= empty_state('No claims yet', 'When you spot your item in the found list, open it and click "Claim this item".', url('/browse.php'), 'Browse found items') ?>
    <?php endif; ?>
