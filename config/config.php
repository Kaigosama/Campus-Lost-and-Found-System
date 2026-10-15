<?php
declare(strict_types=1);

/**
 * Settings: paths, app constants and the database credentials.
 * DB_* come from config/db_connect.local.php, else the environment, else the XAMPP defaults.
 */

define('APP_ROOT', dirname(__DIR__));
date_default_timezone_set('Asia/Manila');

/** '' when public/ is the web root (php -S localhost:8000 -t public); '/clafs' for an Apache alias. */
const BASE_URL = '';

/* ---------------------------------------------------------------- Constants */

const APP_NAME      = 'Cardinal Finds';
const APP_FULL_NAME = 'Campus Lost & Found';   // tagline under the name and in the home page title

/** Form categories live in the `categories` table (admin: index.php?tab=categories). This name asks the user to describe it. */
const OTHER_CATEGORY = 'Other';

const ROLES = [
    'user'         => 'Student / Faculty',
    'staff'        => 'Security & Maintenance',
    'admin'        => 'Office Administrator',
    'master_admin' => 'Master Administrator',
];
const ADMIN_ROLES = ['admin', 'master_admin'];
/** Who handles found items, lost reports, claims and student posts: review, log, edit, verify, hand over, dispose. */
const REVIEWER_ROLES = ['staff'];
/** Who may look at items, reports, claims, returns, disposals and their history (read-only for admins). */
const MONITOR_ROLES = ['staff', 'admin', 'master_admin'];
const LOST_STATUSES  = ['open' => 'Open', 'matched' => 'Matched', 'closed' => 'Closed'];
/** Staff outcomes for a lost report (api/moderate_report.php). Final: the report leaves the normal lists and can't be edited. */
const REPORT_MODERATION_STATUSES = ['rejected' => 'Rejected', 'false_report' => 'False report', 'spam' => 'Spam'];
/** "returned" and "disposed" are final: the item can no longer be edited, claimed or changed back. */
const FOUND_STATUSES = ['stored' => 'In Storage', 'returned' => 'Returned', 'disposed' => 'Disposed'];
/**
 * Main claim status: pending → approved (for collection) → verified (identity checked at the desk) → returned.
 * A rejection keeps the claim "pending"; it is the claim's review outcome, kept in claim_reviews.
 */
const CLAIM_STATUSES = ['pending' => 'Pending', 'approved' => 'Approved for collection', 'verified' => 'Identity verified', 'returned' => 'Returned'];
/**
 * IDs staff may accept at pickup. Placeholder list until the school confirms its policy: edit it here.
 * Only the type and the last four characters of the number are stored (claim_verifications).
 */
const ID_TYPES = [
    'school_id'     => 'Mapúa school ID (student or employee)',
    'government_id' => 'Government-issued photo ID',
    'other'         => 'Other ID allowed by the office (describe in notes)',
];
/** Days a found item is held before staff may dispose of it. Placeholder until the school confirms its policy. */
const DISPOSAL_HOLD_DAYS = 30;
/** Masked ID references are cleared from claim_verifications after this many days (the check itself stays). */
const ID_RECORD_RETENTION_DAYS = 365;

/* ---------------------------------------------------------------- School registration */

/** Format of a school identifier, checked before the reference tables are searched. */
const STUDENT_NUMBER_PATTERN  = '/^\d{10}$/';        // e.g. 2023100001
const EMPLOYEE_NUMBER_PATTERN = '/^EMP-\d{5}$/';     // e.g. EMP-10001
/** Reference records that may register. Others (graduated, withdrawn, separated, retired) are refused. */
const ELIGIBLE_STUDENT_STATUSES  = ['enrolled', 'on_leave'];
const ELIGIBLE_EMPLOYEE_STATUSES = ['active', 'on_leave'];
/** Record look-ups per IP per LIMIT_WINDOW_MINUTES, so the reference data can't be harvested. */
const LOOKUP_MAX_PER_IP = 10;
/** Where people whose record can't be verified are sent. */
const RECORDS_OFFICE = "the Registrar's Office (students) or Human Resources (employees)";

/** Anonymous visitor records (visitors table) unseen for this long are deleted. */
const VISITOR_RETENTION_DAYS = 90;

