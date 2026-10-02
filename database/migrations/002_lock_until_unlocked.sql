-- =====================================================================
--  002 — a locked account stays locked until staff unlock it at the
--  Lost & Found office (admin "Unlock" on index.php?tab=users).
--  locked_until (end of a timed lock) becomes locked_at (when it locked).
-- =====================================================================

-- Timed locks that already ran out are cleared; ones still running become permanent locks.
UPDATE users SET locked_until = NULL WHERE locked_until <= NOW();
ALTER TABLE users CHANGE COLUMN locked_until locked_at DATETIME NULL;
