<?php
declare(strict_types=1);

/**
 * Form validation for items, emails and passwords. Each returns field => message errors.
 */

/**
 * Validates the fields of a lost report ('lost') or found item ('found') from add_item / update_item.
 * A student's found-item post has no storage location yet ($needsStorage false); staff add it when approving.
 * $currentCategoryId is the edited record's category: it stays allowed after an admin deactivates it.
 * Returns [errors (field => message), values (trimmed, keyed by column)].
 */
function validate_item_input(array $in, string $type, bool $needsStorage = true, ?int $currentCategoryId = null): array
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
    // Only active categories for new records; the "Other" category also needs the user's description of the item type.
    $values['category_id']     = (int) $field('category_id');
    $values['category_detail'] = null;
    $category = find_category($values['category_id']);
    if (!$category || (!$category['is_active'] && $values['category_id'] !== $currentCategoryId)) {
        $errors['category_id'] = 'Choose a category from the list.';
    } elseif (is_other_category($category)) {
        $values['category_detail'] = $field('category_detail');
        $len = mb_strlen($values['category_detail']);
        if ($len === 0) $errors['category_detail'] = 'Describe what "Other" is.';
        elseif ($len > 50) $errors['category_detail'] = 'Must be 50 characters or fewer.';
    }
    $locationKey = $type === 'found' ? 'location_found' : 'location_lost';
    // "Other" is stored as the text typed in the <key>_other box (see other_input()).
    $choices = [$locationKey => [CAMPUS_LOCATIONS, 150, 'Choose a location from the list.']];
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

/**
 * The error for a free-text field, or null. Required when $min > 0; $min to $max characters; at least $minWords
 * words; and, for required text, a real sentence rather than "aaaa", "asdf asdf" or "123" (5 different letters or
 * digits, at least one letter). Not a meaning check: the reason code says why, this text only adds the detail.
 * public/js/app.js runs the same checks on fields with data-min-words.
 */
function text_error(string $value, int $min, int $max, int $minWords = 0): ?string
{
    $len = mb_strlen($value);
    if ($len === 0) {
        return $min ? 'This field is required.' : null;
    }
    if ($len < $min) {
        return "Must be at least $min characters.";
    }
    if ($len > $max) {
        return "Must be $max characters or fewer.";
    }
    if ($minWords && count(preg_split('/\s+/u', $value)) < $minWords) {
        return "Please give a little more detail (at least $minWords words).";
    }
    preg_match_all('/[\p{L}\p{N}]/u', mb_strtolower($value), $m);
    if ($min && (count(array_unique($m[0])) < 5 || !preg_match('/\p{L}/u', $value))) {
        return 'Write a real explanation, not repeated or random characters.';
    }
    return null;
}

/** A Yes / No radio as 1 or 0 for the database; null when neither was chosen. */
function yes_no(mixed $value): ?int
{
    return match ($value) { 'yes' => 1, 'no' => 0, default => null };
}

/* ---------------------------------------------------------------- Review decisions */

/**
 * A staff decision on a claim (api/claims.php action=review). decision 'approve' needs pickup_location (one of
 * PICKUP_LOCATIONS), pickup_instructions and required_ids (keys of ID_TYPES), with optional details; 'reject' needs
 * reason_code (CLAIM_REJECTION_REASONS), details and resubmission_allowed, plus required_followup when the claimant
 * may add information. Returns [errors, values]: values are claim_reviews columns, null when not used.
 */
