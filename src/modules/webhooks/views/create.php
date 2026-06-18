<?php
/**
 * Create Webhook Form (Super Admin)
 *
 * @var array<int, array<string, mixed>> $forms List of available forms
 */
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Create Webhook</h1>
        <p class="mt-2 text-sm text-gray-700">Configure a new webhook endpoint for form submissions</p>
    </div>
</div>

<!-- Form Card -->
<div class="card mt-6 p-6">
        <form method="POST" action="/admin/webhooks">
            <?= csrf_field() ?>
            
            <!-- Webhook Name -->
            <div class="mb-6">
                <label for="name">
                    Webhook Name <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       id="name"
                       name="name"
                       required
                       value="<?= sanitize($old['name'] ?? '') ?>"
                       placeholder="e.g., Slack Notifications">
                <p class="help-text">A descriptive name for this webhook</p>
            </div>
            
            <!-- Webhook URL -->
            <div class="mb-6">
                <label for="url">
                    Webhook URL <span class="text-red-500">*</span>
                </label>
                <input type="url"
                       id="url"
                       name="url"
                       required
                       value="<?= sanitize($old['url'] ?? '') ?>"
                       class="input-mono"
                       placeholder="https://api.example.com/webhooks">
                <p class="help-text">Endpoint URL that will receive webhook POST requests</p>
            </div>
            
            <!-- Bearer Token -->
            <div class="mb-6">
                <label for="bearer_token">
                    Bearer Token <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       id="bearer_token"
                       name="bearer_token"
                       required
                       value="<?= sanitize($old['bearer_token'] ?? '') ?>"
                       class="input-mono"
                       placeholder="your_secret_token_here">
                <p class="help-text">Will be sent as "Authorization: Bearer TOKEN" header (encrypted in database)</p>
            </div>
            
            <!-- Include User Data Toggle -->
            <div class="mb-6">
                <div class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="checkbox"
                               id="include_user_data"
                               name="include_user_data"
                               <?= isset($old['include_user_data']) ? 'checked' : (!isset($old['name']) ? 'checked' : '') ?>
                               class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="include_user_data">Include user data (name, email) in payload</label>
                        <p class="text-gray-500">If unchecked, only user UUID will be sent (more private)</p>
                    </div>
                </div>
            </div>
            
            <!-- Active Toggle -->
            <div class="mb-6">
                <div class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="checkbox"
                               id="is_active"
                               name="is_active"
                               <?= isset($old['is_active']) ? 'checked' : (!isset($old['name']) ? 'checked' : '') ?>
                               class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="is_active">Active</label>
                        <p class="text-gray-500">Webhook will fire immediately when submissions are received</p>
                    </div>
                </div>
            </div>
            
            <!-- Form Selection -->
            <div class="mb-6">
                <label for="form_id">
                    Attach to Form (Optional)
                </label>
                <select id="form_id"
                        name="form_id">
                    <option value="">-- None (attach later) --</option>
                    <?php foreach ($forms as $form): ?>
                        <option value="<?= sanitize($form['id']) ?>" <?= isset($old['form_id']) && $old['form_id'] == $form['id'] ? 'selected' : '' ?>>
                            <?= sanitize($form['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="help-text">You can attach/detach webhooks from forms later</p>
            </div>
            
        <!-- Submit Buttons -->
        <div class="flex gap-3 pt-4 border-t border-gray-200 mt-6">
            <button type="submit"
                    class="btn btn-primary">
                Create Webhook
            </button>
            <a href="/admin/webhooks"
               class="inline-flex justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>
