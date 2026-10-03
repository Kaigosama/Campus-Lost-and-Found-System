-- =====================================================================
--  008 — lost-report moderation, false-report violations and the admin
--  activity feed.
--  * lost_reports gains staff outcomes (rejected / false_report / spam)
--    and a soft delete, so removed reports stay on record for audits.
--  * account_violations records each confirmed false report: the first
--    is a warning, the second deactivates the account.
--  * users records why, when and by whom an account was deactivated.
--  * security_events records who performed an action (actor_id) next to
--    the account it concerns (user_id).
-- =====================================================================

-- deactivation_reason: 'false_reports' (two confirmed false reports; reactivated only after an office review)
-- or 'admin' (an administrator's decision). NULL while the account is active.
ALTER TABLE users
    ADD COLUMN deactivation_reason VARCHAR(40)  NULL AFTER is_active,
    ADD COLUMN deactivated_at      DATETIME     NULL AFTER deactivation_reason,
    ADD COLUMN deactivated_by      INT UNSIGNED NULL AFTER deactivated_at,
    ADD CONSTRAINT fk_users_deactivated_by FOREIGN KEY (deactivated_by) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL;

UPDATE users SET deactivation_reason = 'admin', deactivated_at = updated_at WHERE is_active = 0;

-- rejected     staff turned the report down (incomplete, already found, accidental duplicate…); no penalty
-- false_report confirmed intentionally false or misleading; recorded in account_violations
-- spam         junk or repeated submissions; no penalty by itself
-- deleted_*    a false or spam report removed from every list; the row stays for audits
ALTER TABLE lost_reports
    MODIFY COLUMN status ENUM('open','matched','closed','rejected','false_report','spam') NOT NULL DEFAULT 'open',
    ADD COLUMN moderation_reason TEXT         NULL AFTER matched_item_id,
    ADD COLUMN moderated_by      INT UNSIGNED NULL AFTER moderation_reason,
    ADD COLUMN moderated_at      DATETIME     NULL AFTER moderated_by,
    ADD COLUMN deleted_at        DATETIME     NULL AFTER moderated_at,
    ADD COLUMN deleted_by        INT UNSIGNED NULL AFTER deleted_at,
    ADD COLUMN deletion_reason   TEXT         NULL AFTER deleted_by,
    ADD CONSTRAINT fk_lost_moderator FOREIGN KEY (moderated_by) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_lost_deleter FOREIGN KEY (deleted_by) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- One row per confirmed false report. The unique key stops a report from being counted twice.
CREATE TABLE account_violations (
    violation_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,                          -- who filed the false report
    report_id      INT UNSIGNED NOT NULL,
    violation_type ENUM('false_report') NOT NULL DEFAULT 'false_report',
    reason         TEXT         NOT NULL,
    action_taken   ENUM('warning','deactivated') NOT NULL,
    issued_by      INT UNSIGNED NULL,                              -- staff member who confirmed it
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (violation_id),
    UNIQUE KEY uq_violations_report (report_id, violation_type),
    KEY idx_violations_user (user_id, created_at),
    CONSTRAINT fk_violations_user   FOREIGN KEY (user_id)   REFERENCES users (user_id)          ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_violations_report FOREIGN KEY (report_id) REFERENCES lost_reports (report_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_violations_issuer FOREIGN KEY (issued_by) REFERENCES users (user_id)          ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Who did it. user_id stays the account the event concerns (the reporter whose report was marked false, the
-- account that was deactivated); actor_id is the signed-in person who caused it, NULL for anonymous attempts.
ALTER TABLE security_events
    ADD COLUMN actor_id INT UNSIGNED NULL AFTER user_id,
    ADD KEY idx_events_actor (actor_id, created_at),
    ADD CONSTRAINT fk_events_actor FOREIGN KEY (actor_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL;