function claim_review_input(array $in): array
{
    $text   = fn (string $key) => trim((string) ($in[$key] ?? ''));
    $errors = [];
    $v = ['details' => $text('details'), 'reason_code' => null, 'resubmission_allowed' => null, 'required_followup' => null,
          'pickup_location' => null, 'pickup_instructions' => null, 'required_ids' => null];
    $decision = (string) ($in['decision'] ?? '');
    if ($decision === 'approve') {
        $v['pickup_location'] = $text('pickup_location');
        if (!in_array($v['pickup_location'], PICKUP_LOCATIONS, true)) {
            $errors['pickup_location'] = 'Choose where the claimant collects the item.';
        }
        $v['pickup_instructions'] = $text('pickup_instructions');
        if ($message = text_error($v['pickup_instructions'], 20, 1000, 4)) {
            $errors['pickup_instructions'] = $message;
        }
        $ids = array_values(array_unique(array_map(fn ($id) => is_scalar($id) ? (string) $id : '', (array) ($in['required_ids'] ?? []))));
        if (!$ids || array_diff($ids, array_keys(ID_TYPES))) {
            $errors['required_ids'] = 'Tick the identification the claimant must bring.';
        }
        $v['required_ids'] = implode(',', $ids);
        if ($message = text_error($v['details'], 0, 1000)) {
            $errors['details'] = $message;
        }
    } elseif ($decision === 'reject') {
        $v['reason_code'] = (string) ($in['reason_code'] ?? '');
        if (!isset(CLAIM_REJECTION_REASONS[$v['reason_code']])) {
            $errors['reason_code'] = 'Choose a rejection reason.';
        }
        if ($message = text_error($v['details'], 15, 1000, 3)) {
            $errors['details'] = $message;
        }
        $v['resubmission_allowed'] = yes_no($in['resubmission_allowed'] ?? null);
        if ($v['resubmission_allowed'] === null) {
            $errors['resubmission_allowed'] = 'Choose whether the claimant can provide more information.';
        } elseif ($v['resubmission_allowed'] === 1) {
            $v['required_followup'] = $text('required_followup');
            if ($message = text_error($v['required_followup'], 10, 1000, 2)) {
                $errors['required_followup'] = $message;
            }
        }
    } else {
        $errors['decision'] = 'Choose approve or reject.';
    }
    $v['details'] = $v['details'] === '' ? null : $v['details'];
    return [$errors, $v];
}

/**
 * A staff review of a student's found-item post (api/update_status.php). moderation 'approved' needs a storage
 * location, received_at_office = yes (a post is approved only once the item is at the office) and item_condition,
 * with an optional staff-only internal_note; 'rejected' needs reason_code (POST_REJECTION_REASONS), details and
 * resubmission_allowed, plus required_corrections when the poster may post again. Returns [errors, values]: values
 * are found_items columns.
 */
function post_review_input(array $in, array $item): array
{
    $text   = fn (string $key) => trim((string) ($in[$key] ?? ''));
    $errors = [];
    $status = (string) ($in['moderation'] ?? '');
    $v = ['moderation_status' => $status, 'storage_location' => $item['storage_location'], 'moderation_note' => null,
          'moderation_reason_code' => null, 'resubmission_allowed' => null, 'required_corrections' => null,
          'item_condition' => null, 'moderation_internal_note' => null];
    if ($status === 'approved') {
        $v['storage_location'] = $text('storage_location');
        if (!in_array($v['storage_location'], STORAGE_LOCATIONS, true)) {
            $errors['storage_location'] = 'Record where the item is stored.';
        }
        $received = yes_no($in['received_at_office'] ?? null);
        if ($received !== 1) {
            $errors['received_at_office'] = $received === 0 ? 'Approve a post only once the item is at the office.' : 'Confirm whether the office has the item.';
        }
        $v['item_condition'] = (string) ($in['item_condition'] ?? '');
        if (!isset(ITEM_CONDITIONS[$v['item_condition']])) {
            $errors['item_condition'] = 'Choose the item\'s condition.';
        }
        $v['moderation_internal_note'] = $text('internal_note') ?: null;
        if ($message = text_error((string) $v['moderation_internal_note'], 0, 1000)) {
            $errors['internal_note'] = $message;
        }
    } elseif ($status === 'rejected') {
        $v['moderation_reason_code'] = (string) ($in['reason_code'] ?? '');
        if (!isset(POST_REJECTION_REASONS[$v['moderation_reason_code']])) {
            $errors['reason_code'] = 'Choose a rejection reason.';
        }
        $v['moderation_note'] = $text('details');
        if ($message = text_error($v['moderation_note'], 15, 1000, 3)) {
            $errors['details'] = $message;
        }
        $v['resubmission_allowed'] = yes_no($in['resubmission_allowed'] ?? null);
        if ($v['resubmission_allowed'] === null) {
            $errors['resubmission_allowed'] = 'Choose whether the poster can post the item again.';
        } elseif ($v['resubmission_allowed'] === 1) {
            $v['required_corrections'] = $text('required_corrections');
            if ($message = text_error($v['required_corrections'], 10, 1000, 2)) {
                $errors['required_corrections'] = $message;
            }
        }
    } else {
        $errors['moderation'] = 'Choose approve or reject.';
    }
    return [$errors, $v];
}