/** The only choices for where an item was found or lost (Mapúa Makati campus); database/migrations/009 moved older values onto them. */
const CAMPUS_LOCATIONS = [
    'Main Entrance & Lobby', 'Hallways & Elevators', 'Classrooms', 'Computer Labs', 'Science & Health Labs',
    'Media Studios', 'Library', 'Student Lounge', 'Canteen & Café', 'Gymnasium', 'Cinema', 'Prayer Room',
    'Student Services Office', 'Restrooms', 'Parking Area', 'Other',
];
/** The only places in the Lost & Found office where staff store an item; database/migrations/010 moved older values onto them. */
const STORAGE_LOCATIONS = [
    'Cabinet A, Shelf 1', 'Cabinet B, Shelf 2', 'Cabinet C, Shelf 1',
    'Drawer 1 (IDs)', 'Drawer 2 (Keys)', 'Bin 4 (Misc)',
];

const MAX_UPLOAD_MB   = 5;
const UPLOAD_DIR      = APP_ROOT . '/public/uploads';
const IMAGE_TYPES     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const PASSWORD_MIN    = 8;
const RESET_LINK_MINUTES = 60;
/** Unverified Student / Faculty accounts are deleted this long after their verification link expired. */
const UNVERIFIED_GRACE_HOURS = 24;
/** A session ends after this long without an authenticated request. */
const SESSION_IDLE_MINUTES = 30;
/** Consecutive wrong passwords that lock an account (not the master admin) until an admin unlocks it. */
const LOCKOUT_ATTEMPTS = 3;
/** Requests allowed per LIMIT_WINDOW_MINUTES before further tries from that IP are refused. */
const LOGIN_MAX_PER_IP     = 20;
const RESET_MAX_PER_IP     = 5;
const LIMIT_WINDOW_MINUTES = 15;
/** Lost reports one account may file per REPORT_LIMIT_WINDOW_MINUTES (counted in lost_reports, so it holds across browsers). */
const REPORT_LIMIT_MAX            = 3;
const REPORT_LIMIT_WINDOW_MINUTES = 60;
/** The account's own open/matched reports from this many days back are checked for duplicates of a new one. */
const DUPLICATE_LOOKBACK_DAYS = 30;
/** Confirmed false reports that deactivate a student / faculty account; each one before that is a warning. */
const FALSE_REPORTS_TO_DEACTIVATE = 2;
const OFFICE_INFO = 'the Lost & Found desk at the Admin Office, Mapúa Makati (Mon–Fri 8:00 AM–5:00 PM)';
const LOCKED_MESSAGE = 'This account is locked after too many failed log-in attempts. Visit ' . OFFICE_INFO . ' with your ID to have it unlocked.';

/* ---------------------------------------------------------------- Database */

// Per-machine overrides (git-ignored) may define() any DB_* constant before the defaults apply.
if (is_file(__DIR__ . '/db_connect.local.php')) {
    require __DIR__ . '/db_connect.local.php';
}
/** Minutes an email verification link stays valid: EMAIL_VERIFICATION_EXPIRY_MINUTES in the environment, else 30. */
defined('EMAIL_VERIFICATION_EXPIRY_MINUTES')
    || define('EMAIL_VERIFICATION_EXPIRY_MINUTES', max(1, (int) (getenv('EMAIL_VERIFICATION_EXPIRY_MINUTES') ?: 30)));
// Containers (Docker, Railway) set the environment instead: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS,
// or a single MYSQL_URL / DATABASE_URL (mysql://user:pass@host:port/name). Individual DB_* win over the URL.
$dbUrlString = (string) (getenv('MYSQL_URL') ?: getenv('DATABASE_URL'));
$dbUrl = $dbUrlString !== '' ? (parse_url($dbUrlString) ?: []) : [];
$dbFromUrl = [
    'DB_HOST' => $dbUrl['host'] ?? null,
    'DB_PORT' => $dbUrl['port'] ?? null,
    'DB_NAME' => isset($dbUrl['path']) && $dbUrl['path'] !== '/' ? ltrim($dbUrl['path'], '/') : null,
    'DB_USER' => isset($dbUrl['user']) ? rawurldecode($dbUrl['user']) : null,
    'DB_PASS' => isset($dbUrl['pass']) ? rawurldecode($dbUrl['pass']) : null,
];
foreach ($dbFromUrl as $name => $fromUrl) {
    $value = getenv($name);
    if (!defined($name) && ($value !== false || $fromUrl !== null)) {
        define($name, $value !== false ? $value : $fromUrl);
    }
}
unset($dbUrlString, $dbUrl, $dbFromUrl, $name, $fromUrl, $value);
defined('DB_HOST')    || define('DB_HOST', '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT', 3306);
defined('DB_NAME')    || define('DB_NAME', 'clafs');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');
