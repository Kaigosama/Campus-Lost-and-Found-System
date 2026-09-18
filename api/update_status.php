<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * POST /api/update_status.php   (JSON body or form fields)
 *   { type: 'found'|'lost', id, status }
 *   found → staff only. Marking an item returned also closes the linked report and rejects other pending claims.
 *   lost  → staff may set any status; the owner may only close their own open report.
 * Response: { ok, type, id, previous, status, message }
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
                       SET r.status = "closed" WHERE c.item_id = ? AND c.status = "approved"')->execute([$id]);
        $pdo->prepare('UPDATE claims SET status = "rejected", reviewed_by = ?, reviewed_at = NOW(),
                       review_note = "The item was returned to another claimant." WHERE item_id = ? AND status = "pending"')->execute([$user['user_id'], $id]);
    }
    $pdo->commit();
} else {
    if (!isset(LOST_STATUSES[$status])) {
        json_error(422, 'Invalid status.', ['allowed' => array_keys(LOST_STATUSES)]);
    }
    $row = find_lost_report($id);
    if (!$row || (!is_staff() && $row['user_id'] !== $user['user_id'])) {
        json_error(404, 'Report not found.');
    }
    if (!is_staff() && !($row['status'] === 'open' && $status === 'closed')) {
        json_error(403, 'You can only close your own open report.');
    }
    $pdo->prepare('UPDATE lost_reports SET status = ? WHERE report_id = ?')->execute([$status, $id]);
}

json_response([
    'ok'       => true,
    'type'     => $type,
    'id'       => $id,
    'previous' => $row['status'],
    'status'   => $status,
    'message'  => ($type === 'found' ? 'Item' : 'Report') . " #$id is now “" . status_label($status) . '”.',
]);
