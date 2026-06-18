<?php
/**
 * Admin Submissions List View
 *
 * Shows all submissions for a form with filtering and pagination.
 */

// Ensure required variables are defined to prevent PHPStan errors
if (!isset($form) || !isset($submissions)) {
    throw new Exception('Required variables not defined for submissions list view');
}

// Optional variables (set defaults if not defined)
$status_filter = $status_filter ?? null;
$stats = $stats ?? ['total' => 0, 'submitted' => 0, 'clarification_requested' => 0];

// Scoring support
require_once __DIR__ . '/../../forms/scoring.php';
require_once __DIR__ . '/../../forms/models.php';
$scoring_enabled = is_scoring_enabled($form);
// Note: Don't pre-load questions from active version - each submission needs its own version's questions
$show_score_column = $scoring_enabled;
?>

<div class="mb-6">
    <h1 class="page-title"><?= sanitize($form['name']) ?> - Submissions</h1>
    <p class="text-gray-600 mt-1">View and manage all submissions for this form</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="card p-4">
        <div class="body-text">Total Submissions</div>
        <div class="page-title"><?= $stats['total'] ?? 0 ?></div>
    </div>
    <div class="card p-4">
        <div class="body-text">Submitted</div>
        <div class="text-2xl font-bold text-green-600"><?= $stats['submitted'] ?? 0 ?></div>
    </div>
    <div class="card p-4">
        <div class="body-text">Clarification Requested</div>
        <div class="text-2xl font-bold text-amber-600"><?= $stats['clarification_requested'] ?? 0 ?></div>
    </div>
</div>

<!-- Filters -->
<div class="card p-4 mb-6">
    <form method="GET" class="flex gap-4 items-end">
        <div class="flex-1">
            <label>Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <option value="submitted" <?= ($status_filter ?? '') === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                <option value="clarification_requested" <?= ($status_filter ?? '') === 'clarification_requested' ? 'selected' : '' ?>>Clarification Requested</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">
            Filter
        </button>
    </form>
</div>

<!-- Submissions Table -->
<div class="card">
    <?php
    // PERFORMANCE FIX: Pre-load questions for all unique versions to prevent N+1 queries
    // Instead of loading questions inside the loop (N queries), we load them once per unique version
    $questions_by_version = [];
    if ($show_score_column && !empty($submissions['rows'])) {
        // Extract unique version IDs from submissions
        $version_ids = array_unique(array_column($submissions['rows'], 'form_version_id'));
        
        // Load questions for each unique version (typically 1-3 versions, not N submissions)
        foreach ($version_ids as $vid) {
            $questions_by_version[$vid] = get_questions($vid);
        }
    }
    ?>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Submitted</th>
                <th>Status</th>
                <?php if ($show_score_column): ?>
                <th>Score</th>
                <?php endif; ?>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($submissions['rows'])): ?>
                <tr>
                    <td colspan="<?= $show_score_column ? 5 : 4 ?>" class="empty-state">
                        No submissions found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($submissions['rows'] as $submission): ?>
                    <tr class="hover:bg-gray-50">
                        <td>
                            <div class="text-sm font-medium text-gray-900"><?= sanitize($submission['user_name'] ?? 'Unknown') ?></div>
                            <div class="text-sm text-gray-500"><?= sanitize($submission['user_email'] ?? '') ?></div>
                        </td>
                        <td class="whitespace-nowrap text-gray-500">
                            <?= $submission['submitted_at'] ? format_datetime($submission['submitted_at'], 'short') : '-' ?>
                        </td>
                        <td class="whitespace-nowrap">
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
                        </td>
                        <?php if ($show_score_column): ?>
                        <td class="whitespace-nowrap">
                            <?php
                            // Use pre-loaded questions from cache (avoids N+1 query problem)
                            // Each submission uses questions from its actual version, not the active version
                            $submission_questions = $questions_by_version[$submission['form_version_id']] ?? [];
                            $sub_score = calculate_form_score($submission_questions, $submission['answers'] ?? []);
                            if ($sub_score['has_scoring']):
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                                <?= $sub_score['total'] ?> / <?= $sub_score['max_possible'] ?>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td class="whitespace-nowrap">
                            <a href="/admin/forms/<?= $form['uuid'] ?>/submissions/<?= $submission['uuid'] ?>"
                               class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php
$pagination = $submissions;
$base_url = '/admin/forms/' . $form['uuid'] . '/submissions';
$query_params = [];
if ($status_filter) $query_params['status'] = $status_filter;
require __DIR__ . '/../../../layouts/partials/pagination.php';
?>
