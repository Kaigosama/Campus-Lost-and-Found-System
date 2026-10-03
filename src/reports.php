<?php
declare(strict_types=1);

/**
 * Lost-report safeguards: the per-account submission limit, duplicate detection, staff moderation
 * (rejected / false report / spam / removed) and the false-report violations that warn, then deactivate, an account.
 * Every rule here runs on the server; the report form only mirrors them for quicker feedback.
 */

/** Seconds until $userId may file another lost report (0 = allowed now). Counts every report filed, removed ones too. */
function report_limit_wait(int $userId): int
{
    $stmt = db()->prepare('SELECT created_at FROM lost_reports WHERE user_id = ? AND created_at > NOW() - INTERVAL ? MINUTE ORDER BY created_at DESC LIMIT ?');
    $stmt->execute([$userId, REPORT_LIMIT_WINDOW_MINUTES, REPORT_LIMIT_MAX]);
    $times = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($times) < REPORT_LIMIT_MAX) {
        return 0;
    }
    // The oldest of the last REPORT_LIMIT_MAX reports leaves the window first.
    return max(1, strtotime(end($times)) + REPORT_LIMIT_WINDOW_MINUTES * 60 - time());
}

function report_limit_message(int $seconds): string
{
    $minutes = (int) ceil($seconds / 60);
    return 'You have reached the report submission limit (' . REPORT_LIMIT_MAX . ' reports per ' . REPORT_LIMIT_WINDOW_MINUTES . ' minutes). '
        . 'You can submit another report in approximately ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
}

/** Lower-case words of $text without punctuation, for comparing reports. */
function words_of(string $text): array
{
    return array_values(array_unique(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY)));
}

/** Share of words two texts have in common (Jaccard index, 0..1). */
function word_overlap(string $a, string $b): float
{
    $a = words_of($a);
    $b = words_of($b);
    $union = count(array_unique([...$a, ...$b]));
    return $union ? count(array_intersect($a, $b)) / $union : 0.0;
}

/**
 * The account's own recent open or matched reports that look like the new one ($v: validated report fields).
 * Returns ['exact' => report or null, 'similar' => [reports]]. Exact means every field is the same apart from case,
 * spacing and punctuation: a resubmission (often a double click) that is always refused. Similar means the same
 * category, a similar name or description, and the same place or a date lost within a week: the user is warned
 * and may still submit. Other accounts' reports are never compared, so two students may both report a black umbrella.
 */
