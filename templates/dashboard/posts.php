<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: my found posts students / faculty. */ ?>
<div class="page-header">
    <div><h1>My found posts</h1><p>Items you found and posted. Staff approve each one before it is listed publicly.</p></div>
    <a class="btn btn-primary" href="<?= e(url('/report.php?type=found')) ?>">+ Post a found item</a>
</div>

    <?php $myPosts = newest_first(where(all_found_items(), 'added_by', $user['user_id'])); ?>
    <?php if ($myPosts): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th>Item</th><th>Found on</th><th>Review</th><th>Item status</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($myPosts as $post): ?>
                <tr>
                    <td><?= photo_tag($post['image_url'], $post['item_name'], 'photo-thumb') ?></td>
                    <td>
                        <a class="table-title" href="<?= e(item_url('found', $post['item_id'])) ?>"><?= e($post['item_name']) ?></a>
                        <?php if ($post['moderation_status'] === 'rejected' && $post['moderation_note']): ?><span class="table-sub"><?= e($post['moderation_note']) ?></span><?php endif; ?>
                    </td>
                    <td class="nowrap"><?= e(format_date($post['date_found'])) ?></td>
                    <td><?= status_badge($post['moderation_status']) ?></td>
                    <td><?= $post['moderation_status'] === 'approved' ? status_badge($post['status']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(item_url('found', $post['item_id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state('No posts yet', 'Found something on campus? Post it and bring it to the Lost & Found office.', url('/report.php?type=found'), 'Post a found item') ?>
    <?php endif; ?>
