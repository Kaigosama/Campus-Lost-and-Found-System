<?php
declare(strict_types=1);

/**
 * Application core — the one file every page and API endpoint requires first:
 *
 *     require_once __DIR__ . '/config/db_connect.php';        (root pages)
 *     require_once __DIR__ . '/../config/db_connect.php';     (api/)
 *
 * Contents, top to bottom:
 *   1. App constants (name, categories, roles, statuses) — shared by the forms,
 *      the API validation and the SQL ENUMs in docs/schema.sql.
 *   2. MySQL settings + db() — the PDO connection, opened on first use.
 *   3. Shared helpers: escaping, URLs, formatting, HTML fragments, JSON responses.
 *   4. PREVIEW auth stub — ?as=guest|user|staff|admin until real login exists.
 *   5. Data layer — mock rows today; the backend phase rewrites these function
 *      bodies as queries through db() and nothing else changes.
 */

define('APP_ROOT', dirname(__DIR__));

/** '' when the repo root is the web root (php -S localhost:8000); '/clafs' for an Apache alias. */
const BASE_URL = '';

/* =========================================================================
 * 1. App constants
 * ========================================================================= */

const APP_NAME      = 'CLAFS';
const APP_FULL_NAME = 'Campus Lost-and-Found System';

const CATEGORIES = ['Electronics', 'IDs & Cards', 'Bags', 'Clothing', 'Books & Notes', 'Keys', 'Accessories', 'Other'];

const ROLES          = ['user' => 'User', 'staff' => 'Staff', 'admin' => 'Administrator'];
const LOST_STATUSES  = ['open' => 'Open', 'matched' => 'Matched', 'closed' => 'Closed'];
const FOUND_STATUSES = ['stored' => 'In Storage', 'returned' => 'Returned', 'disposed' => 'Disposed'];
const CLAIM_STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

const ALLOWED_EMAIL_DOMAINS = ['mymail.mapua.edu.ph', 'mapua.edu.ph'];

/** Suggested values for the location <datalist>s on the forms. */
const CAMPUS_LOCATIONS = ['Library', 'Cafeteria', 'Gymnasium', 'Student Lounge', 'Parking Area', 'North Building', 'South Building', 'Admin Building', 'Chapel', 'Covered Court'];

const MAX_UPLOAD_MB = 5;

/* =========================================================================
 * 2. MySQL connection (XAMPP defaults)
 *
 * Per-machine overrides go in config/db_connect.local.php (git-ignored),
 * which may define() any DB_* constant before the defaults apply.
 * Nothing connects during the front-end phase. Schema: docs/schema.sql.
 * ========================================================================= */

if (is_file(__DIR__ . '/db_connect.local.php')) {
    require __DIR__ . '/db_connect.local.php';
}

defined('DB_HOST')    || define('DB_HOST', '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT', 3306);
defined('DB_NAME')    || define('DB_NAME', 'clafs');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

/** Shared PDO connection, opened on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/* =========================================================================
 * 3. Shared helpers
 * ========================================================================= */

/** Escape a value for safe output inside HTML. Use on every dynamic value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an app URL from a path relative to the web root, e.g. url('/browse.php'). */
function url(string $path = '/'): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL for a static file inside public/, e.g. asset('css/styles.css'). */
function asset(string $path): string
{
    return url('/public/' . ltrim($path, '/'));
}

/** Detail page for a found item ('found') or a lost report ('lost'). */
function item_url(string $type, int $id): string
{
    return url('/view_item.php?type=' . $type . '&id=' . $id);
}

/**
 * Returns "active" when the current script is $path. $when narrows pages that
 * serve several views, e.g. is_active('/browse.php', $type === 'found').
 */
function is_active(string $path, bool $when = true): string
{
    return $when && ($_SERVER['SCRIPT_NAME'] ?? '') === url($path) ? 'active' : '';
}

/** Current URL with some query parameters replaced — used by filters, pagination and the preview switcher. */
function url_with(array $params): string
{
    $query = array_filter(array_merge($_GET, $params), fn ($v) => $v !== null && $v !== '');
    $path  = $_SERVER['SCRIPT_NAME'] ?? url('/');
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/** Human-readable label for any status key. */
function status_label(string $status): string
{
    $labels = LOST_STATUSES + FOUND_STATUSES + CLAIM_STATUSES;
    return $labels[$status] ?? ucfirst($status);
}

/** Coloured status pill. */
function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(status_label($status)) . '</span>';
}

