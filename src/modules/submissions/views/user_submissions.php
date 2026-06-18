<?php
/**
 * User Submissions List View
 * Shows all of the current user's submissions with filtering and pagination.
 *
 * @var array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $result Paginated result set
 * @var string|null $status_filter Current status filter
 */
$title = t('my_submissions.title');
$result = $result;
$status_filter = $status_filter ?? null;

ob_start();
?>

<div class="mb-6">
    <h1 class="page-title"><?= t('my_submissions.title') ?></h1>
    <p class="body-text mt-1"><?= t('my_submissions.no_submissions_message') ?></p>
</div>

<!-- Status Filters -->
<div class="flex flex-wrap gap-2 mb-6">
    <a href="/submissions"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo !$status_filter ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('my_submissions.filter_all') ?>
    </a>
    <a href="/submissions?status=draft"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo $status_filter === 'draft' ? 'bg-gray-200 text-gray-800' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('common.drafts') ?>
    </a>
    <a href="/submissions?status=submitted"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo $status_filter === 'submitted' ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('my_submissions.filter_submitted') ?>
    </a>
    <a href="/submissions?status=clarification_requested"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo $status_filter === 'clarification_requested' ? 'bg-amber-50 text-amber-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('submission.status_clarification') ?>
    </a>
</div>

<!-- Submissions Table -->
<div class="card">
    <?php if (count($result['rows']) > 0): ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col"><?= t('common.forms') ?></th>
                        <th scope="col"><?= t('common.status') ?></th>
                        <th scope="col"><?= t('common.date') ?></th>
                        <th scope="col" class="text-right"><?= t('common.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $sub): ?>
                        <?php
                        $status_styles = [
                            'draft'          => 'bg-gray-100 text-gray-800',
                            'submitted'      => 'bg-blue-100 text-blue-800',
                            'clarification_requested' => 'bg-amber-100 text-amber-800',
                        ];
                        $badge_class = $status_styles[$sub['status']] ?? 'bg-gray-100 text-gray-800';
                        $is_draft = $sub['status'] === 'draft';
                        $display_date = $is_draft ? ($sub['updated_at'] ?? '') : ($sub['submitted_at'] ?? '');
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="font-medium text-gray-900">
                                <?php echo sanitize($sub['form_name']); ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php
                                $status_labels = [
                                    'draft' => t('submission.status_draft'),
                                    'submitted' => t('submission.status_submitted'),
                                    'in_review' => t('submission.status_in_review'),
                                    'approved' => t('submission.status_approved'),
                                    'rejected' => t('submission.status_rejected'),
                                    'clarification_requested' => t('submission.status_clarification'),
                                ];
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?= $status_labels[$sub['status']] ?? ucfirst(str_replace('_', ' ', $sub['status'])) ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?php if ($is_draft): ?>
                                    <span class="text-gray-400"><?= t('my_submissions.saved_on', ['date' => format_datetime($display_date, 'date_short')]) ?></span>
                                <?php else: ?>
                                    <?php echo format_datetime($display_date, 'date_short'); ?>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <?php if ($is_draft): ?>
                                    <a href="/forms/<?php echo sanitize($sub['form_uuid']); ?>" class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <?= t('common.continue') ?>
                                    </a>
                                <?php else: ?>
                                    <?php
                                    $view_params = ['from' => 'list'];
                                    if ($result['page'] > 1) $view_params['list_page'] = $result['page'];
                                    if ($status_filter) $view_params['list_status'] = $status_filter;
                                    $view_qs = '?' . http_build_query($view_params);
                                    ?>
                                    <a href="/submissions/<?php echo sanitize($sub['uuid']); ?><?= $view_qs ?>" class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <?= t('common.view') ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination (always shown) -->
        <?php
        $pagination = $result;
        $base_url = '/submissions';
        $query_params = [];
        if ($status_filter) $query_params['status'] = $status_filter;
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>

    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">
                <?php if ($status_filter): ?>
                    <?php
                    // Translate status value before substitution
                    $status_labels = [
                        'draft' => t('submission.status_draft'),
                        'submitted' => t('my_submissions.filter_submitted'),
                        'in_review' => t('submission.status_in_review'),
                        'approved' => t('my_submissions.filter_approved'),
                        'rejected' => t('my_submissions.filter_rejected'),
                        'clarification_requested' => t('submission.status_clarification'),
                    ];
                    $status_label = $status_labels[$status_filter] ?? str_replace('_', ' ', $status_filter);
                    ?>
                    <?= t('my_submissions.no_filtered_submissions', ['status' => $status_label]) ?>
                <?php else: ?>
                    <?= t('my_submissions.no_submissions') ?>
                <?php endif; ?>
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php if ($status_filter): ?>
                    <?= t('my_submissions.no_filtered_message') ?>
                <?php else: ?>
                    <?= t('my_submissions.no_submissions_default') ?>
                <?php endif; ?>
            </p>
            <?php if (!$status_filter): ?>
                <div class="mt-6">
                    <a href="/forms" class="text-sm font-medium text-primary-600 hover:text-primary-500">
                        <?= t('my_submissions.browse_available_forms') ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/user.php';
