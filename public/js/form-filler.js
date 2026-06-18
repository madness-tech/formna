/**
 * Form Filler Module
 * 
 * Handles form filling with auto-save, local storage backup, and file uploads.
 */

import { api, toast, debounce } from './common.js';

let formConfig = {};
let lastSavedData = null;
let saveCooldownTimer = null;
let isSaving = false;
let hasInitializedBaseline = false;
let userHasInteracted = false; // Track if user has actually started editing
let sessionUploadedFileIds = []; // File IDs uploaded during this editing session

/**
 * Initialize form filler
 */
export function initFormFiller(config) {
    formConfig = config;
    
    // If server cleared a stale draft (version mismatch), purge localStorage too
    if (config.draftCleared) {
        clearFormCache(config.formUuid);
    }
    
    // When editing an existing submission, the form is pre-populated with
    // submission data server-side. Skip all draft loading to avoid overwriting
    // the submission answers with stale or unrelated draft data.
    if (config.isEditing || config.hasValidationErrors) {
        ensureBaselineSaveState();
    } else {
        loadDraftFromServer();
    }
    
    // Set up auto-save on input changes (localStorage only when editing)
    setupAutoSave();
    
    // Draft save/discard buttons are hidden when editing; skip wiring them
    if (!config.isEditing) {
        setupManualSave();
        setupDiscardDraft();
    } else {
        setupCancelEdit();
    }
    
    // Set up file uploads
    setupFileUploads();
    
    // Wire up remove buttons for file indicators already rendered by the server
    // (e.g. pre-existing attachments when editing a submission)
    wireExistingFileIndicators();
    
    // Set up form submission
    setupFormSubmission();
    
    // Handle page unload
    setupUnloadWarning();
    
    // Set up conditional visibility ("show if" logic)
    setupConditionalVisibility();
}

/**
 * Load draft from server
 */
async function loadDraftFromServer() {
    try {
        const response = await api(`/api/drafts/${formConfig.formUuid}`, 'GET');
        
        if (response.data) {
            populateForm(response.data);
            // Show file indicators for files referenced in the draft
            if (response.files) {
                for (const [fileId, meta] of Object.entries(response.files)) {
                    // Find which question has this file_id
                    for (const [questionId, val] of Object.entries(response.data)) {
                        if (String(val) === String(fileId)) {
                            showFileIndicator(questionId, meta.original_name, meta.size_bytes);
                            break;
                        }
                    }
                }
            }
            lastSavedData = JSON.stringify(response.data);
            const msg = window.formTranslations?.draftLoaded || 'Draft loaded from server';
            toast('info', msg, 4000, 'bottom-center');
            userHasInteracted = true; // User has previously saved data
            setSaveStateUpToDate();
            toggleDiscardButton(true);
        } else {
            // Try loading from localStorage as fallback
            loadFromLocalStorage();
        }
    } catch (error) {
        console.error('Failed to load draft:', error);
        loadFromLocalStorage();
    } finally {
        ensureBaselineSaveState();
    }
}

/**
 * Load from localStorage
 */
function loadFromLocalStorage() {
    const key = `form_draft_${formConfig.formUuid}`;
    const stored = localStorage.getItem(key);
    
    if (stored) {
        try {
            const data = JSON.parse(stored);
            populateForm(data);
            lastSavedData = JSON.stringify(data);
            const msg = window.formTranslations?.draftRestored || 'Draft restored from local storage';
            toast('info', msg, 4000, 'bottom-center');
            userHasInteracted = true; // User has previously saved data
            setSaveStateUpToDate();
            toggleDiscardButton(true);
        } catch (e) {
            console.error('Failed to parse localStorage data:', e);
        }
    }

    ensureBaselineSaveState();
}

/**
 * Populate form with data
 */
