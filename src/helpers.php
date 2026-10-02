<?php
declare(strict_types=1);

/**
 * Escaping, URLs and small formatting helpers used by pages and templates.
 */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** CSS and JS get ?v=<mtime> so browsers fetch the new file after a deploy instead of a cached copy. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = APP_ROOT . '/public/' . $path;
    $version = preg_match('/\.(css|js)$/', $path) && is_file($file) ? '?v=' . filemtime($file) : '';
    return url('/' . $path) . $version;
}

function item_url(string $type, int $id): string
{
    return url('/view_item.php?type=' . $type . '&id=' . $id);
}

/** ' aria-current="page"' when the current script is $path and $when holds (styled in CSS by that attribute). */
function is_active(string $path, bool $when = true): string
{
    return $when && ($_SERVER['SCRIPT_NAME'] ?? '') === url($path) ? ' aria-current="page"' : '';
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

/** "Active now", "Active 5 minutes ago", "Last active yesterday"… from the last authenticated request. */
function activity_label(?string $lastActivity, int $onlineSessions): string
{
    if (!$lastActivity) {
        return 'Never';
    }
    $at  = strtotime($lastActivity);
    $ago = max(0, time() - $at);
    $plural = fn (int $n, string $unit) => "$n $unit" . ($n === 1 ? '' : 's');
    return match (true) {
        $onlineSessions > 0 && $ago < 120             => 'Active now',
        $ago < 3600                                   => 'Active ' . $plural(max(1, intdiv($ago, 60)), 'minute') . ' ago',
        $ago < 86400                                  => 'Active ' . $plural(intdiv($ago, 3600), 'hour') . ' ago',
        date('Y-m-d', $at) === date('Y-m-d', strtotime('-1 day')) => 'Last active yesterday',
        default                                       => 'Last active ' . format_date($lastActivity),
    };
}

/** Badge colour class for a security event type. */
function event_badge(string $type): string
{
    return match (true) {
        in_array($type, ['login_success', 'email_verified', 'account_unlocked', 'account_reactivated', 'register'], true) => 'approved',
        in_array($type, ['account_locked', 'login_blocked_locked', 'session_revoked', 'account_deactivated', 'captcha_failed'], true) => 'rejected',
        str_starts_with($type, 'login_') => 'pending',
        default => 'closed',
    };
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
