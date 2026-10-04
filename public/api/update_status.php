<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/update_status.php   (JSON body or form fields)
 *   { type: 'found'|'lost', id, status, item_id? }
 *   found → staff only. Marking an item returned also closes the linked report and rejects other pending claims.
 *   lost  → staff may set any status (status=matched takes item_id, the found item it was matched to);
 *           the owner may only close their own open report.
 *   { type: 'found', id, moderation: 'approved'|'rejected', review_note?, storage_location? }
 *   → staff review of a student's found-item post. Approving needs a storage location; rejecting needs a reason.
 * Response: { ok, type, id, previous, status, message, rejected_claims: [claim_id…] }
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
        json_error(403, 'Only staff can change an item\'s status.');
    }
    if (!isset(FOUND_STATUSES[$status])) {
        json_error(422, 'Invalid status.', ['allowed' => array_keys(FOUND_STATUSES)]);
    }
    $row = find_found_item($id);
    if (!$row) {
        json_error(404, 'Item not found.');
    }

    $pdo->beginTransaction();
    $returnedAt = $status === 'returned' ? ($row['returned_at'] ?? date('Y-m-d H:i:s')) : $row['returned_at'];
    $pdo->prepare('UPDATE found_items SET status = ?, returned_at = ? WHERE item_id = ?')->execute([$status, $returnedAt, $id]);
    if ($status === 'returned') {
        $pdo->prepare('UPDATE lost_reports r JOIN claims c ON c.report_id = r.report_id
                       SET r.status = "closed", r.matched_item_id = c.item_id WHERE c.item_id = ? AND c.status = "approved"')->execute([$id]);
        $rejectedClaims = array_map('intval', array_column(where(where(all_claims(), 'item_id', $id), 'status', 'pending'), 'claim_id'));
        $pdo->prepare('UPDATE claims SET status = "rejected", reviewed_by = ?, reviewed_at = NOW(),
                       review_note = "The item was returned to another claimant." WHERE item_id = ? AND status = "pending"')->execute([$user['user_id'], $id]);
    }
    $pdo->commit();
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
