<?php
/**
 * PREVIEW STUB — no real authentication yet.
 *
 * The page files call current_user(), has_role(), require_login() and
 * require_role() exactly as they will once sessions and the users table
 * exist. For now the "logged-in user" is chosen with ?as=guest|user|staff|admin
 * (remembered in a cookie) so every screen can be previewed in every role.
 *
 * The backend phase replaces the bodies of these functions; the page files
 * do not change.
 */

const PREVIEW_COOKIE = 'clafs_preview_role';
const PREVIEW_ROLE_TO_USER = ['user' => 3, 'staff' => 2, 'admin' => 1];

/** Resolved once (before any output, from bootstrap) so setcookie() never runs after headers are sent. */
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

/** The logged-in user row, or null for guests. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $role = preview_role();
        $user = $role === 'guest' ? null : mock_user(PREVIEW_ROLE_TO_USER[$role]);
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
    if (!$user) {
        return false;
    }
    return in_array($user['role'], (array) $roles, true);
}

function is_staff(): bool
{
    return has_role(['staff', 'admin']);
}

function is_admin(): bool
{
    return has_role('admin');
}

/** Send guests to the login page. */
function require_login(): void
{
    if (!is_logged_in()) {
        $next = $_SERVER['REQUEST_URI'] ?? url('/dashboard.php');
        header('Location: ' . url('/login.php') . '?next=' . rawurlencode($next));
        exit;
    }
}

/** Require login AND one of the given roles; renders a 403 page otherwise. */
function require_role(array|string $roles): void
{
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        include APP_PATH . '/views/layout/header.php';
        include APP_PATH . '/views/partials/forbidden.php';
        include APP_PATH . '/views/layout/footer.php';
        exit;
    }
}
