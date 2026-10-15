# Professor's required revisions — implementation report

Cardinal Finds (Campus Lost-and-Found System), ITS122P Group 4. All revisions were made to the existing
PHP / MySQL application; nothing was rebuilt, and existing accounts, items, reports, claims and logs are kept.

## 1. Implementation summary

| # | Requirement | How it was addressed |
|---|---|---|
| 2 | Dynamic form categories | New `categories` table replaces the hard-coded `CATEGORIES` list. Lost reports and found items (the only forms with categories) share it through `category_id`. Admin → Categories lists every category with its status and how many records use it, and adds, deactivates, reactivates and deletes. Names are trimmed and unique ignoring case. Only active categories are offered on new records; an edited record keeps its category even if it was deactivated. A category in use can't be deleted (the API refuses and the foreign key is `ON DELETE RESTRICT`); deactivate it instead. The old "Other: typed text" behaviour is kept in `category_detail`. |
| 3 | Admin item monitoring | A `MONITOR_ROLES` permission (staff + admins) gives admins read-only access to the staff views that already exist: Found Items (manage list), Lost Reports, Claims (with returns), Disposed Items, Statistics, every item page with its intake record, claims, review history, ID checks and a new per-item activity history. The pages and endpoints are shared, not copied; every action (review, verify, hand over, dispose, log, edit, delete, post review) still checks `is_staff()` on the server, so admins get 403. |
| 4 | School-based registration | New `student_records` and `employee_records` tables with fictional sample data. Registration is two steps: choose Student or Faculty / Employee and enter the school number (format, record, eligibility and "already registered" are checked on the server), then confirm the name shown from the record and add an email and password. The server looks the number up again on submit, so the type and name can't be changed in the request; the role is always `user`. `users.student_number` / `users.employee_number` are unique foreign keys, so one number = one account even under simultaneous requests. |
| 5 | Stronger passwords | `password_error()` (server) and the live checklist (app.js) also reject the person's first name, last name, full name (case, accents, separators and look-alikes such as `J0s3` handled), any full calendar date (`YYYYMMDD`, `DDMMYYYY`, `MMDDYYYY`, separators ignored), and — on the server only — the actual birth date from the school record in 8- and 6-digit forms. Three-letter names count only as a whole word, so "Ana" rejects `Ana2024!` but not `Banana!24`. The checklist shows strength, each rule as Passed / Not satisfied, and "No personal information: Failed" with the message "Your password must not contain your name or birthday." Applies to registration, change password and reset password. Passwords are bcrypt-hashed and never logged. |
| 6 | Local visitor records | Visitors browse anonymously. On "Accept" in the cookie banner the app creates a `visitors` row identified only by the SHA-256 of a random `clafs_vid` cookie (no name, email, IP or browser data), counts page views while logged out, and links anonymous security events (failed log-ins, CAPTCHA failures, sign-ups) through `security_events.visitor_id`. "Reject" deletes the row and cookie. Rows unseen for 90 days are deleted. Only admins see Admin → Visitors. |
| 7 | 30-minute verification links | `EMAIL_VERIFICATION_EXPIRY_MINUTES` (default 30). Tokens are 256-bit random, stored as SHA-256, single-use; a resend replaces the previous token; resend is rate-limited per IP and per account. An expired link shows "Your email verification link has expired. Request a new verification email to continue." and activates nothing (checked again on the POST). Unverified accounts can't log in, and any session of an unverified account is ended on the server. |
| 8 | CAPTCHA on login | The Turnstile widget is no longer rendered on page load (where it showed "Success!" before anything was typed). app.js renders it only when the form is submitted with its required fields filled in, and a status line shows each state separately: waiting for the form, ready, verifying, failed, expired / timed out, passed ("Sending your details to the server…"). Login success is shown only by the server redirect after the credentials pass; wrong credentials show "Incorrect email or password." The server rejects missing credentials, and missing, invalid, expired or already-used tokens (siteverify). Same flow on registration. |
| 9 | Back and Cancel | A consistent `back_button()` on item and report pages (explicit parent routes, not browser history) and the account review page; `cancel_button()` on report / found-item / edit forms, registration, email confirmation and password reset. Cancel asks before discarding a form with changes; long forms also warn before the tab is closed. Registration step 2 has "Back" (keeps the school number) and "Cancel". ID-check, disposal and category forms have Cancel (clear). Buttons are full width and 44 px tall on phones and have visible focus outlines. |
| 10 | ID verification at pickup | New `claim_verifications` table: ID type from a controlled list (`ID_TYPES` in config, placeholder until the school confirms its policy), only the last four characters of the ID number (stored masked, cleared after 365 days), result, notes, the signed-in staff member and the database time. The form appears only for a claim approved for collection. Staff/admin only; never in public pages or activity logs. |
| 10, 11 | Claim workflow and returns | Pending → Approved for collection → Identity verified → Returned. Approval no longer returns an item; the hand-over is a separate step that needs a verified ID check and runs in one transaction: claim and item become Returned, `returned_at`, `returned_by` and `return_claim_id` are recorded, the linked lost report closes, other open claims get a rejection, and the action is logged. The dashboard (staff and admin) has a Successful returns section and Claims has a Returned tab; both read `successful_returns()`. |
| 12 | No editing returned items | No Edit button for returned (or disposed) items; `report.php` and `api/update_item.php` refuse them (409), and the old status dropdown that could set any status was removed. The record, claim, return information and history are kept. |
| 13 | Rejected claims stay Pending | Rejection no longer changes `claims.status`; it adds a `claim_reviews` row (reviewer, reason, previous outcome, time). The latest row is the current review outcome, shown as "Pending · Review outcome: Rejected". Staff may review a rejected claim again, which adds a row; earlier rows are never changed. Reviewed claims can't be withdrawn, so history isn't lost. |
| 14 | One entry per item | Claims → "Rejected (still pending)" lists each item once with main status Pending, outcome Rejected, the rejected claims (each still its own record and claimant) and the number of rejection events. The item page shows every claim with its full history. |
| 15 | Disposed Items section | Disposal records on `found_items` (`disposed_at`, `disposed_by`, `disposal_reason`, `disposal_notes`). Staff dispose from the item page after confirming; the server refuses items not in storage, not yet accepted, held fewer than `DISPOSAL_HOLD_DAYS` (30, placeholder) or with a claim approved or awaiting review, and re-checks claims under a row lock. Disposed items leave the public list, can't be claimed or edited, and appear under Dashboard → Disposed Items with search, category and date filters (staff and admins). |
| 16 | ERD | `docs/erd.html` is generated from the migrated database and lists every table, key, relationship, cardinality and delete rule. |

