<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * GET /api/get_items.php
 *   type=found (default) | lost
 *   q=keyword  category=…  status=…  from=YYYY-MM-DD  to=YYYY-MM-DD  limit=1..100 (default 50)
 *
 * Visibility mirrors the pages:
 *   found → everyone sees items in storage, public fields only; staff see every status and the private fields
 *   lost  → login required; users see their own reports, staff see all
 *
 * Response: { ok, type, count, items: [...] }
 * Backend phase: replace the all_*() calls with SELECT … WHERE queries through db().
 */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error(405, 'Use GET.');
}

$type     = ($_GET['type'] ?? 'found') === 'lost' ? 'lost' : 'found';
$q        = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
$status   = $_GET['status'] ?? '';
$from     = $_GET['from'] ?? '';
$to       = $_GET['to'] ?? '';
$limit    = min(100, max(1, (int) ($_GET['limit'] ?? 50)));

if ($type === 'found') {
    if (!is_staff()) {
        $status = 'stored'; // the public may only see what is claimable
    }
    $rows = search_rows(array_values(all_found_items()), $q, ['status' => $status, 'category' => $category]);
    $dateColumn = 'date_found';
    if (!is_staff()) {
        $rows = array_map(fn ($i) => array_diff_key($i, ['storage_location' => 1, 'private_details' => 1]), $rows);
    }
} else {
    if (!is_logged_in()) {
        json_error(401, 'Log in to view lost reports.');
    }
    $rows = search_rows(array_values(all_lost_reports()), $q, ['status' => $status, 'category' => $category]);
    if (!is_staff()) {
        $rows = where($rows, 'user_id', current_user()['user_id']);
    }
    $dateColumn = 'date_lost';
}

if ($from) $rows = array_values(array_filter($rows, fn ($r) => $r[$dateColumn] >= $from));
if ($to)   $rows = array_values(array_filter($rows, fn ($r) => $r[$dateColumn] <= $to));
$rows = array_slice(newest_first($rows, $dateColumn), 0, $limit);

json_response(['ok' => true, 'type' => $type, 'count' => count($rows), 'items' => $rows]);
