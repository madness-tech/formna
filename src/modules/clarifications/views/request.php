<!-- Admin: Request Clarification Form -->
<?php
/**
 * @var array<string, mixed> $form
 * @var array<string, mixed> $submission
 * @var array<int, array<string, mixed>> $questions
 * @var array<string, mixed> $answers
 * @var array<int, array<string, mixed>> $existing_clarifications
 * @var string $title
 * @var array<int, array<string, string>> $breadcrumbs
 */
$submission_uuid = $submission['uuid'];
$ps_uuid = $_GET['ps'] ?? null;
?>
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="page-title">Request Clarification</h1>
        <p class="body-text mt-1">
            Select the questions that need clarification or correction from the user.
        </p>
    </div>

    <!-- Submission Info Card -->
    <div class="card mb-6 p-6">
        <h2 class="section-title mb-4">Submission Details</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-600">Form:</span>
                <span class="ml-2 font-medium"><?= sanitize($form['name']) ?></span>
            </div>
            <div>
                <span class="text-gray-600">Submitted:</span>
                <span class="ml-2 font-medium"><?= format_datetime($submission['submitted_at'], 'short') ?></span>
            </div>
            <div>
                <span class="text-gray-600">User:</span>
                <span class="ml-2 font-medium"><?= sanitize(db_one("SELECT name FROM users WHERE id = ?", [$submission['user_id']])['name']) ?></span>
            </div>
            <div>
                <span class="text-gray-600">Status:</span>
                <span class="ml-2 px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                    <?= ucfirst(str_replace('_', ' ', $submission['status'])) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Existing Clarifications -->
    <?php if (!empty($existing_clarifications)): ?>
    <div class="alert alert-warning">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-yellow-800">Previous Clarification Requests</h3>
                <div class="mt-3 space-y-3">
                    <?php foreach ($existing_clarifications as $index => $ec): ?>
                        <div class="bg-white border border-yellow-200 rounded-lg overflow-hidden">
                            <!-- Header (Always visible, clickable) -->
                            <button
                                type="button"
                                onclick="togglePreviousClarification(<?= $index ?>)"
                                class="w-full px-3 py-2 flex items-center justify-between hover:bg-yellow-50 transition-colors text-left"
                            >
                                <div class="flex items-center gap-2 flex-1 flex-wrap">
                                    <span class="text-xs font-medium text-gray-900">
                                        <?= format_datetime($ec['created_at'], 'date_short') ?>
                                    </span>
                                    <?php if (!empty($ec['requester_name'])): ?>
                                        <span class="help-text">
                                            by <?= sanitize($ec['requester_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="px-2 py-0.5 rounded text-xs font-medium
                                        <?= $ec['status'] == 'resolved' ? 'bg-green-100 text-green-800' :
                                           ($ec['status'] == 'responded' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                        <?= ucfirst($ec['status']) ?>
                                    </span>
                                    <span class="text-xs text-gray-600">
                                        <?= count($ec['items']) ?> question(s)
                                    </span>
                                </div>
                                <svg class="w-4 h-4 text-gray-500 transform transition-transform prev-clarif-chevron-<?= $index ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            
                            <!-- Summary (Collapsed by default) -->
                            <div id="prev-clarif-<?= $index ?>" class="hidden border-t border-yellow-100">
                                <div class="px-3 py-3">
                                    <?php if (!empty($ec['message'])): ?>
                                        <div class="text-xs text-gray-600 mb-2">
                                            <span class="font-medium text-gray-700">Message:</span>
                                            <?= mb_strlen($ec['message']) > 100 ? sanitize(mb_substr($ec['message'], 0, 100)) . '...' : sanitize($ec['message']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-xs text-gray-600 mb-3">
                                        <?php
                                        $clarif_count = count(array_filter($ec['items'], fn($i) => $i['type'] === 'clarification'));
                                        $rectif_count = count(array_filter($ec['items'], fn($i) => $i['type'] === 'rectification'));
                                        ?>
                                        <?php if ($clarif_count > 0): ?>
                                            <span class="text-blue-700"><?= $clarif_count ?> clarification<?= $clarif_count > 1 ? 's' : '' ?></span>
                                        <?php endif; ?>
                                        <?php if ($clarif_count > 0 && $rectif_count > 0): ?>, <?php endif; ?>
                                        <?php if ($rectif_count > 0): ?>
                                            <span class="text-red-700"><?= $rectif_count ?> rectification<?= $rectif_count > 1 ? 's' : '' ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <a href="/admin/clarifications/<?= $ec['uuid'] ?>"
                                       class="inline-flex items-center text-xs font-medium text-primary-600 hover:text-primary-700"
                                       target="_blank">
                                        View Full Details
                                        <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="/admin/submissions/<?= $submission_uuid ?>/clarify" class="space-y-6">
        <?= csrf_field() ?>
        <?php if ($ps_uuid): ?>
            <input type="hidden" name="ps" value="<?= sanitize($ps_uuid) ?>">
        <?php endif; ?>

        <!-- Overall Message -->
        <div class="card p-6">
            <label>
                Overall Message (Optional)
            </label>
            <textarea 
                name="message" 
                rows="3"
                placeholder="Provide general context or instructions for the user..."
            ></textarea>
            <p class="help-text">
                This message will be shown to the user before the specific questions.
            </p>
        </div>

        <!-- Questions List -->
        <div class="card">
            <div class="p-6 border-b border-gray-200">
                <h2 class="section-title">Select Questions</h2>
                <p class="body-text mt-1">
                    Choose the questions that need clarification or correction.
                </p>
            </div>

            <div class="divide-y divide-gray-200">
                <?php foreach ($questions as $question): ?>
                    <?php 
                        $config = $question['config'];
                        $question_id = $question['id'];
                        // Answers are stored by numeric question ID (no prefix)
                        $answer = $answers[$question_id] ?? null;

                        // Determine if question was hidden by conditional logic (no answer + has visibility rules)
                        $has_answer = is_array($answer)
                            ? count($answer) > 0
                            : ($answer !== null && $answer !== '');
                        $hidden_by_logic = !$has_answer && !empty($config['visibility']);
                        
                        // Skip display-only elements
                        if (in_array($question['type'], ['heading', 'paragraph', 'divider'])) {
                            continue;
                        }
                    ?>
                    
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <!-- Question Header with Checkbox -->
                        <div class="flex items-start">
                            <input 
                                type="checkbox" 
                                name="questions[]" 
                                value="<?= $question_id ?>"
                                id="q_<?= $question_id ?>"
                                class="mt-1 h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded question-checkbox"
                                <?= $hidden_by_logic ? 'disabled' : '' ?>
                                <?= $hidden_by_logic ? 'title="Hidden from user due to conditional logic"' : '' ?>
                            >
                            <div class="ml-3 flex-1">
                                <label for="q_<?= $question_id ?>" class="text-gray-900 cursor-pointer">
                                    <?= sanitize($config['label']) ?>
                                </label>
                                <?php if ($hidden_by_logic): ?>
                                    <div class="mt-1 inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700" title="This question was never shown to the user because its visibility conditions were not met">
                                        Hidden by conditional logic
                                    </div>
                                <?php endif; ?>
                                
                                <!-- User's Answer -->
                                <div class="mt-2 p-3 bg-gray-50 rounded border border-gray-200">
                                    <div class="help-text">User's Answer:</div>
                                    <div class="text-sm text-gray-900">
                                        <?php if ($hidden_by_logic): ?>
                                            <em class="text-gray-400">Not shown to user (conditional logic)</em>
                                        <?php elseif (is_array($answer)): ?>
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
                                                }, $answer);
                                                echo sanitize(implode(', ', $answer_labels));
                                            } else {
                                                echo sanitize(implode(', ', $answer));
                                            }
                                            ?>
                                        <?php elseif ($answer): ?>
                                            <?php
                                            if ($question['type'] === 'datetime') {
                                                echo sanitize(format_datetime($answer, 'long'));
                                            } elseif ($question['type'] === 'date') {
                                                echo sanitize(format_datetime($answer, 'date'));
                                            } else {
                                                // Map single answer value to label for select/radio fields
                                                $options = $config['options'] ?? [];
                                                if (!empty($options)) {
                                                    $option_map = [];
                                                    foreach ($options as $opt) {
                                                        $option_map[$opt['value']] = $opt['label'];
                                                    }
                                                    echo sanitize($option_map[$answer] ?? $answer);
                                                } else {
                                                    echo sanitize($answer);
                                                }
                                            }
                                            ?>
                                        <?php else: ?>
                                            <em class="text-gray-400">No answer provided</em>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Clarification Details (shown when checked) -->
                                <div class="mt-4 hidden clarification-details" id="details_<?= $question_id ?>">
                                    <div class="space-y-3">
                                        <!-- Type Selection -->
                                        <div>
                                            <label>
                                                Request Type
                                            </label>
                                            <div class="flex gap-4">
                                                <label class="inline-flex items-center">
                                                    <input type="radio" name="type_<?= $question_id ?>" value="clarification" checked class="text-primary-600 focus:ring-primary-500">
                                                    <span class="ml-2 text-sm text-gray-700">Clarification</span>
                                                </label>
                                                <?php if ($question['type'] !== 'file'): ?>
                                                <label class="inline-flex items-center">
                                                    <input type="radio" name="type_<?= $question_id ?>" value="rectification" class="text-primary-600 focus:ring-primary-500">
                                                    <span class="ml-2 text-sm text-gray-700">Correction Required</span>
                                                </label>
                                                <?php else: ?>
                                                <span class="help-text italic self-center">File questions only support clarification</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Guidance Text -->
                                        <div>
                                            <label>
                                                Guidance for User
                                            </label>
                                            <textarea 
                                                name="guidance_<?= $question_id ?>" 
                                                rows="3"
                                                placeholder="Explain what needs clarification or what correction is needed..."
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between items-center">
            <a href="/admin/forms/<?= $form['uuid'] ?>/submissions/<?= $submission_uuid ?><?= $ps_uuid ? '?from=review&ps=' . urlencode($ps_uuid) : '' ?>" class="text-gray-600 hover:text-gray-900">
                ← Back to Submission
            </a>
            <div class="flex gap-3">
                <button 
                    type="submit"
                    class="btn btn-primary"
                >
                    Send Clarification Request
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Toggle clarification details when checkbox is checked
document.querySelectorAll('.question-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const questionId = this.value;
        const details = document.getElementById('details_' + questionId);
        
        if (this.checked) {
            details.classList.remove('hidden');
        } else {
            details.classList.add('hidden');
        }
    });
});

// Toggle previous clarification details
function togglePreviousClarification(index) {
    const details = document.getElementById('prev-clarif-' + index);
    const chevron = document.querySelector('.prev-clarif-chevron-' + index);
    
    if (details.classList.contains('hidden')) {
        details.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        details.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
    }
}
</script>
