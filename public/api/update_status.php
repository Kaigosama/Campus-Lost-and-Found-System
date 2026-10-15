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
 *   { type: 'found', id, moderation: 'approved', storage_location, received_at_office: yes, item_condition, internal_note? }
 *   { type: 'found', id, moderation: 'rejected', reason_code (POST_REJECTION_REASONS), details, resubmission_allowed: yes|no,
 *     required_corrections (when yes) }
 *   → staff review of a student's found-item post (see post_review_input()).
 * Response: { ok, type, id, status, message }
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
    [$errors, $v] = post_review_input($in, $row);
    if ($errors) {
        json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
    }
    $status = $v['moderation_status'];
    $stmt   = $pdo->prepare('UPDATE found_items SET moderation_status = ?, storage_location = ?, moderation_note = ?, moderation_reason_code = ?,
                                    resubmission_allowed = ?, required_corrections = ?, item_condition = ?, moderation_internal_note = ?,
                                    moderated_by = ?, moderated_at = NOW()
                             WHERE item_id = ? AND moderation_status = "pending"');
    $stmt->execute([$status, $v['storage_location'], $v['moderation_note'], $v['moderation_reason_code'], $v['resubmission_allowed'],
                    $v['required_corrections'], $v['item_condition'], $v['moderation_internal_note'], $user['user_id'], $id]);
    if (!$stmt->rowCount()) {
        json_error(409, 'This post has already been reviewed.');
    }
    log_event($status === 'approved' ? 'post_approved' : 'post_rejected', find_user($row['added_by']), ['item_id' => $id, 'item_name' => $row['item_name']]
        + ($status === 'approved' ? ['item_condition' => $v['item_condition']] : ['reason_code' => $v['moderation_reason_code']]));
    json_response([
        'ok' => true, 'type' => 'found', 'id' => $id, 'status' => $status, 'storage_location' => $v['storage_location'],
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
    json_response(['ok' => true, 'type' => 'found', 'id' => $id, 'status' => 'disposed', 'message' => $message]);
} else {
    if (!isset(LOST_STATUSES[$status])) {
        json_error(422, 'Invalid status.', ['allowed' => array_keys(LOST_STATUSES)]);
    }
    $row = find_lost_report($id);
    if (!$row || $row['deleted_at'] !== null || (!is_staff() && $row['reported_by'] !== $user['user_id'])) {
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
        log_event('report_' . $status, find_user($row['reported_by']), ['report_id' => $id, 'item_name' => $row['item_name']] + ($matchedItem ? ['item_id' => $matchedItem] : []));
    }
}

json_response([
    'ok'      => true,
    'type'    => $type,
    'id'      => $id,
    'status'  => $status,
    'message' => "Report #$id is now “" . status_label($status) . '”.',
]);
