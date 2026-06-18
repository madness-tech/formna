/**
 * Form Builder JavaScript
 * Handles drag-and-drop question management and builder interactions
 */

import { api, toast, confirm } from './common.js';
import { PRESETS } from './presets/index.js';

/**
 * Escape HTML to prevent XSS and broken attributes
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Character limits (must match server-side MAX_LABEL_LENGTH / MAX_DESCRIPTION_LENGTH)
const MAX_LABEL_LENGTH = 200;
const MAX_DESCRIPTION_LENGTH = 500;

/**
 * Attach a live character counter to an input/textarea.
 * Inserts a small "X / max" hint below the element.
 */
function attachCharCounter(el, max) {
    const counter = document.createElement('p');
    counter.className = 'mt-1 text-xs text-gray-400 char-counter';
    const update = () => {
        const len = el.value.length;
        counter.textContent = `${len} / ${max}`;
        counter.classList.toggle('text-red-500', len >= max);
        counter.classList.toggle('text-gray-400', len < max);
    };
    update();
    el.addEventListener('input', update);
    el.insertAdjacentElement('afterend', counter);
}

// Get version ID from canvas
const canvas = document.getElementById('question-canvas');
const versionId = canvas ? canvas.dataset.versionId : null;
const isReadOnly = canvas ? canvas.dataset.readonly === 'true' : false;

// State management
let initialized = false;
let sortableInstance = null;
let isProcessing = false; // Prevent concurrent operations

// CRITICAL: If readonly mode, block all editing operations
if (isReadOnly) {
    console.warn('Form builder loaded in READ-ONLY mode - active version protection enabled');
}

/**
 * Initialize Sortable for drag-and-drop
 */
function initSortable() {
    if (!canvas || isReadOnly) return; // Don't enable sorting for readonly versions

    sortableInstance = new Sortable(canvas, {
        animation: 150,
        handle: '.cursor-move',
        ghostClass: 'bg-primary-50',
        dragClass: 'shadow-xl',
        onEnd: async function(evt) {
            // Skip if processing or no actual movement
            if (isProcessing || evt.oldIndex === evt.newIndex) {
                return;
            }

            // Get new order of question IDs
            const questionCards = canvas.querySelectorAll('.question-card');
            const orderedIds = Array.from(questionCards).map(card => card.dataset.questionId);

            try {
                await api('/api/questions/reorder', 'POST', {
                    version_id: versionId,
                    question_ids: orderedIds
                });
                toast('success', 'Questions reordered successfully');
            } catch (error) {
                toast('error', 'Failed to reorder questions');
            }
        }
    });
}

/**
 * Build the preset palette buttons from the PRESETS registry.
 * Called once during init(). To add new preloaded lists, update presets/index.js.
 */
function buildPresetPalette() {
    const container = document.getElementById('preset-palette');
    if (!container) return;

    Object.entries(PRESETS).forEach(([key, preset]) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'add-field-btn w-full text-left px-3 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 hover:border-primary-500 transition-colors';
        btn.dataset.type = 'select';
        btn.dataset.preset = key;
        btn.innerHTML = `
            <div class="flex items-center">
                <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h7" />
                </svg>
                <span class="font-medium">${escapeHtml(preset.label)}</span>
            </div>
        `;
        container.appendChild(btn);
    });
}

/**
 * Add new question
 */
