<?php
declare(strict_types=1);

/**
 * Claim workflow, hand-over and disposal. Every step runs on the server in a transaction with the item row locked,
 * so two staff members can't approve two claims on one item, return an item twice, or dispose of an item that is
 * being claimed.
 *
 *   pending ──approve──▶ approved ──identity verified──▶ verified ──hand-over──▶ returned (item: returned)
 *      ▲  └─reject: stays pending, review outcome "rejected" (claim_reviews keeps every decision)
 *      └──── staff may review a rejected claim again; that adds a new history row, never replaces one
 *
 * An item in storage with no claim approved or awaiting review may be disposed of (item: disposed).
 */

function claim_reviews_of(int $claimId): array       { return where(table('claim_reviews'), 'claim_id', $claimId); }
function claim_verifications_of(int $claimId): array { return where(table('claim_verifications'), 'claim_id', $claimId); }

/** Pending and never reviewed: what the Claims queue waits on. */
function awaiting_review(array $claim): bool
{
    return $claim['status'] === 'pending' && $claim['review_outcome'] === null;
}

/** Pending (main status) with "rejected" as its current review outcome. */
function is_rejected_claim(array $claim): bool
{
    return $claim['status'] === 'pending' && $claim['review_outcome'] === 'rejected';
}

/** Main status badge, plus the "Rejected" review outcome of a rejected pending claim. */
function claim_badges(array $claim, ?string $for = null): string
{
    return status_badge($claim['status'], $for)
        . (is_rejected_claim($claim) ? ' <span class="badge badge-rejected" title="Current review outcome">Review outcome: Rejected</span>' : '');
}

