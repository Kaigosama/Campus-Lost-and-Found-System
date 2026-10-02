<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/update_user.php   (JSON body or form fields)   admins only, never on your own account
 *   { user_id, role? : user|staff|admin, is_active? : 0|1, unlock? : 1 }
 *   Regular admins manage students/faculty and staff. Only the master admin may grant or remove the admin role
 *   or change another admin. No one can grant master_admin here. Deactivating ends the user's sessions.
 * Response: { ok, user_id, role, is_active, locked, message }
 */
require_method('POST');
if (!is_admin()) {
    json_error(is_logged_in() ? 403 : 401, 'Only administrators can manage users.');
}

$in       = json_input();
$userId   = (int) ($in['user_id'] ?? 0);
$target   = find_user($userId);
$me       = current_user();
$isMaster = $me['role'] === 'master_admin';

if (!$target) {
    json_error(404, 'User not found.');
}
if ($userId === $me['user_id']) {
    json_error(403, 'You cannot change your own account.');
}
if (in_array($target['role'], ADMIN_ROLES, true) && !$isMaster) {
    json_error(403, 'Only the master administrator can change administrator accounts.');
}
if ($target['role'] === 'master_admin') {
    json_error(403, 'The master administrator account cannot be changed here.');
}

$role     = $target['role'];
$isActive = (int) $target['is_active'];
$changes  = [];

if (isset($in['role'])) {
    $assignable = $isMaster ? ['user', 'staff', 'admin'] : ['user', 'staff'];
    if (!in_array($in['role'], $assignable, true)) {
        json_error(422, 'You cannot assign that role.', ['allowed' => $assignable]);
    }
    if ($in['role'] !== $role) {
        $role      = $in['role'];
        $changes[] = 'role set to ' . ROLES[$role];
        log_event('role_changed', $target, ['from' => $target['role'], 'to' => $role, 'by' => $me['user_id']]);
    }
}
if (isset($in['is_active']) && (int) (bool) $in['is_active'] !== $isActive) {
    $isActive  = (int) (bool) $in['is_active'];
    $changes[] = $isActive ? 'account reactivated' : 'account deactivated';
    log_event($isActive ? 'account_reactivated' : 'account_deactivated', $target, ['by' => $me['user_id']]);
}
$unlock = !empty($in['unlock']) && account_locked($target);
if ($unlock) {
    $changes[] = 'account unlocked';
    log_event('account_unlocked', $target, ['by' => $me['user_id']]);
}
if (!$changes) {
    json_error(422, 'Nothing to change.');
}

db()->prepare('UPDATE users SET role = ?, is_active = ?' . ($unlock ? ', failed_login_attempts = 0, locked_until = NULL' : '') . ' WHERE user_id = ?')
    ->execute([$role, $isActive, $userId]);
if (!$isActive) {
    revoke_sessions($userId);
}

json_response([
    'ok'        => true,
    'user_id'   => $userId,
    'role'      => $role,
    'is_active' => $isActive,
    'locked'    => !$unlock && account_locked($target),
    'message'   => full_name($target) . ': ' . implode(', ', $changes) . '.',
]);
