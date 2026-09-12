<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * POST /api/add_item.php   (JSON body or form fields)
 *   type=lost   { item_name, category, date_lost,  location_lost,  description }                                  — any logged-in user
 *   type=found  { item_name, category, date_found, location_found, description, storage_location, private_details } — staff
 *
 * Validates with the same rules as the forms (public/js/app.js) and, in the
 * mock phase, returns the row it WOULD have inserted (nothing is persisted).
 * Response 201: { ok, mock, message, item }     422: { ok:false, error, errors:{field: message} }
 * Backend phase: replace the "mock" block with an INSERT through db() and return the new id.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Use POST.');
}
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

// ---- Validation (mirrors the data-validate rules in app.js) ----
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

// ---- Mock insert: build the row exactly as schema.sql would store it ----
$now = date('Y-m-d H:i:s');
if ($type === 'found') {
    $row = [
        'item_id'          => max(array_keys(all_found_items())) + 1,
        'user_id'          => $user['user_id'],
        'item_name'        => $field('item_name'),
        'category'         => $field('category'),
        'description'      => $field('description'),
        'private_details'  => $field('private_details'),
        'date_found'       => $date,
        'location_found'   => $field('location_found'),
        'storage_location' => $field('storage_location'),
        'image_url'        => null,
        'status'           => 'stored',
        'returned_at'      => null,
        'created_at'       => $now,
        'updated_at'       => $now,
    ];
} else {
    $row = [
        'report_id'     => max(array_keys(all_lost_reports())) + 1,
        'user_id'       => $user['user_id'],
        'item_name'     => $field('item_name'),
        'category'      => $field('category'),
        'description'   => $field('description'),
        'date_lost'     => $date,
        'location_lost' => $field('location_lost'),
        'image_url'     => null,
        'status'        => 'open',
        'created_at'    => $now,
        'updated_at'    => $now,
    ];
}

json_response([
    'ok'      => true,
    'mock'    => true,
    'message' => ($type === 'found' ? 'Found item' : 'Lost report') . ' validated. Not saved — no database connected yet.',
    'type'    => $type,
    'item'    => $row,
], 201);