/** "Sep 8, 2026" from a date or datetime string. */
function format_date(?string $value): string
{
    return $value ? date('M j, Y', strtotime($value)) : '—';
}

/** "Sep 8, 2026 · 2:15 PM" */
function format_datetime(?string $value): string
{
    return $value ? date('M j, Y · g:i A', strtotime($value)) : '—';
}

function full_name(array $user): string
{
    return trim($user['first_name'] . ' ' . $user['last_name']);
}

function initials(array $user): string
{
    return mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
}

/** <img> for an item photo (image_url is relative to public/, e.g. "uploads/abc.jpg"), or a placeholder block. */
function photo_tag(?string $imageUrl, string $alt, string $class = 'photo'): string
{
    if ($imageUrl) {
        return '<img src="' . e(asset($imageUrl)) . '" alt="' . e($alt) . '" class="' . e($class) . '" loading="lazy">';
    }
    return '<div class="' . e($class) . ' photo-placeholder" role="img" aria-label="No photo available">'
        . '<svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
        . '<path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg>'
        . '<span>No photo</span></div>';
}

/** <option> list from an assoc array (value => label) or plain list, with the selected value marked. */
function options(array $items, mixed $selected = null, bool $isAssoc = true): string
{
    $html = '';
    foreach ($items as $key => $label) {
        $value = $isAssoc ? $key : $label;
        $sel   = ((string) $value === (string) $selected) ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $html;
}

/** Truncate text to $length characters, adding an ellipsis. */
function excerpt(string $text, int $length = 110): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
}

/** Split an array into pages; returns [items_on_page, current_page, total_pages]. */
function paginate(array $items, int $perPage = 8): array
{
    $total = max(1, (int) ceil(count($items) / $perPage));
    $page  = min($total, max(1, (int) ($_GET['page'] ?? 1)));
    return [array_slice($items, ($page - 1) * $perPage, $perPage), $page, $total];
}

/** Sort rows newest-first by a datetime column. */
function newest_first(array $rows, string $column = 'created_at'): array
{
    usort($rows, fn ($a, $b) => strcmp($b[$column], $a[$column]));
    return $rows;
}

/** Centered "nothing here" block with an optional call-to-action. */
function empty_state(string $title, string $text = '', string $actionUrl = '', string $actionLabel = 'Continue', string $icon = '&#128269;'): string
{
    $html = '<div class="empty-state"><div class="empty-icon" aria-hidden="true">' . $icon . '</div><h2>' . e($title) . '</h2>';
    if ($text !== '') {
        $html .= '<p>' . e($text) . '</p>';
    }
    if ($actionUrl !== '') {
        $html .= '<p><a class="btn btn-primary" href="' . e($actionUrl) . '">' . e($actionLabel) . '</a></p>';
    }
    return $html . '</div>';
}

/** Found-item card for the browse grid. Only PUBLIC fields — never storage_location or private_details. */
function item_card(array $item): string
{
    $href = item_url('found', $item['item_id']);
    ob_start(); ?>
<article class="item-card">
    <a class="item-card-photo" href="<?= e($href) ?>"><?= photo_tag($item['image_url'], $item['item_name']) ?></a>
    <div class="item-card-body">
        <span class="item-card-category"><?= e($item['category']) ?></span>
        <h3 class="item-card-title"><a href="<?= e($href) ?>"><?= e($item['item_name']) ?></a></h3>
        <p class="item-card-desc"><?= e(excerpt($item['description'])) ?></p>
        <dl class="item-card-meta">
            <div><dt>Found</dt><dd><?= e(format_date($item['date_found'])) ?></dd></div>
            <div><dt>Where</dt><dd><?= e($item['location_found']) ?></dd></div>
        </dl>
    </div>
    <div class="item-card-footer">
        <?= status_badge($item['status']) ?>
        <a class="btn btn-outline btn-sm" href="<?= e($href) ?>">View details</a>
    </div>
</article>
<?php
    return ob_get_clean();
}

/** Prev / 1 2 3 / Next links that keep the current filters. Empty when there is one page. */
function pagination(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }
    ob_start(); ?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e(url_with(['page' => $page - 1])) ?>" rel="prev">&laquo; Prev</a>
    <?php else: ?>
        <span class="disabled">&laquo; Prev</span>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current" aria-current="page"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= e(url_with(['page' => $i])) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
        <a href="<?= e(url_with(['page' => $page + 1])) ?>" rel="next">Next &raquo;</a>
    <?php else: ?>
        <span class="disabled">Next &raquo;</span>
    <?php endif; ?>