function similar_reports(int $userId, array $v): array
{
    $stmt = db()->prepare("SELECT * FROM lost_reports WHERE user_id = ? AND deleted_at IS NULL AND status IN ('open', 'matched')
                           AND created_at > NOW() - INTERVAL ? DAY ORDER BY created_at DESC");
    $stmt->execute([$userId, DUPLICATE_LOOKBACK_DAYS]);
    $same = fn (string $a, string $b) => words_of($a) === words_of($b);
    $result = ['exact' => null, 'similar' => []];
    foreach ($stmt as $r) {
        if ($r['category'] !== $v['category']) {
            continue;
        }
        if ($same($r['item_name'], $v['item_name']) && $same($r['description'], $v['description'])
            && $same($r['location_lost'], $v['location_lost']) && $r['date_lost'] === $v['date_lost']) {
            $result['exact'] = $r;
            return $result;
        }
        $alike = word_overlap($r['item_name'], $v['item_name']) >= 0.5 || word_overlap($r['description'], $v['description']) >= 0.6;
        $near  = $same($r['location_lost'], $v['location_lost']) || abs(strtotime($r['date_lost']) - strtotime($v['date_lost'])) <= 7 * 86400;
        if ($alike && $near) {
            $result['similar'][] = $r;
        }
    }
    return $result;
}

/* ---------------------------------------------------------------- Moderation */

function is_moderated_report(array $report): bool
{
    return isset(REPORT_MODERATION_STATUSES[$report['status']]);
}

/** Confirmed false-report violations of an account, oldest first, with the reporting staff member's name. */
function account_violations(int $userId): array
{
    $stmt = db()->prepare('SELECT v.*, r.item_name, u.first_name, u.last_name FROM account_violations v
                           JOIN lost_reports r ON r.report_id = v.report_id LEFT JOIN users u ON u.user_id = v.issued_by
                           WHERE v.user_id = ? ORDER BY v.violation_id');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Staff outcome for an open or matched report: 'rejected', 'false_report' or 'spam'. A false report is a violation:
 * the first ones warn the account, the FALSE_REPORTS_TO_DEACTIVATE-th deactivates it and ends its sessions.
 * Runs in one transaction with the reporter's row locked, so two staff members can't both count the same
 * violation or race past the deactivation. Returns a message for the staff member; throws DomainException
 * when the report was already moderated.
 */
function moderate_report(array $report, string $outcome, string $reason, array $staff): string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? FOR UPDATE')->execute([$report['user_id']]);
        $stmt = $pdo->prepare("UPDATE lost_reports SET status = ?, moderation_reason = ?, moderated_by = ?, moderated_at = NOW()
                               WHERE report_id = ? AND deleted_at IS NULL AND status IN ('open', 'matched')");
        $stmt->execute([$outcome, $reason, $staff['user_id'], $report['report_id']]);
        if (!$stmt->rowCount()) {
            throw new DomainException('This report has already been moderated or removed.');
        }
        $action = null;
        if ($outcome === 'false_report') {
            $count  = 1 + count(account_violations($report['user_id']));
            $action = $count >= FALSE_REPORTS_TO_DEACTIVATE ? 'deactivated' : 'warning';
            $pdo->prepare('INSERT INTO account_violations (user_id, report_id, reason, action_taken, issued_by) VALUES (?, ?, ?, ?, ?)')
                ->execute([$report['user_id'], $report['report_id'], $reason, $action, $staff['user_id']]);
            if ($action === 'deactivated') {
                $pdo->prepare('UPDATE users SET is_active = 0, deactivation_reason = "false_reports", deactivated_at = NOW(), deactivated_by = ? WHERE user_id = ?')
                    ->execute([$staff['user_id'], $report['user_id']]);
                revoke_sessions($report['user_id']);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $owner = find_user($report['user_id']);
    $meta  = ['report_id' => $report['report_id'], 'item_name' => $report['item_name'], 'reason' => $reason];
    log_event(['rejected' => 'report_rejected', 'false_report' => 'report_marked_false', 'spam' => 'report_marked_spam'][$outcome], $owner, $meta);
    $id = $report['report_id'];
    if ($action === null) {
        return "Report #$id marked as " . strtolower(REPORT_MODERATION_STATUSES[$outcome]) . '.';
    }
    $meta['violation'] = $count;
    if ($action === 'warning') {
        log_event('false_report_warning', $owner, $meta);
        notify_false_report_warning($owner, $report, $reason, $count);
        return "Report #$id marked as a false report. The reporter has been warned (violation $count of " . FALSE_REPORTS_TO_DEACTIVATE . ').';
    }
    log_event('account_deactivated', $owner, $meta + ['deactivation_reason' => 'false_reports']);
    notify_false_report_deactivation($owner, $count);
    return "Report #$id marked as a false report. This was the reporter's violation number $count, so their account has been deactivated and logged out.";
}

/** Soft-deletes a false or spam report: it leaves every list, the row stays for audits. */
function remove_report(array $report, string $reason, array $staff): string
{
    $stmt = db()->prepare("UPDATE lost_reports SET deleted_at = NOW(), deleted_by = ?, deletion_reason = ?
                           WHERE report_id = ? AND deleted_at IS NULL AND status IN ('false_report', 'spam')");
    $stmt->execute([$staff['user_id'], $reason, $report['report_id']]);
    if (!$stmt->rowCount()) {
        throw new DomainException('Only reports marked as false or spam can be removed, and only once.');
    }
    log_event('report_deleted', find_user($report['user_id']), ['report_id' => $report['report_id'], 'item_name' => $report['item_name'], 'reason' => $reason]);
    return "Report #{$report['report_id']} removed. It stays on record for audits.";
}

/* ---------------------------------------------------------------- Notices */

/** What a user whose account is deactivated is told on the log-in page and when their session ends. */
function deactivated_message(array $user): string
{
    if ($user['deactivation_reason'] !== 'false_reports') {
        return 'This account has been deactivated. Contact the Lost & Found office.';
    }
    $count = count(account_violations($user['user_id']));
    return "Your account has been deactivated due to repeated false reports ($count confirmed false-report violations). "
        . 'Online self-reactivation is not available. Please visit ' . OFFICE_INFO . ' with your school ID to request an account review.';
}

/** Dashboard and report-form warning for an account with a confirmed false report ('' when it has none). */
function false_report_notice(array $user): string
{
    $violations = account_violations($user['user_id']);
    if (!$violations) {
        return '';
    }
    $last = end($violations);
    return '<div class="alert alert-warning" role="status"><strong>Warning:</strong> your account has received a warning for a confirmed false report'
        . ' (report #' . (int) $last['report_id'] . ', ' . e($last['item_name']) . '). Reason: ' . e($last['reason']) . ' '
        . 'Please ensure future reports contain accurate information. Another confirmed false report may result in the deactivation of your account.</div>';
}

function notify_false_report_warning(array $user, array $report, string $reason, int $count): void
{
    send_mail($user['email'], full_name($user), APP_NAME . ': warning for a false report',
        "Hi {$user['first_name']},\n\n"
        . "Warning: your lost-item report #{$report['report_id']} (\"{$report['item_name']}\") has been determined by Lost & Found staff to be a false report.\n\n"
        . "Reason: $reason\n\n"
        . "This is confirmed false-report violation $count of " . FALSE_REPORTS_TO_DEACTIVATE . '. Further false reports may result in the deactivation of your account. '
        . "Please make sure future reports contain accurate information.\n\n"
        . 'If you think this is a mistake, visit ' . OFFICE_INFO . ".\n\n— " . APP_NAME);
}

function notify_false_report_deactivation(array $user, int $count): void
{
    send_mail($user['email'], full_name($user), APP_NAME . ': your account has been deactivated',
        "Hi {$user['first_name']},\n\n"
        . "Account status: deactivated.\n\n"
        . "Your account has been deactivated due to repeated false reports ($count confirmed false-report violations). "
        . "You have been logged out and can no longer log in, file reports, submit claims or post items.\n\n"
        . 'Online self-reactivation is not available. To request an account review, visit ' . OFFICE_INFO . " with your school ID.\n\n— " . APP_NAME);
}
