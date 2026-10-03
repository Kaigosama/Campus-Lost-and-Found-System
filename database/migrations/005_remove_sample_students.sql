-- =====================================================================
--  005 — remove the four sample Student / Faculty accounts from
--  schema.sql, with the demo claims, lost reports and posts they own
--  (the foreign keys block deleting a user who still owns any).
--  Found items logged by staff stay. Sessions go with the accounts;
--  security log rows keep the email but lose the account link.
-- =====================================================================

CREATE TEMPORARY TABLE sample_students AS
    SELECT user_id FROM users
    WHERE role = 'user' AND email IN ('student1@mymail.mapua.edu.ph', 'student2@mymail.mapua.edu.ph',
                                      'rvillanueva@mapua.edu.ph', 'student9@mymail.mapua.edu.ph');

DELETE FROM claims       WHERE user_id IN (SELECT user_id FROM sample_students);
DELETE FROM lost_reports WHERE user_id IN (SELECT user_id FROM sample_students);
DELETE FROM found_items  WHERE user_id IN (SELECT user_id FROM sample_students);   -- their posts; claims on them cascade
DELETE FROM users        WHERE user_id IN (SELECT user_id FROM sample_students);

DROP TEMPORARY TABLE sample_students;
