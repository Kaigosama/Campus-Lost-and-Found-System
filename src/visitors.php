<?php
declare(strict_types=1);

/**
 * Anonymous visitors (visitors table): people who browse without logging in. A record exists only after the
 * visitor accepts cookies on the banner (api/visitor.php); the browser then holds a random clafs_vid cookie and
 * the table only its SHA-256 hash, so the record is pseudonymous: no name, email, IP address or browser details.
 * Rejecting cookies deletes the record and the cookie. run_retention() deletes records unseen for
 * VISITOR_RETENTION_DAYS. Only administrators see the records (index.php?tab=visitors).
 */

const VISITOR_COOKIE = 'clafs_vid';

/** This browser's visitor row id, or null (no consent, unknown cookie, or deleted). Cached per request. */
function current_visitor_id(): ?int
{
    static $id = false;
    if ($id === false) {
        $id = null;
        $token = (string) ($_COOKIE[VISITOR_COOKIE] ?? '');
        if (PHP_SAPI !== 'cli' && preg_match('/^[0-9a-f]{64}$/', $token)) {
            $stmt = db()->prepare('SELECT visitor_id FROM visitors WHERE token_hash = ?');
            $stmt->execute([hash('sha256', $token)]);
            $id = ((int) $stmt->fetchColumn()) ?: null;
        }
    }
    return $id;
}

function set_visitor_cookie(string $token, int $expires): void
{
    setcookie(VISITOR_COOKIE, $token, ['expires' => $expires, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

/** "accepted": create this browser's record (or keep the existing one). "rejected": delete it and its cookie. */
function record_visitor_consent(string $choice): void
{
    $id = current_visitor_id();
    if ($choice === 'rejected') {
        if ($id) {
            db()->prepare('DELETE FROM visitors WHERE visitor_id = ?')->execute([$id]);
        }
        set_visitor_cookie('', time() - 3600);
        return;
    }
    if ($id) {
        return;
    }
    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO visitors (token_hash) VALUES (?)')->execute([hash('sha256', $token)]);
    set_visitor_cookie($token, time() + VISITOR_RETENTION_DAYS * 86400);
}

/** Counts a page view by a consenting visitor who is not logged in (called by the page header). */
function touch_visitor(): void
{
    if (!is_logged_in() && ($id = current_visitor_id())) {
        db()->prepare('UPDATE visitors SET last_seen_at = NOW(), page_views = page_views + 1 WHERE visitor_id = ?')->execute([$id]);
    }
}
