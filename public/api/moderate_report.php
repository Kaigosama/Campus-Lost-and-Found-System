<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/moderate_report.php   (JSON body or form fields)   staff only
 *   outcome for an open or matched lost report (see report_moderation_input()):
 *   { report_id, action: rejected,     reason_code (REPORT_REJECTION_REASONS), details, resubmission_allowed: yes|no }
 *   { report_id, action: false_report, reason_code (FALSE_REPORT_REASONS), details (the evidence, 30+ characters),
 *     confirm_false: 1, confirm_deactivation: 1 when this violation deactivates the account }
 *       A violation against the reporter: a warning, or deactivation at FALSE_REPORTS_TO_DEACTIVATE.
 *   { report_id, action: spam,         reason_code (SPAM_TYPES), details? }   hidden from lists, never a violation
 *   { report_id, action: delete, details }   remove a report already marked false or spam
 *       (soft delete: deleted_at / deleted_by / deletion_reason; the row stays for audits)
 * Every action is written to the activity log; the reviewer and time come from the session and the database.
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
$report = find_lost_report((int) ($in['report_id'] ?? 0));
if (!$report || $report['deleted_at'] !== null) {
    json_error(404, 'Report not found.');
}
if ($action === 'delete') {
    $details = trim((string) ($in['details'] ?? ''));
    $errors  = ($message = text_error($details, 10, 1000, 2)) ? ['details' => $message] : [];
} else {
    [$errors, $v] = report_moderation_input($in);
    $next = count(account_violations($report['reported_by'])) + 1;   // checked again under the lock in moderate_report()
    if ($action === 'false_report' && $next >= FALSE_REPORTS_TO_DEACTIVATE && empty($in['confirm_deactivation'])) {
        $errors['confirm_deactivation'] = 'This false report deactivates the account. Tick to confirm you understand.';
    }
}
if ($errors) {
    json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
}

try {
    $message = $action === 'delete'
        ? remove_report($report, $details, current_user())
        : moderate_report($report, $v, !empty($in['confirm_deactivation']), current_user());
} catch (DomainException $e) {
    json_error(409, $e->getMessage());
}

json_response(['ok' => true, 'report_id' => (int) $report['report_id'], 'status' => $action === 'delete' ? 'deleted' : $action, 'message' => $message]);
