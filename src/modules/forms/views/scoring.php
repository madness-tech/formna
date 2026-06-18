<!-- Form Scoring Configuration -->

<?php
// Ensure all required variables are defined (PHPStan safety)
$form = $form ?? null;
$version = $version ?? null; 
$questions = $questions ?? [];
$active_version = $active_version ?? null;
$scoring_locked = $scoring_locked ?? false;

// Scoreable question types (display-only types excluded)
$scoreable_types = ['text', 'textarea', 'number', 'email', 'url', 'date', 'datetime', 'select', 'radio', 'checkbox_group', 'multiselect', 'checkbox', 'file'];
$scoreable_questions = array_filter($questions, fn($q) => in_array($q['type'], $scoreable_types));
$scoring_enabled = !empty($form['settings']['scoring_enabled']);
?>

<?php if (!$version): ?>
    <!-- No Version Exists - Must create in builder first -->
    <div class="max-w-3xl mx-auto mt-12">
        <div class="card p-8">
            <div class="text-center">
                <svg class="mx-auto h-16 w-16 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h2 class="page-title mt-4"><?= sanitize($form['name']) ?> — Scoring</h2>
                <p class="body-text mt-2">This form has no versions yet.</p>
                <p class="body-text mt-1">Create the first version in the Form Builder before configuring scoring.</p>

                <div class="mt-6 pt-6 border-t border-gray-200 flex items-center justify-center gap-4">
                    <a href="/admin/forms/<?= $form['uuid'] ?>/builder" class="text-sm text-primary-600 hover:text-primary-500">
                        ← Form Builder
                    </a>
                    <a href="/admin/forms" class="body-text hover:text-gray-500">
                        Back to Forms List
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title"><?= sanitize($form['name']) ?> — Scoring</h1>
        <p class="mt-2 text-sm text-gray-700">
            Assign numeric scores to question answers. Scores are visible only to admins.
            <?php if ($version['status'] === 'draft'): ?>
                <span class="inline-flex items-center ml-2 px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Draft v<?= $version['version_number'] ?></span>
            <?php else: ?>
                <span class="inline-flex items-center ml-2 px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Active v<?= $version['version_number'] ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 flex items-center gap-3">
        <a href="/admin/forms/<?= $form['uuid'] ?>/builder" class="btn btn-secondary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            Form Builder
        </a>
        <a href="/admin/forms/<?= $form['uuid'] ?>/settings" class="btn btn-secondary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Settings
        </a>
    </div>
</div>

<?php if (empty($questions)): ?>
    <!-- No Questions Yet -->
    <div class="card mt-8 p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">No Questions Found</h3>
        <p class="mt-2 text-sm text-gray-500">Add questions in the Form Builder before configuring scoring.</p>
        <a href="/admin/forms/<?= $form['uuid'] ?>/builder" class="btn btn-primary mt-4">
            Go to Form Builder
        </a>
    </div>

<?php elseif (empty($scoreable_questions)): ?>
    <!-- No Scoreable Questions -->
    <div class="card mt-8 p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">No Scoreable Questions</h3>
        <p class="mt-2 text-sm text-gray-500">This form only contains display elements (headings, paragraphs, dividers) which cannot be scored.</p>
    </div>

<?php else: ?>

