<?php
defined('APP_ROOT') || exit;
/**
 * Opens the document and renders the navbar. Pages may set before including:
 *   $pageTitle  string       — used in <title>
 *   $flash      array|null   — ['type' => 'success|error|info|warning', 'message' => '...']
 *   $type       string       — 'found'|'lost' on browse.php / report.php, so the navbar highlights the right link
 */
$title = !empty($pageTitle) ? $pageTitle . ' · ' . APP_NAME : APP_NAME . ' · ' . APP_FULL_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-base="<?= e(BASE_URL) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<?php include APP_ROOT . '/includes/navbar.php'; ?>

<main id="main" class="container page">
<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>
