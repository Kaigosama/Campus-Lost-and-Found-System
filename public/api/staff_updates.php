<?php
const PASSIVE_REQUEST = true;   // polled in the background; must not reset the idle timeout (see session_problem())
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * GET /api/staff_updates.php   — staff only, polled by app.js every 15 seconds on staff pages
 * Response: { ok, latest_claim, latest_post, pending_claims, pending_posts }
 *   latest_* are the newest pending claim id and pending student-post id (0 when none). The page compares them
 *   with its first answer and offers a refresh when something new arrives.
 */
require_method('GET');
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}
if (!is_staff()) {
    json_error(403, 'Staff only.');
}

$row = db()->query("SELECT
        (SELECT COALESCE(MAX(claim_id), 0) FROM claims WHERE status = 'pending')                 AS latest_claim,
        (SELECT COUNT(*) FROM claims WHERE status = 'pending')                                     AS pending_claims,
        (SELECT COALESCE(MAX(item_id), 0) FROM found_items WHERE moderation_status = 'pending')  AS latest_post,
        (SELECT COUNT(*) FROM found_items WHERE moderation_status = 'pending')                     AS pending_posts")->fetch();

header('Cache-Control: no-store');
json_response(['ok' => true] + array_map('intval', $row));
