<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: claims staff; admins read-only. */ ?>
<div class="page-header">
    <div><h1>Claims</h1><p>Pending &rarr; approved for collection &rarr; identity verified &rarr; returned. A rejected claim stays pending, with each rejection kept in its history.
        <?= is_staff() ? '' : 'Read-only for administrators.' ?></p></div>
</div>

    <?php
    // Views over the same claim records. "Rejected" lists each item once, however many of its claims were rejected.
    $claimViews = [
        'review'   => ['Awaiting review', fn ($c) => awaiting_review($c)],
        'rejected' => ['Rejected (still pending)', fn ($c) => is_rejected_claim($c)],
        'approved' => ['Approved for collection', fn ($c) => $c['status'] === 'approved'],
        'verified' => ['Identity verified', fn ($c) => $c['status'] === 'verified'],
        'all'      => ['All', fn ($c) => true],   // returned claims too; hand-overs have their own Returned Items section
    ];
    $status     = $_GET['status'] ?? 'review';
    $status     = isset($claimViews[$status]) ? $status : 'review';
    $itemFilter = (int) ($_GET['item'] ?? 0);

    $rows = array_values(array_filter(all_claims(), $claimViews[$status][1]));
    if ($itemFilter) {
        $rows = where($rows, 'item_id', $itemFilter);
    }
    usort($rows, fn ($a, $b) => $status === 'review'
        ? strcmp($a['created_at'], $b['created_at'])
        : strcmp($b['created_at'], $a['created_at']));

    $groups  = rejected_claim_groups();
    $counts  = array_map(fn ($v) => count(array_filter(all_claims(), $v[1])), $claimViews);
    $counts['rejected'] = count($groups);
    echo pill_tabs(array_map(fn ($v) => $v[0], $claimViews), $counts, $status, 'status');
    ?>

    <?php if ($itemFilter && ($fi = find_found_item($itemFilter))): ?>
        <div class="alert alert-info">
            Showing claims for <strong><?= e($fi['item_name']) ?></strong> only.
            <a href="<?= e(url_with(['item' => null])) ?>">Show all items</a>
        </div>
    <?php endif; ?>

    <?php if ($status === 'rejected'): ?>
        <?php if ($itemFilter) $groups = array_intersect_key($groups, [$itemFilter => 1]); ?>
        <?php if ($groups): ?>
        <p class="text-sm text-muted">One row per item. The claims stay separate records with their own claimants; open the item to see each claim and its full rejection history.</p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Item</th><th>Main status</th><th>Current review outcome</th><th>Rejected claims</th><th>Rejection history</th><th>Latest rejection</th><th class="actions"></th></tr></thead>
                <tbody>
                <?php foreach ($groups as $g): $item = $g['item']; ?>
                    <tr>
                        <td><a class="table-title" href="<?= e(item_url('found', $item['item_id'], 'queue') . '#claims') ?>"><?= e($item['item_name']) ?></a>
                            <span class="table-sub">Item #<?= $item['item_id'] ?> &middot; <?= e(status_label($item['status'])) ?></span></td>
                        <td><?= status_badge('pending') ?></td>
                        <td><span class="badge badge-rejected">Rejected</span></td>
                        <td><?= count($g['claims']) ?> claim<?= count($g['claims']) === 1 ? '' : 's' ?>
                            <span class="table-sub"><?= e(implode(', ', array_map(fn ($c) => '#' . $c['claim_id'] . ' ' . full_name(find_user($c['claimant_id'])), $g['claims']))) ?></span></td>
                        <td><?= $g['rejections'] ?> event<?= $g['rejections'] === 1 ? '' : 's' ?></td>
                        <td class="nowrap"><?= e(format_datetime($g['latest'])) ?></td>
                        <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(item_url('found', $item['item_id'], 'queue') . '#claims') ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <?= empty_state('No rejected claims', 'Items whose claims were rejected show up here, once per item.') ?>
        <?php endif; ?>

    <?php elseif ($rows): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Item</th><th>Claimant</th><th>Proof (excerpt)</th><th>Linked report</th><th>Submitted</th><th>Status</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $claim): $item = find_found_item($claim['item_id']); $claimant = find_user($claim['claimant_id']); ?>
                <tr>
                    <td class="text-muted"><?= $claim['claim_id'] ?></td>
                    <td>
                        <a class="table-title" href="<?= e(item_url('found', $item['item_id'], 'queue')) ?>"><?= e($item['item_name']) ?></a>
                        <span class="table-sub"><?= e($item['storage_location']) ?></span>
                    </td>
                    <td><?= e(full_name($claimant)) ?><span class="table-sub"><?= e($claimant['email']) ?></span></td>
                    <td class="cell-wide"><?= e(excerpt($claim['proof_description'], 80)) ?></td>
                    <td>
                        <?php if ($claim['report_id']): ?>
                            <a href="<?= e(item_url('lost', $claim['report_id'], 'queue')) ?>">#<?= $claim['report_id'] ?></a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="nowrap"><?= e(format_date($claim['date_claimed'])) ?></td>
                    <td><?= claim_badges($claim) ?></td>
                    <td class="actions">
                        <?php $next = is_staff() && in_array($claim['status'], ['pending', 'approved', 'verified'], true) && $item['status'] === 'stored'; ?>
                        <a class="btn <?= $next ? 'btn-primary' : 'btn-outline' ?> btn-sm" href="<?= e(item_url('found', $item['item_id'], 'queue') . '#claim-' . $claim['claim_id']) ?>">
                            <?= !$next ? 'Open' : match ($claim['status']) { 'approved' => 'Verify ID', 'verified' => 'Hand over', default => 'Review' } ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state(
            $status === 'review' ? 'Queue is clear' : 'No claims here',
            $status === 'review' ? 'There are no claims waiting for review right now.' : ''
        ) ?>
    <?php endif; ?>
