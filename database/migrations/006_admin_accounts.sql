-- =====================================================================
--  006 — the two office administrators get real inboxes (security
--  notices and password reset links) and new display names.
-- =====================================================================

UPDATE users SET first_name = 'Ana',     last_name = 'Lysis', email = 'adminanalog1@gmail.com'     WHERE email = 'admin@mapua.edu.ph';
UPDATE users SET first_name = 'Liza C.', last_name = 'Ning',  email = 'adminlizancing2@gmail.com'  WHERE email = 'admin2@mapua.edu.ph';
