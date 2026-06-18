<?php
// Defensive initialization for PHPStan - these should be provided by parent context
$question = $question ?? [];
$questions_by_uid = $questions_by_uid ?? [];
?>
<!-- Question Card Component -->
<div class="stat-card question-card group relative border-2 p-4"
     data-question-id="<?php echo sanitize($question['uid']); ?>"
     data-type="<?php echo sanitize($question['type']); ?>">
    
    <!-- Drag Handle -->
    <div class="absolute left-2 top-2 cursor-move opacity-0 group-hover:opacity-100 transition-opacity">
        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
        </svg>
    </div>
    
    <!-- Question Header -->
    <div class="ml-8 flex items-start justify-between">
        <div class="flex-1 pr-4">
            <div class="question-view">
                <?php
                    $display_label = $question['config']['label'] ?? '';
                    if ($display_label === '') {
                        $display_fallbacks = ['divider' => 'Divider', 'paragraph' => 'Instructional Text', 'heading' => 'Section Heading'];
                        $display_label = $display_fallbacks[$question['type']] ?? 'Untitled Question';
                    }
                ?>
                <h4 class="text-base font-medium text-gray-900">
                    <?php echo sanitize($display_label); ?>
                    <?php if ($question['config']['required'] ?? false): ?>
                        <span class="text-red-500">*</span>
                    <?php endif; ?>
                </h4>
                <?php if (!empty($question['config']['description'])): ?>
                    <p class="mt-1 text-sm text-gray-500"><?php echo sanitize($question['config']['description']); ?></p>
                <?php endif; ?>
                <p class="mt-2 text-xs text-gray-400 uppercase tracking-wide"><?php echo ucfirst(str_replace('_', ' ', $question['type'])); ?></p>
                
                <?php if (!empty($question['config']['visibility']['question_uid'])): ?>
                    <?php
                    $sourceQuestion = $questions_by_uid[$question['config']['visibility']['question_uid']] ?? null;
                    $visibilitySourceLabel = $sourceQuestion
                        ? $sourceQuestion['config']['label'] ?? 'Untitled Question'
                        : null;
                    ?>
                    <div class="mt-2 inline-flex items-center px-2 py-1 rounded-md bg-amber-50 border border-amber-200">
                        <svg class="h-3.5 w-3.5 text-amber-600 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span class="text-xs text-amber-700 font-medium">Conditional: depends on <?php echo $visibilitySourceLabel !== null ? '&ldquo;' . sanitize($visibilitySourceLabel) . '&rdquo;' : 'a question'; ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Edit Form (populated by JS on demand) -->
            <div class="question-edit hidden"></div>
        </div>
        
        <!-- Actions -->
        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <button type="button" class="edit-question p-1.5 text-gray-400 hover:text-primary-600" title="Edit">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button type="button" class="delete-question p-1.5 text-gray-400 hover:text-red-600" title="Delete">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </div>
    </div>
</div>
