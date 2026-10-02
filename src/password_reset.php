<?php
declare(strict_types=1);

/**
 * Forgot password: emailed reset links.
 */

/**
 * Absolute site URL for links in emails. Taken from APP_URL (or Railway's RAILWAY_PUBLIC_DOMAIN) rather than
 * the request, so a forged Host header can't point a reset link at someone else's site.
 */
function app_base_url(): string
{
    $configured = setting('APP_URL') ?: (setting('RAILWAY_PUBLIC_DOMAIN') !== '' ? 'https://' . setting('RAILWAY_PUBLIC_DOMAIN') : '');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');   // local development
}

/**
 * Reset links need no table: the signature is keyed with the user's current password hash, so a link stops
 * working once the password changes (it is single-use) and can't be forged without the database.
 */
function password_reset_sig(array $user, int $expires): string
{
    return hash_hmac('sha256', $user['user_id'] . '|' . $expires, $user['password_hash'] . setting('APP_KEY'));
}

function password_reset_url(array $user): string
{
    $expires = time() + RESET_LINK_MINUTES * 60;
    $query   = http_build_query(['uid' => $user['user_id'], 'expires' => $expires, 'sig' => password_reset_sig($user, $expires)]);
    return app_base_url() . url('/reset_password.php?' . $query);
}

/** The active user a reset link belongs to, or null when it is invalid, expired or already used. */
function user_from_reset_link(array $query): ?array
{
    $user    = find_user((int) ($query['uid'] ?? 0));
    $expires = (int) ($query['expires'] ?? 0);
    if (!$user || !$user['is_active'] || $expires < time() || $expires > time() + RESET_LINK_MINUTES * 60) {
        return null;
    }
    return hash_equals(password_reset_sig($user, $expires), (string) ($query['sig'] ?? '')) ? $user : null;
}

/** Emails a reset link when $email belongs to an active account. Says nothing either way, so emails can't be probed. */
function request_password_reset(string $email): void
{
    $user = find_user_by_email($email);
    if (!$user || !$user['is_active'] || mail_throttled('reset', $user)) {
        return;
    }
    log_event('password_reset_requested', $user);
    $text = "Hi {$user['first_name']},\n\n"
        . "Someone asked to reset the password for your " . APP_NAME . " account. Open this link to choose a new one:\n\n"
        . password_reset_url($user) . "\n\n"
        . 'The link works once and expires in ' . RESET_LINK_MINUTES . " minutes. If you didn't ask for this, ignore this email; your password stays the same.\n\n"
        . '— ' . APP_FULL_NAME;
    send_mail($user['email'], full_name($user), 'Reset your ' . APP_NAME . ' password', $text);
}

/** True when an email of this $kind already went to $user in the last minute; otherwise records this one. */
function mail_throttled(string $kind, array $user): bool
{
    $stamp = sys_get_temp_dir() . "/clafs-$kind-" . $user['user_id'];
    if (is_file($stamp) && filemtime($stamp) > time() - 60) {
        return true;
    }
    touch($stamp);
    return false;
}

/** An account-security email (lockout, repeated failed log-ins). */
function send_security_notice(array $user, string $message): void
{
    if (mail_throttled('security', $user)) {
        return;
    }
    send_mail($user['email'], full_name($user), APP_NAME . ' security alert', "Hi {$user['first_name']},\n\n$message\n\n— " . APP_FULL_NAME);
}

/**
 * Sends a plain-text email through Brevo's HTTPS API (Railway blocks SMTP on its Free and Hobby plans).
 * Without BREVO_API_KEY the email is written to the PHP error log instead, which is enough for local testing.
 */
function send_mail(string $toEmail, string $toName, string $subject, string $text): bool
{
    $apiKey = setting('BREVO_API_KEY');
    if ($apiKey === '') {
        // One log call per line: Apache folds a multi-line message into one line and the link becomes hard to copy.
        foreach (explode("\n", "BREVO_API_KEY is not set, so this email was only logged.\nTo: $toEmail\nSubject: $subject\n\n$text") as $line) {
            error_log("[mail] $line");
        }
        return true;
    }
    $payload = json_encode([
        'sender'      => ['name' => setting('MAIL_FROM_NAME') ?: APP_NAME, 'email' => setting('MAIL_FROM')],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'textContent' => $text,
    ]);
    $context = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "api-key: $apiKey\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
        'content'       => $payload,
        'timeout'       => 10,
        'ignore_errors' => true,   // read Brevo's error body instead of a bare warning
    ]]);
    $response = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $context);
    $status   = preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m) ? (int) $m[1] : 0;
    if ($status >= 200 && $status < 300) {
        return true;
    }
    error_log("[mail] Brevo did not send the email to $toEmail (HTTP $status): " . ($response ?: 'no response'));
    return false;
}