## 2. Files changed

**New**

| File | Purpose |
|---|---|
| `database/migrations/011_categories.sql` | `categories` table, `category_id` on lost reports and found items, data moved from the old text column |
| `database/migrations/012_school_reference.sql` | `student_records`, `employee_records` (fictional data), `users.student_number` / `employee_number` |
| `database/migrations/013_visitors.sql` | `visitors` table, `security_events.visitor_id` |
| `database/migrations/014_claim_workflow.sql` | `claim_reviews`, `claim_verifications`, new claim statuses, return and disposal columns |
| `src/categories.php` | Category queries and name validation |
| `src/school_records.php` | School-number look-up, eligibility, account type label, password personal data |
| `src/visitors.php` | Visitor consent, cookie and page-view tracking |
| `src/claims.php` | Review, ID verification, hand-over, disposal, returns and rejected-item grouping |
| `public/api/categories.php` | Admin category management API |
| `public/api/visitor.php` | Cookie-banner consent API |
| `tests/password_policy_test.php` | Password policy self-check (29 cases, no database) |
| `tests/acceptance_test.py` | End-to-end acceptance checks (106) |
| `docs/REVISIONS.md` | This report |

**Modified**

| File | Change |
|---|---|
| `config/config.php` | Monitor roles, claim statuses, ID types, disposal hold, school-number formats, eligibility, visitor retention, verification expiry; removed `CATEGORIES` and name-entry constants |
| `database/schema.sql`, `database/seed.php` | Drop list includes the new tables; Manila time zone for migrations; retention on deploy |
| `src/repository.php` | Category name and latest review joined into the cached tables; per-item activity; final-item helpers |
| `src/validation.php` | Category validation; personal-information password rules |
| `src/auth.php` | School-number registration, verified-email session check, `can_monitor()` |
| `src/email_verification.php` | Minute-based expiry, expired vs used links, grace period, `run_retention()` |
| `src/security.php` | Turnstile rendered on submit; visitor id on events; error codes logged |
| `src/helpers.php`, `src/views.php`, `src/reports.php`, `src/bootstrap.php` | Labels, back/cancel buttons, category select, returns table, duplicate check by category id |
| `public/register.php`, `login.php`, `verify_email.php`, `resend_verification.php`, `reset_password.php` | New registration flow, CAPTCHA states, expiry messages, Cancel |
| `public/index.php` | Monitoring tabs for admins, returns sections, Claims views, Disposed Items, Categories, Visitors, statistics |
| `public/view_item.php` | Review history, ID checks, hand-over, disposal, item history, admin read-only view |
| `public/report.php`, `browse.php` | Category select, no edits of final items, Cancel guard, admin read-only list |
| `public/api/add_item.php`, `update_item.php`, `update_status.php`, `claims.php`, `get_items.php`, `delete_item.php`, `staff_updates.php` | Category ids, final-item lock, disposal, claim workflow, monitor access |
| `public/js/app.js`, `public/js/api.js`, `public/css/styles.css` | Live password checklist, Turnstile states, unsaved-change guard, new API calls, styles |
| `templates/layout/navbar.php`, `header.php`, `footer.php` | Admin Monitoring menu, visitor page views, cookie banner text |
| `docs/erd.html`, `README.md`, `.env.example`, `docker-compose.yml` | Documentation and configuration |

