<!-- Form Preview -->
<?php
// Defensive initialization for PHPStan - these should always be set by the controller
$form = $form ?? null;
$version = $version ?? null;
$questions = $questions ?? [];

if (!$form || !$version) {
    flash('error', 'Form or version data not available.');
    redirect('/admin/forms');
}
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="page-title"><?php echo sanitize($form['name']); ?></h1>
            <p class="mt-1 text-sm text-gray-500">Preview Mode - Version <?php echo $version['version_number']; ?></p>
        </div>
        <a href="/admin/forms/<?php echo $form['uuid']; ?>/builder" class="btn btn-secondary">
            <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
            Back to Builder
        </a>
    </div>
    
    <div class="card p-8">
        <!-- Form Instructions -->
        <?php if ($form['instructions']): ?>
            <div class="alert alert-info">
                <h3 class="text-sm font-medium text-blue-900 mb-2">Instructions</h3>
                <div class="text-sm text-blue-800 whitespace-pre-line"><?php echo sanitize($form['instructions']); ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Form Questions -->
        <div class="space-y-6">
            <?php foreach ($questions as $question): ?>
                <?php
                $config = $question['config'];
                $required = $config['required'] ?? false;
                $label = sanitize($config['label'] ?? 'Question');
                $description = sanitize($config['description'] ?? '');
                ?>
                
                <div class="question-preview">
                    <?php if ($question['type'] === 'heading'): ?>
                        <!-- Heading -->
                        <h2 class="text-xl font-semibold text-gray-900"><?php echo $label; ?></h2>
                        
                    <?php elseif ($question['type'] === 'paragraph'): ?>
                        <!-- Paragraph -->
                        <p class="body-text"><?php echo $description; ?></p>
                        
                    <?php elseif ($question['type'] === 'divider'): ?>
                        <!-- Divider -->
                        <hr class="border-gray-300">
                        
                    <?php else: ?>
                        <!-- Regular Question -->
                        <label class="text-gray-900">
                            <?php echo $label; ?>
                            <?php if ($required): ?>
                                <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($description): ?>
                            <p class="text-sm text-gray-500 mb-3"><?php echo $description; ?></p>
                        <?php endif; ?>
                        
                        <!-- Field Type Specific Rendering -->
                        <?php if ($question['type'] === 'text'): ?>
                            <input type="text" disabled class="bg-gray-50" placeholder="Short text answer">
                            
                        <?php elseif ($question['type'] === 'textarea'): ?>
                            <textarea disabled rows="4" class="bg-gray-50" placeholder="Long text answer"></textarea>
                            
                        <?php elseif ($question['type'] === 'number'): ?>
                            <input type="number" disabled class="bg-gray-50" placeholder="Number">
                            
                        <?php elseif ($question['type'] === 'email'): ?>
                            <input type="email" disabled class="bg-gray-50" placeholder="email@example.com">
                            
                        <?php elseif ($question['type'] === 'url'): ?>
                            <input type="url" disabled class="bg-gray-50" placeholder="https://">
                            
                        <?php elseif ($question['type'] === 'date'): ?>
                            <input type="date" disabled class="bg-gray-50">
                            
                        <?php elseif ($question['type'] === 'datetime'): ?>
                            <input type="datetime-local" disabled class="bg-gray-50">
                            
                        <?php elseif ($question['type'] === 'select'): ?>
                            <select disabled class="bg-gray-50">
                                <option>Select an option...</option>
                                <?php foreach ($config['options'] ?? [] as $option): ?>
                                    <option><?php echo sanitize($option['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                        <?php elseif ($question['type'] === 'multiselect'): ?>
                            <select disabled multiple size="4" class="bg-gray-50">
                                <?php foreach ($config['options'] ?? [] as $option): ?>
                                    <option><?php echo sanitize($option['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-text">Hold Ctrl/Cmd to select multiple</p>
                            
                        <?php elseif ($question['type'] === 'radio'): ?>
                            <div class="space-y-2">
                                <?php foreach ($config['options'] ?? [] as $index => $option): ?>
                                    <label class="flex items-center">
                                        <input type="radio" disabled name="preview_radio_<?php echo $question['id']; ?>" class="rounded-full border-gray-300 text-primary-600 shadow-sm">
                                        <span class="ml-2 text-sm text-gray-700"><?php echo sanitize($option['label']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                        <?php elseif ($question['type'] === 'checkbox_group'): ?>
                            <div class="space-y-2">
                                <?php foreach ($config['options'] ?? [] as $index => $option): ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" disabled class="rounded border-gray-300 text-primary-600 shadow-sm">
                                        <span class="ml-2 text-sm text-gray-700"><?php echo sanitize($option['label']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                        <?php elseif ($question['type'] === 'file'): ?>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-not-allowed bg-gray-50">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <p class="mb-2 text-sm text-gray-500">Click to upload or drag and drop</p>
                                        <p class="text-xs text-gray-400">Preview mode - file upload disabled</p>
                                    </div>
                                </label>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Preview Footer -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">This is a preview. No data will be saved.</p>
                <button type="button" disabled class="px-6 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed">
                    Submit (Preview Only)
                </button>
            </div>
        </div>
    </div>
</div>
