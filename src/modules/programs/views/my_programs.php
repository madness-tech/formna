<?php
/**
 * My Programs - User's program submissions/enrollments (Paginated + Filterable)
 *
 * @var array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $result Paginated result from list_user_program_submissions()
 * @var string|null $status_filter Current status filter
 */
$title = t('my_programs.title');
$result = $result;
$status_filter = $status_filter ?? null;

ob_start();
?>

<div class="mb-6">
    <h1 class="page-title"><?= t('my_programs.title') ?></h1>
    <p class="body-text mt-1"><?= t('my_programs.no_programs_message') ?></p>
</div>

<!-- Status Filters -->
<div class="flex flex-wrap gap-2 mb-6">
    <a href="/my-programs"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo !$status_filter ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('my_submissions.filter_all') ?>
    </a>
    <a href="/my-programs?status=draft"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo $status_filter === 'draft' ? 'bg-gray-200 text-gray-800' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('common.drafts') ?>
    </a>
    <a href="/my-programs?status=submitted"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo $status_filter === 'submitted' ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('my_submissions.filter_submitted') ?>
    </a>
    <a href="/my-programs?status=in_review"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo $status_filter === 'in_review' ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('submission.status_in_review') ?>
    </a>
    <a href="/my-programs?status=approved"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo $status_filter === 'approved' ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('my_submissions.filter_approved') ?>
    </a>
    <a href="/my-programs?status=rejected"
       class="px-3 py-1.5 text-sm font-medium rounded-md <?php echo $status_filter === 'rejected' ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
        <?= t('my_submissions.filter_rejected') ?>
    </a>
</div>

<div class="card">
    <?php if (!empty($result['rows'])): ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col"><?= t('common.programs') ?></th>
                        <th scope="col"><?= t('common.status') ?></th>
                        <th scope="col"><?= t('submission.submitted_date') ?></th>
                        <th scope="col"><?= t('common.date') ?></th>
                        <th scope="col" class="text-right"><?= t('common.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $ps): ?>
                        <?php
                        $status_styles = [
                            'draft'     => 'bg-gray-100 text-gray-800',
                            'submitted' => 'bg-blue-100 text-blue-800',
                            'in_review' => 'bg-yellow-100 text-yellow-800',
                            'approved'  => 'bg-green-100 text-green-800',
                            'rejected'  => 'bg-red-100 text-red-800',
                        ];
                        $badge = $status_styles[$ps['status']] ?? 'bg-gray-100 text-gray-800';
                        $is_draft = $ps['status'] === 'draft';
                        $display_date = $is_draft ? ($ps['updated_at'] ?? '') : ($ps['submitted_at'] ?? '');
                        $last_decision = !empty($ps['decisions']) ? end($ps['decisions']) : null;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="font-medium text-gray-900">
                                <?php echo sanitize($ps['program_name']); ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php
                                $status_labels = [
                                    'draft' => t('my_programs.status_draft'),
                                    'submitted' => t('my_programs.status_submitted'),
                                    'in_review' => t('my_programs.status_in_review'),
                                    'approved' => t('my_programs.status_approved'),
                                    'rejected' => t('my_programs.status_rejected'),
                                ];
                                ?>
                                <span class="badge <?php echo $badge; ?>">
                                    <?= $status_labels[$ps['status']] ?? ucfirst(str_replace('_', ' ', $ps['status'])) ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?php if ($is_draft): ?>
                                    <span class="text-gray-400"><?= t('my_programs.saved_on', ['date' => format_datetime($display_date, 'date_short')]) ?></span>
                                <?php else: ?>
                                    <?php echo format_datetime($display_date, 'date_short'); ?>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?php if (in_array($ps['status'], ['approved', 'rejected']) && $last_decision && !empty($last_decision['decided_at'])): ?>
                                    <?= format_datetime($last_decision['decided_at'], 'date_short') ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="/programs/<?php echo sanitize($ps['program_uuid']); ?>" class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <?php if ($is_draft): ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        <?php else: ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        <?php endif; ?>
                                    </svg>
                                    <?= $is_draft ? t('common.continue') : t('common.view') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination (always shown) -->
        <?php
        $pagination = $result;
        $base_url = '/my-programs';
        $query_params = [];
        if ($status_filter) $query_params['status'] = $status_filter;
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>

    <?php else: ?>
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">
                <?php if ($status_filter): ?>
                    <?php
                    // Translate status value before substitution
                    $status_labels = [
                        'draft' => t('my_programs.status_draft'),
                        'submitted' => t('my_programs.status_submitted'),
                        'in_review' => t('my_programs.status_in_review'),
                        'approved' => t('my_programs.status_approved'),
                        'rejected' => t('my_programs.status_rejected'),
                    ];
                    $status_label = $status_labels[$status_filter] ?? str_replace('_', ' ', $status_filter);
                    ?>
                    <?= t('my_programs.no_filtered_programs', ['status' => $status_label]) ?>
                <?php else: ?>
                    <?= t('my_programs.no_programs') ?>
                <?php endif; ?>
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php if ($status_filter): ?>
                    <?= t('my_programs.no_filtered_message') ?>
                <?php else: ?>
                    <?= t('my_programs.no_programs_default') ?>
                <?php endif; ?>
            </p>
            <?php if (!$status_filter): ?>
                <div class="mt-4">
                    <a href="/programs" class="text-sm font-medium text-primary-600 hover:text-primary-500"><?= t('my_programs.browse_programs_link') ?></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/user.php';
