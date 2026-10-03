<?php
declare(strict_types=1);

/**
 * Accounts and sessions: log in and out, lockout, registration, password changes and role checks.
 * Every login has a row in user_sessions; the browser's PHP session holds a random token whose hash identifies
 * that row, so sessions expire, end and are revoked on the server, not in the browser.
 * Loaded last by bootstrap.php because it resumes the session as soon as it is included.
 */

/** True over HTTPS, including behind a TLS-terminating proxy such as Railway's. */
function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

ini_set('session.gc_maxlifetime', (string) ((SESSION_IDLE_MINUTES + 5) * 60));
ini_set('session.use_strict_mode', '1');   // never adopt a session id the server didn't issue
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
// Resume a session only when the browser sent its cookie; login_user() starts new ones. A request without the
// cookie (such as a form posted from another site) then gets no fresh cookie that would log the visitor out.
if (PHP_SAPI !== 'cli' && isset($_COOKIE[session_name()])) {   // database/seed.php loads this file from the command line
    session_start();
}

/** The logged-in, active user row, or null for guests. The role always comes from the database row. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = find_user($_SESSION['user_id'] ?? null);
        $problem = $user ? session_problem($user) : null;
        if ($problem !== null) {
            $user = null;
            $_SESSION = ['auth_notice' => $problem];   // shown once on the login page
        }
    }
    return $user;
}

/**
 * A page request whose session just ended (expired, revoked, …) goes to the login page, which says why.
 * API requests get 401 from their endpoint instead, and api.js then opens the login page.
 */
function redirect_ended_session(): void
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (PHP_SAPI === 'cli' || str_contains($script, '/api/') || $script === url('/login.php') || is_logged_in() || empty($_SESSION['auth_notice'])) {
        return;
    }
    $next = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/') : '';
    header('Location: ' . url('/login.php') . $next);
    exit;
}

/** Why this browser's session is no longer valid (a message for the user), or null when it is; also records activity. */
function session_problem(array $user): ?string
{
    if (!$user['is_active']) {
        return 'This account has been deactivated. Contact the Lost & Found office.';
    }
    if (!hash_equals($_SESSION['password_sig'] ?? '', password_sig($user['password_hash']))) {
        return 'Your password was changed. Log in with the new password.';
    }
    $stmt = db()->prepare('SELECT * FROM user_sessions WHERE token_hash = ? AND user_id = ?');
    $stmt->execute([hash('sha256', (string) ($_SESSION['session_token'] ?? '')), $user['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        return 'Please log in again.';
    }
    if ($row['status'] === 'revoked') {
        return 'You were logged out because this account signed in on another device or its access changed.';
    }
    if ($row['status'] !== 'active') {
        return 'Please log in again.';
    }
    if (strtotime($row['last_activity_at']) < time() - SESSION_IDLE_MINUTES * 60) {
        db()->prepare('UPDATE user_sessions SET status = "expired", ended_at = expires_at WHERE session_id = ? AND status = "active"')
            ->execute([$row['session_id']]);
        log_event('session_expired', $user, ['idle_minutes' => SESSION_IDLE_MINUTES], null, (int) $row['session_id']);
        return 'Your session expired after ' . SESSION_IDLE_MINUTES . ' minutes of inactivity. Please log in again.';
    }
    // Authenticated activity, written at most once a minute per session.
    if (strtotime($row['last_activity_at']) < time() - 60) {
        db()->prepare('UPDATE user_sessions SET last_activity_at = NOW(), expires_at = NOW() + INTERVAL ? MINUTE WHERE session_id = ?')
            ->execute([SESSION_IDLE_MINUTES, $row['session_id']]);
        db()->prepare('UPDATE users SET last_activity_at = NOW() WHERE user_id = ?')->execute([$user['user_id']]);
    }
    return null;
}

/** Ends this user's active sessions (all, or all but $exceptSessionId). Returns how many were ended. */
function revoke_sessions(int $userId, int $exceptSessionId = 0): int
{
    $stmt = db()->prepare('UPDATE user_sessions SET status = "revoked", ended_at = NOW() WHERE user_id = ? AND status = "active" AND session_id <> ?');
    $stmt->execute([$userId, $exceptSessionId]);
    return $stmt->rowCount();
}

function login_user(array $user): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    // A browser holds one login at a time: logging in again (another account in another tab) ends the previous one.
    if (!empty($_SESSION['session_row'])) {
        $stmt = db()->prepare('UPDATE user_sessions SET status = "logged_out", ended_at = NOW() WHERE session_id = ? AND status = "active"');
        $stmt->execute([$_SESSION['session_row']]);
        if ($stmt->rowCount()) {
            log_event('logout', find_user($_SESSION['user_id'] ?? null), ['reason' => 'another account logged in on this browser']);
        }
    }
    session_regenerate_id(true);   // no session fixation: the id from before login is discarded

    // A regular admin may be signed in on one device only: the new login ends the older session.
    if ($user['role'] === 'admin' && ($ended = revoke_sessions($user['user_id']))) {
        log_event('session_revoked', $user, ['reason' => 'admin signed in on another device', 'sessions' => $ended]);
    }

    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL ? MINUTE)')
        ->execute([$user['user_id'], hash('sha256', $token), client_ip(), user_agent() ?: null, SESSION_IDLE_MINUTES]);
    $sessionId = (int) db()->lastInsertId();
    db()->prepare('UPDATE users SET failed_login_attempts = 0, last_login_at = NOW(), last_activity_at = NOW() WHERE user_id = ?')
        ->execute([$user['user_id']]);

    $_SESSION = [
        'user_id'       => $user['user_id'],
        'session_token' => $token,
        'session_row'   => $sessionId,
        'password_sig'  => password_sig($user['password_hash']),
    ];
    log_event('login_success', $user, ['role' => $user['role']], null, $sessionId);
}

