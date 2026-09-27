<?php
declare(strict_types=1);

/**
 * Accounts and PHP sessions: log in and out, registration, password changes and role checks.
 * Loaded last by bootstrap.php because it resumes the session as soon as it is included.
 */

/** True over HTTPS, including behind a TLS-terminating proxy such as Railway's. */
function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

ini_set('session.gc_maxlifetime', (string) (REMEMBER_DAYS * 86400));
ini_set('session.use_strict_mode', '1');   // never adopt a session id the server didn't issue
session_set_cookie_params(['path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
// Resume a session only when the browser sent its cookie; login_user() starts new ones. A request without the
// cookie (such as a form posted from another site) then gets no fresh cookie that would log the visitor out.
if (PHP_SAPI !== 'cli' && isset($_COOKIE[session_name()])) {   // database/seed.php loads this file from the command line
    session_start();
}

/** The logged-in, active user row, or null for guests. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = find_user($_SESSION['user_id'] ?? null);
        $staleSession = $user && isset($_SESSION['password_sig']) && !hash_equals($_SESSION['password_sig'], password_sig($user['password_hash']));
        if ($user && (!$user['is_active'] || $staleSession)) {
            $user = null;
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function login_user(array $user, bool $remember = false): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['password_sig'] = password_sig($user['password_hash']);
    if ($remember) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + REMEMBER_DAYS * 86400,
            'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
}

function logout(): void
{
    $_SESSION = [];
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** Validates a registration form and creates the account. Returns field errors; empty means the user was created. */
function register_user(array $in): array
{
    $errors = [];
    foreach (['first_name', 'last_name'] as $key) {
        $name = trim((string) ($in[$key] ?? ''));
        $len  = mb_strlen($name);
        if ($len === 0)                           $errors[$key] = 'This field is required.';
        elseif ($len > 100)                       $errors[$key] = 'Must be 100 characters or fewer.';
        elseif (!preg_match(NAME_PATTERN, $name)) $errors[$key] = 'Use letters only (spaces, hyphens, apostrophes and periods are allowed).';
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

/** Fingerprint of a password hash kept in the session, so changing the password ends every other session. */
function password_sig(string $passwordHash): string
{
    return substr(hash('sha256', $passwordHash), 0, 16);
}

/** Saves a new password. Every session of this user fails the check in current_user() from now on. */
function set_password(array $user, string $password): string
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([$hash, $user['user_id']]);
    return $hash;
}

/** Validates the change-password form for the logged-in user and saves it. Returns field errors; empty means it changed. */
function change_password(array $user, array $in): array
{
    $current = (string) ($in['current_password'] ?? '');
    $errors  = new_password_errors($in, $current);
    if (!password_verify($current, $user['password_hash'])) {
        $errors = ['current_password' => 'Your current password is incorrect.'] + $errors;
    }
    if ($errors) {
        return $errors;
    }
    $_SESSION['password_sig'] = password_sig(set_password($user, (string) $in['new_password']));   // keep this session only
    return [];
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

/** Send guests to the login page, then back here. */
function require_login(): void
{
    if (!is_logged_in()) {
        $next = $_SERVER['REQUEST_URI'] ?? url('/');
        header('Location: ' . url('/login.php') . '?next=' . rawurlencode($next));
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
