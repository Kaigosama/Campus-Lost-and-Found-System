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

$field = fn (string $key) => trim((string) ($in[$key] ?? ''));
$today = date('Y-m-d');

$rules = [
    'item_name'   => [1, 150],
    'description' => [$type === 'found' ? 15 : 20, 2000],
];
if ($type === 'found') {
    $rules += ['location_found' => [1, 150], 'storage_location' => [1, 150], 'private_details' => [15, 2000]];
    $dateKey = 'date_found';
} else {
    $rules += ['location_lost' => [1, 150]];
    $dateKey = 'date_lost';
}

$errors = [];
foreach ($rules as $key => [$min, $max]) {
    $len = mb_strlen($field($key));
    if ($len === 0)     $errors[$key] = 'This field is required.';
    elseif ($len < $min) $errors[$key] = "Must be at least $min characters.";
    elseif ($len > $max) $errors[$key] = "Must be $max characters or fewer.";
}
if (!in_array($field('category'), CATEGORIES, true)) {
    $errors['category'] = 'Choose a valid category.';
}
$date = $field($dateKey);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    $errors[$dateKey] = 'Enter a valid date.';
} elseif ($date > $today) {
    $errors[$dateKey] = 'Date cannot be in the future.';
}
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
    $args = [$user['user_id'], $field('item_name'), $field('category'), $field('description'), $field('private_details'),
             $field('location_found'), $field('storage_location'), $date, $imageUrl];
} else {
    $sql  = 'INSERT INTO lost_reports (user_id, item_name, category, description, location_lost, date_lost, image_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)';
    $args = [$user['user_id'], $field('item_name'), $field('category'), $field('description'), $field('location_lost'), $date, $imageUrl];
}
db()->prepare($sql)->execute($args);
$id  = (int) db()->lastInsertId();
$row = $type === 'found' ? find_found_item($id) : find_lost_report($id);

json_response(['ok' => true, 'type' => $type, 'id' => $id, 'url' => item_url($type, $id), 'item' => $row], 201);