function populateForm(data) {
    Object.keys(data).forEach(questionId => {
        const value = data[questionId];
        const input = document.querySelector(`[name="answers[${questionId}]"], [name="answers[${questionId}][]"]`);
        
        if (!input) return;
        
        const type = input.type || input.tagName.toLowerCase();
        
        if (type === 'checkbox' && !input.name.includes('[]')) {
            input.checked = !!value;
        } else if (input.name.includes('[]')) {
            // Checkbox group or multiselect
            const inputs = document.querySelectorAll(`[name="answers[${questionId}][]"]`);
            const values = Array.isArray(value) ? value : [value];
            inputs.forEach(inp => {
                if (inp.type === 'checkbox') {
                    inp.checked = values.includes(inp.value);
                } else if (inp.tagName === 'SELECT') {
                    Array.from(inp.options).forEach(opt => {
                        opt.selected = values.includes(opt.value);
                    });
                }
            });
        } else if (type === 'radio') {
            const radios = document.querySelectorAll(`[name="answers[${questionId}]"]`);
            radios.forEach(radio => {
                radio.checked = radio.value === value;
            });
        } else {
            input.value = value;
        }
    });
    
    // Trigger visibility evaluation after populating form
    // Need to dispatch from window to reach the listener
    setTimeout(() => {
        const form = document.getElementById('submission-form');
        if (form) {
            form.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }, 100);
}

/**
 * Get current form data
 */
function getFormData() {
    const form = document.getElementById('submission-form');
    const formData = new FormData(form);
    const data = {};
    
    // Get all question containers
    const questions = document.querySelectorAll('.question-container');
    
    questions.forEach(container => {
        const questionId = container.dataset.questionId;
        if (!questionId) return;
        
        // Find all inputs for this question
        const inputs = container.querySelectorAll(`[name="answers[${questionId}]"], [name="answers[${questionId}][]"]`);
        
        if (inputs.length === 0) return;
        
        const firstInput = inputs[0];
        
        if (firstInput.name.includes('[]')) {
            // Multiple values (checkbox group, multiselect)
            const values = [];
            inputs.forEach(input => {
                if (input.type === 'checkbox' && input.checked) {
                    values.push(input.value);
                } else if (input.tagName === 'SELECT') {
                    Array.from(input.selectedOptions).forEach(opt => {
                        values.push(opt.value);
                    });
                }
            });
            if (values.length > 0) {
                data[questionId] = values;
            }
        } else if (firstInput.type === 'radio') {
            // Radio group
            const checked = container.querySelector(`[name="answers[${questionId}]"]:checked`);
            if (checked) {
                data[questionId] = checked.value;
            }
        } else if (firstInput.type === 'checkbox') {
            // Single checkbox
            data[questionId] = firstInput.checked ? '1' : '0';
        } else {
            // Regular input
            if (firstInput.value) {
                data[questionId] = firstInput.value;
            }
        }
    });
    
    return data;
}

/**
 * Set up auto-save on input changes
 */
function setupAutoSave() {
    const form = document.getElementById('submission-form');
    
    const debouncedSave = debounce(() => {
        const data = getFormData();
        const dataStr = JSON.stringify(data);
        
        // Only save if data changed
        if (dataStr !== lastSavedData) {
            saveToLocalStorage(data);
            // Skip server-side draft creation when editing an existing submission
            // to avoid creating unintentional drafts that conflict with the submission
            if (!formConfig.isEditing) {
                saveDraftToServer(data);
            } else {
                lastSavedData = dataStr;
            }
        } else {
            setSaveStateUpToDate();
        }
    }, 1000);

    const markDirty = (e) => {
        // Clear any displayed validation error for the field the user just edited
        const container = e.target?.closest('.question-container');
        if (container) {
            container.querySelectorAll('.form-error, .js-validation-error').forEach(el => el.remove());
        }

        userHasInteracted = true; // User has now started editing

        // Only mark as dirty if data has actually changed from last save
        // This prevents false positives from change events fired on blur
        // (e.g. clicking the save button causes text input to blur → change event)
        const currentData = JSON.stringify(getFormData());
        if (currentData === lastSavedData) {
            return;
        }

        setSaveButtonState(false);
        const msg = window.formTranslations?.unsavedChanges || 'Unsaved changes';
        showAutoSaveIndicator(msg, 'info');
        debouncedSave();
    };
    
    // Listen to all input changes
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
}

/**
 * Save to localStorage immediately
 */
function saveToLocalStorage(data) {
    const key = `form_draft_${formConfig.formUuid}`;
    localStorage.setItem(key, JSON.stringify(data));
}

/**
 * Clear all cache for a form (localStorage)
 */
export function clearFormCache(formUuid) {
    const key = `form_draft_${formUuid}`;
    localStorage.removeItem(key);
    console.log('Form cache cleared for:', formUuid);
}

/**
 * Clear all form caches (useful for cleanup)
 */
export function clearAllFormCaches() {
    const keysToRemove = [];
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith('form_draft_')) {
            keysToRemove.push(key);
        }
    }
    keysToRemove.forEach(key => localStorage.removeItem(key));
    console.log('Cleared', keysToRemove.length, 'form draft caches');
}

