<?php
// Ensure $result is defined with expected structure
$result = $result ?? ['rows' => [], 'page' => 1, 'pages' => 0, 'total' => 0];
?>
<!-- Forms List -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Forms</h1>
        <p class="mt-2 text-sm text-gray-700">Manage all your forms, questions, and settings</p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <a href="/admin/forms/create" class="btn btn-primary">
            <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Create Form
        </a>
    </div>
</div>

<!-- Filters -->
<div class="mt-6 flex gap-4">
    <div class="flex gap-2">
        <a href="/admin/forms" class="filter-tab <?php echo empty($_GET['status']) ? 'filter-tab-active' : ''; ?>">
            All
        </a>
        <a href="/admin/forms?status=draft" class="filter-tab <?php echo ($_GET['status'] ?? '') === 'draft' ? 'filter-tab-active' : ''; ?>">
            Draft
        </a>
        <a href="/admin/forms?status=published" class="filter-tab <?php echo ($_GET['status'] ?? '') === 'published' ? 'filter-tab-active' : ''; ?>">
            Published
        </a>
        <a href="/admin/forms?status=closed" class="filter-tab <?php echo ($_GET['status'] ?? '') === 'closed' ? 'filter-tab-active' : ''; ?>">
            Closed
        </a>
    </div>
</div>

<!-- Forms Table -->
<div class="card mt-6">
    <?php if (count($result['rows']) > 0): ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Form Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Submissions</th>
                        <th scope="col">Created</th>
                        <th scope="col">Creator</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $form): ?>
                        <tr class="hover:bg-gray-50">
                            <td>
                                <div class="text-sm font-medium text-gray-900">
                                    <?php if ($form['can_manage']): ?>
                                        <a href="/admin/forms/<?php echo $form['uuid']; ?>/builder" class="hover:text-primary-600 font-semibold">
                                            <?php echo sanitize($form['name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="font-semibold"><?php echo sanitize($form['name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php
                                $form_closed = is_form_closed($form);
                                if ($form_closed) {
                                    $class = 'bg-red-100 text-red-800';
                                    $label = 'Closed';
                                } else {
                                    $status_classes = [
                                        'draft' => 'bg-yellow-100 text-yellow-800',
                                        'published' => 'bg-green-100 text-green-800'
                                    ];
                                    $class = $status_classes[$form['status']] ?? 'bg-gray-100 text-gray-800';
                                    $label = ucfirst($form['status']);
                                }
                                ?>
                                <span class="badge <?php echo $class; ?>">
                                    <?php echo $label; ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php if ($form['can_view_results']): ?>
                                    <a href="/admin/forms/<?php echo $form['uuid']; ?>/submissions" class="inline-flex items-center gap-1.5 text-sm font-medium group">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                        </svg>
                                        <span class="text-primary-600 group-hover:text-primary-900"><?php echo number_format($form['submission_count']); ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="text-sm text-gray-500"><?php echo number_format($form['submission_count']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?php echo format_datetime($form['created_at'], 'date_short'); ?>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?php echo sanitize($form['creator_name'] ?? 'Unknown'); ?>
                            </td>
                            <td class="whitespace-nowrap text-right font-medium">
                                <div class="flex items-center justify-end gap-1">
                                    <?php if ($form['can_manage']): ?>
                                        <a href="/admin/forms/<?php echo $form['uuid']; ?>/settings"
                                           class="inline-flex items-center p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                                           title="Settings">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </a>
                                        <a href="/admin/forms/<?php echo $form['uuid']; ?>/builder"
                                           class="inline-flex items-center p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                                           title="Edit">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center p-1.5 text-gray-300 cursor-not-allowed opacity-50" title="No permission to edit settings">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </span>
                                        <span class="inline-flex items-center p-1.5 text-gray-300 cursor-not-allowed opacity-50" title="No permission to edit form">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php
        $pagination = $result;
        $base_url = '/admin/forms';
        $query_params = [];
        if (!empty($_GET['status'])) $query_params['status'] = $_GET['status'];
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
        
    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">Nothing to see here</h3>
            <?php 
            $current_status = $_GET['status'] ?? '';
            // Only show "Get started" message and button for "All" and "Published" tabs
            if ($current_status === '' || $current_status === 'published'): 
            ?>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a new form.</p>
                <div class="mt-6">
                    <a href="/admin/forms/create" class="btn btn-primary btn-sm">
                        <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create Form
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
