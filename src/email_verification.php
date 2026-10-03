<?php
declare(strict_types=1);

/**
 * Email verification for new accounts. The emailed link carries a random token; the database keeps only its
 * SHA-256 hash and an expiry. Using the link clears the hash, so it works once.
 */

/** Emails $user a fresh verification link. Any earlier link stops working. */
function send_verification_email(array $user): void
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE users SET email_verification_token_hash = ?, email_verification_expires_at = NOW() + INTERVAL ? HOUR WHERE user_id = ?')
        ->execute([hash('sha256', $token), VERIFY_LINK_HOURS, $user['user_id']]);
    $text = "Hi {$user['first_name']},\n\n"
        . 'Confirm your email address to finish creating your ' . APP_NAME . " account:\n\n"
        . app_base_url() . url('/verify_email.php?token=' . $token) . "\n\n"
        . 'The link works once and expires in ' . VERIFY_LINK_HOURS . " hours. If you didn't sign up, ignore this email.\n\n"
        . '— ' . APP_NAME;
    send_mail($user['email'], full_name($user), 'Confirm your ' . APP_NAME . ' email address', $text);
}

/** The unverified account a link's token belongs to, or null when it is invalid, expired or already used. */
function user_from_verification_token(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE email_verification_token_hash = ? AND email_verified = 0 AND email_verification_expires_at > NOW()');
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

function mark_email_verified(array $user): void
{
    db()->prepare('UPDATE users SET email_verified = 1, email_verified_at = NOW(), email_verification_token_hash = NULL, email_verification_expires_at = NULL WHERE user_id = ?')
        ->execute([$user['user_id']]);
    log_event('email_verified', $user);
}

/** Sends a new link when $email belongs to an active, unverified account. Says nothing either way. */
function resend_verification(string $email): void
{
    $user = find_user_by_email($email);
    if (!$user || !$user['is_active'] || $user['email_verified'] || mail_throttled('verify', $user)) {
        return;
    }
    send_verification_email($user);
    log_event('verification_resent', $user);
}

/**
 * Deletes student / faculty accounts whose verification link expired unconfirmed, so the address can register again.
 * Accounts that somehow own reports, posts or claims are kept (those foreign keys would block the delete anyway).
 * ponytail: no cron here, so this runs on each deploy (seed.php) and when an admin opens the dashboard.
 */
function delete_expired_unverified(): int
{
    $deleted = db()->exec("DELETE u FROM users u
        WHERE u.role = 'user' AND u.email_verified = 0
          AND (u.email_verification_expires_at IS NULL OR u.email_verification_expires_at < NOW())
          AND NOT EXISTS (SELECT 1 FROM lost_reports r WHERE r.user_id = u.user_id)
          AND NOT EXISTS (SELECT 1 FROM found_items f WHERE f.user_id = u.user_id)
          AND NOT EXISTS (SELECT 1 FROM claims c WHERE c.user_id = u.user_id)");
    if ($deleted) {
        log_event('unverified_accounts_deleted', null, ['count' => $deleted]);
    }
    return (int) $deleted;
}

/** Someone registered with an email that already has an account: tell its owner instead of the visitor. */
function notify_existing_account(array $user): void
{
    if (!$user['email_verified']) {
        resend_verification($user['email']);
        return;
    }
    if (mail_throttled('exists', $user)) {
        return;
    }
    $text = "Hi {$user['first_name']},\n\n"
        . 'Someone tried to create a new ' . APP_NAME . " account with this email address, but you already have one.\n\n"
        . 'Log in: ' . app_base_url() . url('/login.php') . "\n"
        . 'Forgot your password? ' . app_base_url() . url('/forgot_password.php') . "\n\n"
        . "If this wasn't you, you can ignore this email.\n\n"
        . '— ' . APP_NAME;
    send_mail($user['email'], full_name($user), 'You already have a ' . APP_NAME . ' account', $text);
}
