<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * POST /api/update_status.php   (JSON body or form fields)
 *   { type: 'found'|'lost', id: <int>, status: <new status> }
 *
 *   found → staff only; status ∈ FOUND_STATUSES
 *   lost  → staff may set any LOST_STATUSES; the report's owner may only set 'closed' on an open report
 *
 * Response: { ok, mock, type, id, status, message }
 * Backend phase: replace the mock block with an UPDATE … SET status=?, updated_at=NOW() through db()
 * (and, for found→returned, set returned_at, close the linked report and reject other pending claims).
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Use POST.');
}
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}

$in     = json_input();
$type   = ($in['type'] ?? 'found') === 'lost' ? 'lost' : 'found';
$id     = (int) ($in['id'] ?? 0);
$status = (string) ($in['status'] ?? '');
$user   = current_user();

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
}

// ---- Mock update: report what would change; nothing is persisted yet ----
json_response([
    'ok'       => true,
    'mock'     => true,
    'type'     => $type,
    'id'       => $id,
    'previous' => $row['status'],
    'status'   => $status,
    'message'  => ($type === 'found' ? 'Item' : 'Report') . " #$id would move from “" . status_label($row['status']) . '” to “' . status_label($status) . '”. Not saved — no database connected yet.',
]);
