<?php
/** @var array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $result */
$result = $result ?? ['rows' => [], 'total' => 0, 'pages' => 0, 'page' => 1, 'per_page' => 20];
?>
<!-- Programs Index (Admin) -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Programs</h1>
        <p class="mt-2 text-sm text-gray-700">Group forms and configure review processes</p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <form method="POST" action="/admin/programs/store" style="display: inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create Program
            </button>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="mt-6 flex gap-4">
    <div class="flex gap-2">
        <a href="/admin/programs" class="filter-tab <?php echo empty($_GET['status']) ? 'filter-tab-active' : ''; ?>">
            All
        </a>
        <a href="/admin/programs?status=draft" class="filter-tab <?php echo ($_GET['status'] ?? '') === 'draft' ? 'filter-tab-active' : ''; ?>">
            Draft
        </a>
        <a href="/admin/programs?status=active" class="filter-tab <?php echo ($_GET['status'] ?? '') === 'active' ? 'filter-tab-active' : ''; ?>">
            Active
        </a>
        <a href="/admin/programs?status=closed" class="filter-tab <?php echo ($_GET['status'] ?? '') === 'closed' ? 'filter-tab-active' : ''; ?>">
            Closed
        </a>
    </div>
</div>

<!-- Programs Table -->
<div class="card mt-6">
    <?php if (!empty($programs)): ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Program Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Forms</th>
                        <th scope="col">Entries</th>
                        <th scope="col">Review Stages</th>
                        <th scope="col">Creator</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($programs as $prog): ?>
                        <tr class="hover:bg-gray-50">
                            <td>
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="/admin/programs/<?= $prog['uuid'] ?>/setup" class="hover:text-primary-600 font-semibold">
                                        <?= sanitize($prog['name']) ?>
                                    </a>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php
                                $prog_expired = $prog['status'] === 'active' && is_program_effectively_closed($prog);
                                if ($prog_expired) {
                                    $class = 'bg-amber-100 text-amber-800';
                                    $label = 'Expired';
                                } else {
                                    $status_classes = [
                                        'active' => 'bg-green-100 text-green-800',
                                        'draft' => 'bg-gray-100 text-gray-800',
                                        'closed' => 'bg-red-100 text-red-800'
                                    ];
                                    $class = $status_classes[$prog['status']] ?? 'bg-gray-100 text-gray-800';
                                    $label = ucfirst($prog['status']);
                                }
                                ?>
                                <span class="badge <?= $class ?>">
                                    <?= $label ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5 text-sm text-gray-500">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span><?= count($prog['form_ids']) ?></span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <a href="/admin/programs/<?= $prog['uuid'] ?>/submissions" class="inline-flex items-center gap-1.5 text-sm font-medium group">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                    </svg>
                                    <span class="text-primary-600 group-hover:text-primary-900"><?= number_format($prog['submission_count'] ?? 0) ?></span>
                                </a>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php if (!empty($prog['review_stages'])): ?>
                                    <span class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="font-medium"><?= count($prog['review_stages']) ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 text-sm text-gray-500">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                        <span class="font-medium">None</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?= sanitize($prog['creator_name']) ?>
                            </td>
                            <td class="whitespace-nowrap text-right font-medium">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="/admin/programs/<?= $prog['uuid'] ?>/setup"
                                       class="inline-flex items-center p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                                       title="Edit">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </a>
                                    <?php if (empty($prog['submission_count'])): ?>
                                        <button onclick="deleteProgram('<?= $prog['uuid'] ?>')"
                                           class="inline-flex items-center p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                           title="Delete">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
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
        $base_url = '/admin/programs';
        $query_params = [];
        if (!empty($_GET['status'])) $query_params['status'] = $_GET['status'];
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">Nothing to see here</h3>
            <?php
            $current_status = $_GET['status'] ?? '';
            // Only show "Get started" message and button for "All" and "Active" tabs
            if ($current_status === '' || $current_status === 'active'):
            ?>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a new program.</p>
                <div class="mt-6">
                    <form method="POST" action="/admin/programs/store" style="display: inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Create Program
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteProgram(uuid) {
    if (!confirm('Are you sure you want to delete this program? Programs with submissions cannot be deleted.')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/programs/delete';
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_csrf';
    csrf.value = '<?= csrf_token() ?>';
    form.appendChild(csrf);
    const uuidInput = document.createElement('input');
    uuidInput.type = 'hidden';
    uuidInput.name = 'uuid';
    uuidInput.value = uuid;
    form.appendChild(uuidInput);
    document.body.appendChild(form);
    form.submit();
}
</script>
