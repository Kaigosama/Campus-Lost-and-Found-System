<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/add_item.php   (multipart/form-data with optional "photo" file, form fields, or JSON)
 *   type=lost   { item_name, category, date_lost,  location_lost,  description }                                  — students / faculty, staff
 *   type=found  { item_name, category, date_found, location_found, description, storage_location, private_details }
 *               staff: logged at intake and public at once.
 *               students / faculty: a post without storage_location that stays "pending" until staff approve it.
 *               Administrators don't log found items.
 * Response 201: { ok, type, id, url, item }     422: { ok:false, error, errors:{field: message} }
 */
require_method('POST');
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}

$in   = json_input();
$type = ($in['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
$user = current_user();
$isIntake = $user['role'] === 'staff';

if ($type === 'found' && !has_role(['staff', 'user'])) {
    json_error(403, 'Administrators do not log found items. Staff handle intake.');
}

[$errors, $v] = validate_item_input($in, $type, $isIntake);
if ($errors) {
    json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
}
$imageUrl = save_photo($_FILES['photo'] ?? null, $photoError);
if ($photoError) {
    json_error(422, 'Please fix the highlighted fields.', ['errors' => ['photo' => $photoError]]);
}

if ($type === 'found') {
    $sql  = 'INSERT INTO found_items (user_id, item_name, category, description, private_details, location_found, storage_location, date_found, image_url, moderation_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $args = [$user['user_id'], $v['item_name'], $v['category'], $v['description'], $v['private_details'],
             $v['location_found'], $isIntake ? $v['storage_location'] : '', $v['date_found'], $imageUrl, $isIntake ? 'approved' : 'pending'];
} else {
    $sql  = 'INSERT INTO lost_reports (user_id, item_name, category, description, location_lost, date_lost, image_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)';
    $args = [$user['user_id'], $v['item_name'], $v['category'], $v['description'], $v['location_lost'], $v['date_lost'], $imageUrl];
}
db()->prepare($sql)->execute($args);
$id  = (int) db()->lastInsertId();
$row = $type === 'found' ? find_found_item($id) : find_lost_report($id);

json_response(['ok' => true, 'type' => $type, 'id' => $id, 'url' => item_url($type, $id), 'item' => $row], 201);