<form method="POST" action="/admin/forms/<?= $form['uuid'] ?>/scoring" class="space-y-6 mt-6">
    <?= csrf_field() ?>

    <!-- Enable/Disable Toggle -->
    <div class="card">
        <div class="card-header bg-gradient-to-r from-amber-50 to-orange-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center flex-1">
                    <div class="flex-shrink-0 w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="section-title">Scoring</h3>
                        <p class="body-text mt-0.5">
                            <?php if ($scoring_locked): ?>
                                Scoring state is locked after receiving submissions. You can only edit scoring values.
                            <?php else: ?>
                                Enable scoring to assign numeric values to answers
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <?php if ($scoring_locked): ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800">
                            <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            Locked
                        </span>
                    <?php endif; ?>
                    <label class="relative inline-flex items-center <?= $scoring_locked ? 'cursor-not-allowed opacity-60' : 'cursor-pointer' ?>">
                        <input type="checkbox" name="scoring_enabled" value="1" class="sr-only peer" id="scoring-toggle"
                               <?= $scoring_enabled ? 'checked' : '' ?>
                               <?= $scoring_locked ? 'disabled' : '' ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none <?= $scoring_locked ? '' : 'peer-focus:ring-4 peer-focus:ring-amber-300' ?> rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500 <?= $scoring_locked ? 'peer-disabled:opacity-60' : '' ?>"></div>
                        <span class="ms-3 text-sm font-medium text-gray-700" id="scoring-toggle-label"><?= $scoring_enabled ? 'Enabled' : 'Disabled' ?></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Questions Scoring Configuration -->
    <div id="scoring-questions" class="space-y-4 <?= $scoring_enabled ? '' : 'opacity-50 pointer-events-none' ?>">
        <?php foreach ($scoreable_questions as $question):
            $config = $question['config'];
            $scoring = $config['scoring'] ?? [];
            $type = $question['type'];
            $q_id = $question['uid'];
        ?>
        <div class="card">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900">
                            <?= sanitize($config['label'] ?? 'Untitled Question') ?>
                            <?php if (!empty($config['required'])): ?>
                                <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </h4>
                        <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700">
                            <?= ucfirst(str_replace('_', ' ', $type)) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4">

                <?php if (in_array($type, ['text', 'textarea', 'number', 'email', 'url', 'date', 'datetime', 'file'])): ?>
                    <!-- Input/File: empty vs non_empty -->
                    <div class="grid grid-cols-2 gap-4 max-w-md">
                        <div>
                            <label class="text-xs text-gray-600">Score if empty</label>
                            <input type="number" name="scoring[<?= $q_id ?>][empty]"
                                   value="<?= (int) ($scoring['empty'] ?? 0) ?>"
                                   class="focus:ring-amber-500 focus:border-amber-500"
                                   min="0" step="1">
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Score if answered</label>
                            <input type="number" name="scoring[<?= $q_id ?>][non_empty]"
                                   value="<?= (int) ($scoring['non_empty'] ?? 0) ?>"
                                   class="focus:ring-amber-500 focus:border-amber-500"
                                   min="0" step="1">
                        </div>
                    </div>

                <?php elseif (in_array($type, ['select', 'radio'])): ?>
                    <!-- Single-select: score per option -->
                    <div class="space-y-2 max-w-md">
                    <?php foreach (($config['options'] ?? []) as $option):
                        $opt_value = $option['value'] ?? '';
                        if ($opt_value === '') {
                            continue;
                        }
                    ?>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-700 flex-1 truncate"><?= sanitize($option['label'] ?? $opt_value) ?></span>
                            <input type="number" name="scoring[<?= $q_id ?>][<?= sanitize($opt_value) ?>]"
                                   value="<?= (int) ($scoring[$opt_value] ?? 0) ?>"
                                   class="w-24 py-1.5 focus:ring-amber-500 focus:border-amber-500"
                                   min="0" step="1"
                                   placeholder="Score">
                        </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif (in_array($type, ['multiselect', 'checkbox_group'])): ?>
                    <!-- Multi-select: score per option (summed) -->
                    <p class="help-text">Score = sum of all selected options' scores</p>
                    <div class="space-y-2 max-w-md">
                    <?php foreach (($config['options'] ?? []) as $option):
                        $opt_value = $option['value'] ?? '';
                        if ($opt_value === '') {
                            continue;
                        }
                    ?>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-700 flex-1 truncate"><?= sanitize($option['label'] ?? $opt_value) ?></span>
                            <input type="number" name="scoring[<?= $q_id ?>][<?= sanitize($opt_value) ?>]"
                                   value="<?= (int) ($scoring[$opt_value] ?? 0) ?>"
                                   class="w-24 py-1.5 focus:ring-amber-500 focus:border-amber-500"
                                   min="0" step="1"
                                   placeholder="Score">
                        </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($type === 'checkbox'): ?>
                    <!-- Checkbox: checked vs unchecked -->
                    <div class="grid grid-cols-2 gap-4 max-w-md">
                        <div>
                            <label class="text-xs text-gray-600">Score if unchecked</label>
                            <input type="number" name="scoring[<?= $q_id ?>][unchecked]"
                                   value="<?= (int) ($scoring['unchecked'] ?? 0) ?>"
                                   class="focus:ring-amber-500 focus:border-amber-500"
                                   min="0" step="1">
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Score if checked</label>
                            <input type="number" name="scoring[<?= $q_id ?>][checked]"
                                   value="<?= (int) ($scoring['checked'] ?? 0) ?>"
                                   class="focus:ring-amber-500 focus:border-amber-500"
                                   min="0" step="1">
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Save Button -->
    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Save Scoring
        </button>
    </div>
</form>

<script>
    // Toggle scoring questions visibility when the toggle changes
    document.getElementById('scoring-toggle').addEventListener('change', function() {
        const container = document.getElementById('scoring-questions');
        const label = document.getElementById('scoring-toggle-label');
        if (this.checked) {
            container.classList.remove('opacity-50', 'pointer-events-none');
            label.textContent = 'Enabled';
        } else {
            container.classList.add('opacity-50', 'pointer-events-none');
            label.textContent = 'Disabled';
        }
    });
</script>

<?php endif; ?>

<?php endif; /* end !$version else */ ?>
