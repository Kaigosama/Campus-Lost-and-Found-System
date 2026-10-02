-- =====================================================================
--  001 — account security, session tracking and post moderation.
--  Applied once by database/seed.php (recorded in schema_migrations).
--  Existing rows are kept: current accounts are marked verified and
--  existing found items approved, so nothing disappears or locks out.
-- =====================================================================

-- Email verification, lockout and activity. Email stays the unique login; names are not unique.
ALTER TABLE users
    MODIFY COLUMN role ENUM('user','staff','admin','master_admin') NOT NULL DEFAULT 'user',
    ADD COLUMN email_verified                TINYINT(1)       NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN email_verified_at             DATETIME         NULL AFTER email_verified,
    ADD COLUMN email_verification_token_hash CHAR(64)         NULL AFTER email_verified_at,   -- SHA-256 of the emailed token
    ADD COLUMN email_verification_expires_at DATETIME         NULL AFTER email_verification_token_hash,
    ADD COLUMN failed_login_attempts         TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER email_verification_expires_at,
    ADD COLUMN locked_until                  DATETIME         NULL AFTER failed_login_attempts,
    ADD COLUMN last_login_at                 DATETIME         NULL AFTER locked_until,
    ADD COLUMN last_activity_at              DATETIME         NULL AFTER last_login_at,
    ADD UNIQUE KEY uq_users_verification (email_verification_token_hash);

UPDATE users SET email_verified = 1, email_verified_at = created_at;

-- Students and faculty may post items they found; staff approve them before they are public.
ALTER TABLE found_items
    ADD COLUMN moderation_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER status,
    ADD COLUMN moderated_by      INT UNSIGNED NULL AFTER moderation_status,
    ADD COLUMN moderation_note   TEXT         NULL AFTER moderated_by,
    ADD COLUMN moderated_at      DATETIME     NULL AFTER moderation_note,
    ADD KEY idx_found_moderation (moderation_status),
    ADD CONSTRAINT fk_found_moderator FOREIGN KEY (moderated_by) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- One row per login. The browser holds a random token; only its hash is stored here.
CREATE TABLE user_sessions (
    session_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED  NOT NULL,
    token_hash       CHAR(64)      NOT NULL,
    status           ENUM('active','logged_out','expired','revoked') NOT NULL DEFAULT 'active',
    ip_address       VARCHAR(45)   NULL,
    user_agent       VARCHAR(255)  NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at       DATETIME      NOT NULL,                       -- last_activity_at + SESSION_IDLE_MINUTES
    ended_at         DATETIME      NULL,                           -- logout, expiry or revocation time
    PRIMARY KEY (session_id),
    UNIQUE KEY uq_sessions_token (token_hash),
    KEY idx_sessions_user (user_id, status),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Login history and other security events, viewable by admins.
CREATE TABLE security_events (
    event_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NULL,
    email       VARCHAR(190)    NULL,                              -- as typed, for attempts on unknown accounts
    event_type  VARCHAR(40)     NOT NULL,
    session_id  INT UNSIGNED    NULL,
    ip_address  VARCHAR(45)     NULL,
    user_agent  VARCHAR(255)    NULL,
    metadata    JSON            NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_events_user (user_id, created_at),
    KEY idx_events_type (event_type, created_at),
    CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Two regular admins and one master admin in total. Same published sample password as the other seed
-- accounts; seed.php replaces it with SEED_PASSWORD when that is set (always set it on a public deploy).
INSERT IGNORE INTO users (first_name, last_name, email, password_hash, role, is_active, email_verified, email_verified_at) VALUES
    ('Liza',  'Navarro', 'admin2@mapua.edu.ph',       '$2y$10$IAwEs/B32tlkg/sfgBNKRe5sveKeQ9wBgzD87w3nhoFjEtWd0AFAi', 'admin',        1, 1, NOW()),
    ('Paolo', 'Ramos',   'masteradmin@mapua.edu.ph',  '$2y$10$IAwEs/B32tlkg/sfgBNKRe5sveKeQ9wBgzD87w3nhoFjEtWd0AFAi', 'master_admin', 1, 1, NOW());
