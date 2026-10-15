<?php
require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * POST /api/visitor.php   { choice: accepted|rejected }   sent by the cookie banner (app.js)
 *   accepted → a pseudonymous visitor record and its clafs_vid cookie (only while not logged in)
 *   rejected → this browser's visitor record and cookie are deleted
 * See src/visitors.php. Response: { ok, choice }
 */
require_method('POST');
$choice = (string) (json_input()['choice'] ?? '');
if (!in_array($choice, ['accepted', 'rejected'], true)) {
    json_error(422, 'Choose accepted or rejected.');
}
if ($choice === 'rejected' || !is_logged_in()) {   // signed-in people are users, not anonymous visitors
    record_visitor_consent($choice);
}
json_response(['ok' => true, 'choice' => $choice]);
