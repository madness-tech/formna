<?php
/**
 * Submission Confirmation View
 * 
 * Shows submission details after successful submission.
 */

$layout = 'user';

// Ensure required variables are defined
$form = $form ?? [];
$submission = $submission ?? [];
$questions = $questions ?? [];
$files = $files ?? [];
$from_form_uuid = $from_form_uuid ?? null;
$back_to_submissions_url = $back_to_submissions_url ?? null;
$allows_multiple = $allows_multiple ?? false;
$can_submit_new = $can_submit_new ?? true;
$is_editable = $is_editable ?? false;

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <!-- Success Message -->
    <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded-lg mb-6">
        <div class="flex items-center">
            <svg class="w-8 h-8 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <h2 class="text-lg font-semibold text-green-900"><?= t('submission.received') ?></h2>
                <p class="text-green-700"><?= t('submission.received_message') ?></p>
            </div>
        </div>
    </div>
    
    <!-- Submission Details -->
    <div class="card p-8 mb-6">
        <h1 class="page-title mb-6"><?= sanitize($form['name']) ?></h1>
        
        <!-- Metadata -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600"><?= t('submission.submission_id') ?></span>
                <span class="font-mono text-gray-900"><?= sanitize($submission['uuid']) ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600"><?= t('submission.submitted_date') ?></span>
                <span class="text-gray-900"><?= format_datetime($submission['submitted_at'], 'long') ?></span>
            </div>
        </div>
        
        <!-- Answers -->
        <h3 class="section-title mb-4"><?= t('submission.your_responses') ?></h3>
        
        <div class="space-y-6">
            <?php foreach ($questions as $question):
                $config = $question['config'];
                $answer = $submission['answers'][$question['id']] ?? null;
                
                // Skip display-only questions
                if (in_array($question['type'], ['heading', 'paragraph', 'divider'])) {
                    continue;
                }
                
                // For file questions, determine visibility via the files table
                $question_files = [];
                if ($question['type'] === 'file') {
                    $question_files = array_values(array_filter($files, fn($f) => $f['question_id'] == $question['id']));
                }
                
                $has_no_answer = ($answer === null || $answer === '' || (is_array($answer) && empty($answer)));
                if ($question['type'] === 'file') {
                    $has_no_answer = empty($question_files);
                }
            ?>
            
            <div class="border-b border-gray-200 pb-4">
                <p class="text-sm font-medium text-gray-700 mb-2"><?= sanitize($config['label']) ?></p>
                
                <div class="text-gray-900">
                    <?php if ($has_no_answer): ?>
                        <p><em class="text-gray-400"><?= t('submission.no_answer') ?></em></p>
                    <?php elseif ($question['type'] === 'textarea'): ?>
                        <p class="whitespace-pre-wrap"><?= sanitize($answer) ?></p>
                        
                    <?php elseif ($question['type'] === 'checkbox_group' || $question['type'] === 'multiselect'): ?>
                        <?php if (is_array($answer)): ?>
                            <ul class="list-disc list-inside">
                                <?php
                                // Map values to labels
                                foreach ($answer as $val):
                                    $display_value = $val;
                                    // Find the label for this value
                                    if (isset($config['options']) && is_array($config['options'])) {
                                        foreach ($config['options'] as $option) {
                                            if (isset($option['value']) && $option['value'] === $val) {
                                                $display_value = $option['label'];
                                                break;
                                            }
                                        }
                                    }
                                ?>
                                    <li><?= sanitize($display_value) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                    <?php elseif ($question['type'] === 'checkbox'): ?>
                        <p><?= $answer ? t('common.yes') : t('common.no') ?></p>
                        
                    <?php elseif ($question['type'] === 'file'): ?>
                        <ul class="space-y-2">
                            <?php
                            $previewable_mimes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
                            foreach ($question_files as $file):
                                $can_preview = in_array($file['mime_type'], $previewable_mimes, true);
                            ?>
                                <li class="flex items-center text-sm">
                                    <svg class="w-5 h-5 text-gray-400 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    <?php if ($can_preview): ?>
                                    <button type="button"
                                            class="text-primary-600 hover:underline text-left"
                                            data-preview-file
                                            data-file-id="<?= (int)$file['id'] ?>"
                                            data-file-name="<?= sanitize($file['original_name']) ?>"
                                            data-file-mime="<?= sanitize($file['mime_type']) ?>">
                                        <?= sanitize($file['original_name']) ?>
                                    </button>
                                    <?php else: ?>
                                    <a href="/files/<?= (int)$file['id'] ?>/download" class="text-primary-600 hover:underline">
                                        <?= sanitize($file['original_name']) ?>
                                    </a>
                                    <?php endif; ?>
                                    <span class="text-gray-500 ml-2">(<?= number_format($file['size_bytes'] / 1024, 1) ?> KB)</span>
                                    <?php if ($can_preview): ?>
                                    <a href="/files/<?= (int)$file['id'] ?>/download" class="ml-2 text-gray-400 hover:text-gray-600" title="<?= t('common.download') ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                    <?php elseif ($question['type'] === 'date'): ?>
                        <p><?= format_datetime($answer, 'date') ?></p>

                    <?php elseif ($question['type'] === 'datetime'): ?>
                        <p><?= format_datetime($answer, 'long') ?></p>
                        
                    <?php elseif (in_array($question['type'], ['select', 'radio'])): ?>
                        <?php
                        // Map value to label for select and radio
                        $display_value = $answer;
                        if (isset($config['options']) && is_array($config['options'])) {
                            foreach ($config['options'] as $option) {
                                if (isset($option['value']) && $option['value'] === $answer) {
                                    $display_value = $option['label'];
                                    break;
                                }
                            }
                        }
                        ?>
                        <p><?= sanitize($display_value) ?></p>
                        
                    <?php else: ?>
                        <p><?= sanitize($answer) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="flex gap-4">
        <?php if ($back_to_submissions_url): ?>
            <a href="<?= sanitize($back_to_submissions_url) ?>" class="btn btn-secondary">
                <?= t('submission.back_to_submissions') ?>
            </a>
        <?php elseif ($from_form_uuid): ?>
            <a href="/forms/<?= sanitize($from_form_uuid) ?>" class="btn btn-secondary">
                <?= t('submission.return_to_form') ?>
            </a>
        <?php else: ?>
            <a href="/dashboard" class="btn btn-secondary">
                <?= t('submission.back_to_dashboard') ?>
            </a>
        <?php endif; ?>
        
        <?php if ($is_editable): ?>
            <a href="/submissions/<?= sanitize($submission['uuid']) ?>/edit" class="btn <?= ($allows_multiple && $can_submit_new) ? 'btn-secondary' : 'btn-primary' ?>">
                <?= t('submission.edit_submission') ?>
            </a>
        <?php endif; ?>
        
        <?php if ($allows_multiple && $can_submit_new): ?>
            <a href="/forms/<?= sanitize($form['uuid']) ?>" class="btn btn-primary">
                <?= t('submission.submit_another') ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<script type="module">
    // Clear form cache after successful submission
    import { clearFormCache } from '<?= asset('/js/form-filler.js') ?>';
    import { openFilePreview } from '<?= asset('/js/file-preview.js') ?>';
    
    // Clear the cache for this specific form
    const formUuid = <?= json_encode($form['uuid'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    clearFormCache(formUuid);

    // File preview buttons
    document.querySelectorAll('[data-preview-file]').forEach(btn => {
        btn.addEventListener('click', () => {
            openFilePreview(
                parseInt(btn.dataset.fileId, 10),
                btn.dataset.fileName,
                btn.dataset.fileMime
            );
        });
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/' . $layout . '.php';