/**
 * Save draft to server
 */
async function saveDraftToServer(data) {
    if (isSaving) return;
    isSaving = true;
    const savingMsg = window.formTranslations?.saving || 'Saving...';
    showAutoSaveIndicator(savingMsg, 'saving');
    setSaveButtonState(true);
    
    try {
        const response = await api(`/api/drafts/${formConfig.formUuid}`, 'POST', { data });
        
        lastSavedData = JSON.stringify(data);
        setSaveStateUpToDate();
        toggleDiscardButton(true);
    } catch (error) {
        console.error('Auto-save failed:', error);
        const errorMsg = window.formTranslations?.saveFailed || 'Save failed - using local backup';
        showAutoSaveIndicator(errorMsg, 'error');
        setTimeout(() => hideAutoSaveIndicator(), 3000);
        setSaveButtonState(false);
    }
    isSaving = false;
}

/**
 * Set up manual save button
 */
function setupManualSave() {
    const saveBtn = document.getElementById('save-draft-btn');
    if (!saveBtn) return;
    
    saveBtn.addEventListener('click', async () => {
        if (saveBtn.disabled || isSaving) return;
        const data = getFormData();
        const dataStr = JSON.stringify(data);

        // Skip if data hasn't changed since last save
        if (dataStr === lastSavedData) {
            setSaveStateUpToDate();
            return;
        }

        saveToLocalStorage(data);
        
        const savingMsg = window.formTranslations?.saving || 'Saving...';
        showAutoSaveIndicator(savingMsg, 'saving');
        setSaveButtonState(true);
        
        try {
            await api(`/api/drafts/${formConfig.formUuid}`, 'POST', { data });
            lastSavedData = dataStr;
            const successMsg = window.formTranslations?.draftSavedSuccess || 'Draft saved successfully!';
            toast('success', successMsg);
            setSaveStateUpToDate();
            toggleDiscardButton(true);
        } catch (error) {
            console.error('Save failed:', error);
            const errorMsg = window.formTranslations?.draftSaveFailed || 'Failed to save draft';
            toast('error', errorMsg);
            hideAutoSaveIndicator();
            setSaveButtonState(false);
        }
    });
}

/**
 * Set up discard draft button
 */
function setupDiscardDraft() {
    const discardBtn = document.getElementById('discard-draft-btn');
    if (!discardBtn) return;

    discardBtn.addEventListener('click', async () => {
        const confirmMsg = window.formTranslations?.discardConfirm || 'Are you sure you want to discard this draft? All saved progress will be lost.';
        if (!confirm(confirmMsg)) {
            return;
        }

        discardBtn.disabled = true;

        try {
            await api(`/api/drafts/${formConfig.formUuid}`, 'DELETE');
            clearFormCache(formConfig.formUuid);
            const successMsg = window.formTranslations?.draftDiscarded || 'Draft discarded';
            toast('success', successMsg);
            // Redirect to dashboard after short delay
            setTimeout(() => { window.location.href = '/dashboard'; }, 600);
        } catch (error) {
            console.error('Failed to discard draft:', error);
            discardBtn.disabled = false;
        }
    });
}

/**
 * Set up cancel edit button — cleans up unlinked uploads from this session,
 * clears the localStorage draft, and navigates back to the submission view.
 */
function setupCancelEdit() {
    const cancelBtn = document.getElementById('cancel-edit-btn');
    if (!cancelBtn) return;

    const destinationUrl = cancelBtn.href;

    cancelBtn.addEventListener('click', async (e) => {
        e.preventDefault();

        const confirmMsg = window.formTranslations?.cancelEditConfirm
            || 'Discard your changes? Any new uploads will be removed and the submission will remain as-is.';
        if (!confirm(confirmMsg)) return;

        // Delete any files uploaded during this editing session (they are unlinked)
        const deletePromises = sessionUploadedFileIds.map(fileId =>
            api(`/api/files/${fileId}`, 'DELETE').catch(err => {
                console.error('Failed to delete session file:', fileId, err);
            })
        );
        await Promise.all(deletePromises);

        // Clear localStorage draft for this form
        clearFormCache(formConfig.formUuid);

        // Reset baseline so the beforeunload handler does not block navigation
        lastSavedData = JSON.stringify(getFormData());

        window.location.href = destinationUrl;
    });
}

