<?php
$title = t('auth.register');
ob_start();
?>

<?php require __DIR__ . '/../../../layouts/partials/flash.php'; ?>

<h2 class="page-title mb-6"><?= t('auth.register_heading') ?></h2>

<form method="POST" action="/register" class="space-y-5" id="register-form" novalidate>
    <?php echo csrf_field(); ?>

    <!-- Inline error banner (hidden by default) -->
    <div class="alert alert-danger hidden" id="form-error" role="alert"></div>

    <div>
        <label for="name">
            <?= t('auth.full_name_label') ?> <span class="text-red-500">*</span>
        </label>
        <input
            type="text"
            id="name"
            name="name"
            required
            placeholder="<?= t('auth.name_placeholder') ?>"
        />
    </div>

    <div>
        <label for="email">
            <?= t('auth.email_label') ?> <span class="text-red-500">*</span>
        </label>
        <input
            type="email"
            id="email"
            name="email"
            required
            placeholder="<?= t('auth.email_placeholder') ?>"
        />
    </div>

    <div>
        <label for="password">
            <?= t('auth.password_label') ?> <span class="text-red-500">*</span>
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

        <?php
        $pw_labels = [
            'length'    => t('validation.password_req_length'),
            'uppercase' => t('validation.password_req_uppercase'),
            'lowercase' => t('validation.password_req_lowercase'),
            'digit'     => t('validation.password_req_digit'),
        ];
        require __DIR__ . '/../../../layouts/partials/password_rules.php';
        ?>
    </div>

    <div>
        <label for="password_confirm">
            <?= t('auth.confirm_password_label') ?> <span class="text-red-500">*</span>
        </label>
        <div class="relative">
            <input
                type="password"
                id="password_confirm"
                name="password_confirm"
                required
                placeholder="<?= t('auth.password_placeholder') ?>"
                style="padding-inline-end: 2.5rem"
            />
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                onclick="togglePassword('password_confirm', this)"
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
        <p class="text-xs text-red-500 mt-2 hidden" id="pw-match-error"><?= sanitize(t('validation.passwords_must_match')) ?></p>
    </div>

    <div>
        <button
            type="submit"
            class="btn btn-primary btn-full"
            id="register-submit"
        >
            <svg class="animate-spin h-4 w-4 hidden" id="register-spinner" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span id="register-label"><?= t('auth.create_account') ?></span>
        </button>
    </div>
</form>

<div class="mt-6 text-center">
    <p class="body-text">
        <?= t('auth.have_account') ?>
        <a href="/login" class="font-medium text-primary-600 hover:text-primary-500">
            <?= t('auth.sign_in_link') ?>
        </a>
    </p>
</div>

<script type="module">
import {
    togglePasswordVisibility,
    initPasswordValidation,
    validatePasswordStrength,
    validatePasswordMatch,
} from '<?= asset('/js/password-validation.js') ?>';

const toggleLabels = {
    show: <?= json_encode(t('auth.show_password')) ?>,
    hide: <?= json_encode(t('auth.hide_password')) ?>,
};
window.togglePassword = (id, btn) => togglePasswordVisibility(id, btn, toggleLabels);

const pw = initPasswordValidation({
    passwordId: 'password',
    confirmId:  'password_confirm',
});

const nameInput  = document.getElementById('name');
const emailInput = document.getElementById('email');
const nameRegex  = /^[\p{L} .'-]+$/u;
const nameHasLetterRegex = /\p{L}/u;
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const MSG_NAME_REQUIRED = <?= json_encode(t('validation.name_required')) ?>;
const MSG_NAME_INVALID  = <?= json_encode(t('validation.name_invalid')) ?>;
const MSG_EMAIL_INVALID = <?= json_encode(t('validation.invalid_email')) ?>;
const MSG_STRENGTH      = <?= json_encode(t('validation.password_requirements_not_met')) ?>;
const MSG_MATCH         = <?= json_encode(t('validation.passwords_must_match')) ?>;
const MSG_LOADING       = <?= json_encode(t('auth.creating_account')) ?>;

document.getElementById('register-form').addEventListener('submit', function(e) {
    const banner = document.getElementById('form-error');
    banner.classList.add('hidden');

    // Validate name
    const name = nameInput.value.trim();
    if (!name) {
        e.preventDefault();
        nameInput.focus();
        banner.textContent = MSG_NAME_REQUIRED;
        banner.classList.remove('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }
    if (!nameRegex.test(name) || !nameHasLetterRegex.test(name)) {
        e.preventDefault();
        nameInput.focus();
        banner.textContent = MSG_NAME_INVALID;
        banner.classList.remove('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }

    // Validate email
    const email = emailInput.value.trim();
    if (!email || !emailRegex.test(email)) {
        e.preventDefault();
        emailInput.focus();
        banner.textContent = MSG_EMAIL_INVALID;
        banner.classList.remove('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }

    // Validate password strength
    const password = document.getElementById('password').value;
    if (!validatePasswordStrength(pw.rules, password, 'form-error', MSG_STRENGTH)) {
        e.preventDefault();
        return;
    }

    // Validate password match
    if (!validatePasswordMatch(password, document.getElementById('password_confirm').value, 'pw-match-error', 'form-error', MSG_MATCH)) {
        e.preventDefault();
        return;
    }

    // Loading state
    const btn = document.getElementById('register-submit');
    btn.disabled = true;
    document.getElementById('register-spinner').classList.remove('hidden');
    document.getElementById('register-label').textContent = MSG_LOADING;
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/auth.php';
?>
