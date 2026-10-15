<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/update_item.php   (multipart/form-data with optional "photo" file, form fields, or JSON)
 *   type=lost   { id, item_name, category_id, category_detail?, date_lost,  location_lost,  description }  — owner, while the report is open
 *   type=found  { id, item_name, category_id, category_detail?, date_found, location_found, description,
 *                 storage_location, private_details }                                                   — staff, while the item is in storage
 *   Same field rules as add_item.php; a record keeps its category even after an admin deactivates it.
 *   A new photo replaces the old one; leave it out to keep it. An item's status changes only through the claim
 *   hand-over (api/claims.php) or disposal (api/update_status.php). Returned and disposed items can't be edited.
 * Response: { ok, type, id, url, item }     422: { ok:false, error, errors:{field: message} }   409: final item
 */
require_method('POST');
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}

$in   = json_input();
$type = ($in['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
$id   = (int) ($in['id'] ?? 0);
$user = current_user();

if ($type === 'found') {
    if (!is_staff()) {
        json_error(403, 'Only staff can edit found items.');
    }
    $row = find_found_item($id);
    if (!$row) {
        json_error(404, 'Item not found.');
    }
    if (is_final_item($row)) {
        json_error(409, 'This item was ' . strtolower(status_label($row['status'])) . ', so its record can no longer be edited.');
    }
} else {
    $row = find_lost_report($id);
    if (!$row || $row['reported_by'] !== $user['user_id']) {
        json_error(404, 'Report not found.');
    }
    if ($row['status'] !== 'open') {
        json_error(403, 'Only open reports can be edited.');
    }
}

[$errors, $v] = validate_item_input($in, $type, true, (int) $row['category_id']);
if ($errors) {
    json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
}
$imageUrl = save_photo($_FILES['photo'] ?? null, $photoError);
if ($photoError) {
    json_error(422, 'Please fix the highlighted fields.', ['errors' => ['photo' => $photoError]]);
}
if ($imageUrl && $row['image_url'] && is_file(APP_ROOT . '/public/' . $row['image_url'])) {
    @unlink(APP_ROOT . '/public/' . $row['image_url']);
}
$imageUrl = $imageUrl ?? $row['image_url'];

if ($type === 'found') {
    // status = 'stored' in the WHERE: an item returned or disposed since the check above stays untouched.
    $stmt = db()->prepare('UPDATE found_items SET item_name = ?, category_id = ?, category_detail = ?, description = ?, private_details = ?,
                           location_found = ?, storage_location = ?, date_found = ?, image_url = ? WHERE item_id = ? AND status = "stored"');
    $stmt->execute([$v['item_name'], $v['category_id'], $v['category_detail'], $v['description'], $v['private_details'], $v['location_found'],
                    $v['storage_location'], $v['date_found'], $imageUrl, $id]);
    if ($stmt->rowCount()) {
        log_event('found_item_edited', null, ['item_id' => $id, 'item_name' => $v['item_name']]);
    }
} else {
    db()->prepare('UPDATE lost_reports SET item_name = ?, category_id = ?, category_detail = ?, description = ?, location_lost = ?, date_lost = ?, image_url = ?
                   WHERE report_id = ?')
        ->execute([$v['item_name'], $v['category_id'], $v['category_detail'], $v['description'], $v['location_lost'], $v['date_lost'], $imageUrl, $id]);
    log_event('report_edited', $user, ['report_id' => $id, 'item_name' => $v['item_name']]);
}

$stmt = db()->prepare($type === 'found' ? 'SELECT * FROM found_items WHERE item_id = ?' : 'SELECT * FROM lost_reports WHERE report_id = ?');
$stmt->execute([$id]);

json_response(['ok' => true, 'type' => $type, 'id' => $id, 'url' => item_url($type, $id), 'item' => $stmt->fetch()]);