/**
 * A staff outcome for a lost report (api/moderate_report.php): action 'rejected' (REPORT_REJECTION_REASONS, details,
 * resubmission_allowed), 'false_report' (FALSE_REPORT_REASONS, at least 30 characters of evidence in details, and
 * confirm_false; confirm_deactivation too is checked by moderate_report(), which knows the violation count) or 'spam'
 * (SPAM_TYPES, optional details). Returns [errors, values]: values are lost_reports columns.
 */
function report_moderation_input(array $in): array
{
    $action  = (string) ($in['action'] ?? '');
    $details = trim((string) ($in['details'] ?? ''));
    $lists   = ['rejected' => REPORT_REJECTION_REASONS, 'false_report' => FALSE_REPORT_REASONS, 'spam' => SPAM_TYPES];
    if (!isset($lists[$action])) {
        return [['action' => 'Choose an action.'], []];
    }
    $errors = [];
    $v = ['status' => $action, 'moderation_reason_code' => (string) ($in['reason_code'] ?? ''), 'moderation_reason' => $details ?: null, 'resubmission_allowed' => null];
    if (!isset($lists[$action][$v['moderation_reason_code']])) {
        $errors['reason_code'] = $action === 'spam' ? 'Choose the type of spam.' : 'Choose a reason.';
    }
    $rule = ['rejected' => [15, 3], 'false_report' => [30, 5], 'spam' => [0, 0]][$action];
    if ($message = text_error($details, $rule[0], 1000, $rule[1])) {
        $errors['details'] = $message;
    }
    if ($action === 'rejected') {
        $v['resubmission_allowed'] = yes_no($in['resubmission_allowed'] ?? null);
        if ($v['resubmission_allowed'] === null) {
            $errors['resubmission_allowed'] = 'Choose whether the reporter can file the report again.';
        }
    }
    if ($action === 'false_report' && empty($in['confirm_false'])) {
        $errors['confirm_false'] = 'Confirm that the report was reviewed and is intentionally false.';
    }
    return [$errors, $v];
}

/** The oldest date_found / date_lost accepted: one year before today. */
function earliest_item_date(): string
{
    return date('Y-m-d', strtotime('-1 year'));
}

/**
 * Mistyped email domains, as meant domain => typos. A typo'd domain often still takes mail (gmail.com.com.com does), so
 * the confirmation link would go to a stranger. footer.php sends these lists to public/js/app.js for the live check.
 */
const EMAIL_DOMAIN_TYPOS = [
    'gmail.com'   => ['gmial.com', 'gmai.com', 'gmal.com', 'gamil.com', 'gmali.com', 'gnail.com', 'gmaill.com', 'gmaul.com'],
    'yahoo.com'   => ['yaho.com', 'yahooo.com', 'yhoo.com', 'yahoo.co', 'yahoo.cm'],
    'hotmail.com' => ['hotmial.com', 'hotmal.com', 'hotmai.com', 'hotmil.com', 'hotnail.com', 'hotmail.co', 'hotmail.cm'],
    'outlook.com' => ['outlok.com', 'outllok.com', 'outloo.com', 'oulook.com', 'otlook.com', 'outlook.co', 'outlook.cm'],
    'icloud.com'  => ['iclod.com', 'icoud.com'],
];
/**
 * Providers with a single domain, as start => domain: anything else that starts the same is a typo
 * (gmail.co, gmail.com.ph, gmail.sdhskdjfjk all mean gmail.com). Yahoo and Hotmail have real country domains.
 */
