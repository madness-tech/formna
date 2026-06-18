<?php
/**
 * @var array<string,mixed> $program
 * @var int $existingCount
 * @var bool $canSubmitNew
 * @var array<string,mixed>|null $draft
 * @var array<int,array<string,mixed>> $formStatuses
 * @var array<int,array<string,mixed>> $pastSubmissions
 * @var array<int,array<string,mixed>> $pastSubmissionDetails
 * @var int $maxSubs
 */

$settings = $program['settings'];
?>

<div class="mb-8">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="page-title"><?= sanitize($program['name']) ?></h1>
            <?php if ($program['description']): ?>
                <p class="body-text mt-1"><?= sanitize($program['description']) ?></p>
            <?php endif; ?>
        </div>
        <a href="/programs" class="text-sm text-primary-600 hover:text-primary-800 font-medium whitespace-nowrap ml-4">
            <?= t('program_view.back_to_programs') ?>
        </a>
    </div>
</div>

<!-- Program Info Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <?php if (!empty($settings['submission_period_end'])): ?>
        <div class="card p-4">
            <div class="body-text flex items-center mb-1">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <?= t('program_view.deadline') ?>
            </div>
            <div class="section-title"><?= format_datetime($settings['submission_period_end'], 'short') ?></div>
        </div>
    <?php endif; ?>
    <div class="card p-4">
        <div class="body-text flex items-center mb-1">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <?= t('program_view.required_forms') ?>
        </div>
        <div class="section-title"><?= count($program['forms']) ?></div>
    </div>
    <div class="card p-4">
        <div class="body-text flex items-center mb-1">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <?= t('program_view.your_submissions') ?>
        </div>
        <div class="section-title"><?= $existingCount ?></div>
    </div>
</div>

