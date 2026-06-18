<?php
/**
 * Create Language View
 */
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Add Language</h1>
        <p class="mt-2 text-sm text-gray-700">Configure a new language for the end-user interface</p>
    </div>
</div>

<form method="POST" action="/admin/languages">
    <?= csrf_field() ?>

    <!-- Language Details Card -->
    <div class="card mt-6">
        <div class="card-header bg-gradient-to-r from-primary-50 to-blue-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="section-title">Language Details</h3>
                    <p class="body-text mt-0.5">Basic information about the language</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="code">Language Code <span class="text-red-500">*</span></label>
                    <input type="text" id="code" name="code" required
                           placeholder="fr" maxlength="10"
                           pattern="[a-z]{2,10}(-[a-zA-Z]{2,10})?"
                           value="<?= sanitize($_POST['code'] ?? '') ?>">
                    <p class="help-text">ISO 639-1 code (e.g., fr, de, es, zh)</p>
                </div>
                <div>
                    <label for="direction">Text Direction <span class="text-red-500">*</span></label>
                    <select id="direction" name="direction" required>
                        <option value="ltr" <?= ($_POST['direction'] ?? 'ltr') === 'ltr' ? 'selected' : '' ?>>Left-to-Right (LTR)</option>
                        <option value="rtl" <?= ($_POST['direction'] ?? '') === 'rtl' ? 'selected' : '' ?>>Right-to-Left (RTL)</option>
                    </select>
                    <p class="help-text">Reading direction for this language</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="name">Language Name <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" required
                           placeholder="French" maxlength="100"
                           value="<?= sanitize($_POST['name'] ?? '') ?>">
                    <p class="help-text">English name (e.g., French, German, Spanish)</p>
                </div>
                <div>
                    <label for="native_name">Native Name <span class="text-red-500">*</span></label>
                    <input type="text" id="native_name" name="native_name" required
                           placeholder="Français" maxlength="100"
                           value="<?= sanitize($_POST['native_name'] ?? '') ?>">
                    <p class="help-text">Name in the language itself (e.g., Français)</p>
                </div>
            </div>

            <div>
                <div class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                               <?= !empty($_POST['is_active']) ? 'checked' : '' ?>>
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="is_active">Activate immediately</label>
                        <p class="text-gray-500">Make this language available to end users right away</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Translations Card -->
    <div class="card mt-6">
        <div class="card-header bg-gradient-to-r from-gray-50 to-slate-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="section-title">Translations</h3>
                    <p class="body-text mt-0.5">Paste your translation JSON below. <a href="/admin/languages/template" class="text-primary-600 hover:text-primary-700 font-medium">Download the English template</a> as a reference.</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <textarea
                id="translations"
                name="translations"
                rows="20"
                class="font-mono text-sm"
                placeholder="Paste your translation JSON here…"
            ><?= sanitize($_POST['translations'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3 mt-6">
        <a href="/admin/languages" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Add Language</button>
    </div>
</form>
