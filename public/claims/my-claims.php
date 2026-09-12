<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();

$user = current_user();
$pageTitle = 'My claims';

$claims = array_values(array_filter(mock_claims(), fn ($c) => $c['user_id'] === $user['user_id']));
usort($claims, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

include APP_PATH . '/views/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>My claims</h1>
        <p>Ownership claims you've submitted and their review status.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/found/browse.php')) ?>">Browse found items</a>
</div>

<?php if ($claims): ?>
<div class="grid">
    <?php foreach ($claims as $claim): $item = mock_found_item($claim['item_id']); $reviewer = mock_user($claim['reviewed_by']); ?>
        <article class="card">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <?= photo_tag($item['photo_path'], $item['item_name'], 'photo-thumb') ?>
                    <div>
                        <h2 class="mb-0"><a href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>"><?= e($item['item_name']) ?></a></h2>
                        <small>Claim #<?= $claim['claim_id'] ?> &middot; submitted <?= e(format_datetime($claim['created_at'])) ?></small>
                    </div>
                </div>
                <?= status_badge($claim['status']) ?>
            </div>

            <div class="grid grid-2">
                <div>
                    <h3 class="text-sm text-muted">What you told us</h3>
                    <div class="quote claimant text-sm"><?= e($claim['proof_description']) ?></div>
                    <?php if ($claim['lost_report_id']): $r = mock_lost_report($claim['lost_report_id']); ?>
                        <p class="text-sm mt-1 mb-0">Linked report: <a href="<?= e(url('/lost/view.php?id=' . $r['report_id'])) ?>">#<?= $r['report_id'] ?> <?= e($r['item_name']) ?></a></p>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-sm text-muted">Staff response</h3>
                    <?php if ($claim['status'] === 'pending'): ?>
                        <p class="text-sm text-muted">Awaiting review. Most claims are reviewed within one working day.</p>
                    <?php elseif ($claim['status'] === 'approved'): ?>
                        <div class="alert alert-success text-sm">
                            <strong>Approved.</strong> <?= e($claim['review_note']) ?>
                        </div>
                        <p class="text-sm mb-0">Reviewed by <?= e(full_name($reviewer)) ?> on <?= e(format_datetime($claim['reviewed_at'])) ?></p>
                    <?php else: ?>
                        <div class="alert alert-error text-sm">
                            <strong>Not approved.</strong> <?= e($claim['review_note']) ?>
                        </div>
                        <p class="text-sm mb-0">Reviewed by <?= e(full_name($reviewer)) ?> on <?= e(format_datetime($claim['reviewed_at'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($claim['status'] === 'pending'): ?>
                <div class="form-actions" style="margin-top:1rem;padding-top:.75rem">
                    <form method="post" action="<?= e(url('/claims/my-claims.php')) ?>" data-confirm="Withdraw this claim?">
                        <input type="hidden" name="action" value="withdraw">
                        <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Withdraw claim</button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <?php
    $emptyTitle = 'No claims yet';
    $emptyText  = 'When you spot your item in the found list, open it and click "Claim this item".';
    $emptyActionUrl = url('/found/browse.php');
    $emptyActionLabel = 'Browse found items';
    include APP_PATH . '/views/partials/empty-state.php';
    ?>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