/**
 * Show/hide the discard draft button based on whether a draft exists
 */
function toggleDiscardButton(hasDraft) {
    const discardBtn = document.getElementById('discard-draft-btn');
    if (!discardBtn) return;

    if (hasDraft) {
        discardBtn.classList.remove('hidden');
    } else {
        discardBtn.classList.add('hidden');
    }
}

/**
 * Format bytes as a human-readable file size string
 */
function formatFileSize(bytes) {
    bytes = parseInt(bytes, 10) || 0;
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

/**
 * Bind the remove-file click handler for a question's file indicator.
 * Safe to call multiple times — skips if already bound.
 */
function bindFileRemoveButton(questionId) {
    const info = document.getElementById(`q_${questionId}_file_info`);
    if (!info) return;

    const removeBtn = info.querySelector('[data-file-remove]');
    if (!removeBtn || removeBtn.dataset.bound) return;

    removeBtn.dataset.bound = '1';
    removeBtn.addEventListener('click', async () => {
        const hiddenInput = document.getElementById(`q_${questionId}_file_id`);
        const fileId = hiddenInput?.value;

        // Ask server to remove/detach the file.
        // For submission-linked files the server only clears the UI
        // indicator — actual deletion is deferred to form submission.
        if (fileId) {
            try {
                await api(`/api/files/${fileId}`, 'DELETE');
            } catch (e) {
                console.error('Failed to delete file:', e);
            }
        }

        hideFileIndicator(questionId);
        if (hiddenInput) {
            hiddenInput.value = '';
            hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        // Reset the file input so the same file can be re-selected
        const fileInput = document.querySelector(`input[type="file"][data-question-id="${questionId}"]`);
        if (fileInput) fileInput.value = '';
    });
}

/**
 * Wire up remove buttons for file indicators that were rendered server-side
 * (e.g. pre-existing attachments when editing a submission or after a
 * validation-error re-render).
 */
function wireExistingFileIndicators() {
    document.querySelectorAll('[data-file-remove]').forEach(removeBtn => {
        if (removeBtn.dataset.bound) return;
        const info = removeBtn.closest('[id$="_file_info"]');
        if (!info) return;
        const match = info.id.match(/^q_(\d+)_file_info$/);
        if (match) {
            bindFileRemoveButton(match[1]);
        }
    });
}

/**
 * Show the file indicator for a question, displaying filename and size
 */
function showFileIndicator(questionId, filename, sizeBytes) {
    const info = document.getElementById(`q_${questionId}_file_info`);
    if (!info) return;
    const label = info.querySelector('[data-file-label]');
    if (label) {
        const text = (window.formTranslations?.fileAttached || 'Attached: {filename} ({size})')
            .replace('{filename}', filename)
            .replace('{size}', formatFileSize(sizeBytes));
        label.textContent = text;
    }
    info.classList.remove('hidden');
    info.classList.add('inline-flex');

    bindFileRemoveButton(questionId);
}

/**
 * Hide the file indicator for a question
 */
function hideFileIndicator(questionId) {
    const info = document.getElementById(`q_${questionId}_file_info`);
    if (!info) return;
    info.classList.add('hidden');
    info.classList.remove('inline-flex');
}

/**
 * Set up file uploads
 */
function setupFileUploads() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            const questionId = input.dataset.questionId;
            const progressBar = document.getElementById(`q_${questionId}_progress`);
            const hiddenInput = document.getElementById(`q_${questionId}_file_id`);
            
            // Client-side file size check before uploading
            const maxSizeMb = parseFloat(input.dataset.maxSizeMb);
            if (maxSizeMb && file.size > maxSizeMb * 1024 * 1024) {
                const sizeLabel = maxSizeMb + ' MB';
                const errorMsg = (window.formTranslations?.fileTooLarge || 'File is too large. Maximum size: {size}.')
                    .replace('{size}', sizeLabel);
                toast('error', errorMsg);
                input.value = '';
                return;
            }
            
            // Show progress
            if (progressBar) {
                progressBar.classList.remove('hidden');
            }
            
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('question_id', questionId);
                
                // Include submission_id when editing so the server uses the
                // edit path instead of the new-submission path (which checks
                // the submission limit and would reject the upload).
                if (formConfig.isEditing && formConfig.submissionId) {
                    formData.append('submission_id', formConfig.submissionId);
                }
                
                // Upload file
                const response = await uploadFile(formData, (percent) => {
                    if (progressBar) {
                        const bar = progressBar.querySelector('div');
                        if (bar) bar.style.width = percent + '%';
                    }
                });
                
                // Store file ID in hidden input and trigger auto-save
                if (hiddenInput && response.file_id) {
                    hiddenInput.value = response.file_id;
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                
                // Track uploads during editing so cancel/submit can clean up orphans
                if (formConfig.isEditing && response.file_id) {
                    sessionUploadedFileIds.push(String(response.file_id));
                }
                
                // Show file indicator
                showFileIndicator(questionId, file.name, file.size);
                
                const successMsg = window.formTranslations?.fileUploadedSuccess?.replace('{filename}', file.name)
                    || `File "${file.name}" uploaded successfully!`;
                toast('success', successMsg);
                
                // Hide progress after 1 second
                setTimeout(() => {
                    if (progressBar) {
                        progressBar.classList.add('hidden');
                    }
                }, 1000);
                
            } catch (error) {
                console.error('Upload failed:', error);
                const errorMsg = window.formTranslations?.fileUploadFailed || 'File upload failed';
                toast('error', error.message || errorMsg);
                
                if (progressBar) {
                    progressBar.classList.add('hidden');
                }
                
                // Reset file input
                input.value = '';
            }
        });
    });
}

