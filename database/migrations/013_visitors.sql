-- =====================================================================
--  013 — a local record of anonymous visitors (people browsing without
--  logging in), kept in this database instead of a third-party service.
--  * A row exists only after the visitor clicks "Accept" on the cookie
--    banner; it is then found again through a random cookie (clafs_vid)
--    whose SHA-256 hash is the only identifier stored. No name, email,
--    IP address or browser details are kept here.
--  * "Reject" (or withdrawing consent later) deletes the row and the cookie.
--  * Rows unseen for VISITOR_RETENTION_DAYS are deleted (run_retention()).
--  * security_events.visitor_id ties anonymous events (failed log-ins,
--    CAPTCHA failures, sign-ups) to the browser they came from.
-- =====================================================================

CREATE TABLE visitors (
    visitor_id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    token_hash     CHAR(64)      NOT NULL,                        -- SHA-256 of the clafs_vid cookie
    consented_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    first_seen_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    page_views     INT UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (visitor_id),
    UNIQUE KEY uq_visitors_token (token_hash),
    KEY idx_visitors_seen (last_seen_at)
) ENGINE=InnoDB;

ALTER TABLE security_events
    ADD COLUMN visitor_id INT UNSIGNED NULL AFTER actor_id,
    ADD KEY idx_events_visitor (visitor_id, created_at),
    ADD CONSTRAINT fk_events_visitor FOREIGN KEY (visitor_id) REFERENCES visitors (visitor_id)
        ON UPDATE CASCADE ON DELETE SET NULL;
