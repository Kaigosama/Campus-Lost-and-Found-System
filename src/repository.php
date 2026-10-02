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
function all_lost_reports(): array { return table('lost_reports'); }
function all_claims(): array       { return table('claims'); }

function find_user(?int $id): ?array       { return $id ? (all_users()[$id] ?? null) : null; }
function find_found_item(int $id): ?array  { return all_found_items()[$id] ?? null; }
function find_lost_report(int $id): ?array { return all_lost_reports()[$id] ?? null; }
function find_claim(int $id): ?array       { return all_claims()[$id] ?? null; }

/** Found items the public may see: approved by staff and still in storage. */
function is_public_item(array $item): bool
{
    return $item['status'] === 'stored' && $item['moderation_status'] === 'approved';
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
            if ($value !== '' && $value !== null && (string) ($row[$field] ?? '') !== (string) $value) {
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
