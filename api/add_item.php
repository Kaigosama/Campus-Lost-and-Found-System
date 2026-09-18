<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * POST /api/add_item.php   (multipart/form-data with optional "photo" file, form fields, or JSON)
 *   type=lost   { item_name, category, date_lost,  location_lost,  description }                                  — any logged-in user
 *   type=found  { item_name, category, date_found, location_found, description, storage_location, private_details } — staff
 * Response 201: { ok, type, id, url, item }     422: { ok:false, error, errors:{field: message} }
 */
require_method('POST');
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}

$in   = json_input();
$type = ($in['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
$user = current_user();

if ($type === 'found' && !is_staff()) {
    json_error(403, 'Only staff can log found items.');
}

[$errors, $v] = validate_item_input($in, $type);
if ($errors) {
    json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
}
$imageUrl = save_photo($_FILES['photo'] ?? null, $photoError);
if ($photoError) {
    json_error(422, 'Please fix the highlighted fields.', ['errors' => ['photo' => $photoError]]);
}

if ($type === 'found') {
    $sql  = 'INSERT INTO found_items (user_id, item_name, category, description, private_details, location_found, storage_location, date_found, image_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $args = [$user['user_id'], $v['item_name'], $v['category'], $v['description'], $v['private_details'],
             $v['location_found'], $v['storage_location'], $v['date_found'], $imageUrl];
} else {
    $sql  = 'INSERT INTO lost_reports (user_id, item_name, category, description, location_lost, date_lost, image_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)';
    $args = [$user['user_id'], $v['item_name'], $v['category'], $v['description'], $v['location_lost'], $v['date_lost'], $imageUrl];
}
db()->prepare($sql)->execute($args);
$id  = (int) db()->lastInsertId();
$row = $type === 'found' ? find_found_item($id) : find_lost_report($id);

json_response(['ok' => true, 'type' => $type, 'id' => $id, 'url' => item_url($type, $id), 'item' => $row], 201);
