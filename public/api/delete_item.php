<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/delete_item.php   { id }   — staff only
 *   Deletes a found item logged by mistake. Refused once the item was returned or a claim on it was approved;
 *   use status Disposed for real items instead. Pending and rejected claims on it are deleted with it, lost
 *   reports matched to it go back to "open", and an uploaded photo is removed. Recorded in the security log.
 * Response: { ok, id, message }
 */
require_method('POST');
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}
if (!is_staff()) {
    json_error(403, 'Only staff can delete found items.');
}

$in   = json_input();
$id   = (int) ($in['id'] ?? 0);
$item = find_found_item($id);
if (!$item) {
    json_error(404, 'Item not found.');
}
if (!can_delete_found_item($item)) {
    json_error(409, 'This item was returned or has an approved claim, so it stays on record. Set its status to Disposed instead.');
}

$pdo = db();
$pdo->beginTransaction();
$pdo->prepare('UPDATE lost_reports SET status = "open" WHERE matched_item_id = ? AND status = "matched"')->execute([$id]);
$pdo->prepare('DELETE FROM found_items WHERE item_id = ?')->execute([$id]);   // claims cascade; matched_item_id is set NULL
$pdo->commit();

// Sample photos under images/ are shared with other records; only this item's own upload is removed.
if (str_starts_with((string) $item['image_url'], 'uploads/')) {
    @unlink(APP_ROOT . '/public/' . $item['image_url']);
}
log_event('found_item_deleted', current_user(), ['item_id' => $id, 'item_name' => $item['item_name']]);

json_response(['ok' => true, 'id' => $id, 'message' => "Item #$id deleted."]);