const EMAIL_ONLY_DOMAINS = [
    'gmail.'        => 'gmail.com',
    'icloud.'       => 'icloud.com',
    'mapua.'        => 'mapua.edu.ph',
    'mymail.mapua.' => 'mymail.mapua.edu.ph',
];
/** Misspellings of the .com ending; none is a real top-level domain. */
const EMAIL_TLD_TYPOS = ['con', 'cmo', 'ocm', 'comm', 'coom', 'conm', 'cpm', 'vom', 'xom', 'cim'];

/** "Did you mean …?" when $email's domain looks mistyped (gmail.com.com, gmial.com, school.con), else null. */
function email_typo_error(string $email): ?string
{
    $at     = strrpos($email, '@');
    $domain = substr($email, $at + 1);
    $labels = [];
    foreach (explode('.', $domain) as $label) {   // a repeated part: gmail.com.com.com → gmail.com
        if ($label !== end($labels)) {
            $labels[] = $label;
        }
    }
    if (in_array(end($labels), EMAIL_TLD_TYPOS, true)) {
        $labels[array_key_last($labels)] = 'com';
    }
    $fixed = implode('.', $labels);
    foreach (EMAIL_DOMAIN_TYPOS as $meant => $typos) {
        if (in_array($fixed, $typos, true)) {
            $fixed = $meant;
        }
    }
    foreach (EMAIL_ONLY_DOMAINS as $start => $only) {
        if (str_starts_with($fixed, $start)) {
            $fixed = $only;
        }
    }
    return $fixed === $domain ? null : 'Check the email address. Did you mean ' . substr($email, 0, $at + 1) . $fixed . '?';
}

/**
 * Whether $email's domain exists in DNS and can take mail: an MX record, or an address (mail falls back to it).
 * Catches made-up domains (gmail.sdhskdjfjk) that no typo list can. Server only; the browser can't look up DNS.
 */
function email_domain_exists(string $email): bool
{
    $domain = substr($email, strrpos($email, '@') + 1) . '.';   // trailing dot: no local search suffix is tried
    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
}

/** Password policy; public/js/app.js shows the same rules as a live checklist. */
const PASSWORD_RULES = [
    '/[a-z]/'        => 'Password must contain at least one lowercase letter.',
    '/[A-Z]/'        => 'Password must contain at least one uppercase letter.',
    '/[0-9]/'        => 'Password must contain at least one number.',
    '/[^A-Za-z0-9]/' => 'Password must contain at least one special character.',
];
const PERSONAL_INFO_MESSAGE = 'Your password must not contain your name or birthday.';

/**
 * The first rule $password breaks, or null when it meets the policy. $person (first_name, last_name, birth_date or
 * null; see password_person()) adds the personal-information rule. The password is never logged or stored here.
 */
function password_error(string $password, ?array $person = null): ?string
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
    return password_has_personal_info($password, $person) ? PERSONAL_INFO_MESSAGE : null;
}

/*
 * Personal information in a password. app.js runs the same name and full-date checks live (passwordPersonal());
 * the actual birthday is checked only here, because sending it to the browser would reveal it to anyone who
 * looks up a school number.
 *   Names: each name word of 3+ letters, and the first, last and full name run together, after lower-casing,
 *     removing accents and reading look-alikes as letters (J0s3 → jose). Words of 4+ letters match anywhere in the
 *     password's letters; 3-letter names only as a whole word, so "Ana" rejects "Ana2024!" and "MyAna!24" but
 *     not "Banana!24".
 *   Dates: any full calendar date (YYYYMMDD, DDMMYYYY, MMDDYYYY, years 1900 to now, separators ignored), which
 *     catches every birthday in those forms without knowing it; plus the actual birth date in 6-digit forms.
 */

