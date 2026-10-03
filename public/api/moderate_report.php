<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/moderate_report.php   (JSON body or form fields)   staff only
 *   { report_id, action: rejected|false_report|spam, reason }   outcome for an open or matched lost report
 *       false_report is a violation against the reporter: a warning, or deactivation at FALSE_REPORTS_TO_DEACTIVATE.
 *   { report_id, action: delete, reason }                        remove a report already marked false or spam
 *       (soft delete: deleted_at / deleted_by / deletion_reason; the row stays for audits)
 * Every action needs a reason of 10–1000 characters and is written to the activity log.
 * Response: { ok, report_id, status, message }
 */
require_method('POST');
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}
if (!is_staff()) {
    json_error(403, 'Only Lost & Found staff can moderate lost reports.');
}

$in     = json_input();
$action = (string) ($in['action'] ?? '');
$reason = trim((string) ($in['reason'] ?? ''));
$report = find_lost_report((int) ($in['report_id'] ?? 0));
if (!$report || $report['deleted_at'] !== null) {
    json_error(404, 'Report not found.');
}
if ($action !== 'delete' && !isset(REPORT_MODERATION_STATUSES[$action])) {
    json_error(422, 'Choose an action.', ['allowed' => [...array_keys(REPORT_MODERATION_STATUSES), 'delete']]);
}
$len = mb_strlen($reason);
if ($len < 10 || $len > 1000) {
    json_error(422, 'Please fix the highlighted fields.', ['errors' => ['reason' => $len < 10 ? 'Give a reason (at least 10 characters).' : 'Must be 1000 characters or fewer.']]);
}

try {
    $message = $action === 'delete' ? remove_report($report, $reason, current_user()) : moderate_report($report, $action, $reason, current_user());
} catch (DomainException $e) {
    json_error(409, $e->getMessage());
}

json_response(['ok' => true, 'report_id' => (int) $report['report_id'], 'status' => $action === 'delete' ? 'deleted' : $action, 'message' => $message]);
