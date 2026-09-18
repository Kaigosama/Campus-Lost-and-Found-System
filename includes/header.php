<?php
defined('APP_ROOT') || exit;
/** Pages may set $pageTitle, $flash (['type' => …, 'message' => …]) and $type ('found'|'lost', for the navbar) first. */
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
