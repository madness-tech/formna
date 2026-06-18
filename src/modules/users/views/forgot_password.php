<?php
$title = t('auth.forgot_password_heading');
ob_start();
?>

<h2 class="page-title mb-2"><?= sanitize(t('auth.forgot_password_heading')) ?></h2>
<p class="text-sm text-gray-600 mb-6"><?= sanitize(t('auth.forgot_password_description')) ?></p>

<form method="POST" action="/forgot-password" class="space-y-6">
    <?= csrf_field() ?>

    <div>
        <label for="email">
            <?= t('auth.email_label') ?>
        </label>
        <input
            type="email"
            id="email"
            name="email"
            required
            autofocus
            placeholder="<?= t('auth.email_placeholder') ?>"
        />
    </div>

    <div>
        <button type="submit" class="btn btn-primary btn-full">
            <?= t('auth.send_reset_link') ?>
        </button>
    </div>
</form>

<div class="mt-6 text-center">
    <a href="/login" class="text-sm text-gray-500 hover:text-gray-700">
        <?= sanitize(t('auth.back_to_sign_in')) ?>
    </a>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/auth.php';
?>
