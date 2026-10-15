<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/categories.php   (JSON body or form fields)   administrators only
 *   action=create      { name, description? }        a new, active category. Names are unique ignoring case.
 *   action=set_active  { category_id, is_active: 0|1 } deactivate (hidden from new reports and found items; records
 *                                                     that use it keep it) or reactivate
 *   action=delete      { category_id }                only when no lost report or found item uses it; otherwise 409
 * Every change is written to the activity log.
 * Response: { ok, action, category_id, message }   422: { ok:false, error, errors:{field: message} }
 */
require_method('POST');
if (!is_admin()) {
    json_error(is_logged_in() ? 403 : 401, 'Only administrators can manage categories.');
}

$in     = json_input();
$action = (string) ($in['action'] ?? '');
$me     = current_user();

if ($action === 'create') {
    [$name, $nameError] = clean_category_name((string) ($in['name'] ?? ''));
    $description = trim((string) ($in['description'] ?? ''));
    $errors = array_filter(['name' => $nameError, 'description' => mb_strlen($description) > 255 ? 'Must be 255 characters or fewer.' : null]);
    if ($errors) {
        json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
    }
    try {
        db()->prepare('INSERT INTO categories (name, description, created_by) VALUES (?, ?, ?)')->execute([$name, $description ?: null, $me['user_id']]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        json_error(409, 'A category with that name already exists.', ['errors' => ['name' => 'A category with that name already exists (names ignore upper/lower case).']]);
    }
    $id = (int) db()->lastInsertId();
    log_event('category_created', null, ['category_id' => $id, 'name' => $name]);
    json_response(['ok' => true, 'action' => $action, 'category_id' => $id, 'message' => "Category “{$name}” added. It is now offered on report and found-item forms."], 201);
}

$category = find_category((int) ($in['category_id'] ?? 0));
if (!$category) {
    json_error(404, 'Category not found.');
}
$id   = $category['category_id'];
$name = $category['name'];

if ($action === 'set_active') {
    $active = (int) (bool) ($in['is_active'] ?? 0);
    db()->prepare('UPDATE categories SET is_active = ? WHERE category_id = ?')->execute([$active, $id]);
    log_event($active ? 'category_activated' : 'category_deactivated', null, ['category_id' => $id, 'name' => $name]);
    json_response(['ok' => true, 'action' => $action, 'category_id' => $id, 'message' => $active
        ? "Category “{$name}” is active and offered on new forms again."
        : "Category “{$name}” deactivated. It is no longer offered on new forms; existing records keep it."]);
}

if ($action === 'delete') {
    $usage = category_usage()[$id] ?? [];
    if (array_sum($usage)) {
        json_error(409, "“{$name}” is used by " . ($usage['lost'] ?? 0) . ' lost report(s) and ' . ($usage['found'] ?? 0)
            . ' found item(s), so it stays on record. Deactivate it instead to stop offering it.');
    }
    try {
        db()->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$id]);
    } catch (PDOException $e) {   // a record started using it a moment ago: the foreign key refuses the delete
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        json_error(409, "“{$name}” is now used by a record, so it can't be deleted. Deactivate it instead.");
    }
    log_event('category_deleted', null, ['category_id' => $id, 'name' => $name]);
    json_response(['ok' => true, 'action' => $action, 'category_id' => $id, 'message' => "Category “{$name}” deleted."]);
}

json_error(422, 'Unknown action.', ['allowed' => ['create', 'set_active', 'delete']]);
