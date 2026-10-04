<?php
declare(strict_types=1);

/**
 * Reusable pieces of HTML: empty states, item cards, pagination, filter tabs and error pages.
 */

function empty_state(string $title, string $text = '', string $actionUrl = '', string $actionLabel = 'Continue', string $icon = '&#128269;'): string
{
    $html = '<div class="empty-state"><div class="empty-icon" aria-hidden="true">' . $icon . '</div><h2>' . e($title) . '</h2>';
    if ($text !== '') {
        $html .= '<p>' . e($text) . '</p>';
    }
    if ($actionUrl !== '') {
        $html .= '<p><a class="btn btn-primary" href="' . e($actionUrl) . '">' . e($actionLabel) . '</a></p>';
    }
    return $html . '</div>';
}

/**
 * Reset button for a long form: puts every field back to how the page loaded (blank, or the saved values when editing).
 * app.js keeps it disabled until a field has changed.
 */
function reset_button(): string
{
    return '<button type="reset" class="btn btn-ghost btn-lg" data-confirm="Are you sure you would like to reset?">Reset</button>';
}

/** Found-item card for the browse grid. Public fields only. */
function item_card(array $item): string
{
    $href = item_url('found', $item['item_id']);
    ob_start(); ?>
<article class="item-card">
    <div class="item-card-photo"><?= photo_tag($item['image_url'], $item['item_name']) ?></div>
    <div class="item-card-body">
        <span class="item-card-category"><?= e($item['category']) ?></span>
        <h3 class="item-card-title"><a href="<?= e($href) ?>"><?= e($item['item_name']) ?></a></h3>
        <p class="item-card-desc"><?= e(excerpt($item['description'])) ?></p>
        <dl class="item-card-meta">
            <div><dt>Found</dt><dd><?= e(format_date($item['date_found'])) ?></dd></div>
            <div><dt>Where</dt><dd><?= e($item['location_found']) ?></dd></div>
        </dl>
    </div>
    <div class="item-card-footer">
        <?= status_badge($item['status']) ?>
        <span class="btn btn-outline btn-sm" aria-hidden="true">View details</span>
    </div>
</article>
<?php
    return ob_get_clean();
}

function pagination(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }
    ob_start(); ?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e(url_with(['page' => $page - 1])) ?>" rel="prev">&laquo; Prev</a>
    <?php else: ?>
        <span class="disabled">&laquo; Prev</span>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current" aria-current="page"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= e(url_with(['page' => $i])) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
        <a href="<?= e(url_with(['page' => $page + 1])) ?>" rel="next">Next &raquo;</a>
    <?php else: ?>
        <span class="disabled">Next &raquo;</span>
    <?php endif; ?>
</nav>
<?php
    return ob_get_clean();
}

/** Filter tabs from [key => label] with a count per key; $param is the query parameter that selects a tab. */
function pill_tabs(array $tabs, array $counts, string $current, string $param): string
{
    $html = '<nav class="pill-tabs" aria-label="Filter">';
    foreach ($tabs as $key => $label) {
        $active = $current === $key ? ' aria-current="page"' : '';
        $count  = isset($counts[$key]) ? ' <span class="count">' . $counts[$key] . '</span>' : '';
        $html  .= '<a href="' . e(url_with([$param => $key === '' ? null : $key, 'page' => null])) . '"' . $active . '>' . e($label) . $count . '</a>';
    }
    return $html . '</nav>';
}

/**
 * Admin activity table from activity_events(): who acted, what they did, the account affected when it isn't
 * themselves, the record involved (report, claim, item) and when. Reasons and notes are shown as recorded.
 */
function activity_table(array $events, string $id = 'activityTable'): string
{
    if (!$events) {
        return empty_state('No activity yet', 'Matching events show up here as people use the system.');
    }
    $person = fn (?string $first, ?string $last, ?string $role) => '<span class="table-title">' . e(trim("$first $last")) . '</span>'
        . '<span class="table-sub">' . e(ROLES[$role] ?? (string) $role) . '</span>';
    ob_start(); ?>
<div class="table-wrap">
    <table class="table" id="<?= e($id) ?>">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Account affected</th><th>Record</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($events as $ev):
            $meta   = json_decode((string) $ev['metadata'], true) ?: [];
            $actor  = $ev['actor_id'] ?? null;
            $target = array_filter([
                isset($meta['report_id']) ? 'Report #' . $meta['report_id'] : null,
                isset($meta['claim_id']) ? 'Claim #' . $meta['claim_id'] : null,
                isset($meta['item_id']) ? 'Item #' . $meta['item_id'] : null,
            ]);
            $details = array_diff_key($meta, ['report_id' => 1, 'claim_id' => 1, 'item_id' => 1, 'by' => 1]);
        ?>
            <tr>
                <td class="nowrap"><?= e(time_ago($ev['created_at'])) ?><span class="table-sub"><?= e(format_datetime($ev['created_at'])) ?></span></td>
                <td>
                    <?php if ($actor): ?><?= $person($ev['a_first'], $ev['a_last'], $ev['a_role']) ?>
                    <?php elseif ($ev['user_id']): ?><?= $person($ev['u_first'], $ev['u_last'], $ev['u_role']) ?>
                    <?php else: ?><span class="text-muted">Not signed in</span><span class="table-sub"><?= e($ev['email'] ?? '') ?></span><?php endif; ?>
                </td>
                <td><span class="badge badge-<?= e(event_badge($ev['event_type'])) ?>"><?= e(event_label($ev['event_type'])) ?></span></td>
                <td><?= $actor && $ev['user_id'] && (int) $ev['user_id'] !== (int) $actor ? $person($ev['u_first'], $ev['u_last'], $ev['u_role']) : '<span class="text-muted">—</span>' ?></td>
                <td class="nowrap"><?= $target ? e(implode(', ', $target)) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-sm cell-wide"><?php foreach ($details as $k => $v): ?><?= e(str_replace('_', ' ', (string) $k)) ?>: <?= e(is_scalar($v) ? (string) $v : json_encode($v)) ?><br><?php endforeach; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
    return ob_get_clean();
}

/** Render a full-page message (404, 403, …) inside the normal layout and stop. */
function abort(int $code, string $title, string $text = '', string $backUrl = '', string $backLabel = 'Go back', string $icon = '&#128269;'): void
{
    http_response_code($code);
    $pageTitle = $title;
    include APP_ROOT . '/templates/layout/header.php';
    echo empty_state($title, $text, $backUrl, $backLabel, $icon);
    include APP_ROOT . '/templates/layout/footer.php';
    exit;
}
