<?php
$page_title = 'Create User';
$current_user = current_user();

// $assignable_roles is provided by the controller via get_assignable_roles()
if (!isset($assignable_roles) || !is_array($assignable_roles)) {
    $assignable_roles = [];
}

ob_start();
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <div class="flex items-center gap-4">
            <a href="/admin/users" class="text-gray-400 hover:text-gray-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <div>
                <h1 class="page-title">Create User</h1>
                <p class="mt-2 text-sm text-gray-700">Add a new admin, reviewer, or user account.</p>
            </div>
        </div>
    </div>
</div>

<form action="/admin/users" method="POST" class="mt-6 max-w-2xl space-y-6" id="admin-user-create-form" novalidate>
        <?= csrf_field() ?>
        
        <!-- Basic Information -->
        <div class="card">
            <div class="card-header bg-gradient-to-r from-primary-50 to-blue-50">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="section-title">Basic Information</h3>
                        <p class="body-text mt-0.5">User account details and credentials</p>
                    </div>
                </div>
            </div>
            <div class="p-6 space-y-5">
                <!-- Name -->
                <div>
                    <label for="name">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" required>
                </div>

                <!-- Email -->
                <div>
                    <label for="email">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" id="email" required>
                </div>

                <!-- Role -->
                <div>
                    <label for="role">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select name="role" id="role" required>
                        <?php foreach ($assignable_roles as $role_value => $role_label): ?>
                            <option value="<?= sanitize($role_value) ?>"><?= sanitize($role_label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help-text">
                        <?php if ($current_user['role'] === 'super_admin'): ?>
                            You can assign Admin, Reviewer, or User roles.
                        <?php else: ?>
                            You can assign Reviewer or User roles only.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Password -->
        <div class="card">
            <div class="card-header bg-gradient-to-r from-purple-50 to-pink-50">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="section-title">Password</h3>
                        <p class="body-text mt-0.5">Set a secure password for this account</p>
                    </div>
                </div>
            </div>
            <div class="p-6 space-y-5">
                <!-- Inline error banner (hidden by default) -->
                <div class="alert alert-danger hidden" id="pw-form-error" role="alert"></div>

                <!-- Password -->
                <div>
                    <label for="password">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required minlength="8"
                               class="focus:ring-purple-500 focus:border-purple-500"
                               style="padding-inline-end: 2.5rem">
                        <button type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                onclick="togglePassword('password', this)"
                                aria-label="Show password">
                            <svg class="h-4 w-4 icon-eye" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg class="h-4 w-4 icon-eye-off hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>

                    <?php $pw_labels = []; require __DIR__ . '/../../../layouts/partials/password_rules.php'; ?>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirm">
                        Confirm Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="password_confirm" id="password_confirm" required minlength="8"
                               class="focus:ring-purple-500 focus:border-purple-500"
                               style="padding-inline-end: 2.5rem">
                        <button type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                onclick="togglePassword('password_confirm', this)"
                                aria-label="Show password">
                            <svg class="h-4 w-4 icon-eye" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg class="h-4 w-4 icon-eye-off hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-red-500 mt-2 hidden" id="pw-match-error">Passwords must match.</p>
                </div>

                <!-- Generate Password Button -->
                <div class="pt-2 border-t border-gray-100">
                    <button type="button" onclick="generatePassword()" 
                            class="btn btn-secondary gap-x-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Generate Random Password
                    </button>
                </div>

                <!-- Generated Password Display -->
                <div id="generated-password-display" class="hidden bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-green-800">Password Generated</p>
                            <p class="mt-1 text-sm text-green-700">
                                Make sure to copy this password and share it securely with the user:
                            </p>
                            <div class="mt-2 flex items-center gap-2">
                                <code id="generated-password-text" class="text-sm font-mono bg-white px-3 py-2 rounded border border-green-300 flex-1"></code>
                                <button type="button" onclick="copyPassword()" 
                                        class="inline-flex items-center gap-x-1.5 rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                                    </svg>
                                    Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-4">
            <button type="submit" 
                    class="btn btn-primary hover:bg-primary-500">
                Create User
            </button>
            <a href="/admin/users" 
               class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>

<script type="module">
import {
    togglePasswordVisibility,
    initPasswordValidation,
    validatePasswordStrength,
    validatePasswordMatch,
} from '<?= asset('/js/password-validation.js') ?>';

window.togglePassword = (id, btn) => togglePasswordVisibility(id, btn);

const pw = initPasswordValidation({
    passwordId: 'password',
    confirmId:  'password_confirm',
});

const nameInput  = document.getElementById('name');
const emailInput = document.getElementById('email');
const nameRegex  = /^[\p{L} .'-]+$/u;
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

document.getElementById('admin-user-create-form').addEventListener('submit', function(e) {
    const banner = document.getElementById('pw-form-error');
    banner.classList.add('hidden');

    // Validate name
    const name = nameInput.value.trim();
    if (!name) {
        e.preventDefault();
        nameInput.focus();
        banner.textContent = 'Name is required.';
        banner.classList.remove('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }
    if (!nameRegex.test(name)) {
        e.preventDefault();
        nameInput.focus();
        banner.textContent = 'Name contains invalid characters. Use letters, spaces, hyphens, apostrophes, and periods only.';
        banner.classList.remove('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }

    // Validate email
    const email = emailInput.value.trim();
    if (!email || !emailRegex.test(email)) {
        e.preventDefault();
        emailInput.focus();
        banner.textContent = 'A valid email address is required.';
        banner.classList.remove('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }

    // Validate password strength
    const password = document.getElementById('password').value;
    if (!validatePasswordStrength(pw.rules, password, 'pw-form-error', 'Please meet all password requirements.')) {
        e.preventDefault();
        return;
    }

    // Validate password match
    if (!validatePasswordMatch(password, document.getElementById('password_confirm').value, 'pw-match-error', 'pw-form-error', 'Passwords must match.')) {
        e.preventDefault();
        return;
    }
});

// Generate random password
window.generatePassword = function() {
    const length  = 16;
    const upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lower   = 'abcdefghijklmnopqrstuvwxyz';
    const digits  = '0123456789';
    const special = '!@#$%^&*';
    const charset = upper + lower + digits + special;

    function secureRandInt(max) {
        const arr = new Uint32Array(1);
        let result;
        do {
            crypto.getRandomValues(arr);
            result = arr[0] % max;
        } while (arr[0] - result > (0xFFFFFFFF - max + 1));
        return result;
    }

    let chars = [
        upper[secureRandInt(upper.length)],
        lower[secureRandInt(lower.length)],
        digits[secureRandInt(digits.length)],
        special[secureRandInt(special.length)],
    ];

    for (let i = chars.length; i < length; i++) {
        chars.push(charset[secureRandInt(charset.length)]);
    }

    for (let i = chars.length - 1; i > 0; i--) {
        const j = secureRandInt(i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }

    const password = chars.join('');

    document.getElementById('password').value = password;
    document.getElementById('password_confirm').value = password;
    document.getElementById('password').type = 'text';
    document.getElementById('password_confirm').type = 'text';

    document.getElementById('generated-password-text').textContent = password;
    document.getElementById('generated-password-display').classList.remove('hidden');

    // Update the live complexity guide and clear errors
    pw.updateRules();
    document.getElementById('pw-match-error').classList.add('hidden');
    document.getElementById('pw-form-error').classList.add('hidden');
};

window.copyPassword = function() {
    const password = document.getElementById('generated-password-text').textContent;
    navigator.clipboard.writeText(password).then(() => {
        alert('Password copied to clipboard!');
    });
};
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/admin.php';
?>
