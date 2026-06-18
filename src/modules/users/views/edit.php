<?php
$page_title = 'Edit User';
$current_user = current_user();

// Defensive checks for required variables
if (!isset($user) || !is_array($user)) {
    flash('error', 'User data not found.');
    redirect('/admin/users');
}

if (!isset($assignable_roles) || !is_array($assignable_roles)) {
    $assignable_roles = [];
}

if (!isset($permissions) || !is_array($permissions)) {
    $permissions = [];
}

// Helper functions for badges (guarded to avoid redeclaration across views)
if (!function_exists('get_role_badge_class')) {
    function get_role_badge_class(string $role): string {
        return match($role) {
            'super_admin' => 'bg-purple-100 text-purple-800',
            'admin' => 'bg-blue-100 text-blue-800',
            'reviewer' => 'bg-green-100 text-green-800',
            'user' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }
}

if (!function_exists('format_role')) {
    function format_role(string $role): string {
        return match($role) {
            'super_admin' => 'Super Admin',
            default => ucfirst($role)
        };
    }
}

ob_start();
?>

<!-- Header -->
<div class="sm:flex sm:items-center sm:justify-between">
    <div class="sm:flex-auto">
        <h1 class="page-title">Edit User</h1>
        <p class="mt-2 text-sm text-gray-700"><?= sanitize($user['name']) ?> — <?= sanitize($user['email']) ?></p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <div class="flex items-center gap-3">
            <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold <?= get_role_badge_class($user['role']) ?>">
                <?= format_role($user['role']) ?>
            </span>
            <a href="/admin/users/<?= sanitize($user['uuid']) ?>/profile"
               class="btn btn-secondary gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                View Profile
            </a>
        </div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2 space-y-6">
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
                            <p class="body-text mt-0.5">User account details and role</p>
                        </div>
                    </div>
                </div>
                <form action="/admin/users/<?= sanitize($user['uuid']) ?>" method="POST" class="p-6 space-y-5" id="admin-user-edit-form">
                    <?= csrf_field() ?>
                    
                    <!-- Name -->
                    <div>
                        <label for="name">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="name" required
                               maxlength="100"
                               value="<?= sanitize($user['name']) ?>">
                    </div>

                    <!-- Email (Read-only) -->
                    <div>
                        <label for="email">
                            Email
                        </label>
                        <input type="email" id="email" disabled
                               value="<?= sanitize($user['email']) ?>"
                               class="bg-gray-50 text-gray-500">
                        <p class="help-text">Email cannot be changed.</p>
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="role">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select name="role" id="role" required>
                            <?php foreach ($assignable_roles as $role_value => $role_label): ?>
                                <option value="<?= sanitize($role_value) ?>" <?= $user['role'] === $role_value ? 'selected' : '' ?>>
                                    <?= sanitize($role_label) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (!isset($assignable_roles[$user['role']])): ?>
                                <!-- Keep current role if it's not in assignable (e.g., super_admin editing another super_admin) -->
                                <option value="<?= sanitize($user['role']) ?>" selected>
                                    <?= format_role($user['role']) ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select name="status" id="status" required
                                <?= ($user['id'] == $current_user['id'] || $user['status'] === 'pending') ? 'disabled' : '' ?>>
                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            <?php if ($user['status'] === 'pending'): ?>
                                <option value="pending" selected>Pending (email unverified)</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($user['id'] == $current_user['id']): ?>
                            <p class="help-text">You cannot change your own status.</p>
                        <?php elseif ($user['status'] === 'pending'): ?>
                            <p class="help-text">Status is managed by the email verification flow.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Global Reports Access (super_admin editing an admin) -->
                    <?php if ($current_user['role'] === 'super_admin' && $user['role'] === 'admin'): ?>
                    <div class="relative flex items-start">
                        <div class="flex h-6 items-center">
                            <input id="can_global_reports" name="can_global_reports" type="checkbox" value="1"
                                   <?= !empty($user['can_global_reports']) ? 'checked' : '' ?>
                                   class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                        </div>
                        <div class="ml-3 text-sm leading-6">
                            <label for="can_global_reports" class="font-medium text-gray-900">Global Reports Access</label>
                            <p class="text-gray-500">Allow this admin to view reports across all forms and programs, not just their own.</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                        <button type="submit" 
                                class="btn btn-primary hover:bg-primary-500">
                            Save Changes
                        </button>
                        <a href="/admin/users" 
                           class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Reset Password -->
            <div class="card">
                <div class="card-header bg-gradient-to-r from-purple-50 to-pink-50">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="section-title">Reset Password</h3>
                            <p class="body-text mt-0.5">Set a new password for this user</p>
                        </div>
                    </div>
                </div>
                <form action="/admin/users/<?= sanitize($user['uuid']) ?>/reset-password" method="POST" class="p-6 space-y-5" id="admin-user-reset-password-form" novalidate>
                    <?= csrf_field() ?>

                    <!-- Inline error banner (hidden by default) -->
                    <div class="alert alert-danger hidden" id="pw-form-error" role="alert"></div>
                    
                    <!-- New Password -->
                    <div>
                        <label for="new_password">
                            New Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="new_password" id="new_password" required minlength="8"
                                   class="focus:ring-purple-500 focus:border-purple-500"
                                   style="padding-inline-end: 2.5rem">
                            <button type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                    onclick="togglePassword('new_password', this)"
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

                    <!-- Confirm New Password -->
                    <div>
                        <label for="new_password_confirm">
                            Confirm New Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="new_password_confirm" id="new_password_confirm" required minlength="8"
                                   class="focus:ring-purple-500 focus:border-purple-500"
                                   style="padding-inline-end: 2.5rem">
                            <button type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                    onclick="togglePassword('new_password_confirm', this)"
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

                    <!-- Actions -->
                    <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                        <button type="submit" 
                                class="rounded-lg bg-red-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 transition">
                            Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Activity -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg p-6">
                <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Activity</h3>
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Last Login</dt>
                        <dd class="mt-1 text-gray-900 font-medium">
                            <?= $user['last_login_at'] ? format_datetime($user['last_login_at'], 'short') : 'Never' ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Account Created</dt>
                        <dd class="mt-1 text-gray-900 font-medium">
                            <?= format_datetime($user['created_at'], 'short') ?>
                        </dd>
                    </div>
                    <?php if ($user['invited_by']): ?>
                        <div>
                            <dt class="text-gray-500">Created By</dt>
                            <dd class="mt-1 text-gray-900 font-medium">
                                <?= sanitize($user['invited_by_name']) ?>
                                <div class="help-text"><?= sanitize($user['invited_by_email']) ?></div>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($user['email_verified_at']): ?>
                        <div>
                            <dt class="text-gray-500">Email Verified</dt>
                            <dd class="mt-1 text-gray-900 font-medium">
                                <?= format_datetime($user['email_verified_at'], 'date') ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Form Permissions -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg p-6">
                <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Form Permissions</h3>
                <?php if (empty($permissions)): ?>
                    <p class="text-sm text-gray-500">No explicit form permissions assigned.</p>
                    <p class="mt-2 text-xs text-gray-400">Permissions are managed from individual form settings.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($permissions as $perm): ?>
                            <div class="text-sm">
                                <a href="/admin/forms/<?= sanitize($perm['form_uuid']) ?>/settings" 
                                   class="font-medium text-primary-600 hover:text-primary-500">
                                    <?= sanitize($perm['form_name']) ?>
                                </a>
                                <div class="help-text">
                                    Access: <span class="font-medium"><?= sanitize($perm['access']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-4 text-xs text-gray-400">Manage these permissions from the form settings page.</p>
                <?php endif; ?>
            </div>

            <!-- Danger Zone -->
            <?php if ($user['id'] != $current_user['id'] && $user['status'] !== 'pending'): ?>
                <div class="bg-white shadow-sm ring-1 ring-red-900/10 rounded-lg p-6 border border-red-200">
                    <h3 class="text-base font-semibold leading-6 text-red-900 mb-2">Danger Zone</h3>
                    <p class="body-text mb-4">
                        <?= $user['status'] === 'suspended' ? 'Activate' : 'Suspend' ?> this user account.
                    </p>
                    <form action="/admin/users/<?= sanitize($user['uuid']) ?>/toggle-status" method="POST"
                          onsubmit="return confirm('Are you sure you want to <?= $user['status'] === 'active' ? 'suspend' : 'activate' ?> this user?')">
                        <?= csrf_field() ?>
                        <button type="submit" 
                                class="w-full rounded-md <?= $user['status'] === 'active' ? 'bg-red-600 hover:bg-red-500' : 'bg-green-600 hover:bg-green-500' ?> px-3 py-2 text-sm font-semibold text-white shadow-sm">
                            <?= $user['status'] === 'active' ? 'Suspend User' : 'Activate User' ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
    </div>
</div>

<script>
// Name validation for the edit form (uses setCustomValidity — separate from password form)
(function() {
    const form = document.getElementById('admin-user-edit-form');
    const input = document.getElementById('name');
    if (!form || !input) return;

    const nameRegex = /^[\p{L} .'-]+$/u;
    const invalidMessage = 'Name contains invalid characters. Use letters, spaces, hyphens, apostrophes, and periods only.';

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

<script type="module">
import {
    togglePasswordVisibility,
    initPasswordValidation,
    validatePasswordStrength,
    validatePasswordMatch,
} from '<?= asset('/js/password-validation.js') ?>';

window.togglePassword = (id, btn) => togglePasswordVisibility(id, btn);

const pw = initPasswordValidation({
    passwordId: 'new_password',
    confirmId:  'new_password_confirm',
});

document.getElementById('admin-user-reset-password-form').addEventListener('submit', function(e) {
    const banner = document.getElementById('pw-form-error');
    banner.classList.add('hidden');

    const password = document.getElementById('new_password').value;

    if (!validatePasswordStrength(pw.rules, password, 'pw-form-error', 'Please meet all password requirements.')) {
        e.preventDefault();
        return;
    }

    if (!validatePasswordMatch(password, document.getElementById('new_password_confirm').value, 'pw-match-error', 'pw-form-error', 'Passwords must match.')) {
        e.preventDefault();
        return;
    }

    if (!confirm('Are you sure you want to reset this user\'s password?')) {
        e.preventDefault();
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/admin.php';
?>
