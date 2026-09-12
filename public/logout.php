<?php
require_once __DIR__ . '/../app/bootstrap.php';

// PREVIEW: clears the mock role. The backend phase replaces this with
// session_destroy() + cookie cleanup.
setcookie(PREVIEW_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);

header('Location: ' . url('/index.php?as=guest'));
exit;