</nav>
<?php
    return ob_get_clean();
}

/** Pill tabs from [key => label] with a count per key; $param is the query parameter that selects a tab. */
function pill_tabs(array $tabs, array $counts, string $current, string $param): string
{
    $html = '<nav class="pill-tabs" aria-label="Filter">';
    foreach ($tabs as $key => $label) {
        $active = $current === $key ? ' class="active"' : '';
        $count  = isset($counts[$key]) ? ' <span class="count">' . $counts[$key] . '</span>' : '';
        $html  .= '<a href="' . e(url_with([$param => $key === '' ? null : $key, 'page' => null])) . '"' . $active . '>' . e($label) . $count . '</a>';
    }
    return $html . '</nav>';
}

/** Render a full-page message (404, 403, …) inside the normal layout and stop. */
function abort(int $code, string $title, string $text = '', string $backUrl = '', string $backLabel = 'Go back', string $icon = '&#128269;'): void
{
    http_response_code($code);
    $pageTitle = $title;
    include APP_ROOT . '/includes/header.php';
    echo empty_state($title, $text, $backUrl, $backLabel, $icon);
    include APP_ROOT . '/includes/footer.php';
    exit;
}

/* ---- JSON responses for api/ ---- */

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(int $code, string $message, array $extra = []): void
{
    json_response(['ok' => false, 'error' => $message] + $extra, $code);
}

