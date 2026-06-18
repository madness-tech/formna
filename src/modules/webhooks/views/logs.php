<?php
/**
 * Webhook Logs Viewer (Super Admin)
 *
 * @var array<string, mixed> $webhook Webhook data
 * @var array<string, mixed> $logs Paginated webhook logs
 * @var array<string, mixed> $stats Webhook statistics
 * @var string|null $status_filter Current status filter
 */

$status_colors = [
    'success' => 'bg-green-100 text-green-800',
    'failed' => 'bg-red-100 text-red-800',
    'pending' => 'bg-yellow-100 text-yellow-800',
    'retrying' => 'bg-orange-100 text-orange-800'
];
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title"><?= sanitize($webhook['name']) ?></h1>
        <p class="mt-2 text-sm text-gray-700">Webhook delivery logs and performance metrics</p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <a href="/admin/webhooks/<?= sanitize($webhook['id']) ?>/edit"
           class="btn btn-primary">
            <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
            </svg>
            Edit Webhook
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="card p-4">
        <div class="body-text">Total</div>
        <div class="page-title"><?= number_format($stats['total_deliveries'] ?? 0) ?></div>
    </div>
    <div class="card p-4">
        <div class="body-text">Success</div>
        <div class="text-2xl font-bold text-green-600"><?= number_format($stats['successful'] ?? 0) ?></div>
    </div>
    <div class="card p-4">
        <div class="body-text">Failed</div>
        <div class="text-2xl font-bold text-red-600"><?= number_format($stats['failed'] ?? 0) ?></div>
    </div>
    <div class="card p-4">
        <div class="body-text">Success Rate</div>
        <div class="text-2xl font-bold text-primary-600"><?= $stats['success_rate'] ?>%</div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="mt-6 flex gap-2">
        <a href="/admin/webhooks/<?= sanitize($webhook['id']) ?>/logs"
           class="filter-tab <?= !$status_filter ? 'filter-tab-active' : '' ?>">
            All
        </a>
        <a href="/admin/webhooks/<?= sanitize($webhook['id']) ?>/logs?status=success"
           class="filter-tab <?= $status_filter === 'success' ? 'filter-tab-active' : '' ?>">
            Success
        </a>
        <a href="/admin/webhooks/<?= sanitize($webhook['id']) ?>/logs?status=failed"
           class="filter-tab <?= $status_filter === 'failed' ? 'filter-tab-active' : '' ?>">
            Failed
        </a>
        <a href="/admin/webhooks/<?= sanitize($webhook['id']) ?>/logs?status=pending"
           class="filter-tab <?= $status_filter === 'pending' ? 'filter-tab-active' : '' ?>">
            Pending
        </a>
        <a href="/admin/webhooks/<?= sanitize($webhook['id']) ?>/logs?status=retrying"
           class="filter-tab <?= $status_filter === 'retrying' ? 'filter-tab-active' : '' ?>">
            Retrying
        </a>
</div>

<!-- Logs Table -->
    <div class="card mt-6">
        <?php if (empty($logs['data'])): ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">No logs found</h3>
                <p class="mt-1 text-sm text-gray-500">This webhook hasn't been triggered yet<?= $status_filter ? ' with status "' . sanitize($status_filter) . '"' : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">
                                Timestamp
                            </th>
                            <th scope="col">
                                Form
                            </th>
                            <th scope="col">
                                Submission
                            </th>
                            <th scope="col">
                                Status
                            </th>
                            <th scope="col">
                                HTTP Code
                            </th>
                            <th scope="col">
                                Attempts
                            </th>
                            <th scope="col">
                                Response
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs['data'] as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap text-gray-900">
                                    <?= format_datetime($log['created_at'], 'M d, Y H:i:s') ?>
                                </td>
                                <td class="whitespace-nowrap text-gray-900">
                                    <?= sanitize($log['form_name'] ?? 'N/A') ?>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php if ($log['submission_uuid']): ?>
                                        <a href="/admin/submissions/<?= sanitize($log['submission_uuid']) ?>"
                                           class="text-primary-600 hover:text-primary-900 hover:underline font-mono text-xs">
                                            <?= sanitize(substr($log['submission_uuid'], 0, 8)) ?>...
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="badge <?= $status_colors[$log['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                                        <?= sanitize(ucfirst($log['status'])) ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php if ($log['response_status']): ?>
                                        <span class="font-mono font-medium <?= $log['response_status'] >= 200 && $log['response_status'] < 300 ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= sanitize($log['response_status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap text-gray-500">
                                    <?= sanitize($log['attempts']) ?>
                                </td>
                                <td class="text-gray-500">
                                    <?php if ($log['response_body']): ?>
                                        <details class="cursor-pointer">
                                            <summary class="text-primary-600 hover:text-primary-900 hover:underline">View</summary>
                                            <pre class="mt-2 p-3 bg-gray-50 rounded-md text-xs overflow-auto max-h-40 border border-gray-200"><?= sanitize(substr($log['response_body'], 0, 500)) ?></pre>
                                        </details>
                                    <?php else: ?>
                                        <span class="text-gray-400">--</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <!-- Pagination -->
    <?php
    $pagination = $logs;
    $base_url = '/admin/webhooks/' . (int)$webhook['id'] . '/logs';
    $query_params = [];
    if ($status_filter) $query_params['status'] = $status_filter;
    require __DIR__ . '/../../../layouts/partials/pagination.php';
    ?>
</div>