/**
 * Upload file with progress
 */
function uploadFile(formData, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                onProgress(percent);
            }
        });
        
        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    reject(new Error('Invalid response'));
                }
            } else {
                try {
                    const error = JSON.parse(xhr.responseText);
                    reject(new Error(error.error || 'Upload failed'));
                } catch (e) {
                    reject(new Error('Upload failed'));
                }
            }
        });
        
        xhr.addEventListener('error', () => {
            reject(new Error('Network error'));
        });
        
        xhr.open('POST', '/api/files/upload');
        if (csrfToken) {
            xhr.setRequestHeader('X-CSRF-Token', csrfToken);
        }
        xhr.send(formData);
    });
}

/**
 * Show auto-save indicator
 */
function showAutoSaveIndicator(message, type) {
    const indicator = document.getElementById('autosave-indicator');
    if (!indicator) return;
    
    indicator.innerHTML = '';
    indicator.classList.remove(
        'hidden',
        'text-gray-600', 'text-green-600', 'text-blue-600', 'text-red-600',
        'bg-gray-50', 'bg-green-50', 'bg-blue-50', 'bg-red-50',
        'border-gray-200', 'border-green-200', 'border-blue-200', 'border-red-200'
    );

    const styles = {
        saving: { text: 'text-blue-700', bg: 'bg-blue-50', border: 'border-blue-200', iconBg: 'bg-blue-100', iconText: 'text-blue-700', icon: 'spinner' },
        success: { text: 'text-green-700', bg: 'bg-green-50', border: 'border-green-200', iconBg: 'bg-green-100', iconText: 'text-green-700', icon: 'check' },
        error: { text: 'text-red-700', bg: 'bg-red-50', border: 'border-red-200', iconBg: 'bg-red-100', iconText: 'text-red-700', icon: 'alert' },
        info: { text: 'text-gray-700', bg: 'bg-gray-50', border: 'border-gray-200', iconBg: 'bg-gray-200', iconText: 'text-gray-700', icon: 'dot' }
    };

    const variant = styles[type] || styles.info;

    indicator.classList.add(
        'inline-flex', 'items-center', 'gap-2', 'text-sm', 'rounded-full', 'border', 'px-3', 'py-1.5',
        variant.text, variant.bg, variant.border
    );

    const iconWrapper = document.createElement('span');
    iconWrapper.className = `inline-flex items-center justify-center w-5 h-5 rounded-full ${variant.iconBg} ${variant.iconText}`;

    const iconSvg = {
        check: '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>',
        alert: '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v3m0 3h.01M10.29 3.86L1.82 18a1.5 1.5 0 001.29 2.25h17.78A1.5 1.5 0 0022.18 18L13.71 3.86a1.5 1.5 0 00-2.42 0z" /></svg>',
        spinner: '<svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>',
        dot: '<svg class="w-2.5 h-2.5" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4" /></svg>'
    };

    iconWrapper.innerHTML = iconSvg[variant.icon];
    indicator.appendChild(iconWrapper);

    const textNode = document.createElement('span');
    textNode.textContent = message;
    indicator.appendChild(textNode);
}

