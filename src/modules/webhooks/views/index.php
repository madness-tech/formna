<?php
/**
 * Webhooks List (Super Admin)
 *
 * @var array<int, array<string, mixed>> $webhooks List of webhooks with stats
 * @var array<string, mixed> $global_stats Global webhook statistics
 * @var string $filter Current filter: 'active' or 'deleted'
 * @var array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $result
 */
$result = $result ?? ['rows' => [], 'total' => 0, 'pages' => 0, 'page' => 1, 'per_page' => 20];
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Webhooks</h1>
        <p class="mt-2 text-sm text-gray-700">Manage automated HTTP notifications for form submissions</p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <a href="/admin/webhooks/create" class="btn btn-primary">
            <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Webhook
        </a>
    </div>
</div>

<!-- Global Statistics -->
<div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="body-text">Active Webhooks</div>
            <div class="page-title"><?= $global_stats['active_webhooks'] ?></div>
        </div>
        <div class="card p-4">
            <div class="body-text">Pending Deliveries</div>
            <div class="text-2xl font-bold text-yellow-600"><?= $global_stats['pending_deliveries'] ?></div>
        </div>
        <div class="card p-4">
            <div class="body-text">Failed (24h)</div>
            <div class="text-2xl font-bold text-red-600"><?= $global_stats['failed_last_24h'] ?></div>
        </div>
        <div class="card p-4">
            <div class="body-text">Success Rate (7d)</div>
            <div class="text-2xl font-bold text-green-600"><?= $global_stats['success_rate'] ?>%</div>
        </div>
</div>

<!-- Filter Tabs -->
<div class="mt-6 flex gap-2">
        <a href="/admin/webhooks" class="filter-tab <?= $filter === 'active' ? 'filter-tab-active' : '' ?>">
            Active
        </a>
        <a href="/admin/webhooks?filter=deleted" class="filter-tab <?= $filter === 'deleted' ? 'filter-tab-active' : '' ?>">
            Deleted
    </a>
</div>

<!-- Webhooks Table -->
<div class="card mt-6">
        <?php if (empty($webhooks)): ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">Nothing to see here</h3>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name / URL</th>
                            <th scope="col">Form</th>
                            <th scope="col">Status</th>
                            <th scope="col">Deliveries</th>
                            <th scope="col">Success Rate</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($webhooks as $webhook): ?>
                            <tr class="hover:bg-gray-50 <?= $webhook['deleted_at'] ? 'opacity-50' : '' ?>">
                                <td>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= sanitize($webhook['name']) ?>
                                    </div>
                                    <div class="help-text font-mono truncate max-w-xs" title="<?= sanitize($webhook['url']) ?>">
                                        <?= sanitize($webhook['url']) ?>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php if ($webhook['form']): ?>
                                        <a href="/admin/forms/<?= sanitize($webhook['form']['uuid']) ?>/settings" class="text-primary-600 hover:text-primary-900 hover:underline">
                                            <?= sanitize($webhook['form']['name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not Attached</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php if ($webhook['deleted_at']): ?>
                                        <span class="badge badge-gray">Deleted</span>
                                    <?php elseif ($webhook['is_active']): ?>
                                        <span class="badge badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-yellow">Inactive</span>
                                    <?php endif; ?>
                                    <?php if ($webhook['include_user_data']): ?>
                                        <span class="badge badge-blue mt-1">+ User Data</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php if ($webhook['stats']['total_deliveries'] > 0): ?>
                                        <span class="text-sm text-gray-900"><?= number_format($webhook['stats']['total_deliveries']) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php if ($webhook['stats']['total_deliveries'] > 0): ?>
                                        <span class="font-medium <?= $webhook['stats']['success_rate'] >= 90 ? 'text-green-600' : ($webhook['stats']['success_rate'] >= 70 ? 'text-yellow-600' : 'text-red-600') ?>">
                                            <?= $webhook['stats']['success_rate'] ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap text-right font-medium">
                                    <?php if (!$webhook['deleted_at']): ?>
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="/admin/webhooks/<?= sanitize($webhook['id']) ?>/logs"
                                               class="inline-flex items-center p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                                               title="View Logs">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                                                </svg>
                                            </a>
                                            <a href="/admin/webhooks/<?= sanitize($webhook['id']) ?>/edit"
                                               class="inline-flex items-center p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                                               title="Edit">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                </svg>
                                            </a>
                                            <form method="POST" action="/admin/webhooks/<?= sanitize($webhook['id']) ?>/toggle" class="inline">
                                                <?= csrf_field() ?>
                                                <button type="submit"
                                                        class="inline-flex items-center p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                                                        title="<?= $webhook['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                    <?php if ($webhook['is_active']): ?>
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a9 9 0 010-7.07m7.072 0a9 9 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z" />
                                                        </svg>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                            <form method="POST" action="/admin/webhooks/<?= sanitize($webhook['id']) ?>/delete"
                                                  onsubmit="return confirm('Are you sure you want to delete this webhook?')" class="inline">
                                                <?= csrf_field() ?>
                                                <button type="submit"
                                                        class="inline-flex items-center p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                        title="Delete">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">Deleted</span>
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
        $pagination = $result;
        $base_url = '/admin/webhooks';
        $query_params = [];
        if ($filter !== 'active') $query_params['filter'] = $filter;
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
    </div>
