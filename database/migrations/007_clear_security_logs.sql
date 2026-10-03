-- =====================================================================
--  007 — clean start for the Security Logs tab before launch: every
--  security event goes, and so do finished sessions. Sessions still
--  active stay, so nobody signed in is logged out by the deploy.
-- =====================================================================

DELETE FROM security_events;
DELETE FROM user_sessions WHERE status <> 'active';
