<?php
/**
 * Account Settings — Uses main user layout with tabbed interface
 *
 * @var array{id: int, name: string, email: string, email_verified_at: ?string, timezone: string, created_at: string} $user Current user data
 * @var array<int, array{type: string, email: string, created_at: string, expires_at: string}> $pending_verifications Pending email verifications
 */
$user = $user ?? [];
$pending_verifications = $pending_verifications;
$timezones = get_timezone_list();
/** @var string $active_tab Current active tab ('account' or 'security') */
$active_tab = $active_tab ?? 'account';

$page_title = t('account.title');
$breadcrumbs = [
    ['label' => t('account.title')],
];

ob_start();
?>

<!-- Tab Navigation -->
<div class="border-b border-gray-200 mb-6">
    <nav class="-mb-px flex gap-x-6" aria-label="Settings tabs">
        <a href="/settings?tab=account"
           class="whitespace-nowrap border-b-2 pb-3 px-1 text-sm font-medium <?= $active_tab === 'account' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>">
            <svg class="inline-block h-4 w-4 <?= is_rtl() ? 'ml-1.5' : 'mr-1.5' ?> -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            <?= t('account.tab_account') ?>
        </a>
        <a href="/settings?tab=security"
           class="whitespace-nowrap border-b-2 pb-3 px-1 text-sm font-medium <?= $active_tab === 'security' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>">
            <svg class="inline-block h-4 w-4 <?= is_rtl() ? 'ml-1.5' : 'mr-1.5' ?> -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </svg>
            <?= t('account.tab_security') ?>
        </a>
    </nav>
</div>

<?php if ($active_tab === 'account'): ?>
<!-- ═══════════════════════════════════════════════════════════ -->
<!-- ACCOUNT TAB                                                -->
<!-- ═══════════════════════════════════════════════════════════ -->

