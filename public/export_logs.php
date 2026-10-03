<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once APP_ROOT . '/src/export.php';

/**
 * ?format=pdf                         Download the security logs (events and login sessions) as a printable PDF.
 * ?format=csv&table=events|sessions   One table as CSV, every field in full (a CSV file holds a single table).
 * Admins and the master admin only. The PDF cuts long cells; the CSVs don't. Each export is logged.
 */
require_role(ADMIN_ROLES);
$format = ($_GET['format'] ?? '') === 'pdf' ? 'pdf' : 'csv';
$table  = ($_GET['table'] ?? '') === 'sessions' ? 'sessions' : 'events';
$user   = current_user();

$account = fn (array $r) => $r['user_id'] ? full_name($r) : 'Unknown account';
$role    = fn (array $r) => $r['role'] ? (ROLES[$r['role']] ?? $r['role']) : '';

$events = [['When', 'Event', 'Account', 'Email', 'Role', 'Details', 'IP', 'Device']];
foreach (db()->query('SELECT e.*, u.first_name, u.last_name, u.role FROM security_events e LEFT JOIN users u ON u.user_id = e.user_id ORDER BY e.event_id DESC') as $ev) {
    $details = [];
    foreach (json_decode((string) $ev['metadata'], true) ?: [] as $k => $v) {
        $details[] = str_replace('_', ' ', (string) $k) . ': ' . (is_scalar($v) ? (string) $v : json_encode($v));
    }
    if ($ev['session_id']) $details[] = 'session #' . $ev['session_id'];
    $events[] = [format_datetime($ev['created_at']), str_replace('_', ' ', $ev['event_type']), $account($ev), $ev['email'] ?? '',
                 $role($ev), implode('; ', $details), $ev['ip_address'] ?? '', $ev['user_agent'] ?? ''];
}

$sessions = [['#', 'Account', 'Email', 'Role', 'Status', 'Logged in', 'Last activity', 'Ended / expires', 'IP', 'Device']];
foreach (db()->query('SELECT s.*, u.first_name, u.last_name, u.email, u.role FROM user_sessions s JOIN users u ON u.user_id = s.user_id ORDER BY s.session_id DESC') as $s) {
    $sessions[] = [$s['session_id'], $account($s), $s['email'], $role($s), ucwords(str_replace('_', ' ', $s['status'])),
                   format_datetime($s['created_at']), format_datetime($s['last_activity_at']),
                   $s['ended_at'] ? format_datetime($s['ended_at']) : 'expires ' . format_datetime($s['expires_at']),
                   $s['ip_address'] ?? '', $s['user_agent'] ?? ''];
}

$stamp    = date('Y-m-d_Hi');
$exported = 'Exported ' . format_datetime(date('Y-m-d H:i:s')) . ' by ' . full_name($user) . ' (' . (ROLES[$user['role']] ?? $user['role']) . ')';
if ($format === 'pdf') {
    // Column widths in characters; a landscape page holds about 186.
    $body = pdf_build(APP_NAME . ' security logs', $exported, [
        'Security events (' . (count($events) - 1) . ')' => ['widths' => [23, 21, 15, 27, 23, 37, 15, 25], 'rows' => $events],
        'Login sessions (' . (count($sessions) - 1) . ')' => ['widths' => [4, 15, 27, 23, 11, 23, 23, 31, 15, 14], 'rows' => $sessions],
    ]);
    $type = 'application/pdf';
    $name = "security-logs-$stamp.pdf";
} else {
    $body = csv_build($table === 'sessions' ? $sessions : $events);
    $type = 'text/csv; charset=utf-8';
    $name = "security-$table-$stamp.csv";
}

log_event('security_logs_exported', $user, ['format' => $format === 'csv' ? "csv ($table)" : 'pdf', 'events' => count($events) - 1, 'sessions' => count($sessions) - 1]);
header('Content-Type: ' . $type);
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . strlen($body));
header('Cache-Control: no-store');
echo $body;