## 3. Database changes

Migrations 011–014 run once each, in order, through `php database/seed.php` (automatically on every Docker / Railway
start). They only add tables and columns and move data; no record is deleted.

- **011** `categories` (PK `category_id`, unique `name`, `is_active`, `created_by` → users SET NULL). `lost_reports.category_id`
  and `found_items.category_id` NOT NULL → categories RESTRICT. The old `category` column becomes `category_detail`
  (only text typed for "Other"). Existing names map to their category; anything else maps to Other with its text kept.
- **012** `student_records` (PK `student_number`), `employee_records` (PK `employee_number`), both with unique `school_email`
  and an eligibility status. `users.student_number` / `employee_number`: unique, FK RESTRICT, CHECK at most one.
- **013** `visitors` (PK `visitor_id`, unique `token_hash`); `security_events.visitor_id` → visitors SET NULL.
- **014** `claim_reviews` (claim CASCADE, reviewer SET NULL); `claim_verifications` (claim CASCADE, `verified_by` RESTRICT);
  existing decisions copied into `claim_reviews`; `claims.status` becomes pending / approved / verified / returned (old
  rejected → pending; approved on a returned item → returned); `claims.reviewed_by`, `review_note`, `reviewed_at` dropped
  after the copy; `found_items` gains `returned_by`, `return_claim_id` (unique, → claims RESTRICT), `disposed_at`,
  `disposed_by`, `disposal_reason`, `disposal_notes`.

Tested on an empty database, on a copy of a populated local database, and on the original sample claims (a rejected claim
became Pending with one history row).

## 4. Updated ERD

Open [`erd.html`](erd.html) in a browser (the diagram is drawn by Mermaid from a CDN; the tables under it list the same
schema offline). It covers users, student and employee reference records, visitors, sessions, security events,
categories, lost reports, found items (with return and disposal records), claims, claim review history, identity checks
and violations, with every primary key, foreign key, unique key, cardinality and delete rule.

## 5. Registration configuration

- The sample data in `database/migrations/012_school_reference.sql` is **fictional** (names invented, emails on the
  reserved `.test` domain). Eligible students: `2023100001`–`2023100003`, `2022100004`, `2022100005`, `2024100006`,
  `2024100007`, `2021100008` (on leave), `2023100011` (no birth date), `2024100012`. Not eligible: `2020100009`
  (graduated), `2022100010` (withdrawn). Employees: `EMP-10001`–`EMP-10005`; `EMP-10006` is separated.
- To use authorized school records, load them into the same tables (for example `LOAD DATA LOCAL INFILE` from the
  Registrar / HR export, or `INSERT … ON DUPLICATE KEY UPDATE` to refresh statuses), and delete the sample rows that no
  account uses. Adjust `STUDENT_NUMBER_PATTERN`, `EMPLOYEE_NUMBER_PATTERN` and the eligible statuses in
  `config/config.php` to the real formats. `birth_date` may be left NULL; the name and full-date rules still apply.
