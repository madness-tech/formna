<?php
/**
 * MFA Verify — Enter TOTP code (pre-login)
 */
$title = t('mfa.title_verify');
ob_start();
?>

<h2 class="page-title mb-2"><?= sanitize(t('mfa.title_verify')) ?></h2>
<p class="text-sm text-gray-600 mb-6"><?= sanitize(t('mfa.verify_description')) ?></p>

<form method="POST" action="/mfa/verify" class="space-y-6" id="mfa-verify-form">
    <?= csrf_field() ?>

    <div>
        <label for="code"><?= sanitize(t('mfa.auth_code_label')) ?></label>
        <input
            type="text"
            id="code"
            name="code"
            required
            autofocus
            autocomplete="one-time-code"
            inputmode="numeric"
            pattern="\d{6}"
            maxlength="6"
            placeholder="000000"
            class="text-center text-2xl tracking-[0.3em] font-mono"
        />
    </div>

    <div>
        <button type="submit" class="btn btn-primary btn-full">
            <?= sanitize(t('mfa.verify_button')) ?>
        </button>
    </div>
</form>

<div class="mt-6 border-t border-gray-200 pt-6">
    <details class="group">
        <summary class="text-sm font-medium text-gray-600 hover:text-gray-900 cursor-pointer select-none">
            <?= sanitize(t('mfa.lost_access')) ?>
        </summary>
        <div class="mt-4">
            <form method="POST" action="/mfa/verify" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="use_recovery" value="1" />

                <div>
                    <label for="recovery_code"><?= sanitize(t('mfa.recovery_code_label')) ?></label>
                    <input
                        type="text"
                        id="recovery_code"
                        name="code"
                        required
                        autocomplete="off"
                        placeholder="XXXXX-XXXXX-XXXXX-XXXXX"
                        class="font-mono"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        <?= sanitize(t('mfa.recovery_help')) ?>
                    </p>
                </div>

                <button type="submit" class="btn btn-secondary btn-full">
                    <?= sanitize(t('mfa.use_recovery_code')) ?>
                </button>
            </form>
        </div>
    </details>
</div>

<div class="mt-4 text-center">
    <a href="/login" class="text-sm text-gray-500 hover:text-gray-700">
        <?= sanitize(t('mfa.back_to_login')) ?>
    </a>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/auth.php';
?>
