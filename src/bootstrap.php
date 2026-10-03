<?php
declare(strict_types=1);

/**
 * Application core — required first by every page, API endpoint and database/seed.php.
 * Loads the settings, then the helpers, then auth (which resumes the session).
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/views.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/password_reset.php';
require_once __DIR__ . '/email_verification.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/auth.php';

reject_cross_site_post();
redirect_ended_session();
