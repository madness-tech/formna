<?php
/**
 * MFA Admin Settings — Super Admin toggles per role
 *
 * @var array{mfa_enabled_admin: int, mfa_enabled_reviewer: int, mfa_enabled_user: int} $flags
 * @var array<int, array{label: string}> $breadcrumbs
 */
$page_title = 'MFA Settings';
ob_start();
?>

<div class="sm:flex sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="page-title">Two-Factor Authentication Settings</h1>
        <p class="mt-2 text-sm text-gray-600">Configure which roles require MFA at login.</p>
    </div>
</div>

<div class="card p-6 max-w-2xl">
    <form method="POST" action="/admin/mfa/settings" class="space-y-6">
        <?= csrf_field() ?>

        <!-- Super Admin — always on -->
        <div class="flex items-center justify-between py-3 border-b border-gray-100">
            <div>
                <p class="text-sm font-semibold text-gray-900">Super Admin</p>
                <p class="text-xs text-gray-500 mt-0.5">Super admins must always have MFA enabled.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-green text-xs">Always Required</span>
                <input type="checkbox" checked disabled class="opacity-50 cursor-not-allowed" />
            </div>
        </div>

        <!-- Admin -->
        <div class="flex items-center justify-between py-3 border-b border-gray-100">
            <div>
                <p class="text-sm font-semibold text-gray-900">Admin</p>
                <p class="text-xs text-gray-500 mt-0.5">Require MFA for all admin accounts.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="mfa_enabled_admin" value="1"
                       <?= $flags['mfa_enabled_admin'] ? 'checked' : '' ?>
                       class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
            </label>
        </div>

        <!-- Reviewer -->
        <div class="flex items-center justify-between py-3 border-b border-gray-100">
            <div>
                <p class="text-sm font-semibold text-gray-900">Reviewer</p>
                <p class="text-xs text-gray-500 mt-0.5">Require MFA for all reviewer accounts.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="mfa_enabled_reviewer" value="1"
                       <?= $flags['mfa_enabled_reviewer'] ? 'checked' : '' ?>
                       class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
            </label>
        </div>

        <!-- User -->
        <div class="flex items-center justify-between py-3 border-b border-gray-100">
            <div>
                <p class="text-sm font-semibold text-gray-900">User</p>
                <p class="text-xs text-gray-500 mt-0.5">Require MFA for all regular user accounts.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="mfa_enabled_user" value="1"
                       <?= $flags['mfa_enabled_user'] ? 'checked' : '' ?>
                       class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
            </label>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-800">
                <strong>Note:</strong> When MFA is enabled for a role, users will be asked to set up TOTP the next time they log in. Existing MFA users are unaffected.
            </p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                Save Settings
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/admin.php';
?>
