<?php
/**
 * AI Admin Settings — Super Admin configuration page
 *
 * @var array{enabled: bool, provider: string, base_url: string, api_key: string, model: string, max_tokens: ?int, site_url: string, site_name: string, has_api_key: bool} $settings
 * @var array<int, array{label: string}> $breadcrumbs
 */
$page_title = 'AI Settings';

/** Default base URLs per provider (duplicated here for JS) */
$provider_urls_json = json_encode(AI_PROVIDER_BASE_URLS);
?>

<div class="sm:flex sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="page-title">AI Settings</h1>
        <p class="mt-2 text-sm text-gray-600">Configure the AI provider used for submission summarization.</p>
    </div>
</div>

<div class="card p-6 max-w-2xl">
    <form method="POST" action="/admin/ai-settings" class="space-y-6">
        <?= csrf_field() ?>

        <!-- Enable/Disable Toggle -->
        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
            <div>
                <p class="text-sm font-semibold text-gray-900">Enable AI Features</p>
                <p class="text-xs text-gray-500 mt-0.5">When disabled, all AI endpoints return 403 and the summary panel is hidden.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="ai_enabled" value="1"
                       <?= $settings['enabled'] ? 'checked' : '' ?>
                       class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
            </label>
        </div>

        <!-- Provider -->
        <div>
            <label for="ai_provider" class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
            <select name="ai_provider" id="ai_provider"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                <option value="openrouter" <?= $settings['provider'] === 'openrouter' ? 'selected' : '' ?>>OpenRouter</option>
                <option value="openai" <?= $settings['provider'] === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                <option value="ollama" <?= $settings['provider'] === 'ollama' ? 'selected' : '' ?>>Ollama (Local)</option>
                <option value="custom" <?= $settings['provider'] === 'custom' ? 'selected' : '' ?>>Custom</option>
            </select>
            <p class="mt-1 text-xs text-gray-500">Selecting a provider auto-fills the base URL. Choose "Custom" for other OpenAI-compatible APIs.</p>
        </div>

        <!-- Base URL -->
        <div>
            <label for="ai_base_url" class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
            <input type="url" name="ai_base_url" id="ai_base_url"
                   value="<?= sanitize($settings['base_url']) ?>"
                   placeholder="https://openrouter.ai/api/v1"
                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" />
            <p class="mt-1 text-xs text-gray-500">API base URL (no trailing slash). The client appends <code>/chat/completions</code>.</p>
        </div>

        <!-- API Key -->
        <div>
            <label for="ai_api_key" class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
            <?php if ($settings['has_api_key']): ?>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        ✓ Key configured
                    </span>
                    <button type="submit" name="clear_api_key" value="1"
                            class="text-xs text-red-600 hover:text-red-800 underline"
                            onclick="return window.confirm('Clear the stored API key?');">
                        Clear key
                    </button>
                </div>
            <?php endif; ?>
            <input type="password" name="ai_api_key" id="ai_api_key"
                   placeholder="<?= $settings['has_api_key'] ? '••••••••  (leave blank to keep current key)' : 'Enter API key' ?>"
                   autocomplete="off"
                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" />
            <p class="mt-1 text-xs text-gray-500">Encrypted at rest. Leave blank to keep the current key. Not required for Ollama.</p>
        </div>

        <!-- Model -->
        <div>
            <label for="ai_model" class="block text-sm font-medium text-gray-700 mb-1">Model</label>
            <input type="text" name="ai_model" id="ai_model"
                   value="<?= sanitize($settings['model']) ?>"
                   placeholder="anthropic/claude-sonnet-4"
                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" />
            <p class="mt-1 text-xs text-gray-500">Model identifier. For OpenRouter use the full path (e.g. <code>anthropic/claude-sonnet-4</code>).</p>
        </div>

        <!-- Max Tokens -->
        <div>
            <label for="ai_max_tokens" class="block text-sm font-medium text-gray-700 mb-1">Max Response Tokens</label>
            <input type="number" name="ai_max_tokens" id="ai_max_tokens"
                   value="<?= $settings['max_tokens'] !== null ? (int)$settings['max_tokens'] : '' ?>"
                   placeholder="<?= LLM_DEFAULT_MAX_TOKENS ?>"
                   min="1" max="16384"
                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" />
            <p class="mt-1 text-xs text-gray-500">Maximum number of tokens in the AI response. Leave blank for the default (<?= LLM_DEFAULT_MAX_TOKENS ?>). Different providers may have different limits.</p>
        </div>

        <!-- OpenRouter-specific fields -->
        <div id="openrouter-fields" class="space-y-4 <?= $settings['provider'] === 'openrouter' ? '' : 'hidden' ?>">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-xs text-blue-800 font-medium">OpenRouter-specific settings</p>
            </div>

            <div>
                <label for="ai_site_url" class="block text-sm font-medium text-gray-700 mb-1">Site URL</label>
                <input type="url" name="ai_site_url" id="ai_site_url"
                       value="<?= sanitize($settings['site_url']) ?>"
                       placeholder="https://yourapp.example.com"
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" />
                <p class="mt-1 text-xs text-gray-500">Sent as <code>HTTP-Referer</code> header. Optional — helps OpenRouter track usage.</p>
            </div>
            <div>
                <label for="ai_site_name" class="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
                <input type="text" name="ai_site_name" id="ai_site_name"
                       value="<?= sanitize($settings['site_name']) ?>"
                       placeholder="My App"
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" />
                <p class="mt-1 text-xs text-gray-500">Sent as <code>X-Title</code> header. Optional.</p>
            </div>
        </div>

        <!-- Info box -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-800">
                <strong>Note:</strong> AI summaries are generated on-demand by admins and reviewers from the review submission page.
                No data is sent to the AI provider automatically. No personally identifiable information (names, emails, user IDs) is included in prompts.
            </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <button type="button" id="test-connection-btn"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Test Connection
            </button>
            <button type="submit" class="btn btn-primary">
                Save Settings
            </button>
        </div>
    </form>
