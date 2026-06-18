<?php
/**
 * MFA Enable — Password gate (logged-in user)
 */
$page_title = t('mfa.title_enable');
$breadcrumbs = [
    ['label' => t('account.title'), 'url' => '/settings?tab=security'],
    ['label' => t('mfa.title_enable')],
];
ob_start();
?>

<div class="max-w-2xl">
    <div class="card p-6">
        <h2 class="section-title mb-2"><?= sanitize(t('mfa.verify_identity')) ?></h2>
        <p class="text-sm text-gray-600 mb-6">
            <?= sanitize(t('mfa.enable_password_description')) ?>
        </p>

        <form method="POST" action="/mfa/enable" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label for="password"><?= sanitize(t('mfa.current_password')) ?></label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autofocus
                    autocomplete="current-password"
                />
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="/settings?tab=security" class="btn btn-secondary">
                    <?= sanitize(t('common.cancel')) ?>
                </a>
                <button type="submit" class="btn btn-primary">
                    <?= sanitize(t('common.continue')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/user.php';
?>