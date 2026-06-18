<?php
// Scoring support for program submissions list
require_once __DIR__ . '/../../forms/models.php';

// Ensure required variables are defined with defaults
$program = $program ?? [];
$stats = $stats ?? ['total' => 0, 'draft' => 0, 'submitted' => 0, 'in_review' => 0, 'approved' => 0, 'rejected' => 0];
$submissions = $submissions ?? [];
$submissions_lookup = $submissions_lookup ?? [];
$status = $status ?? null;
$result = $result ?? ['page' => 1, 'pages' => 1];

// Pre-load scoring data: check which program forms have scoring enabled
/** @var array<int, array{form: array<string, mixed>}> */
$scored_forms = []; // form_id => ['form' => ...]
$any_scoring = false;
foreach (($program['form_ids'] ?? []) as $fid) {
    $f = get_form_by_id($fid);
    if ($f && is_scoring_enabled($f)) {
        // Don't pre-load questions - they need to be version-specific per submission
        $scored_forms[$fid] = ['form' => $f];
        $any_scoring = true;
    }
}

?>

<!-- Program Submissions List (Admin) -->

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title"><?= sanitize($program['name']) ?></h1>
        <p class="mt-2 text-sm text-gray-700">Review and manage program submissions</p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 flex items-center gap-3">
        <?php
        $statusBadgeConfig = [
            'active' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
            'closed' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z'],
        ];
        $statusConfig = $statusBadgeConfig[$program['status']] ?? $statusBadgeConfig['draft'];
        ?>
        <span class="badge <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?>">
            <?= ucfirst($program['status']) ?>
        </span>
        <a href="/admin/programs/<?= $program['uuid'] ?>/setup" class="btn btn-secondary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Configure
        </a>
    </div>
</div>

<!-- Filters -->
<div class="mt-6 flex gap-4">
    <div class="flex gap-2">
        <a href="?" class="filter-tab <?= $status === null ? 'filter-tab-active' : '' ?>">
            All <span class="ml-1.5 text-xs <?= $status === null ? 'text-primary-600' : 'text-gray-500' ?>">(<?= $stats['total'] ?>)</span>
        </a>
        <a href="?status=draft" class="filter-tab <?= $status === 'draft' ? 'filter-tab-active' : '' ?>">
            Draft <span class="ml-1.5 text-xs <?= $status === 'draft' ? 'text-primary-600' : 'text-gray-500' ?>">(<?= $stats['draft'] ?>)</span>
        </a>
        <a href="?status=submitted" class="filter-tab <?= $status === 'submitted' ? 'filter-tab-active' : '' ?>">
            Submitted <span class="ml-1.5 text-xs <?= $status === 'submitted' ? 'text-primary-600' : 'text-gray-500' ?>">(<?= $stats['submitted'] ?>)</span>
        </a>
        <a href="?status=in_review" class="filter-tab <?= $status === 'in_review' ? 'filter-tab-active' : '' ?>">
            In Review <span class="ml-1.5 text-xs <?= $status === 'in_review' ? 'text-primary-600' : 'text-gray-500' ?>">(<?= $stats['in_review'] ?>)</span>
        </a>
        <a href="?status=approved" class="filter-tab <?= $status === 'approved' ? 'filter-tab-active' : '' ?>">
            Approved <span class="ml-1.5 text-xs <?= $status === 'approved' ? 'text-primary-600' : 'text-gray-500' ?>">(<?= $stats['approved'] ?>)</span>
        </a>
        <a href="?status=rejected" class="filter-tab <?= $status === 'rejected' ? 'filter-tab-active' : '' ?>">
            Rejected <span class="ml-1.5 text-xs <?= $status === 'rejected' ? 'text-primary-600' : 'text-gray-500' ?>">(<?= $stats['rejected'] ?>)</span>
        </a>
    </div>
</div>


<!-- Submissions Table -->
<?php if (empty($submissions)): ?>
    <div class="card mt-6 p-16 text-center">
            <div class="max-w-sm mx-auto">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="section-title mt-4">No submissions found</h3>
                <p class="body-text mt-2">
                    <?php if ($status === 'draft'): ?>
                        There are no draft submissions. Users create drafts when they start attaching forms but haven't submitted yet.
                    <?php elseif ($status === null): ?>
                        There are no submissions for this program yet.
                    <?php else: ?>
                        There are no submissions with status "<?= ucfirst(str_replace('_', ' ', $status)) ?>" for this program.
                    <?php endif; ?>
                </p>
            </div>
        </div>
