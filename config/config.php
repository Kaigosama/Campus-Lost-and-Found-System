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

const CATEGORIES = ['Electronics', 'IDs & Cards', 'Bags', 'Clothing', 'Books & Notes', 'Keys', 'Accessories', 'Other'];

const ROLES = [
    'user'         => 'Student / Faculty',
    'staff'        => 'Security & Maintenance',
    'admin'        => 'Office Administrator',
    'master_admin' => 'Master Administrator',
];
const ADMIN_ROLES = ['admin', 'master_admin'];
/** Who handles found items, lost reports, claims and student posts. Admins manage accounts only and see none of these records. */
const REVIEWER_ROLES = ['staff'];
const LOST_STATUSES  = ['open' => 'Open', 'matched' => 'Matched', 'closed' => 'Closed'];
/** Staff outcomes for a lost report (api/moderate_report.php). Final: the report leaves the normal lists and can't be edited. */
const REPORT_MODERATION_STATUSES = ['rejected' => 'Rejected', 'false_report' => 'False report', 'spam' => 'Spam'];
const FOUND_STATUSES = ['stored' => 'In Storage', 'returned' => 'Returned', 'disposed' => 'Disposed'];
const CLAIM_STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

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
const NAME_MIN        = 2;   // first name
const RESET_LINK_MINUTES = 60;
const VERIFY_LINK_HOURS  = 24;
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
const OFFICE_INFO = 'the Lost & Found office (Admin Bldg, Rm 104, Mon–Fri 8:00 AM–5:00 PM)';
const LOCKED_MESSAGE = 'This account is locked after too many failed log-in attempts. Visit ' . OFFICE_INFO . ' with your ID to have it unlocked.';
/** Person names: letters (any script), spaces, hyphens, apostrophes and periods — and must start with a letter. */
const NAME_PATTERN    = "/^\\p{L}[\\p{L}\\p{M} .'\\-]*$/u";

/* ---------------------------------------------------------------- Database */

// Per-machine overrides (git-ignored) may define() any DB_* constant before the defaults apply.
if (is_file(__DIR__ . '/db_connect.local.php')) {
    require __DIR__ . '/db_connect.local.php';
}
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
