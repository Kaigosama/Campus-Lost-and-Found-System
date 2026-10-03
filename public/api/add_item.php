<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/add_item.php   (multipart/form-data with optional "photo" file, form fields, or JSON)
 *   type=lost   { item_name, category, date_lost,  location_lost,  description, confirm_duplicate? }           — students / faculty
 *   type=found  { item_name, category, date_found, location_found, description, storage_location, private_details }
 *               staff: logged at intake and public at once.
 *               students / faculty: a post without storage_location that stays "pending" until staff approve it.
 *               Administrators don't log found items.
 * A lost report passes, in order: login and an active account (current_user()), field validation, then, with the
 * account's row locked so parallel or double-clicked requests run one at a time: the submission limit
 * (REPORT_LIMIT_MAX per REPORT_LIMIT_WINDOW_MINUTES), an exact-duplicate check (always refused) and a
 * similar-report check (refused unless confirm_duplicate=1).
 * Response 201: { ok, type, id, url, item }     422: { ok:false, error, errors:{field: message} }
 *          429: { ok:false, error, retry_after }  rate limited
 *          409: { ok:false, error, duplicate:true, matches:[…] }  similar reports; resend with confirm_duplicate=1
 *          409: { ok:false, error, existing }    exact duplicate
 */
require_method('POST');
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}

$in   = json_input();
$type = ($in['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
$user = current_user();
$isIntake = $user['role'] === 'staff';

if ($type === 'lost' && !has_role('user')) {
    json_error(403, 'Only students and faculty file lost reports.');
}
if (!has_role(['staff', 'user'])) {
    json_error(403, 'Administrators do not log found items.');
}
if ($type === 'lost' && ($wait = report_limit_wait($user['user_id']))) {   // early answer; re-checked under the lock below
    log_event('report_rate_limited', $user);
    json_error(429, report_limit_message($wait), ['retry_after' => $wait]);
}

[$errors, $v] = validate_item_input($in, $type, $isIntake);
if ($errors) {
    json_error(422, 'Please complete all required fields before submitting.', ['errors' => $errors]);
}

$pdo = db();
$pdo->beginTransaction();
if ($type === 'lost') {
    // Serialises this account's submissions: a second request waits here until the first has committed its report.
    $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? FOR UPDATE')->execute([$user['user_id']]);
    if ($wait = report_limit_wait($user['user_id'])) {
        $pdo->rollBack();
        log_event('report_rate_limited', $user);
        json_error(429, report_limit_message($wait), ['retry_after' => $wait]);
    }
    $dupes = similar_reports($user['user_id'], $v);
    if ($dupes['exact']) {
        $pdo->rollBack();
        $existing = (int) $dupes['exact']['report_id'];
        log_event('report_duplicate_blocked', $user, ['report_id' => $existing, 'item_name' => $v['item_name']]);
        json_error(409, "You already submitted this exact report (#$existing). Check My Lost Reports instead of submitting it again.",
            ['existing' => $existing, 'url' => item_url('lost', $existing)]);
    }
    if ($dupes['similar'] && empty($in['confirm_duplicate'])) {
        $pdo->rollBack();
        json_error(409, 'You may already have a similar report. Please check your existing reports before submitting another one.', [
            'duplicate' => true,
            'matches'   => array_map(fn ($r) => [
                'report_id' => (int) $r['report_id'], 'item_name' => $r['item_name'], 'location_lost' => $r['location_lost'],
                'date_lost' => $r['date_lost'], 'status' => $r['status'], 'url' => item_url('lost', (int) $r['report_id']),
            ], $dupes['similar']),
        ]);
    }
}

$imageUrl = save_photo($_FILES['photo'] ?? null, $photoError);
if ($photoError) {
    $pdo->rollBack();
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
$pdo->prepare($sql)->execute($args);
$id = (int) $pdo->lastInsertId();
$pdo->commit();

$row = $type === 'found' ? find_found_item($id) : find_lost_report($id);
log_event(match (true) {
    $type === 'lost' => 'report_created',
    $isIntake        => 'found_item_logged',
    default          => 'post_submitted',
}, $user, ($type === 'lost' ? ['report_id' => $id] : ['item_id' => $id]) + ['item_name' => $v['item_name']]
    + ($type === 'lost' && !empty($dupes['similar']) ? ['similar_to' => implode(', ', array_map(fn ($r) => '#' . $r['report_id'], $dupes['similar']))] : []));

json_response(['ok' => true, 'type' => $type, 'id' => $id, 'url' => item_url($type, $id), 'item' => $row], 201);
