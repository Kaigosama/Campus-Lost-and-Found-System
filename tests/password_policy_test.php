<?php
declare(strict_types=1);

/**
 * Self-check for the password policy in src/validation.php. No database needed:
 *   php tests/password_policy_test.php
 * Prints each failing case and exits 1 if any fail.
 */

require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/src/validation.php';

$jose = ['first_name' => 'Jose', 'last_name' => 'Dela Cruz', 'birth_date' => '2004-10-25'];
$ana  = ['first_name' => 'Ana', 'last_name' => 'Peña', 'birth_date' => null];

// [password, person, expected error (null = accepted)]
$cases = [
    // basic rules still apply first
    ['short1!A', null, null],
    ['alllower1!', null, 'Password must contain at least one uppercase letter.'],
    // names, in any case, with look-alikes, separators or run together
    ['Jose#2024x', $jose, PERSONAL_INFO_MESSAGE],
    ['jOsE!Secure9', $jose, PERSONAL_INFO_MESSAGE],
    ['J0s3!Secure9', $jose, PERSONAL_INFO_MESSAGE],
    ['Dela-Cruz!77', $jose, PERSONAL_INFO_MESSAGE],
    ['delacruzA!1', $jose, PERSONAL_INFO_MESSAGE],
    ['Cruz!Pass99', $jose, PERSONAL_INFO_MESSAGE],
    ['JoseDelaCruz1!', $jose, PERSONAL_INFO_MESSAGE],
    ['Pena!Rocks22', $ana, PERSONAL_INFO_MESSAGE],        // accent removed from the record's name
    ['Ana2024!x', $ana, PERSONAL_INFO_MESSAGE],           // 3-letter name as a whole word
    ['MyAna!x24', $ana, PERSONAL_INFO_MESSAGE],           // …or a capitalised word inside
    // birthdays: the professor's examples, separators, 6-digit forms (actual birthday only)
    ['Xq!20041025', $jose, PERSONAL_INFO_MESSAGE],
    ['Xq!25102004', $jose, PERSONAL_INFO_MESSAGE],
    ['Xq!10252004', $jose, PERSONAL_INFO_MESSAGE],
    ['Xq!25-10-2004', $jose, PERSONAL_INFO_MESSAGE],
    ['Xq!2004/10/25', $jose, PERSONAL_INFO_MESSAGE],
    ['Xq!041025a', $jose, PERSONAL_INFO_MESSAGE],
    ['Xq!251004a', $jose, PERSONAL_INFO_MESSAGE],
    // any full calendar date is refused, even without a known birthday
    ['Xq!19991231', null, PERSONAL_INFO_MESSAGE],
    ['Xq!31121999', $ana, PERSONAL_INFO_MESSAGE],
    // legitimate passwords: no false positives
    ['Banana!Split24', $ana, null],                       // "ana" inside another word
    ['Planning#2025', ['first_name' => 'Ann', 'last_name' => 'Lee', 'birth_date' => null], null],
    ['Tr0ub4dor&3x', $jose, null],
    ['Correct-Horse-42', $jose, null],
    ['Blue!Sky1025', $jose, null],                        // 4 digits: not a full date
    ['Xq!041026a', $jose, null],                          // a 6-digit number that isn't the birthday
    ['Xq!12345678', $jose, null],                         // 8 digits that aren't a valid date
    ['Xq!99999999', null, null],
];

$failed = 0;
foreach ($cases as [$password, $person, $expected]) {
    $actual = password_error($password, $person);
    if ($actual !== $expected) {
        $failed++;
        echo "FAIL  $password  expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
}
echo $failed ? "$failed of " . count($cases) . " cases failed\n" : 'All ' . count($cases) . " password policy cases passed\n";
exit($failed ? 1 : 0);
