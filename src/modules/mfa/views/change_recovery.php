<?php
/**
 * MFA Change — New recovery code display (logged-in user)
 *
 * @var string $recovery_code Plaintext new recovery code
 */
$page_title = t('mfa.title_change');
$breadcrumbs = [
    ['label' => t('account.title'), 'url' => '/settings?tab=security'],
    ['label' => t('mfa.title_change')],
];
ob_start();
?>

<div class="max-w-2xl space-y-6">
    <div class="card p-6">
        <h2 class="section-title mb-2"><?= sanitize(t('mfa.change_recovery_heading')) ?></h2>
        <p class="text-sm text-gray-600 mb-6">
            <?= sanitize(t('mfa.change_recovery_description')) ?>
        </p>

        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-red-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div>
                    <p class="text-sm font-semibold text-red-800"><?= sanitize(t('mfa.shown_once_warning')) ?></p>
                    <p class="text-sm text-red-700 mt-1">
                        <?= sanitize(t('mfa.change_recovery_warning')) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Recovery Code Display -->
        <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-6 text-center mb-6">
            <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold mb-3"><?= sanitize(t('mfa.new_recovery_code_label')) ?></p>
            <code id="recovery-code" class="text-xl font-mono font-bold text-gray-900 tracking-wider select-all"><?= sanitize($recovery_code) ?></code>
            <div class="mt-4">
                <button type="button" onclick="copyRecovery()" id="copy-btn" class="btn btn-secondary text-sm">
                    <svg class="h-4 w-4 mr-1.5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                    </svg>
                    <?= sanitize(t('mfa.copy_to_clipboard')) ?>
                </button>
            </div>
        </div>

        <!-- Action buttons -->
        <form method="POST" action="/mfa/change/confirm">
            <?= csrf_field() ?>
            <div class="flex items-center justify-end gap-3">
                <a href="/settings?tab=security" class="btn btn-secondary">
                    <?= sanitize(t('common.cancel')) ?>
                </a>
                <button type="submit" class="btn btn-primary" onclick="return confirm(<?= sanitize(json_encode(t('mfa.activate_confirm'))) ?>)">
                    <?= sanitize(t('mfa.activate_new_mfa')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function copyRecovery() {
        var code = document.getElementById('recovery-code').textContent;
        navigator.clipboard.writeText(code).then(function() {
            var btn = document.getElementById('copy-btn');
            btn.textContent = '\u2713 ' + <?= json_encode(t('mfa.copied')) ?>;
            btn.classList.add('text-green-700');
            setTimeout(function() {
                btn.innerHTML = '<svg class="h-4 w-4 mr-1.5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>' + <?= json_encode(sanitize(t('mfa.copy_to_clipboard'))) ?>;
                btn.classList.remove('text-green-700');
            }, 2000);
        });
    }
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/user.php';
?>
