-- =====================================================================
--  CLAFS — Campus Lost-and-Found System
--  MySQL 8 / MariaDB 10.4+ schema.   Diagram: docs/erd.html
--
--  Sections 1–4 create the four tables exactly as drawn in the ERD.
--  Section 5 adds the columns the current front-end relies on that are
--  NOT in the ERD yet; delete it to keep the database identical to the
--  diagram (and trim the UI), or fold those columns into the ERD.
--  Section 6 seeds the same rows the mock data uses (needs section 5).
--
--  Usage:  mysql -u root -p < docs/schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS clafs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clafs;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS claims, found_items, lost_reports, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. users
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    first_name  VARCHAR(100)  NOT NULL,
    last_name   VARCHAR(100)  NOT NULL,
    email       VARCHAR(190)  NOT NULL,
    role        ENUM('user','staff','admin') NOT NULL DEFAULT 'user',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. lost_reports        users ||--o{ lost_reports
-- ---------------------------------------------------------------------
CREATE TABLE lost_reports (
    report_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED  NOT NULL,                        -- who reported it
    category       VARCHAR(50)   NOT NULL,                        -- one of CATEGORIES in config/db_connect.php
    description    TEXT          NOT NULL,
    location_lost  VARCHAR(150)  NOT NULL,
    date_lost      DATE          NOT NULL,
    image_url      VARCHAR(255)  NULL,                            -- path relative to public/, e.g. uploads/abc.jpg
    status         ENUM('open','matched','closed') NOT NULL DEFAULT 'open',
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (report_id),
    KEY idx_lost_user   (user_id),
    KEY idx_lost_status (status, date_lost),
    CONSTRAINT fk_lost_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. found_items         users ||--o{ found_items
-- ---------------------------------------------------------------------
CREATE TABLE found_items (
    item_id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED  NOT NULL,                     -- staff member who logged it
    category          VARCHAR(50)   NOT NULL,
    description       TEXT          NOT NULL,                     -- public description
    location_found    VARCHAR(150)  NOT NULL,
    storage_location  VARCHAR(150)  NOT NULL,                     -- staff only
    date_found        DATE          NOT NULL,
    image_url         VARCHAR(255)  NULL,
    status            ENUM('stored','returned','disposed') NOT NULL DEFAULT 'stored',
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY idx_found_user   (user_id),
    KEY idx_found_status (status, date_found),
    CONSTRAINT fk_found_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. claims              found_items ||--o{ claims
--                        users       ||--o{ claims
--                        lost_reports |o--o{ claims   (report_id optional)
-- ---------------------------------------------------------------------
CREATE TABLE claims (
    claim_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    item_id       INT UNSIGNED  NOT NULL,
    user_id       INT UNSIGNED  NOT NULL,                         -- claimant
    report_id     INT UNSIGNED  NULL,                             -- the claimant's lost report, if linked
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    date_claimed  DATE          NOT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (claim_id),
    KEY idx_claims_item   (item_id, status),
    KEY idx_claims_user   (user_id),
    KEY idx_claims_report (report_id),
    CONSTRAINT fk_claims_item   FOREIGN KEY (item_id)   REFERENCES found_items  (item_id)   ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_claims_user   FOREIGN KEY (user_id)   REFERENCES users        (user_id)   ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_claims_report FOREIGN KEY (report_id) REFERENCES lost_reports (report_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 5. UI additions — columns the current pages use that are NOT in the
--    ERD. Decide with the group: add them to the ERD, or remove them
--    from the UI and delete this section.
-- =====================================================================

-- Login needs a credential column regardless of the diagram; is_active backs the admin
-- "Deactivate" action (index.php?tab=users).
ALTER TABLE users
    ADD COLUMN password_hash VARCHAR(255) NOT NULL AFTER email,
    ADD COLUMN is_active     TINYINT(1)   NOT NULL DEFAULT 1 AFTER role;

-- Items are titled ("Blue JanSport backpack") everywhere in the UI, not just described.
ALTER TABLE lost_reports
    ADD COLUMN item_name VARCHAR(150) NOT NULL AFTER user_id;

-- private_details is what staff compare a claim against; returned_at feeds the hand-over record.
ALTER TABLE found_items
    ADD COLUMN item_name       VARCHAR(150) NOT NULL AFTER user_id,
    ADD COLUMN private_details TEXT         NOT NULL AFTER description,
    ADD COLUMN returned_at     DATETIME     NULL     AFTER status;

-- The ownership-verification flow: what the claimant wrote, who decided, what they said, when.
ALTER TABLE claims
    ADD COLUMN proof_description TEXT         NOT NULL AFTER report_id,
    ADD COLUMN reviewed_by       INT UNSIGNED NULL     AFTER date_claimed,
    ADD COLUMN review_note       TEXT         NULL     AFTER reviewed_by,
    ADD COLUMN reviewed_at       DATETIME     NULL     AFTER review_note,
    ADD CONSTRAINT fk_claims_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- =====================================================================
-- 6. Seed data — the same rows as the mock data layer in config/db_connect.php.
--    Every password is "password123" (bcrypt); change before any real use.
-- =====================================================================

INSERT INTO users (user_id, first_name, last_name, email, password_hash, role, is_active, created_at, updated_at) VALUES
    (1, 'Ana',   'Reyes',      'admin@mapua.edu.ph',           '$2y$10$IAwEs/B32tlkg/sfgBNKRe5sveKeQ9wBgzD87w3nhoFjEtWd0AFAi', 'admin', 1, '2026-08-01 09:00:00', '2026-08-01 09:00:00'),
    (2, 'Marco', 'Santos',     'staff@mapua.edu.ph',           '$2y$10$IAwEs/B32tlkg/sfgBNKRe5sveKeQ9wBgzD87w3nhoFjEtWd0AFAi', 'staff', 1, '2026-08-01 09:05:00', '2026-08-01 09:05:00'),
    (3, 'Jose',  'Dela Cruz',  'student1@mymail.mapua.edu.ph', '$2y$10$IAwEs/B32tlkg/sfgBNKRe5sveKeQ9wBgzD87w3nhoFjEtWd0AFAi', 'user',  1, '2026-08-15 14:20:00', '2026-08-15 14:20:00'),
    (4, 'Bea',   'Lim',        'student2@mymail.mapua.edu.ph', '$2y$10$IAwEs/B32tlkg/sfgBNKRe5sveKeQ9wBgzD87w3nhoFjEtWd0AFAi', 'user',  1, '2026-08-20 10:12:00', '2026-08-20 10:12:00'),
    (5, 'Ramon', 'Villanueva', 'rvillanueva@mapua.edu.ph',     '$2y$10$IAwEs/B32tlkg/sfgBNKRe5sveKeQ9wBgzD87w3nhoFjEtWd0AFAi', 'user',  1, '2026-09-01 08:45:00', '2026-09-01 08:45:00'),
    (6, 'Carla', 'Mendoza',    'student9@mymail.mapua.edu.ph', '$2y$10$IAwEs/B32tlkg/sfgBNKRe5sveKeQ9wBgzD87w3nhoFjEtWd0AFAi', 'user',  0, '2026-09-03 16:30:00', '2026-09-05 10:00:00');

INSERT INTO found_items (item_id, user_id, item_name, category, description, private_details, location_found, storage_location, date_found, image_url, status, returned_at, created_at, updated_at) VALUES
    (1, 2, 'Black JBL earbuds case',            'Electronics', 'Small black charging case for wireless earbuds. Found on a study table near the windows.',       'Case has a deep scratch on the lid. Left earbud is missing; only the right one is inside.',                 'Library',        'Cabinet A, Shelf 1', '2026-09-08', NULL, 'stored',   NULL,                  '2026-09-08 11:40:00', '2026-09-08 11:40:00'),
    (2, 2, 'Blue JanSport backpack',            'Bags',        'Navy blue backpack, medium size, left on the bleachers after PE class.',                          'Contains a green calculus notebook, a folding umbrella, and a Casio watch in the front pocket.',            'Gymnasium',      'Cabinet B, Shelf 2', '2026-09-05', NULL, 'stored',   NULL,                  '2026-09-05 16:05:00', '2026-09-05 16:05:00'),
    (3, 2, 'Mapua student ID card',             'IDs & Cards', 'Student ID card in a clear plastic holder with a red lanyard. Turned in by cafeteria staff.',      'Name on card: Jose Dela Cruz. Student number ends in 4471. Lanyard has a small keychain bear.',             'Cafeteria',      'Drawer 1 (IDs)',     '2026-09-10', NULL, 'stored',   NULL,                  '2026-09-10 13:15:00', '2026-09-11 09:00:00'),
    (4, 2, 'Casio fx-991 scientific calculator','Electronics', 'Grey/black Casio scientific calculator with slide cover. Left in a lecture room.',                'Initials "K.S." written in marker on the back of the slide cover. Battery cover is cracked.',               'North Building', 'Cabinet A, Shelf 3', '2026-09-03', NULL, 'stored',   NULL,                  '2026-09-03 10:20:00', '2026-09-03 10:20:00'),
    (5, 2, 'Silver keychain with 3 keys',       'Keys',        'Keychain with three keys found near the motorcycle parking area.',                               'Has a red bottle-opener tag and one key is a small padlock key.',                                          'Parking Area',   'Drawer 2 (Keys)',    '2026-09-11', NULL, 'stored',   NULL,                  '2026-09-11 08:50:00', '2026-09-11 08:50:00'),
    (6, 2, 'Grey hoodie',                       'Clothing',    'Plain grey pullover hoodie, size medium.',                                                        'Name tag inside collar: "B. Lim". Small bleach stain on the left sleeve.',                                 'Student Lounge', 'Cabinet C, Shelf 1', '2026-08-28', NULL, 'returned', '2026-09-02 15:30:00', '2026-08-28 17:10:00', '2026-09-02 15:30:00'),
    (7, 2, 'Black folding umbrella',            'Accessories', 'Compact black umbrella, unbranded.',                                                              'Handle has a piece of yellow tape wrapped around it.',                                                     'Admin Building', 'Bin 4 (Misc)',       '2026-07-14', NULL, 'disposed', NULL,                  '2026-07-14 09:00:00', '2026-08-30 12:00:00');

INSERT INTO lost_reports (report_id, user_id, item_name, category, description, location_lost, date_lost, image_url, status, created_at, updated_at) VALUES
    (1, 3, 'JBL wireless earbuds (black)',  'Electronics',   'JBL Tune 230 earbuds in a black case. The case lid has a scratch and I think the left earbud was already out of the case when I lost it.', 'Library',        '2026-09-07', NULL, 'open',    '2026-09-07 18:25:00', '2026-09-07 18:25:00'),
    (2, 3, 'Student ID with red lanyard',   'IDs & Cards',   'My Mapua ID in a clear holder. The lanyard has a tiny bear keychain.',                                                                      'Cafeteria',      '2026-09-10', NULL, 'matched', '2026-09-10 14:00:00', '2026-09-11 09:00:00'),
    (3, 4, 'Red Hydro Flask water bottle',  'Other',         '32 oz red bottle with a sticker of a cat on the side.',                                                                                    'Covered Court',  '2026-09-09', NULL, 'open',    '2026-09-09 12:10:00', '2026-09-09 12:10:00'),
    (4, 3, 'Physics textbook (Serway)',     'Books & Notes', 'Hardbound physics textbook with my name on the first page.',                                                                               'South Building', '2026-08-20', NULL, 'closed',  '2026-08-20 09:30:00', '2026-08-25 11:00:00'),
    (5, 5, 'Grey hoodie',                   'Clothing',      'Grey pullover hoodie, medium. Has a name tag inside the collar.',                                                                          'Student Lounge', '2026-08-27', NULL, 'closed',  '2026-08-27 20:00:00', '2026-09-02 15:30:00');

INSERT INTO claims (claim_id, item_id, user_id, report_id, proof_description, status, date_claimed, reviewed_by, review_note, reviewed_at, created_at, updated_at) VALUES
    (1, 1, 3, 1,    'These are JBL Tune 230 earbuds. The case has a scratch on the lid, and only the right earbud should be inside because I had the left one in my ear when I lost the case.', 'pending',  '2026-09-09', NULL, NULL,                                                                                                          NULL,                  '2026-09-09 08:15:00', '2026-09-09 08:15:00'),
    (2, 3, 3, 2,    'It is my student ID. My student number ends in 4471 and the lanyard has a small bear keychain.',                                                                           'approved', '2026-09-10', 2,    'Details match. Please bring a valid ID to the Lost & Found office (Admin Bldg, Rm 104) to claim.',            '2026-09-11 09:00:00', '2026-09-10 15:30:00', '2026-09-11 09:00:00'),
    (3, 2, 4, NULL, 'Blue backpack with my laptop and charger inside.',                                                                                                                         'rejected', '2026-09-06', 2,    'Described contents do not match what was logged at intake.',                                                  '2026-09-06 10:45:00', '2026-09-06 09:20:00', '2026-09-06 10:45:00'),
    (4, 2, 5, NULL, 'Navy JanSport. There should be a green calculus notebook, a folding umbrella and my Casio watch in the front pocket.',                                                    'pending',  '2026-09-11', NULL, NULL,                                                                                                          NULL,                  '2026-09-11 17:05:00', '2026-09-11 17:05:00');
