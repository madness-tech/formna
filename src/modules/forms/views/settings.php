<?php
/**
 * Safety checks for PHPStan - these variables are guaranteed to be set by the controller
 */
if (!isset($form) || !isset($all_forms)) {
    throw new RuntimeException('Required variables not set in view');
}

$active_program_links = $active_program_links ?? [];
$can_unpublish_form = $can_unpublish_form ?? true;
?>
<!-- Form Settings -->

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title"><?= sanitize($form['name']) ?> - Settings</h1>
        <p class="mt-2 text-sm text-gray-700">Configure form behavior, deadlines, permissions, and access control</p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 flex items-center gap-3">
        <a href="/admin/forms/<?= $form['uuid'] ?>/builder" class="btn btn-secondary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            Form Builder
        </a>
    </div>
</div>

<form method="POST" action="/admin/forms/<?php echo $form['uuid']; ?>/settings" class="space-y-6 mt-6" novalidate>
    <?php echo csrf_field(); ?>
    
    <!-- Basic Information -->
    <div class="card">
        <div class="card-header bg-gradient-to-r from-primary-50 to-blue-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="section-title">Basic Information</h3>
                    <p class="body-text mt-0.5">Form name, description, and instructions for users</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <div>
                <label for="name">Form Name <span class="text-red-500">*</span></label>
                <input type="text"
                       name="name"
                       id="name"
                       value="<?php echo sanitize($form['name']); ?>"
                       required>
                <p class="help-text">A clear, descriptive name for your form</p>
            </div>
            
            <div>
                <label for="description">Description</label>
                <textarea name="description"
                          id="description"
                          rows="3"><?php echo sanitize($form['description']); ?></textarea>
                <p class="help-text">A brief description shown to users when viewing or filling out this form</p>
            </div>
            
            <div>
                <label for="instructions">User Instructions</label>
                <textarea name="instructions"
                          id="instructions"
                          rows="4"><?php echo sanitize($form['instructions']); ?></textarea>
                <p class="help-text">These instructions will be displayed at the top of the form for users</p>
            </div>
        </div>
    </div>
    
    <!-- Access & Permissions -->
    <div class="card">
        <div class="card-header bg-gradient-to-r from-purple-50 to-pink-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="section-title">Requirements</h3>
                    <p class="body-text mt-0.5">Control what is required to access this form</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <div>
                <label>Prerequisites</label>
                <select name="prerequisite_form_ids[]"
                        multiple
                        class="focus:ring-purple-500 focus:border-purple-500"
                        size="5">
                    <?php foreach ($all_forms as $f): ?>
                        <option value="<?php echo $f['id']; ?>"
                                <?php echo in_array($f['id'], $form['settings']['prerequisite_form_ids'] ?? []) ? 'selected' : ''; ?>>
                            <?php echo sanitize($f['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="help-text">Users must complete these forms first. Hold Ctrl/Cmd to select multiple</p>
            </div>
        </div>
    </div>
    
    <!-- Form Administrators -->
    <?php
    $current_user = current_user();
    $is_owner = is_form_owner($form['id'], $current_user['id']);
    $is_super_admin = $current_user['role'] === 'super_admin';
    $can_manage_admins = $is_owner || $is_super_admin;
    ?>
    <?php if ($can_manage_admins): ?>
    <div class="card" id="form-admins-section">
        <div class="card-header bg-gradient-to-r from-primary-50 to-purple-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="section-title">Form Administrators</h3>
                    <p class="body-text mt-0.5">Grant other admins permission to manage this form</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <!-- Search and Add Admin -->
            <div>
                <label>Add Administrator</label>
                <div class="flex gap-3">
                    <div class="flex-1 relative">
                        <input type="text"
                               id="admin-search"
                               placeholder="Search by name or email..."
                               autocomplete="off">
                        <div id="search-results" class="card hidden absolute z-10 w-full mt-1 shadow-lg max-h-60 overflow-y-auto"></div>
                    </div>
                </div>
                <p class="help-text">Search for admins or reviewers to grant access</p>
            </div>

            <!-- Admins Table -->
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Permission</th>
                            <th>Added</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="admins-table-body">
                        <tr>
                            <td colspan="5" class="empty-state">
                                <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                Loading administrators...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Deadlines & Limits -->
    <div class="card">
        <div class="card-header bg-gradient-to-r from-green-50 to-emerald-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="section-title">Deadlines & Limits</h3>
                    <p class="body-text mt-0.5">Configure submission deadlines, expiry, and user limits</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="submission_deadline">Submission Deadline</label>
                    <input type="datetime-local"
                           name="submission_deadline"
                           id="submission_deadline"
                           value="<?= to_datetime_local($form['settings']['submission_deadline'] ?? null) ?>"
                           min="<?= to_datetime_local(datetime_add(now(), '+1 hour')) ?>"
                           class="focus:ring-green-500 focus:border-green-500">
                    <p class="help-text">No new submissions accepted after this date/time. Must be at least 1 hour from now</p>
                </div>
                
                <div>
                    <label for="submission_limit">Max Entries Per User</label>
                    <input type="number"
                           name="submission_limit"
                           id="submission_limit"
                           value="<?php echo $form['settings']['submission_limit'] ?? 1; ?>"
                           min="0"
                           class="focus:ring-green-500 focus:border-green-500">
                    <p class="help-text">Default is 1. Set 0 for unlimited. Applies per user</p>
                </div>
            </div>
            
            <div>
                <label for="expiry_days">Submission Expiry (Days)</label>
                <input type="number"
                       name="expiry_days"
                       id="expiry_days"
                       value="<?php echo $form['settings']['expiry_days'] ?? ''; ?>"
                       min="1"
                       class="focus:ring-green-500 focus:border-green-500">
                <p class="help-text">Submissions expire after this many days. Leave empty for no expiry</p>
            </div>
        </div>
    </div>
    
    <!-- Editability -->
    <div class="card">
        <div class="card-header bg-gradient-to-r from-orange-50 to-amber-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="section-title">Editability</h3>
                    <p class="body-text mt-0.5">Allow users to modify submissions after they've been submitted</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <label class="flex items-start p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition">
                <input type="checkbox"
                       name="is_editable_after_submit"
                       id="is_editable_after_submit"
                       <?php echo ($form['settings']['is_editable_after_submit'] ?? false) ? 'checked' : ''; ?>
                       class="mt-0.5 h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                <div class="ml-3">
                    <span class="text-sm font-medium text-gray-900">Allow users to edit after submission</span>
                    <p class="help-text">Enable post-submission editing with optional time limits</p>
                </div>
            </label>
            
            <div id="editability-options" class="ml-3 space-y-5 <?php echo !($form['settings']['is_editable_after_submit'] ?? false) ? 'hidden' : ''; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="editable_days">Editable for (Days)</label>
                        <input type="number"
                               name="editable_days"
                               id="editable_days"
                               value="<?php echo $form['settings']['editable_days'] ?? ''; ?>"
                               min="1"
                               class="focus:ring-orange-500 focus:border-orange-500">
                        <p class="help-text">Number of days after submission during which edits are allowed</p>
                    </div>
                    
                    <div>
                        <label for="editable_until_date">Or, Editable Until</label>
                        <input type="date"
                               name="editable_until_date"
                               id="editable_until_date"
                               value="<?= !empty($form['settings']['editable_until_date']) ? format_datetime($form['settings']['editable_until_date'], 'Y-m-d') : '' ?>"
                               class="focus:ring-orange-500 focus:border-orange-500">
                        <p class="help-text">All submissions become locked after this date</p>
                    </div>
                </div>

                <div class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
                    <p>
                        You can set either limit or both. If both are set, the system applies the stricter rule:
                        editing closes at whichever limit is reached first.
                    </p>
                </div>

                <div id="editability-both-limits-warning" class="hidden rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status" aria-live="polite">
                    Both limits are currently set. Users will lose edit access at the earliest of the two limits.
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scoring Configuration -->
    <div class="card">
        <div class="card-header bg-gradient-to-r from-blue-50 to-primary-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="section-title">Scoring Configuration</h3>
                    <p class="body-text mt-0.5">Assign numeric scores to question answers for admin review and evaluation</p>
                </div>
                <a href="/admin/forms/<?= $form['uuid'] ?>/scoring" class="btn btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Configure Scoring
                </a>
            </div>
        </div>
    </div>
    
    <!-- Save Actions -->
    <div class="flex items-center justify-between pt-4">
        <a href="/admin/forms" class="btn btn-secondary">
            ← Back to Forms
        </a>
        <div class="flex items-center gap-3">
            <?php if ($form['status'] === 'draft' && !empty($form['active_version_id'])): ?>
                <button type="button" onclick="publishForm()" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                    Publish Form
                </button>
            <?php elseif ($form['status'] === 'published'): ?>
                <?php if ($can_unpublish_form): ?>
                    <button type="button" onclick="unpublishForm()" class="px-4 py-2 text-sm font-medium text-white bg-yellow-600 rounded-lg hover:bg-yellow-700 transition">
                        Unpublish Form
                    </button>
                <?php else: ?>
                    <button type="button" disabled class="px-4 py-2 text-sm font-medium text-white bg-gray-400 rounded-lg cursor-not-allowed opacity-80">
                        Unpublish Form
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">
                Save Settings
            </button>
        </div>
    </div>
</form>

<?php if (($form['status'] ?? '') === 'published' && !$can_unpublish_form): ?>
    <div class="mt-6 bg-amber-50 border border-amber-200 rounded-lg p-5">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-sm font-semibold text-amber-900">Unpublish is unavailable</h3>
                <div class="mt-2 text-sm text-amber-800">
                    <p>This form cannot be unpublished while it is linked to active program(s):</p>
                    <ul class="mt-2 ml-5 list-disc space-y-1">
                        <?php foreach ($active_program_links as $program): ?>
                            <li><strong><?= sanitize((string)($program['name'] ?? 'Unnamed Program')) ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="mt-2">Remove this form from those active programs, or unpublish/close those programs first.</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Hidden forms for publish/unpublish -->
<?php if ($form['status'] === 'draft' && !empty($form['active_version_id'])): ?>
    <form id="publishForm" method="POST" action="/admin/forms/<?= $form['uuid'] ?>/republish" style="display:none;">
        <?= csrf_field() ?>
    </form>
<?php elseif ($form['status'] === 'published'): ?>
    <form id="unpublishForm" method="POST" action="/admin/forms/<?= $form['uuid'] ?>/unpublish" style="display:none;">
        <?= csrf_field() ?>
    </form>
<?php endif; ?>

<!-- Danger Zone -->
<div class="mt-6 bg-white rounded-lg shadow-sm border border-red-200 overflow-hidden">
    <div class="bg-gradient-to-r from-red-50 to-pink-50 px-6 py-4 border-b border-red-200">
        <div class="flex items-center">
            <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-lg font-semibold text-red-900">Danger Zone</h3>
                <p class="text-sm text-red-700 mt-0.5">Permanently delete this form. This will soft-delete the form, preserving all data but hiding it completely. This action can be reversed only by database restore.</p>
            </div>
            <form method="POST" action="/admin/forms/<?php echo $form['uuid']; ?>/delete" onsubmit="return confirm('Are you sure you want to permanently delete this form? This action cannot be easily undone.');">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-danger">
                    Delete Form
                </button>
            </form>
        </div>
    </div>
</div>

<?php if ($can_manage_admins): ?>
<script src="<?= asset('/js/form-admins.js') ?>"></script>
<script>
    // Initialize form admins management
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof FormAdmins !== 'undefined') {
            window.formAdminsManager = new FormAdmins('<?= $form['uuid'] ?>', <?= $current_user['id'] ?>, <?= $is_owner ? 'true' : 'false' ?>);
        }
    });