async function addQuestion(type, presetKey = null) {
    if (!versionId || isProcessing || isReadOnly) {
        if (isReadOnly) {
            toast('error', 'Cannot edit active version. Please create a new draft.');
        }
        return;
    }

    isProcessing = true;

    try {
        // Get current question count for sort order
        const currentQuestions = canvas.querySelectorAll('.question-card');
        const sortOrder = currentQuestions.length + 1;

        // Default config based on type
        const defaultConfigs = {
            text: { label: 'Short Text Question', description: '', required: false, validation: { min_length: null, max_length: null } },
            textarea: { label: 'Long Text Question', description: '', required: false, validation: { min_length: null, max_length: null } },
            number: { label: 'Number Question', description: '', required: false, validation: { min: null, max: null } },
            email: { label: 'Email Question', description: '', required: false, validation: {} },
            url: { label: 'URL Question', description: '', required: false, validation: {} },
            date: { label: 'Date Question', description: '', required: false, validation: {} },
            datetime: { label: 'Date & Time Question', description: '', required: false, validation: {} },
            select: { label: 'Dropdown Question', description: '', required: false, options: [{ value: 'option1', label: 'Option 1' }], validation: {} },
            radio: { label: 'Radio Button Question', description: '', required: false, options: [{ value: 'option1', label: 'Option 1' }], validation: {} },
            checkbox_group: { label: 'Checkbox Question', description: '', required: false, options: [{ value: 'option1', label: 'Option 1' }], validation: {} },
            multiselect: { label: 'Multi-Select Question', description: '', required: false, options: [{ value: 'option1', label: 'Option 1' }], validation: {} },
            file: { label: 'File Upload Question', description: '', required: false, validation: { max_file_size_mb: 10, allowed_extensions: ['pdf', 'doc', 'docx'] } },
            heading: { label: 'Section Heading', description: '', validation: {} },
            paragraph: { label: '', description: 'Instruction text goes here...', validation: {} },
            divider: { label: '', description: '', validation: {} }
        };

        const config = defaultConfigs[type] || defaultConfigs.text;

        // If a preset key is given, override the options and label with preset data
        if (presetKey && PRESETS[presetKey]) {
            const preset = PRESETS[presetKey];
            config.label = preset.label;
            config.options = preset.options;
        }

        const result = await api('/api/questions', 'POST', {
            version_id: versionId,
            type: type,
            sort_order: sortOrder,
            config: config
        });

        // Remove empty state if exists
        const emptyState = document.getElementById('empty-state');
        if (emptyState) emptyState.remove();

        // Add question card to canvas
        const card = createQuestionCard(result.question);
        canvas.appendChild(card);

        // Auto-expand edit form (skip dividers — nothing to edit)
        if (type !== 'divider') {
            editQuestion(card, result.question);
        }

        toast('success', 'Question added');
    } catch (error) {
        toast('error', 'Failed to add question');
        console.error('Add question error:', error);
    } finally {
        isProcessing = false;
    }
}

/**
 * Get a display-friendly fallback label for a question type when no label is set
 */
function displayLabel(type, label) {
    if (label) return label;
    const fallbacks = { divider: 'Divider', paragraph: 'Instructional Text', heading: 'Section Heading' };
    return fallbacks[type] || 'Untitled Question';
}

/**
 * Create question card HTML element
 * Note: Event listeners are handled via delegation in init(), not attached here
 */
function createQuestionCard(question) {
    const div = document.createElement('div');
    div.className = 'question-card group relative bg-white border-2 border-gray-200 rounded-lg p-4 hover:border-primary-300 transition-colors';
    div.dataset.questionId = question.uid;
    div.dataset.type = question.type;

    const config = question.config;
    const required = config.required ? '<span class="text-red-500">*</span>' : '';
    const typeLabel = question.type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());

    div.innerHTML = `
        <div class="absolute left-2 top-2 cursor-move opacity-0 group-hover:opacity-100 transition-opacity">
            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
            </svg>
        </div>
        
        <div class="ml-8 flex items-start justify-between">
            <div class="flex-1 pr-4">
                <div class="question-view">
                    <h4 class="text-base font-medium text-gray-900">${escapeHtml(displayLabel(question.type, config.label))} ${required}</h4>
                    ${config.description ? `<p class="mt-1 text-sm text-gray-500">${escapeHtml(config.description)}</p>` : ''}
                    <p class="mt-2 text-xs text-gray-400 uppercase tracking-wide">${typeLabel}</p>
                </div>
                <div class="question-edit hidden"></div>
            </div>
            
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
    `;

    return div;
}