- Names are copied into `users` at registration (staff and admin accounts have no school record, and history keeps the
  name the account had); the birth date and school email stay only in the reference tables.

## 6. Email configuration

| Variable | Default | Meaning |
|---|---|---|
| `EMAIL_VERIFICATION_EXPIRY_MINUTES` | `30` | Lifetime of a verification link |
| `BREVO_API_KEY`, `MAIL_FROM`, `MAIL_FROM_NAME` | — | Sending mail (unchanged); without a key, emails are written to the server log |
| `APP_URL` | — | Address used in emailed links |

Unconfirmed accounts are removed `UNVERIFIED_GRACE_HOURS` (24) after their link expires, so the email and school number
can register again; until then "Resend verification email" works.

## 7. Roles and permissions

| Role | Items / reports / claims | Accounts and settings |
|---|---|---|
| Student / Faculty (`user`) | Report lost items, post found items, claim, withdraw an unreviewed claim, see their own records | Own password |
| Security & Maintenance (`staff`) | Everything on items: log, edit (not once returned or disposed), delete mistakes, review posts, approve / reject claims, verify IDs, hand over, dispose, match and moderate reports | Own password |
| Office Administrator (`admin`) | Read-only monitoring of all of the above, including ID checks, returns, disposals and item history | Users (students and staff), categories, visitor records, security logs |
| Master Administrator | Same as admin | Also administrator accounts |

Registration always creates a Student / Faculty account. Staff and admin roles are granted only in Admin → Users.

## 8. Testing results

- `php tests/password_policy_test.php`: **29 / 29 passed** (names, look-alikes, accents, birthdays, full dates,
  legitimate passwords without false positives).
- `python tests/acceptance_test.py` against a test container: **106 / 106 passed**, covering every scenario in section 17
  of the brief: category create / deactivate / reactivate / delete rules and permissions; student and employee look-ups,
  invalid, ineligible and tampered identifiers, duplicate numbers and emails, role forcing; password rules on the server;
  30-minute expiry, expired, used and replaced links, resend, unverified log-in; CAPTCHA pass / fail via Cloudflare's
  test keys, missing credentials, wrong password; rejection history, Pending main status, grouped rejected listing,
  ID check (masked, validator, server time, not logged), hand-over consistency, returns on dashboard and Claims, edit
  lock; disposal permissions, eligibility, confirmation, listing, exclusion from claims; admin read-only monitoring;
  visitor records; no orphaned foreign keys; all migrations applied.
- Browser check (desktop and 375 px phone): login CAPTCHA states, registration steps with the live checklist, Back,
  admin dashboard, Claims → Rejected, Categories, item page history. No horizontal scrolling at phone width.

## 9. Deployment

1. Deploy as before (push to `main` for Railway, or `docker compose up --build`). Migrations 011–014 run on start.
2. Optionally set `EMAIL_VERIFICATION_EXPIRY_MINUTES` (defaults to 30).
3. Load authorized student and employee records before opening registration (section 5).
4. Ask the school to confirm `ID_TYPES` and `DISPOSAL_HOLD_DAYS` in `config/config.php`.

## 10. Known limitations

- **ID types and disposal holding period are placeholders** until the school confirms its policy. No separate approval
  step for disposal is modelled, and there is no audited "reverse disposal" or admin correction of returned records
  (the brief makes both conditional on school policy).
- **Anyone who knows another person's school number could register it** with their own email. The real person is then
  blocked until the office deactivates that account. Sending the verification link to the school email on record would
  close this once real school mailboxes are used; the sample data uses undeliverable `.test` addresses, so it was not
  enforced.
- **The birth-date rule is checked on submit only** (sending the birthday to the browser would reveal it). The live
  checklist catches names and any full date as you type.
- Walk-in owners without an account can't be handed an item through the app: a return needs a claim with a verified ID.
- Items returned or disposed before these revisions have no "handed over by" / disposal record; the pages say
  "Not recorded" rather than inventing one.
- Retention clean-up runs on each deploy and when an admin opens the dashboard; there is no scheduled job.
- The ERD diagram needs internet access to draw (Mermaid CDN); its tables work offline.
