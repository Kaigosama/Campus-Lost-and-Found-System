<?php
declare(strict_types=1);

/**
 * Form validation for items and passwords. Each returns field => message errors.
 */

/**
 * Validates the fields of a lost report ('lost') or found item ('found') from add_item / update_item.
 * A student's found-item post has no storage location yet ($needsStorage false); staff add it when approving.
 * Returns [errors (field => message), values (trimmed, keyed by column)].
 */
function validate_item_input(array $in, string $type, bool $needsStorage = true): array
{
    $field = fn (string $key) => trim((string) ($in[$key] ?? ''));
    $rules = [
        'item_name'   => [1, 150],
        'description' => [$type === 'found' ? 15 : 20, 2000],
    ];
    if ($type === 'found') {
        $rules += ['location_found' => [1, 150], 'storage_location' => [$needsStorage ? 1 : 0, 150], 'private_details' => [15, 2000]];
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
        if ($len === 0 && $min) $errors[$key] = 'This field is required.';
        elseif ($len < $min) $errors[$key] = "Must be at least $min characters.";
        elseif ($len > $max) $errors[$key] = "Must be $max characters or fewer.";
    }
    $values['category'] = $field('category');
    $locationKey = $type === 'found' ? 'location_found' : 'location_lost';
    // "Other" is stored as the text typed in the <key>_other box (see other_input()).
    $choices = ['category' => [CATEGORIES, 50, 'Choose a valid category.'], $locationKey => [CAMPUS_LOCATIONS, 150, 'Choose a location from the list.']];
    foreach ($choices as $key => [$list, $max, $message]) {
        if (isset($errors[$key])) continue;
        if ($values[$key] === 'Other') {
            $values[$key] = $field($key . '_other');
            $len = mb_strlen($values[$key]);
            if ($len === 0) $errors[$key . '_other'] = 'Describe what "Other" is.';
            elseif ($len > $max) $errors[$key . '_other'] = "Must be $max characters or fewer.";
        } elseif (!in_array($values[$key], $list, true)) {
            $errors[$key] = $message;
        }
    }
    if ($type === 'found' && $needsStorage && !isset($errors['storage_location']) && !in_array($values['storage_location'], STORAGE_LOCATIONS, true)) {
        $errors['storage_location'] = 'Choose a storage location from the list.';
    }
    $values[$dateKey] = $field($dateKey);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values[$dateKey]) || !strtotime($values[$dateKey])) {
        $errors[$dateKey] = 'Enter a valid date.';
    } elseif ($values[$dateKey] > date('Y-m-d')) {
        $errors[$dateKey] = 'Date cannot be in the future.';
    } elseif ($values[$dateKey] < earliest_item_date()) {
        $errors[$dateKey] = 'Date cannot be more than a year ago.';
    }
    return [$errors, $values];
}

/** The oldest date_found / date_lost accepted: one year before today. */
function earliest_item_date(): string
{
    return date('Y-m-d', strtotime('-1 year'));
}

/** A person's name: required, at least $min characters, letters (any script) plus spaces, hyphens, apostrophes, periods. */
function name_error(string $name, string $label, int $min): ?string
{
    $len = mb_strlen($name);
    return match (true) {
        $len === 0                       => "$label is required.",
        $len < $min                      => "$label must contain at least $min characters.",
        $len > 100                       => "$label must be 100 characters or fewer.",
        !preg_match(NAME_PATTERN, $name) => "$label: use letters only (spaces, hyphens, apostrophes and periods are allowed).",
        default                          => null,
    };
}

/** Password policy; public/js/app.js shows the same rules as a live checklist. */
const PASSWORD_RULES = [
    '/[a-z]/'        => 'Password must contain at least one lowercase letter.',
    '/[A-Z]/'        => 'Password must contain at least one uppercase letter.',
    '/[0-9]/'        => 'Password must contain at least one number.',
    '/[^A-Za-z0-9]/' => 'Password must contain at least one special character.',
];

/** The first rule $password breaks, or null when it meets the policy. */
function password_error(string $password): ?string
{
    if ($password === '') {
        return 'Password is required.';
    }
    if (strlen($password) < PASSWORD_MIN) {
        return 'Password must be at least ' . PASSWORD_MIN . ' characters.';
    }
    if (strlen($password) > 72) {   // bcrypt ignores everything after 72 bytes
        return 'Password must be 72 characters or fewer.';
    }
    foreach (PASSWORD_RULES as $pattern => $message) {
        if (!preg_match($pattern, $password)) {
            return $message;
        }
    }
    return null;
}

/** Rules for the new_password / new_password_confirm fields. Returns field errors. */
function new_password_errors(array $in, ?string $current = null): array
{
    $new = (string) ($in['new_password'] ?? '');
    if ($message = password_error($new)) {
        return ['new_password' => $message];
    }
    if ($current !== null && $new === $current) {
        return ['new_password' => 'Choose a password different from your current one.'];
    }
    if ($new !== (string) ($in['new_password_confirm'] ?? '')) {
        return ['new_password_confirm' => 'Passwords do not match.'];
    }
    return [];
}
