<?php
declare(strict_types=1);

/**
 * Form categories (categories table), shared by lost reports and found items. Admins add, deactivate, reactivate
 * and delete them on index.php?tab=categories through api/categories.php. Only active categories are offered on
 * new records; old records keep showing theirs. A category used by any record can't be deleted.
 */

function all_categories(): array { return table('categories'); }
function find_category(int $id): ?array { return all_categories()[$id] ?? null; }

/** Categories offered in a form, by name. $keepId keeps a record's current category even when it is inactive. */
function selectable_categories(?int $keepId = null): array
{
    $rows = array_filter(all_categories(), fn ($c) => $c['is_active'] || $c['category_id'] === $keepId);
    usort($rows, fn ($a, $b) => [$a['name'] === OTHER_CATEGORY, $a['name']] <=> [$b['name'] === OTHER_CATEGORY, $b['name']]);   // "Other" last
    return $rows;
}

/** Every category name (inactive ones too, so older records can still be filtered), "Other" last. */
function category_names(): array
{
    $names = array_column(all_categories(), 'name');
    usort($names, fn ($a, $b) => [$a === OTHER_CATEGORY, $a] <=> [$b === OTHER_CATEGORY, $b]);
    return $names;
}

function is_other_category(array $category): bool
{
    return mb_strtolower($category['name']) === mb_strtolower(OTHER_CATEGORY);
}

/** [lost reports, found items] using each category (removed reports count too: they still reference it). */
function category_usage(): array
{
    $usage = [];
    foreach (['lost' => 'lost_reports', 'found' => 'found_items'] as $key => $table) {
        foreach (db()->query("SELECT category_id, COUNT(*) FROM $table GROUP BY category_id")->fetchAll(PDO::FETCH_KEY_PAIR) as $id => $n) {
            $usage[$id][$key] = (int) $n;
        }
    }
    return $usage;
}

/** Trimmed name with single spaces, or a validation message. Returns [name, error|null]. */
function clean_category_name(string $name): array
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    $len  = mb_strlen($name);
    return [$name, match (true) {
        $len === 0  => 'Enter a category name.',
        $len < 2    => 'Category name must be at least 2 characters.',
        $len > 50   => 'Category name must be 50 characters or fewer.',
        !preg_match("/^[\\p{L}\\p{N}][\\p{L}\\p{N} &\\/'().,-]*$/u", $name) => 'Use letters, numbers, spaces and & / \' ( ) . , - only.',
        default     => null,
    }];
}
