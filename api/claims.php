<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * POST /api/claims.php   (JSON body or form fields)
 *   action=create    { item_id, proof_description, report_id?, confirm_truth }   any logged-in user, one claim per item
 *   action=review    { claim_id, decision: approve|reject, review_note }         staff
 *   action=withdraw  { claim_id }                                                 claimant, while the claim is pending
 * Response: { ok, action, claim_id, status, message, claim }   422: { ok:false, error, errors:{field: message} }
 */
require_method('POST');
if (!is_logged_in()) {
    json_error(401, 'Log in first.');
}

$in     = json_input();
$action = (string) ($in['action'] ?? '');
$user   = current_user();
$pdo    = db();
$errors = [];

if ($action === 'create') {
    $itemId = (int) ($in['item_id'] ?? 0);
    $item   = find_found_item($itemId);
    if (!$item || $item['status'] !== 'stored') {
        json_error(404, 'This item is not available to claim.');
    }
    if (where(where(all_claims(), 'item_id', $itemId), 'user_id', $user['user_id'])) {
        json_error(409, 'You have already submitted a claim for this item.');
    }

    $proof = trim((string) ($in['proof_description'] ?? ''));
    $len   = mb_strlen($proof);
    if ($len === 0)      $errors['proof_description'] = 'This field is required.';
    elseif ($len < 30)   $errors['proof_description'] = 'Must be at least 30 characters.';
    elseif ($len > 2000) $errors['proof_description'] = 'Must be 2000 characters or fewer.';
    elseif (count(preg_split('/\s+/', $proof)) < 8) $errors['proof_description'] = 'Please give a little more detail (at least 8 words).';

    $reportId = (int) ($in['report_id'] ?? 0) ?: null;
    if ($reportId) {
        $report = find_lost_report($reportId);
        if (!$report || $report['user_id'] !== $user['user_id'] || $report['status'] !== 'open') {
            $errors['report_id'] = 'Choose one of your open lost reports.';
        }
    }
    if (empty($in['confirm_truth'])) {
        $errors['confirm_truth'] = 'Please tick this box to continue.';
    }
    if ($errors) {
        json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
    }

    $pdo->prepare('INSERT INTO claims (item_id, user_id, report_id, proof_description, status, date_claimed) VALUES (?, ?, ?, ?, "pending", CURDATE())')
        ->execute([$itemId, $user['user_id'], $reportId, $proof]);
    $claimId = (int) $pdo->lastInsertId();
    $status  = 'pending';
    $message = 'Your claim was submitted. Staff will review it, usually within one working day.';

} elseif ($action === 'review') {
    if (!is_staff()) {
        json_error(403, 'Only staff can review claims.');
    }
    $claimId  = (int) ($in['claim_id'] ?? 0);
    $claim    = find_claim($claimId);
    $decision = (string) ($in['decision'] ?? '');
    $note     = trim((string) ($in['review_note'] ?? ''));
    if (!$claim) {
        json_error(404, 'Claim not found.');
    }
    if ($claim['status'] !== 'pending') {
        json_error(409, 'This claim has already been reviewed.');
    }
    if (!in_array($decision, ['approve', 'reject'], true)) {
        json_error(422, 'Choose approve or reject.', ['errors' => ['decision' => 'Choose approve or reject.']]);
    }
    $len = mb_strlen($note);
    if ($len === 0)      $errors['review_note'] = 'This field is required.';
    elseif ($len < 10)   $errors['review_note'] = 'Must be at least 10 characters.';
    elseif ($len > 1000) $errors['review_note'] = 'Must be 1000 characters or fewer.';
    if ($errors) {
        json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
    }

    $status = $decision === 'approve' ? 'approved' : 'rejected';
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE claims SET status = ?, reviewed_by = ?, review_note = ?, reviewed_at = NOW() WHERE claim_id = ?')
        ->execute([$status, $user['user_id'], $note, $claimId]);
    if ($status === 'approved' && $claim['report_id']) {
        $pdo->prepare('UPDATE lost_reports SET status = "matched", matched_item_id = ? WHERE report_id = ? AND status = "open"')
            ->execute([$claim['item_id'], $claim['report_id']]);
    }
    $pdo->commit();
    $message = "Claim #$claimId " . $status . '. The claimant can now see your note.';

} elseif ($action === 'withdraw') {
    $claimId = (int) ($in['claim_id'] ?? 0);
    $claim   = find_claim($claimId);
    if (!$claim || $claim['user_id'] !== $user['user_id']) {
        json_error(404, 'Claim not found.');
    }
    if ($claim['status'] !== 'pending') {
        json_error(409, 'Only pending claims can be withdrawn.');
    }
    $pdo->prepare('DELETE FROM claims WHERE claim_id = ?')->execute([$claimId]);
    $status  = 'withdrawn';
    $message = "Claim #$claimId withdrawn.";

} else {
    json_error(422, 'Unknown action.', ['allowed' => ['create', 'review', 'withdraw']]);
}

$stmt = $pdo->prepare('SELECT * FROM claims WHERE claim_id = ?');
$stmt->execute([$claimId]);

json_response(['ok' => true, 'action' => $action, 'claim_id' => $claimId, 'status' => $status, 'message' => $message, 'claim' => $stmt->fetch() ?: null], $action === 'create' ? 201 : 200);
