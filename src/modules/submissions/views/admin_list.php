<?php
/**
 * Admin All Submissions View
 * 
 * Shows all submissions across all forms with filtering and pagination.
 * 
 * SECURITY: Access controlled by admin_submissions_list() controller
 * - Super admins see all submissions
 * - Regular admins only see submissions from forms they created
 */

$current_filters = [
    'status' => $_GET['status'] ?? '',
    'form_id' => $_GET['form_id'] ?? '',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'include_deleted' => isset($_GET['include_deleted']),
    'search' => $_GET['search'] ?? '',
    'sort' => $_GET['sort'] ?? 'date_desc',
    'clarification_status' => $_GET['clarification_status'] ?? ''
];

// Ensure required variables are defined
$result = $result ?? ['total' => 0, 'rows' => [], 'page' => 1, 'per_page' => 20, 'pages' => 0];
$stats = $stats ?? ['submitted' => 0, 'clarification_requested' => 0, 'deleted_forms' => 0];
$forms_list = $forms_list ?? [];
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">All Submissions</h1>
        <p class="mt-2 text-sm text-gray-700">View and manage submissions across all forms</p>
    </div>
</div>

<!-- Stats -->
<div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="card p-4">
        <div class="body-text">Total</div>
        <div class="page-title"><?= number_format($result['total']) ?></div>
    </div>
    <div class="card p-4">
        <div class="body-text">Submitted</div>
        <div class="text-2xl font-bold text-green-600"><?= $stats['submitted'] ?></div>
    </div>
    <div class="card p-4">
        <div class="body-text">Clarification Requested</div>
        <div class="text-2xl font-bold text-amber-600"><?= $stats['clarification_requested'] ?></div>
    </div>
    <div class="card p-4">
        <div class="body-text">From Deleted Forms</div>
        <div class="text-2xl font-bold text-orange-600"><?= $stats['deleted_forms'] ?></div>
    </div>
</div>