function password_has_personal_info(string $password, ?array $person): bool
{
    return password_has_full_date($password)
        || ($person !== null && (password_has_name($password, $person)
            || (!empty($person['birth_date']) && password_has_birthday($password, (string) $person['birth_date']))));
}

/** Lower case without accents: "Peña" → "pena". */
function fold_text(string $text): string
{
    return strtr(mb_strtolower($text), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
    ]);
}

/** The name words and run-together names the password must not contain. */
function name_tokens(array $person): array
{
    $words  = fn (string $s) => preg_split('/[^a-z]+/', fold_text($s), -1, PREG_SPLIT_NO_EMPTY);
    $first  = $words((string) ($person['first_name'] ?? ''));
    $last   = $words((string) ($person['last_name'] ?? ''));
    $tokens = [...$first, ...$last, implode('', $first), implode('', $last), implode('', [...$first, ...$last])];
    return array_values(array_unique(array_filter($tokens, fn ($t) => strlen($t) >= 3)));
}

function password_has_name(string $password, array $person): bool
{
    // Words of the password: runs of letters split where a capital follows a small letter ("MyAna" → my, ana).
    $mapped = strtr($password, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);
    preg_match_all('/\p{L}+/u', $mapped, $m);
    $words = [];
    foreach ($m[0] as $run) {
        foreach (preg_split('/(?<=\p{Ll})(?=\p{Lu})/u', $run) as $word) {
            $words[] = fold_text($word);
        }
    }
    $letters = implode('', $words);
    foreach (name_tokens($person) as $token) {
        if (strlen($token) >= 4 ? str_contains($letters, $token) : in_array($token, $words, true)) {
            return true;
        }
    }
    return false;
}

/** Runs of digits, with - / . _ and spaces between digits dropped: "25-10-2004" → "25102004". */
function password_digit_runs(string $password): array
{
    preg_match_all('/\d+/', preg_replace('/(?<=\d)[\s\-\/._]+(?=\d)/', '', $password), $m);
    return $m[0];
}

function password_has_full_date(string $password): bool
{
    $thisYear = (int) date('Y');
    foreach (password_digit_runs($password) as $run) {
        for ($i = 0; $i + 8 <= strlen($run); $i++) {
            $s = substr($run, $i, 8);
            foreach ([[0, 4, 6], [4, 2, 0], [4, 0, 2]] as [$y, $m, $d]) {   // YYYYMMDD, DDMMYYYY, MMDDYYYY
                $year = (int) substr($s, $y, 4);
                if ($year >= 1900 && $year <= $thisYear && checkdate((int) substr($s, $m, 2), (int) substr($s, $d, 2), $year)) {
                    return true;
                }
            }
        }
    }
    return false;
}

/** The actual birth date (Y-m-d) in 8- and 6-digit forms: 20041025, 25102004, 10252004, 041025, 251004, 102504. */
function password_has_birthday(string $password, string $birthDate): bool
{
    [$y, $m, $d] = explode('-', $birthDate);
    $yy    = substr($y, 2);
    $forms = ["$y$m$d", "$d$m$y", "$m$d$y", "$yy$m$d", "$d$m$yy", "$m$d$yy"];
    foreach (password_digit_runs($password) as $run) {
        foreach ($forms as $form) {
            if (str_contains($run, $form)) {
                return true;
            }
        }
    }
    return false;
}

/** Rules for the new_password / new_password_confirm fields. Returns field errors. */
function new_password_errors(array $in, ?string $current = null, ?array $person = null): array
{
    $new = (string) ($in['new_password'] ?? '');
    if ($message = password_error($new, $person)) {
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