/** Request body for the API: decoded JSON when sent as application/json, otherwise the form fields. */
function json_input(): array
{
    if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

/* =========================================================================
 * 4. PREVIEW AUTH STUB — no real authentication yet.
 *
 * Pages call current_user(), has_role(), require_login() and require_role()
 * exactly as they will once sessions exist. For now the "logged-in user" is
 * chosen with ?as=guest|user|staff|admin (remembered in a cookie) so every
 * screen can be previewed in every role. The backend phase replaces the
 * bodies of these functions; the pages do not change.
 * ========================================================================= */

const PREVIEW_COOKIE = 'clafs_preview_role';
const PREVIEW_ROLE_TO_USER = ['user' => 3, 'staff' => 2, 'admin' => 1];

/** Resolved once, before any output, so setcookie() never runs after headers are sent. */
function preview_role(): string
{
    static $role = null;
    if ($role !== null) {
        return $role;
    }

    $allowed = ['guest', 'user', 'staff', 'admin'];
    $chosen  = $_GET['as'] ?? null;

    if ($chosen !== null && in_array($chosen, $allowed, true)) {
        setcookie(PREVIEW_COOKIE, $chosen, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        return $role = $chosen;
    }
    $fromCookie = $_COOKIE[PREVIEW_COOKIE] ?? 'guest';
    return $role = in_array($fromCookie, $allowed, true) ? $fromCookie : 'guest';
}

/** PREVIEW: forget the mock role. Later: session_destroy() + cookie cleanup. */
function logout(): void
{
    setcookie(PREVIEW_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
}

/** The logged-in user row, or null for guests. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $role = preview_role();
        $user = $role === 'guest' ? null : find_user(PREVIEW_ROLE_TO_USER[$role]);
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** True when the current user holds any of the given roles. */
function has_role(array|string $roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], (array) $roles, true);
}

function is_staff(): bool
{
    return has_role(['staff', 'admin']);
}

function is_admin(): bool
{
    return has_role('admin');
}

/** Send guests to the login card on the homepage. */
function require_login(): void
{
    if (!is_logged_in()) {
        $next = $_SERVER['REQUEST_URI'] ?? url('/');
        header('Location: ' . url('/') . '?next=' . rawurlencode($next) . '#account');
        exit;
    }
}

/** Require login AND one of the given roles; renders a 403 page otherwise. */
function require_role(array|string $roles): void
{
    require_login();
    if (!has_role($roles)) {
        abort(
            403,
            'Access denied',
            "Your account doesn't have permission to view this page. If you think this is a mistake, contact the Lost & Found office.",
            url('/'),
            'Back to dashboard',
            '&#128274;'
        );
    }
}

/* =========================================================================
 * 5. Data layer — MOCK rows until the database exists.
 *
 * Column names follow docs/erd.html + docs/schema.sql, including the
 * "UI additions" block at the end of the schema (item_name, private_details,
 * proof_description, review_*, is_active). Pages never touch the arrays
 * directly; they call the accessors below, which the backend phase rewrites
 * as SELECT/INSERT/UPDATE queries through db().
 * ========================================================================= */

/** The whole mock dataset, built once per request. */
function mock_db(): array
{
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    $users = [
        1 => ['user_id' => 1, 'first_name' => 'Ana',   'last_name' => 'Reyes',      'email' => 'admin@mapua.edu.ph',           'role' => 'admin', 'is_active' => 1, 'created_at' => '2026-08-01 09:00:00', 'updated_at' => '2026-08-01 09:00:00'],
        2 => ['user_id' => 2, 'first_name' => 'Marco', 'last_name' => 'Santos',     'email' => 'staff@mapua.edu.ph',           'role' => 'staff', 'is_active' => 1, 'created_at' => '2026-08-01 09:05:00', 'updated_at' => '2026-08-01 09:05:00'],
        3 => ['user_id' => 3, 'first_name' => 'Jose',  'last_name' => 'Dela Cruz',  'email' => 'student1@mymail.mapua.edu.ph', 'role' => 'user',  'is_active' => 1, 'created_at' => '2026-08-15 14:20:00', 'updated_at' => '2026-08-15 14:20:00'],
        4 => ['user_id' => 4, 'first_name' => 'Bea',   'last_name' => 'Lim',        'email' => 'student2@mymail.mapua.edu.ph', 'role' => 'user',  'is_active' => 1, 'created_at' => '2026-08-20 10:12:00', 'updated_at' => '2026-08-20 10:12:00'],
        5 => ['user_id' => 5, 'first_name' => 'Ramon', 'last_name' => 'Villanueva', 'email' => 'rvillanueva@mapua.edu.ph',     'role' => 'user',  'is_active' => 1, 'created_at' => '2026-09-01 08:45:00', 'updated_at' => '2026-09-01 08:45:00'],
        6 => ['user_id' => 6, 'first_name' => 'Carla', 'last_name' => 'Mendoza',    'email' => 'student9@mymail.mapua.edu.ph', 'role' => 'user',  'is_active' => 0, 'created_at' => '2026-09-03 16:30:00', 'updated_at' => '2026-09-05 10:00:00'],
    ];

    $foundItems = [
        1 => [
            'item_id' => 1, 'user_id' => 2,
            'item_name' => 'Black JBL earbuds case', 'category' => 'Electronics',
            'description' => 'Small black charging case for wireless earbuds. Found on a study table near the windows.',
            'private_details' => 'Case has a deep scratch on the lid. Left earbud is missing; only the right one is inside.',
            'date_found' => '2026-09-08', 'location_found' => 'Library', 'storage_location' => 'Cabinet A, Shelf 1',
            'image_url' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-08 11:40:00', 'updated_at' => '2026-09-08 11:40:00',
        ],
        2 => [
            'item_id' => 2, 'user_id' => 2,
            'item_name' => 'Blue JanSport backpack', 'category' => 'Bags',
            'description' => 'Navy blue backpack, medium size, left on the bleachers after PE class.',
            'private_details' => 'Contains a green calculus notebook, a folding umbrella, and a Casio watch in the front pocket.',
            'date_found' => '2026-09-05', 'location_found' => 'Gymnasium', 'storage_location' => 'Cabinet B, Shelf 2',
            'image_url' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-05 16:05:00', 'updated_at' => '2026-09-05 16:05:00',
        ],
        3 => [
            'item_id' => 3, 'user_id' => 2,
            'item_name' => 'Mapua student ID card', 'category' => 'IDs & Cards',
            'description' => 'Student ID card in a clear plastic holder with a red lanyard. Turned in by cafeteria staff.',
            'private_details' => 'Name on card: Jose Dela Cruz. Student number ends in 4471. Lanyard has a small keychain bear.',
            'date_found' => '2026-09-10', 'location_found' => 'Cafeteria', 'storage_location' => 'Drawer 1 (IDs)',
            'image_url' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-10 13:15:00', 'updated_at' => '2026-09-11 09:00:00',
        ],
        4 => [
            'item_id' => 4, 'user_id' => 2,
            'item_name' => 'Casio fx-991 scientific calculator', 'category' => 'Electronics',
            'description' => 'Grey/black Casio scientific calculator with slide cover. Left in a lecture room.',
            'private_details' => 'Initials "K.S." written in marker on the back of the slide cover. Battery cover is cracked.',
            'date_found' => '2026-09-03', 'location_found' => 'North Building', 'storage_location' => 'Cabinet A, Shelf 3',
            'image_url' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-03 10:20:00', 'updated_at' => '2026-09-03 10:20:00',
        ],
        5 => [
            'item_id' => 5, 'user_id' => 2,
            'item_name' => 'Silver keychain with 3 keys', 'category' => 'Keys',
            'description' => 'Keychain with three keys found near the motorcycle parking area.',
            'private_details' => 'Has a red bottle-opener tag and one key is a small padlock key.',
            'date_found' => '2026-09-11', 'location_found' => 'Parking Area', 'storage_location' => 'Drawer 2 (Keys)',
            'image_url' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-11 08:50:00', 'updated_at' => '2026-09-11 08:50:00',
        ],
        6 => [
            'item_id' => 6, 'user_id' => 2,
            'item_name' => 'Grey hoodie', 'category' => 'Clothing',
            'description' => 'Plain grey pullover hoodie, size medium.',
            'private_details' => 'Name tag inside collar: "B. Lim". Small bleach stain on the left sleeve.',
            'date_found' => '2026-08-28', 'location_found' => 'Student Lounge', 'storage_location' => 'Cabinet C, Shelf 1',
            'image_url' => null, 'status' => 'returned', 'returned_at' => '2026-09-02 15:30:00',
            'created_at' => '2026-08-28 17:10:00', 'updated_at' => '2026-09-02 15:30:00',
        ],
        7 => [
            'item_id' => 7, 'user_id' => 2,
            'item_name' => 'Black folding umbrella', 'category' => 'Accessories',
            'description' => 'Compact black umbrella, unbranded.',
            'private_details' => 'Handle has a piece of yellow tape wrapped around it.',
            'date_found' => '2026-07-14', 'location_found' => 'Admin Building', 'storage_location' => 'Bin 4 (Misc)',
            'image_url' => null, 'status' => 'disposed', 'returned_at' => null,
            'created_at' => '2026-07-14 09:00:00', 'updated_at' => '2026-08-30 12:00:00',
        ],
    ];

    $lostReports = [
        1 => [
            'report_id' => 1, 'user_id' => 3,
            'item_name' => 'JBL wireless earbuds (black)', 'category' => 'Electronics',
            'description' => 'JBL Tune 230 earbuds in a black case. The case lid has a scratch and I think the left earbud was already out of the case when I lost it.',
            'date_lost' => '2026-09-07', 'location_lost' => 'Library',
            'image_url' => null, 'status' => 'open',
            'created_at' => '2026-09-07 18:25:00', 'updated_at' => '2026-09-07 18:25:00',
        ],
        2 => [
            'report_id' => 2, 'user_id' => 3,
            'item_name' => 'Student ID with red lanyard', 'category' => 'IDs & Cards',
            'description' => 'My Mapua ID in a clear holder. The lanyard has a tiny bear keychain.',
            'date_lost' => '2026-09-10', 'location_lost' => 'Cafeteria',
            'image_url' => null, 'status' => 'matched',
            'created_at' => '2026-09-10 14:00:00', 'updated_at' => '2026-09-11 09:00:00',
        ],
        3 => [
            'report_id' => 3, 'user_id' => 4,
            'item_name' => 'Red Hydro Flask water bottle', 'category' => 'Other',
            'description' => '32 oz red bottle with a sticker of a cat on the side.',
            'date_lost' => '2026-09-09', 'location_lost' => 'Covered Court',
            'image_url' => null, 'status' => 'open',
            'created_at' => '2026-09-09 12:10:00', 'updated_at' => '2026-09-09 12:10:00',
        ],
        4 => [
            'report_id' => 4, 'user_id' => 3,
            'item_name' => 'Physics textbook (Serway)', 'category' => 'Books & Notes',
            'description' => 'Hardbound physics textbook with my name on the first page.',
            'date_lost' => '2026-08-20', 'location_lost' => 'South Building',
            'image_url' => null, 'status' => 'closed',
            'created_at' => '2026-08-20 09:30:00', 'updated_at' => '2026-08-25 11:00:00',
        ],
        5 => [
            'report_id' => 5, 'user_id' => 5,
            'item_name' => 'Grey hoodie', 'category' => 'Clothing',
            'description' => 'Grey pullover hoodie, medium. Has a name tag inside the collar.',
            'date_lost' => '2026-08-27', 'location_lost' => 'Student Lounge',
            'image_url' => null, 'status' => 'closed',
            'created_at' => '2026-08-27 20:00:00', 'updated_at' => '2026-09-02 15:30:00',
        ],
    ];

    $claims = [
        1 => [
            'claim_id' => 1, 'item_id' => 1, 'user_id' => 3, 'report_id' => 1,
            'proof_description' => 'These are JBL Tune 230 earbuds. The case has a scratch on the lid, and only the right earbud should be inside because I had the left one in my ear when I lost the case.',
            'status' => 'pending', 'date_claimed' => '2026-09-09',
            'reviewed_by' => null, 'review_note' => null, 'reviewed_at' => null,
            'created_at' => '2026-09-09 08:15:00', 'updated_at' => '2026-09-09 08:15:00',
        ],
        2 => [
            'claim_id' => 2, 'item_id' => 3, 'user_id' => 3, 'report_id' => 2,
            'proof_description' => 'It is my student ID. My student number ends in 4471 and the lanyard has a small bear keychain.',
            'status' => 'approved', 'date_claimed' => '2026-09-10',
            'reviewed_by' => 2, 'review_note' => 'Details match. Please bring a valid ID to the Lost & Found office (Admin Bldg, Rm 104) to claim.', 'reviewed_at' => '2026-09-11 09:00:00',
            'created_at' => '2026-09-10 15:30:00', 'updated_at' => '2026-09-11 09:00:00',
        ],
        3 => [
            'claim_id' => 3, 'item_id' => 2, 'user_id' => 4, 'report_id' => null,
            'proof_description' => 'Blue backpack with my laptop and charger inside.',
            'status' => 'rejected', 'date_claimed' => '2026-09-06',
            'reviewed_by' => 2, 'review_note' => 'Described contents do not match what was logged at intake.', 'reviewed_at' => '2026-09-06 10:45:00',
            'created_at' => '2026-09-06 09:20:00', 'updated_at' => '2026-09-06 10:45:00',
        ],
        4 => [
            'claim_id' => 4, 'item_id' => 2, 'user_id' => 5, 'report_id' => null,
            'proof_description' => 'Navy JanSport. There should be a green calculus notebook, a folding umbrella and my Casio watch in the front pocket.',
            'status' => 'pending', 'date_claimed' => '2026-09-11',
            'reviewed_by' => null, 'review_note' => null, 'reviewed_at' => null,
            'created_at' => '2026-09-11 17:05:00', 'updated_at' => '2026-09-11 17:05:00',
        ],
    ];

    return $db = [
        'users'        => $users,
        'found_items'  => $foundItems,
        'lost_reports' => $lostReports,
        'claims'       => $claims,
    ];
}

/* ---- Collections (later: SELECT * FROM …), keyed by primary key ---- */

function all_users(): array        { return mock_db()['users']; }
function all_found_items(): array  { return mock_db()['found_items']; }
function all_lost_reports(): array { return mock_db()['lost_reports']; }
function all_claims(): array       { return mock_db()['claims']; }

/* ---- Single rows (later: SELECT … WHERE id = ?) ---- */

function find_user(?int $id): ?array       { return $id !== null ? (all_users()[$id] ?? null) : null; }
function find_found_item(int $id): ?array  { return all_found_items()[$id] ?? null; }
function find_lost_report(int $id): ?array { return all_lost_reports()[$id] ?? null; }
function find_claim(int $id): ?array       { return all_claims()[$id] ?? null; }

/** Rows whose $column equals $value, as a plain list. */
function where(array $rows, string $column, mixed $value): array
{
    return array_values(array_filter($rows, fn ($row) => $row[$column] === $value));
}

/** Count of rows whose $column equals $value. */
function count_where(array $rows, string $column, mixed $value): int
{
    return count(where($rows, $column, $value));
}

/** Filter rows by a keyword (matches item_name/description/location) and exact-match fields ('' or null = any). */
function search_rows(array $rows, string $keyword = '', array $exact = []): array
{
    $keyword = mb_strtolower(trim($keyword));
    return array_values(array_filter($rows, function (array $row) use ($keyword, $exact) {
        foreach ($exact as $field => $value) {
            if ($value !== '' && $value !== null && (string) ($row[$field] ?? '') !== (string) $value) {
                return false;
            }
        }
        if ($keyword === '') {
            return true;
        }
        $haystack = mb_strtolower(implode(' ', [
            $row['item_name'] ?? '', $row['description'] ?? '',
            $row['location_found'] ?? '', $row['location_lost'] ?? '',
        ]));
        return str_contains($haystack, $keyword);
    }));
}

/* ---- Boot ---- */

preview_role(); // resolve the mock role (and set its cookie) before any output
