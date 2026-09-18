<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * POST /api/update_user.php   (JSON body or form fields)   admin only, never on your own account
 *   { user_id, role? : user|staff|admin, is_active? : 0|1 }
 * Response: { ok, user_id, role, is_active, message }
 */
require_method('POST');
if (!is_admin()) {
    json_error(is_logged_in() ? 403 : 401, 'Only administrators can manage users.');
}

$in     = json_input();
$userId = (int) ($in['user_id'] ?? 0);
$target = find_user($userId);
$me     = current_user();

if (!$target) {
    json_error(404, 'User not found.');
}
if ($userId === $me['user_id']) {
    json_error(403, 'You cannot change your own account.');
}

$role     = $target['role'];
$isActive = (int) $target['is_active'];
$changes  = [];

if (isset($in['role'])) {
    if (!isset(ROLES[$in['role']])) {
        json_error(422, 'Invalid role.', ['allowed' => array_keys(ROLES)]);
    }
    $role      = $in['role'];
    $changes[] = 'role set to ' . ROLES[$role];
}
if (isset($in['is_active'])) {
    $isActive  = (int) (bool) $in['is_active'];
    $changes[] = $isActive ? 'account reactivated' : 'account deactivated';
}
if (!$changes) {
    json_error(422, 'Nothing to change.');
}

db()->prepare('UPDATE users SET role = ?, is_active = ? WHERE user_id = ?')->execute([$role, $isActive, $userId]);

json_response([
    'ok'        => true,
    'user_id'   => $userId,
    'role'      => $role,
    'is_active' => $isActive,
    'message'   => full_name($target) . ': ' . implode(', ', $changes) . '.',
]);
