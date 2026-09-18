<?php
declare(strict_types=1);

/**
 * Application core — required first by every page and API endpoint.
 * Constants, database connection, shared helpers, session auth and the data layer.
 */

define('APP_ROOT', dirname(__DIR__));
date_default_timezone_set('Asia/Manila');

/** '' when the repo root is the web root (php -S localhost:8000); '/clafs' for an Apache alias. */
const BASE_URL = '';

/* ---------------------------------------------------------------- Constants */

const APP_NAME      = 'CLAFS';
const APP_FULL_NAME = 'Campus Lost-and-Found System';

const CATEGORIES = ['Electronics', 'IDs & Cards', 'Bags', 'Clothing', 'Books & Notes', 'Keys', 'Accessories', 'Other'];

const ROLES = [
    'user'  => 'Student / Faculty',
    'staff' => 'Security & Maintenance',
    'admin' => 'Office Administrator',
];
const LOST_STATUSES  = ['open' => 'Open', 'matched' => 'Matched', 'closed' => 'Closed'];
const FOUND_STATUSES = ['stored' => 'In Storage', 'returned' => 'Returned', 'disposed' => 'Disposed'];
const CLAIM_STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

const ALLOWED_EMAIL_DOMAINS = ['mymail.mapua.edu.ph', 'mapua.edu.ph'];
const CAMPUS_LOCATIONS = ['Library', 'Cafeteria', 'Gymnasium', 'Student Lounge', 'Parking Area', 'North Building', 'South Building', 'Admin Building', 'Chapel', 'Covered Court'];

const MAX_UPLOAD_MB   = 5;
const UPLOAD_DIR      = APP_ROOT . '/public/uploads';
const IMAGE_TYPES     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const PASSWORD_MIN    = 8;
const REMEMBER_DAYS   = 30;

/* ---------------------------------------------------------------- Database */

// Per-machine overrides (git-ignored) may define() any DB_* constant before the defaults apply.
if (is_file(__DIR__ . '/db_connect.local.php')) {
    require __DIR__ . '/db_connect.local.php';
}
defined('DB_HOST')    || define('DB_HOST', '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT', 3306);
defined('DB_NAME')    || define('DB_NAME', 'clafs');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit("Database connection failed: {$e->getMessage()}\n\nImport docs/schema.sql and set DB_* constants in config/db_connect.local.php.");
        }
    }
    return $pdo;
}

/* ---------------------------------------------------------------- Helpers */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('/public/' . ltrim($path, '/'));
}

function item_url(string $type, int $id): string
{
    return url('/view_item.php?type=' . $type . '&id=' . $id);
}

/** "active" when the current script is $path and $when holds. */
function is_active(string $path, bool $when = true): string
{
    return $when && ($_SERVER['SCRIPT_NAME'] ?? '') === url($path) ? 'active' : '';
}

/** Current URL with some query parameters replaced. */
function url_with(array $params): string
{
    $query = array_filter(array_merge($_GET, $params), fn ($v) => $v !== null && $v !== '');
    $path  = $_SERVER['SCRIPT_NAME'] ?? url('/');
    return $path . ($query ? '?' . http_build_query($query) : '');
}

function status_label(string $status): string
{
    $labels = LOST_STATUSES + FOUND_STATUSES + CLAIM_STATUSES;
    return $labels[$status] ?? ucfirst($status);
}

/** $for ("found-3", "claim-7", …) lets app.js update the badge in place after an API call. */
function status_badge(string $status, ?string $for = null): string
{
    $attr = $for !== null ? ' data-status-for="' . e($for) . '"' : '';
    return '<span class="badge badge-' . e($status) . '"' . $attr . '>' . e(status_label($status)) . '</span>';
}

function format_date(?string $value): string
{
    return $value ? date('M j, Y', strtotime($value)) : '—';
}

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

/** <img> for an item photo (image_url is relative to public/), or a placeholder block. Real photos open in the app.js lightbox. */
function photo_tag(?string $imageUrl, string $alt, string $class = 'photo'): string
{
    if ($imageUrl) {
        return '<img src="' . e(asset($imageUrl)) . '" alt="' . e($alt) . '" class="' . e($class) . '" loading="lazy" data-lightbox>';
    }
    return '<div class="' . e($class) . ' photo-placeholder" role="img" aria-label="No photo available">'
        . '<svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
        . '<path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg>'
        . '<span>No photo</span></div>';
}

/** <option> list from an assoc array (value => label) or a plain list. */
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

function excerpt(string $text, int $length = 110): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
}

/** Returns [items_on_page, current_page, total_pages]. */
function paginate(array $items, int $perPage = 8): array
{
    $total = max(1, (int) ceil(count($items) / $perPage));
    $page  = min($total, max(1, (int) ($_GET['page'] ?? 1)));
    return [array_slice($items, ($page - 1) * $perPage, $perPage), $page, $total];
}

function newest_first(array $rows, string $column = 'created_at'): array
{
    usort($rows, fn ($a, $b) => strcmp($b[$column], $a[$column]));
    return $rows;
}

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

/** Found-item card for the browse grid. Public fields only. */
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

/** Filter tabs from [key => label] with a count per key; $param is the query parameter that selects a tab. */
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

/** Decoded JSON body when sent as application/json, otherwise the form fields. */
function json_input(): array
{
    if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_error(405, "Use $method.");
    }
}

/**
 * Validates the fields of a lost report ('lost') or found item ('found') from add_item / update_item.
 * Returns [errors (field => message), values (trimmed, keyed by column)].
 */
