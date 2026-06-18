<?php
/**
 * Edit Language View
 *
 * Variables injected by the controller via extract():
 * $language              array<string, mixed>
 * $translations_json     string
 * $total_keys            int
 * $translated_count      int
 * $has_bundled_file      bool   – true when a bundled UI .json exists for this language
 * $help_translations_json string – raw JSON of help translations (DB if available, otherwise file)
 * $has_bundled_help_file  bool   – true when a bundled help .json exists on disk for this language
 * $help_total_keys        int
 * $help_translated        int
 */
$language               = $language ?? [];
$translations_json      = $translations_json ?? '';
$total_keys             = $total_keys ?? 0;
$translated_count       = $translated_count ?? 0;
$has_bundled_file       = $has_bundled_file ?? false;
$help_translations_json = $help_translations_json ?? '';
$has_bundled_help_file  = $has_bundled_help_file ?? false;
$help_total_keys        = $help_total_keys ?? 0;
$help_translated        = $help_translated ?? 0;
$missing_count    = $total_keys - $translated_count;
$pct              = $total_keys > 0 ? (int) round(($translated_count / $total_keys) * 100) : 0;
$help_missing     = $help_total_keys - $help_translated;
$help_pct         = $help_total_keys > 0 ? (int) round(($help_translated / $help_total_keys) * 100) : 0;
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Edit Language: <?= sanitize($language['name']) ?></h1>
        <p class="mt-2 text-sm text-gray-700">
            <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded"><?= sanitize($language['code']) ?></span>
            <?php if ($language['is_system']): ?>
                <span class="badge badge-gray ml-1">System</span>
            <?php endif; ?>
            <?php if ($language['is_default']): ?>
                <span class="badge badge-green ml-1">Default</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <a href="/admin/languages" class="btn btn-secondary">
            <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
            Back
        </a>
    </div>
</div>

<!-- Language Details Card -->
<form method="POST" action="/admin/languages/<?= (int)$language['id'] ?>">
    <?= csrf_field() ?>

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
                    <p class="body-text mt-0.5">Update the language name, native name, or text direction</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="name">Language Name <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" required maxlength="100"
                           value="<?= sanitize($language['name']) ?>">
                </div>
                <div>
                    <label for="native_name">Native Name <span class="text-red-500">*</span></label>
                    <input type="text" id="native_name" name="native_name" required maxlength="100"
                           value="<?= sanitize($language['native_name']) ?>">
                </div>
                <div>
                    <label for="direction">Text Direction</label>
                    <select id="direction" name="direction">
                        <option value="ltr" <?= $language['direction'] === 'ltr' ? 'selected' : '' ?>>Left-to-Right (LTR)</option>
                        <option value="rtl" <?= $language['direction'] === 'rtl' ? 'selected' : '' ?>>Right-to-Left (RTL)</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 mt-5">
                <button type="submit" class="btn btn-primary">Save Details</button>
            </div>
        </div>
    </div>
</form>

<!-- UI Translations Card -->
<form method="POST" action="/admin/languages/<?= (int)$language['id'] ?>/translations">
    <?= csrf_field() ?>

    <div class="card mt-6">
        <div class="card-header bg-gradient-to-r from-gray-50 to-slate-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="section-title">UI Translations</h3>
                        <p class="body-text mt-0.5">Manage the JSON translation strings for the user interface</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500"><?= $translated_count ?>/<?= $total_keys ?></span>
                    <div class="w-24 bg-gray-200 rounded-full h-1.5">
                        <div class="h-1.5 rounded-full <?= $pct === 100 ? 'bg-green-500' : ($pct > 50 ? 'bg-yellow-500' : 'bg-red-500') ?>"
                             style="width: <?= $pct ?>%"></div>
                    </div>
                    <span class="text-sm font-medium <?= $pct === 100 ? 'text-green-600' : ($pct > 50 ? 'text-yellow-600' : 'text-red-600') ?>"><?= $pct ?>%</span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <?php if ($missing_count > 0): ?>
                <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-sm text-yellow-800">
                        <strong><?= $missing_count ?></strong> translation key<?= $missing_count != 1 ? 's are' : ' is' ?> missing and will fall back to English.
                    </p>
                </div>
            <?php endif; ?>

            <textarea
                id="translations"
                name="translations"
                rows="25"
                class="font-mono text-sm"
                placeholder="Paste your UI translation JSON here…"
            ><?= sanitize($translations_json) ?></textarea>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200 mt-4">
                <a href="/admin/languages/template" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                    <svg class="inline h-4 w-4 mr-1 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Download English UI Template
                </a>
                <div class="flex items-center gap-2">
                    <?php if ($has_bundled_file): ?>
                        <button type="button"
                                class="btn btn-secondary"
                                onclick="document.getElementById('restore-from-file-form').submit()"
                                title="Overwrite saved UI translations with the bundled file defaults">
                            <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Restore from file
                        </button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">Update UI Translations</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php if ($has_bundled_file): ?>
