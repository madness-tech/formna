<?php
/** @var array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $result */
$result = $result ?? ['rows' => [], 'total' => 0, 'pages' => 0, 'page' => 1, 'per_page' => 15];
?>
<!-- User: Clarification Requests List -->
<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="page-title"><?= t('clarifications.title') ?></h1>
        <p class="body-text mt-1">
            <?= t('clarifications.subtitle') ?>
        </p>
    </div>

    <?php if (empty($result['rows'])): ?>
        <!-- Empty State -->
        <div class="card p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900"><?= t('clarifications.no_requests') ?></h3>
            <p class="body-text mt-2">
                <?= t('clarifications.no_requests_detail') ?>
            </p>
            <div class="mt-6">
                <a href="/dashboard" class="text-primary-600 hover:text-primary-700 font-medium">
                    <?= t('clarifications.back_to_dashboard') ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Clarifications List -->
        <div class="space-y-4">
            <?php foreach ($result['rows'] as $clarification): ?>
                <div class="card hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="section-title">
                                    <?= sanitize($clarification['form_name']) ?>
                                </h3>
                                <p class="body-text mt-1">
                                    <?= t('clarifications.requested_by_on', ['name' => sanitize($clarification['requester_name']), 'date' => format_datetime($clarification['created_at'], 'short')]) ?>
                                </p>
                            </div>
                            <?php $is_revision = ($clarification['status'] === 'open' && !empty($clarification['admin_feedback'])); ?>
                            <?php
                            $clar_status_labels = [
                                'open' => t('clarifications.status_open'),
                                'responded' => t('clarifications.responded'),
                                'resolved' => t('clarifications.status_resolved'),
                                'rejected' => t('clarifications.status_rejected'),
                                'cancelled' => t('clarifications.status_cancelled'),
                            ];
                            ?>
                            <span class="px-3 py-1 rounded-full text-xs font-medium
                                <?= $clarification['status'] == 'resolved' ? 'bg-green-100 text-green-800' :
                                   ($clarification['status'] == 'responded' ? 'bg-blue-100 text-blue-800' :
                                   ($is_revision ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800')) ?>">
                                <?= $is_revision ? t('clarifications.revision_needed') : ($clar_status_labels[$clarification['status']] ?? ucfirst($clarification['status'])) ?>
                            </span>
                        </div>

                        <!-- Message -->
                        <?php if (!empty($clarification['message'])): ?>
                        <div class="alert alert-info">
                            <div class="text-sm text-blue-900">
                                <?= nl2br(sanitize($clarification['message'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Questions Summary -->
                        <div class="mt-4 mb-4">
                            <div class="text-sm text-gray-700">
                                <?= t('clarifications.questions_need_attention', ['count' => count($clarification['items'])]) ?>
                            </div>
                            <div class="mt-2 space-y-1">
                                <?php 
                                $pending_count = 0;
                                foreach ($clarification['items'] as $item): 
                                    if (($item['status'] ?? null) !== 'responded') {
                                        $pending_count++;
                                    }
                                ?>
                                    <div class="flex items-center text-sm">
                                        <?php if (($item['status'] ?? null) === 'responded'): ?>
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-600">Question #<?= $item['question_id'] ?> -
                                                <span class="text-green-600 font-medium"><?= t('clarifications.responded') ?></span>
                                            </span>
                                        <?php else: ?>
                                            <svg class="w-4 h-4 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-900">
                                                <?= t('clarifications.type_needed', ['type' => ucfirst($item['type'])]) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                            <a href="/submissions/<?= $clarification['submission_uuid'] ?>" class="body-text hover:text-gray-900">
                                <?= t('clarifications.view_original') ?>
                            </a>
                            
                            <?php if ($clarification['status'] === 'open'): ?>
                                <a href="/requests/<?= $clarification['uuid'] ?>"
                                   class="btn btn-primary">
                                    <?= t('clarifications.respond_now') ?>
                                    <?php if ($pending_count > 0): ?>
                                        <span class="ml-1 bg-primary-500 px-2 py-0.5 rounded-full text-xs">
                                            <?= $pending_count ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            <?php elseif ($clarification['status'] === 'responded'): ?>
                                <span class="text-sm text-blue-600 font-medium">
                                    ✓ <?= t('clarifications.waiting_review') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-sm text-green-600 font-medium">
                                    ✓ <?= t('clarifications.status_resolved') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php
        $pagination = $result;
        $base_url = '/requests';
        $query_params = [];
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
    <?php endif; ?>
</div>
