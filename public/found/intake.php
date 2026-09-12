<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['staff', 'admin']);

$pageTitle   = 'Log a found item';
$formAction  = url('/found/intake.php');
$submitLabel = 'Save to storage';
$item        = null;

include APP_PATH . '/views/layout/header.php';
?>

<div class="breadcrumb"><a href="<?= e(url('/found/manage.php')) ?>">Manage found items</a><span>Log found item</span></div>

<div class="page-header">
    <div>
        <h1>Log a found item</h1>
        <p>Record an item turned in to the office and where it is stored.</p>
    </div>
</div>

<div class="grid grid-sidebar">
    <div class="card">
        <?php include APP_PATH . '/views/partials/found-item-form.php'; ?>
    </div>

    <aside>
        <div class="card card-muted">
            <h3>Intake checklist</h3>
            <ol class="text-sm" style="padding-left:1.2rem;margin:0">
                <li>Tag the item with the ID shown after saving.</li>
                <li>Photograph it without exposing private details.</li>
                <li>Note distinguishing marks in the staff-only box.</li>
                <li>Place it in storage and record the exact location.</li>
                <li>Check <a href="<?= e(url('/lost/all.php')) ?>">open lost reports</a> for a match.</li>
            </ol>
        </div>
    </aside>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
