<?php
declare(strict_types=1);

/**
 * Email verification for new accounts. The emailed link carries a random token; the database keeps only its
 * SHA-256 hash and its expiry (EMAIL_VERIFICATION_EXPIRY_MINUTES, 30 by default). Using the link clears the hash,
 * so it works once; sending a new link replaces the hash, so earlier links stop working.
 */

/** "30 minutes", "1 hour", "2 hours" for the link lifetime. */
function verification_lifetime(): string
{
    $m = EMAIL_VERIFICATION_EXPIRY_MINUTES;
    return $m % 60 ? $m . ' minute' . ($m === 1 ? '' : 's') : ($m / 60) . ' hour' . ($m === 60 ? '' : 's');
}

/** Emails $user a fresh verification link. Any earlier link stops working. */
function send_verification_email(array $user): void
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE users SET email_verification_token_hash = ?, email_verification_expires_at = NOW() + INTERVAL ? MINUTE WHERE user_id = ?')
        ->execute([hash('sha256', $token), EMAIL_VERIFICATION_EXPIRY_MINUTES, $user['user_id']]);
    $text = "Hi {$user['first_name']},\n\n"
        . 'Confirm your email address to finish creating your ' . APP_NAME . " account:\n\n"
        . app_base_url() . url('/verify_email.php?token=' . $token) . "\n\n"
        . 'The link works once and expires in ' . verification_lifetime() . ". If it expires, request a new one from the log-in page.\n"
        . "If you didn't sign up, ignore this email.\n\n"
        . '— ' . APP_NAME;
    send_mail($user['email'], full_name($user), 'Confirm your ' . APP_NAME . ' email address', $text);
}

/**
 * What a link's token is: ['valid', user], ['expired', user] (the newest link of an unverified account, out of
 * time) or ['invalid', null] (malformed, replaced by a newer link, already used, or the account is confirmed).
 */
function verification_token_state(string $token): array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return ['invalid', null];
    }
    $stmt = db()->prepare('SELECT *, email_verification_expires_at > NOW() AS link_valid FROM ' . USERS_SQL . ' t WHERE email_verification_token_hash = ? AND email_verified = 0');
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();
    return !$user ? ['invalid', null] : [$user['link_valid'] ? 'valid' : 'expired', $user];
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
 * Deletes student / faculty accounts left unconfirmed UNVERIFIED_GRACE_HOURS after their link expired, so the
 * address and school number can register again. Until then a new link can still be requested.
 * Accounts that somehow own reports, posts or claims are kept (those foreign keys would block the delete anyway).
 */
function delete_expired_unverified(): int
{
    $deleted = db()->exec("DELETE u FROM users u
        WHERE u.role = 'user' AND u.email_verified = 0
          AND (u.email_verification_expires_at IS NULL OR u.email_verification_expires_at < NOW() - INTERVAL " . UNVERIFIED_GRACE_HOURS . " HOUR)
          AND NOT EXISTS (SELECT 1 FROM lost_reports r WHERE r.reported_by = u.user_id)
          AND NOT EXISTS (SELECT 1 FROM found_items f WHERE f.added_by = u.user_id)
          AND NOT EXISTS (SELECT 1 FROM claims c WHERE c.claimant_id = u.user_id)");
    if ($deleted) {
        log_event('unverified_accounts_deleted', null, ['count' => $deleted]);
    }
    return (int) $deleted;
}

/**
 * Data retention, run on each deploy (seed.php) and when an admin opens the dashboard (there is no cron):
 * expired unconfirmed sign-ups and the masked ID numbers of identity checks older than ID_RECORD_RETENTION_DAYS
 * (the check, its result and who did it stay on record).
 * Returns [what => rows affected].
 */
function run_retention(): array
{
    $done = ['unverified accounts deleted' => delete_expired_unverified()];
    $done['ID references cleared'] = (int) db()->exec('UPDATE claim_verifications SET id_reference = NULL
        WHERE id_reference IS NOT NULL AND verified_at < NOW() - INTERVAL ' . ID_RECORD_RETENTION_DAYS . ' DAY');
    return array_filter($done);
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
