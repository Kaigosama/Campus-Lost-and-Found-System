-- =====================================================================
--  015 — one users table for every login, linked to the school's
--  records; clearer column names; anonymous visitor records removed.
--
--  students / employees (were student_records / employee_records) answer
--  "who exists in the school?". users answers "who has an account?":
--  every Student, Faculty / Employee, staff and administrator account,
--  each a row here, so sessions, the security log and every action keep
--  pointing at users.user_id.
--  * A Student / Faculty account links to its record through the unique
--    users.student_number or users.employee_number (at most one, set by
--    the server at registration). A staff or administrator account may
--    link to its employee record; seeded office accounts without one
--    keep their own name.
--  * Names are no longer copied: a linked account's name is read from its
--    school record, so users.first_name / last_name are kept only for
--    accounts with no record (chk_users_name_source).
--  * Accounts registered before school records existed are linked by an
--    exact, unambiguous match of their login email to a record's school
--    email. Any still unlinked stay as they are (nothing is guessed from
--    names) and are listed for review under Dashboard → Accounts needing
--    attention; see docs/REVISIONS.md for the counts.
--  * Columns that held "a user" now say which one:
--      lost_reports.user_id → reported_by    claims.user_id → claimant_id
--      found_items.user_id  → added_by (staff who logged it, or the student / faculty member who posted it)
--  * visitors is dropped: it only counted page views of anonymous people
--    who accepted cookies. Nobody without an account submits or claims
--    anything; anonymous security events keep their IP address and device.
-- =====================================================================

RENAME TABLE student_records TO students, employee_records TO employees;
ALTER TABLE students  RENAME INDEX uq_student_records_email  TO uq_students_email;
ALTER TABLE employees RENAME INDEX uq_employee_records_email TO uq_employees_email;

-- Accounts registered before school records: link by school email when exactly one record matches it and no
-- other account uses that record. A Student / Faculty email found in both tables is left for review.
UPDATE users u
JOIN students s ON s.school_email = u.email
LEFT JOIN users taken ON taken.student_number = s.student_number
SET u.student_number = s.student_number
WHERE u.role = 'user' AND u.student_number IS NULL AND u.employee_number IS NULL AND taken.user_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM employees e WHERE e.school_email = u.email);
UPDATE users u
JOIN employees e ON e.school_email = u.email
LEFT JOIN users taken ON taken.employee_number = e.employee_number
SET u.employee_number = e.employee_number
WHERE u.student_number IS NULL AND u.employee_number IS NULL AND taken.user_id IS NULL
  AND (u.role <> 'user' OR NOT EXISTS (SELECT 1 FROM students s WHERE s.school_email = u.email));

-- A linked account takes its name from the school record; only accounts with no record keep one here.
ALTER TABLE users
    MODIFY COLUMN first_name VARCHAR(100) NULL,
    MODIFY COLUMN last_name  VARCHAR(100) NULL;
UPDATE users SET updated_at = updated_at, first_name = NULL, last_name = NULL
WHERE student_number IS NOT NULL OR employee_number IS NOT NULL;
ALTER TABLE users
    ADD CONSTRAINT chk_users_name_source CHECK (
        (student_number IS NULL AND employee_number IS NULL) = (first_name IS NOT NULL AND last_name IS NOT NULL));

ALTER TABLE lost_reports RENAME COLUMN user_id TO reported_by;
ALTER TABLE claims       RENAME COLUMN user_id TO claimant_id;
ALTER TABLE found_items  RENAME COLUMN user_id TO added_by;

ALTER TABLE security_events
    DROP FOREIGN KEY fk_events_visitor,
    DROP KEY idx_events_visitor,
    DROP COLUMN visitor_id;
DROP TABLE visitors;
