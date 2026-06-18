<?php
/** @var array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $result */
$result = $result ?? ['rows' => [], 'total' => 0, 'pages' => 0, 'page' => 1, 'per_page' => 20];
?>
<!-- Program Review Queue (Admin) -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Review Queue</h1>
        <p class="mt-2 text-sm text-gray-700">Program submissions awaiting your review</p>
    </div>
</div>

<!-- Filters -->
    <?php if (!empty($programsWithReviews)): ?>
    <div class="mt-6 flex gap-4">
        <div class="flex items-center gap-2 ml-auto">
            <label for="programFilter">Program:</label>
            <select id="programFilter" onchange="filterByProgram(this.value)">
                <option value="">All Programs</option>
                <?php foreach ($programsWithReviews as $prog): ?>
                    <option value="<?= $prog['id'] ?>" <?= (isset($_GET['program_id']) && (int)$_GET['program_id'] === $prog['id']) ? 'selected' : '' ?>>
                        <?= sanitize($prog['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>

<!-- Review Queue Table -->
<div class="card mt-6">
    <?php if (!empty($pendingReviews)): ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Applicant</th>
                        <th scope="col">Program</th>
                        <th scope="col">Stage</th>
                        <th scope="col">Assigned</th>
                        <th scope="col">Waiting</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingReviews as $review): ?>
                        <?php
                        // Calculate time waiting
                        $submitted_ts = strtotime($review['submitted_at']);
                        $days_waiting = max(0, (int)floor((time() - $submitted_ts) / 86400));
                        $waiting_class = $days_waiting >= 7 ? 'text-red-600 font-semibold' : ($days_waiting >= 3 ? 'text-amber-600 font-medium' : 'text-gray-500');
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td>
                                <div class="text-sm font-medium text-gray-900 font-semibold">
                                    <?= sanitize($review['user_name']) ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?= sanitize($review['user_email']) ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-sm font-medium text-gray-900">
                                    <?= sanitize($review['program_name']) ?>
                                </div>
                                <div class="help-text">
                                    <?= count($review['submission_ids']) ?> form<?= count($review['submission_ids']) != 1 ? 's' : '' ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="badge badge-blue">
                                    Stage <?= $review['current_stage'] ?>
                                </span>
                                <?php if (!empty($review['current_stage_info'])): ?>
                                    <div class="help-text">
                                        <?= sanitize($review['current_stage_info']['name']) ?>
                                        · <?= $review['current_stage_info']['action'] === 'score' ? 'Score' : 'Approve/Reject' ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($review['previous_stage_outcomes'])): ?>
                                    <div class="mt-1.5 space-y-0.5">
                                        <?php foreach ($review['previous_stage_outcomes'] as $stageNum => $outcome): ?>
                                            <div class="text-xs <?= $outcome['decision'] === 'approved' ? 'text-green-600' : 'text-red-600' ?>">
                                                Stage <?= $stageNum ?>: <?= ucfirst($outcome['decision']) ?><?= isset($outcome['score']) ? ' (' . $outcome['score'] . ')' : '' ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?= sanitize($review['current_stage_info']['reviewer_name'] ?? 'Unassigned') ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="text-sm <?= $waiting_class ?>">
                                    <?php if ($days_waiting === 0): ?>
                                        Today
                                    <?php elseif ($days_waiting === 1): ?>
                                        1 day
                                    <?php else: ?>
                                        <?= $days_waiting ?> days
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?= format_datetime($review['submitted_at'], 'date_short') ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-right font-medium">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="/admin/review-queue/<?= $review['uuid'] ?>"
                                       class="btn btn-primary btn-sm">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Review</span>
                                    </a>
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
        $base_url = '/admin/review-queue';
        $query_params = [];
        if (!empty($_GET['program_id'])) $query_params['program_id'] = $_GET['program_id'];
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
        <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">
                <?php echo !empty($_GET['program_id']) ? 'Nothing to see here' : 'All caught up!'; ?>
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php echo !empty($_GET['program_id']) ? 'No reviews match the selected program.' : 'You have no pending reviews at this time.'; ?>
            </p>
        </div>
        <?php endif; ?>
</div>

<script>
function filterByProgram(programId) {
    const currentUrl = new URL(window.location.href);
    if (programId) {
        currentUrl.searchParams.set('program_id', programId);
    } else {
        currentUrl.searchParams.delete('program_id');
    }
    window.location.href = currentUrl.toString();
}
</script>
