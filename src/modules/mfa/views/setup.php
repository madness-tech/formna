<?php
/**
 * MFA Setup — QR code + code entry (pre-login, first-time)
 *
 * @var string $secret   Base32 TOTP secret (plaintext, for manual entry)
 * @var string $totp_uri otpauth:// URI for QR code
 */
$title = t('mfa.title_setup');
ob_start();
?>

<h2 class="page-title mb-2"><?= sanitize(t('mfa.title_setup')) ?></h2>
<p class="text-sm text-gray-600 mb-6">
    <?= sanitize(t('mfa.setup_description')) ?>
</p>

<!-- QR Code -->
<div class="flex justify-center mb-6">
    <div id="qrcode" class="bg-white p-3 rounded-lg border border-gray-200 inline-block"></div>
</div>

<!-- Manual entry fallback -->
<details class="mb-6">
    <summary class="text-sm text-gray-500 hover:text-gray-700 cursor-pointer select-none text-center">
        <?= sanitize(t('mfa.cant_scan_qr')) ?>
    </summary>
    <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-xs text-gray-500 mb-1"><?= sanitize(t('mfa.manual_entry')) ?></p>
        <div class="flex items-center gap-2">
            <code id="secret-display" class="flex-1 text-sm font-mono bg-white px-3 py-2 rounded border border-gray-200 select-all break-all"><?= sanitize($secret) ?></code>
            <button type="button" onclick="copySecret(this)" class="btn btn-secondary text-xs px-3 py-2 flex-shrink-0" title="Copy">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                </svg>
            </button>
        </div>
    </div>
</details>

<!-- Verify code form -->
<form method="POST" action="/mfa/setup" class="space-y-6">
    <?= csrf_field() ?>

    <div>
        <label for="code"><?= sanitize(t('mfa.verification_code_label')) ?></label>
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
            <?= sanitize(t('mfa.verify_continue')) ?>
        </button>
    </div>
</form>

<div class="mt-4 text-center">
    <a href="/login" class="text-sm text-gray-500 hover:text-gray-700">
        <?= sanitize(t('mfa.cancel_return_login')) ?>
    </a>
</div>

<script src="<?= asset('/js/qrcode.min.js') ?>"></script>
<script>
    (function() {
        var uri = <?= json_encode($totp_uri, JSON_UNESCAPED_SLASHES) ?>;
        if (typeof QRCode !== 'undefined') {
            new QRCode(document.getElementById('qrcode'), {
                text: uri,
                width: 200,
                height: 200,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    })();

    function copySecret(btn) {
        var text = document.getElementById('secret-display').textContent;
        var original = btn.innerHTML;
        navigator.clipboard.writeText(text).then(function() {
            btn.innerHTML = '<svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
            setTimeout(function() { btn.innerHTML = original; }, 1500);
        });
    }
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/auth.php';
?>
