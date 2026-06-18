<?php
/**
 * Program Review Submission (Admin)
 *
 * Shows a summary of the program submission with compact form cards
 * that link to the full detail view for each form submission.
 * The reviewer makes their decision here without needing to scroll
 * through all form data inline.
 */

// Scoring support
require_once __DIR__ . '/../../forms/scoring.php';

// Ensure required variables are defined (set by controller)
if (!isset($ps) || !isset($currentStage)) {
    throw new Exception('Required variables not provided by controller');
}

// Calculate scores for each form submission
$program_form_scores = [];
if (!empty($ps['form_submissions'])) {
    foreach ($ps['form_submissions'] as $formId => $data) {
        $form_data = $data['form'] ?? null;
        if ($form_data) {
            decode_json_fields($form_data, ['settings']);
            if (is_scoring_enabled($form_data)) {
                $program_form_scores[$formId] = calculate_form_score($data['questions'], $data['submission']['answers'] ?? []);
            }
        }
    }
}
$program_score = calculate_program_score($program_form_scores);
?>

<!-- Program Review Submission (Admin) -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-900">Review Submission</h1>
            <?php
            $header_status_config = [
                'submitted'  => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800'],
                'in_review'  => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800'],
                'approved'   => ['bg' => 'bg-green-100', 'text' => 'text-green-800'],
                'rejected'   => ['bg' => 'bg-red-100', 'text' => 'text-red-800'],
            ];
            $hs = $header_status_config[$ps['status']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800'];
            ?>
            <span class="badge <?= $hs['bg'] ?> <?= $hs['text'] ?>">
                <?= ucfirst(str_replace('_', ' ', $ps['status'])) ?>
            </span>
        </div>
        <p class="body-text mt-2"><?= sanitize($ps['program']['name']) ?></p>
    </div>

    <?php if (is_ai_enabled()): ?>
    <!-- AI Summary Panel -->
    <div id="ai-summary-panel" class="mb-6">
        <div class="card overflow-hidden">
            <!-- Header (always visible, toggles collapse) -->
            <button type="button" id="ai-summary-toggle"
                    class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-3">
                    <span class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                        </svg>
                    </span>
                    <span class="text-sm font-semibold text-gray-900">AI Summary</span>
                    <span id="ai-summary-badge" class="hidden px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-700">Cached</span>
                </div>
                <svg id="ai-summary-chevron" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <!-- Collapsible body -->
            <div id="ai-summary-body" class="hidden border-t border-gray-200">
                <!-- Loading state -->
                <div id="ai-summary-loading" class="hidden px-6 py-8 text-center">
                    <svg class="animate-spin h-6 w-6 text-purple-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-sm text-gray-500">Generating summary&hellip; This may take a moment.</p>
                </div>

                <!-- Empty state (no summary yet) -->
                <div id="ai-summary-empty" class="hidden px-6 py-8 text-center">
                    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                    </svg>
                    <p class="text-sm text-gray-500 mb-4">No AI summary generated yet for this submission.</p>
                    <button type="button" id="ai-summary-generate-btn" class="btn btn-primary inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                        </svg>
                        Generate Summary
                    </button>
                </div>

                <!-- Summary content -->
                <div id="ai-summary-content" class="hidden">
                    <div class="px-6 py-5">
                        <div id="ai-summary-text" class="prose-ai"></div>
                    </div>
                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-400">
                            <span id="ai-summary-meta-by"></span>
                            <span id="ai-summary-meta-date"></span>
                            <span id="ai-summary-meta-model"></span>
                        </div>
                        <button type="button" id="ai-summary-regenerate-btn" class="inline-flex items-center gap-1.5 text-xs font-medium text-purple-600 hover:text-purple-800">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                            </svg>
                            Regenerate
                        </button>
                    </div>
                    <div class="px-6 py-2 bg-amber-50 border-t border-amber-100">
                        <p class="text-xs text-amber-600">AI-generated summaries may contain inaccuracies. Always verify against the original submission data.</p>
                    </div>
                </div>

                <!-- Error state -->
                <div id="ai-summary-error" class="hidden px-6 py-6 text-center">
                    <p id="ai-summary-error-text" class="text-sm text-red-600 mb-3"></p>
                    <button type="button" id="ai-summary-retry-btn" class="text-sm font-medium text-primary-600 hover:text-primary-800">Try Again</button>
                </div>
            </div>
        </div>
    </div>

    <script type="module">
        import { getCsrfToken, api, toast } from '<?= asset('/js/common.js') ?>';

        const PS_UUID = <?= json_encode($ps['uuid']) ?>;

        // DOM refs
        const panel      = document.getElementById('ai-summary-panel');
        const toggle     = document.getElementById('ai-summary-toggle');
        const chevron    = document.getElementById('ai-summary-chevron');
        const body       = document.getElementById('ai-summary-body');
        const badge      = document.getElementById('ai-summary-badge');
        const loading    = document.getElementById('ai-summary-loading');
        const empty      = document.getElementById('ai-summary-empty');
        const content    = document.getElementById('ai-summary-content');
        const errorEl    = document.getElementById('ai-summary-error');
        const errorText  = document.getElementById('ai-summary-error-text');
        const summaryTxt = document.getElementById('ai-summary-text');
        const metaBy     = document.getElementById('ai-summary-meta-by');
        const metaDate   = document.getElementById('ai-summary-meta-date');
        const metaModel  = document.getElementById('ai-summary-meta-model');
        const genBtn     = document.getElementById('ai-summary-generate-btn');
        const regenBtn   = document.getElementById('ai-summary-regenerate-btn');
        const retryBtn   = document.getElementById('ai-summary-retry-btn');

        let isOpen = false;
        let hasSummary = false;
        let isGenerating = false;

        function showState(state) {
            loading.classList.add('hidden');
            empty.classList.add('hidden');
            content.classList.add('hidden');
            errorEl.classList.add('hidden');
            if (state === 'loading') loading.classList.remove('hidden');
            else if (state === 'empty') empty.classList.remove('hidden');
            else if (state === 'content') content.classList.remove('hidden');
            else if (state === 'error') errorEl.classList.remove('hidden');
        }

        function setOpen(open) {
            isOpen = open;
            body.classList.toggle('hidden', !open);
            chevron.style.transform = open ? 'rotate(180deg)' : '';
        }

        function renderSummary(data) {
            summaryTxt.innerHTML = data.summary_html || '';
            metaBy.textContent = data.generated_by ? `Generated by ${data.generated_by}` : '';
            metaDate.textContent = data.created_at || '';
            metaModel.textContent = data.model ? `Model: ${data.model}` : '';
            badge.classList.remove('hidden');
            hasSummary = true;
            showState('content');
        }

        // Collapse toggle
        toggle.addEventListener('click', () => setOpen(!isOpen));

        // Fetch cached summary on load
        async function fetchExisting() {
            try {
                const res = await api(`/api/admin/ai/summary/program_submission/${PS_UUID}`);
                if (res.success && res.summary) {
                    renderSummary(res);
                    setOpen(true);
                } else {
                    showState('empty');
                    setOpen(true);
                }
            } catch {
                showState('empty');
                setOpen(true);
            }
        }

        // Generate summary
        async function generate(regenerate = false) {
            if (isGenerating) return;
            isGenerating = true;
            badge.classList.add('hidden');
            showState('loading');
            setOpen(true);
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

            try {
                const body = {
                    entity_type: 'program_submission',
                    entity_uuid: PS_UUID,
                };
                if (regenerate) body.regenerate = true;

                const res = await api('/api/admin/ai/summarize', 'POST', body);

                if (res.success && res.summary) {
                    renderSummary(res);
                    toast('success', 'Summary generated successfully');
                } else {
                    errorText.textContent = res.error || 'Failed to generate summary.';
                    showState('error');
                }
            } catch (err) {
                errorText.textContent = err.message || 'Network error. Please try again.';
                showState('error');
            } finally {
                isGenerating = false;
            }
        }

        genBtn.addEventListener('click', () => generate(false));
        regenBtn.addEventListener('click', () => generate(true));
        retryBtn.addEventListener('click', () => generate(false));

        // Init
        fetchExisting();
    </script>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">

            <?php if ($program_score['has_scoring']): ?>
            <!-- Program Score Summary -->
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-lg shadow-sm border border-amber-200 p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-semibold text-gray-900">Program Total Score</h3>
                            <p class="text-2xl font-bold text-amber-700"><?= $program_score['total'] ?> <span class="text-sm font-normal text-gray-500">/ <?= $program_score['max_possible'] ?></span></p>
                        </div>
                    </div>
                    <?php if ($program_score['max_possible'] > 0): ?>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Percentage</p>
                        <p class="text-lg font-bold text-amber-700"><?= round(($program_score['total'] / $program_score['max_possible']) * 100) ?>%</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form Submission Cards -->
            <?php if (!empty($ps['form_submissions'])): ?>
                <?php foreach ($ps['form_submissions'] as $formId => $data): ?>
                    <?php
                    $form_score_data = $program_form_scores[$formId] ?? null;
                    $form_uuid = $data['form']['uuid'] ?? null;
                    $sub_uuid = $data['submission']['uuid'] ?? null;

                    // Count answered questions (exclude display-only types)
                    $answered_count = 0;
                    $total_questions = 0;
                    foreach ($data['questions'] as $q) {
                        if (in_array($q['type'], ['heading', 'paragraph', 'divider'])) {
                            continue;
                        }
                        $total_questions++;
                        $ans = $data['submission']['answers'][$q['id']] ?? null;
                        if ($ans !== null && $ans !== '' && (!is_array($ans) || !empty($ans))) {
                            $answered_count++;
                        }
                    }

                    // Build the detail link with back-navigation context
                    $detail_url = $form_uuid && $sub_uuid
                        ? '/admin/forms/' . urlencode($form_uuid) . '/submissions/' . urlencode($sub_uuid) . '?from=review&ps=' . urlencode($ps['uuid'])
                        : null;
                    ?>
                    <div class="stat-card">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <h2 class="section-title">
                                        <?= sanitize($data['form']['name'] ?? 'Form #' . $formId) ?>
                                    </h2>
                                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <?= format_datetime($data['submission']['submitted_at'], 'short') ?>
                                        </span>
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                            </svg>
                                            <?= $answered_count ?> / <?= $total_questions ?> answered
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 ml-4">
                                    <?php if ($form_score_data && $form_score_data['has_scoring']): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-amber-100 text-amber-800">
                                        <?= $form_score_data['total'] ?> / <?= $form_score_data['max_possible'] ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($detail_url): ?>
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <a href="<?= $detail_url ?>" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-700">
                                    View Full Submission
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"></path>
                                    </svg>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card p-8 text-center">
                    <p class="text-sm text-gray-500">No form submissions attached.</p>
                </div>
            <?php endif; ?>

            <!-- Decision Form / Completed Notice -->
            <?php if (in_array($ps['status'], ['approved', 'rejected'])): ?>
                <?php
                // Find the final decision for the completed banner
                $final_decision = !empty($ps['decisions']) ? end($ps['decisions']) : null;
                ?>
                <!-- Submission already reviewed -->
                <div class="card p-6">
                    <div class="flex items-start <?= $ps['status'] === 'approved' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?> border rounded-lg p-4">
                        <?php if ($ps['status'] === 'approved'): ?>
                            <svg class="w-5 h-5 text-green-600 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-green-900">Submission Approved</h3>
                                <?php if ($final_decision): ?>
                                    <p class="mt-1 text-sm text-green-700">
                                        Decided by <span class="font-medium"><?= sanitize($final_decision['reviewer_name'] ?? 'Unknown') ?></span>
                                        on <?= format_datetime($final_decision['decided_at'], 'short') ?>
                                    </p>
                                    <?php if (!empty($final_decision['justification'])): ?>
                                        <p class="mt-2 text-sm text-green-800 italic">"<?= sanitize($final_decision['justification']) ?>"</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="mt-1 text-sm text-green-700">No further action is needed.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <svg class="w-5 h-5 text-red-600 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-red-900">Submission Rejected</h3>
                                <?php if ($final_decision): ?>
                                    <p class="mt-1 text-sm text-red-700">
                                        Decided by <span class="font-medium"><?= sanitize($final_decision['reviewer_name'] ?? 'Unknown') ?></span>
                                        on <?= format_datetime($final_decision['decided_at'], 'short') ?>
                                    </p>
                                    <?php if (!empty($final_decision['justification'])): ?>
                                        <p class="mt-2 text-sm text-red-800 italic">"<?= sanitize($final_decision['justification']) ?>"</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="mt-1 text-sm text-red-700">No further action is needed.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <a href="/admin/review-queue" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Back to Review Queue
                        </a>
                    </div>
                </div>
            <?php elseif (!empty($can_decide)): ?>
                <div class="card p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Your Decision</h2>

                    <form method="POST" action="/admin/review-queue/<?= $ps['uuid'] ?>/decide">
                        <?= csrf_field() ?>

                        <div class="space-y-4">
                            <?php if ($currentStage['action'] === 'score'): ?>
                                <!-- Score-based Review -->
                                <div>
                                    <label for="score">
                                        Score (0–100) *
                                    </label>
                                    <input type="number" name="score" id="score" min="0" max="100" required>
                                    <?php if (!empty($currentStage['pass_threshold'])): ?>
                                        <p class="help-text">
                                            Pass threshold: <span class="font-medium"><?= $currentStage['pass_threshold'] ?></span>
                                            · Scores at or above this threshold will pass
                                        </p>
                                    <?php else: ?>
                                        <p class="help-text">Enter a score between 0 and 100</p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Approve/Reject Decision -->
                                <div>
                                    <label>Decision *</label>
                                    <div class="space-y-2">
                                        <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                            <input type="radio" name="decision" value="approved" required class="text-primary-600 focus:ring-primary-500">
                                            <span class="ml-3 text-sm font-medium text-gray-900">Approve</span>
                                        </label>
                                        <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                            <input type="radio" name="decision" value="rejected" required class="text-primary-600 focus:ring-primary-500">
                                            <span class="ml-3 text-sm font-medium text-gray-900">Reject</span>
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Justification -->
                            <div>
                                <label for="justification">
                                    Justification
                                    <span class="help-text">(Internal only — never shown to applicants)</span>
                                </label>
                                <textarea name="justification" id="justification" rows="4"
                                          placeholder="Explain your decision..."></textarea>
                            </div>

                            <!-- Submit -->
                            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                                <a href="/admin/review-queue" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    Submit Decision
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Admin view-only notice (not the assigned reviewer) -->
                <div class="card p-6">
                    <div class="flex items-center bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <h3 class="text-sm font-semibold text-blue-900">Pending Review</h3>
                            <p class="mt-1 text-sm text-blue-700">This submission is awaiting review from the assigned reviewer for stage <?= (int)$ps['current_stage'] ?>.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <a href="/admin/review-queue" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Back to Review Queue
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Sidebar -->
        <div class="space-y-6">

            <!-- User Info -->
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">User Information</h3>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-gray-600">Name:</span>
                        <div class="font-medium text-gray-900">
                            <a href="/admin/users/<?= (int)$ps['user']['id'] ?>/edit" class="text-primary-600 hover:text-primary-800"><?= sanitize($ps['user']['name']) ?></a>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-600">Email:</span>
                        <div class="font-medium text-gray-900"><?= sanitize($ps['user']['email']) ?></div>
                    </div>
                    <div>
                        <span class="text-gray-600">Submitted:</span>
                        <div class="font-medium text-gray-900"><?= $ps['submitted_at'] ? format_datetime($ps['submitted_at'], 'short') : '—' ?></div>
                    </div>
                </div>
            </div>

            <!-- Review Progress -->
            <?php if (!empty($ps['program']['review_stages'])): ?>
                <?php
                // Build stage outcome lookup from decisions
                $stage_outcomes = [];
                foreach ($ps['decisions'] as $d) {
                    $stage_outcomes[(int)$d['stage']] = $d;
                }
                ?>
                <div class="card p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Review Progress</h3>
                    <div class="space-y-3">
                        <?php foreach ($ps['program']['review_stages'] as $stage): ?>
                            <?php $outcome = $stage_outcomes[(int)$stage['order']] ?? null; ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <?php if ($stage['order'] < $ps['current_stage'] || ($outcome && in_array($ps['status'], ['approved', 'rejected']))): ?>
                                        <?php if ($outcome && $outcome['decision'] === 'rejected'): ?>
                                            <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($stage['order'] == $ps['current_stage']): ?>
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center">
                                            <div class="w-2 h-2 rounded-full bg-primary-600"></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center">
                                            <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-3 flex-1">
                                    <div class="text-sm font-medium text-gray-900"><?= sanitize($stage['name']) ?></div>
                                    <?php if (isset($stage['reviewer'])): ?>
                                        <div class="help-text"><?= sanitize($stage['reviewer']['name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($outcome): ?>
                                        <div class="text-xs mt-0.5 <?= $outcome['decision'] === 'approved' ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= ucfirst($outcome['decision']) ?><?= isset($outcome['score']) ? ' · Score: ' . $outcome['score'] : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Attached Forms Summary -->
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Attached Forms</h3>
                <div class="space-y-2">
                    <?php foreach ($ps['program']['forms'] as $form): ?>
                        <?php $isAttached = isset($ps['submission_ids'][(string)$form['id']]); ?>
                        <div class="flex items-center text-sm">
                            <?php if ($isAttached): ?>
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-4 h-4 text-gray-300 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                            <?php endif; ?>
                            <span class="<?= $isAttached ? 'text-gray-900' : 'text-gray-400' ?>"><?= sanitize($form['name']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Previous Decisions -->
            <?php if (!empty($ps['decisions'])): ?>
                <?php
                // Build a map of stage numbers to stage names
                $stage_name_map = [];
                foreach ($ps['program']['review_stages'] as $s) {
                    $stage_name_map[(int)$s['order']] = $s['name'];
                }
                ?>
                <div class="card p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Decision History</h3>
                    <div class="space-y-3">
                        <?php foreach ($ps['decisions'] as $decision): ?>
                            <div class="pb-3 border-b border-gray-200 last:border-0">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-medium text-gray-500"><?= sanitize($stage_name_map[(int)$decision['stage']] ?? 'Stage ' . $decision['stage']) ?></span>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded <?= $decision['decision'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= ucfirst($decision['decision']) ?>
                                    </span>
                                </div>
                                <div class="help-text">
                                    by <?= sanitize($decision['reviewer_name'] ?? 'Unknown') ?>
                                </div>
                                <?php if (isset($decision['score'])): ?>
                                    <div class="text-sm text-gray-700">Score: <?= $decision['score'] ?></div>
                                <?php endif; ?>
                                <?php if (!empty($decision['justification'])): ?>
                                    <div class="body-text mt-1"><?= sanitize($decision['justification']) ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-400 mt-1"><?= format_datetime($decision['decided_at'], 'short') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>
