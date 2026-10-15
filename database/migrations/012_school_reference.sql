-- =====================================================================
--  012 — registration is checked against the school's own records.
--  student_records and employee_records hold the school reference data;
--  a new Student / Faculty account must name one of them (users.student_number
--  or users.employee_number) and gets its name from it, not from the form.
--  * Each school identifier can belong to one account only (unique keys).
--  * A reference row linked to an account can't be deleted or renumbered
--    (RESTRICT; MySQL allows the one-identifier CHECK only without cascades).
--  * Existing accounts (staff, administrators, earlier sign-ups) keep NULL in
--    both columns; they are unaffected.
--  * birth_date is used only to reject passwords that contain it. It is never
--    copied into users and never sent to the browser.
--
--  THE ROWS BELOW ARE FICTIONAL SAMPLE DATA for development and testing. They
--  are not real Mapúa students or employees, and the .test email domain can't
--  receive mail. Replace them with authorized school records before real use
--  (see docs/REVISIONS.md, "Registration configuration").
-- =====================================================================

CREATE TABLE student_records (
    student_number   VARCHAR(20)   NOT NULL,                      -- STUDENT_NUMBER_PATTERN in config/config.php
    first_name       VARCHAR(100)  NOT NULL,
    last_name        VARCHAR(100)  NOT NULL,
    birth_date       DATE          NULL,                          -- password check only
    school_email     VARCHAR(190)  NULL,
    college          VARCHAR(150)  NULL,
    program          VARCHAR(150)  NULL,
    academic_status  ENUM('enrolled','on_leave','graduated','withdrawn') NOT NULL DEFAULT 'enrolled',
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_number),
    UNIQUE KEY uq_student_records_email (school_email)
) ENGINE=InnoDB;

CREATE TABLE employee_records (
    employee_number    VARCHAR(20)   NOT NULL,                    -- EMPLOYEE_NUMBER_PATTERN in config/config.php
    first_name         VARCHAR(100)  NOT NULL,
    last_name          VARCHAR(100)  NOT NULL,
    birth_date         DATE          NULL,                        -- password check only
    school_email       VARCHAR(190)  NULL,
    department         VARCHAR(150)  NULL,
    employment_status  ENUM('active','on_leave','separated','retired') NOT NULL DEFAULT 'active',
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_number),
    UNIQUE KEY uq_employee_records_email (school_email)
) ENGINE=InnoDB;

ALTER TABLE users
    ADD COLUMN student_number  VARCHAR(20) NULL AFTER email,
    ADD COLUMN employee_number VARCHAR(20) NULL AFTER student_number,
    ADD UNIQUE KEY uq_users_student_number (student_number),
    ADD UNIQUE KEY uq_users_employee_number (employee_number),
    ADD CONSTRAINT fk_users_student FOREIGN KEY (student_number) REFERENCES student_records (student_number)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    ADD CONSTRAINT fk_users_employee FOREIGN KEY (employee_number) REFERENCES employee_records (employee_number)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    ADD CONSTRAINT chk_users_one_school_id CHECK (student_number IS NULL OR employee_number IS NULL);

-- Fictional sample students. Two are not eligible to register (graduated, withdrawn) for testing.
INSERT INTO student_records (student_number, first_name, last_name, birth_date, school_email, college, program, academic_status) VALUES
    ('2023100001', 'Andrea',   'Villareal',  '2005-03-14', 'avillareal@student.cardinal.test', 'School of Information Technology',      'BS Information Technology',  'enrolled'),
    ('2023100002', 'Miguel',   'Santiago',   '2004-10-25', 'msantiago@student.cardinal.test',  'School of Information Technology',      'BS Computer Science',        'enrolled'),
    ('2023100003', 'Bianca',   'Dela Paz',   '2005-07-02', 'bdelapaz@student.cardinal.test',   'School of Civil Engineering',           'BS Civil Engineering',       'enrolled'),
    ('2022100004', 'Rafael',   'Manalo',     '2004-01-30', 'rmanalo@student.cardinal.test',    'School of Business and Management',     'BS Accountancy',             'enrolled'),
    ('2022100005', 'Katrina',  'Soriano',    '2003-12-09', 'ksoriano@student.cardinal.test',   'School of Media Studies',               'BA Multimedia Arts',         'enrolled'),
    ('2024100006', 'Joaquin',  'Esguerra',   '2006-05-21', 'jesguerra@student.cardinal.test',  'School of Electrical Engineering',      'BS Electronics Engineering', 'enrolled'),
    ('2024100007', 'Patricia', 'Lacson',     '2006-08-17', 'placson@student.cardinal.test',    'School of Health Sciences',             'BS Nursing',                 'enrolled'),
    ('2021100008', 'Gabriel',  'Ocampo',     '2003-04-11', 'gocampo@student.cardinal.test',    'School of Architecture',                'BS Architecture',            'on_leave'),
    ('2020100009', 'Isabel',   'Navarro',    '2002-09-05', 'inavarro@student.cardinal.test',   'School of Information Technology',      'BS Information Technology',  'graduated'),
    ('2022100010', 'Daniel',   'Aquino',     '2004-06-28', 'daquino@student.cardinal.test',    'School of Mechanical Engineering',      'BS Mechanical Engineering',  'withdrawn'),
    ('2023100011', 'Sofia',    'Bautista',   NULL,         'sbautista@student.cardinal.test',  'School of Chemical Engineering',        'BS Chemical Engineering',    'enrolled'),
    ('2024100012', 'Lorenzo',  'Mercado',    '2006-02-14', NULL,                               'School of Information Technology',      'BS Data Science',            'enrolled');

-- Fictional sample employees (faculty and office personnel). One is separated, so not eligible.
INSERT INTO employee_records (employee_number, first_name, last_name, birth_date, school_email, department, employment_status) VALUES
    ('EMP-10001', 'Teresa',   'Gonzales',  '1980-11-03', 'tgonzales@staff.cardinal.test', 'School of Information Technology', 'active'),
    ('EMP-10002', 'Ramon',    'Castillo',  '1975-02-19', 'rcastillo@staff.cardinal.test', 'Department of Mathematics',        'active'),
    ('EMP-10003', 'Cristina', 'Valdez',    '1988-06-30', 'cvaldez@staff.cardinal.test',   'Registrar''s Office',              'active'),
    ('EMP-10004', 'Eduardo',  'Ramirez',   '1969-09-12', 'eramirez@staff.cardinal.test',  'Department of Physics',            'on_leave'),
    ('EMP-10005', 'Melissa',  'Torres',    NULL,         'mtorres@staff.cardinal.test',   'Library',                          'active'),
    ('EMP-10006', 'Victor',   'Salazar',   '1972-04-08', 'vsalazar@staff.cardinal.test',  'Facilities Office',                'separated');
