<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: post review staff. */ ?>
<div class="page-header">
    <div><h1>Post review</h1><p>Found-item posts from students and faculty. Approve them once the item is at the office.</p></div>
</div>

    <?php if ($pendingPosts): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Item</th><th>Posted by</th><th>Found</th><th>Submitted</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($pendingPosts) as $post): $poster = find_user($post['added_by']); ?>
                <tr>
                    <td class="text-muted"><?= $post['item_id'] ?></td>
                    <td>
                        <a class="table-title" href="<?= e(item_url('found', $post['item_id'])) ?>"><?= e($post['item_name']) ?></a>
                        <span class="table-sub"><?= e($post['category']) ?></span>
                    </td>
                    <td><?= e(full_name($poster)) ?><span class="table-sub"><?= e($poster['email']) ?></span></td>
                    <td><?= e($post['location_found']) ?><span class="table-sub"><?= e(format_date($post['date_found'])) ?></span></td>
                    <td class="nowrap"><?= e(format_datetime($post['created_at'])) ?></td>
                    <td class="actions"><a class="btn btn-primary btn-sm" href="<?= e(item_url('found', $post['item_id'])) ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state('Nothing to review', 'There are no student or faculty posts waiting for approval.') ?>
    <?php endif; ?>
