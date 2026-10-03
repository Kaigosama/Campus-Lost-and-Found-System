<?php
declare(strict_types=1);

/**
 * Data layer: whole tables loaded once per request and filtered in PHP.
 */

const TABLE_KEYS = ['users' => 'user_id', 'found_items' => 'item_id', 'lost_reports' => 'report_id', 'claims' => 'claim_id'];

/** Whole table keyed by primary key, loaded once per request. Pages filter in PHP with the helpers below. */
function table(string $name): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $cache[$name] = array_column(db()->query("SELECT * FROM `$name`")->fetchAll(), null, TABLE_KEYS[$name]);
    }
    return $cache[$name];
}

function all_users(): array        { return table('users'); }
function all_found_items(): array  { return table('found_items'); }
/** Lost reports still on record: removed (soft-deleted) false or spam reports are left out of every list. */
function all_lost_reports(): array { return array_filter(table('lost_reports'), fn ($r) => $r['deleted_at'] === null); }
function all_claims(): array       { return table('claims'); }

function find_user(?int $id): ?array       { return $id ? (all_users()[$id] ?? null) : null; }
function find_found_item(int $id): ?array  { return all_found_items()[$id] ?? null; }
/** Includes removed reports, for staff audits; callers check deleted_at (or require status "open", which a removed report never has). */
function find_lost_report(int $id): ?array { return table('lost_reports')[$id] ?? null; }
function find_claim(int $id): ?array       { return all_claims()[$id] ?? null; }

/** Found items the public may see: approved by staff and still in storage. */
function is_public_item(array $item): bool
{
    return $item['status'] === 'stored' && $item['moderation_status'] === 'approved';
}

/** A found item logged by mistake can be deleted until it is returned or a claim on it is approved. */
function can_delete_found_item(array $item): bool
{
    return $item['status'] !== 'returned'
        && !count_where(where(all_claims(), 'item_id', $item['item_id']), 'status', 'approved');
}

function public_found_items(): array
{
    return array_filter(all_found_items(), 'is_public_item');
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([mb_strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

/** Admin activity feed filters: label and the event_type prefixes they cover. "admin" is anything staff or admins did to records or accounts. */
const ACTIVITY_FILTERS = [
    ''        => ['All', []],
    'reports' => ['Reports', ['report_', 'false_report_']],
    'claims'  => ['Claims', ['claim_']],
    'posts'   => ['Posts', ['post_', 'found_item_']],
    'accounts' => ['Accounts', ['register', 'email_verified', 'account_', 'role_changed', 'password_', 'false_report_']],
    'auth'    => ['Authentication', ['login_', 'logout', 'session_', 'captcha_']],
    'admin'   => ['Administrative actions', []],
];

/**
 * Newest security/activity events with the account they concern (u_*) and who performed them (a_*).
 * $filter is a key of ACTIVITY_FILTERS; $userId limits it to events about or by one account.
 */
function activity_events(string $filter = '', ?int $userId = null, int $limit = 100): array
{
    $where = [];
    $args  = [];
    if ($filter === 'admin') {
        $where[] = "a.role IN ('staff', 'admin', 'master_admin') AND e.event_type NOT REGEXP '^(login_|logout|session_|captcha_|password_)'";
    } elseif ($prefixes = ACTIVITY_FILTERS[$filter][1] ?? []) {
        $where[] = '(' . implode(' OR ', array_fill(0, count($prefixes), 'e.event_type LIKE ?')) . ')';
        $args    = array_map(fn ($p) => $p . '%', $prefixes);
    }
    if ($userId) {
        $where[] = '(e.user_id = ? OR e.actor_id = ?)';
        array_push($args, $userId, $userId);
    }
    $stmt = db()->prepare('SELECT e.*, u.first_name AS u_first, u.last_name AS u_last, u.role AS u_role,
                                  a.first_name AS a_first, a.last_name AS a_last, a.role AS a_role
                           FROM security_events e LEFT JOIN users u ON u.user_id = e.user_id LEFT JOIN users a ON a.user_id = e.actor_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY e.event_id DESC LIMIT ' . $limit);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/**
 * Student / faculty accounts an admin may want to look at: false-report violations, spam reports, a lockout,
 * deactivation, submission-limit or duplicate hits in the last 7 days, or the same name as an account deactivated
 * for false reports (a possible re-registration; shown for review, never blocked automatically).
 */
function accounts_needing_attention(): array
{
    return db()->query("SELECT * FROM (
            SELECT u.*,
                (SELECT COUNT(*) FROM account_violations v WHERE v.user_id = u.user_id) AS violations,
                (SELECT COUNT(*) FROM lost_reports r WHERE r.user_id = u.user_id AND r.status = 'spam') AS spam_reports,
                (SELECT COUNT(*) FROM security_events e WHERE e.user_id = u.user_id AND e.created_at > NOW() - INTERVAL 7 DAY
                    AND e.event_type IN ('report_rate_limited', 'report_duplicate_blocked')) AS blocked_submissions,
                EXISTS (SELECT 1 FROM users d WHERE d.deactivation_reason = 'false_reports' AND d.user_id <> u.user_id
                    AND LOWER(d.first_name) = LOWER(u.first_name) AND LOWER(d.last_name) = LOWER(u.last_name)) AS name_matches_deactivated
            FROM users u WHERE u.role = 'user') t
        WHERE violations > 0 OR spam_reports > 0 OR blocked_submissions > 0 OR name_matches_deactivated OR locked_at IS NOT NULL OR is_active = 0
        ORDER BY is_active, violations DESC, spam_reports DESC, blocked_submissions DESC LIMIT 50")->fetchAll();
}

/** Rows whose $column equals $value, as a plain list. */
function where(array $rows, string $column, mixed $value): array
{
    return array_values(array_filter($rows, fn ($row) => $row[$column] === $value));
}

function count_where(array $rows, string $column, mixed $value): int
{
    return count(where($rows, $column, $value));
}

/** Filter rows by a keyword (item_name/description/location) and exact-match fields ('' or null = any). */
function search_rows(array $rows, string $keyword = '', array $exact = []): array
{
    $keyword = mb_strtolower(trim($keyword));
    return array_values(array_filter($rows, function (array $row) use ($keyword, $exact) {
        foreach ($exact as $field => $value) {
            $actual = (string) ($row[$field] ?? '');
            if ($field === 'category') $actual = listed($actual, CATEGORIES);   // a typed "Other" category matches the Other filter
            if ($value !== '' && $value !== null && $actual !== (string) $value) {
                return false;
            }
        }
        if ($keyword === '') {
            return true;
        }
        $haystack = mb_strtolower(implode(' ', [
            $row['item_name'] ?? '', $row['description'] ?? '',
            $row['location_found'] ?? '', $row['location_lost'] ?? '',
        ]));
        return str_contains($haystack, $keyword);
    }));
}
