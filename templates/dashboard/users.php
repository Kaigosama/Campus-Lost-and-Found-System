<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: users admin. */ ?>
<div class="page-header">
    <div><h1>Users &amp; activity</h1><p>Roles, account status and recent activity. New registrations start as <strong><?= e(ROLES['user']) ?></strong>.
        <?= has_role('master_admin') ? 'As master administrator you can also manage administrator accounts.' : 'Only the master administrator can change administrator accounts.' ?></p></div>
</div>

<?php if ($member = find_user((int) ($_GET['id'] ?? 0))): /* one account's review page */ ?>
    <?php
    $memberViolations = account_violations($member['user_id']);
    $deactivator      = find_user($member['deactivated_by']);
    $falseReports     = $member['deactivation_reason'] === 'false_reports';
    ?>
    <?= back_button(url('/?tab=users'), 'All users') ?>
    <div class="grid grid-2 mb-3">
        <div class="card">
            <div class="card-header">
                <h2><?= e(full_name($member)) ?></h2>
                <span class="badge badge-<?= $member['is_active'] ? 'active' : 'inactive' ?>" data-status-for="user-<?= $member['user_id'] ?>"><?= $member['is_active'] ? 'Active' : 'Deactivated' ?></span>
            </div>
            <dl class="detail-list">
                <dt>Email</dt><dd><?= e($member['email']) ?><?= $member['email_verified'] ? '' : ' <span class="badge badge-pending">Unverified</span>' ?></dd>
                <dt>Role</dt><dd><?= e(ROLES[$member['role']] ?? $member['role']) ?></dd>
                <dt>School record</dt><dd><?= $member['student_number'] || $member['employee_number'] ? e(account_type_label($member)) : ($member['role'] === 'user' ? '<span class="badge badge-pending">None: registered before school records; needs linking</span>' : '<span class="text-muted">None (office account without an employee record)</span>') ?></dd>
                <dt>Registered</dt><dd><?= e(format_datetime($member['created_at'])) ?></dd>
                <dt>Last login</dt><dd><?= e(format_datetime($member['last_login_at'])) ?></dd>
                <?php if (account_locked($member)): ?><dt>Locked</dt><dd><?= e(format_datetime($member['locked_at'])) ?> (unlock from the users list)</dd><?php endif; ?>
                <?php if (!$member['is_active']): ?>
                    <dt>Deactivated</dt>
                    <dd>
                        <?= e(format_datetime($member['deactivated_at'])) ?><?= $deactivator ? ' by ' . e(full_name($deactivator)) : '' ?><br>
                        <small>Reason: <?= $falseReports ? 'repeated confirmed false reports' : 'administrator decision' ?></small>
                    </dd>
                <?php endif; ?>
            </dl>
        </div>

        <div class="card">
            <h2>Account status</h2>
            <?php if (!can_manage_user($member)): ?>
                <p class="text-muted mb-0">You can't change this account.</p>
            <?php elseif ($member['is_active']): ?>
                <p class="text-sm">Deactivating logs the user out everywhere and stops them logging in.</p>
                <form method="post" action="<?= e(url('/?tab=users&id=' . $member['user_id'])) ?>" class="form" data-api="update_user" data-done="replace"
                      data-confirm="Deactivate this account? They will be logged out and no longer able to log in.">
                    <input type="hidden" name="user_id" value="<?= $member['user_id'] ?>">
                    <input type="hidden" name="is_active" value="0">
                    <button type="submit" class="btn btn-secondary">Deactivate account</button>
                </form>
            <?php else: ?>
                <?php if ($falseReports): ?>
                    <div class="alert alert-warning text-sm">Deactivated after <?= count($memberViolations) ?> confirmed false reports. Reactivate only after the
                        account holder has come to the Lost &amp; Found office, their identity has been checked against their school ID, and the review supports it.</div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/?tab=users&id=' . $member['user_id'])) ?>" class="form" data-validate data-api="update_user" data-done="replace"
                      data-confirm="Reactivate this account?">
                    <input type="hidden" name="user_id" value="<?= $member['user_id'] ?>">
                    <input type="hidden" name="is_active" value="1">
                    <div class="form-group">
                        <label for="review_note">Review notes <?= $falseReports ? '<span class="req" aria-hidden="true">*</span>' : '<span class="text-muted text-sm">(optional)</span>' ?></label>
                        <textarea id="review_note" name="note" maxlength="1000" <?= $falseReports ? 'required minlength="10"' : '' ?>
                                  placeholder="e.g. Identity checked against school ID at the office on <?= e(date('M j')) ?>; student acknowledged the false-report policy."></textarea>
                        <span class="form-hint">Saved in the activity log with your name.</span>
                    </div>
                    <button type="submit" class="btn btn-success">Reactivate account</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <h2>False-report violations (<?= count($memberViolations) ?> of <?= FALSE_REPORTS_TO_DEACTIVATE ?>)</h2>
    <?php if ($memberViolations): ?>
    <div class="table-wrap mb-3">
        <table class="table">
            <thead><tr><th>#</th><th>Report</th><th>Date</th><th>Action</th><th>Reason</th><th>Issued by</th></tr></thead>
            <tbody>
            <?php foreach ($memberViolations as $i => $v): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td>#<?= (int) $v['report_id'] ?> <?= e($v['item_name']) ?></td>
                    <td class="nowrap"><?= e(format_datetime($v['created_at'])) ?></td>
                    <td><span class="badge badge-<?= $v['action_taken'] === 'warning' ? 'pending' : 'rejected' ?>"><?= $v['action_taken'] === 'warning' ? 'Warning' : 'Account deactivated' ?></span></td>
                    <td class="cell-wide"><?= e($v['reason']) ?></td>
                    <td><?= $v['issued_by'] ? e($v['first_name'] . ' ' . $v['last_name']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted mb-3">None.</p>
    <?php endif; ?>

    <h2>Activity</h2>
    <?php $memberEvents = activity_events('', $member['user_id']); ?>
    <div class="table-tools">
        <input type="search" placeholder="Filter this account's activity…" aria-label="Filter account activity" data-table-filter="#memberActivity">
        <span class="text-sm text-muted">Showing <span data-filter-count="#memberActivity"><?= count($memberEvents) ?></span> (latest 100)</span>
    </div>
    <?= activity_table($memberEvents, 'memberActivity') ?>
    <p class="filter-empty" data-filter-empty hidden>No activity matches that filter.</p>
<?php else: ?>
    <?php
    $roleFilter = $_GET['role'] ?? '';
    $rows = newest_first($roleFilter === '' ? array_values(all_users()) : where(all_users(), 'role', $roleFilter));
    $counts = ['' => count(all_users())];
    foreach (ROLES as $key => $label) {
        $counts[$key] = count_where(all_users(), 'role', $key);
    }
    echo pill_tabs(['' => 'All'] + ROLES, $counts, $roleFilter, 'role');

    // Sessions with authenticated activity inside the idle window count as online.
    $stmt = db()->prepare('SELECT user_id, COUNT(*) FROM user_sessions WHERE status = "active" AND last_activity_at > NOW() - INTERVAL ? MINUTE GROUP BY user_id');
    $stmt->execute([SESSION_IDLE_MINUTES]);
    $online     = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $violations = db()->query("SELECT reported_by, COUNT(*) FROM lost_reports WHERE status = 'false_report' GROUP BY reported_by")->fetchAll(PDO::FETCH_KEY_PAIR);
    $isMaster   = has_role('master_admin');
    $assignable = array_intersect_key(ROLES, array_flip($isMaster ? ['user', 'staff', 'admin'] : ['user', 'staff']));
    ?>

    <div class="table-tools">
        <input type="search" placeholder="Search name or email…" aria-label="Filter users" data-table-filter="#usersTable">
        <span class="text-sm text-muted">Showing <span data-filter-count="#usersTable"><?= count($rows) ?></span></span>
    </div>

    <div class="table-wrap">
        <table class="table" id="usersTable">
            <thead><tr><th>Name</th><th>Role</th><th>Account</th><th>Last login</th><th>Activity</th><th>Session</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u):
                $isMe     = $u['user_id'] === $user['user_id'];
                $canEdit  = can_manage_user($u);
                $sessions = (int) ($online[$u['user_id']] ?? 0);
                $locked   = account_locked($u);
            ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-1">
                            <span class="avatar" aria-hidden="true"><?= e(initials($u)) ?></span>
                            <span class="table-title"><a href="<?= e(url('/?tab=users&id=' . $u['user_id'])) ?>"><?= e(full_name($u)) ?></a><?= $isMe ? ' <small class="text-muted">(you)</small>' : '' ?>
                                <span class="table-sub"><?= e($u['email']) ?></span>
                                <?php if ($u['student_number'] || $u['employee_number']): ?><span class="table-sub"><?= e(account_type_label($u)) ?></span>
                                <?php elseif ($u['role'] === 'user'): ?><span class="badge badge-pending">No school record</span><?php endif; ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if ($canEdit): ?>
                        <form method="post" action="<?= e(url('/?tab=users')) ?>" class="inline-form" data-api="update_user" data-confirm="Change this user's role?">
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <label for="role-<?= $u['user_id'] ?>" class="sr-only">Role for <?= e(full_name($u)) ?></label>
                            <select id="role-<?= $u['user_id'] ?>" name="role" class="inline-select">
                                <?= options($assignable, $u['role']) ?>
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm">Save<span class="sr-only"> role for <?= e(full_name($u)) ?></span></button>
                        </form>
                        <?php else: ?>
                            <?= e(ROLES[$u['role']] ?? $u['role']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>" data-status-for="user-<?= $u['user_id'] ?>"><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></span>
                        <?php if (!$u['email_verified']): ?><span class="badge badge-pending">Unverified</span><?php endif; ?>
                        <?php if ($locked): ?><span class="badge badge-rejected" data-locked-for="<?= $u['user_id'] ?>" title="Locked <?= e(format_datetime($u['locked_at'])) ?>">Locked</span><?php endif; ?>
                        <?php if (!empty($violations[$u['user_id']])): ?><span class="badge badge-false_report"><?= (int) $violations[$u['user_id']] ?> false report<?= (int) $violations[$u['user_id']] === 1 ? '' : 's' ?></span><?php endif; ?>
                    </td>
                    <td class="nowrap"><?= e(format_datetime($u['last_login_at'])) ?></td>
                    <td class="nowrap"><?= e(activity_label($u['last_activity_at'], $sessions)) ?></td>
                    <td class="nowrap"><?= $sessions ? '<span class="badge badge-active">Online</span>' . ($sessions > 1 ? ' <small>' . $sessions . ' devices</small>' : '') : '<span class="badge">Offline</span>' ?></td>
                    <td class="actions">
                        <?php if ($canEdit && $locked): ?>
                            <form method="post" action="<?= e(url('/?tab=users')) ?>" class="inline-form" data-api="update_user" data-done="remove" data-confirm="Unlock this account now?">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <input type="hidden" name="unlock" value="1">
                                <button type="submit" class="btn btn-sm btn-outline">Unlock</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canEdit && $u['deactivation_reason'] === 'false_reports'): /* reactivation records an in-person review */ ?>
                            <a class="btn btn-sm btn-outline" href="<?= e(url('/?tab=users&id=' . $u['user_id'])) ?>">Review account</a>
                        <?php elseif ($canEdit): ?>
                            <form method="post" action="<?= e(url('/?tab=users')) ?>" class="inline-form" data-api="update_user"
                                  data-confirm="<?= $u['is_active'] ? 'Deactivate this account? They will be logged out and no longer able to log in.' : 'Reactivate this account?' ?>">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $u['is_active'] ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-secondary' : 'btn-success' ?>" data-toggle-active><?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="filter-empty" data-filter-empty hidden>No users match that search.</p>
    <p class="text-sm text-muted mt-1">Activity is the last authenticated request. A session counts as online until <?= SESSION_IDLE_MINUTES ?> minutes without one.</p>
<?php endif; ?>
