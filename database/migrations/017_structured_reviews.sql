-- =====================================================================
--  017 — structured review decisions.
--
--  A claim review, a found-post review and a lost-report moderation used
--  to keep only one free-text note. Each decision now stores a standard
--  reason code (a key of the lists in config/config.php, checked by the
--  server) and the fields its action needs; the free text is the detail
--  under it.
--  * claim_reviews: reason → details (nullable: approval notes are
--    optional); rejection: reason_code, resubmission_allowed,
--    required_followup; approval: pickup_location, pickup_instructions,
--    required_ids (comma-separated keys of ID_TYPES).
--  * found_items (student posts): moderation_reason_code,
--    resubmission_allowed, required_corrections for a rejection;
--    item_condition and a staff-only moderation_internal_note for an
--    approval. moderation_note stays the detail shown to the poster.
--  * lost_reports: moderation_reason_code and resubmission_allowed;
--    moderation_reason stays the detail (or the evidence for a false report).
--  Earlier decisions keep their text and get no reason code: the pages show
--  them as legacy reviews, nothing is invented for them.
-- =====================================================================

ALTER TABLE claim_reviews
    CHANGE COLUMN reason details TEXT NULL,
    ADD COLUMN reason_code          VARCHAR(40)  NULL AFTER previous_outcome,
    ADD COLUMN resubmission_allowed TINYINT(1)   NULL AFTER details,
    ADD COLUMN required_followup    TEXT         NULL AFTER resubmission_allowed,
    ADD COLUMN pickup_location      VARCHAR(100) NULL AFTER required_followup,
    ADD COLUMN pickup_instructions  TEXT         NULL AFTER pickup_location,
    ADD COLUMN required_ids         VARCHAR(100) NULL AFTER pickup_instructions;

ALTER TABLE found_items
    ADD COLUMN moderation_reason_code   VARCHAR(40) NULL AFTER moderation_note,
    ADD COLUMN resubmission_allowed     TINYINT(1)  NULL AFTER moderation_reason_code,
    ADD COLUMN required_corrections     TEXT        NULL AFTER resubmission_allowed,
    ADD COLUMN item_condition           ENUM('good','minor_damage','damaged','unknown') NULL AFTER required_corrections,
    ADD COLUMN moderation_internal_note TEXT        NULL AFTER item_condition;

ALTER TABLE lost_reports
    ADD COLUMN moderation_reason_code VARCHAR(40) NULL AFTER matched_item_id,
    ADD COLUMN resubmission_allowed   TINYINT(1)  NULL AFTER moderation_reason;
