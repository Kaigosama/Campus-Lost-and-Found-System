<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: lost reports own; every report for staff. */ ?>
<div class="page-header">
    <?php if (can_monitor()): ?>
        <div><h1>Lost reports</h1><p>Every report filed by students and faculty, newest first. <?= is_staff() ? 'Open one to match it to a found item.' : 'Read-only for administrators.' ?></p></div>
    <?php else: ?>
        <div><h1>My lost reports</h1><p>Everything you've reported, newest first.</p></div>
        <a class="btn btn-primary" href="<?= e(url('/report.php')) ?>">+ New report</a>
    <?php endif; ?>
</div>

    <?php
    $status = $_GET['status'] ?? '';
    // Staff's "All" is the working list: rejected, false and spam reports only appear under their own tab, and
    // removed ones under "Removed" (kept for audits). Students and faculty see every report of their own that wasn't removed.
    $working = can_monitor() ? array_values(array_filter($reportRows, fn ($r) => !is_moderated_report($r))) : $reportRows;
    $removed = can_monitor() ? newest_first(array_values(array_filter(table('lost_reports'), fn ($r) => $r['deleted_at'] !== null))) : [];
    $counts  = ['' => count($working), 'removed' => count($removed)];
    foreach (LOST_STATUSES + REPORT_MODERATION_STATUSES as $key => $label) {
        $counts[$key] = count_where($reportRows, 'status', $key);
    }
    $rows = match ($status) { '' => $working, 'removed' => $removed, default => where($reportRows, 'status', $status) };

    // Staff and admins also search by keyword, category and date lost (reports are dated within the past year).
    $q        = trim((string) ($_GET['q'] ?? ''));
    $category = (string) ($_GET['category'] ?? '');
    $clamp    = fn (string $d) => $d === '' ? '' : min(max($d, earliest_item_date()), date('Y-m-d'));
    $from     = $clamp((string) ($_GET['from'] ?? ''));
    $to       = $clamp((string) ($_GET['to'] ?? ''));
    $filtered = $q !== '' || $category !== '' || $from !== '' || $to !== '';
    $rows = search_rows($rows, $q, ['category' => $category]);
    if ($from !== '') $rows = array_values(array_filter($rows, fn ($r) => $r['date_lost'] >= $from));
    if ($to !== '')   $rows = array_values(array_filter($rows, fn ($r) => $r['date_lost'] <= $to));
    [$pageRows, $page, $totalPages] = paginate($rows, 10);

    $pills = ['' => 'All'] + LOST_STATUSES + REPORT_MODERATION_STATUSES + (can_monitor() ? ['removed' => 'Removed'] : []);
    if (!can_monitor()) {   // an owner sees an outcome tab only when one of their reports has that outcome
        $pills = array_filter($pills, fn ($key) => !isset(REPORT_MODERATION_STATUSES[$key]) || $counts[$key] > 0, ARRAY_FILTER_USE_KEY);
    }
    echo pill_tabs($pills, $counts, $status, 'status');
    ?>

    <?php if (can_monitor()): ?>
    <form method="get" action="<?= e(url('/')) ?>" class="filter-bar" role="search">
        <input type="hidden" name="tab" value="reports">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <div class="form-group grow">
            <label for="q">Keyword</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Item, description, location…">
        </div>
        <div class="form-group filter-extra">
            <label for="category">Category</label>
            <select id="category" name="category"><option value="">Any</option><?= options(category_names(), $category, false) ?></select>
        </div>
        <div class="form-group filter-extra">
            <label for="from">Lost from</label>
            <input type="date" id="from" name="from" value="<?= e($from) ?>" min="<?= earliest_item_date() ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group filter-extra">
            <label for="to">Lost to</label>
            <input type="date" id="to" name="to" value="<?= e($to) ?>" min="<?= earliest_item_date() ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a class="btn btn-secondary" href="<?= e(url_with(['q' => null, 'category' => null, 'from' => null, 'to' => null, 'page' => null])) ?>">Reset</a>
        <button type="button" class="btn btn-ghost filter-toggle" aria-expanded="true" data-filter-toggle hidden>More filters</button>
    </form>
    <p class="result-count"><?= count($rows) ?> report<?= count($rows) === 1 ? '' : 's' ?></p>
    <?php endif; ?>

    <?php if ($pageRows): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th>Item</th><?= can_monitor() ? '<th>Reported by</th>' : '' ?><th>Category</th><th>Lost on</th><th>Where</th><th>Status</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($pageRows as $report): ?>
                <tr>
                    <td><?= photo_tag($report['image_url'], $report['item_name'], 'photo-thumb') ?></td>
                    <td>
                        <a class="table-title" href="<?= e(item_url('lost', $report['report_id'])) ?>"><?= e($report['item_name']) ?></a>
                        <span class="table-sub"><?= e(excerpt($report['description'], 70)) ?></span>
                    </td>
                    <?php if (can_monitor()): $owner = find_user($report['reported_by']); ?>
                        <td><?= e(full_name($owner)) ?><span class="table-sub"><?= e($owner['email']) ?></span></td>
                    <?php endif; ?>
                    <td><?= e($report['category']) ?></td>
                    <td class="nowrap"><?= e(format_date($report['date_lost'])) ?></td>
                    <td><?= e($report['location_lost']) ?></td>
                    <td><?= status_badge($report['status']) ?><?= $report['deleted_at'] ? ' <span class="badge badge-closed">Removed</span>' : '' ?></td>
                    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(item_url('lost', $report['report_id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination($page, $totalPages) ?>
    <?php else: ?>
        <?php $emptyTitle = $filtered ? 'No reports match those filters' : ($status ? 'No ' . strtolower(status_label($status)) . ' reports' : 'No lost reports yet'); ?>
        <?= can_monitor()
            ? empty_state($emptyTitle, $filtered ? 'Try a broader keyword or clear the filters.' : 'Reports filed by students and faculty show up here.')
            : empty_state($emptyTitle, 'When you report a lost item it will show up here with its current status.', url('/report.php'), 'Report a lost item') ?>
    <?php endif; ?>
