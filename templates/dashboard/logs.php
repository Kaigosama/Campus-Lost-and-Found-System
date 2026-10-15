<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: security logs admin. */ ?>
<div class="page-header">
    <div><h1>Security logs</h1><p>Log-ins, failures, lockouts and session history. Session tokens are never shown.</p></div>
    <div class="btn-row">
        <a class="btn btn-outline" href="<?= e(url('/export_logs.php?format=pdf')) ?>" download>Download PDF</a>
        <a class="btn btn-outline" href="<?= e(url('/export_logs.php?format=csv&table=events')) ?>" download>Events CSV</a>
        <a class="btn btn-outline" href="<?= e(url('/export_logs.php?format=csv&table=sessions')) ?>" download>Sessions CSV</a>
    </div>
</div>

    <?php
    // Each table is searched, filtered and paged on its own: s* query parameters for sessions, e* for events.
    $perPage = 10;
    $logPage = function (string $from, array $where, array $args, string $select, string $order, string $param) use ($perPage): array {
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = db()->prepare("SELECT COUNT(*) $from $sqlWhere");
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min($pages, max(1, (int) ($_GET[$param] ?? 1)));
        $stmt  = db()->prepare("SELECT $select $from $sqlWhere ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
        $stmt->execute($args);
        return [$stmt->fetchAll(), $page, $pages, $total];
    };
    $like = fn (string $q) => '%' . addcslashes($q, '%_\\') . '%';

    $sessionBadge = ['active' => 'active', 'logged_out' => 'closed', 'expired' => 'pending', 'revoked' => 'rejected'];
    $sq      = trim((string) ($_GET['sq'] ?? ''));
    $sstatus = isset($sessionBadge[$_GET['sstatus'] ?? '']) ? $_GET['sstatus'] : '';
    $where = $args = [];
    if ($sstatus !== '') { $where[] = 's.status = ?'; $args[] = $sstatus; }
    if ($sq !== '') {
        $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR s.ip_address LIKE ? OR s.user_agent LIKE ?)";
        array_push($args, ...array_fill(0, 4, $like($sq)));
    }
    [$sessionRows, $spage, $spages, $stotal] = $logPage('FROM user_sessions s JOIN ' . USERS_SQL . ' u ON u.user_id = s.user_id', $where, $args,
        's.session_id, s.user_id, s.status, s.ip_address, s.user_agent, s.created_at, s.last_activity_at, s.expires_at, s.ended_at, u.first_name, u.last_name, u.email, u.role',
        's.session_id DESC', 'spage');

    $eventTypes = db()->query('SELECT DISTINCT event_type FROM security_events ORDER BY event_type')->fetchAll(PDO::FETCH_COLUMN);
    $eq    = trim((string) ($_GET['eq'] ?? ''));
    $etype = in_array($_GET['etype'] ?? '', $eventTypes, true) ? $_GET['etype'] : '';
    $where = $args = [];
    if ($etype !== '') { $where[] = 'e.event_type = ?'; $args[] = $etype; }
    if ($eq !== '') {
        $where[] = "(REPLACE(e.event_type, '_', ' ') LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR e.email LIKE ?
                     OR e.ip_address LIKE ? OR e.user_agent LIKE ? OR CAST(e.metadata AS CHAR) LIKE ?)";
        array_push($args, ...array_fill(0, 7, $like($eq)));
    }
    [$events, $epage, $epages, $etotal] = $logPage('FROM security_events e LEFT JOIN ' . USERS_SQL . ' u ON u.user_id = e.user_id', $where, $args,
        'e.*, u.first_name, u.last_name, u.role', 'e.event_id DESC', 'epage');

    // Hidden inputs that keep the other table's search, filter and page when one table's form is submitted.
    $keep = fn (array $names) => implode('', array_map(fn ($n) => isset($_GET[$n]) && $_GET[$n] !== '' ? '<input type="hidden" name="' . $n . '" value="' . e((string) $_GET[$n]) . '">' : '', $names));
    ?>
    <h2 id="sessions">Login sessions</h2>
    <form method="get" action="<?= e(url('/')) ?>#sessions" class="filter-bar" role="search" data-auto-submit>
        <input type="hidden" name="tab" value="logs"><?= $keep(['eq', 'etype', 'epage']) ?>
        <div class="form-group grow">
            <label for="sq">Search</label>
            <input type="search" id="sq" name="sq" value="<?= e($sq) ?>" placeholder="Name, email, IP, device…">
        </div>
        <div class="form-group">
            <label for="sstatus">Status</label>
            <select id="sstatus" name="sstatus">
                <option value="">All statuses</option>
                <?php foreach (array_keys($sessionBadge) as $st): ?>
                    <option value="<?= $st ?>" <?= $sstatus === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <a class="btn btn-secondary" href="<?= e(url_with(['sq' => null, 'sstatus' => null, 'spage' => null]) . '#sessions') ?>">Reset</a>
    </form>
    <p class="result-count"><?= $stotal ?> session<?= $stotal === 1 ? '' : 's' ?></p>
    <?php if ($sessionRows): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Account</th><th>Status</th><th>Logged in</th><th>Last activity</th><th>Ended / expires</th><th>IP</th><th>Device</th></tr></thead>
            <tbody>
            <?php foreach ($sessionRows as $s): ?>
                <tr>
                    <td class="text-muted"><?= $s['session_id'] ?></td>
                    <td><?= e(full_name($s)) ?><span class="table-sub"><?= e($s['email']) ?> &middot; <?= e(ROLES[$s['role']] ?? $s['role']) ?></span></td>
                    <td><span class="badge badge-<?= $sessionBadge[$s['status']] ?>"><?= e(ucwords(str_replace('_', ' ', $s['status']))) ?></span></td>
                    <td class="nowrap"><?= e(format_datetime($s['created_at'])) ?></td>
                    <td class="nowrap"><?= e(format_datetime($s['last_activity_at'])) ?></td>
                    <td class="nowrap"><?= e($s['ended_at'] ? format_datetime($s['ended_at']) : 'expires ' . date('g:i A', strtotime($s['expires_at']))) ?></td>
                    <td class="nowrap"><?= e($s['ip_address'] ?? '—') ?></td>
                    <td class="cell-wide text-sm" title="<?= e($s['user_agent']) ?>"><?= e(excerpt((string) $s['user_agent'], 60)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= arrow_pager($spage, $spages, 'spage', 'sessions') ?>
    <?php else: ?>
        <p class="text-muted">No sessions match that search.</p>
    <?php endif; ?>

    <h2 id="events" class="mt-3">Security events</h2>
    <form method="get" action="<?= e(url('/')) ?>#events" class="filter-bar" role="search" data-auto-submit>
        <input type="hidden" name="tab" value="logs"><?= $keep(['sq', 'sstatus', 'spage']) ?>
        <div class="form-group grow">
            <label for="eq">Search</label>
            <input type="search" id="eq" name="eq" value="<?= e($eq) ?>" placeholder="Event, account, IP, details…">
        </div>
        <div class="form-group">
            <label for="etype">Event</label>
            <select id="etype" name="etype">
                <option value="">All events</option>
                <?php foreach ($eventTypes as $t): ?>
                    <option value="<?= e($t) ?>" <?= $etype === $t ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <a class="btn btn-secondary" href="<?= e(url_with(['eq' => null, 'etype' => null, 'epage' => null]) . '#events') ?>">Reset</a>
    </form>
    <p class="result-count"><?= $etotal ?> event<?= $etotal === 1 ? '' : 's' ?></p>
    <?php if ($events): ?>
    <div class="table-wrap">
        <table class="table" id="eventsTable">
            <thead><tr><th>When</th><th>Event</th><th>Account</th><th>Details</th><th>IP</th><th>Device</th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev): $meta = json_decode((string) $ev['metadata'], true) ?: []; ?>
                <tr>
                    <td class="nowrap"><?= e(format_datetime($ev['created_at'])) ?></td>
                    <td class="nowrap"><span class="badge badge-<?= e(event_badge($ev['event_type'])) ?>"><?= e(str_replace('_', ' ', $ev['event_type'])) ?></span></td>
                    <td>
                        <?= $ev['user_id'] ? e(full_name($ev)) : '<span class="text-muted">Unknown account</span>' ?>
                        <span class="table-sub"><?= e($ev['email'] ?? '') ?><?= $ev['role'] ? ' &middot; ' . e(ROLES[$ev['role']] ?? $ev['role']) : '' ?></span>
                    </td>
                    <td class="text-sm"><?php foreach ($meta as $k => $v): ?><?= e(str_replace('_', ' ', (string) $k)) ?>: <?= e(is_scalar($v) ? (string) $v : json_encode($v)) ?><br><?php endforeach; ?><?= $ev['session_id'] ? '<span class="text-muted">session #' . (int) $ev['session_id'] . '</span>' : '' ?></td>
                    <td class="nowrap"><?= e($ev['ip_address'] ?? '—') ?></td>
                    <td class="cell-wide text-sm" title="<?= e($ev['user_agent']) ?>"><?= e(excerpt((string) $ev['user_agent'], 50)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= arrow_pager($epage, $epages, 'epage', 'events') ?>
    <?php else: ?>
        <p class="text-muted">No events match that search.</p>
    <?php endif; ?>
