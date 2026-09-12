<?php
/**
 * MOCK DATA — placeholder rows so the pages render before the database exists.
 * Shapes match the planned tables (users, lost_reports, found_items, claims).
 *
 * Pages never touch the arrays directly; they call the accessor functions at
 * the bottom of this file. The backend phase replaces those function bodies
 * with PDO queries and the pages keep working unchanged.
 */

/** The whole mock dataset, built once per request. */
function mock_db(): array
{
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    $users = [
        1 => ['user_id' => 1, 'first_name' => 'Ana',   'last_name' => 'Reyes',      'email' => 'admin@mapua.edu.ph',           'role' => 'admin', 'is_active' => 1, 'created_at' => '2026-08-01 09:00:00'],
        2 => ['user_id' => 2, 'first_name' => 'Marco', 'last_name' => 'Santos',     'email' => 'staff@mapua.edu.ph',           'role' => 'staff', 'is_active' => 1, 'created_at' => '2026-08-01 09:05:00'],
        3 => ['user_id' => 3, 'first_name' => 'Jose',  'last_name' => 'Dela Cruz',  'email' => 'student1@mymail.mapua.edu.ph', 'role' => 'user',  'is_active' => 1, 'created_at' => '2026-08-15 14:20:00'],
        4 => ['user_id' => 4, 'first_name' => 'Bea',   'last_name' => 'Lim',        'email' => 'student2@mymail.mapua.edu.ph', 'role' => 'user',  'is_active' => 1, 'created_at' => '2026-08-20 10:12:00'],
        5 => ['user_id' => 5, 'first_name' => 'Ramon', 'last_name' => 'Villanueva', 'email' => 'rvillanueva@mapua.edu.ph',     'role' => 'user',  'is_active' => 1, 'created_at' => '2026-09-01 08:45:00'],
        6 => ['user_id' => 6, 'first_name' => 'Carla', 'last_name' => 'Mendoza',    'email' => 'student9@mymail.mapua.edu.ph', 'role' => 'user',  'is_active' => 0, 'created_at' => '2026-09-03 16:30:00'],
    ];

    $foundItems = [
        1 => [
            'item_id' => 1, 'user_id' => 2,
            'item_name' => 'Black JBL earbuds case', 'category' => 'Electronics',
            'description' => 'Small black charging case for wireless earbuds. Found on a study table near the windows.',
            'private_details' => 'Case has a deep scratch on the lid. Left earbud is missing; only the right one is inside.',
            'date_found' => '2026-09-08', 'location_found' => 'Library', 'storage_location' => 'Cabinet A, Shelf 1',
            'photo_path' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-08 11:40:00', 'updated_at' => '2026-09-08 11:40:00',
        ],
        2 => [
            'item_id' => 2, 'user_id' => 2,
            'item_name' => 'Blue JanSport backpack', 'category' => 'Bags',
            'description' => 'Navy blue backpack, medium size, left on the bleachers after PE class.',
            'private_details' => 'Contains a green calculus notebook, a folding umbrella, and a Casio watch in the front pocket.',
            'date_found' => '2026-09-05', 'location_found' => 'Gymnasium', 'storage_location' => 'Cabinet B, Shelf 2',
            'photo_path' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-05 16:05:00', 'updated_at' => '2026-09-05 16:05:00',
        ],
        3 => [
            'item_id' => 3, 'user_id' => 2,
            'item_name' => 'Mapua student ID card', 'category' => 'IDs & Cards',
            'description' => 'Student ID card in a clear plastic holder with a red lanyard. Turned in by cafeteria staff.',
            'private_details' => 'Name on card: Jose Dela Cruz. Student number ends in 4471. Lanyard has a small keychain bear.',
            'date_found' => '2026-09-10', 'location_found' => 'Cafeteria', 'storage_location' => 'Drawer 1 (IDs)',
            'photo_path' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-10 13:15:00', 'updated_at' => '2026-09-11 09:00:00',
        ],
        4 => [
            'item_id' => 4, 'user_id' => 2,
            'item_name' => 'Casio fx-991 scientific calculator', 'category' => 'Electronics',
            'description' => 'Grey/black Casio scientific calculator with slide cover. Left in a lecture room.',
            'private_details' => 'Initials "K.S." written in marker on the back of the slide cover. Battery cover is cracked.',
            'date_found' => '2026-09-03', 'location_found' => 'North Building', 'storage_location' => 'Cabinet A, Shelf 3',
            'photo_path' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-03 10:20:00', 'updated_at' => '2026-09-03 10:20:00',
        ],
        5 => [
            'item_id' => 5, 'user_id' => 2,
            'item_name' => 'Silver keychain with 3 keys', 'category' => 'Keys',
            'description' => 'Keychain with three keys found near the motorcycle parking area.',
            'private_details' => 'Has a red bottle-opener tag and one key is a small padlock key.',
            'date_found' => '2026-09-11', 'location_found' => 'Parking Area', 'storage_location' => 'Drawer 2 (Keys)',
            'photo_path' => null, 'status' => 'stored', 'returned_at' => null,
            'created_at' => '2026-09-11 08:50:00', 'updated_at' => '2026-09-11 08:50:00',
        ],
        6 => [
            'item_id' => 6, 'user_id' => 2,
            'item_name' => 'Grey hoodie', 'category' => 'Clothing',
            'description' => 'Plain grey pullover hoodie, size medium.',
            'private_details' => 'Name tag inside collar: "B. Lim". Small bleach stain on the left sleeve.',
            'date_found' => '2026-08-28', 'location_found' => 'Student Lounge', 'storage_location' => 'Cabinet C, Shelf 1',
            'photo_path' => null, 'status' => 'returned', 'returned_at' => '2026-09-02 15:30:00',
            'created_at' => '2026-08-28 17:10:00', 'updated_at' => '2026-09-02 15:30:00',
        ],
        7 => [
            'item_id' => 7, 'user_id' => 2,
            'item_name' => 'Black folding umbrella', 'category' => 'Accessories',
            'description' => 'Compact black umbrella, unbranded.',
            'private_details' => 'Handle has a piece of yellow tape wrapped around it.',
            'date_found' => '2026-07-14', 'location_found' => 'Admin Building', 'storage_location' => 'Bin 4 (Misc)',
            'photo_path' => null, 'status' => 'disposed', 'returned_at' => null,
            'created_at' => '2026-07-14 09:00:00', 'updated_at' => '2026-08-30 12:00:00',
        ],
    ];

    $lostReports = [
        1 => [
            'report_id' => 1, 'user_id' => 3,
            'item_name' => 'JBL wireless earbuds (black)', 'category' => 'Electronics',
            'description' => 'JBL Tune 230 earbuds in a black case. The case lid has a scratch and I think the left earbud was already out of the case when I lost it.',
            'date_lost' => '2026-09-07', 'location_lost' => 'Library',
            'photo_path' => null, 'status' => 'open',
            'created_at' => '2026-09-07 18:25:00', 'updated_at' => '2026-09-07 18:25:00',
        ],
        2 => [
            'report_id' => 2, 'user_id' => 3,
            'item_name' => 'Student ID with red lanyard', 'category' => 'IDs & Cards',
            'description' => 'My Mapua ID in a clear holder. The lanyard has a tiny bear keychain.',
            'date_lost' => '2026-09-10', 'location_lost' => 'Cafeteria',
            'photo_path' => null, 'status' => 'matched',
            'created_at' => '2026-09-10 14:00:00', 'updated_at' => '2026-09-11 09:00:00',
        ],
        3 => [
            'report_id' => 3, 'user_id' => 4,
            'item_name' => 'Red Hydro Flask water bottle', 'category' => 'Other',
            'description' => '32 oz red bottle with a sticker of a cat on the side.',
            'date_lost' => '2026-09-09', 'location_lost' => 'Covered Court',
            'photo_path' => null, 'status' => 'open',
            'created_at' => '2026-09-09 12:10:00', 'updated_at' => '2026-09-09 12:10:00',
        ],
        4 => [
            'report_id' => 4, 'user_id' => 3,
            'item_name' => 'Physics textbook (Serway)', 'category' => 'Books & Notes',
            'description' => 'Hardbound physics textbook with my name on the first page.',
            'date_lost' => '2026-08-20', 'location_lost' => 'South Building',
            'photo_path' => null, 'status' => 'closed',
            'created_at' => '2026-08-20 09:30:00', 'updated_at' => '2026-08-25 11:00:00',
        ],
        5 => [
            'report_id' => 5, 'user_id' => 5,
            'item_name' => 'Grey hoodie', 'category' => 'Clothing',
            'description' => 'Grey pullover hoodie, medium. Has a name tag inside the collar.',
            'date_lost' => '2026-08-27', 'location_lost' => 'Student Lounge',
            'photo_path' => null, 'status' => 'closed',
            'created_at' => '2026-08-27 20:00:00', 'updated_at' => '2026-09-02 15:30:00',
        ],
    ];

    $claims = [
        1 => [
            'claim_id' => 1, 'item_id' => 1, 'user_id' => 3, 'lost_report_id' => 1,
            'proof_description' => 'These are JBL Tune 230 earbuds. The case has a scratch on the lid, and only the right earbud should be inside because I had the left one in my ear when I lost the case.',
            'status' => 'pending', 'reviewed_by' => null, 'review_note' => null, 'reviewed_at' => null,
            'created_at' => '2026-09-09 08:15:00',
        ],
        2 => [
            'claim_id' => 2, 'item_id' => 3, 'user_id' => 3, 'lost_report_id' => 2,
            'proof_description' => 'It is my student ID. My student number ends in 4471 and the lanyard has a small bear keychain.',
            'status' => 'approved', 'reviewed_by' => 2, 'review_note' => 'Details match. Please bring a valid ID to the Lost & Found office (Admin Bldg, Rm 104) to claim.', 'reviewed_at' => '2026-09-11 09:00:00',
            'created_at' => '2026-09-10 15:30:00',
        ],
        3 => [
            'claim_id' => 3, 'item_id' => 2, 'user_id' => 4, 'lost_report_id' => null,
            'proof_description' => 'Blue backpack with my laptop and charger inside.',
            'status' => 'rejected', 'reviewed_by' => 2, 'review_note' => 'Described contents do not match what was logged at intake.', 'reviewed_at' => '2026-09-06 10:45:00',
            'created_at' => '2026-09-06 09:20:00',
        ],
        4 => [
            'claim_id' => 4, 'item_id' => 2, 'user_id' => 5, 'lost_report_id' => null,
            'proof_description' => 'Navy JanSport. There should be a green calculus notebook, a folding umbrella and my Casio watch in the front pocket.',
            'status' => 'pending', 'reviewed_by' => null, 'review_note' => null, 'reviewed_at' => null,
            'created_at' => '2026-09-11 17:05:00',
        ],
    ];

    return $db = [
        'users'        => $users,
        'found_items'  => $foundItems,
        'lost_reports' => $lostReports,
        'claims'       => $claims,
    ];
}

/* ---- Collection accessors (later: SELECT * queries) ---- */

/** @return array<int, array> keyed by user_id */
function mock_users(): array
{
    return mock_db()['users'];
}

/** @return array<int, array> keyed by item_id */
function mock_found_items(): array
{
    return mock_db()['found_items'];
}

/** @return array<int, array> keyed by report_id */
function mock_lost_reports(): array
{
    return mock_db()['lost_reports'];
}

/** @return array<int, array> keyed by claim_id */
function mock_claims(): array
{
    return mock_db()['claims'];
}

/* ---- Single-row lookups (later: SELECT ... WHERE id = ?) ---- */

function mock_user(?int $id): ?array
{
    return $id !== null ? (mock_users()[$id] ?? null) : null;
}

function mock_found_item(int $id): ?array
{
    return mock_found_items()[$id] ?? null;
}

function mock_lost_report(int $id): ?array
{
    return mock_lost_reports()[$id] ?? null;
}

function mock_claim(int $id): ?array
{
    return mock_claims()[$id] ?? null;
}

/** Filter a list of rows by a keyword (matches item_name/description/location) and exact-match fields. */
function mock_filter(array $rows, string $keyword = '', array $exact = []): array
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