<div class="max-w-2xl space-y-6">
    <!-- Personal Information -->
    <div class="card p-6">
        <h2 class="section-title mb-4"><?= t('account.personal_info') ?></h2>
        
        <form method="POST" action="/settings/name" class="space-y-4" id="account-name-form">
            <?php echo csrf_field(); ?>
            
            <div>
                <label for="name"><?= t('account.full_name') ?></label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    value="<?php echo sanitize($user['name']); ?>"
                    required
                    maxlength="100"
                >
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary whitespace-nowrap flex-shrink-0">
                    <?= t('account.update_name') ?>
                </button>
            </div>
        </form>

        <script>
        (function() {
            const form = document.getElementById('account-name-form');
            const input = document.getElementById('name');
            if (!form || !input) return;

            const nameRegex = /^[\p{L} .'-]+$/u;
            const invalidMessage = <?= json_encode(t('validation.name_invalid')) ?>;

            form.addEventListener('submit', function(e) {
                const value = input.value.trim();
                input.setCustomValidity('');

                if (value !== '' && !nameRegex.test(value)) {
                    e.preventDefault();
                    input.setCustomValidity(invalidMessage);
                    input.reportValidity();
                }
            });
        })();
        </script>
    </div>

    <!-- Email Address -->
    <div class="card p-6">
        <h2 class="section-title mb-4"><?= t('account.email_section') ?></h2>
        
        <div class="mb-4">
            <label><?= t('account.current_email') ?></label>
            <div class="flex items-center gap-3">
                <input 
                    type="email" 
                    value="<?php echo sanitize($user['email']); ?>"
                    disabled
                    class="flex-1 border-gray-200 bg-gray-50 text-gray-500"
                >
                <?php if ($user['email_verified_at']): ?>
                    <span class="badge badge-green">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <?= t('account.verified') ?>
                    </span>
                <?php else: ?>
                    <span class="badge badge-yellow">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <?= t('account.unverified') ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($user['email_verified_at']): ?>
                <p class="text-xs text-gray-400 mt-1">
                    <?= t('account.verified_on', ['date' => format_datetime($user['email_verified_at'], 'date')]) ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (!$user['email_verified_at']): ?>
            <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-800 mb-2"><?= t('account.email_not_verified') ?></p>
                <form method="POST" action="/settings/resend-verification" class="inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="text-sm font-medium text-yellow-900 hover:text-yellow-700 underline">
                        <?= t('account.resend_verification') ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="/settings/email" class="space-y-4">
            <?php echo csrf_field(); ?>
            
            <div>
                <label for="new_email"><?= t('account.new_email') ?></label>
                <input 
                    type="email" 
                    id="new_email" 
                    name="new_email" 
                    placeholder="newemail@example.com"
                >
            </div>
            
            <div class="flex items-start justify-between gap-4">
                <p class="help-text">
                    <?= t('account.email_change_notice') ?>
                </p>
                <button type="submit" class="btn btn-primary whitespace-nowrap flex-shrink-0">
                    <?= t('account.change_email') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Timezone Preference -->
    <div class="card p-6">
        <h2 class="section-title mb-4"><?= t('account.timezone_section') ?></h2>
        
        <form method="POST" action="/settings/timezone" class="space-y-4">
            <?php echo csrf_field(); ?>
            
            <div>
                <label for="timezone"><?= t('account.your_timezone') ?></label>
                <select
                    id="timezone"
                    name="timezone"
                    required
                >
                    <?php foreach ($timezones as $tz_value => $tz_label): ?>
                        <option
                            value="<?php echo sanitize($tz_value); ?>"
                            <?php echo $user['timezone'] === $tz_value ? 'selected' : ''; ?>
                        >
                            <?php echo sanitize($tz_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex items-start justify-between gap-4">
                <p class="help-text">
                    <?= t('account.timezone_help') ?>
                </p>
                <button type="submit" class="btn btn-primary whitespace-nowrap flex-shrink-0">
                    <?= t('account.update_timezone') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════ -->
<!-- SECURITY TAB                                               -->
<!-- ═══════════════════════════════════════════════════════════ -->

<?php
    require_once __DIR__ . '/../../mfa/models.php';
    $mfa_has_confirmed = mfa_user_has_confirmed($user['id']);
    $mfa_is_required = mfa_required_for_role(current_user()['role']);
?>

<div class="max-w-2xl space-y-6">
    <!-- Two-Factor Authentication -->
    <div class="card p-6">
        <h2 class="section-title mb-6"><?= t('account.mfa_title') ?></h2>

        <div class="space-y-6">
            <!-- Status and Description -->
            <div>
                <?php if ($mfa_has_confirmed): ?>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="badge badge-green">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <?= t('account.mfa_enabled') ?>
                        </span>
                        <?php if ($mfa_is_required): ?>
                            <span class="text-xs text-gray-500">(<?= t('account.mfa_required_role') ?>)</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-600">
                        <?= t('account.mfa_enabled_description') ?>
                    </p>
                <?php else: ?>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="badge badge-yellow">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <?= t('account.mfa_not_configured') ?>
                        </span>
                        <?php if ($mfa_is_required): ?>
                            <span class="text-xs text-orange-600 font-medium">(<?= t('account.mfa_required_role') ?>)</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-600">
                        <?= t('account.mfa_disabled_description') ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Action Button -->
            <div class="flex items-start justify-between gap-4">
                <?php if ($mfa_has_confirmed): ?>
                    <p class="help-text">
                        <?= t('account.mfa_change_hint') ?>
                    </p>
                    <a href="/mfa/change" class="btn btn-secondary whitespace-nowrap flex-shrink-0">
                        <?= t('account.mfa_change_button') ?>
                    </a>
                <?php else: ?>
                    <p class="help-text">
                        <?= t('account.mfa_setup_hint') ?>
                    </p>
                    <a href="/mfa/enable" class="btn btn-primary whitespace-nowrap flex-shrink-0">
                        <?= t('account.mfa_enable_button') ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Info Notice -->
            <?php if (!$mfa_has_confirmed && $mfa_is_required): ?>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="flex gap-3">
                        <svg class="h-5 w-5 text-orange-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-orange-900 mb-1"><?= t('account.mfa_action_required') ?></p>
                            <p class="text-sm text-orange-800">
                                <?= t('account.mfa_required_notice') ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php elseif (!$mfa_has_confirmed): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex gap-3">
                        <svg class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <div>
                            <p class="text-sm text-blue-800">
                                <?= t('account.mfa_enable_notice') ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Password -->
    <div class="card p-6">
        <h2 class="section-title mb-4"><?= t('account.password_section') ?></h2>
        
        <form method="POST" action="/settings/password" class="space-y-4" id="pw-change-form" novalidate>
            <?php echo csrf_field(); ?>

            <!-- Inline error banner (hidden by default) -->
            <div class="alert alert-danger hidden" id="pw-form-error" role="alert"></div>
            
            <div>
                <label for="current_password"><?= t('account.current_password') ?></label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="current_password" 
                        name="current_password" 
                        required
                        style="padding-inline-end: 2.5rem"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                        onclick="togglePassword('current_password', this)"
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
            
            <div>
                <label for="new_password"><?= t('account.new_password') ?></label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        required
                        minlength="8"
                        style="padding-inline-end: 2.5rem"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                        onclick="togglePassword('new_password', this)"
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
                <label for="confirm_password"><?= t('account.confirm_password') ?></label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required
                        minlength="8"
                        style="padding-inline-end: 2.5rem"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                        onclick="togglePassword('confirm_password', this)"
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
            
            <div class="flex items-start justify-between gap-4">
                <p class="help-text">
                    <?= t('account.password_change_notice') ?>
                </p>
                <button type="submit" class="btn btn-primary whitespace-nowrap flex-shrink-0">
                    <?= t('account.update_password') ?>
                </button>
            </div>
        </form>
    </div>
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
    passwordId: 'new_password',
    confirmId:  'confirm_password',
});

const MSG_STRENGTH = <?= json_encode(t('validation.password_requirements_not_met')) ?>;
const MSG_MATCH    = <?= json_encode(t('validation.passwords_must_match')) ?>;

document.getElementById('pw-change-form').addEventListener('submit', function(e) {
    const banner = document.getElementById('pw-form-error');
    banner.classList.add('hidden');

    const password = document.getElementById('new_password').value;

    if (!validatePasswordStrength(pw.rules, password, 'pw-form-error', MSG_STRENGTH)) {
        e.preventDefault();
        return;
    }

    if (!validatePasswordMatch(password, document.getElementById('confirm_password').value, 'pw-match-error', 'pw-form-error', MSG_MATCH)) {
        e.preventDefault();
        return;
    }
});
</script>

<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/user.php';
?>
