<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: account. */ ?>
<div class="page-header">
    <div><h1>Account</h1><p>Your profile and password.</p></div>
</div>

    <?php
    $err = fn (string $key) => isset($errors[$key]) ? '<span class="form-error" id="' . $key . '-error">' . e($errors[$key]) . '</span>' : '';
    $inv = fn (string $key) => isset($errors[$key]) ? ' class="is-invalid" aria-invalid="true"' : '';
    ?>
    <?php if (isset($_GET['changed'])): ?>
        <div class="alert alert-success" role="status">Password changed. Any other device signed in to this account has been logged out.</div>
    <?php endif; ?>

    <div class="grid grid-2">
        <div class="card">
            <h2>Profile</h2>
            <div class="flex items-center gap-2 mb-3">
                <span class="avatar" aria-hidden="true"><?= e(initials($user)) ?></span>
                <div>
                    <div class="fw-600"><?= e(full_name($user)) ?></div>
                    <div class="text-sm text-muted"><?= e($user['email']) ?></div>
                </div>
            </div>
            <dl class="item-card-meta">
                <div><dt>Role</dt><dd><?= e(ROLES[$user['role']] ?? $user['role']) ?></dd></div>
                <?php if ($user['student_number'] || $user['employee_number']): ?><div><dt>School record</dt><dd><?= e(account_type_label($user)) ?></dd></div><?php endif; ?>
                <div><dt>Member since</dt><dd><?= e(format_date($user['created_at'])) ?></dd></div>
            </dl>
            <p class="text-sm text-muted mb-0">Your name comes from the school's records. To change it or your role, contact the Lost &amp; Found office.</p>
        </div>

        <div class="card">
            <h2>Change password</h2>
            <form method="post" action="<?= e(url('/?tab=account')) ?>" class="form" data-validate data-submit-guard>
                <input type="email" name="username" value="<?= e($user['email']) ?>" autocomplete="username" hidden>
                <div class="form-group">
                    <label for="current_password">Current password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password" aria-describedby="current_password-error"<?= $inv('current_password') ?>>
                    <?= $err('current_password') ?>
                </div>
                <div class="form-group">
                    <label for="new_password">New password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="new_password" name="new_password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" data-label="New password" data-password-policy data-differs="current_password"
                           data-person-names="<?= e($user['first_name'] . '|' . $user['last_name']) ?>" data-birthday-checked<?= $inv('new_password') ?>>
                    <?= $err('new_password') ?>
                </div>
                <div class="form-group">
                    <label for="new_password_confirm">Confirm new password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" required autocomplete="new-password" data-match="new_password" data-label="Confirm new password" data-no-paste<?= $inv('new_password_confirm') ?>>
                    <?= $err('new_password_confirm') ?>
                </div>
                <button type="submit" class="btn btn-primary">Change password</button>
            </form>
        </div>
    </div>
