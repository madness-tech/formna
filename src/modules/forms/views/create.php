<!-- Create New Form -->

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Create New Form</h1>
        <p class="mt-2 text-sm text-gray-700">Set up a new form with basic information to get started</p>
    </div>
</div>

<!-- Form Card -->
<div class="card mt-6">
    <div class="card-header bg-gradient-to-r from-primary-50 to-blue-50">
        <div class="flex items-center">
            <div class="flex-shrink-0 w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <h3 class="section-title">Form Details</h3>
                <p class="body-text mt-0.5">Configure the basic information for your new form</p>
            </div>
        </div>
    </div>
    
    <div class="p-6">
        <form method="POST" action="/admin/forms" class="space-y-5">
            <?php echo csrf_field(); ?>
            
            <!-- Form Name -->
            <div>
                <label for="name">Form Name <span class="text-red-500">*</span></label>
                <input type="text"
                       name="name"
                       id="name"
                       required
                       placeholder="e.g., Employee Registration Form">
                <p class="help-text">A clear, descriptive name for your form</p>
            </div>
            
            <!-- Description -->
            <div>
                <label for="description">Description</label>
                <textarea name="description"
                          id="description"
                          rows="3"
                          placeholder="Brief description of the form's purpose..."></textarea>
                <p class="help-text">A brief description shown to users when viewing or filling out this form</p>
            </div>
            
            <!-- Instructions -->
            <div>
                <label for="instructions">User Instructions</label>
                <textarea name="instructions"
                          id="instructions"
                          rows="4"
                          placeholder="Instructions shown to users when filling out the form..."></textarea>
                <p class="help-text">These instructions will be displayed at the top of the form for users</p>
            </div>
            
            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="/admin/forms" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    Create Form
                </button>
            </div>
        </form>
    </div>
</div>