</div>

<!-- Test result area -->
<div id="test-result" class="hidden card p-4 mt-6 max-w-2xl"></div>

<script type="module">
    import { getCsrfToken, toast } from '<?= asset('/js/common.js') ?>';

    // Provider → base URL auto-fill
    const providerUrls = <?= $provider_urls_json ?>;
    const providerSelect = document.getElementById('ai_provider');
    const baseUrlInput = document.getElementById('ai_base_url');
    const openrouterFields = document.getElementById('openrouter-fields');

    function syncProviderUI(forceUrl = false) {
        const provider = providerSelect.value;
        const defaultUrl = providerUrls[provider] || '';

        if (forceUrl) {
            // User explicitly changed the provider — always update base URL
            baseUrlInput.value = defaultUrl;
        } else {
            // Initial page load — only fill if empty or matches a known default
            const currentUrl = baseUrlInput.value.trim();
            const isKnownDefault = Object.values(providerUrls).includes(currentUrl);
            if (currentUrl === '' || isKnownDefault) {
                baseUrlInput.value = defaultUrl;
            }
        }

        // Toggle OpenRouter fields
        openrouterFields.classList.toggle('hidden', provider !== 'openrouter');
    }

    // On provider change: always overwrite base URL with the provider default
    providerSelect.addEventListener('change', () => syncProviderUI(true));
    // On initial load: preserve any custom URL already saved
    syncProviderUI(false);

    // Test Connection
    const testBtn = document.getElementById('test-connection-btn');
    const testResult = document.getElementById('test-result');

    testBtn.addEventListener('click', async () => {
        testBtn.disabled = true;
        testBtn.innerHTML = `
            <svg class="animate-spin w-4 h-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Testing…
        `;
        testResult.classList.add('hidden');

        try {
            const response = await fetch('/admin/ai-settings/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    provider:  document.getElementById('ai_provider').value,
                    base_url:  document.getElementById('ai_base_url').value,
                    api_key:   document.getElementById('ai_api_key').value,
                    model:     document.getElementById('ai_model').value,
                    site_url:  document.getElementById('ai_site_url').value,
                    site_name: document.getElementById('ai_site_name').value,
                }),
            });
            const data = await response.json();

            testResult.classList.remove('hidden');
            if (data.success) {
                testResult.className = 'card p-4 mt-6 max-w-2xl bg-green-50 border border-green-200';
                testResult.innerHTML = `
                    <div class="flex items-center text-sm text-green-800">
                        <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <span><strong>Connection successful!</strong> Model: ${data.model || 'unknown'}</span>
                    </div>
                `;
            } else {
                testResult.className = 'card p-4 mt-6 max-w-2xl bg-red-50 border border-red-200';
                testResult.innerHTML = `
                    <div class="flex items-center text-sm text-red-800">
                        <svg class="w-5 h-5 mr-2 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <span><strong>Connection failed:</strong> ${data.error || 'Unknown error'}</span>
                    </div>
                `;
            }
        } catch (err) {
            testResult.classList.remove('hidden');
            testResult.className = 'card p-4 mt-6 max-w-2xl bg-red-50 border border-red-200';
            testResult.innerHTML = `
                <div class="text-sm text-red-800">
                    <strong>Request failed:</strong> ${err.message || 'Network error'}
                </div>
            `;
        } finally {
            testBtn.disabled = false;
            testBtn.innerHTML = `
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Test Connection
            `;
        }
    });
</script>