<!-- Rules & Instructions -->
<?php if (!empty($settings['rules']) || !empty($settings['instructions'])): ?>
    <div class="card p-6 mb-8">
        <?php if (!empty($settings['rules'])): ?>
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-2"><?= t('program_view.rules') ?></h3>
                <div class="text-sm text-gray-700 whitespace-pre-line"><?= sanitize($settings['rules']) ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($settings['instructions'])): ?>
            <div>
                <h3 class="text-sm font-semibold text-gray-900 mb-2"><?= t('program_view.instructions') ?></h3>
                <div class="text-sm text-gray-700 whitespace-pre-line"><?= sanitize($settings['instructions']) ?></div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($canSubmitNew): ?>
    <!-- Current Draft — Form Checklist -->
    <div class="card mb-8">
        <div class="bg-primary-50 px-6 py-4 border-b border-primary-100">
            <h2 class="text-lg font-semibold text-primary-900"><?= t('program_view.your_program_entry') ?></h2>
            <p class="text-sm text-primary-700 mt-1"><?= t('program_view.attach_instructions') ?></p>
        </div>

        <div class="divide-y divide-gray-200">
            <?php foreach ($formStatuses as $fs): ?>
                <?php
                $form = $fs['form'];
                $userSubs = $fs['user_submissions'];
                $attachedId = $fs['attached_submission_id'];
                $isAttached = !empty($attachedId);
                ?>
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start flex-1">
                            <!-- Status icon -->
                            <div class="flex-shrink-0">
                                <?php if ($isAttached): ?>
                                    <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                <?php else: ?>
                                    <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center">
                                        <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Form info -->
                            <div class="ml-4 flex-1">
                                <h3 class="text-sm font-semibold text-gray-900 leading-6"><?= sanitize($form['name']) ?></h3>
                                <?php if (!empty($form['description'])): ?>
                                    <p class="text-sm text-gray-500 mt-0.5"><?= sanitize($form['description']) ?></p>
                                <?php endif; ?>

                                <?php if ($isAttached): ?>
                                    <!-- Show attached submission -->
                                    <?php
                                    $attachedSub = null;
                                    foreach ($userSubs as $s) {
                                        if ((int)$s['id'] === (int)$attachedId) {
                                            $attachedSub = $s;
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php if ($attachedSub): ?>
                                        <div class="mt-2 inline-flex items-center px-3 py-1.5 bg-green-50 border border-green-200 rounded-lg text-sm">
                                            <svg class="w-4 h-4 text-green-600 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                            </svg>
                                            <span class="text-green-800">
                                                <?= t('program_view.attached_on', ['date' => format_datetime($attachedSub['submitted_at'], 'date_short')]) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif (empty($userSubs)): ?>
                                    <!-- No submissions available -->
                                    <p class="mt-2 text-sm text-amber-600">
                                        <?= t('program_view.not_submitted_yet') ?>
                                        <a href="/forms/<?= $form['uuid'] ?>" class="underline font-medium hover:text-amber-800"><?= t('program_view.fill_out_now') ?></a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="ml-4 flex-shrink-0">
                            <?php if ($isAttached): ?>
                                <!-- Detach button -->
                                <form method="POST" action="/programs/<?= $program['uuid'] ?>/attach">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_id" value="<?= $form['id'] ?>">
                                    <input type="hidden" name="attach_action" value="detach">
                                    <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium">
                                        <?= t('program_view.detach') ?>
                                    </button>
                                </form>
                            <?php elseif (!empty($userSubs)): ?>
                                <!-- Attach selector -->
                                <form method="POST" action="/programs/<?= $program['uuid'] ?>/attach" class="flex items-center gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_id" value="<?= $form['id'] ?>">
                                    <input type="hidden" name="attach_action" value="attach">
                                    <?php if (count($userSubs) === 1): ?>
                                        <input type="hidden" name="submission_id" value="<?= $userSubs[0]['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <?= t('program_view.attach') ?>
                                        </button>
                                    <?php else: ?>
                                        <select name="submission_id" required class="px-2 py-1.5">
                                            <option value=""><?= t('program_view.select_submission') ?></option>
                                            <?php foreach ($userSubs as $s): ?>
                                                <?php
                                                $sub_status_labels = [
                                                    'submitted' => t('submission.status_submitted'),
                                                    'in_review' => t('submission.status_in_review'),
                                                    'approved' => t('submission.status_approved'),
                                                    'rejected' => t('submission.status_rejected'),
                                                    'clarification_requested' => t('submission.status_clarification'),
                                                ];
                                                ?>
                                                <option value="<?= $s['id'] ?>">
                                                    <?= t('program_view.submitted_on', ['date' => format_datetime($s['submitted_at'], 'short')]) ?>
                                                    <?php if ($s['status'] !== 'submitted'): ?>
                                                        (<?= $sub_status_labels[$s['status']] ?? ucfirst($s['status']) ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <?= t('program_view.attach') ?>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Submit Entry -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            <?php
            $allAttached = true;
            foreach ($formStatuses as $fs) {
                if (empty($fs['attached_submission_id'])) {
                    $allAttached = false;
                    break;
                }
            }
            ?>
            <div class="flex items-center gap-3">
                <?php if ($allAttached): ?>
                    <form method="POST" action="/programs/<?= $program['uuid'] ?>/submit" onsubmit="return confirm(<?= sanitize(json_encode(t('program_view.confirm_submit'))) ?>)">
                        <?= csrf_field() ?>
                        <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold text-sm transition">
                            <?= t('program_view.submit_program_entry') ?>
                        </button>
                    </form>
                <?php else: ?>
                    <button disabled class="w-full sm:w-auto px-6 py-3 bg-gray-300 text-gray-500 rounded-lg font-semibold text-sm cursor-not-allowed">
                        <?= t('program_view.submit_program_entry') ?>
                    </button>
                <?php endif; ?>

                <?php if ($draft): ?>
                    <form method="POST" action="/programs/<?= $program['uuid'] ?>/discard-draft" onsubmit="return confirm(<?= sanitize(json_encode(t('program_view.confirm_discard'))) ?>)">
                        <?= csrf_field() ?>
                        <button type="submit" class="inline-flex items-center p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" title="<?= t('program_view.discard_draft') ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="help-text mt-2">
                <?php if ($allAttached): ?>
                    <?= t('program_view.all_attached') ?>
                <?php else: ?>
                    <?= t('program_view.attach_all_required') ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

<?php elseif ($maxSubs > 1): ?>
    <!-- Max submissions reached (only shown when more than 1 submission is allowed) -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <div>
                <h3 class="text-sm font-semibold text-yellow-900"><?= t('program_view.max_submissions_reached') ?></h3>
                <p class="mt-1 text-sm text-yellow-700"><?= t('program_view.max_submissions_message', ['max' => $maxSubs]) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Past Submissions for this Program -->
<?php if (!empty($pastSubmissions)): ?>
    <?php
    // Build form lookup by ID for quick access in the loop
    $formsById = [];
    foreach ($program['forms'] as $f) {
        $formsById[$f['id']] = $f;
    }

    $ps_status_labels = [
        'draft' => t('my_programs.status_draft'),
        'submitted' => t('my_programs.status_submitted'),
        'in_review' => t('my_programs.status_in_review'),
        'approved' => t('my_programs.status_approved'),
        'rejected' => t('my_programs.status_rejected'),
    ];
    $statusColors = [
        'submitted'  => 'bg-blue-100 text-blue-800',
        'in_review'  => 'bg-yellow-100 text-yellow-800',
        'approved'   => 'bg-green-100 text-green-800',
        'rejected'   => 'bg-red-100 text-red-800',
    ];
    ?>
    <div class="mt-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4"><?= t('program_view.your_submissions') ?></h2>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th class="w-8"></th>
                        <th><?= t('common.status') ?></th>
                        <th><?= t('submission.submitted_date') ?></th>
                        <th><?= t('program_view.decision_date') ?></th>
                        <th><?= t('common.forms') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pastSubmissions as $psIndex => $ps): ?>
                        <?php
                        $last_decision = !empty($ps['decisions']) ? end($ps['decisions']) : null;
                        $color = $statusColors[$ps['status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="togglePsDetail(<?= $psIndex ?>)">
                            <td class="pe-0">
                                <svg class="w-4 h-4 text-gray-400 transform transition-transform duration-200 ps-chevron-<?= $psIndex ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"></path>
                                </svg>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $color ?>">
                                    <?= $ps_status_labels[$ps['status']] ?? ucfirst(str_replace('_', ' ', $ps['status'])) ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?= $ps['submitted_at'] ? format_datetime($ps['submitted_at'], 'short') : '—' ?>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?php if (in_array($ps['status'], ['approved', 'rejected']) && $last_decision && !empty($last_decision['decided_at'])): ?>
                                    <?= format_datetime($last_decision['decided_at'], 'short') ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-gray-900">
                                <?= count($ps['submission_ids']) ?> / <?= count($program['forms']) ?>
                            </td>
                        </tr>
                        <!-- Expandable detail row: attached form submissions -->
                        <tr id="ps-detail-<?= $psIndex ?>" class="hidden">
                            <td colspan="5" class="px-4 py-4 bg-gray-50 border-t-0">
                                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3"><?= t('program_view.attached_forms') ?></div>
                                <div class="space-y-2">
                                    <?php foreach ($program['forms'] as $form): ?>
                                        <?php
                                        $subId = $ps['submission_ids'][(string)$form['id']] ?? null;
                                        $subDetail = $subId ? ($pastSubmissionDetails[(int)$subId] ?? null) : null;
                                        ?>
                                        <div class="flex items-center justify-between py-2 px-3 bg-white rounded-lg border border-gray-200">
                                            <div class="flex items-center min-w-0">
                                                <?php if ($subDetail): ?>
                                                    <div class="flex-shrink-0 w-5 h-5 rounded-full bg-green-100 flex items-center justify-center">
                                                        <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="flex-shrink-0 w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="ms-2.5 text-sm text-gray-900 truncate"><?= sanitize($form['name']) ?></span>
                                            </div>
                                            <?php if ($subDetail): ?>
                                                <div class="flex items-center gap-3 flex-shrink-0 ms-4">
                                                    <span class="text-xs text-gray-500"><?= format_datetime($subDetail['submitted_at'], 'date_short') ?></span>
                                                    <a href="/submissions/<?= sanitize($subDetail['uuid']) ?>" class="text-sm text-primary-600 hover:text-primary-800 font-medium" onclick="event.stopPropagation()">
                                                        <?= t('common.view') ?> <?= is_rtl() ? '←' : '→' ?>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400 flex-shrink-0 ms-4"><?= t('program_view.not_attached') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function togglePsDetail(index) {
        var detail = document.getElementById('ps-detail-' + index);
        var chevron = document.querySelector('.ps-chevron-' + index);
        var isRtl = document.documentElement.dir === 'rtl';
        if (detail.classList.contains('hidden')) {
            detail.classList.remove('hidden');
            chevron.style.transform = isRtl ? 'rotate(-90deg)' : 'rotate(90deg)';
        } else {
            detail.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
        }
    }
    </script>
<?php endif; ?>
