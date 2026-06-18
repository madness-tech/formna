<?php
/**
 * Languages Management - Index
 *
 * Variables injected by the controller via extract():
 * $languages          list<array<string, mixed>>
 * $bundled_available  list<array<string, mixed>> – bundled files not yet in DB
 */
$languages         = $languages ?? [];
$bundled_available = $bundled_available ?? [];
$total_keys        = count(i18n_get_all_keys());
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Languages</h1>
        <p class="mt-2 text-sm text-gray-700">Manage languages and translations for the end-user interface.</p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none flex items-center gap-2">
        <a href="/admin/languages/template" class="btn btn-secondary">
            <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Template
        </a>
        <a href="/admin/languages/create" class="btn btn-primary">
            <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Add Language
        </a>
    </div>
</div>

<!-- Languages Table -->
<div class="card mt-6">
    <?php if (empty($languages)): ?>
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">No languages configured</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by adding your first language.</p>
            <div class="mt-6">
                <a href="/admin/languages/create" class="btn btn-primary">
                    <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add Language
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Language</th>
                        <th scope="col">Direction</th>
                        <th scope="col">Translation Coverage</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($languages as $lang): ?>
                        <?php
                        $missing = $lang['missing_count'] ?? $total_keys;
                        $translated = $total_keys - $missing;
                        $pct = $total_keys > 0 ? (int) round(($translated / $total_keys) * 100) : 0;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 inline-flex items-center justify-center h-9 w-9 rounded-lg bg-primary-50 text-primary-700 text-xs font-bold uppercase">
                                        <?= sanitize($lang['code']) ?>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= sanitize($lang['name']) ?>
                                            <?php if ($lang['is_system']): ?>
                                                <span class="ml-1 badge badge-gray">System</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="help-text mt-0"><?= sanitize($lang['native_name']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="badge <?= $lang['direction'] === 'rtl' ? 'badge-purple' : 'badge-gray' ?>">
                                    <?= strtoupper(sanitize($lang['direction'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-24 bg-gray-200 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full <?= $pct === 100 ? 'bg-green-500' : ($pct > 50 ? 'bg-yellow-500' : 'bg-red-500') ?>"
                                             style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600 whitespace-nowrap"><?= $translated ?>/<?= $total_keys ?> (<?= $pct ?>%)</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php if ($lang['is_default']): ?>
                                    <span class="badge badge-green">Default</span>
                                <?php elseif ($lang['is_active']): ?>
                                    <span class="badge badge-blue">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <!-- Edit -->
                                    <a href="/admin/languages/<?= $lang['id'] ?>/edit"
                                       class="inline-flex items-center p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                                       title="Edit">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                    </a>

                                    <!-- Toggle Active -->
                                    <?php if (!($lang['is_active'] && $lang['is_default'])): ?>
                                        <form method="POST" action="/admin/languages/<?= $lang['id'] ?>/toggle" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="inline-flex items-center p-1.5 rounded-lg transition-colors <?= $lang['is_active'] ? 'text-gray-400 hover:text-yellow-600 hover:bg-yellow-50' : 'text-gray-400 hover:text-green-600 hover:bg-green-50' ?>"
                                                    title="<?= $lang['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <?php if ($lang['is_active']): ?>
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                <?php else: ?>
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Set Default -->
                                    <?php if ($lang['is_active'] && !$lang['is_default']): ?>
                                        <form method="POST" action="/admin/languages/<?= $lang['id'] ?>/default" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="inline-flex items-center p-1.5 text-gray-400 hover:text-yellow-500 hover:bg-yellow-50 rounded-lg transition-colors"
                                                    title="Set as Default">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Delete -->
                                    <?php if (!$lang['is_system'] && !$lang['is_default']): ?>
                                        <form method="POST" action="/admin/languages/<?= $lang['id'] ?>/delete" class="inline"
                                              onsubmit="return confirm('Are you sure you want to delete this language?')">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="inline-flex items-center p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                    title="Delete">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($languages)): ?>
<p class="mt-4 text-xs text-gray-500">
    System languages cannot be deleted but can be deactivated. The default language is used when no user preference is set.
</p>
<?php endif; ?>

<?php if (!empty($bundled_available)): ?>
<!-- Bundled Languages Panel -->
<div class="mt-8">
    <h2 class="text-base font-semibold text-gray-900">Bundled Languages</h2>
    <p class="mt-1 text-sm text-gray-500">
        These language files are included with the application but have not yet been imported into the database.
        Import a language to make it available for activation, or remove the file from the server if you don't need it.
    </p>

    <div class="card mt-4 divide-y divide-gray-100">
        <?php foreach ($bundled_available as $bl): ?>
            <div class="flex items-center justify-between px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 inline-flex items-center justify-center h-9 w-9 rounded-lg bg-gray-100 text-gray-500 text-xs font-bold uppercase">
                        <?= sanitize($bl['code']) ?>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900"><?= sanitize($bl['name']) ?></p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <?= $bl['direction'] === 'rtl' ? 'Right-to-Left (RTL)' : 'Left-to-Right (LTR)' ?>
                            &middot; <span class="font-mono"><?= sanitize($bl['code']) ?>.json</span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Import -->
                    <form method="POST" action="/admin/languages/import-bundled">
                        <?= csrf_field() ?>
                        <input type="hidden" name="code" value="<?= sanitize($bl['code']) ?>">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <svg class="-ml-0.5 mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            Import
                        </button>
                    </form>
                    <!-- Remove file from server -->
                    <form method="POST" action="/admin/languages/delete-bundled-file"
                          onsubmit="return confirm('Permanently delete <?= sanitize($bl['name']) ?> (<?= sanitize($bl['code']) ?>.json) from the server? This cannot be undone.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="code" value="<?= sanitize($bl['code']) ?>">
                        <button type="submit"
                                class="btn btn-secondary btn-sm text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                                title="Remove file from server">
                            <svg class="-ml-0.5 mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            Remove file
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