<?php else: ?>
    <div class="card mt-6">
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th class="py-4 font-semibold text-gray-700">Applicant</th>
                            <th class="py-4 font-semibold text-gray-700">Status</th>
                            <?php if ($any_scoring): ?>
                            <th class="py-4 font-semibold text-gray-700">Score</th>
                            <?php endif; ?>
                            <?php if ($status !== 'draft'): ?>
                            <th class="py-4 font-semibold text-gray-700">Review Progress</th>
                            <th class="py-4 font-semibold text-gray-700">Date Submitted</th>
                            <?php else: ?>
                            <th class="py-4 font-semibold text-gray-700">Forms Attached</th>
                            <th class="py-4 font-semibold text-gray-700">Date Created</th>
                            <?php endif; ?>
                            <?php if ($status !== 'draft'): ?>
                            <th class="py-4 font-semibold text-gray-700">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td>
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center">
                                            <span class="text-primary-700 font-semibold text-sm">
                                                <?= strtoupper(substr($sub['user_name'] ?? 'U', 0, 1)) ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-semibold text-gray-900"><?= sanitize($sub['user_name'] ?? 'Unknown User') ?></div>
                                            <div class="help-text"><?= sanitize($sub['user_email'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php
                                    $statusConfig = [
                                        'draft'      => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                                        'submitted'  => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                                        'in_review'  => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                                        'approved'   => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                                        'rejected'   => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
                                    ];
                                    $config = $statusConfig[$sub['status']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => ''];
                                    ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $config['bg'] ?> <?= $config['text'] ?>">
                                        <?php if (!empty($config['icon'])): ?>
                                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $config['icon'] ?>"></path>
                                            </svg>
                                        <?php endif; ?>
                                        <?= ucfirst(str_replace('_', ' ', $sub['status'])) ?>
                                    </span>
                                </td>
                                <?php if ($any_scoring): ?>
                                <td class="whitespace-nowrap">
                                    <?php
                                    $row_score = _calc_row_program_score($sub['submission_ids'], $scored_forms, $submissions_lookup);
                                    if ($row_score && $row_score['has_scoring']):
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                                        <?= $row_score['total'] ?> / <?= $row_score['max_possible'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($status !== 'draft'): ?>
                                <td class="whitespace-nowrap">
                                    <?php if (in_array($sub['status'], ['submitted', 'in_review'])): ?>
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900">Stage <?= $sub['current_stage'] ?></span>
                                            <?php if (!empty($program['review_stages'])): ?>
                                                <span class="text-sm text-gray-500 ml-1">of <?= count($program['review_stages']) ?></span>
                                                <div class="ml-3 flex-1 max-w-[100px]">
                                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                                        <div class="h-full bg-primary-600 rounded-full" style="width: <?= ($sub['current_stage'] / count($program['review_stages'])) * 100 ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (in_array($sub['status'], ['approved', 'rejected'])): ?>
                                        <span class="text-sm text-gray-500">Completed</span>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php if ($sub['submitted_at']): ?>
                                        <div class="text-sm text-gray-900"><?= format_datetime($sub['submitted_at'], 'date_short') ?></div>
                                        <div class="help-text"><?= format_datetime($sub['submitted_at'], 'time') ?></div>
                                        <?php
                                        // Show decision date for finalized items
                                        if (in_array($sub['status'], ['approved', 'rejected']) && !empty($sub['decisions'])):
                                            $last_decision = end($sub['decisions']);
                                            if (!empty($last_decision['decided_at'])):
                                        ?>
                                            <div class="text-xs text-gray-400 mt-1">
                                                Decided <?= format_datetime($last_decision['decided_at'], 'date_short') ?>
                                            </div>
                                        <?php endif; endif; ?>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php else: ?>
                                <td>
                                    <?php
                                    $formsAttached = count($sub['submission_ids']);
                                    $totalForms = count($program['form_ids']);
                                    $percentage = $totalForms > 0 ? ($formsAttached / $totalForms) * 100 : 0;
                                    $isComplete = $formsAttached === $totalForms;
                                    ?>
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs font-medium text-gray-700"><?= $formsAttached ?> / <?= $totalForms ?> forms</span>
                                                <span class="text-xs font-medium text-gray-700"><?= round($percentage) ?>%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="<?= $isComplete ? 'bg-green-500' : 'bg-primary-600' ?> h-2 rounded-full transition-all" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php if ($sub['created_at']): ?>
                                        <div class="text-sm text-gray-900"><?= format_datetime($sub['created_at'], 'date_short') ?></div>
                                        <div class="help-text"><?= format_datetime($sub['created_at'], 'time') ?></div>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($status !== 'draft'): ?>
                                <td class="whitespace-nowrap font-medium">
                                    <a href="/admin/review-queue/<?= $sub['uuid'] ?>" class="inline-flex items-center text-primary-600 hover:text-primary-900 transition">
                                        <?= in_array($sub['status'], ['approved', 'rejected']) ? 'View' : 'Review' ?>
                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"></path>
                                        </svg>
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Table Footer with Count -->
            <div class="bg-gray-50 px-6 py-3 border-t border-gray-200">
                <p class="body-text">
                    Showing <span class="font-medium text-gray-900"><?= count($submissions) ?></span>
                    <?php if ($status): ?>
                    <span class="font-medium text-gray-900"><?= ucfirst(str_replace('_', ' ', $status)) ?></span>
                    <?php endif; ?>
                    <?= count($submissions) === 1 ? 'submission' : 'submissions' ?>
                </p>
            </div>
        </div>

        <!-- Pagination -->
        <?php
        $pagination = $result;
        $base_url = '/admin/programs/' . $program['uuid'] . '/submissions';
        $query_params = [];
        if ($status) $query_params['status'] = $status;
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
<?php endif; ?>
