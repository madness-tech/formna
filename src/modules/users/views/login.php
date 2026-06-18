<?php
$title = t('auth.sign_in');
ob_start();
?>

<?php require __DIR__ . '/../../../layouts/partials/flash.php'; ?>

<?php if (($_GET['msg'] ?? '') === 'password_changed'): ?>
    <div class="alert alert-success mb-6">
        <?= sanitize(t('account.password_updated')) ?>
    </div>
<?php elseif (($_GET['msg'] ?? '') === 'email_changed'): ?>
    <div class="alert alert-success mb-6">
        Your email has been changed successfully. Please log in with your new email.
    </div>
<?php endif; ?>

<h2 class="page-title mb-6"><?= t('auth.sign_in_heading') ?></h2>

<form method="POST" action="/login" class="space-y-6" id="login-form">
    <?php echo csrf_field(); ?>
    
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
        <label for="password">
            <?= t('auth.password_label') ?>
        </label>
        <div class="relative">
            <input
                type="password"
                id="password"
                name="password"
                required
                placeholder="<?= t('auth.password_placeholder') ?>"
                style="padding-inline-end: 2.5rem"
            />
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                onclick="togglePassword('password', this)"
                aria-label="<?= sanitize(t('auth.show_password')) ?>"
            >
                <svg class="h-4 w-4 icon-eye" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg class="h-4 w-4 icon-eye-off hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
            </button>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" name="remember_me" value="1" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 h-4 w-4" />
            <span class="text-sm text-gray-600"><?= t('auth.remember_me') ?></span>
        </label>
        <a href="/forgot-password" class="text-sm font-medium text-primary-600 hover:text-primary-500">
            <?= t('auth.forgot_password') ?>
        </a>
    </div>
    
    <div>
        <button
            type="submit"
            class="btn btn-primary btn-full"
            id="login-submit"
        >
            <svg class="animate-spin h-4 w-4 hidden" id="login-spinner" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span id="login-label"><?= t('auth.sign_in') ?></span>
        </button>
    </div>
</form>

<div class="mt-6 text-center">
    <p class="body-text">
        <?= t('auth.no_account') ?>
        <a href="/register" class="font-medium text-primary-600 hover:text-primary-500">
            <?= t('auth.register_now') ?>
        </a>
    </p>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.querySelector('.icon-eye').classList.toggle('hidden', isHidden);
    btn.querySelector('.icon-eye-off').classList.toggle('hidden', !isHidden);
    btn.setAttribute('aria-label', isHidden
        ? <?= json_encode(t('auth.hide_password')) ?>
        : <?= json_encode(t('auth.show_password')) ?>
    );
}

const loginForm = document.getElementById('login-form');
const loginSubmitBtn = document.getElementById('login-submit');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');

function updateLoginSubmitState() {
    const hasEmail = emailInput.value.trim().length > 0;
    const hasPassword = passwordInput.value.trim().length > 0;
    loginSubmitBtn.disabled = !(hasEmail && hasPassword);
}

emailInput.addEventListener('input', updateLoginSubmitState);
passwordInput.addEventListener('input', updateLoginSubmitState);
emailInput.addEventListener('change', updateLoginSubmitState);
passwordInput.addEventListener('change', updateLoginSubmitState);
updateLoginSubmitState();

loginForm.addEventListener('submit', function() {
    const btn = loginSubmitBtn;
    btn.disabled = true;
    document.getElementById('login-spinner').classList.remove('hidden');
    document.getElementById('login-label').textContent = <?= json_encode(t('auth.signing_in')) ?>;
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/auth.php';
?>
