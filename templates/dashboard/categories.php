<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: categories admin. */ ?>
<div class="page-header">
    <div><h1>Categories</h1><p>The categories offered on lost-report and found-item forms. Deactivating hides one from new forms; records that already use it keep it.</p></div>
</div>

    <?php
    $usage = category_usage();
    $cats  = array_values(all_categories());
    usort($cats, fn ($a, $b) => [!$a['is_active'], $a['name']] <=> [!$b['is_active'], $b['name']]);   // active first, then by name
    ?>
    <div class="grid grid-sidebar">
        <div>
            <div class="table-wrap">
                <table class="table" id="categoriesTable">
                    <thead><tr><th>Category</th><th>Status</th><th>Used by</th><th>Added</th><th>Updated</th><th class="actions"></th></tr></thead>
                    <tbody>
                    <?php foreach ($cats as $c):
                        $lost  = $usage[$c['category_id']]['lost'] ?? 0;
                        $found = $usage[$c['category_id']]['found'] ?? 0;
                        $creator = find_user($c['created_by']); ?>
                        <tr>
                            <td><span class="table-title"><?= e($c['name']) ?></span><?php if ($c['description']): ?><span class="table-sub"><?= e($c['description']) ?></span><?php endif; ?></td>
                            <td><span class="badge badge-<?= $c['is_active'] ? 'active' : 'inactive' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="nowrap"><?= $lost + $found ? "$lost report" . ($lost === 1 ? '' : 's') . ", $found item" . ($found === 1 ? '' : 's') : '<span class="text-muted">Not used</span>' ?></td>
                            <td class="nowrap"><?= e(format_date($c['created_at'])) ?><span class="table-sub"><?= $creator ? e(full_name($creator)) : 'Original list' ?></span></td>
                            <td class="nowrap"><?= e(format_datetime($c['updated_at'])) ?></td>
                            <td class="actions">
                                <form method="post" action="<?= e(url('/?tab=categories')) ?>" class="inline-form" data-api="category" data-done="reload"
                                      data-confirm="<?= e($c['is_active'] ? 'Deactivate "' . $c['name'] . '"? It won\'t be offered on new forms; records that use it keep it.' : 'Activate "' . $c['name'] . '" again?') ?>">
                                    <input type="hidden" name="action" value="set_active">
                                    <input type="hidden" name="category_id" value="<?= $c['category_id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $c['is_active'] ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm <?= $c['is_active'] ? 'btn-secondary' : 'btn-success' ?>"><?= $c['is_active'] ? 'Deactivate' : 'Activate' ?><span class="sr-only"> <?= e($c['name']) ?></span></button>
                                </form>
                                <?php if ($lost + $found): ?>
                                    <button type="button" class="btn btn-sm btn-ghost" disabled title="Used by existing records: deactivate it instead">Delete<span class="sr-only"> <?= e($c['name']) ?> (in use)</span></button>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('/?tab=categories')) ?>" class="inline-form" data-api="category" data-done="reload"
                                          data-confirm="<?= e('Delete "' . $c['name'] . '" permanently? No record uses it.') ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?= $c['category_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete<span class="sr-only"> <?= e($c['name']) ?></span></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-sm text-muted mt-1">A category used by any lost report or found item can't be deleted, so old records keep their category; deactivate it instead.
                "<?= e(OTHER_CATEGORY) ?>" asks the user to describe the item type.</p>
        </div>
        <aside>
            <div class="card">
                <h2>Add a category</h2>
                <form method="post" action="<?= e(url('/?tab=categories')) ?>" class="form" data-validate data-api="category" data-done="reload">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label for="cat_name">Name <span class="req" aria-hidden="true">*</span></label>
                        <input type="text" id="cat_name" name="name" required minlength="2" maxlength="50" data-label="Name" placeholder="e.g. Sports Equipment">
                        <span class="form-hint">Must differ from every existing name (upper and lower case count as the same).</span>
                    </div>
                    <div class="form-group">
                        <label for="cat_description">Description <span class="text-muted text-sm">(optional)</span></label>
                        <input type="text" id="cat_description" name="description" maxlength="255" placeholder="e.g. Balls, rackets, gym gear">
                    </div>
                    <div class="form-actions compact">
                        <button type="submit" class="btn btn-primary">Add category</button>
                        <button type="reset" class="btn btn-ghost">Cancel</button>
                    </div>
                </form>
            </div>
        </aside>
    </div>
