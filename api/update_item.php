<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * POST /api/update_item.php   (multipart/form-data with optional "photo" file, form fields, or JSON)
 *   type=lost   { id, item_name, category, date_lost,  location_lost,  description }                   — owner, while the report is open
 *   type=found  { id, item_name, category, date_found, location_found, description,
 *                 storage_location, private_details, status }                                          — staff
 *   Same field rules as add_item.php. A new photo replaces the old one; leave it out to keep it.
 * Response: { ok, type, id, url, item }     422: { ok:false, error, errors:{field: message} }
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
} else {
    $row = find_lost_report($id);
    if (!$row || $row['user_id'] !== $user['user_id']) {
        json_error(404, 'Report not found.');
    }
    if ($row['status'] !== 'open') {
        json_error(403, 'Only open reports can be edited.');
    }
}

[$errors, $v] = validate_item_input($in, $type);
if ($type === 'found') {
    $status = (string) ($in['status'] ?? $row['status']);
    if (!isset(FOUND_STATUSES[$status])) {
        $errors['status'] = 'Choose a valid status.';
    }
}
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
    $returnedAt = $status === 'returned' ? ($row['returned_at'] ?? date('Y-m-d H:i:s')) : $row['returned_at'];
    db()->prepare('UPDATE found_items SET item_name = ?, category = ?, description = ?, private_details = ?, location_found = ?,
                   storage_location = ?, date_found = ?, image_url = ?, status = ?, returned_at = ? WHERE item_id = ?')
        ->execute([$v['item_name'], $v['category'], $v['description'], $v['private_details'], $v['location_found'],
                   $v['storage_location'], $v['date_found'], $imageUrl, $status, $returnedAt, $id]);
} else {
    db()->prepare('UPDATE lost_reports SET item_name = ?, category = ?, description = ?, location_lost = ?, date_lost = ?, image_url = ?
                   WHERE report_id = ?')
        ->execute([$v['item_name'], $v['category'], $v['description'], $v['location_lost'], $v['date_lost'], $imageUrl, $id]);
}

$stmt = db()->prepare($type === 'found' ? 'SELECT * FROM found_items WHERE item_id = ?' : 'SELECT * FROM lost_reports WHERE report_id = ?');
$stmt->execute([$id]);

json_response(['ok' => true, 'type' => $type, 'id' => $id, 'url' => item_url($type, $id), 'item' => $stmt->fetch()]);
