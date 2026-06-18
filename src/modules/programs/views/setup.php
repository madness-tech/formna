<?php
/**
 * Program Setup View
 *
 * @var array<string,mixed> $program Program data with id, uuid, name, description, status, form_ids, review_stages, settings
 * @var array<string,mixed> $stats Program statistics (total submissions, etc.)
 * @var array<int,array<string,mixed>> $allForms All published forms available for program selection
 * @var array<int,array<string,mixed>> $reviewers All active admin/reviewer users available for assignment
 * @var array<int,array{name:string,deadline:string}> $deadlineConflicts Forms whose deadlines are earlier than the program closing date
 * @var array<int,array{id:int,name:string,reason:string,status:?string}> $unavailableForms Forms that block activation
 */
?>

<!-- Program Setup (Admin) — Unified: info + forms + settings + review stages -->
<?php $hasSubmissions = program_has_submissions($program['id']); ?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title"><?= sanitize($program['name']) ?></h1>
        <p class="mt-2 text-sm text-gray-700">Configure program requirements, review workflow, and submission settings</p>
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
        <a href="/admin/programs/<?= $program['uuid'] ?>/submissions" class="btn btn-secondary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            View Submissions
            <span class="ml-2 px-2 py-0.5 bg-primary-100 text-primary-700 rounded-full text-xs font-semibold"><?= $stats['total'] ?? 0 ?></span>
        </a>
        <?php if ($program['status'] === 'active'): ?>
            <form method="POST" action="/admin/programs/<?= $program['uuid'] ?>/end" onsubmit="return confirm('Are you sure you want to close this program? No new submissions will be accepted and batch notifications will be sent if enabled.')" style="display: inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Close Program
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Warning Banner for Locked Fields -->
<?php if ($hasSubmissions): ?>
    <div class="mt-6 bg-amber-50 border border-amber-200 rounded-lg p-5">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-sm font-semibold text-amber-900">Configuration Restrictions in Effect</h3>
                    <div class="mt-2 text-sm text-amber-800">
                        <p>This program has received <span class="font-semibold"><?= $stats['total'] ?> submission<?= $stats['total'] != 1 ? 's' : '' ?></span>. To maintain fairness and consistency for all applicants:</p>
                        <ul class="mt-2 ml-5 list-disc space-y-1">
                            <li><strong>Required Forms</strong> cannot be added or removed</li>
                            <li><strong>Review stage structure</strong> (names, actions, thresholds) cannot be modified</li>
                            <li><strong>Submission Opening Date</strong> cannot be changed</li>
                        </ul>
                        <p class="mt-2">You can still update program description, rules, instructions, submission closing date, notification settings, and <strong>reassign reviewers</strong> on any stage.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Warning Banner for Form Deadline Conflicts -->
    <?php if (!empty($deadlineConflicts)): ?>
        <div class="mt-6 bg-orange-50 border border-orange-200 rounded-lg p-5">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-sm font-semibold text-orange-900">Form Deadline Mismatch Detected</h3>
                    <div class="mt-2 text-sm text-orange-800">
                        <p>The program closes on <strong><?= format_datetime($program['settings']['submission_period_end'], 'long') ?></strong>, but the following forms have earlier submission deadlines:</p>
                        <ul class="mt-2 ml-5 list-disc space-y-1">
                            <?php foreach ($deadlineConflicts as $conflict): ?>
                                <li><strong><?= sanitize($conflict['name']) ?></strong> — closes <?= format_datetime($conflict['deadline'], 'long') ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="mt-2">Users will be unable to submit these forms after their deadlines pass, even though the program is still accepting applications. Consider aligning the form deadlines with the program closing date, or adjusting the program closing date.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($unavailableForms)): ?>
        <div class="mt-6 bg-red-50 border border-red-200 rounded-lg p-5">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-sm font-semibold text-red-900">Program activation blocked by unavailable forms</h3>
                    <div class="mt-2 text-sm text-red-800">
                        <p>This program cannot be published until all required forms are available and published.</p>
                        <ul class="mt-2 ml-5 list-disc space-y-1">
                            <?php foreach ($unavailableForms as $unavailable): ?>
                                <li>
                                    <strong><?= sanitize((string)$unavailable['name']) ?></strong>
                                    <?php if (!empty($unavailable['status'])): ?>
                                        — current status: <?= sanitize((string)$unavailable['status']) ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/programs/<?= $program['uuid'] ?>/setup" id="programForm">
        <?= csrf_field() ?>
    
        <!-- 1. Basic Information -->
        <div class="card mt-6">
            <div class="card-header bg-gradient-to-r from-primary-50 to-blue-50">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="section-title">Basic Information</h3>
                        <p class="body-text mt-0.5">Program name, description, and applicant guidance</p>
                    </div>
                </div>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label for="progName">Program Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="progName" required value="<?= sanitize($program['name']) ?>"
                           placeholder="e.g., 2024 Innovation Fellowship">
                    <p class="help-text">This will be displayed to applicants on the programs list</p>
                </div>
                <div>
                    <label for="progDesc">Description</label>
                    <textarea name="description" id="progDesc" rows="3"
                              placeholder="Brief overview of the program and its objectives"><?= sanitize($program['description']) ?></textarea>
                    <p class="help-text">A brief summary helping applicants understand the program's purpose</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="progRules">Rules & Eligibility</label>
                        <textarea name="rules" id="progRules" rows="4"
                                  placeholder="e.g., Must be 18+, available criteria, restrictions..."><?= sanitize($program['settings']['rules'] ?? '') ?></textarea>
                        <p class="help-text">Eligibility criteria and program rules shown to applicants</p>
                    </div>
                    <div>
                        <label for="progInstructions">Application Instructions</label>
                        <textarea name="instructions" id="progInstructions" rows="4"
                                  placeholder="e.g., Complete all forms, ensure documents are clear..."><?= sanitize($program['settings']['instructions'] ?? '') ?></textarea>
                        <p class="help-text">Step-by-step guidance to help applicants complete submissions</p>
                    </div>
                </div>
            </div>
        </div>

    <!-- 2. Required Forms -->
    <?php
    // Build lookup for selected forms in order
    $selectedFormIds = $program['form_ids'] ?? [];
    $allFormsById = [];
    foreach ($allForms as $f) {
        $allFormsById[$f['id']] = $f;
    }
    ?>
    <div class="card mt-6 <?= $hasSubmissions ? 'relative' : '' ?>">
            <?php if ($hasSubmissions): ?>
                <div class="absolute top-5 right-6 z-10">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                        <svg class="h-3.5 w-3.5 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                        Locked
                    </span>
                </div>
            <?php endif; ?>
            <div class="card-header bg-gradient-to-r from-purple-50 to-pink-50">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="section-title">Required Forms</h3>
                        <p class="body-text mt-0.5">
                            <?php if ($hasSubmissions): ?>
                                Form requirements locked — <?= count($selectedFormIds) ?> form<?= count($selectedFormIds) != 1 ? 's' : '' ?> currently required for submission
                            <?php else: ?>
                                Select forms and drag to set the order applicants will see them
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <?php if (empty($allForms)): ?>
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-900">No forms available</p>
                        <p class="text-sm text-gray-500">Create forms first before configuring program requirements.</p>
                    </div>
                <?php else: ?>

                    <?php if (!$hasSubmissions): ?>
                        <!-- Selected Forms (sortable) -->
                        <div class="mb-4">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Selected Forms — drag to reorder</label>
                            <div id="selectedFormsList" class="mt-2 border border-gray-300 rounded-lg bg-white min-h-[52px]">
                                <?php if (empty($selectedFormIds)): ?>
                                    <div id="noFormsSelected" class="p-4 text-center text-sm text-gray-400">
                                        No forms selected. Choose from the list below.
                                    </div>
                                <?php endif; ?>
                                <?php foreach ($selectedFormIds as $fid): ?>
                                    <?php $f = $allFormsById[$fid] ?? null; if (!$f) continue; ?>
                                    <div class="selected-form-item flex items-center p-3 border-b border-gray-100 last:border-0 group" data-form-id="<?= $f['id'] ?>">
                                        <input type="hidden" name="form_ids[]" value="<?= $f['id'] ?>">
                                        <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500 cursor-grab active:cursor-grabbing flex-shrink-0 drag-handle" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
                                        </svg>
                                        <span class="ml-3 text-sm font-medium text-gray-900 flex-1"><?= sanitize($f['name']) ?></span>
                                        <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                            <?= $f['status'] === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                            <?= $f['status'] === 'published' ? '✓ Published' : ucfirst($f['status']) ?>
                                        </span>
                                        <button type="button" onclick="removeForm(this)" class="ml-3 text-gray-300 hover:text-red-500 transition flex-shrink-0" title="Remove">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Available Forms (click to add) -->
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Available Forms — click to add</label>
                            <div id="availableFormsList" class="mt-2 border border-gray-200 rounded-lg bg-gray-50 max-h-60 overflow-y-auto">
                                <?php
                                $hasAvailable = false;
                                foreach ($allForms as $form):
                                    $isSelected = in_array($form['id'], $selectedFormIds);
                                    $hasAvailable = $hasAvailable || !$isSelected;
                                ?>
                                    <div class="available-form-item flex items-center p-3 border-b border-gray-200 last:border-0 hover:bg-white cursor-pointer transition <?= $isSelected ? 'hidden' : '' ?>"
                                         data-form-id="<?= $form['id'] ?>"
                                         data-form-name="<?= sanitize($form['name']) ?>"
                                         data-form-status="<?= sanitize($form['status']) ?>"
                                         onclick="addForm(this)">
                                        <svg class="w-4 h-4 text-purple-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        <span class="ml-3 text-sm text-gray-700 flex-1"><?= sanitize($form['name']) ?></span>
                                        <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                            <?= $form['status'] === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                            <?= $form['status'] === 'published' ? '✓ Published' : ucfirst($form['status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <div id="noFormsAvailable" class="p-3 text-center text-sm text-gray-400 <?= $hasAvailable ? 'hidden' : '' ?>">
                                    All available forms have been selected.
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Locked: read-only ordered list -->
                        <div class="border border-gray-300 rounded-lg bg-gray-50">
                            <?php foreach ($selectedFormIds as $fid): ?>
                                <?php $f = $allFormsById[$fid] ?? null; if (!$f) continue; ?>
                                <div class="flex items-center p-3 border-b border-gray-200 last:border-0">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-100 text-purple-700 text-xs font-semibold flex-shrink-0">
                                        <?= array_search($fid, $selectedFormIds) + 1 ?>
                                    </span>
                                    <span class="ml-3 text-sm font-medium text-gray-600 flex-1"><?= sanitize($f['name']) ?></span>
                                    <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        <?= $f['status'] === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                        <?= $f['status'] === 'published' ? '✓ Published' : ucfirst($f['status']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($selectedFormIds)): ?>
                    <p class="mt-3 text-xs text-gray-600">
                        <strong><?= count($selectedFormIds) ?> form<?= count($selectedFormIds) != 1 ? 's' : '' ?> selected</strong>
                        — Applicants will see the forms in the order shown above
                    </p>
                <?php endif; ?>
            </div>
        </div>

    <!-- 3. Submission Settings -->
    <div class="card mt-6">
            <div class="card-header bg-gradient-to-r from-green-50 to-emerald-50">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="section-title">Submission Settings</h3>
                        <p class="body-text mt-0.5">Configure submission window, limits, and notification preferences</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label for="maxSubs">
                            Max Submissions Per User
                        </label>
                        <input type="number" name="max_submissions_per_user" id="maxSubs" min="1"
                               value="<?= (int)($program['settings']['max_submissions_per_user'] ?? 1) ?>"
                               class="focus:ring-green-500 focus:border-green-500">
                        <p class="help-text">How many times each user can apply to this program</p>
                    </div>
                    <div>
                        <label for="periodStart">
                            Submission Period Opens
                            <?php if ($hasSubmissions): ?>
                                <span class="inline-flex items-center ml-2 px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                    Locked
                                </span>
                            <?php endif; ?>
                        </label>
                        <input type="datetime-local" name="submission_period_start" id="periodStart"
                               value="<?= to_datetime_local($program['settings']['submission_period_start'] ?? null) ?>"
                               <?= $hasSubmissions ? 'readonly' : '' ?>
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition
                                      <?= $hasSubmissions ? 'bg-gray-100 cursor-not-allowed text-gray-600' : '' ?>">
                        <p class="text-xs mt-1.5 <?= $hasSubmissions ? 'text-amber-700 font-medium' : 'text-gray-500' ?>">
                            <?= $hasSubmissions ? 'Cannot be changed after submissions are received' : 'Must be current or future date/time' ?>
                        </p>
                    </div>
                    <div>
                        <label for="periodEnd">
                            Submission Period Closes
                        </label>
                        <input type="datetime-local" name="submission_period_end" id="periodEnd"
                               value="<?= to_datetime_local($program['settings']['submission_period_end'] ?? null) ?>"
                               class="focus:ring-green-500 focus:border-green-500">
                        <p class="help-text">Must be at least 1 hour after opening date</p>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Notification Settings</h4>
                    <div class="space-y-3">
                        <label class="flex items-start p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition">
                            <input type="checkbox" name="batch_notifications" value="1"
                                   <?= !empty($program['settings']['batch_notifications']) ? 'checked' : '' ?>
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-900">Send batch notifications when program closes</span>
                                <p class="help-text">Wait until program closure to notify all applicants at once (instead of notifying individually after each decision)</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

    <!-- 4. Review Stages -->
    <div class="card mt-6 <?= $hasSubmissions ? 'relative' : '' ?>">
            <?php if ($hasSubmissions): ?>
                <div class="absolute top-5 right-6 z-10">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                        <svg class="h-3.5 w-3.5 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                        Structure Locked
                    </span>
                </div>
            <?php endif; ?>
            <div class="card-header bg-gradient-to-r from-orange-50 to-amber-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center flex-1">
                        <div class="flex-shrink-0 w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="section-title">Review Stages</h3>
                            <p class="body-text mt-0.5">
                                <?php if ($hasSubmissions): ?>
                                    Stage structure locked — you can reassign reviewers on any stage
                                <?php else: ?>
                                    Define the review pipeline and assign reviewers to each stage
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php if (!$hasSubmissions): ?>
                        <button type="button" onclick="addStage()"
                                class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm font-medium transition ml-4">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Stage
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="p-6">
                <div id="stagesContainer" class="space-y-4">
                    <?php if (!empty($program['review_stages'])): ?>
                        <?php foreach ($program['review_stages'] as $index => $stage): ?>
                            <div class="stage-card border-2 border-gray-200 rounded-lg p-5 <?= $hasSubmissions ? 'bg-gray-50' : 'bg-white hover:border-orange-200' ?> transition" data-order="<?= $stage['order'] ?>">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex items-center">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-orange-100 text-orange-700 font-semibold text-sm mr-3">
                                            <?= $stage['order'] ?>
                                        </div>
                                        <h4 class="text-base font-semibold text-gray-900">Stage <?= $stage['order'] ?></h4>
                                    </div>
                                    <?php if (!$hasSubmissions): ?>
                                        <button type="button" onclick="removeStage(this)"
                                                class="text-red-600 hover:text-red-700 text-sm font-medium flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Remove
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php if (!$hasSubmissions): ?>
                                        <input type="hidden" name="stages[<?= $index ?>][order]" value="<?= $stage['order'] ?>" class="stage-order">
                                    <?php endif; ?>
                                    <div>
                                        <label>Stage Name</label>
                                        <input type="text" <?= $hasSubmissions ? '' : 'name="stages[' . $index . '][name]"' ?> value="<?= sanitize($stage['name']) ?>" required
                                               <?= $hasSubmissions ? 'readonly' : '' ?>
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm <?= $hasSubmissions ? 'bg-gray-100 cursor-not-allowed text-gray-600' : '' ?>"
                                               placeholder="e.g., Initial Review">
                                    </div>
                                    <div>
                                        <label>
                                            Reviewer
                                            <?php if ($hasSubmissions): ?>
                                                <span class="inline-flex items-center ml-2 px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                                    <svg class="h-3 w-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                    </svg>
                                                    Editable
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                        <select name="<?= $hasSubmissions ? 'stage_reviewers[' . $stage['order'] . ']' : 'stages[' . $index . '][reviewer_id]' ?>" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm <?= $hasSubmissions ? 'border-primary-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500' : '' ?>">
                                            <option value="">Select reviewer…</option>
                                            <?php foreach ($reviewers as $reviewer): ?>
                                                <option value="<?= $reviewer['id'] ?>" <?= (int)$stage['reviewer_id'] === (int)$reviewer['id'] ? 'selected' : '' ?>>
                                                    <?= sanitize($reviewer['name']) ?> (<?= sanitize($reviewer['role']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($hasSubmissions): ?>
                                            <p class="mt-1 text-xs text-green-700">Reassigning moves pending reviews to the new reviewer</p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label>Review Action</label>
                                        <select <?= $hasSubmissions ? '' : 'name="stages[' . $index . '][action]"' ?>
                                                <?= $hasSubmissions ? 'disabled' : '' ?>
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm <?= $hasSubmissions ? 'bg-gray-100 cursor-not-allowed text-gray-600' : '' ?>">
                                            <option value="approve_reject" <?= ($stage['action'] ?? '') === 'approve_reject' ? 'selected' : '' ?>>Approve / Reject</option>
                                            <option value="score" <?= ($stage['action'] ?? '') === 'score' ? 'selected' : '' ?>>Score (0-100)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Pass Threshold <span class="help-text">(for scoring only)</span></label>
                                        <input type="number" <?= $hasSubmissions ? '' : 'name="stages[' . $index . '][pass_threshold]"' ?> value="<?= $stage['pass_threshold'] ?? '' ?>"
                                               min="0" max="100"
                                               <?= $hasSubmissions ? 'readonly' : '' ?>
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm <?= $hasSubmissions ? 'bg-gray-100 cursor-not-allowed text-gray-600' : '' ?>"
                                               placeholder="e.g., 70">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8" id="noStagesMsg">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            <p class="mt-2 text-sm font-medium text-gray-900">No review stages configured</p>
                            <p class="text-sm text-gray-500">You must configure at least one review stage</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <!-- Actions -->
    <div class="mt-6 flex justify-between items-center">
            <a href="/admin/programs" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                ← Back to Programs
            </a>
            <div class="flex items-center space-x-3">
                <?php if ($program['status'] === 'draft'): ?>
                    <?php if (empty($unavailableForms)): ?>
                        <button type="button" onclick="publishProgram()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">
                            Publish Program
                        </button>
                    <?php else: ?>
                        <button type="button" disabled class="px-4 py-2 bg-gray-400 text-white rounded-lg font-medium cursor-not-allowed opacity-80">
                            Publish Program
                        </button>
                    <?php endif; ?>
                <?php elseif ($program['status'] === 'active'): ?>
                    <button type="button" onclick="unpublishProgram()" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium">
                        Unpublish Program
                    </button>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">
                    Save Program
                </button>
            </div>
    </div>
</form>

<!-- Hidden forms for publish/unpublish -->
<?php if ($program['status'] === 'draft'): ?>
    <form id="publishForm" method="POST" action="/admin/programs/<?= $program['uuid'] ?>/publish" style="display:none;">
        <?= csrf_field() ?>
    </form>
<?php elseif ($program['status'] === 'active'): ?>
    <form id="unpublishForm" method="POST" action="/admin/programs/<?= $program['uuid'] ?>/unpublish" style="display:none;">
        <?= csrf_field() ?>
    </form>
<?php endif; ?>

<script src="<?= asset('/js/vendor/Sortable.min.js') ?>"></script>
<script>
let stageCounter = <?= !empty($program['review_stages']) ? count($program['review_stages']) : 0 ?>;

function addStage() {
    const container = document.getElementById('stagesContainer');
    const msg = document.getElementById('noStagesMsg');
    if (msg) msg.remove();

    // Clear any validation error on the stages container
    container.classList.remove('border-red-400');
    var stageErr = container.parentNode.querySelector('.field-error');
    if (stageErr) stageErr.remove();

    const order = container.querySelectorAll('.stage-card').length + 1;
    stageCounter++;

    const html = `
        <div class="stage-card border border-gray-200 rounded-lg p-4" data-order="${order}">
            <div class="flex justify-between items-start mb-3">
                <h4 class="text-sm font-semibold text-gray-700">Stage ${order}</h4>
                <button type="button" onclick="removeStage(this)" class="text-red-600 hover:text-red-700 text-sm">Remove</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="stages[${stageCounter}][order]" value="${order}" class="stage-order">
                <div>
                    <label class="text-xs text-gray-600">Stage Name</label>
                    <input type="text" name="stages[${stageCounter}][name]" required placeholder="e.g. Initial Review">
                </div>
                <div>
                    <label class="text-xs text-gray-600">Reviewer</label>
                    <select name="stages[${stageCounter}][reviewer_id]" required>
                        <option value="">Select reviewer…</option>
                        <?php foreach ($reviewers as $reviewer): ?>
                            <option value="<?= $reviewer['id'] ?>"><?= sanitize($reviewer['name']) ?> (<?= sanitize($reviewer['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-600">Action</label>
                    <select name="stages[${stageCounter}][action]">
                        <option value="approve_reject">Approve / Reject</option>
                        <option value="score">Score</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-600">Pass Threshold (score only)</label>
                    <input type="number" name="stages[${stageCounter}][pass_threshold]" min="0" max="100" placeholder="e.g. 70">
                </div>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
    updateStageNumbers();
}

function removeStage(btn) {
    btn.closest('.stage-card').remove();
    updateStageNumbers();
    // Show message if empty
    const container = document.getElementById('stagesContainer');
    if (!container.querySelector('.stage-card')) {
        container.innerHTML = '<p class="empty-state py-4" id="noStagesMsg">No review stages configured. You must add at least one stage to save this program.</p>';
    }
}

function updateStageNumbers() {
    document.querySelectorAll('.stage-card').forEach((card, idx) => {
        const order = idx + 1;
        card.dataset.order = order;
        card.querySelector('h4').textContent = `Stage ${order}`;
        card.querySelector('.stage-order').value = order;
    });
}

function publishProgram() {
    if (confirm('Are you sure you want to publish this program? It will become visible to users.')) {
        document.getElementById('publishForm').submit();
    }
}

function unpublishProgram() {
    if (confirm('Are you sure you want to unpublish this program? It will no longer be visible to users.')) {
        document.getElementById('unpublishForm').submit();
    }
}

// Inline validation helpers (matches form settings pattern)
function showFieldError(field, message) {
    field.classList.add('border-red-400');
    field.classList.remove('border-gray-300', 'border-gray-200');
    var err = document.createElement('p');
    err.className = 'field-error';
    err.setAttribute('role', 'alert');
    err.textContent = message;
    field.parentNode.insertBefore(err, field.nextSibling);
}

// Prominent section-level error banner for containers (forms list, stages)
function showSectionError(container, message) {
    container.classList.add('border-red-400');
    container.classList.remove('border-gray-300', 'border-gray-200');
    var banner = document.createElement('div');
    banner.className = 'field-error flex items-center gap-2 mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium';
    banner.setAttribute('role', 'alert');
    banner.innerHTML = '<svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">'
        + '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>'
        + '</svg>' + message;
    container.parentNode.insertBefore(banner, container.nextSibling);
}

function clearErrors() {
    document.querySelectorAll('#programForm .field-error').forEach(function(el) { el.remove(); });
    document.querySelectorAll('#programForm .border-red-400').forEach(function(el) {
        el.classList.remove('border-red-400');
        el.classList.add('border-gray-300');
    });
}

// Clear error on a field when user interacts with it
document.getElementById('programForm').addEventListener('input', function(e) {
    var field = e.target;
    if (field.classList.contains('border-red-400')) {
        field.classList.remove('border-red-400');
        field.classList.add('border-gray-300');
        var next = field.nextElementSibling;
        if (next && next.classList.contains('field-error')) next.remove();
    }
});

// Set minimum end date when start date changes
document.getElementById('periodStart').addEventListener('change', function() {
    const startValue = this.value;
    const endInput = document.getElementById('periodEnd');
    
    if (startValue) {
        const startDate = new Date(startValue);
        startDate.setHours(startDate.getHours() + 1);
        const minEndFromStart = startDate.toISOString().slice(0, 16);
        
        // Use the later of: current time or (start + 1 hour)
        const now = new Date();
        const currentMin = now.toISOString().slice(0, 16);
        endInput.min = minEndFromStart > currentMin ? minEndFromStart : currentMin;
        
        // If current end value is less than minimum, clear it
        if (endInput.value && new Date(endInput.value) < startDate) {
            endInput.value = '';
        }
    } else {
        // If start cleared, reset end min to current time
        const now = new Date();
        endInput.min = now.toISOString().slice(0, 16);
    }
});

// Set current browser time as minimum for datetime inputs (timezone-aware)
(function() {
    const startInput = document.getElementById('periodStart');
    const endInput = document.getElementById('periodEnd');
    const hasSubmissions = <?= $hasSubmissions ? 'true' : 'false' ?>;
    
    // Get current time in user's browser timezone
    const now = new Date();
    const currentMin = now.toISOString().slice(0, 16);
    
    // Set min for both inputs to current browser time
    if (!hasSubmissions) {
        startInput.min = currentMin;
    }
    endInput.min = currentMin;
    
    // Update end min when start date changes (must be at least 1 hour after start)
    if (startInput.value) {
        const startDate = new Date(startInput.value);
        startDate.setHours(startDate.getHours() + 1);
        const minEndFromStart = startDate.toISOString().slice(0, 16);
        // Use the later of: current time or (start + 1 hour)
        endInput.min = minEndFromStart > currentMin ? minEndFromStart : currentMin;
    }
})();

// Validate on form submit
document.getElementById('programForm').addEventListener('submit', function(e) {
    clearErrors();
    var errors = [];
    var hasSubmissions = <?= $hasSubmissions ? 'true' : 'false' ?>;

    // Program name is required
    var nameInput = document.getElementById('progName');
    if (!nameInput.value.trim()) {
        showFieldError(nameInput, 'Program name is required.');
        errors.push(nameInput);
    }

    // Require at least one form (only when forms are editable)
    if (!hasSubmissions) {
        var selectedForms = document.querySelectorAll('#selectedFormsList .selected-form-item');
        if (selectedForms.length === 0) {
            var formsList = document.getElementById('selectedFormsList');
            if (formsList) {
                showSectionError(formsList, 'Please select at least one form.');
                errors.push(formsList);
            }
        }
    }

    // Validate submission period dates
    var startInput = document.getElementById('periodStart');
    var endInput = document.getElementById('periodEnd');
    var now = new Date();
    
    // Check if opening date is in the past (only when editable)
    if (!hasSubmissions && startInput.value) {
        var startTime = new Date(startInput.value).getTime();
        if (startTime < now.getTime()) {
            showFieldError(startInput, 'Submission opening date cannot be in the past. Please select a current or future date.');
            errors.push(startInput);
        }
    }
    
    // Check if closing date is in the past
    if (endInput.value) {
        var endTime = new Date(endInput.value).getTime();
        if (endTime < now.getTime()) {
            showFieldError(endInput, 'Submission closure date cannot be in the past. Please select a current or future date.');
            errors.push(endInput);
        }
    }
    
    // Validate closure is at least 1 hour after start
    if (startInput.value && endInput.value) {
        var startTime = new Date(startInput.value).getTime();
        var endTime = new Date(endInput.value).getTime();
        var oneHourMs = 60 * 60 * 1000;
        if (endTime < (startTime + oneHourMs)) {
            showFieldError(endInput, 'Submission closure date must be at least 1 hour after the opening date.');
            errors.push(endInput);
        }
    }

    // Require at least one review stage (only when stages are editable)
    if (!hasSubmissions) {
        var stageCards = document.querySelectorAll('#stagesContainer .stage-card');
        if (stageCards.length === 0) {
            var stagesContainer = document.getElementById('stagesContainer');
            showSectionError(stagesContainer, 'Please add at least one review stage before saving.');
            errors.push(stagesContainer);
        }
    }

    if (errors.length > 0) {
        e.preventDefault();
        errors[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (errors[0].focus) errors[0].focus();
        return false;
    }
});

// ========================================================================
// Form Sorting (drag-and-drop reorder + add/remove)
// ========================================================================

<?php if (!$hasSubmissions && !empty($allForms)): ?>
(function() {
    const selectedList = document.getElementById('selectedFormsList');
    const availableList = document.getElementById('availableFormsList');
    if (!selectedList) return;

    // Initialize SortableJS on selected forms
    new Sortable(selectedList, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'bg-purple-50',
        dragClass: 'shadow-lg',
        filter: '#noFormsSelected',
    });

    function updateEmptyStates() {
        const hasSelected = selectedList.querySelector('.selected-form-item');
        const placeholder = document.getElementById('noFormsSelected');
        if (!hasSelected && !placeholder) {
            selectedList.insertAdjacentHTML('beforeend',
                '<div id="noFormsSelected" class="p-4 text-center text-sm text-gray-400">No forms selected. Choose from the list below.</div>'
            );
        } else if (hasSelected && placeholder) {
            placeholder.remove();
        }

        const hasAvailable = availableList.querySelector('.available-form-item:not(.hidden)');
        const noAvail = document.getElementById('noFormsAvailable');
        if (noAvail) {
            noAvail.classList.toggle('hidden', !!hasAvailable);
        }
    }

    // Expose addForm globally
    window.addForm = function(el) {
        // Clear any validation error on the forms list
        selectedList.classList.remove('border-red-400');
        var formErr = selectedList.parentNode.querySelector('.field-error');
        if (formErr) formErr.remove();

        const formId = el.dataset.formId;
        const formName = el.dataset.formName;
        const formStatus = el.dataset.formStatus;
        const statusBadge = formStatus === 'published'
            ? '<span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">✓ Published</span>'
            : '<span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">' + formStatus.charAt(0).toUpperCase() + formStatus.slice(1) + '</span>';

        const html = `<div class="selected-form-item flex items-center p-3 border-b border-gray-100 last:border-0 group" data-form-id="${formId}">
            <input type="hidden" name="form_ids[]" value="${formId}">
            <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500 cursor-grab active:cursor-grabbing flex-shrink-0 drag-handle" fill="currentColor" viewBox="0 0 20 20">
                <path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
            </svg>
            <span class="ml-3 text-sm font-medium text-gray-900 flex-1">${formName}</span>
            ${statusBadge}
            <button type="button" onclick="removeForm(this)" class="ml-3 text-gray-300 hover:text-red-500 transition flex-shrink-0" title="Remove">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>`;

        selectedList.insertAdjacentHTML('beforeend', html);
        el.classList.add('hidden');
        updateEmptyStates();
    };

    // Expose removeForm globally
    window.removeForm = function(btn) {
        const item = btn.closest('.selected-form-item');
        const formId = item.dataset.formId;
        item.remove();

        // Show the form back in available list
        const avail = availableList.querySelector('.available-form-item[data-form-id="' + formId + '"]');
        if (avail) {
            avail.classList.remove('hidden');
        }
        updateEmptyStates();
    };
})();
<?php endif; ?>
</script>
