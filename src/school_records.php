<?php
declare(strict_types=1);

/**
 * School reference records (student_records, employee_records) behind registration: a new Student / Faculty
 * account names its school identifier, and its name comes from the matching record. The record's birth date is
 * used only by the password check and never leaves the server.
 */

/** Registration types: label, reference table, its key column (also the users column), format, example, eligible statuses. */
const SCHOOL_ID_TYPES = [
    'student'  => ['Student', 'student_records', 'student_number', STUDENT_NUMBER_PATTERN, '2023100001', ELIGIBLE_STUDENT_STATUSES, 'academic_status', 'student number'],
    'employee' => ['Faculty / Employee', 'employee_records', 'employee_number', EMPLOYEE_NUMBER_PATTERN, 'EMP-10001', ELIGIBLE_EMPLOYEE_STATUSES, 'employment_status', 'employee number'],
];

/**
 * Checks a school identifier for registration: its format, that the school's records hold it, that the record may
 * register and that no account uses it yet. Returns [record|null, error|null]. The record has school_id, type,
 * first_name, last_name, birth_date, school_email, affiliation (program or department) and status.
 */
function lookup_school_record(string $type, string $schoolId): array
{
    if (!isset(SCHOOL_ID_TYPES[$type])) {
        return [null, 'Choose whether you are a student or faculty / employee.'];
    }
    [$label, $table, $column, $pattern, $example, $eligible, $statusColumn, $noun] = SCHOOL_ID_TYPES[$type];
    $schoolId = strtoupper(trim($schoolId));
    if ($schoolId === '') {
        return [null, "Enter your $noun."];
    }
    if (!preg_match($pattern, $schoolId)) {
        return [null, "That doesn't look like a $noun. Use the format on your school ID, e.g. $example."];
    }
    $stmt = db()->prepare("SELECT * FROM $table WHERE $column = ?");
    $stmt->execute([$schoolId]);
    $row = $stmt->fetch();
    if (!$row) {
        return [null, "We couldn't verify this $noun in the school's records. Check it, or contact " . RECORDS_OFFICE . '.'];
    }
    if (!in_array($row[$statusColumn], $eligible, true)) {
        return [null, "This $noun can't register (school record status: " . str_replace('_', ' ', $row[$statusColumn]) . '). Contact ' . RECORDS_OFFICE . ' if this is wrong.'];
    }
    $stmt = db()->prepare("SELECT email_verified FROM users WHERE $column = ?");
    $stmt->execute([$schoolId]);
    $verified = $stmt->fetchColumn();
    if ($verified !== false) {
        return [null, $verified
            ? "An account already uses this $noun. Log in, or reset your password if you forgot it."
            : "An account for this $noun is waiting for its email to be confirmed. Use the link we emailed, or request a new one."];
    }
    return [[
        'school_id'    => $schoolId,
        'type'         => $type,
        'type_label'   => $label,
        'first_name'   => $row['first_name'],
        'last_name'    => $row['last_name'],
        'birth_date'   => $row['birth_date'],
        'school_email' => $row['school_email'],
        'affiliation'  => $type === 'student' ? trim(($row['program'] ?? '') . ' · ' . ($row['college'] ?? ''), ' ·') : (string) $row['department'],
        'status'       => str_replace('_', ' ', $row[$statusColumn]),
    ], null];
}

/** Account type shown for a user: from the school identifier it registered with, else its role. */
function account_type_label(array $user): string
{
    return match (true) {
        !empty($user['student_number'])  => 'Student · ' . $user['student_number'],
        !empty($user['employee_number']) => 'Faculty / Employee · ' . $user['employee_number'],
        default                          => ROLES[$user['role']] ?? $user['role'],
    };
}

/** Name and birth date the password check compares against: the account's name, the birth date from its school record. */
function password_person(array $user): array
{
    $birthDate = null;
    foreach (SCHOOL_ID_TYPES as [, $table, $column]) {
        if (!empty($user[$column])) {
            $stmt = db()->prepare("SELECT birth_date FROM $table WHERE $column = ?");
            $stmt->execute([$user[$column]]);
            $birthDate = $stmt->fetchColumn() ?: null;
        }
    }
    return ['first_name' => $user['first_name'], 'last_name' => $user['last_name'], 'birth_date' => $birthDate];
}
