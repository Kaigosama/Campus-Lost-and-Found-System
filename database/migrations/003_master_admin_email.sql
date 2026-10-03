-- =====================================================================
--  003 — the master admin logs in with a real inbox, so security
--  notices and password reset links reach them.
-- =====================================================================

UPDATE users SET email = 'masteradminp@gmail.com' WHERE role = 'master_admin';
