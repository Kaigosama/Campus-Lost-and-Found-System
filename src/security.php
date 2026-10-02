<?php
declare(strict_types=1);

/**
 * Security log, Cloudflare Turnstile and the cross-site POST check.
 */

/** True on the public deploy, where missing secrets must fail closed instead of falling back to test values. */
function is_production(): bool
{
    return setting('RAILWAY_PROJECT_ID') !== '' || setting('APP_ENV') === 'production';
}

function user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/**
 * Records a security event (login_success, login_failed, account_locked, logout, session_expired, …).
 * $email is what was typed, for attempts on accounts that may not exist.
 */
function log_event(string $type, ?array $user = null, array $metadata = [], ?string $email = null, ?int $sessionId = null): void
{
    db()->prepare('INSERT INTO security_events (user_id, email, event_type, session_id, ip_address, user_agent, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $user['user_id'] ?? null,
            mb_substr($email ?? ($user['email'] ?? ''), 0, 190) ?: null,
            $type,
            $sessionId ?? ($_SESSION['session_row'] ?? null),
            PHP_SAPI === 'cli' ? null : client_ip(),
            PHP_SAPI === 'cli' ? null : (user_agent() ?: null),
            $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
}

/* ---------------------------------------------------------------- Turnstile */

// Cloudflare's published test keys: they always pass, so local development works without an account.
const TURNSTILE_TEST_SITE_KEY   = '1x00000000000000000000AA';
const TURNSTILE_TEST_SECRET_KEY = '1x0000000000000000000000000000000AA';

function turnstile_site_key(): string
{
    return setting('TURNSTILE_SITE_KEY') ?: (is_production() ? '' : TURNSTILE_TEST_SITE_KEY);
}

/** The widget for a form. Its token arrives as the cf-turnstile-response field. */
function turnstile_widget(): string
{
    $key = turnstile_site_key();
    if ($key === '') {
        return '<div class="alert alert-error" role="alert">The CAPTCHA is not configured, so this form cannot be used yet. Contact the Lost &amp; Found office.</div>';
    }
    return '<div class="form-group"><div class="cf-turnstile" data-sitekey="' . e($key) . '"></div></div>'
        . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

/** Verifies the posted Turnstile token with Cloudflare. The widget saying "success" is never trusted on its own. */
function turnstile_passed(): bool
{
    $secret = setting('TURNSTILE_SECRET_KEY') ?: (is_production() ? '' : TURNSTILE_TEST_SECRET_KEY);
    $token  = (string) ($_POST['cf-turnstile-response'] ?? '');
    if ($secret === '') {
        error_log('[turnstile] TURNSTILE_SECRET_KEY is not set; every CAPTCHA check fails until it is.');
        return false;
    }
    if ($token === '' || strlen($token) > 2048) {
        return false;
    }
    $context = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => client_ip()]),
        'timeout'       => 10,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    $result   = json_decode((string) $response, true);
    if (!is_array($result)) {
        error_log('[turnstile] No answer from Cloudflare; treating the check as failed.');
        return false;
    }
    return ($result['success'] ?? false) === true;
}

/* ------------------------------------------------------- Cross-site requests */

/**
 * CSRF defence for every POST: browsers send Origin (or at least Referer) with form posts and fetch(), so a POST
 * that names another site is refused. Together with the SameSite=Lax session cookie this stops forged requests.
 * Requests with neither header come from non-browser clients, which hold no victim's cookie.
 */
function reject_cross_site_post(): void
{
    if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
    if ($source === '') {
        return;
    }
    $hostOf = function (string $url): string {
        $parts = parse_url($url) ?: [];
        return strtolower(($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    };
    $allowed = array_filter([
        strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')),
        setting('APP_URL') !== '' ? $hostOf(setting('APP_URL')) : '',
        strtolower(setting('RAILWAY_PUBLIC_DOMAIN')),
    ]);
    if (!in_array($hostOf($source), $allowed, true)) {
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            json_error(403, 'Cross-site request refused.');
        }
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Cross-site request refused.');
    }
}