<!-- Filters -->
<div class="card mt-6 p-6">
    <h2 class="section-title mb-4">Filters</h2>
    <form method="GET" action="/admin/submissions" class="space-y-4">
        <!-- Row 1: Status, Clarification, Form, Sort -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="submitted" <?= $current_filters['status'] === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="clarification_requested" <?= $current_filters['status'] === 'clarification_requested' ? 'selected' : '' ?>>Clarification Requested</option>
                </select>
            </div>
            
            <div>
                <label>Clarification</label>
                <select name="clarification_status">
                    <option value="">All</option>
                    <option value="responded" <?= $current_filters['clarification_status'] === 'responded' ? 'selected' : '' ?>>Response Received</option>
                    <option value="open" <?= $current_filters['clarification_status'] === 'open' ? 'selected' : '' ?>>Awaiting Response</option>
                </select>
            </div>
            
            <div>
                <label>Form</label>
                <select name="form_id">
                    <option value="">All Forms</option>
                    <?php foreach ($forms_list as $form): ?>
                        <option value="<?= $form['id'] ?>" <?= $current_filters['form_id'] == $form['id'] ? 'selected' : '' ?>>
                            <?= sanitize($form['name']) ?>
                            <?php if ($form['deleted_at']): ?>
                                (Deleted)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Sort By</label>
                <select name="sort">
                    <option value="date_desc" <?= $current_filters['sort'] === 'date_desc' ? 'selected' : '' ?>>Date (Newest First)</option>
                    <option value="date_asc" <?= $current_filters['sort'] === 'date_asc' ? 'selected' : '' ?>>Date (Oldest First)</option>
                    <option value="form_asc" <?= $current_filters['sort'] === 'form_asc' ? 'selected' : '' ?>>Form Name (A-Z)</option>
                    <option value="form_desc" <?= $current_filters['sort'] === 'form_desc' ? 'selected' : '' ?>>Form Name (Z-A)</option>
                    <option value="user_asc" <?= $current_filters['sort'] === 'user_asc' ? 'selected' : '' ?>>User Name (A-Z)</option>
                    <option value="user_desc" <?= $current_filters['sort'] === 'user_desc' ? 'selected' : '' ?>>User Name (Z-A)</option>
                    <option value="status_asc" <?= $current_filters['sort'] === 'status_asc' ? 'selected' : '' ?>>Status (A-Z)</option>
                    <option value="status_desc" <?= $current_filters['sort'] === 'status_desc' ? 'selected' : '' ?>>Status (Z-A)</option>
                </select>
            </div>
        </div>
        
        <!-- Row 2: Date Range, Search -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label>Date From</label>
                <input type="date" name="date_from" id="filter_date_from"
                       value="<?= sanitize($current_filters['date_from']) ?>"
                       max="<?= sanitize($current_filters['date_to']) ?>">
            </div>
            
            <div>
                <label>Date To</label>
                <input type="date" name="date_to" id="filter_date_to"
                       value="<?= sanitize($current_filters['date_to']) ?>"
                       min="<?= sanitize($current_filters['date_from']) ?>">
            </div>
            
            <div>
                <label>Search User</label>
                <input type="text" name="search" value="<?= sanitize($current_filters['search']) ?>" 
                       placeholder="Name or email...">
            </div>
        </div>
        
        <!-- Row 3: Checkboxes and Buttons -->
        <div class="flex items-end justify-between">
            <div class="flex items-center">
                <input type="checkbox" name="include_deleted" id="include_deleted" value="1"
                       <?= $current_filters['include_deleted'] ? 'checked' : '' ?>
                       class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                <label for="include_deleted" class="ml-2">
                    Include deleted forms
                </label>
            </div>
            
            <div class="flex gap-2">
                <a href="/admin/submissions" class="btn btn-secondary">
                    Clear Filters
                </a>
                <button type="submit" class="btn btn-primary">
                    Apply Filters
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Submissions Table -->
<div class="card mt-6">
    <table>
        <thead>
            <tr>
                <th>
                    Submitted
                </th>
                <th>
                    Form Name
                </th>
                <th>
                    User
                </th>
                <th>
                    Status
                </th>
                <th>
                    Actions
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($result['rows'])): ?>
                <tr>
                    <td colspan="5" class="py-12 text-center">
                        <div class="text-gray-400 mb-2">
                            <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <p class="text-gray-500 text-lg">No submissions found</p>
                        <p class="text-gray-400 text-sm mt-1">Try adjusting your filters</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($result['rows'] as $submission): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap text-gray-900">
                            <?= $submission['submitted_at'] ? format_datetime($submission['submitted_at'], 'short') : '-' ?>
                        </td>
                        <td>
                            <div class="flex items-center">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= sanitize($submission['form_name'] ?? 'Unknown Form') ?>
                                </div>
                                <?php if ($submission['form_deleted_at']): ?>
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800" 
                                          title="Form deleted on <?= format_datetime($submission['form_deleted_at'], 'short') ?>">
                                        <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Deleted
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="text-sm font-medium text-gray-900"><?= sanitize($submission['user_name'] ?? 'Unknown') ?></div>
                            <div class="text-sm text-gray-500"><?= sanitize($submission['user_email'] ?? '') ?></div>
                        </td>
                        <td class="whitespace-nowrap">
                            <div class="flex flex-col gap-1 items-start">
                                <span class="badge
                                    <?php
                                    echo match($submission['status']) {
                                        'submitted' => 'bg-blue-100 text-blue-800',
                                        'clarification_requested' => 'bg-amber-100 text-amber-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                    ?>
                                ">
                                    <?= ucwords(str_replace('_', ' ', $submission['status'])) ?>
                                </span>
                                <?php if (!empty($submission['clarification_status'])): ?>
                                    <?php if ($submission['clarification_status'] === 'responded'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700" title="Clarification response received">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                            </svg>
                                            Response Received
                                        </span>
                                    <?php elseif ($submission['clarification_status'] === 'open'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-700" title="Awaiting clarification response">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Awaiting Response
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="whitespace-nowrap">
                            <?php if ($submission['form_uuid']): ?>
                                <a href="/admin/forms/<?= sanitize($submission['form_uuid']) ?>/submissions/<?= sanitize($submission['uuid']) ?>"
                                   class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    View Details
                                </a>
                            <?php else: ?>
                                <span class="text-gray-400">Form unavailable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php
    $pagination = $result;
    $base_url = '/admin/submissions';
    $query_params = [];
    if ($current_filters['status']) $query_params['status'] = $current_filters['status'];
    if ($current_filters['form_id']) $query_params['form_id'] = $current_filters['form_id'];
    if ($current_filters['date_from']) $query_params['date_from'] = $current_filters['date_from'];
    if ($current_filters['date_to']) $query_params['date_to'] = $current_filters['date_to'];
    if ($current_filters['include_deleted']) $query_params['include_deleted'] = '1';
    if ($current_filters['search']) $query_params['search'] = $current_filters['search'];
    if ($current_filters['sort'] && $current_filters['sort'] !== 'date_desc') $query_params['sort'] = $current_filters['sort'];
    if ($current_filters['clarification_status']) $query_params['clarification_status'] = $current_filters['clarification_status'];
    require __DIR__ . '/../../../layouts/partials/pagination.php';
    ?>
</div>

<!-- Date range constraint -->
<script>
const dateFrom = document.getElementById('filter_date_from');
const dateTo = document.getElementById('filter_date_to');
dateFrom.addEventListener('change', () => { dateTo.min = dateFrom.value; });
dateTo.addEventListener('change', () => { dateFrom.max = dateTo.value; });
</script>
