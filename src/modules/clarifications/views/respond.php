<!-- User: Respond to Clarification Request -->
<?php
/**
 * @var array<string, mixed> $form
 * @var array<string, mixed> $submission
 * @var array<string, mixed> $clarification
 * @var array<string, mixed> $requester
 * @var array<int, array<string, mixed>> $questions
 * @var array<string, mixed> $answers
 * @var string $title
 */
?>
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="page-title"><?= t('clarifications.respond_heading') ?></h1>
        <p class="body-text mt-1">
            <?= t('clarifications.respond_subtitle') ?>
        </p>
    </div>

    <!-- Request Info Card -->
    <div class="card mb-6 p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="section-title"><?= sanitize($form['name']) ?></h2>
                <p class="body-text mt-1">
                    <?= t('clarifications.request_from_on', ['name' => sanitize($requester['name']), 'date' => format_datetime($clarification['created_at'], 'short')]) ?>
                </p>
            </div>
            <?php
            $clar_status_labels = [
                'open' => t('clarifications.status_open'),
                'responded' => t('clarifications.responded'),
                'resolved' => t('clarifications.status_resolved'),
                'rejected' => t('clarifications.status_rejected'),
                'cancelled' => t('clarifications.status_cancelled'),
            ];
            ?>
            <span class="badge badge-yellow">
                <?= $clar_status_labels[$clarification['status']] ?? ucfirst($clarification['status']) ?>
            </span>
        </div>

        <!-- Overall Message -->
        <?php if (!empty($clarification['message'])): ?>
        <div class="alert alert-info">
            <div class="flex">
                <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-900"><?= t('clarifications.admin_message') ?></h3>
                    <div class="mt-2 text-sm text-blue-800">
                        <?= nl2br(sanitize($clarification['message'])) ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rejection Feedback (shown when response was sent back for revision) -->
        <?php if (!empty($clarification['admin_feedback'])): ?>
        <div class="alert alert-danger mt-4">
            <div class="flex">
                <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <h3 class="text-sm font-semibold text-red-900"><?= t('clarifications.revision_required') ?></h3>
                    <p class="mt-1 text-sm text-red-700">
                        <?= t('clarifications.revision_feedback') ?>
                    </p>
                    <div class="mt-2 p-3 bg-white border border-red-100 rounded text-sm text-red-800">
                        <?= nl2br(sanitize($clarification['admin_feedback'])) ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <form method="POST" action="/requests/<?= $clarification['uuid'] ?>" class="space-y-6">
        <?= csrf_field() ?>

        <!-- Questions Requiring Response -->
        <div class="space-y-6">
            <?php foreach ($clarification['items'] as $index => $item): ?>
                <?php 
                    $question = $questions[$item['question_id']] ?? null;
                    if (!$question) continue;
                    
                    $config = $question['config'];
                    // Answers stored by numeric question ID (no prefix)
                    $original_answer = $answers[$item['question_id']] ?? null;
                    $has_response = !empty($item['response']);
                ?>
                
                <div class="card">
                    <!-- Question Header -->
                    <div class="p-6 bg-gray-50 border-b border-gray-200">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <?php
                                    $type_labels = [
                                        'clarification' => t('clarifications.clarification_type'),
                                        'rectification' => t('clarifications.correction_type'),
                                    ];
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        <?= $item['type'] === 'rectification' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' ?>">
                                        <?= $type_labels[$item['type']] ?? ucfirst($item['type']) ?>
                                    </span>
                                    <?php if ($has_response): ?>
                                        <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                            ✓ <?= t('clarifications.responded') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="section-title">
                                    <?= sanitize($config['label']) ?>
                                </h3>
                                <?php if (!empty($config['description'])): ?>
                                    <p class="body-text mt-1">
                                        <?= sanitize($config['description']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-4">
                        <!-- Admin's Guidance -->
                        <div>
                            <label>
                                <?= $item['type'] === 'rectification' ? t('clarifications.correction_type') : t('clarifications.clarification_needed') ?>
                            </label>
                            <div class="alert alert-warning">
                                <div class="text-sm text-gray-900">
                                    <?= nl2br(sanitize($item['reason'] ?? '')) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Original Answer -->
                        <div>
                            <label>
                                <?= t('clarifications.original_answer') ?>
                            </label>
                            <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                <div class="text-sm text-gray-900">
                                    <?php if (is_array($original_answer)): ?>
                                        <?php
                                        // Map answer values to labels for select/radio/checkbox fields
                                        $options = $config['options'] ?? [];
                                        if (!empty($options)) {
                                            // Build a value => label map
                                            $option_map = [];
                                            foreach ($options as $opt) {
                                                $option_map[$opt['value']] = $opt['label'];
                                            }
                                            // Convert answer values to labels
                                            $answer_labels = array_map(function($val) use ($option_map) {
                                                return $option_map[$val] ?? $val;
                                            }, $original_answer);
                                            echo sanitize(implode(', ', $answer_labels));
                                        } else {
                                            echo sanitize(implode(', ', $original_answer));
                                        }
                                        ?>
                    <?php elseif ($original_answer): ?>
                        <?php
                        if ($question['type'] === 'datetime') {
                            echo sanitize(format_datetime($original_answer, 'long'));
                        } elseif ($question['type'] === 'date') {
                            echo sanitize(format_datetime($original_answer, 'date'));
                        } else {
                            // Map single answer value to label for select/radio fields
                            $options = $config['options'] ?? [];
                            if (!empty($options)) {
                                $option_map = [];
                                foreach ($options as $opt) {
                                    $option_map[$opt['value']] = $opt['label'];
                                }
                                echo sanitize($option_map[$original_answer] ?? $original_answer);
                            } else {
                                echo sanitize($original_answer);
                            }
                        }
                        ?>
                                    <?php else: ?>
                                        <em class="text-gray-400"><?= t('clarifications.no_answer') ?></em>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Response Field -->
                        <div>
                            <label>
                                <?= t('clarifications.your_response') ?> <span class="text-red-500">*</span>
                            </label>
                            
                            <?php if ($item['type'] === 'clarification'): ?>
                                <!-- Clarification: free-text response (informational only) -->
                                <textarea 
                                    name="q_<?= $item['question_id'] ?>" 
                                    rows="4"
                                    required
                                    placeholder="Provide additional details or clarification..."
                                ><?= $has_response ? sanitize($item['response']) : '' ?></textarea>
                                <p class="help-text">
                                    <?= t('clarifications.provide_info') ?>
                                </p>
                            <?php else: ?>
                                <!-- Rectification: render the actual field type so the corrected value is valid -->
                                <?php
                                    $q_type = $question['type'];
                                    $q_name = 'q_' . $item['question_id'];
                                    // Pre-fill with previous response if responded, otherwise original answer
                                    $prefill = $has_response ? $item['response'] : $original_answer;
                                ?>
                                
                                <?php if ($q_type === 'select'): ?>
                                    <select 
                                        name="<?= $q_name ?>" 
                                        required
                                        class="py-3"
                                    >
                                        <option value=""><?= t('form_fill.select_default') ?></option>
                                        <?php foreach ($config['options'] ?? [] as $option): ?>
                                            <option 
                                                value="<?= sanitize($option['value']) ?>"
                                                <?= (string)$prefill === (string)$option['value'] ? 'selected' : '' ?>
                                            >
                                                <?= sanitize($option['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                
                                <?php elseif ($q_type === 'radio'): ?>
                                    <div class="space-y-2 mt-1">
                                        <?php foreach ($config['options'] ?? [] as $option): ?>
                                            <label class="flex items-center">
                                                <input 
                                                    type="radio" 
                                                    name="<?= $q_name ?>" 
                                                    value="<?= sanitize($option['value']) ?>"
                                                    <?= (string)$prefill === (string)$option['value'] ? 'checked' : '' ?>
                                                    required
                                                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300"
                                                >
                                                <span class="ml-2 text-sm text-gray-700"><?= sanitize($option['label']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                
                                <?php elseif ($q_type === 'checkbox_group'): ?>
                                    <?php $prefill_arr = is_array($prefill) ? $prefill : ($prefill ? [$prefill] : []); ?>
                                    <div class="space-y-2 mt-1">
                                        <?php foreach ($config['options'] ?? [] as $option): ?>
                                            <label class="flex items-center">
                                                <input 
                                                    type="checkbox" 
                                                    name="<?= $q_name ?>[]" 
                                                    value="<?= sanitize($option['value']) ?>"
                                                    <?= in_array($option['value'], $prefill_arr) ? 'checked' : '' ?>
                                                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                                >
                                                <span class="ml-2 text-sm text-gray-700"><?= sanitize($option['label']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                
                                <?php elseif ($q_type === 'multiselect'): ?>
                                    <?php $prefill_arr = is_array($prefill) ? $prefill : ($prefill ? [$prefill] : []); ?>
                                    <select 
                                        name="<?= $q_name ?>[]" 
                                        multiple
                                        required
                                        class="py-3"
                                    >
                                        <?php foreach ($config['options'] ?? [] as $option): ?>
                                            <option 
                                                value="<?= sanitize($option['value']) ?>"
                                                <?= in_array($option['value'], $prefill_arr) ? 'selected' : '' ?>
                                            >
                                                <?= sanitize($option['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-gray-400 mt-1"><?= t('form_fill.hold_ctrl') ?></p>
                                
                                <?php elseif ($q_type === 'date'): ?>
                                    <input 
                                        type="date" 
                                        name="<?= $q_name ?>" 
                                        value="<?= sanitize((string)($prefill ?? '')) ?>"
                                        required
                                    >
                                
                                <?php elseif ($q_type === 'datetime'): ?>
                                    <input 
                                        type="datetime-local" 
                                        name="<?= $q_name ?>" 
                                        value="<?= sanitize(to_datetime_local((string)($prefill ?? ''))) ?>"
                                        required
                                    >
                                
                                <?php elseif ($q_type === 'number'): ?>
                                    <input 
                                        type="number" 
                                        name="<?= $q_name ?>" 
                                        value="<?= sanitize((string)($prefill ?? '')) ?>"
                                        step="any"
                                        required
                                        placeholder="Enter a number..."
                                    >
                                
                                <?php elseif ($q_type === 'email'): ?>
                                    <input 
                                        type="email" 
                                        name="<?= $q_name ?>" 
                                        value="<?= sanitize((string)($prefill ?? '')) ?>"
                                        required
                                        placeholder="email@example.com"
                                    >
                                
                                <?php elseif ($q_type === 'url'): ?>
                                    <input 
                                        type="url" 
                                        name="<?= $q_name ?>" 
                                        value="<?= sanitize((string)($prefill ?? '')) ?>"
                                        required
                                        placeholder="https://"
                                    >
                                
                                <?php else: ?>
                                    <!-- text / textarea / fallback -->
                                    <textarea 
                                        name="<?= $q_name ?>" 
                                        rows="4"
                                        required
                                        placeholder="Provide the corrected information..."
                                    ><?= sanitize((string)($prefill ?? '')) ?></textarea>
                                <?php endif; ?>
                                
                                <p class="help-text">
                                    <?= t('clarifications.provide_info') ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
            <div class="flex gap-4">
                <a href="/requests" class="text-gray-600 hover:text-gray-900">
                    ← <?= t('common.back') ?>
                </a>
                <a href="/submissions/<?= $submission['uuid'] ?>" class="text-primary-600 hover:text-primary-700">
                    <?= t('clarifications.view_original') ?>
                </a>
            </div>
            <button 
                type="submit"
                class="btn btn-primary"
            >
                <?= t('clarifications.submit_response') ?>
            </button>
        </div>
    </form>
</div>
