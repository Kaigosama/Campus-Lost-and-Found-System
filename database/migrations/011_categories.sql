-- =====================================================================
--  011 — form categories move from CATEGORIES in config/config.php into
--  a `categories` table that administrators manage (index.php?tab=categories).
--  Lost reports and found items now point to a category row by id.
--  * The old free-text `category` column becomes `category_detail`: it keeps
--    only what a user typed after choosing "Other"; every listed name moves
--    to category_id. Nothing is lost and display names stay the same.
--  * A category in use can't be deleted (ON DELETE RESTRICT); an admin
--    deactivates it instead, which hides it from new forms only.
--  updated_at is kept, so the change does not look like a user's edit.
-- =====================================================================

CREATE TABLE categories (
    category_id  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name         VARCHAR(50)   NOT NULL,                          -- unique ignoring case (utf8mb4_unicode_ci)
    description  VARCHAR(255)  NULL,
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,                -- 0: hidden from new submissions, kept on old records
    created_by   INT UNSIGNED  NULL,                              -- admin who added it; NULL for the original eight
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_categories_name (name),
    KEY idx_categories_active (is_active, name),
    CONSTRAINT fk_categories_creator FOREIGN KEY (created_by) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO categories (name, description) VALUES
    ('Electronics',   'Phones, earbuds, chargers, calculators, laptops'),
    ('IDs & Cards',   'School IDs, licences, bank and transit cards'),
    ('Bags',          'Backpacks, pouches, totes, wallets'),
    ('Clothing',      'Jackets, hoodies, uniforms, caps'),
    ('Books & Notes', 'Textbooks, notebooks, reviewers'),
    ('Keys',          'Keys and keychains'),
    ('Accessories',   'Umbrellas, watches, jewellery, eyewear'),
    ('Other',         'Anything else; the user describes it');

ALTER TABLE lost_reports
    CHANGE COLUMN category category_detail VARCHAR(50) NULL,
    ADD COLUMN category_id INT UNSIGNED NULL AFTER item_name;
ALTER TABLE found_items
    CHANGE COLUMN category category_detail VARCHAR(50) NULL,
    ADD COLUMN category_id INT UNSIGNED NULL AFTER item_name;

-- Listed names become a category_id; anything typed into "Other" stays as the detail of the Other category.
UPDATE lost_reports r JOIN categories c ON c.name = r.category_detail COLLATE utf8mb4_unicode_ci
SET r.updated_at = r.updated_at, r.category_id = c.category_id, r.category_detail = NULL;
UPDATE lost_reports SET updated_at = updated_at, category_id = (SELECT category_id FROM categories WHERE name = 'Other')
WHERE category_id IS NULL;
UPDATE found_items f JOIN categories c ON c.name = f.category_detail COLLATE utf8mb4_unicode_ci
SET f.updated_at = f.updated_at, f.category_id = c.category_id, f.category_detail = NULL;
UPDATE found_items SET updated_at = updated_at, category_id = (SELECT category_id FROM categories WHERE name = 'Other')
WHERE category_id IS NULL;

ALTER TABLE lost_reports
    MODIFY COLUMN category_id INT UNSIGNED NOT NULL,
    ADD KEY idx_lost_category (category_id),
    ADD CONSTRAINT fk_lost_category FOREIGN KEY (category_id) REFERENCES categories (category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE found_items
    MODIFY COLUMN category_id INT UNSIGNED NOT NULL,
    ADD KEY idx_found_category (category_id),
    ADD CONSTRAINT fk_found_category FOREIGN KEY (category_id) REFERENCES categories (category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT;
