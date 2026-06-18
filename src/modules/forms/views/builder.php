<!-- Form Builder -->
<?php
// SECURITY FIX: GET requests are now idempotent - no automatic version creation.
// Users must explicitly create a draft version via POST request.

// Defensive initialization for PHPStan - these should always be set by the controller
$form = $form ?? null;
$version = $version ?? null;
$active_version = $active_version ?? null;
$questions = $questions ?? [];
$user_draft_count = $user_draft_count ?? 0;

if (!$form) {
    flash('error', 'Form data not available.');
    redirect('/admin/forms');
}
?>

<?php if (!$version): ?>
    <!-- No Draft Version - Show Creation Prompt -->
    <div class="max-w-3xl mx-auto mt-12">
        <div class="card p-8">
            <div class="text-center">
                <svg class="mx-auto h-16 w-16 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <h2 class="page-title mt-4"><?php echo sanitize($form['name']); ?></h2>
                
                <?php if ($active_version): ?>
                    <p class="body-text mt-2">This form has an active published version (v<?php echo $active_version['version_number']; ?>).</p>
                    <p class="body-text mt-1">To make changes, create a new draft version below.</p>
                    
                    <div class="mt-6">
                        <form method="POST" action="/admin/forms/<?php echo $form['uuid']; ?>/create-draft" class="inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-primary">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Create Draft Version
                            </button>
                        </form>
                    </div>
                    
                    <div class="mt-4">
                        <a href="/admin/forms/<?php echo $form['uuid']; ?>/preview" class="text-sm text-primary-600 hover:text-primary-500">
                            Preview published version
                        </a>
                    </div>
                <?php else: ?>
                    <p class="body-text mt-2">This form has no versions yet.</p>
                    <p class="body-text mt-1">Create the first version to start building your form.</p>
                    
                    <div class="mt-6">
                        <form method="POST" action="/admin/forms/<?php echo $form['uuid']; ?>/create-draft" class="inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-primary">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Create First Version
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <a href="/admin/forms/<?php echo $form['uuid']; ?>/settings" class="body-text hover:text-gray-500">
                        ← Back to Form Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Loading overlay — removed by form-builder.js init() -->
    <div id="builder-loading" class="flex items-center justify-center py-24">
        <div class="text-center">
            <svg class="animate-spin h-8 w-8 text-primary-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm text-gray-500">Loading form builder…</p>
        </div>
    </div>

    <!-- Draft Version Exists - Show Builder (hidden until JS ready) -->
    <div id="builder-content" class="hidden">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="page-title"><?php echo sanitize($form['name']); ?></h1>
            <p class="mt-1 text-sm text-gray-500">Version <?php echo $version['version_number']; ?> - <?php echo ucfirst($version['status']); ?></p>
        </div>
        <div class="flex items-center gap-3">
        <a href="/admin/forms/<?php echo $form['uuid']; ?>/preview" target="_blank" class="btn btn-secondary">
            <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Preview
        </a>
        <a href="/admin/forms/<?php echo $form['uuid']; ?>/settings" class="btn btn-secondary">
            <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Settings
        </a>
        <?php if ($version['status'] === 'draft'): ?>
            <?php if ($version['version_number'] > 1): ?>
                <form method="POST" action="/admin/forms/<?php echo $form['uuid']; ?>/discard-draft" class="inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50" onclick="return confirm('Discard this draft? All changes will be lost and cannot be recovered.');">
                        <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        Discard Draft
                    </button>
                </form>
            <?php endif; ?>
            <form method="POST" action="/admin/forms/<?php echo $form['uuid']; ?>/publish" class="inline">
                <?php echo csrf_field(); ?>
                <?php
                    $publish_confirm = 'Publish this version? It will become the active version for this form.';
                    if ($user_draft_count > 0) {
                        $publish_confirm = '⚠️ ' . $user_draft_count . ' user(s) have in-progress drafts for this form. Publishing a new version will reset their progress and they will need to start over. Publish anyway?';
                    }
                ?>
                <button type="submit" class="btn btn-primary" onclick="return confirm(<?php echo htmlspecialchars(json_encode($publish_confirm), ENT_QUOTES); ?>);">
                    <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Publish Version
                </button>
            </form>
            <?php if ($user_draft_count > 0): ?>
                <span class="text-xs text-amber-600 font-medium ml-1" title="<?php echo $user_draft_count; ?> user(s) have in-progress drafts">
                    <svg class="inline h-4 w-4 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <?php echo $user_draft_count; ?> draft(s)
                </span>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Question Types Palette -->
    <div class="lg:col-span-1">
        <div class="card p-4 sticky top-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Add Field</h3>
            <div class="space-y-2" id="field-palette">
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="text">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                        <span class="font-medium">Short Text</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="textarea">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h7" />
                        </svg>
                        <span class="font-medium">Long Text</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="number">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                        <span class="font-medium">Number</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="email">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span class="font-medium">Email</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="url">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        <span class="font-medium">URL</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="select">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                        </svg>
                        <span class="font-medium">Dropdown</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="radio">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">Radio Buttons</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="checkbox_group">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <span class="font-medium">Checkboxes</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="multiselect">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        <span class="font-medium">Multi-Select</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="date">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="font-medium">Date</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="datetime">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">Date &amp; Time</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="file">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span class="font-medium">File Upload</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="heading">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="font-medium">Heading</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="paragraph">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10M4 18h7" />
                        </svg>
                        <span class="font-medium">Instructional Text</span>
                    </div>
                </button>
                
                <button type="button" class="add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors" data-type="divider">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                        </svg>
                        <span class="font-medium">Divider</span>
                    </div>
                </button>
            </div>

            <!-- Preloaded Lists — buttons injected by form-builder.js from the presets registry -->
            <h3 class="text-sm font-semibold text-gray-900 mt-5 mb-3">Preloaded Lists</h3>
            <div class="space-y-2" id="preset-palette"></div>
        </div>
    </div>
    
    <!-- Form Canvas -->
    <div class="lg:col-span-3">
        <div class="card p-6">
            <div id="question-canvas" class="space-y-4 min-h-96" data-version-id="<?php echo $version['id']; ?>">
                <?php if (count($questions) === 0): ?>
                    <div class="empty-state" id="empty-state">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <p class="mt-2 text-sm">No questions yet. Click a field type on the left to add your first question.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($questions as $question): ?>
                        <?php include __DIR__ . '/_question_card.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>
    </div><!-- /builder-content -->

    <!-- Load SortableJS and Form Builder Script -->
    <link rel="modulepreload" href="<?= asset('/js/form-builder.js') ?>">
    <script src="<?= asset('/js/vendor/Sortable.min.js') ?>"></script>
    <script type="module" src="<?= asset('/js/form-builder.js') ?>"></script>
<?php endif; ?>