<form id="restore-from-file-form"
      method="POST"
      action="/admin/languages/<?= (int)$language['id'] ?>/restore-from-file"
      onsubmit="return confirm('Restore bundled file defaults? This will overwrite your currently saved UI translations and cannot be undone.')">
    <?= csrf_field() ?>
</form>
<?php endif; ?>

<!-- Help Translations Card -->
<form method="POST" action="/admin/languages/<?= (int)$language['id'] ?>/help-translations">
    <?= csrf_field() ?>

    <div class="card mt-6">
        <div class="card-header bg-gradient-to-r from-emerald-50 to-teal-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="section-title">Help Center Translations</h3>
                        <p class="body-text mt-0.5">Manage help content translations. Edits are saved to the database and overlay the bundled file at runtime.</p>
                    </div>
                </div>
                <?php if ($help_total_keys > 0 && $help_translations_json !== ''): ?>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500"><?= $help_translated ?>/<?= $help_total_keys ?></span>
                    <div class="w-24 bg-gray-200 rounded-full h-1.5">
                        <div class="h-1.5 rounded-full <?= $help_pct === 100 ? 'bg-green-500' : ($help_pct > 50 ? 'bg-yellow-500' : 'bg-red-500') ?>"
                             style="width: <?= $help_pct ?>%"></div>
                    </div>
                    <span class="text-sm font-medium <?= $help_pct === 100 ? 'text-green-600' : ($help_pct > 50 ? 'text-yellow-600' : 'text-red-600') ?>"><?= $help_pct ?>%</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-6">
            <?php if (!$has_bundled_help_file && $help_translations_json === ''): ?>
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <strong>No help content exists for this language.</strong>
                        Paste translated help JSON below and save to make the Help Center available.
                        Use the English help template as a starting point.
                    </p>
                </div>
            <?php elseif ($help_missing > 0): ?>
                <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-sm text-yellow-800">
                        <strong><?= $help_missing ?></strong> help translation key<?= $help_missing != 1 ? 's are' : ' is' ?> missing compared to the English source.
                    </p>
                </div>
            <?php endif; ?>

            <textarea
                id="help_translations"
                name="help_translations"
                rows="20"
                class="font-mono text-sm"
                placeholder="Paste your help translation JSON here… Download the English template for reference."
            ><?= sanitize($help_translations_json) ?></textarea>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200 mt-4">
                <a href="/admin/languages/help-template" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                    <svg class="inline h-4 w-4 mr-1 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Download English Help Template
                </a>
                <div class="flex items-center gap-2">
                    <?php if ($has_bundled_help_file): ?>
                        <button type="button"
                                class="btn btn-secondary"
                                onclick="document.getElementById('restore-help-from-file-form').submit()"
                                title="Overwrite saved help translations with the bundled file defaults">
                            <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Restore from file
                        </button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">Update Help Translations</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php if ($has_bundled_help_file): ?>
<form id="restore-help-from-file-form"
      method="POST"
      action="/admin/languages/<?= (int)$language['id'] ?>/restore-help-from-file"
      onsubmit="return confirm('Restore bundled help file defaults? This will overwrite your currently saved help translations and cannot be undone.')">
    <?= csrf_field() ?>
</form>
<?php endif; ?>