/**
 * Hide auto-save indicator
 */
function hideAutoSaveIndicator() {
    const indicator = document.getElementById('autosave-indicator');
    if (!indicator) return;
    indicator.innerHTML = '';
    indicator.className = 'hidden';
}

function scheduleIndicatorHide() {
    if (saveCooldownTimer) clearTimeout(saveCooldownTimer);
    saveCooldownTimer = setTimeout(() => hideAutoSaveIndicator(), 2000);
}

function setSaveButtonState(isUpToDate) {
    const saveBtn = document.getElementById('save-draft-btn');
    if (!saveBtn) return;
    saveBtn.disabled = isUpToDate;
}

function setSaveStateUpToDate() {
    setSaveButtonState(true);
    // Only show "Up to date" indicator if user has actually interacted with the form
    if (userHasInteracted) {
        const msg = window.formTranslations?.upToDate || 'Up to date';
        showAutoSaveIndicator(msg, 'success');
        scheduleIndicatorHide();
    }
}

function ensureBaselineSaveState() {
    if (hasInitializedBaseline) return;
    const currentData = getFormData();
    lastSavedData = JSON.stringify(currentData);
    // Don't show "Up to date" for fresh forms - setSaveStateUpToDate will check userHasInteracted
    setSaveStateUpToDate();
    hasInitializedBaseline = true;
}

/**
 * Format time
 */
function formatTime(datetime) {
    const date = new Date(datetime);
    return date.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
}

/**
 * Format date time
 */
function formatDateTime(datetime) {
    const date = new Date(datetime);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

/**
 * Disable validation for all inputs in a container
 * This prevents "invalid form control not focusable" errors for hidden required fields
 */
function disableValidationForContainer(container) {
    const inputs = container.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        // Store original required state
        if (input.hasAttribute('required')) {
            input.dataset.originallyRequired = 'true';
            input.removeAttribute('required');
        }
    });
}

/**
 * Re-enable validation for all inputs in a container
 */
function enableValidationForContainer(container) {
    const inputs = container.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        // Restore original required state
        if (input.dataset.originallyRequired === 'true') {
            input.setAttribute('required', '');
        }
    });
}

/**
 * Set up conditional visibility ("show if" logic)
 * Questions with visibility rules start hidden and appear when their condition is met.
 */
function setupConditionalVisibility() {
    const form = document.getElementById('submission-form');
    if (!form) return;
    
    // Parse all question containers and their visibility rules
    const allQuestions = Array.from(document.querySelectorAll('.question-container'));
    const conditionalQuestions = []; // Questions that have visibility rules
    
    allQuestions.forEach(container => {
        const visibilityData = container.dataset.visibility;
        if (visibilityData) {
            try {
                const visibility = JSON.parse(visibilityData);
                if (visibility.question_uid && visibility.values && visibility.values.length > 0) {
                    conditionalQuestions.push({
                        element: container,
                        questionUid: visibility.question_uid,
                        values: visibility.values
                    });
                    // Start hidden
                    container.classList.add('hidden');
                    container.dataset.conditionallyHidden = 'true';
                }
            } catch (e) {
                console.error('Failed to parse visibility config:', e);
            }
        }
    });
    
    if (conditionalQuestions.length === 0) return; // No conditional questions
    
    // Evaluate all conditions
    const evaluateVisibility = () => {
        conditionalQuestions.forEach(cq => {
            // Find source question by UID
            const sourceContainer = document.querySelector(`.question-container[data-question-uid="${cq.questionUid}"]`);
            if (!sourceContainer) {
                // Source question not found (possibly deleted) - keep question hidden
                cq.element.classList.add('hidden');
                cq.element.dataset.conditionallyHidden = 'true';
                disableValidationForContainer(cq.element);
                return;
            }
            
            const sourceId = sourceContainer.dataset.questionId;
            const sourceValues = getQuestionValues(sourceId);
            
            // Check if any of the source's selected values match the required values
            const isVisible = cq.values.some(requiredVal => sourceValues.includes(requiredVal));
            
            if (isVisible) {
                cq.element.classList.remove('hidden');
                cq.element.dataset.conditionallyHidden = 'false';
                enableValidationForContainer(cq.element);
            } else {
                cq.element.classList.add('hidden');
                cq.element.dataset.conditionallyHidden = 'true';
                disableValidationForContainer(cq.element);
            }
        });
    };
    
    // Listen for changes on the form
    form.addEventListener('change', evaluateVisibility);
    form.addEventListener('input', evaluateVisibility); // Also listen to input events for immediate feedback
    
    // Initial evaluation (for drafts/edits that already have values)
    evaluateVisibility();
}

