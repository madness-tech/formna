<?php
/**
 * @var array<string,array<string,mixed>> $triggers Email triggers configuration
 * @var array<string,array<string,mixed>> $template_map Template mapping array
 */
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Email Templates</h1>
        <p class="mt-2 text-sm text-gray-700">Configure automated email notifications sent across the platform</p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <form method="POST" action="/admin/email-templates/test" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary">
                <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
                Send Test Email
            </button>
        </form>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6">
        <?php foreach ($triggers as $trigger_key => $trigger_info): ?>
            <?php $template = $template_map[$trigger_key] ?? null; ?>

            <div class="card">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <h3 class="section-title">
                                    <?= sanitize($trigger_info['label']) ?>
                                </h3>
                                <?php if ($template): ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= $template['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                        <?= $template['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-yellow">
                                        Not Configured
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="body-text mt-1"><?= sanitize($trigger_info['description']) ?></p>
                        </div>

                        <?php if ($template): ?>
                            <button
                                type="button"
                                onclick="toggleTemplate(<?= $template['id'] ?>)"
                                class="text-sm text-primary-600 hover:text-primary-800"
                            >
                                <?= $template['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="/admin/email-templates/save" class="space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="trigger" value="<?= $trigger_key ?>">

                        <div>
                            <label>Subject Line</label>
                            <input
                                type="text"
                                name="subject"
                                placeholder="e.g., Submission Received - {form_name}"
                                value="<?= sanitize($template['subject'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div>
                            <label>Email Body</label>
                            <textarea
                                name="body"
                                rows="8"
                                class="font-mono"
                                placeholder="Dear {user_name},&#10;&#10;Your submission has been received..."
                                required
                            ><?= sanitize($template['body'] ?? '') ?></textarea>

                            <div class="mt-2">
                                <p class="text-xs font-medium text-gray-700 mb-1">Available Placeholders:</p>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($trigger_info['placeholders'] as $placeholder): ?>
                                        <code class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded cursor-pointer hover:bg-gray-200"
                                              onclick="copyToClipboard('<?= $placeholder ?>')"
                                              title="Click to copy">
                                            <?= $placeholder ?>
                                        </code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($trigger_info['supports_delay']): ?>
                            <div>
                                <label>Delay (days)</label>
                                <input
                                    type="number"
                                    name="delay_days"
                                    min="1"
                                    max="365"
                                    class="w-32"
                                    placeholder="e.g., 3"
                                    value="<?= $template['delay_days'] ?? '' ?>"
                                >
                                <p class="help-text">Number of days to wait before sending</p>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                name="is_active"
                                id="active_<?= $trigger_key ?>"
                                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                <?= ($template['is_active'] ?? true) ? 'checked' : '' ?>
                            >
                            <label for="active_<?= $trigger_key ?>" class="ml-2">
                                Enable this email template
                            </label>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                            <div>
                                    <?php if ($template): ?>
                                        <button
                                            type="button"
                                            onclick="resetTemplate('<?= $trigger_key ?>')"
                                            class="body-text hover:text-gray-800"
                                        >
                                            Reset to Default
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Save Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script type="module">
import { api, toast } from '<?= asset('/js/common.js') ?>';

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        toast('success', 'Copied: ' + text);
    });
}

async function toggleTemplate(id) {
    if (!confirm('Toggle template status?')) return;

    try {
        const response = await api('/admin/email-templates/toggle', 'POST', { id });
        if (response.success) {
            location.reload();
        }
    } catch (error) {
        toast('error', 'Failed to toggle template');
    }
}

function resetTemplate(trigger) {
    if (!confirm('Reset this template to its default content? Your customizations will be lost.')) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/email-templates/reset';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_csrf';
    csrf.value = document.querySelector('meta[name="csrf-token"]').content;

    const triggerInput = document.createElement('input');
    triggerInput.type = 'hidden';
    triggerInput.name = 'trigger';
    triggerInput.value = trigger;

    form.appendChild(csrf);
    form.appendChild(triggerInput);
    document.body.appendChild(form);
    form.submit();
}

window.copyToClipboard = copyToClipboard;
window.toggleTemplate = toggleTemplate;
window.resetTemplate = resetTemplate;
</script>
