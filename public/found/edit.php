<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['staff', 'admin']);

$item = mock_found_item((int) ($_GET['id'] ?? 0));
if (!$item) {
    http_response_code(404);
    $pageTitle = 'Item not found';
    include APP_PATH . '/views/layout/header.php';
    $emptyTitle = 'Item not found';
    $emptyActionUrl = url('/found/manage.php');
    $emptyActionLabel = 'Back to manage items';
    include APP_PATH . '/views/partials/empty-state.php';
    include APP_PATH . '/views/layout/footer.php';
    exit;
}

$pageTitle   = 'Edit: ' . $item['item_name'];
$formAction  = url('/found/edit.php?id=' . $item['item_id']);
$submitLabel = 'Save changes';

include APP_PATH . '/views/layout/header.php';
?>

<div class="breadcrumb">
    <a href="<?= e(url('/found/manage.php')) ?>">Manage found items</a>
    <span><a href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>"><?= e($item['item_name']) ?></a></span>
    <span>Edit</span>
</div>

<div class="page-header">
    <div>
        <h1>Edit item #<?= $item['item_id'] ?></h1>
        <p>Logged <?= e(format_datetime($item['created_at'])) ?> &middot; last updated <?= e(format_datetime($item['updated_at'])) ?></p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/found/view.php?id=' . $item['item_id'])) ?>">View item</a>
</div>

<div class="card">
    <?php include APP_PATH . '/views/partials/found-item-form.php'; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
