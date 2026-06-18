<?php
/**
 * Form Filling View
 * 
 * Renders a form for users to fill out and submit.
 */

// Ensure required variables are defined to prevent PHPStan errors
if (!isset($form) || !isset($questions)) {
    throw new Exception('Required variables not defined for form fill view');
}

// Optional variables (set defaults if not defined)
$submission = $submission ?? null;
$draft_data = $draft_data ?? [];
$errors = $errors ?? [];
$is_editing = $is_editing ?? false;
$edit_files = $edit_files ?? [];
$past_submissions = $past_submissions ?? null;
$can_submit_new = $can_submit_new ?? true;
$allows_multiple = $allows_multiple ?? false;
$allow_edits = $allow_edits ?? false;
$submitted_count = $submitted_count ?? 0;

// Include validation functions for get_client_validation_attrs
require_once __DIR__ . '/../../forms/validation.php';

$layout = 'user';
ob_start();
?>

<div class="max-w-3xl mx-auto">
    <!-- Form Header -->
    <div class="mb-6">
        <h1 class="page-title"><?= sanitize($form['name']) ?></h1>
        
        <?php if ($form['description']): ?>
            <p class="text-gray-600 mt-1"><?= sanitize($form['description']) ?></p>
        <?php endif; ?>
        
        <!-- Metadata line -->
        <?php
        $has_deadline = isset($form['settings']['submission_deadline']) && $form['settings']['submission_deadline'];
        $show_metadata = $has_deadline || $allows_multiple;
        ?>
        <?php if ($show_metadata): ?>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500">
                <?php if (isset($form['settings']['submission_deadline']) && $form['settings']['submission_deadline']): ?>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?= t('forms_browse.due_date', ['date' => format_datetime($form['settings']['submission_deadline'], 'short')]) ?>
                    </span>
                <?php endif; ?>
                <?php if ($allows_multiple): ?>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.678 48.678 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 003.7 3.7 48.656 48.656 0 007.324 0 4.006 4.006 0 003.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3l-3 3" />
                        </svg>
                        <?php
                        $limit = $form['settings']['submission_limit'] ?? 1;
                        if ($limit === 0): ?>
                            <?= t('user_dashboard.unlimited_submissions', ['count' => $submitted_count]) ?>
                        <?php else: ?>
                            <?= t('user_dashboard.submissions_used', ['used' => $submitted_count, 'limit' => $limit]) ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($form['instructions']): ?>
        <div class="bg-primary-50 border-l-4 border-primary-600 p-4 mb-6 rounded-r-lg">
            <div class="text-sm text-primary-900"><?= nl2br(sanitize($form['instructions'])) ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($can_submit_new): ?>
    <!-- New Submission Section -->
    <div id="new-submission-section">
        <?php if ($allows_multiple && !empty($past_submissions['rows'])): ?>
        <!-- Collapsible toggle — form starts EXPANDED -->
        <button type="button"
                id="toggle-form-btn"
                class="w-full mb-4 px-4 py-3 flex items-center justify-between text-left text-sm font-medium text-gray-600 hover:text-gray-900 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 transition">
            <span id="toggle-label"><?= $is_editing ? t('form_fill.hide_edit_form') : t('form_fill.hide_form') ?></span>
            <svg id="toggle-icon" class="w-4 h-4 transition-transform rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>
        <div id="form-content">
        <?php else: ?>
        <div id="form-content">
        <?php endif; ?>
        
        <?php if ($is_editing && $submission): ?>
        <!-- Editing banner -->
        <div class="bg-blue-50 border-l-4 border-blue-600 p-4 mb-4 rounded-r-lg">
            <div class="flex items-center text-sm text-blue-900">
                <svg class="w-4 h-4 text-blue-600 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
                <?= t('form_fill.editing_submission', ['date' => format_datetime($submission['submitted_at'], 'short')]) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Form (single card containing all questions) -->
        <form id="submission-form" method="POST" action="<?= $is_editing ? '/submissions/' . $submission['uuid'] . '/update' : '/forms/' . $form['uuid'] . '/submit' ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            
            <div class="card">
            <?php foreach ($questions as $index => $question): 
                $config = $question['config'];
                $question_id = 'q_' . $question['id'];
                $value = $draft_data[$question['id']] ?? '';
                $error = $errors[$question['id']] ?? null;
                $validation_attrs = get_client_validation_attrs($question);
                
                $is_display_only = in_array($question['type'], ['heading', 'paragraph', 'divider']);
                $is_first = ($index === 0);
                $border_class = $is_first ? '' : 'border-t border-gray-100';
            ?>
            
            <?php if ($question['type'] === 'divider'): ?>
                <!-- Divider -->
                <div class="question-container <?= $border_class ?>"
                     data-question-id="<?= $question['id'] ?>"
                     data-question-uid="<?= $question['uid'] ?>"
                     <?php if (isset($config['visibility']) && !empty($config['visibility'])): ?>
                     data-visibility='<?= json_encode($config['visibility']) ?>'
                     <?php endif; ?>>
                    <hr class="border-t-2 border-gray-200 mx-6">
                </div>
                
            <?php elseif ($question['type'] === 'heading'): ?>
                <!-- Heading -->
                <div class="question-container px-6 pt-6 pb-2 <?= $border_class ?>"
                     data-question-id="<?= $question['id'] ?>"
                     data-question-uid="<?= $question['uid'] ?>"
                     <?php if (isset($config['visibility']) && !empty($config['visibility'])): ?>
                     data-visibility='<?= json_encode($config['visibility']) ?>'
                     <?php endif; ?>>
                    <h2 class="text-lg font-bold text-gray-900"><?= sanitize($config['label']) ?></h2>
                    <?php if (!empty($config['description'])): ?>
                        <p class="text-gray-500 text-sm mt-0.5"><?= nl2br(sanitize($config['description'])) ?></p>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($question['type'] === 'paragraph'): ?>
                <!-- Paragraph -->
                <div class="question-container px-6 py-4 <?= $border_class ?>"
                     data-question-id="<?= $question['id'] ?>"
                     data-question-uid="<?= $question['uid'] ?>"
                     <?php if (isset($config['visibility']) && !empty($config['visibility'])): ?>
                     data-visibility='<?= json_encode($config['visibility']) ?>'
                     <?php endif; ?>>
                    <div class="body-text leading-relaxed"><?= nl2br(sanitize($config['description'] ?? $config['label'])) ?></div>
                </div>
                
            <?php else: ?>
                <!-- Question Field -->
                <div class="question-container px-6 py-5 <?= $border_class ?>"
                     data-question-id="<?= $question['id'] ?>"
                     data-question-uid="<?= $question['uid'] ?>"
                     <?php if (isset($config['visibility']) && !empty($config['visibility'])): ?>
                     data-visibility='<?= json_encode($config['visibility']) ?>'
                     <?php endif; ?>>
                    
                    <label>
                        <span class="text-sm font-medium text-gray-900">
                            <?= sanitize($config['label']) ?>
                            <?php if ($config['required'] ?? false): ?>
                                <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </span>
                        
                        <?php if (!empty($config['description'])): ?>
                            <span class="block text-sm text-gray-500 mt-0.5"><?= sanitize($config['description']) ?></span>
                        <?php endif; ?>
                        
                        <?php if (!empty($config['tooltip'])): ?>
                            <span class="block text-xs text-gray-400 mt-0.5" title="<?= sanitize($config['tooltip']) ?>">
                                ℹ️ <?= sanitize($config['tooltip']) ?>
                            </span>
                        <?php endif; ?>
                    </label>
                    
                    <!-- Input Field -->
                    <?php if ($question['type'] === 'text'): ?>
                        <input 
                            type="text" 
                            name="answers[<?= $question['id'] ?>]" 
                            id="<?= $question_id ?>"
                            value="<?= sanitize($value) ?>"
                            placeholder="<?= sanitize($config['placeholder'] ?? '') ?>"
                            <?= $validation_attrs ?>
                        >
                        
                    <?php elseif ($question['type'] === 'textarea'): ?>
                        <textarea 
                            name="answers[<?= $question['id'] ?>]" 
                            id="<?= $question_id ?>"
                            rows="4"
                            placeholder="<?= sanitize($config['placeholder'] ?? '') ?>"
                            <?= $validation_attrs ?>
                        ><?= sanitize($value) ?></textarea>
                        
                    <?php elseif ($question['type'] === 'number'): ?>
                        <input 
                            type="number" 
                            name="answers[<?= $question['id'] ?>]" 
                            id="<?= $question_id ?>"
                            value="<?= sanitize($value) ?>"
                            placeholder="<?= sanitize($config['placeholder'] ?? '') ?>"
                            step="any"
                            <?= $validation_attrs ?>
                        >
                        
                    <?php elseif ($question['type'] === 'email'): ?>
                        <input 
                            type="email" 
                            name="answers[<?= $question['id'] ?>]" 
                            id="<?= $question_id ?>"
                            value="<?= sanitize($value) ?>"
                            placeholder="<?= sanitize($config['placeholder'] ?? '') ?>"
                            <?= $validation_attrs ?>
                        >
                        
                    <?php elseif ($question['type'] === 'url'): ?>
                        <input 
                            type="url" 
                            name="answers[<?= $question['id'] ?>]" 
                            id="<?= $question_id ?>"
                            value="<?= sanitize($value) ?>"
                            placeholder="<?= sanitize($config['placeholder'] ?? '') ?>"
                            <?= $validation_attrs ?>
                        >
                        
                    <?php elseif ($question['type'] === 'date'): ?>
                        <input 
                            type="date" 
                            name="answers[<?= $question['id'] ?>]" 
                            id="<?= $question_id ?>"
                            value="<?= sanitize($value) ?>"
                            <?= $validation_attrs ?>
                        >
                        
                    <?php elseif ($question['type'] === 'datetime'): ?>
                        <input 
                            type="datetime-local" 
                            name="answers[<?= $question['id'] ?>]" 
                            id="<?= $question_id ?>"
                            value="<?= sanitize($value) ?>"
                            <?= $validation_attrs ?>
                        >
                        
                    <?php elseif ($question['type'] === 'select'): ?>
                        <select 
                            name="answers[<?= $question['id'] ?>]" 
                            id="<?= $question_id ?>"
                            <?= $validation_attrs ?>
                        >
                            <option value=""><?= t('form_fill.select_default') ?></option>
                            <?php foreach ($config['options'] ?? [] as $option): ?>
                                <option 
                                    value="<?= sanitize($option['value']) ?>"
                                    <?= $value === $option['value'] ? 'selected' : '' ?>
                                >
                                    <?= sanitize($option['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                    <?php elseif ($question['type'] === 'multiselect'): ?>
                        <select 
                            name="answers[<?= $question['id'] ?>][]" 
                            id="<?= $question_id ?>"
                            multiple
                            <?= $validation_attrs ?>
                        >
                            <?php 
                            $selected_values = is_array($value) ? $value : [];
                            foreach ($config['options'] ?? [] as $option): 
                            ?>
                                <option 
                                    value="<?= sanitize($option['value']) ?>"
                                    <?= in_array($option['value'], $selected_values) ? 'selected' : '' ?>
                                >
                                    <?= sanitize($option['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1"><?= t('form_fill.hold_ctrl') ?></p>
                        
                    <?php elseif ($question['type'] === 'radio'): ?>
                        <div class="space-y-2 mt-1">
                            <?php foreach ($config['options'] ?? [] as $option): ?>
                                <label class="flex items-center">
                                    <input 
                                        type="radio" 
                                        name="answers[<?= $question['id'] ?>]" 
                                        value="<?= sanitize($option['value']) ?>"
                                        <?= $value === $option['value'] ? 'checked' : '' ?>
                                        class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300"
                                        <?= $validation_attrs ?>
                                    >
                                    <span class="ml-2 text-sm text-gray-700"><?= sanitize($option['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                    <?php elseif ($question['type'] === 'checkbox_group'): ?>
                        <div class="space-y-2 mt-1">
                            <?php 
                            $selected_values = is_array($value) ? $value : [];
                            foreach ($config['options'] ?? [] as $option): 
                            ?>
                                <label class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        name="answers[<?= $question['id'] ?>][]" 
                                        value="<?= sanitize($option['value']) ?>"
                                        <?= in_array($option['value'], $selected_values) ? 'checked' : '' ?>
                                        class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                    >
                                    <span class="ml-2 text-sm text-gray-700"><?= sanitize($option['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                    <?php elseif ($question['type'] === 'checkbox'): ?>
                        <label class="flex items-center mt-1">
                            <input 
                                type="checkbox" 
                                name="answers[<?= $question['id'] ?>]" 
                                value="1"
                                <?= $value ? 'checked' : '' ?>
                                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                <?= $validation_attrs ?>
                            >
                            <span class="ml-2 text-sm text-gray-700"><?= sanitize($config['placeholder'] ?? 'Yes') ?></span>
                        </label>
                        
                    <?php elseif ($question['type'] === 'file'): ?>
                        <input 
                            type="file" 
                            name="file_<?= $question['id'] ?>" 
                            id="<?= $question_id ?>"
                            class="w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                            data-question-id="<?= $question['id'] ?>"
                            <?php if (!empty($config['validation']['max_file_size_mb'])): ?>
                            data-max-size-mb="<?= $config['validation']['max_file_size_mb'] ?>"
                            <?php endif; ?>
                            <?= $validation_attrs ?>
                        >
                        <input type="hidden" name="answers[<?= $question['id'] ?>]" id="<?= $question_id ?>_file_id" value="<?= sanitize($value) ?>">
                        <?php $has_edit_file = !empty($edit_files[$question['id']]); ?>
                        <div id="<?= $question_id ?>_file_info" class="<?= $has_edit_file ? 'inline-flex' : 'hidden' ?> mt-2 items-center gap-2 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                            <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="truncate" data-file-label><?php if ($has_edit_file): ?><?= sanitize($edit_files[$question['id']]['original_name']) ?><?php endif; ?></span>
                            <button type="button" data-file-remove class="ml-1 text-gray-400 hover:text-red-500 focus:outline-none" title="<?= t('form_fill.file_remove') ?>">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div id="<?= $question_id ?>_progress" class="hidden mt-2">
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-primary-600 h-1.5 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                        <?php
                        $file_hints = [];
                        if (!empty($config['validation']['allowed_extensions'])) {
                            $file_hints[] = t('form_fill.file_types', ['types' => implode(', ', $config['validation']['allowed_extensions'])]);
                        }
                        if (!empty($config['validation']['max_file_size_mb'])) {
                            $file_hints[] = t('form_fill.file_max_size', ['size' => $config['validation']['max_file_size_mb'] . ' MB']);
                        }
                        if (!empty($file_hints)): ?>
                            <p class="text-xs text-gray-400 mt-1">
                                <?= implode(' · ', $file_hints) ?>
                            </p>
                        <?php endif; ?>
                        
                    <?php endif; ?>
                    
                    <!-- Error Message -->
                    <?php if ($error): ?>
                        <p class="mt-1.5 text-sm text-red-600 form-error"><?= sanitize($error) ?></p>
                    <?php endif; ?>
                    
                </div>
            <?php endif; ?>
            
            <?php endforeach; ?>
            
                <!-- Actions row inside the card -->
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <?php if (!$is_editing): ?>
                        <button
                            type="button"
                            id="save-draft-btn"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <?= t('form_fill.save_draft') ?>
                        </button>
                        <?php endif; ?>
                        <div id="autosave-indicator" class="body-text hidden flex items-center gap-2"></div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <?php if (!$is_editing): ?>
                        <button
                            type="button"
                            id="discard-draft-btn"
                            class="hidden p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            title="<?= t('form_fill.discard_draft') ?>"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        <?php endif; ?>
                        <?php if ($is_editing && $submission): ?>
                        <a href="/submissions/<?= sanitize($submission['uuid']) ?>"
                           id="cancel-edit-btn"
                           class="btn btn-secondary"
                        ><?= t('form_fill.cancel_edit') ?></a>
                        <?php endif; ?>
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <?= $is_editing ? t('form_fill.update_submission') : t('form_fill.submit_form') ?>
                        </button>
                    </div>
                </div>
            </div><!-- end card -->
        </form>
        
        </div><!-- end form-content -->
    </div><!-- end new-submission-section -->
    <?php else: ?>
    <!-- Limit reached message -->
    <div class="bg-amber-50 border-l-4 border-amber-500 p-4 mb-6 rounded-r-lg">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <div>
                <p class="text-sm font-medium text-amber-900"><?= t('form_fill.submission_limit_reached') ?></p>
                <p class="text-sm text-amber-800 mt-0.5">
                    <?= t('form_fill.submission_limit_message', ['limit' => $form['settings']['submission_limit']]) ?>
                    <?php if ($allow_edits): ?>
                        <?= t('form_fill.can_edit_previous') ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($past_submissions && !empty($past_submissions['rows'])): ?>
    <!-- Past Submissions -->
    <div class="mt-8">
        <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">
            <?= t('form_fill.past_submissions') ?> (<?= $submitted_count ?>)
        </h2>
        <div class="space-y-2">
            <?php foreach ($past_submissions['rows'] as $sub): ?>
            <div class="card flex items-center justify-between px-4 py-3 hover:border-gray-300 transition">
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Status dot -->
                    <?php
                    $dot_colors = [
                        'submitted' => 'bg-green-500',
                        'clarification_requested' => 'bg-yellow-500',
                    ];
                    $dot_class = $dot_colors[$sub['status']] ?? 'bg-gray-400';
                    ?>
                    <span class="w-2 h-2 rounded-full <?= $dot_class ?> flex-shrink-0"></span>
                    <span class="text-sm text-gray-900"><?= format_datetime($sub['submitted_at'], 'short') ?></span>
                    <?php
                    $status_labels = [
                        'draft' => t('submission.status_draft'),
                        'submitted' => t('submission.status_submitted'),
                        'in_review' => t('submission.status_in_review'),
                        'approved' => t('submission.status_approved'),
                        'rejected' => t('submission.status_rejected'),
                        'clarification_requested' => t('submission.status_clarification'),
                    ];
                    ?>
                    <span class="help-text">
                        <?= $status_labels[$sub['status']] ?? ucfirst($sub['status']) ?>
                    </span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 ml-4">
                    <a href="/submissions/<?= sanitize($sub['uuid']) ?>?from_form=<?= sanitize($form['uuid']) ?>"
                       class="text-sm text-primary-600 hover:text-primary-800 font-medium"
                       title="<?= t('form_fill.view') ?>"><?= t('form_fill.view') ?></a>
                    <?php if ($allow_edits && is_submission_editable($sub, $form)): ?>
                    <span class="text-gray-300">·</span>
                    <a href="/submissions/<?= sanitize($sub['uuid']) ?>/edit"
                       class="text-sm text-primary-600 hover:text-primary-800 font-medium"
                       title="<?= t('form_fill.edit') ?>"><?= t('form_fill.edit') ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($past_submissions['pages'] > 1): ?>
            <div class="mt-4">
            <?php
            $pagination = $past_submissions;
            $base_url = '/forms/' . $form['uuid'];
            $query_params = ['submissions_page' => null];
            require __DIR__ . '/../../../layouts/partials/pagination.php';
            ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($allows_multiple && !empty($past_submissions['rows']) && $can_submit_new): ?>
<!-- Toggle form visibility script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggle-form-btn');
    const formContent = document.getElementById('form-content');
    const toggleIcon = document.getElementById('toggle-icon');
    const toggleLabel = document.getElementById('toggle-label');
    
    if (toggleBtn && formContent) {
        toggleBtn.addEventListener('click', function() {
            const isHidden = formContent.classList.toggle('hidden');
            toggleIcon.classList.toggle('rotate-180', !isHidden);
            if (toggleLabel) {
                toggleLabel.textContent = isHidden
                    ? '<?= $is_editing ? t('form_fill.show_edit_form') : t('form_fill.show_form') ?>'
                    : '<?= $is_editing ? t('form_fill.hide_edit_form') : t('form_fill.hide_form') ?>';
            }
        });
    }
});
</script>
<?php endif; ?>

<?php
// Build validation rules for JS-enforced validation.
// This mirrors server-side rules so the client can catch violations (e.g. when
// HTML attributes like maxlength have been manually removed from the DOM)
// before the form reaches the server, giving immediate feedback.
$js_validation_rules = [];
foreach ($questions as $q) {
    if (in_array($q['type'], ['heading', 'paragraph', 'divider'])) continue;
    $qcfg = $q['config'];
    $js_validation_rules[(string)$q['id']] = [
        'type'       => $q['type'],
        'required'   => $qcfg['required'] ?? false,
        'validation' => $qcfg['validation'] ?? [],
    ];
}
?>

<!-- Include Form Filler JavaScript -->
<script type="module">
    import { initFormFiller } from '<?= asset('/js/form-filler.js') ?>';
    
    // Inject translations for autosave and toasts
    window.formTranslations = {
        saving: <?= json_encode(t('form_fill.autosave_saving')) ?>,
        upToDate: <?= json_encode(t('form_fill.autosave_up_to_date')) ?>,
        unsavedChanges: <?= json_encode(t('form_fill.autosave_unsaved_changes')) ?>,
        saveFailed: <?= json_encode(t('form_fill.autosave_save_failed')) ?>,
        draftLoaded: <?= json_encode(t('form_fill.autosave_draft_loaded')) ?>,
        draftRestored: <?= json_encode(t('form_fill.autosave_draft_restored')) ?>,
        draftSavedSuccess: <?= json_encode(t('form_fill.draft_saved_success')) ?>,
        draftSaveFailed: <?= json_encode(t('form_fill.draft_save_failed')) ?>,
        draftDiscarded: <?= json_encode(t('form_fill.draft_discarded')) ?>,
        fileUploadedSuccess: <?= json_encode(t('form_fill.file_uploaded_success')) ?>,
        fileUploadFailed: <?= json_encode(t('form_fill.file_upload_failed')) ?>,
        fileTooLarge: <?= json_encode(t('validation.file_too_large')) ?>,
        fileAttached: <?= json_encode(t('form_fill.file_attached')) ?>,
        fileReplace: <?= json_encode(t('form_fill.file_replace')) ?>,
        discardConfirm: <?= json_encode(t('form_fill.discard_draft_confirm')) ?>,
        cancelEditConfirm: <?= json_encode(t('form_fill.cancel_edit_confirm')) ?>
    };
    
    initFormFiller({
        formId: <?= $form['id'] ?>,
        formUuid: '<?= $form['uuid'] ?>',
        isEditing: <?= $is_editing ? 'true' : 'false' ?>,
        <?php if ($is_editing && $submission): ?>
        submissionId: <?= (int)$submission['id'] ?>,
        <?php endif; ?>
        draftCleared: <?= !empty($draft_was_cleared) ? 'true' : 'false' ?>,
        hasValidationErrors: <?= !empty($errors) ? 'true' : 'false' ?>,
        validationRules: <?= json_encode($js_validation_rules) ?>,
        validationMessages: {
            required:        <?= json_encode(t('validation.required_field')) ?>,
            minLength:       <?= json_encode(t('validation.min_length')) ?>,
            maxLength:       <?= json_encode(t('validation.max_length')) ?>,
            invalidFormat:   <?= json_encode(t('validation.invalid_format')) ?>,
            invalidNumber:   <?= json_encode(t('validation.invalid_number')) ?>,
            invalidEmail:    <?= json_encode(t('validation.invalid_email')) ?>,
            invalidUrl:      <?= json_encode(t('validation.invalid_url')) ?>,
            minValue:        <?= json_encode(t('validation.min_value')) ?>,
            maxValue:        <?= json_encode(t('validation.max_value')) ?>,
            minSelections:   <?= json_encode(t('validation.min_selections')) ?>,
            maxSelections:   <?= json_encode(t('validation.max_selections')) ?>
        }
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/' . $layout . '.php';
