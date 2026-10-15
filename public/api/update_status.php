<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/update_status.php   (JSON body or form fields)
 *   { type: 'found', id, status: 'disposed', disposal_reason, disposal_notes?, confirm_disposal: 1 }
 *   → staff only: dispose of an item in storage held DISPOSAL_HOLD_DAYS or more, with no claim approved or awaiting
 *     review (see disposal_blocker()). Records who, when and why. "returned" is set only by the claim hand-over
 *     (api/claims.php action=return); returned and disposed are final and never change back.
 *   { type: 'lost', id, status, item_id? }
 *   → staff may set any status (status=matched takes item_id, the found item it was matched to);
 *     the owner may only close their own open report.
 *   { type: 'found', id, moderation: 'approved'|'rejected', review_note?, storage_location? }
 *   → staff review of a student's found-item post. Approving needs a storage location; rejecting needs a reason.
 * Response: { ok, type, id, previous, status, message, rejected_claims: [] }
 *   (moderation responses also carry storage_location)
 */
require_method('POST');
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}

$in     = json_input();
$type   = ($in['type'] ?? 'found') === 'lost' ? 'lost' : 'found';
$id     = (int) ($in['id'] ?? 0);
$status = (string) ($in['status'] ?? '');
$user   = current_user();
$pdo    = db();
$rejectedClaims = [];

if ($type === 'found' && isset($in['moderation'])) {
    if (!is_staff()) {
        json_error(403, 'Only staff can review posts.');
    }
    $row = find_found_item($id);
    if (!$row) {
        json_error(404, 'Item not found.');
    }
    if ($row['moderation_status'] !== 'pending') {
        json_error(409, 'This post has already been reviewed.');
    }
    $status  = (string) $in['moderation'];
    $note    = trim((string) ($in['review_note'] ?? ''));
    $storage = trim((string) ($in['storage_location'] ?? '')) ?: $row['storage_location'];
    $errors  = [];
    if (!in_array($status, ['approved', 'rejected'], true)) {
        json_error(422, 'Choose approve or reject.');
    }
    if ($status === 'rejected' && mb_strlen($note) < 10) {
        $errors['review_note'] = 'Give the poster a reason (at least 10 characters).';
    }
    if (mb_strlen($note) > 1000) {
        $errors['review_note'] = 'Must be 1000 characters or fewer.';
    }
    if ($status === 'approved' && !in_array($storage, STORAGE_LOCATIONS, true)) {
        $errors['storage_location'] = 'Record where the item is stored before approving it.';
    }
    if ($errors) {
        json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
    }
    $pdo->prepare('UPDATE found_items SET moderation_status = ?, moderated_by = ?, moderation_note = ?, moderated_at = NOW(), storage_location = ? WHERE item_id = ?')
        ->execute([$status, $user['user_id'], $note ?: null, $storage, $id]);
    log_event($status === 'approved' ? 'post_approved' : 'post_rejected', find_user($row['user_id']),
        ['item_id' => $id, 'item_name' => $row['item_name']] + ($note !== '' ? ['reason' => $note] : []));
    json_response([
        'ok' => true, 'type' => 'found', 'id' => $id, 'previous' => 'pending', 'status' => $status, 'storage_location' => $storage, 'rejected_claims' => [],
        'message' => $status === 'approved' ? "Post #$id approved. It is now listed publicly." : "Post #$id rejected. The poster can see your reason.",
    ]);
}

if ($type === 'found') {
    if (!is_staff()) {
        json_error(403, 'Only Lost & Found staff can dispose of items.');
    }
    $row = find_found_item($id);
    if (!$row) {
        json_error(404, 'Item not found.');
    }
    if ($status !== 'disposed') {
        json_error(422, $status === 'returned'
            ? 'Items are marked returned from the claim hand-over, after the claimant\'s ID is verified.'
            : 'Returned and disposed are final; an item\'s status can only be set to disposed here.', ['allowed' => ['disposed']]);
    }
    $reason = trim((string) ($in['disposal_reason'] ?? ''));
    $notes  = trim((string) ($in['disposal_notes'] ?? ''));
    $errors = [];
    if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
        $errors['disposal_reason'] = mb_strlen($reason) < 10 ? 'Give the reason for disposal (at least 10 characters).' : 'Must be 1000 characters or fewer.';
    }
    if (mb_strlen($notes) > 1000) {
        $errors['disposal_notes'] = 'Must be 1000 characters or fewer.';
    }
    if (empty($in['confirm_disposal'])) {
        $errors['confirm_disposal'] = 'Tick this box to confirm the disposal.';
    }
    if ($errors) {
        json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
    }
    try {
        $message = dispose_item($row, $reason, $notes, $user);
    } catch (DomainException $e) {
        json_error(409, $e->getMessage());
    }
    json_response(['ok' => true, 'type' => 'found', 'id' => $id, 'previous' => $row['status'], 'status' => 'disposed', 'message' => $message, 'rejected_claims' => []]);
} else {
    if (!isset(LOST_STATUSES[$status])) {
        json_error(422, 'Invalid status.', ['allowed' => array_keys(LOST_STATUSES)]);
    }
    $row = find_lost_report($id);
    if (!$row || $row['deleted_at'] !== null || (!is_staff() && $row['user_id'] !== $user['user_id'])) {
        json_error(404, 'Report not found.');
    }
    if (is_moderated_report($row)) {
        json_error(409, 'This report was closed by staff moderation (' . status_label($row['status']) . ') and can no longer change status.');
    }
    if (!is_staff() && !($row['status'] === 'open' && $status === 'closed')) {
        json_error(403, 'You can only close your own open report.');
    }
    $matchedItem = $row['matched_item_id'];
    if ($status === 'matched') {
        $matchedItem = (int) ($in['item_id'] ?? 0) ?: null;
        if ($matchedItem && !find_found_item($matchedItem)) {
            json_error(422, 'Choose a valid found item.', ['errors' => ['item_id' => 'Choose a valid found item.']]);
        }
    }
    $pdo->prepare('UPDATE lost_reports SET status = ?, matched_item_id = ? WHERE report_id = ?')->execute([$status, $matchedItem, $id]);
    if ($status !== $row['status']) {
        log_event('report_' . $status, find_user($row['user_id']), ['report_id' => $id, 'item_name' => $row['item_name']] + ($matchedItem ? ['item_id' => $matchedItem] : []));
    }
}

json_response([
    'ok'              => true,
    'type'            => $type,
    'id'              => $id,
    'previous'        => $row['status'],
    'status'          => $status,
    'message'         => ($type === 'found' ? 'Item' : 'Report') . " #$id is now “" . status_label($status) . '”.',
    'rejected_claims' => $rejectedClaims,
]);