/**
 * Edit question inline
 */
function editQuestion(card, question) {
    const viewDiv = card.querySelector('.question-view');
    const editDiv = card.querySelector('.question-edit');

    if (!viewDiv || !editDiv) return;

    const config = question.config;
    const type = question.type;

    // Build edit form HTML
    let editHtml = `<div class="space-y-3">`;

    if (type === 'divider') {
        editHtml += `
            <p class="text-sm text-gray-500">This is a visual divider. No content to edit.</p>
        `;
    } else if (type === 'paragraph') {
        editHtml += `
            <div>
                <label>Instructional Text *</label>
                <textarea class="mt-1 question-description" rows="4" maxlength="${MAX_DESCRIPTION_LENGTH}" placeholder="Enter instructions or guidance for the applicant">${escapeHtml(config.description || '')}</textarea>
            </div>
        `;
    } else if (type === 'heading') {
        editHtml += `
            <div>
                <label>Heading Text *</label>
                <input type="text" class="mt-1 question-label" maxlength="${MAX_LABEL_LENGTH}" value="${escapeHtml(config.label || '')}" placeholder="Enter section heading">
            </div>
            <div>
                <label>Description</label>
                <textarea class="mt-1 question-description" rows="2" maxlength="${MAX_DESCRIPTION_LENGTH}" placeholder="Optional subtitle or description">${escapeHtml(config.description || '')}</textarea>
            </div>
        `;
    } else {
        editHtml += `
            <div>
                <label>Question Label *</label>
                <input type="text" class="mt-1 question-label" maxlength="${MAX_LABEL_LENGTH}" value="${escapeHtml(config.label || '')}" placeholder="Enter your question">
            </div>
            <div>
                <label>Description</label>
                <textarea class="mt-1 question-description" rows="2" maxlength="${MAX_DESCRIPTION_LENGTH}" placeholder="Additional help text (optional)">${escapeHtml(config.description || '')}</textarea>
            </div>
            <div>
                <label class="flex items-center">
                    <input type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 question-required" ${config.required ? 'checked' : ''}>
                    <span class="ml-2 text-sm text-gray-700">Required field</span>
                </label>
            </div>
        `;
    }

    // Type-specific fields
    if (['text', 'textarea'].includes(type)) {
        editHtml += `
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label>Min Length</label>
                    <input type="number" class="mt-1 question-min-length" value="${config.validation?.min_length || ''}" placeholder="0">
                </div>
                <div>
                    <label>Max Length</label>
                    <input type="number" class="mt-1 question-max-length" value="${config.validation?.max_length || ''}" placeholder="Unlimited">
                </div>
            </div>
            <div>
                <label>Validation Pattern (Regex)</label>
                <input type="text" class="mt-1 question-pattern" value="${escapeHtml(config.validation?.pattern || '')}" placeholder="e.g. ^[A-Z]{2}[0-9]{4}$">
                <p class="mt-1 text-xs text-gray-400">Regular expression to validate input format</p>
            </div>
        `;
    }

    if (type === 'number') {
        editHtml += `
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label>Minimum</label>
                    <input type="number" class="mt-1 question-min" value="${config.validation?.min || ''}">
                </div>
                <div>
                    <label>Maximum</label>
                    <input type="number" class="mt-1 question-max" value="${config.validation?.max || ''}">
                </div>
            </div>
        `;
    }

    if (['select', 'radio', 'checkbox_group', 'multiselect'].includes(type)) {
        const referencedValues = question.referenced_option_values || [];
        
        editHtml += `
            <div>
                <label>Options</label>
                <div class="space-y-2 question-options">
        `;
        
        const options = config.options || [{ value: 'option1', label: 'Option 1' }];
        options.forEach((opt, index) => {
            const isLocked = referencedValues.includes(opt.value);
            editHtml += `
                <div class="flex items-center gap-2 option-row" data-value="${escapeHtml(opt.value)}">
                    <input type="text" class="flex-1 option-label ${isLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''}" value="${escapeHtml(opt.label)}" placeholder="Option label" data-index="${index}" ${isLocked ? 'readonly title="This option is used in conditional logic and cannot be edited"' : ''}>
                    ${isLocked ? `<svg class="h-5 w-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="This option is used in conditional logic and cannot be edited"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>` : `<button type="button" class="text-red-600 hover:text-red-800 remove-option">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>`}
                </div>
            `;
        });
        
        editHtml += `
                </div>
                <button type="button" class="mt-2 text-sm text-primary-600 hover:text-primary-700 add-option">+ Add Option</button>
            </div>
        `;
    }

    // Conditional visibility (for all non-display types, only if eligible source questions exist)
    const currentCardEl = canvas.querySelector(`.question-card[data-question-id="${question.uid}"]`);
    const hasEligibleSources = Array.from(canvas.querySelectorAll('.question-card')).some(q =>
        q !== currentCardEl && ['select', 'radio', 'checkbox_group', 'multiselect'].includes(q.dataset.type)
    );

    if (!['heading', 'paragraph', 'divider'].includes(type) && hasEligibleSources) {
        const hasVisibility = config.visibility && config.visibility.question_uid;
        editHtml += `
            <div class="visibility-config border-t border-gray-200 pt-3">
                <label class="flex items-center">
                    <input type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 visibility-toggle" ${hasVisibility ? 'checked' : ''}>
                    <span class="ml-2 text-sm font-medium text-gray-700">Show conditionally</span>
                </label>
                <div class="visibility-fields ${hasVisibility ? '' : 'hidden'} mt-3">
                    <div class="bg-gray-50 rounded-lg p-3 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">When this question:</label>
                            <select class="visibility-question">
                                <option value="">Select a question…</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Has one of these answers:</label>
                            <div class="visibility-values rounded-md border border-gray-200 bg-white px-2.5 py-1.5 max-h-40 overflow-y-auto"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    editHtml += `
            <div class="flex items-center gap-2 pt-3 border-t border-gray-200">
                <button type="button" class="btn btn-primary btn-sm save-question">Save</button>
                <button type="button" class="btn btn-secondary btn-sm cancel-edit">Cancel</button>
            </div>
        </div>
    `;

    editDiv.innerHTML = editHtml;

    // Show edit form, hide view
    viewDiv.classList.add('hidden');
    editDiv.classList.remove('hidden');

    // Attach live character counters to label and description fields
    const labelInput = editDiv.querySelector('.question-label');
    if (labelInput) attachCharCounter(labelInput, MAX_LABEL_LENGTH);
    const descInput = editDiv.querySelector('.question-description');
    if (descInput) attachCharCounter(descInput, MAX_DESCRIPTION_LENGTH);

    // Attach option management handlers (these are scoped to the edit form)
    if (['select', 'radio', 'checkbox_group', 'multiselect'].includes(type)) {
        const optionsContainer = editDiv.querySelector('.question-options');
        const addOptionBtn = editDiv.querySelector('.add-option');

        addOptionBtn.addEventListener('click', () => {
            const newRow = document.createElement('div');
            newRow.className = 'flex items-center gap-2 option-row';
            newRow.innerHTML = `
                <input type="text" class="flex-1 option-label" value="" placeholder="Option label">
                <button type="button" class="text-red-600 hover:text-red-800 remove-option">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            `;
            optionsContainer.appendChild(newRow);
            newRow.querySelector('.remove-option').addEventListener('click', () => newRow.remove());
        });

        editDiv.querySelectorAll('.remove-option').forEach(btn => {
            btn.addEventListener('click', () => {
                if (optionsContainer.children.length > 1) {
                    btn.closest('.option-row').remove();
                } else {
                    toast('warning', 'At least one option is required');
                }
            });
        });
    }

    // Initialize conditional visibility controls
    if (!['heading', 'paragraph', 'divider'].includes(type) && hasEligibleSources) {
        initVisibilityControls(editDiv, question, config);
    }

    // Save handler
    editDiv.querySelector('.save-question').addEventListener('click', async () => {
        await saveQuestion(card, question.uid, type);
    });

    // Cancel handler
    editDiv.querySelector('.cancel-edit').addEventListener('click', () => {
        viewDiv.classList.remove('hidden');
        editDiv.classList.add('hidden');
    });
}

/**
 * Save question changes
 */
async function saveQuestion(card, questionUid, type) {
    if (isProcessing || isReadOnly) {
        if (isReadOnly) {
            toast('error', 'Cannot edit active version. Please create a new draft.');
        }
        return;
    }

    isProcessing = true;

    try {
        const editDiv = card.querySelector('.question-edit');
        
        const label = editDiv.querySelector('.question-label')?.value || '';
        const description = editDiv.querySelector('.question-description')?.value || '';
        const required = editDiv.querySelector('.question-required')?.checked || false;

        const config = {
            label,
            description,
            required,
            validation: {}
        };

        // Gather validation rules
        if (['text', 'textarea'].includes(type)) {
            config.validation.min_length = parseInt(editDiv.querySelector('.question-min-length')?.value) || null;
            config.validation.max_length = parseInt(editDiv.querySelector('.question-max-length')?.value) || null;
            config.validation.pattern = editDiv.querySelector('.question-pattern')?.value.trim() || null;
        }

        if (type === 'number') {
            config.validation.min = parseFloat(editDiv.querySelector('.question-min')?.value) || null;
            config.validation.max = parseFloat(editDiv.querySelector('.question-max')?.value) || null;
        }

        // Gather options (preserve original values for locked options via data-value)
        if(['select', 'radio', 'checkbox_group', 'multiselect'].includes(type)) {
            const optionRows = editDiv.querySelectorAll('.option-row');
            config.options = Array.from(optionRows).map((row, index) => {
                const input = row.querySelector('.option-label');
                const label = input?.value || `Option ${index + 1}`;
                const value = row.dataset.value || `option${index + 1}`;
                return { value, label };
            }).filter(opt => opt.label.trim() !== '');
        }

        // Gather visibility condition
        if (!['heading', 'paragraph', 'divider'].includes(type) && editDiv.querySelector('.visibility-toggle')) {
            const visToggle = editDiv.querySelector('.visibility-toggle');
            if (visToggle && visToggle.checked) {
                const visQuestion = editDiv.querySelector('.visibility-question')?.value;
                const visCheckboxes = editDiv.querySelectorAll('.visibility-value-checkbox:checked');
                const visValues = Array.from(visCheckboxes).map(cb => cb.value);
                if (!visQuestion || visValues.length === 0) {
                    toast('warning', 'Conditional visibility is enabled but incomplete. Please select a question and at least one answer, or uncheck "Show conditionally".');
                    isProcessing = false;
                    return;
                }
                config.visibility = {
                    question_uid: visQuestion,
                    values: visValues
                };
            }
        }

        await api(`/api/questions/${questionUid}`, 'POST', { config });
        
        // Update view
        const viewDiv = card.querySelector('.question-view');
        const requiredMark = required ? '<span class="text-red-500">*</span>' : '';
        
        // Build conditional visibility badge if present
        let conditionalBadge = '';
        if (config.visibility && config.visibility.question_uid) {
            // Find the source question label
            let sourceLabel = 'a question';
            const allQuestions = canvas.querySelectorAll('.question-card');
            allQuestions.forEach(q => {
                if (q.dataset.questionId === config.visibility.question_uid) {
                    const labelEl = q.querySelector('.question-view h4');
                    if (labelEl) {
                        sourceLabel = '"' + labelEl.textContent.trim().replace('*', '').trim() + '"';
                    }
                }
            });
            
            conditionalBadge = `
                <div class="mt-2 inline-flex items-center px-2 py-1 rounded-md bg-amber-50 border border-amber-200">
                    <svg class="h-3.5 w-3.5 text-amber-600 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span class="text-xs text-amber-700 font-medium">Conditional: depends on ${escapeHtml(sourceLabel)}</span>
                </div>
            `;
        }
        
        viewDiv.innerHTML = `
            <h4 class="text-base font-medium text-gray-900">${escapeHtml(displayLabel(type, label))} ${requiredMark}</h4>
            ${description ? `<p class="mt-1 text-sm text-gray-500">${escapeHtml(description)}</p>` : ''}
            <p class="mt-2 text-xs text-gray-400 uppercase tracking-wide">${type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>
            ${conditionalBadge}
        `;
        
        viewDiv.classList.remove('hidden');
        editDiv.classList.add('hidden');
        
        toast('success', 'Question saved');
    } catch (error) {
        console.error('Save question error:', error);
    } finally {
        isProcessing = false;
    }
}

/**
 * Initialize conditional visibility controls
 * Populates the question dropdown with eligible source questions and loads
 * saved values when editing an existing condition.
 */
function initVisibilityControls(editDiv, question, config) {
    const visToggle = editDiv.querySelector('.visibility-toggle');
    const visFields = editDiv.querySelector('.visibility-fields');
    const visQuestionSelect = editDiv.querySelector('.visibility-question');
    const visValuesContainer = editDiv.querySelector('.visibility-values');
    
    if (!visToggle || !visFields) return;
    
    // Toggle visibility fields
    visToggle.addEventListener('change', () => {
        visFields.classList.toggle('hidden', !visToggle.checked);
    });

    const showVisPlaceholder = () => {
        visValuesContainer.innerHTML = '<p class="text-xs text-gray-400 py-1">Select a question to load answers</p>';
    };
    
    // Collect eligible source questions (selection-based types, excluding self)
    const currentCard = editDiv.closest('.question-card');
    const allQuestions = canvas.querySelectorAll('.question-card');
    
    allQuestions.forEach(q => {
        if (q === currentCard) return;
        const qType = q.dataset.type;
        if (['select', 'radio', 'checkbox_group', 'multiselect'].includes(qType)) {
            const label = q.querySelector('.question-view h4')?.textContent.trim().replace('*', '').trim() || 'Untitled';
            const opt = document.createElement('option');
            opt.value = q.dataset.questionId;
            opt.textContent = label;
            if (config.visibility?.question_uid === q.dataset.questionId) {
                opt.selected = true;
            }
            visQuestionSelect.appendChild(opt);
        }
    });
    
    // Load option checkboxes for a source question
    const loadValuesForQuestion = async (questionUid) => {
        visValuesContainer.innerHTML = '';
        if (!questionUid) {
            showVisPlaceholder();
            return;
        }
        
        try {
            const result = await api(`/api/questions/${questionUid}`, 'GET');
            const options = result.question.config.options || [];
            
            if (options.length === 0) {
                visValuesContainer.innerHTML = '<p class="text-xs text-gray-400 py-1">No options available</p>';
                return;
            }
            
            const savedValues = config.visibility?.values || [];
            
            options.forEach(opt => {
                const label = document.createElement('label');
                label.className = 'flex items-center text-sm last:mb-0 last:pb-0';
                label.innerHTML = `
                    <input type="checkbox"
                           class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 visibility-value-checkbox"
                           value="${escapeHtml(opt.value)}"
                           ${savedValues.includes(opt.value) ? 'checked' : ''}>
                    <span class="ml-2 text-gray-700">${escapeHtml(opt.label)}</span>
                `;
                visValuesContainer.appendChild(label);
            });
        } catch (error) {
            console.error('Failed to load question options:', error);
            visValuesContainer.innerHTML = '<p class="text-xs text-red-500 py-1">Failed to load options</p>';
        }
    };
    
    // Load values on dropdown change
    visQuestionSelect.addEventListener('change', () => {
        loadValuesForQuestion(visQuestionSelect.value);
    });
    
    // If there's a saved condition, load its values immediately
    if (config.visibility?.question_uid) {
        loadValuesForQuestion(config.visibility.question_uid);
    } else {
        showVisPlaceholder();
    }
}

/**
 * Delete question
 */
async function deleteQuestion(card, questionUid) {
    if (isProcessing || isReadOnly) {
        if (isReadOnly) {
            toast('error', 'Cannot edit active version. Please create a new draft.');
        }
        return;
    }

    isProcessing = true;
    
    try {
        await api(`/api/questions/${questionUid}`, 'DELETE');
        
        // Remove the card from DOM
        card.remove();
        
        // Show empty state if no questions left
        if (canvas.querySelectorAll('.question-card').length === 0) {
            canvas.innerHTML = `
                <div class="empty-state" id="empty-state">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <p class="mt-2 text-sm">No questions yet. Click a field type on the left to add your first question.</p>
                </div>
            `;
        }
        
        toast('success', 'Question deleted');
    } catch (error) {
        console.error('Delete error:', error);
    } finally {
        // Reset processing flag after a short delay to allow DOM to settle
        setTimeout(() => {
            isProcessing = false;
        }, 100);
    }
}

/**
 * Initialize event listeners using event delegation
 * This prevents duplicate listeners and handles dynamically created elements
 */
function init() {
    // Prevent multiple initializations
    if (initialized) return;
    initialized = true;

    // Remove loading overlay and show builder content
    const loadingEl = document.getElementById('builder-loading');
    const contentEl = document.getElementById('builder-content');
    if (loadingEl) loadingEl.remove();
    if (contentEl) contentEl.classList.remove('hidden');

    // Initialize sortable
    initSortable();

    // Build preset palette buttons from registry
    buildPresetPalette();

    // Event delegation for add field buttons (regular palette)
    const fieldPalette = document.getElementById('field-palette');
    if (fieldPalette && !isReadOnly) {
        fieldPalette.addEventListener('click', (e) => {
            const btn = e.target.closest('.add-field-btn');
            if (btn && !isProcessing) {
                addQuestion(btn.dataset.type);
            }
        });
    }

    // Event delegation for preset palette buttons
    const presetPalette = document.getElementById('preset-palette');
    if (presetPalette && !isReadOnly) {
        presetPalette.addEventListener('click', (e) => {
            const btn = e.target.closest('.add-field-btn');
            if (btn && !isProcessing) {
                addQuestion(btn.dataset.type, btn.dataset.preset || null);
            }
        });
    }

    // Event delegation for question card actions
    // This single listener handles all cards (existing and dynamically added)
    if (canvas && !isReadOnly) {
        canvas.addEventListener('click', async (e) => {
            // Check if processing to prevent race conditions
            if (isProcessing) return;

            const editBtn = e.target.closest('.edit-question');
            const deleteBtn = e.target.closest('.delete-question');
            
            if (editBtn) {
                const card = editBtn.closest('.question-card');
                const questionId = card.dataset.questionId;
                
                // Fetch full question data
                try {
                    const result = await api(`/api/questions/${questionId}`, 'GET');
                    editQuestion(card, result.question);
                } catch (error) {
                    toast('error', 'Failed to load question');
                }
            } else if (deleteBtn) {
                const card = deleteBtn.closest('.question-card');
                const questionId = card.dataset.questionId;
                
                if (await confirm('Delete this question? This action cannot be undone.')) {
                    await deleteQuestion(card, questionId);
                }
            }
        });
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
