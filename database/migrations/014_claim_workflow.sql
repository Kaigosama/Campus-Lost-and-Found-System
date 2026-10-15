-- =====================================================================
--  014 — claim review history, identity checks at pickup, return and
--  disposal records.
--
--  Claim workflow (claims.status, the main status):
--    pending  → approved (approved for collection) → verified (identity
--    verified at the desk) → returned (handed over; final)
--  A rejection does NOT change the main status: the claim stays "pending"
--  and the rejection is a row in claim_reviews. The latest claim_reviews
--  row is the claim's current review outcome (approved / rejected).
--  So reviewed_by / review_note / reviewed_at move from claims into
--  claim_reviews, one row per decision, never overwritten.
--
--  claim_verifications: one row per identity check. Only the ID type and
--  the last four characters of the ID number are stored, never the full
--  number; the masked reference is cleared after ID_RECORD_RETENTION_DAYS.
--
--  found_items gains who handed it over and for which claim (returned_by,
--  return_claim_id next to the existing returned_at), and the disposal
--  record (disposed_at, disposed_by, disposal_reason, disposal_notes).
--  "returned" and "disposed" stay the two final item statuses.
-- =====================================================================

CREATE TABLE claim_reviews (
    review_id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    claim_id          INT UNSIGNED  NOT NULL,
    reviewer_id       INT UNSIGNED  NULL,                         -- staff member; NULL only for old rows whose reviewer is gone
    outcome           ENUM('approved','rejected') NOT NULL,
    previous_outcome  ENUM('approved','rejected') NULL,           -- the outcome before this review, NULL for the first one
    reason            TEXT          NOT NULL,                     -- the note shown to the claimant
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    KEY idx_reviews_claim (claim_id, review_id),
    KEY idx_reviews_outcome (outcome, created_at),
    CONSTRAINT fk_reviews_claim    FOREIGN KEY (claim_id)    REFERENCES claims (claim_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (user_id)   ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Every decision already made becomes the first history row of its claim.
INSERT INTO claim_reviews (claim_id, reviewer_id, outcome, reason, created_at)
SELECT claim_id, reviewed_by, status, COALESCE(review_note, ''), COALESCE(reviewed_at, updated_at)
FROM claims WHERE status IN ('approved', 'rejected') ORDER BY COALESCE(reviewed_at, updated_at), claim_id;

CREATE TABLE claim_verifications (
    verification_id  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    claim_id         INT UNSIGNED  NOT NULL,
    id_type          VARCHAR(40)   NOT NULL,                      -- a key of ID_TYPES in config/config.php
    id_reference     VARCHAR(12)   NULL,                          -- masked, e.g. "••••0266"; NULL after the retention period
    result           ENUM('verified','failed') NOT NULL,
    notes            TEXT          NULL,
    verified_by      INT UNSIGNED  NOT NULL,                      -- the signed-in staff member, never chosen by anyone
    verified_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- set by the database, never by the browser
    PRIMARY KEY (verification_id),
    KEY idx_verifications_claim (claim_id, verification_id),
    CONSTRAINT fk_verifications_claim    FOREIGN KEY (claim_id)    REFERENCES claims (claim_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_verifications_verifier FOREIGN KEY (verified_by) REFERENCES users (user_id)   ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Rejected claims go back to the Pending main status (their rejection is now in claim_reviews), and an approved
-- claim whose item was already handed over becomes "returned".
ALTER TABLE claims
    DROP FOREIGN KEY fk_claims_reviewer,
    MODIFY COLUMN status ENUM('pending','approved','rejected','verified','returned') NOT NULL DEFAULT 'pending';
UPDATE claims SET updated_at = updated_at, status = 'pending' WHERE status = 'rejected';
UPDATE claims c JOIN found_items f ON f.item_id = c.item_id
SET c.updated_at = c.updated_at, c.status = 'returned'
WHERE f.status = 'returned' AND c.status = 'approved';
ALTER TABLE claims
    MODIFY COLUMN status ENUM('pending','approved','verified','returned') NOT NULL DEFAULT 'pending',
    DROP COLUMN reviewed_by,
    DROP COLUMN review_note,
    DROP COLUMN reviewed_at;

ALTER TABLE found_items
    ADD COLUMN returned_by      INT UNSIGNED NULL AFTER returned_at,     -- staff member who handed it over
    ADD COLUMN return_claim_id  INT UNSIGNED NULL AFTER returned_by,     -- the claim it was returned under
    ADD COLUMN disposed_at      DATETIME     NULL AFTER return_claim_id,
    ADD COLUMN disposed_by      INT UNSIGNED NULL AFTER disposed_at,
    ADD COLUMN disposal_reason  TEXT         NULL AFTER disposed_by,
    ADD COLUMN disposal_notes   TEXT         NULL AFTER disposal_reason,
    ADD UNIQUE KEY uq_found_return_claim (return_claim_id),
    ADD CONSTRAINT fk_found_returned_by  FOREIGN KEY (returned_by)     REFERENCES users (user_id)   ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_found_return_claim FOREIGN KEY (return_claim_id) REFERENCES claims (claim_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_found_disposed_by  FOREIGN KEY (disposed_by)     REFERENCES users (user_id)   ON UPDATE CASCADE ON DELETE SET NULL;

-- Items returned before this migration: link the claim they went out under (who handed them over wasn't recorded).
UPDATE found_items f JOIN (SELECT item_id, MIN(claim_id) AS claim_id FROM claims WHERE status = 'returned' GROUP BY item_id) c
    ON c.item_id = f.item_id
SET f.updated_at = f.updated_at, f.return_claim_id = c.claim_id;
