<?php
/**
 * MFA Change — Password gate (logged-in user)
 */
$page_title = t('mfa.title_change');
$breadcrumbs = [
    ['label' => t('account.title'), 'url' => '/settings?tab=security'],
    ['label' => t('mfa.title_change')],
];
ob_start();
?>

<div class="max-w-2xl">
    <div class="card p-6">
        <h2 class="section-title mb-2"><?= sanitize(t('mfa.verify_identity')) ?></h2>
        <p class="text-sm text-gray-600 mb-6">
            <?= sanitize(t('mfa.enter_password_description')) ?>
        </p>

        <form method="POST" action="/mfa/change/auth" class="space-y-4">
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

            <div>
                <label for="code"><?= sanitize(t('mfa.auth_code_label')) ?></label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    required
                    autocomplete="one-time-code"
                    inputmode="numeric"
                    pattern="\d{6}"
                    maxlength="6"
                    placeholder="000000"
                    class="text-center text-2xl tracking-[0.3em] font-mono"
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