function logout(): void
{
    if (!empty($_SESSION['session_row'])) {
        $stmt = db()->prepare('UPDATE user_sessions SET status = "logged_out", ended_at = NOW() WHERE session_id = ? AND status = "active"');
        $stmt->execute([$_SESSION['session_row']]);
        if ($stmt->rowCount()) {
            log_event('logout', find_user($_SESSION['user_id'] ?? null));
        }
    }
    $_SESSION = [];
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/* ---------------------------------------------------------------- Lockout */

/**
 * Locked by too many wrong passwords, until an admin unlocks it (api/update_user.php). Neither waiting nor a
 * password reset unlocks it. The master admin is never locked; their failures are only logged.
 */
function account_locked(array $user): bool
{
    return $user['role'] !== 'master_admin' && $user['locked_at'] !== null;
}

/** Counts a wrong password for $user; the LOCKOUT_ATTEMPTS-th in a row locks the account. Returns true if it locked now. */
function record_failed_login(array $user): bool
{
    db()->prepare('UPDATE users SET failed_login_attempts = LEAST(failed_login_attempts + 1, 255) WHERE user_id = ?')->execute([$user['user_id']]);
    $stmt = db()->prepare('SELECT failed_login_attempts FROM users WHERE user_id = ?');
    $stmt->execute([$user['user_id']]);
    $attempts = (int) $stmt->fetchColumn();
    log_event('login_failed', $user, ['consecutive_failures' => $attempts]);

    if ($attempts < LOCKOUT_ATTEMPTS) {
        return false;
    }
    if ($user['role'] === 'master_admin') {
        if ($attempts === LOCKOUT_ATTEMPTS) {   // warn once per run of failures; the counter resets on a successful login
            send_security_notice($user, "There have been $attempts failed attempts to log in to your master administrator account. "
                . "The account is not locked, but if this wasn't you, reset your password now: " . app_base_url() . url('/forgot_password.php'));
        }
        return false;
    }
    db()->prepare('UPDATE users SET failed_login_attempts = 0, locked_at = NOW() WHERE user_id = ?')->execute([$user['user_id']]);
    log_event('account_locked', $user, ['failures' => LOCKOUT_ATTEMPTS]);
    send_security_notice($user, 'Your account was locked after ' . LOCKOUT_ATTEMPTS . ' wrong passwords in a row. '
        . 'Visit the Lost & Found office (Admin Bldg, Rm 104, Mon–Fri 8:00 AM–5:00 PM) with your ID to have it unlocked. '
        . "If these attempts weren't you, tell the office so they can check your account.");
    return true;
}

/* ---------------------------------------------------------------- Registration */

/**
 * Validates a registration form and creates an unverified account, then emails the verification link.
 * Returns field errors; empty means done. A taken email is not reported (that would reveal who has an account):
 * the owner of that address is emailed instead, and the visitor sees the same "check your email" page.
 */
function register_user(array $in, bool $captchaPassed): array
{
    $first  = trim((string) ($in['first_name'] ?? ''));
    $last   = trim((string) ($in['last_name'] ?? ''));
    $errors = array_filter([
        'first_name' => name_error($first, 'First name', NAME_MIN),
        'last_name'  => name_error($last, 'Last name', 1),
    ]);
    $email  = mb_strtolower(trim((string) ($in['email'] ?? '')));
    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors['email'] = 'Enter a valid email address.';
    }
    $password = (string) ($in['password'] ?? '');
    $confirm  = (string) ($in['password_confirm'] ?? '');
    if ($message = password_error($password)) {
        $errors['password'] = $message;
    }
    if ($confirm === '') {
        $errors['password_confirm'] = 'Confirm password is required.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if (empty($in['agree'])) {
        $errors['agree'] = 'Please tick this box to continue.';
    }
    if (!$captchaPassed) {
        $errors['captcha'] = 'Please complete the CAPTCHA check.';
        log_event('captcha_failed', null, ['form' => 'register'], $email ?: null);
    }
    if ($errors) {
        return $errors;
    }

    if ($existing = find_user_by_email($email)) {
        notify_existing_account($existing);
        log_event('register_existing_email', $existing);
        return [];
    }
    db()->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role, email_verified) VALUES (?, ?, ?, ?, "user", 0)')
        ->execute([$first, $last, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $user = find_user_by_email($email);
    send_verification_email($user);
    log_event('register', $user);
    return [];
}

/* ---------------------------------------------------------------- Passwords */

/** Fingerprint of a password hash kept in the session, so changing the password ends every other session. */
function password_sig(string $passwordHash): string
{
    return substr(hash('sha256', $passwordHash), 0, 16);
}

/**
 * Saves a new password. A lockout stays: only an admin unlocks an account. Every other session of this user ends:
 * their rows are revoked and they fail the password check in session_problem().
 */
function set_password(array $user, string $password, int $keepSessionId = 0): string
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash = ?, failed_login_attempts = 0 WHERE user_id = ?')
        ->execute([$hash, $user['user_id']]);
    revoke_sessions($user['user_id'], $keepSessionId);
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
    $_SESSION['password_sig'] = password_sig(set_password($user, (string) $in['new_password'], (int) $_SESSION['session_row']));   // keep this session only
    log_event('password_changed', $user);
    return [];
}

/* ---------------------------------------------------------------- Roles */

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(array|string $roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], (array) $roles, true);
}

/** Security & maintenance staff: review claims and student posts, manage found items and lost reports. Not admins. */
function is_staff(): bool
{
    return has_role(REVIEWER_ROLES);
}

function is_admin(): bool
{
    return has_role(ADMIN_ROLES);
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
