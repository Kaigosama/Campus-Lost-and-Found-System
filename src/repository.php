<?php
declare(strict_types=1);

/**
 * Data layer: whole tables loaded once per request and filtered in PHP.
 */

/**
 * users with each account's name resolved: a Student / Faculty account (or an office account linked to an employee
 * record) is named by its school record; only accounts with no record keep a name of their own in users.
 * Every query that shows a person's name reads users through this. List new users columns here too.
 */
const USERS_SQL = '(SELECT u.user_id, u.email, u.student_number, u.employee_number, u.password_hash, u.role, u.is_active,
        u.deactivation_reason, u.deactivated_at, u.deactivated_by, u.email_verified, u.email_verified_at,
        u.email_verification_token_hash, u.email_verification_expires_at, u.failed_login_attempts, u.locked_at,
        u.last_login_at, u.last_activity_at, u.created_at, u.updated_at,
        COALESCE(s.first_name, e.first_name, u.first_name) AS first_name, COALESCE(s.last_name, e.last_name, u.last_name) AS last_name
    FROM users u LEFT JOIN students s ON s.student_number = u.student_number LEFT JOIN employees e ON e.employee_number = u.employee_number)';

const TABLE_KEYS = [
    'users' => 'user_id', 'found_items' => 'item_id', 'lost_reports' => 'report_id', 'claims' => 'claim_id',
    'categories' => 'category_id', 'claim_reviews' => 'review_id', 'claim_verifications' => 'verification_id',
];
/**
 * Tables read with extra columns:
 *   found_items, lost_reports  category_name (the category row) and category (what is shown: the "Other" detail or the name)
 *   claims                     the latest claim_reviews row: review_outcome, reviewed_by, review_note, reviewed_at
 */
const TABLE_QUERIES = [
    'users'        => 'SELECT * FROM ' . USERS_SQL . ' t',
    'found_items'  => 'SELECT t.*, c.name AS category_name, COALESCE(t.category_detail, c.name) AS category
                       FROM found_items t JOIN categories c ON c.category_id = t.category_id',
    'lost_reports' => 'SELECT t.*, c.name AS category_name, COALESCE(t.category_detail, c.name) AS category
                       FROM lost_reports t JOIN categories c ON c.category_id = t.category_id',
    'claims'       => 'SELECT c.*, r.outcome AS review_outcome, r.reviewer_id AS reviewed_by, r.reason AS review_note, r.created_at AS reviewed_at
                       FROM claims c LEFT JOIN claim_reviews r
                         ON r.review_id = (SELECT MAX(review_id) FROM claim_reviews WHERE claim_id = c.claim_id)',
];

/** Whole table keyed by primary key, loaded once per request. Pages filter in PHP with the helpers below. */
function table(string $name): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $cache[$name] = array_column(db()->query(TABLE_QUERIES[$name] ?? "SELECT * FROM `$name`")->fetchAll(), null, TABLE_KEYS[$name]);
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

/** Returned and disposed items are final: no normal edit, claim or status change. */
function is_final_item(array $item): bool
{
    return $item['status'] !== 'stored';
}

/**
 * A found item logged by mistake can be deleted while it is in storage and no claim on it has been reviewed,
 * so review history is never deleted with it.
 */
function can_delete_found_item(array $item): bool
{
    return !is_final_item($item)
        && !array_filter(where(all_claims(), 'item_id', $item['item_id']), fn ($c) => $c['review_outcome'] !== null);
}

function public_found_items(): array
{
    return array_filter(all_found_items(), 'is_public_item');
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM ' . USERS_SQL . ' t WHERE email = ? LIMIT 1');
    $stmt->execute([mb_strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

/** Admin activity feed filters: label and the event_type prefixes they cover. "admin" is anything staff or admins did to records or accounts. */
const ACTIVITY_FILTERS = [
    ''        => ['All', []],
    'reports' => ['Reports', ['report_', 'false_report_']],
    'claims'  => ['Claims', ['claim_']],
    'posts'   => ['Posts & items', ['post_', 'found_item_', 'category_']],
    'accounts' => ['Accounts', ['register', 'email_verified', 'account_', 'role_changed', 'password_', 'false_report_']],
    'auth'    => ['Authentication', ['login_', 'logout', 'session_', 'captcha_']],
    'admin'   => ['Administrative actions', []],
];

/**
 * Newest security/activity events with the account they concern (u_*) and who performed them (a_*).
 * $filter is a key of ACTIVITY_FILTERS; $userId limits it to events about or by one account; $itemId to events
 * about one found item (logged, edited, claimed, reviewed, verified, returned, disposed).
 */
function activity_events(string $filter = '', ?int $userId = null, int $limit = 100, ?int $itemId = null): array
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
    if ($itemId) {
        $where[] = "CAST(JSON_UNQUOTE(JSON_EXTRACT(e.metadata, '$.item_id')) AS UNSIGNED) = ?";
        $args[]  = $itemId;
    }
    $stmt = db()->prepare('SELECT e.*, u.first_name AS u_first, u.last_name AS u_last, u.role AS u_role,
                                  a.first_name AS a_first, a.last_name AS a_last, a.role AS a_role
                           FROM security_events e LEFT JOIN ' . USERS_SQL . ' u ON u.user_id = e.user_id LEFT JOIN ' . USERS_SQL . ' a ON a.user_id = e.actor_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY e.event_id DESC LIMIT ' . $limit);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/**
 * Student / faculty accounts an admin may want to look at: false-report violations, spam reports, a lockout,
 * deactivation, submission-limit or duplicate hits in the last 7 days, the same name as an account deactivated
 * for false reports (a possible re-registration; shown for review, never blocked automatically), or no link to a
 * school record (registered before school records existed and not matched by migration 015).
 */
function accounts_needing_attention(): array
{
    return db()->query("SELECT * FROM (
            SELECT u.*,
                (SELECT COUNT(*) FROM account_violations v WHERE v.user_id = u.user_id) AS violations,
                (SELECT COUNT(*) FROM lost_reports r WHERE r.reported_by = u.user_id AND r.status = 'spam') AS spam_reports,
                (SELECT COUNT(*) FROM security_events e WHERE e.user_id = u.user_id AND e.created_at > NOW() - INTERVAL 7 DAY
                    AND e.event_type IN ('report_rate_limited', 'report_duplicate_blocked')) AS blocked_submissions,
                EXISTS (SELECT 1 FROM " . USERS_SQL . " d WHERE d.deactivation_reason = 'false_reports' AND d.user_id <> u.user_id
                    AND LOWER(d.first_name) = LOWER(u.first_name) AND LOWER(d.last_name) = LOWER(u.last_name)) AS name_matches_deactivated,
                u.student_number IS NULL AND u.employee_number IS NULL AS no_school_record
            FROM " . USERS_SQL . " u WHERE u.role = 'user') t
        WHERE violations > 0 OR spam_reports > 0 OR blocked_submissions > 0 OR name_matches_deactivated OR no_school_record
           OR locked_at IS NOT NULL OR is_active = 0
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
            $actual = (string) ($row[$field === 'category' ? 'category_name' : $field] ?? '');   // a typed "Other" detail matches the Other filter
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
