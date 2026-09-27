<?php
declare(strict_types=1);

/**
 * Form validation for items and passwords. Each returns field => message errors.
 */

/**
 * Validates the fields of a lost report ('lost') or found item ('found') from add_item / update_item.
 * Returns [errors (field => message), values (trimmed, keyed by column)].
 */
function validate_item_input(array $in, string $type): array
{
    $field = fn (string $key) => trim((string) ($in[$key] ?? ''));
    $rules = [
        'item_name'   => [1, 150],
        'description' => [$type === 'found' ? 15 : 20, 2000],
    ];
    if ($type === 'found') {
        $rules += ['location_found' => [1, 150], 'storage_location' => [1, 150], 'private_details' => [15, 2000]];
        $dateKey = 'date_found';
    } else {
        $rules += ['location_lost' => [1, 150]];
        $dateKey = 'date_lost';
    }

    $errors = [];
    $values = [];
    foreach ($rules as $key => [$min, $max]) {
        $values[$key] = $field($key);
        $len = mb_strlen($values[$key]);
        if ($len === 0)     $errors[$key] = 'This field is required.';
        elseif ($len < $min) $errors[$key] = "Must be at least $min characters.";
        elseif ($len > $max) $errors[$key] = "Must be $max characters or fewer.";
    }
    $values['category'] = $field('category');
    if (!in_array($values['category'], CATEGORIES, true)) {
        $errors['category'] = 'Choose a valid category.';
    }
    $values[$dateKey] = $field($dateKey);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values[$dateKey]) || !strtotime($values[$dateKey])) {
        $errors[$dateKey] = 'Enter a valid date.';
    } elseif ($values[$dateKey] > date('Y-m-d')) {
        $errors[$dateKey] = 'Date cannot be in the future.';
    }
    return [$errors, $values];
}

/** Rules for the new_password / new_password_confirm fields. Returns field errors. */
function new_password_errors(array $in, ?string $current = null): array
{
    $new = (string) ($in['new_password'] ?? '');
    if (strlen($new) < PASSWORD_MIN) {
        return ['new_password' => 'Password must be at least ' . PASSWORD_MIN . ' characters.'];
    }
    if ($current !== null && $new === $current) {
        return ['new_password' => 'Choose a password different from your current one.'];
    }
    if ($new !== (string) ($in['new_password_confirm'] ?? '')) {
        return ['new_password_confirm' => 'Passwords do not match.'];
    }
    return [];
}
