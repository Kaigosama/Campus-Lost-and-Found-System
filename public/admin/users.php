<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role('admin');

$me = current_user();
$pageTitle = 'Manage users';

$roleFilter = $_GET['role'] ?? '';
$users = array_values(mock_users());
if ($roleFilter !== '') {
    $users = array_values(array_filter($users, fn ($u) => $u['role'] === $roleFilter));
}
usort($users, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

$counts = ['' => count(mock_users())];
foreach (ROLES as $key => $label) {
    $counts[$key] = count(array_filter(mock_users(), fn ($u) => $u['role'] === $key));
}

include APP_PATH . '/views/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1>Manage users</h1>
        <p>Promote staff, deactivate accounts. New registrations always start as <strong>User</strong>.</p>
    </div>
</div>

<nav class="pill-tabs" aria-label="Filter by role">
    <a href="<?= e(url_with(['role' => null])) ?>" class="<?= $roleFilter === '' ? 'active' : '' ?>">All <span class="count"><?= $counts[''] ?></span></a>
    <?php foreach (ROLES as $key => $label): ?>
        <a href="<?= e(url_with(['role' => $key])) ?>" class="<?= $roleFilter === $key ? 'active' : '' ?>"><?= e($label) ?> <span class="count"><?= $counts[$key] ?></span></a>
    <?php endforeach; ?>
</nav>

<div class="table-tools">
    <input type="search" placeholder="Search name or email…" aria-label="Filter users" data-table-filter="#usersTable">
    <span class="text-sm text-muted">Showing <span data-filter-count="#usersTable"><?= count($users) ?></span></span>
</div>

<div class="table-wrap">
    <table class="table" id="usersTable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th class="actions"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): $isMe = $u['user_id'] === $me['user_id']; ?>
            <tr>
                <td>
                    <div class="flex items-center gap-1">
                        <span class="avatar" style="background:var(--primary-light)" aria-hidden="true"><?= e(initials($u)) ?></span>
                        <span class="table-title"><?= e(full_name($u)) ?><?= $isMe ? ' <small class="text-muted">(you)</small>' : '' ?></span>
                    </div>
                </td>
                <td><?= e($u['email']) ?></td>
                <td>
                    <form method="post" action="<?= e(url('/admin/users.php')) ?>" data-confirm="Change this user's role?">
                        <input type="hidden" name="action" value="role">
                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                        <label for="role-<?= $u['user_id'] ?>" class="sr-only">Role</label>
                        <select id="role-<?= $u['user_id'] ?>" name="role" class="inline-select" onchange="this.form.requestSubmit()" <?= $isMe ? 'disabled title="You cannot change your own role"' : '' ?>>
                            <?= options(ROLES, $u['role']) ?>
                        </select>
                    </form>
                </td>
                <td><span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></span></td>
                <td class="nowrap"><?= e(format_date($u['created_at'])) ?></td>
                <td class="actions">
                    <?php if (!$isMe): ?>
                        <form method="post" action="<?= e(url('/admin/users.php')) ?>" style="display:inline"
                              data-confirm="<?= $u['is_active'] ? 'Deactivate this account? They will no longer be able to log in.' : 'Reactivate this account?' ?>">
                            <input type="hidden" name="action" value="<?= $u['is_active'] ? 'deactivate' : 'activate' ?>">
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-secondary' : 'btn-success' ?>">
                                <?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="filter-empty" data-filter-empty hidden>No users match that search.</p>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
