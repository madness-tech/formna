<?php
/**
 * Edit Webhook Form (Super Admin)
 *
 * @var array<string, mixed> $webhook Webhook data
 * @var array<int, array<string, mixed>> $forms List of available forms
 * @var array<string, mixed>|null $attached_form Currently attached form
 */
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Edit Webhook</h1>
        <p class="mt-2 text-sm text-gray-700">Update webhook configuration and settings</p>
    </div>
</div>

<!-- Form Card -->
<div class="card mt-6 p-6">
        <form method="POST" action="/admin/webhooks/<?= sanitize($webhook['id']) ?>">
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
                       value="<?= sanitize($webhook['name']) ?>">
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
                       value="<?= sanitize($webhook['url']) ?>"
                       class="input-mono">
                <p class="help-text">Endpoint URL that will receive webhook POST requests</p>
            </div>
            
            <!-- Bearer Token -->
            <div class="mb-6">
                <label for="bearer_token">
                    Bearer Token
                </label>
                <input type="text"
                       id="bearer_token"
                       name="bearer_token"
                       class="input-mono"
                       placeholder="Leave blank to keep current token">
                <p class="help-text">Leave blank to keep existing token, or enter new token to update</p>
            </div>
            
            <!-- Include User Data Toggle -->
            <div class="mb-6">
                <div class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="checkbox"
                               id="include_user_data"
                               name="include_user_data"
                               <?= $webhook['include_user_data'] ? 'checked' : '' ?>
                               class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="include_user_data">Include user data (name, email) in payload</label>
                        <p class="text-gray-500">If unchecked, only user UUID will be sent</p>
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
                               <?= $webhook['is_active'] ? 'checked' : '' ?>
                               class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="is_active">Active</label>
                        <p class="text-gray-500">Webhook will fire when submissions are received</p>
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
                    <option value="">-- None --</option>
                    <?php foreach ($forms as $form): ?>
                        <option value="<?= sanitize($form['id']) ?>"
                                <?= $attached_form && $attached_form['id'] == $form['id'] ? 'selected' : '' ?>>
                            <?= sanitize($form['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
        <!-- Submit Buttons -->
        <div class="flex gap-3 pt-4 border-t border-gray-200 mt-6">
            <button type="submit"
                    class="btn btn-primary">
                Update Webhook
            </button>
            <a href="/admin/webhooks"
               class="inline-flex justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>
