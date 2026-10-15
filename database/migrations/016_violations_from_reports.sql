-- =====================================================================
--  016 — false-report violations are read from lost_reports;
--  account_violations is dropped.
--
--  An account_violations row was a copy of a lost report that staff marked
--  "false_report": user_id = reported_by, reason = moderation_reason,
--  issued_by = moderated_by, created_at = moderated_at. violation_type had
--  one possible value, and action_taken follows from the order: the
--  FALSE_REPORTS_TO_DEACTIVATE-th false report of an account deactivates
--  it, each one before warns. The deactivation itself stays on users
--  (deactivation_reason, deactivated_at, deactivated_by) and in
--  security_events (false_report_warning, account_deactivated).
--  A moderated report never changes status again, so the history holds.
-- =====================================================================

-- 1. Anything a violation holds that its report lacks is copied onto the report first, so nothing is lost.
UPDATE lost_reports r
JOIN account_violations v ON v.report_id = r.report_id
SET r.status            = 'false_report',
    r.moderation_reason = COALESCE(r.moderation_reason, v.reason),
    r.moderated_by      = COALESCE(r.moderated_by, v.issued_by),
    r.moderated_at      = COALESCE(r.moderated_at, v.created_at);

-- 2. Counting an account's false reports now reads lost_reports by reporter and status.
ALTER TABLE lost_reports
    DROP INDEX idx_lost_user,
    ADD KEY idx_lost_user (reported_by, status);

-- 3. The copy goes.
DROP TABLE account_violations;