function validate_item_input(array $in, string $type): array
{
    $field = fn (string $key) => trim((string) ($in[$key] ?? ''));
    $rules = [
        'item_name'   => [1, 150],
        'description' => [$type === 'found' ? 15 : 20, 2000],
    ];
    if ($type === 'found') {
        $rules += ['location_found' => [1, 150], 'storage_location' => [1, 150], 'private_details' => [15, 2000]];
        $dateKey = 'date_found';
    } else {
        $rules += ['location_lost' => [1, 150]];
        $dateKey = 'date_lost';
    }

    $errors = [];
    $values = [];
    foreach ($rules as $key => [$min, $max]) {
        $values[$key] = $field($key);
        $len = mb_strlen($values[$key]);
        if ($len === 0)     $errors[$key] = 'This field is required.';
        elseif ($len < $min) $errors[$key] = "Must be at least $min characters.";
        elseif ($len > $max) $errors[$key] = "Must be $max characters or fewer.";
    }
    $values['category'] = $field('category');
    if (!in_array($values['category'], CATEGORIES, true)) {
        $errors['category'] = 'Choose a valid category.';
    }
    $values[$dateKey] = $field($dateKey);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values[$dateKey]) || !strtotime($values[$dateKey])) {
        $errors[$dateKey] = 'Enter a valid date.';
    } elseif ($values[$dateKey] > date('Y-m-d')) {
        $errors[$dateKey] = 'Date cannot be in the future.';
    }
    return [$errors, $values];
}

/**
 * Stores an uploaded photo ($_FILES entry) in public/uploads under a random name.
 * Returns the image_url (relative to public/), null when no file was sent, or sets $error.
 */
function save_photo(?array $file, ?string &$error): ?string
{
    $error = null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        $error = 'Image must be ' . MAX_UPLOAD_MB . ' MB or smaller.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = 'Upload failed. Please try again.';
        return null;
    }
    // Trust the file contents, not the client's filename or declared type.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        $error = 'Only JPG, PNG or WEBP images are allowed.';
        return null;
    }
    $name = bin2hex(random_bytes(8)) . '.' . IMAGE_TYPES[$mime];
    is_dir(UPLOAD_DIR) || mkdir(UPLOAD_DIR, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        $error = 'Could not save the image.';
        return null;
    }
    return 'uploads/' . $name;
}

/* ---------------------------------------------------------------- Auth (PHP sessions) */

ini_set('session.gc_maxlifetime', (string) (REMEMBER_DAYS * 86400));
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

/** The logged-in, active user row, or null for guests. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = find_user($_SESSION['user_id'] ?? null);
        if ($user && !$user['is_active']) {
            $user = null;
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function login_user(array $user, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    if ($remember) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + REMEMBER_DAYS * 86400,
            'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
}

function logout(): void
{
    $_SESSION = [];
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/']);
    session_destroy();
}

/** Validates a registration form and creates the account. Returns field errors; empty means the user was created. */
function register_user(array $in): array
{
    $errors = [];
    foreach (['first_name', 'last_name'] as $key) {
        $len = mb_strlen(trim((string) ($in[$key] ?? '')));
        if ($len === 0)     $errors[$key] = 'This field is required.';
        elseif ($len > 100) $errors[$key] = 'Must be 100 characters or fewer.';
    }
    $email  = mb_strtolower(trim((string) ($in['email'] ?? '')));
    $domain = substr(strrchr($email, '@') ?: '', 1);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (!in_array($domain, ALLOWED_EMAIL_DOMAINS, true)) {
        $errors['email'] = 'Use your Mapua email (@' . implode(' or @', ALLOWED_EMAIL_DOMAINS) . ').';
    } elseif (find_user_by_email($email)) {
        $errors['email'] = 'An account with this email already exists.';
    }
    $password = (string) ($in['password'] ?? '');
    if (strlen($password) < PASSWORD_MIN) {
        $errors['password'] = 'Password must be at least ' . PASSWORD_MIN . ' characters.';
    } elseif ($password !== (string) ($in['password_confirm'] ?? '')) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if ($errors) {
        return $errors;
    }
    db()->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
        ->execute([trim($in['first_name']), trim($in['last_name']), $email, password_hash($password, PASSWORD_DEFAULT), 'user']);
    return [];
}

/** Only same-site paths are safe redirect targets after login. */
function safe_redirect(string $next): string
{
    return ($next !== '' && $next[0] === '/' && !str_starts_with($next, '//')) ? $next : url('/');
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

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

/* ---------------------------------------------------------------- Data layer */

const TABLE_KEYS = ['users' => 'user_id', 'found_items' => 'item_id', 'lost_reports' => 'report_id', 'claims' => 'claim_id'];

/** Whole table keyed by primary key, loaded once per request. Pages filter in PHP with the helpers below. */
function table(string $name): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $cache[$name] = array_column(db()->query("SELECT * FROM `$name`")->fetchAll(), null, TABLE_KEYS[$name]);
    }
    return $cache[$name];
}

function all_users(): array        { return table('users'); }
function all_found_items(): array  { return table('found_items'); }
function all_lost_reports(): array { return table('lost_reports'); }
function all_claims(): array       { return table('claims'); }

function find_user(?int $id): ?array       { return $id ? (all_users()[$id] ?? null) : null; }
function find_found_item(int $id): ?array  { return all_found_items()[$id] ?? null; }
function find_lost_report(int $id): ?array { return all_lost_reports()[$id] ?? null; }
function find_claim(int $id): ?array       { return all_claims()[$id] ?? null; }

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([mb_strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

/** Rows whose $column equals $value, as a plain list. */
function where(array $rows, string $column, mixed $value): array
{
    return array_values(array_filter($rows, fn ($row) => $row[$column] === $value));
}

function count_where(array $rows, string $column, mixed $value): int
{
    return count(where($rows, $column, $value));
}

/** Filter rows by a keyword (item_name/description/location) and exact-match fields ('' or null = any). */
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