/**
 * Get the current selected value(s) for a question by its numeric ID
 */
function getQuestionValues(questionId) {
    const container = document.querySelector(`.question-container[data-question-id="${questionId}"]`);
    if (!container) return [];
    
    // Check for radio buttons
    const radio = container.querySelector(`input[type="radio"][name="answers[${questionId}]"]:checked`);
    if (radio) return [radio.value];
    
    // Check for single select
    const select = container.querySelector(`select[name="answers[${questionId}]"]`);
    if (select && select.value) return [select.value];
    
    // Check for checkboxes (checkbox group)
    const checkboxes = container.querySelectorAll(`input[type="checkbox"][name="answers[${questionId}][]"]:checked`);
    if (checkboxes.length > 0) {
        return Array.from(checkboxes).map(cb => cb.value);
    }
    
    // Check for multiselect
    const multiselect = container.querySelector(`select[name="answers[${questionId}][]"]`);
    if (multiselect) {
        return Array.from(multiselect.selectedOptions).map(opt => opt.value);
    }
    
    return [];
}

/**
 * Validate form fields against the server-configured rules.
 * Runs even if HTML attributes (maxlength, min, max, pattern, required) have
 * been removed from the DOM by the user, providing a second enforcement layer
 * before the request reaches the server.
 * Returns an array of { questionId, message } error objects.
 */
function validateFormClientSide() {
    const rules = formConfig.validationRules || {};
    const errors = [];

    for (const [questionId, rule] of Object.entries(rules)) {
        const container = document.querySelector(`.question-container[data-question-id="${questionId}"]`);
        if (!container || container.dataset.conditionallyHidden === 'true') continue;

        const validation = rule.validation || {};

        // Collect the current answer
        let answer = null;
        let isEmpty = false;

        if (rule.type === 'checkbox_group' || rule.type === 'multiselect') {
            const multiselect = container.querySelector(`select[name="answers[${questionId}][]"]`);
            if (multiselect) {
                answer = Array.from(multiselect.selectedOptions).map(opt => opt.value);
            } else {
                answer = Array.from(
                    container.querySelectorAll(`input[type="checkbox"][name="answers[${questionId}][]"]:checked`)
                ).map(cb => cb.value);
            }
            isEmpty = answer.length === 0;
        } else if (rule.type === 'radio') {
            const checked = container.querySelector(`input[type="radio"][name="answers[${questionId}]"]:checked`);
            answer = checked ? checked.value : '';
            isEmpty = answer === '';
        } else {
            const input = container.querySelector(`[name="answers[${questionId}]"]`);
            answer = input ? input.value : '';
            isEmpty = answer === '';
        }

        // Required check
        if (rule.required && isEmpty) {
            errors.push({ questionId, message: validationMsg('required') });
            continue;
        }

        if (isEmpty) continue;

        // Type-specific rules
        switch (rule.type) {
            case 'text':
            case 'textarea':
                if (validation.min_length != null && answer.length < validation.min_length) {
                    errors.push({ questionId, message: validationMsg('minLength', { min: validation.min_length }) });
                } else if (validation.max_length != null && answer.length > validation.max_length) {
                    errors.push({ questionId, message: validationMsg('maxLength', { max: validation.max_length }) });
                } else if (validation.pattern) {
                    try {
                        if (!new RegExp(validation.pattern, 'u').test(answer)) {
                            errors.push({ questionId, message: validationMsg('invalidFormat') });
                        }
                    } catch (_) { /* invalid regex — server will catch it */ }
                }
                break;

            case 'number': {
                const num = parseFloat(answer);
                if (isNaN(num)) {
                    errors.push({ questionId, message: validationMsg('invalidNumber') });
                } else if (validation.min != null && num < validation.min) {
                    errors.push({ questionId, message: validationMsg('minValue', { min: validation.min }) });
                } else if (validation.max != null && num > validation.max) {
                    errors.push({ questionId, message: validationMsg('maxValue', { max: validation.max }) });
                }
                break;
            }

            case 'email':
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(answer)) {
                    errors.push({ questionId, message: validationMsg('invalidEmail') });
                }
                break;

            case 'url':
                try { new URL(answer); } catch (_) {
                    errors.push({ questionId, message: validationMsg('invalidUrl') });
                }
                break;

            case 'checkbox_group':
            case 'multiselect': {
                const count = answer.length;
                if (validation.min_selected != null && count < validation.min_selected) {
                    errors.push({ questionId, message: validationMsg('minSelections', { min: validation.min_selected }) });
                } else if (validation.max_selected != null && count > validation.max_selected) {
                    errors.push({ questionId, message: validationMsg('maxSelections', { max: validation.max_selected }) });
                }
                break;
            }
        }
    }

    return errors;
}

