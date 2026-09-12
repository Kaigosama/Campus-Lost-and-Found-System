<?php
declare(strict_types=1);

/**
 * Every page starts with: require_once __DIR__ . '/../app/bootstrap.php';
 *
 * Front-end phase: loads constants, helpers, mock data and the preview auth
 * stub. Later phases add config.php, db.php, csrf.php and the real auth here —
 * nothing in public/ needs to change.
 */

define('APP_PATH', __DIR__);

/** '' when serving with `php -S localhost:8000 -t public`; '/clafs' for an Apache alias. */
const BASE_URL = '';

require_once APP_PATH . '/constants.php';
require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/mock-data.php';
require_once APP_PATH . '/auth.php';

preview_role(); // resolve the mock role (and set its cookie) before any output
