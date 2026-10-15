<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/claims.php   (JSON body or form fields). The workflow is described in src/claims.php.
 *   action=create    { item_id, proof_description, report_id?, confirm_truth }   students / faculty, one claim per item
 *   action=review    { claim_id, decision: approve|reject, review_note }         staff; a pending claim, also one rejected before
 *                    approve → "approved" (for collection); reject → stays "pending", rejection added to its history
 *   action=verify    { claim_id, id_type, id_last4, id_result: verified|failed, id_notes? }
 *                    staff, at pickup of an approved claim. Only the last four characters of the ID number are stored.
 *   action=return    { claim_id, confirm_handover }                              staff, after a verified ID check: hand-over
 *   action=withdraw  { claim_id }                                                 claimant, while the claim was never reviewed
 * The reviewer, validator and every time stamp come from the session and the database, never from the request.
 * Response: { ok, action, claim_id, status, message, claim, rejected_claims? }   422: { ok:false, error, errors:{field: message} }
 *           409: the claim or item moved on (already reviewed, another claim approved, item returned or disposed)
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
$extra  = [];

/** The claim named in the request, for staff actions. */
function staff_claim(array $in): array
{
    if (!is_staff()) {
        json_error(403, 'Only Lost & Found staff can do this.');
    }
    return find_claim((int) ($in['claim_id'] ?? 0)) ?? json_error(404, 'Claim not found.');
}

/** Length check for a free-text field; adds to $errors. */
function text_rule(array &$errors, string $field, string $value, int $min, int $max): void
{
    $len = mb_strlen($value);
    if ($len === 0 && $min)  $errors[$field] = 'This field is required.';
    elseif ($len < $min)     $errors[$field] = "Must be at least $min characters.";
    elseif ($len > $max)     $errors[$field] = "Must be $max characters or fewer.";
}

try {
    if ($action === 'create') {
        // Staff review claims and never file them; admins do neither.
        if (!has_role('user')) {
            json_error(403, 'Only students and faculty can submit claims.');
        }
        $itemId = (int) ($in['item_id'] ?? 0);
        $item   = find_found_item($itemId);
        if (!$item || !is_public_item($item)) {
            json_error(404, 'This item is not available to claim.');
        }
        if ($item['user_id'] === $user['user_id']) {
            json_error(403, 'You posted this item, so you cannot claim it.');
        }
        if (where(where(all_claims(), 'item_id', $itemId), 'user_id', $user['user_id'])) {
            json_error(409, 'You have already submitted a claim for this item.');
        }
        $proof = trim((string) ($in['proof_description'] ?? ''));
        text_rule($errors, 'proof_description', $proof, 30, 2000);
        if (!$errors && count(preg_split('/\s+/', $proof)) < 8) {
            $errors['proof_description'] = 'Please give a little more detail (at least 8 words).';
        }
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
        log_event('claim_submitted', $user, ['claim_id' => $claimId, 'item_id' => $itemId, 'item_name' => $item['item_name']]);
        $message = 'Your claim was submitted. Staff will review it, usually within one working day.';

    } elseif ($action === 'review') {
        $claim    = staff_claim($in);
        $claimId  = $claim['claim_id'];
        $decision = (string) ($in['decision'] ?? '');
        $note     = trim((string) ($in['review_note'] ?? ''));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            json_error(422, 'Choose approve or reject.', ['errors' => ['decision' => 'Choose approve or reject.']]);
        }
        text_rule($errors, 'review_note', $note, 10, 1000);
        if ($errors) {
            json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
        }
        $message = review_claim($claim, $decision, $note, $user);

    } elseif ($action === 'verify') {
        $claim   = staff_claim($in);
        $claimId = $claim['claim_id'];
        $idType  = (string) ($in['id_type'] ?? '');
        $last4   = trim((string) ($in['id_last4'] ?? ''));
        $result  = (string) ($in['id_result'] ?? '');
        $notes   = trim((string) ($in['id_notes'] ?? ''));
        if (!isset(ID_TYPES[$idType])) {
            $errors['id_type'] = 'Choose the type of ID presented.';
        }
        if (!preg_match('/^[A-Za-z0-9]{4}$/', $last4)) {
            $errors['id_last4'] = 'Enter the last 4 letters or digits of the ID number.';
        }
        if (!in_array($result, ['verified', 'failed'], true)) {
            $errors['id_result'] = 'Choose whether the ID matched the claimant.';
        }
        text_rule($errors, 'id_notes', $notes, $idType === 'other' || $result === 'failed' ? 10 : 0, 1000);
        if ($errors) {
            json_error(422, 'Please fix the highlighted fields.', ['errors' => $errors]);
        }
        $message = verify_claim_identity($claim, $idType, $last4, $result === 'verified', $notes, $user);

    } elseif ($action === 'return') {
        $claim   = staff_claim($in);
        $claimId = $claim['claim_id'];
        if (empty($in['confirm_handover'])) {
            json_error(422, 'Please fix the highlighted fields.', ['errors' => ['confirm_handover' => 'Tick this box once the item is in the claimant\'s hands.']]);
        }
        [$message, $closed] = complete_return($claim, $user);
        $extra = ['rejected_claims' => $closed];

    } elseif ($action === 'withdraw') {
        $claimId = (int) ($in['claim_id'] ?? 0);
        $claim   = find_claim($claimId);
        if (!$claim || $claim['user_id'] !== $user['user_id']) {
            json_error(404, 'Claim not found.');
        }
        // A reviewed claim stays, so its review history is kept.
        $stmt = $pdo->prepare('DELETE FROM claims WHERE claim_id = ? AND status = "pending" AND NOT EXISTS (SELECT 1 FROM claim_reviews r WHERE r.claim_id = claims.claim_id)');
        $stmt->execute([$claimId]);
        if (!$stmt->rowCount()) {
            json_error(409, 'Only claims that have not been reviewed yet can be withdrawn.');
        }
        log_event('claim_withdrawn', $user, ['claim_id' => $claimId, 'item_id' => $claim['item_id']]);
        $message = "Claim #$claimId withdrawn.";

    } else {
        json_error(422, 'Unknown action.', ['allowed' => ['create', 'review', 'verify', 'return', 'withdraw']]);
    }
} catch (DomainException $e) {
    json_error(409, $e->getMessage());
}

$stmt = $pdo->prepare('SELECT * FROM claims WHERE claim_id = ?');
$stmt->execute([$claimId]);
$claim = $stmt->fetch() ?: null;

json_response(['ok' => true, 'action' => $action, 'claim_id' => $claimId, 'status' => $claim['status'] ?? 'withdrawn', 'message' => $message, 'claim' => $claim] + $extra,
    $action === 'create' ? 201 : 200);
