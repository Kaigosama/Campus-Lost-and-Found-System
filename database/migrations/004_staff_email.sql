-- =====================================================================
--  004 — the seeded staff account logs in with a real inbox, so
--  security notices and password reset links reach them.
-- =====================================================================

UPDATE users SET email = 'staffmarcos1@gmail.com' WHERE email = 'staff@mapua.edu.ph';