</script>
<?php endif; ?>

<script>
    // Toggle editability options + guidance when both limits are set
    (function() {
        const editableToggle = document.getElementById('is_editable_after_submit');
        const editabilityOptions = document.getElementById('editability-options');
        const editableDaysInput = document.getElementById('editable_days');
        const editableUntilInput = document.getElementById('editable_until_date');
        const bothLimitsWarning = document.getElementById('editability-both-limits-warning');

        if (!editableToggle || !editabilityOptions || !editableDaysInput || !editableUntilInput || !bothLimitsWarning) {
            return;
        }

        function refreshEditabilityUi() {
            if (editableToggle.checked) {
                editabilityOptions.classList.remove('hidden');
            } else {
                editabilityOptions.classList.add('hidden');
                bothLimitsWarning.classList.add('hidden');
                return;
            }

            const hasEditableDays = editableDaysInput.value.trim() !== '';
            const hasEditableUntil = editableUntilInput.value.trim() !== '';

            if (hasEditableDays && hasEditableUntil) {
                bothLimitsWarning.classList.remove('hidden');
            } else {
                bothLimitsWarning.classList.add('hidden');
            }
        }

        editableToggle.addEventListener('change', refreshEditabilityUi);
        editableDaysInput.addEventListener('input', refreshEditabilityUi);
        editableUntilInput.addEventListener('change', refreshEditabilityUi);

        refreshEditabilityUi();
    })();

    // Universal client-side form validation (replaces browser tooltips with inline errors)
    (function() {
        var form = document.querySelector('form[action$="/settings"]');
        if (!form) return;

        // Resolve a friendly label for a field from its associated <label> element
        function fieldLabel(field) {
            var label = form.querySelector('label[for="' + field.id + '"]');
            if (label) {
                var clone = label.cloneNode(true);
                var span = clone.querySelector('span');
                if (span) span.remove();
                return clone.textContent.trim();
            }
            return 'This field';
        }

        // Convert HTML5 ValidityState into a human-readable message
        function friendlyMessage(field) {
            var v = field.validity;
            var label = fieldLabel(field);
            if (v.valueMissing) return label + ' is required.';
            if (v.rangeUnderflow) return label + ' must be ' + field.min + ' or more.';
            if (v.rangeOverflow) return label + ' must be ' + field.max + ' or less.';
            if (v.typeMismatch) return 'Please enter a valid value for ' + label + '.';
            if (v.tooShort) return label + ' must be at least ' + field.minLength + ' characters.';
            if (v.tooLong) return label + ' must be no more than ' + field.maxLength + ' characters.';
            if (v.patternMismatch) return 'Please enter a valid value for ' + label + '.';
            return label + ' is invalid.';
        }

        // Show an inline error below a field
        function showFieldError(field, message) {
            field.classList.add('border-red-400');
            field.classList.remove('border-gray-300');
            var err = document.createElement('p');
            err.className = 'field-error';
            err.setAttribute('role', 'alert');
            err.textContent = message;
            field.parentNode.insertBefore(err, field.nextSibling);
        }

        // Clear all previous inline errors
        function clearErrors() {
            form.querySelectorAll('.field-error').forEach(function(el) { el.remove(); });
            form.querySelectorAll('.border-red-400').forEach(function(el) {
                el.classList.remove('border-red-400');
                el.classList.add('border-gray-300');
            });
        }

        // Clear error on a field when user interacts with it
        form.addEventListener('input', function(e) {
            var field = e.target;
            if (field.classList.contains('border-red-400')) {
                field.classList.remove('border-red-400');
                field.classList.add('border-gray-300');
                var next = field.nextElementSibling;
                if (next && next.classList.contains('field-error')) next.remove();
            }
        });

        form.addEventListener('submit', function(e) {
            clearErrors();

            var errors = [];

            // Run native HTML5 constraint validation on all fields
            var fields = form.querySelectorAll('input, select, textarea');
            fields.forEach(function(field) {
                if (!field.checkValidity()) {
                    var msg = friendlyMessage(field);
                    showFieldError(field, msg);
                    errors.push(field);
                }
            });

            // Custom: submission deadline must be at least 1 hour from now
            var deadline = document.getElementById('submission_deadline');
            if (deadline && deadline.value && !deadline.classList.contains('border-red-400')) {
                var selected = new Date(deadline.value);
                var minimumTime = new Date(Date.now() + 60 * 60 * 1000);
                if (selected < minimumTime) {
                    showFieldError(deadline, 'Submission deadline must be at least 1 hour from now.');
                    errors.push(deadline);
                }
            }

            if (errors.length > 0) {
                e.preventDefault();
                errors[0].focus();
                errors[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    })();

    // Publish form function (republish a previously unpublished form)
    function publishForm() {
        if (confirm('Are you sure you want to publish this form? It will become visible to users.')) {
            document.getElementById('publishForm').submit();
        }
    }

    // Unpublish form function
    function unpublishForm() {
        if (confirm('Are you sure you want to unpublish this form? It will no longer be visible to users.')) {
            document.getElementById('unpublishForm').submit();
        }
    }
</script>