/**
 * Render JS validation error messages next to their fields.
 * Mirrors the server-rendered .form-error style.
 */
function showClientSideErrors(errors) {
    errors.forEach(({ questionId, message }) => {
        const container = document.querySelector(`.question-container[data-question-id="${questionId}"]`);
        if (!container) return;

        const p = document.createElement('p');
        p.className = 'mt-1.5 text-sm text-red-600 js-validation-error';
        p.textContent = message;
        container.appendChild(p);
    });

    // Scroll the first error into view
    const first = document.querySelector('.js-validation-error');
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Remove all JS-rendered validation error messages from the DOM.
 */
function clearClientSideErrors() {
    document.querySelectorAll('.js-validation-error').forEach(el => el.remove());
}

/**
 * Return a localised validation message, substituting {placeholder} tokens.
 */
function validationMsg(key, params = {}) {
    let msg = formConfig.validationMessages?.[key] ?? key;
    for (const [k, v] of Object.entries(params)) {
        msg = msg.replace(`{${k}}`, String(v));
    }
    return msg;
}

/**
 * Set up form submission
 */
function setupFormSubmission() {
    const form = document.getElementById('submission-form');
    if (!form) return;
    
    form.addEventListener('submit', (e) => {
        // Clear any JS errors from a previous failed attempt
        clearClientSideErrors();

        // Enforce validation rules even when HTML attributes have been stripped
        const clientErrors = validateFormClientSide();
        if (clientErrors.length > 0) {
            e.preventDefault();
            showClientSideErrors(clientErrors);
            return;
        }

        // Collect visible question UIDs (not conditionally hidden)
        const visibleQuestions = Array.from(document.querySelectorAll('.question-container'))
            .filter(q => q.dataset.conditionallyHidden !== 'true')
            .map(q => q.dataset.questionUid)
            .filter(uid => uid);
        
        // Remove any existing _visible_questions inputs
        form.querySelectorAll('input[name="_visible_questions[]"]').forEach(el => el.remove());
        
        // Add hidden inputs for visible question UIDs
        visibleQuestions.forEach(uid => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_visible_questions[]';
            input.value = uid;
            form.appendChild(input);
        });
        
        // When editing, fire-and-forget cleanup of any orphaned session uploads
        // that are not referenced in the final answers (belt-and-suspenders)
        if (formConfig.isEditing && sessionUploadedFileIds.length > 0) {
            const finalFileIds = new Set(
                Array.from(document.querySelectorAll('input[id$="_file_id"]'))
                    .map(inp => inp.value)
                    .filter(Boolean)
            );
            sessionUploadedFileIds.forEach(fileId => {
                if (!finalFileIds.has(fileId)) {
                    api(`/api/files/${fileId}`, 'DELETE').catch(() => {});
                }
            });
        }
        
        // Clear localStorage cache on submit
        // Server will delete the draft, so we clear the local cache too
        const key = `form_draft_${formConfig.formUuid}`;
        localStorage.removeItem(key);
        console.log('Form cache cleared on submission');
    });
}

/**
 * Set up unload warning
 */
function setupUnloadWarning() {
    window.addEventListener('beforeunload', (e) => {
        const currentData = getFormData();
        const currentDataStr = JSON.stringify(currentData);
        
        // Only warn if there are unsaved changes
        if (currentDataStr !== lastSavedData) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });
}