/** Runs $work inside a transaction, rolling back on any error. */
function in_transaction(callable $work): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** The claim's main status and latest review outcome, read inside a transaction with both rows locked. */
function lock_claim(PDO $pdo, array $claim): array
{
    $pdo->prepare('SELECT item_id FROM found_items WHERE item_id = ? FOR UPDATE')->execute([$claim['item_id']]);
    $stmt = $pdo->prepare('SELECT c.status, (SELECT outcome FROM claim_reviews r WHERE r.claim_id = c.claim_id ORDER BY review_id DESC LIMIT 1) AS outcome
                           FROM claims c WHERE c.claim_id = ? FOR UPDATE');
    $stmt->execute([$claim['claim_id']]);
    return $stmt->fetch() ?: throw new DomainException('Claim not found.');
}

/**
 * Staff decision on a pending claim (also one rejected before): 'approve' makes it "approved for collection",
 * 'reject' keeps it pending with a new rejection in its history. $note is shown to the claimant.
 */
function review_claim(array $claim, string $decision, string $note, array $staff): string
{
    $outcome = $decision === 'approve' ? 'approved' : 'rejected';
    $events  = in_transaction(function (PDO $pdo) use ($claim, $outcome, $note, $staff) {
        $now = lock_claim($pdo, $claim);
        if ($now['status'] !== 'pending') {
            throw new DomainException('This claim is no longer waiting for a decision.');
        }
        if ($outcome === 'approved') {
            $item = $pdo->prepare('SELECT status FROM found_items WHERE item_id = ?');
            $item->execute([$claim['item_id']]);
            if ($item->fetchColumn() !== 'stored') {
                throw new DomainException('The item is no longer in storage, so this claim can\'t be approved.');
            }
            $other = $pdo->prepare("SELECT claim_id FROM claims WHERE item_id = ? AND claim_id <> ? AND status IN ('approved', 'verified')");
            $other->execute([$claim['item_id'], $claim['claim_id']]);
            if ($otherId = $other->fetchColumn()) {
                throw new DomainException("Claim #$otherId on this item is already approved for collection. Reject this one, or wait until that claim is settled.");
            }
            $pdo->prepare("UPDATE claims SET status = 'approved' WHERE claim_id = ?")->execute([$claim['claim_id']]);
            if ($claim['report_id']) {
                $pdo->prepare('UPDATE lost_reports SET status = "matched", matched_item_id = ? WHERE report_id = ? AND status = "open"')
                    ->execute([$claim['item_id'], $claim['report_id']]);
            }
        }
        $pdo->prepare('INSERT INTO claim_reviews (claim_id, reviewer_id, outcome, previous_outcome, reason) VALUES (?, ?, ?, ?, ?)')
            ->execute([$claim['claim_id'], $staff['user_id'], $outcome, $now['outcome'], $note]);
        $count = $pdo->prepare('SELECT COUNT(*) FROM claim_reviews WHERE claim_id = ? AND outcome = "rejected"');
        $count->execute([$claim['claim_id']]);
        return (int) $count->fetchColumn();
    });
    log_event('claim_' . $outcome, find_user($claim['claimant_id']), ['claim_id' => $claim['claim_id'], 'item_id' => $claim['item_id'], 'note' => $note]
        + ($outcome === 'rejected' ? ['rejections' => $events] : []));
    $id = $claim['claim_id'];
    return $outcome === 'approved'
        ? "Claim #$id approved for collection. Verify the claimant's ID when they come to the desk."
        : "Claim #$id rejected. It stays pending, with $events rejection" . ($events === 1 ? '' : 's') . ' in its history.';
}

/**
 * Records the ID check at pickup for an approved claim. Only the ID type and the last four characters of its
 * number are stored ("••••1234"); the time comes from the database and the validator is the signed-in staff member.
 * A failed check leaves the claim approved; a passed one makes it "verified", ready to hand over.
 */
function verify_claim_identity(array $claim, string $idType, string $last4, bool $passed, string $notes, array $staff): string
{
    in_transaction(function (PDO $pdo) use ($claim, $idType, $last4, $passed, $notes, $staff) {
        if (lock_claim($pdo, $claim)['status'] !== 'approved') {
            throw new DomainException('Only a claim approved for collection can have its ID checked.');
        }
        $pdo->prepare('INSERT INTO claim_verifications (claim_id, id_type, id_reference, result, notes, verified_by) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$claim['claim_id'], $idType, '••••' . strtoupper($last4), $passed ? 'verified' : 'failed', $notes ?: null, $staff['user_id']]);
        if ($passed) {
            $pdo->prepare("UPDATE claims SET status = 'verified' WHERE claim_id = ?")->execute([$claim['claim_id']]);
        }
    });
    // The ID number is never written to the activity log.
    log_event($passed ? 'claim_identity_verified' : 'claim_identity_failed', find_user($claim['claimant_id']),
        ['claim_id' => $claim['claim_id'], 'item_id' => $claim['item_id'], 'id_type' => ID_TYPES[$idType]]);
    return $passed
        ? "Identity verified for claim #{$claim['claim_id']}. Hand the item over, then mark it as returned."
        : "ID check recorded as failed for claim #{$claim['claim_id']}. The claim stays approved; don't release the item.";
}

/**
 * Hand-over of a verified claim: in one transaction the claim becomes "returned", the item "returned" with the time,
 * staff member and claim, the claimant's linked lost report is closed, and every other open claim on the item gets
 * a rejection ("returned to another claimant") while staying pending. Returns [message, ids of those claims].
 */
function complete_return(array $claim, array $staff): array
{
    $closed = in_transaction(function (PDO $pdo) use ($claim, $staff) {
        if (lock_claim($pdo, $claim)['status'] !== 'verified') {
            throw new DomainException('Verify the claimant\'s ID before marking the item as returned.');
        }
        $stmt = $pdo->prepare("UPDATE found_items SET status = 'returned', returned_at = NOW(), returned_by = ?, return_claim_id = ?
                               WHERE item_id = ? AND status = 'stored'");
        $stmt->execute([$staff['user_id'], $claim['claim_id'], $claim['item_id']]);
        if (!$stmt->rowCount()) {
            throw new DomainException('This item is no longer in storage.');
        }
        $pdo->prepare("UPDATE claims SET status = 'returned' WHERE claim_id = ?")->execute([$claim['claim_id']]);
        if ($claim['report_id']) {
            $pdo->prepare("UPDATE lost_reports SET status = 'closed', matched_item_id = ? WHERE report_id = ? AND deleted_at IS NULL AND status IN ('open', 'matched')")
                ->execute([$claim['item_id'], $claim['report_id']]);
        }
        $others = $pdo->prepare("SELECT c.claim_id, (SELECT outcome FROM claim_reviews r WHERE r.claim_id = c.claim_id ORDER BY review_id DESC LIMIT 1) AS outcome
                                 FROM claims c WHERE c.item_id = ? AND c.claim_id <> ? AND c.status <> 'returned' FOR UPDATE");
        $others->execute([$claim['item_id'], $claim['claim_id']]);
        $closed = [];
        foreach ($others->fetchAll() as $other) {
            if ($other['outcome'] === 'rejected') {
                continue;   // already rejected: its history says so
            }
            $pdo->prepare("UPDATE claims SET status = 'pending' WHERE claim_id = ?")->execute([$other['claim_id']]);
            $pdo->prepare('INSERT INTO claim_reviews (claim_id, reviewer_id, outcome, previous_outcome, reason) VALUES (?, ?, "rejected", ?, ?)')
                ->execute([$other['claim_id'], $staff['user_id'], $other['outcome'], 'The item was returned to another claimant.']);
            $closed[] = (int) $other['claim_id'];
        }
        return $closed;
    });
    $item = find_found_item($claim['item_id']);
    log_event('item_returned', find_user($claim['claimant_id']), ['claim_id' => $claim['claim_id'], 'item_id' => $claim['item_id'], 'item_name' => $item['item_name']]);
    foreach ($closed as $otherId) {
        log_event('claim_rejected', find_user(find_claim($otherId)['user_id']), ['claim_id' => $otherId, 'item_id' => $claim['item_id'], 'note' => 'The item was returned to another claimant.']);
    }
    return ["Item #{$claim['item_id']} returned to the claimant of claim #{$claim['claim_id']}." . ($closed ? ' Other open claims on it were rejected.' : ''), $closed];
}

/** Why $item can't be disposed of now (a message for staff), or null when it can. */
function disposal_blocker(array $item): ?string
{
    if ($item['status'] !== 'stored') {
        return 'Only items in storage can be disposed of.';
    }
    if ($item['moderation_status'] !== 'approved') {
        return 'Review this found-item post first: only items accepted into storage can be disposed of.';
    }
    $held = (int) floor((time() - strtotime($item['date_found'])) / 86400);
    if ($held < DISPOSAL_HOLD_DAYS) {
        return 'Items are held for at least ' . DISPOSAL_HOLD_DAYS . " days before disposal. This one has been held for $held day" . ($held === 1 ? '' : 's') . '.';
    }
    $claims = where(all_claims(), 'item_id', $item['item_id']);
    if (array_filter($claims, fn ($c) => in_array($c['status'], ['approved', 'verified'], true))) {
        return 'A claim on this item is approved for collection. Settle it before disposing of the item.';
    }
    if ($waiting = count(array_filter($claims, 'awaiting_review'))) {
        return "$waiting claim" . ($waiting === 1 ? ' is' : 's are') . ' still awaiting review. Approve or reject ' . ($waiting === 1 ? 'it' : 'them') . ' first.';
    }
    return null;
}

/** Marks an eligible item disposed of, with who, when and why. Lost reports matched to it are reopened. */
function dispose_item(array $item, string $reason, string $notes, array $staff): string
{
    if ($blocker = disposal_blocker($item)) {
        throw new DomainException($blocker);
    }
    in_transaction(function (PDO $pdo) use ($item, $reason, $notes, $staff) {
        $pdo->prepare('SELECT item_id FROM found_items WHERE item_id = ? FOR UPDATE')->execute([$item['item_id']]);
        // Checked again under the lock, in case a claim arrived or was approved since the page loaded.
        $busy = $pdo->prepare("SELECT COUNT(*) FROM claims c WHERE c.item_id = ? AND (c.status IN ('approved', 'verified')
                               OR (c.status = 'pending' AND NOT EXISTS (SELECT 1 FROM claim_reviews r WHERE r.claim_id = c.claim_id)))");
        $busy->execute([$item['item_id']]);
        if ($busy->fetchColumn()) {
            throw new DomainException('A claim on this item was just submitted or approved. Reload the page.');
        }
        $stmt = $pdo->prepare("UPDATE found_items SET status = 'disposed', disposed_at = NOW(), disposed_by = ?, disposal_reason = ?, disposal_notes = ?
                               WHERE item_id = ? AND status = 'stored'");
        $stmt->execute([$staff['user_id'], $reason, $notes ?: null, $item['item_id']]);
        if (!$stmt->rowCount()) {
            throw new DomainException('This item is no longer in storage.');
        }
        $pdo->prepare('UPDATE lost_reports SET status = "open", matched_item_id = NULL WHERE matched_item_id = ? AND status = "matched"')->execute([$item['item_id']]);
    });
    log_event('found_item_disposed', null, ['item_id' => $item['item_id'], 'item_name' => $item['item_name'], 'reason' => $reason]);
    return "Item #{$item['item_id']} marked as disposed. It is listed under Disposed Items.";
}

/**
 * Returned items, newest first, from the same records the item pages use (found_items + claims +
 * claim_verifications), for the dashboard and the Claims section. Each row: item, claim, claimant, returned_by,
 * verification (the passed ID check) — the last four are null for items returned before return records existed.
 */
function successful_returns(?int $limit = null): array
{
    $rows = [];
    foreach (newest_first(where(all_found_items(), 'status', 'returned'), 'returned_at') as $item) {
        $claim = $item['return_claim_id'] ? find_claim((int) $item['return_claim_id']) : null;
        $rows[] = [
            'item'         => $item,
            'claim'        => $claim,
            'claimant'     => $claim ? find_user($claim['claimant_id']) : null,
            'returned_by'  => find_user($item['returned_by']),
            'verification' => $claim ? (where(claim_verifications_of($claim['claim_id']), 'result', 'verified')[0] ?? null) : null,
        ];
    }
    return $limit ? array_slice($rows, 0, $limit) : $rows;
}

/**
 * Items with at least one rejected (still pending) claim, one entry per item however many claims were rejected:
 * item, claims (the rejected ones, each still its own record), rejections (history rows across them), latest.
 */
function rejected_claim_groups(): array
{
    $groups = [];
    foreach (array_filter(all_claims(), 'is_rejected_claim') as $claim) {
        $group = &$groups[$claim['item_id']];
        $group['item']     = find_found_item($claim['item_id']);
        $group['claims'][] = $claim;
        $rejections = where(claim_reviews_of($claim['claim_id']), 'outcome', 'rejected');
        $group['rejections'] = ($group['rejections'] ?? 0) + count($rejections);
        $group['latest'] = max($group['latest'] ?? '', $claim['reviewed_at']);
        unset($group);
    }
    uasort($groups, fn ($a, $b) => strcmp($b['latest'], $a['latest']));
    return $groups;
}
