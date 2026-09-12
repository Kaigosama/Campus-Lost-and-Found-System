<?php
/**
 * View helpers shared by every page.
 */

/** Escape a value for safe output inside HTML. Use on every dynamic value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an app URL from a path relative to the web root, e.g. url('/found/browse.php'). */
function url(string $path = '/'): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL for a file inside public/assets. */
function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

/** Returns "active" when the current script matches $path (or starts with it when $prefix is true). */
function is_active(string $path, bool $prefix = false): string
{
    $current = $_SERVER['SCRIPT_NAME'] ?? '';
    $target  = url($path);
    $match   = $prefix ? str_starts_with($current, $target) : $current === $target;
    return $match ? 'active' : '';
}

/** Current URL with some query parameters replaced — used by filters, pagination and the preview switcher. */
function url_with(array $params): string
{
    $query = array_merge($_GET, $params);
    $query = array_filter($query, fn ($v) => $v !== null && $v !== '');
    $path  = $_SERVER['SCRIPT_NAME'] ?? url('/');
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/** Human-readable label for any status key. */
function status_label(string $status): string
{
    $labels = LOST_STATUSES + FOUND_STATUSES + CLAIM_STATUSES;
    return $labels[$status] ?? ucfirst($status);
}

/** Coloured status pill. */
function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(status_label($status)) . '</span>';
}

/** "Sep 8, 2026" from a date or datetime string. */
function format_date(?string $value): string
{
    if (!$value) {
        return '—';
    }
    return date('M j, Y', strtotime($value));
}

/** "Sep 8, 2026 · 2:15 PM" */
function format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    return date('M j, Y · g:i A', strtotime($value));
}

function full_name(array $user): string
{
    return trim($user['first_name'] . ' ' . $user['last_name']);
}

function initials(array $user): string
{
    return mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
}

/** <img> for an item photo, or a placeholder block when there is none. */
function photo_tag(?string $path, string $alt, string $class = 'photo'): string
{
    if ($path) {
        return '<img src="' . e(url($path)) . '" alt="' . e($alt) . '" class="' . e($class) . '" loading="lazy">';
    }
    return '<div class="' . e($class) . ' photo-placeholder" role="img" aria-label="No photo available">'
        . '<svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
        . '<path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg>'
        . '<span>No photo</span></div>';
}

/** <option> list from an assoc array (value => label) or plain list, with the selected value marked. */
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

/** Truncate text to $length characters, adding an ellipsis. */
function excerpt(string $text, int $length = 110): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
}

/** Split an array into pages; returns [items_on_page, current_page, total_pages]. */
function paginate(array $items, int $perPage = 8): array
{
    $total   = max(1, (int) ceil(count($items) / $perPage));
    $page    = min($total, max(1, (int) ($_GET['page'] ?? 1)));
    $slice   = array_slice($items, ($page - 1) * $perPage, $perPage);
    return [$slice, $page, $total];
}
