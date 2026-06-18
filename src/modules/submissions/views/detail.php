<?php
/**
 * Admin Submission Detail View
 * 
 * Shows detailed view of a single submission for admin review.
 */

// Ensure required variables are defined to prevent PHPStan errors
if (!isset($submission) || !isset($form) || !isset($questions) || !isset($files) || !isset($submitter) || !isset($version)) {
    throw new Exception('Required variables not defined for submission detail view');
}

$clarifications = $clarifications ?? [];
$clarification_items_by_question = $clarification_items_by_question ?? [];
$pending_response_count = $pending_response_count ?? 0;
$has_active_clarification = $has_active_clarification ?? false;
$responded_clarification = $responded_clarification ?? null;
$scoring_enabled = $scoring_enabled ?? false;
$form_score = $form_score ?? null;
?>

<?php if ($pending_response_count > 0): ?>
<!-- Alert Banner for Pending Responses -->
<div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-lg">
    <div class="flex items-center">
        <svg class="w-6 h-6 text-amber-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <div>
            <h3 class="text-sm font-semibold text-amber-800">
                <?= $pending_response_count ?> Clarification Response<?= $pending_response_count > 1 ? 's' : '' ?> Pending Review
            </h3>
            <p class="text-sm text-amber-700 mt-1">
                The user has responded to your clarification request. Please review the responses below (highlighted in amber).
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Program context data (loaded in controller)
$program_contexts = $program_contexts ?? [];
$audit_trail = $audit_trail ?? [];
$has_programs = !empty($program_contexts);
$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'programs' ? 'programs' : 'submission';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="page-title">Submission Details</h1>
        <p class="text-gray-600 mt-1"><?= sanitize($form['name']) ?></p>
    </div>
    <?php if (!empty($from_review) && $from_review === 'review' && !empty($review_ps_uuid)): ?>
        <a href="/admin/review-queue/<?= sanitize($review_ps_uuid) ?>" class="btn btn-secondary">
            ← Back to Review
        </a>
    <?php else: ?>
        <a href="/admin/forms/<?= $form['uuid'] ?>/submissions" class="btn btn-secondary">
            ← Back to List
        </a>
    <?php endif; ?>
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

    const SUB_UUID = <?= json_encode($submission['uuid']) ?>;

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
            const res = await api(`/api/admin/ai/summary/submission/${SUB_UUID}`);
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
            const reqBody = {
                entity_type: 'submission',
                entity_uuid: SUB_UUID,
            };
            if (regenerate) reqBody.regenerate = true;

            const res = await api('/api/admin/ai/summarize', 'POST', reqBody);

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

<?php if ($has_programs): ?>
<!-- Tab Navigation -->
<div class="mb-6 border-b border-gray-200">
    <nav class="-mb-px flex space-x-8">
        <a href="?<?= http_build_query(array_merge($_GET, ['tab' => 'submission'])) ?>"
           class="whitespace-nowrap py-3 px-1 border-b-2 text-sm font-medium <?= $active_tab === 'submission' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
            Submission
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['tab' => 'programs'])) ?>"
           class="whitespace-nowrap py-3 px-1 border-b-2 text-sm font-medium <?= $active_tab === 'programs' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
            Programs
            <span class="badge badge-gray ml-1.5">
                <?= count($program_contexts) ?>
            </span>
        </a>
    </nav>
</div>
<?php endif; ?>

<?php if ($active_tab === 'programs' && $has_programs): ?>
<!-- Programs Tab Content -->
<div class="space-y-6">
    <?php foreach ($program_contexts as $pc): ?>
        <?php
        $ps_status_config = [
            'draft'     => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon_color' => 'text-gray-500', 'border' => 'border-gray-200'],
            'submitted' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-800', 'icon_color' => 'text-blue-600', 'border' => 'border-blue-200'],
            'in_review' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-800', 'icon_color' => 'text-amber-600', 'border' => 'border-amber-200'],
            'approved'  => ['bg' => 'bg-green-50', 'text' => 'text-green-800', 'icon_color' => 'text-green-600', 'border' => 'border-green-200'],
            'rejected'  => ['bg' => 'bg-red-50', 'text' => 'text-red-800', 'icon_color' => 'text-red-600', 'border' => 'border-red-200'],
        ];
        $ps_sc = $ps_status_config[$pc['ps_status']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon_color' => 'text-gray-500', 'border' => 'border-gray-200'];
        $total_stages = count($pc['review_stages']);
        $current_stage_num = (int)$pc['current_stage'];
        $last_decision = !empty($pc['decisions']) ? end($pc['decisions']) : null;
        $is_in_progress = in_array($pc['ps_status'], ['submitted', 'in_review']);
        $is_final = in_array($pc['ps_status'], ['approved', 'rejected']);
        ?>
        
        <div class="bg-white rounded-lg shadow-sm border-2 <?= $ps_sc['border'] ?> overflow-hidden">
            <!-- Header -->
            <div class="card-header bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full <?= $ps_sc['bg'] ?> flex items-center justify-center">
                                <?php if ($pc['ps_status'] === 'approved'): ?>
                                    <svg class="w-6 h-6 <?= $ps_sc['text'] ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                <?php elseif ($pc['ps_status'] === 'rejected'): ?>
                                    <svg class="w-6 h-6 <?= $ps_sc['text'] ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                <?php elseif ($pc['ps_status'] === 'in_review'): ?>
                                    <svg class="w-6 h-6 <?= $ps_sc['text'] ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-6 h-6 <?= $ps_sc['text'] ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 class="section-title"><?= sanitize($pc['program_name']) ?></h3>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <span class="badge <?= $ps_sc['bg'] ?> <?= $ps_sc['text'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $pc['ps_status'])) ?>
                                    </span>
                                    <?php if (!empty($pc['ps_submitted_at'])): ?>
                                        <span class="help-text">
                                            Submitted <?= format_datetime($pc['ps_submitted_at'], 'date_short') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="/admin/review-queue/<?= sanitize($pc['ps_uuid']) ?>"
                       class="btn btn-primary">
                        <?= $is_final ? 'View Review' : 'Review' ?>
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"/>
                        </svg>
                    </a>
                </div>
            </div>
            
            <!-- Content -->
            <div class="px-6 py-5">
                <?php if ($is_in_progress && $total_stages > 0): ?>
                    <!-- Review Progress -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Review Progress</span>
                            <span class="body-text">
                                <?php if (!empty($pc['review_stages'][$current_stage_num - 1]['name'])): ?>
                                    <?= sanitize($pc['review_stages'][$current_stage_num - 1]['name']) ?>
                                    <span class="text-gray-400 mx-1">•</span>
                                <?php endif; ?>
                                Stage <?= $current_stage_num ?> of <?= $total_stages ?>
                            </span>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div class="relative">
                            <div class="overflow-hidden h-2 text-xs flex rounded-full bg-gray-200">
                                <div style="width: <?= round(($current_stage_num / $total_stages) * 100) ?>%"
                                     class="btn btn-primary shadow-none flex-col text-center transition-all duration-500"></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($is_final && $last_decision): ?>
                    <!-- Final Decision -->
                    <div class="rounded-lg <?= $pc['ps_status'] === 'approved' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?> p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <?php if ($pc['ps_status'] === 'approved'): ?>
                                    <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3 flex-1">
                                <h4 class="text-sm font-semibold <?= $pc['ps_status'] === 'approved' ? 'text-green-900' : 'text-red-900' ?>">
                                    <?= $pc['ps_status'] === 'approved' ? 'Application Approved' : 'Application Rejected' ?>
                                </h4>
                                <?php if (!empty($last_decision['decided_at'])): ?>
                                    <p class="mt-1 text-sm <?= $pc['ps_status'] === 'approved' ? 'text-green-700' : 'text-red-700' ?>">
                                        Decision made on <?= format_datetime($last_decision['decided_at'], 'long') ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($last_decision['justification'])): ?>
                                    <div class="mt-2 text-sm <?= $pc['ps_status'] === 'approved' ? 'text-green-800' : 'text-red-800' ?>">
                                        <p class="font-medium">Reviewer Comments:</p>
                                        <p class="mt-1 italic">"<?= sanitize($last_decision['justification']) ?>"</p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($last_decision['score'])): ?>
                                    <div class="mt-2 inline-flex items-center px-2.5 py-1 rounded-md <?= $pc['ps_status'] === 'approved' ? 'bg-green-100' : 'bg-red-100' ?>">
                                        <svg class="w-4 h-4 mr-1.5 <?= $pc['ps_status'] === 'approved' ? 'text-green-700' : 'text-red-700' ?>" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        <span class="text-sm font-semibold <?= $pc['ps_status'] === 'approved' ? 'text-green-900' : 'text-red-900' ?>">
                                            Score: <?= sanitize($last_decision['score']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- Submission Tab Content (default) -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Submission Info -->
        <div class="card p-6">
            <h2 class="section-title mb-4">Submission Information</h2>
            
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Submitted By</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= sanitize($submitter['name']) ?></dd>
                    <dd class="help-text"><?= sanitize($submitter['email']) ?></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Submission ID</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono"><?= sanitize($submission['uuid']) ?></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1">
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
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Form Version</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= $version['version_number'] ?></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Submitted At</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        <?= format_datetime($submission['submitted_at'], 'long') ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        <?= format_datetime($submission['updated_at'], 'long') ?>
                    </dd>
                </div>
            </dl>
        </div>
        
        <?php if ($scoring_enabled && $form_score && $form_score['has_scoring']): ?>
        <!-- Score Summary -->
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-lg shadow-sm border border-amber-200 p-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-semibold text-gray-900">Total Score</h3>
                        <p class="text-2xl font-bold text-amber-700"><?= $form_score['total'] ?> <span class="text-sm font-normal text-gray-500">/ <?= $form_score['max_possible'] ?></span></p>
                    </div>
                </div>
                <?php if ($form_score['max_possible'] > 0): ?>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Percentage</p>
                    <p class="text-lg font-bold text-amber-700"><?= round(($form_score['total'] / $form_score['max_possible']) * 100) ?>%</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Answers -->
        <div class="card p-6">
            <h2 class="section-title mb-4">Responses</h2>
            
            <div class="space-y-6">
                <?php foreach ($questions as $question):
                    $config = $question['config']; // Already decoded in controller
                    $answer = $submission['answers'][$question['id']] ?? null;
                    
                    // Skip display-only questions
                    if (in_array($question['type'], ['heading', 'paragraph', 'divider'])) {
                        if ($question['type'] === 'heading') {
                            echo '<h3 class="text-xl font-bold text-gray-900 mt-6 mb-2">' . sanitize($config['label']) . '</h3>';
                        }
                        continue;
                    }
                    
                    // Determine if this question has an answer
                    $question_files = [];
                    $has_no_answer = ($answer === null || $answer === '' || (is_array($answer) && empty($answer)));
                    if ($question['type'] === 'file') {
                        $question_files = array_values(array_filter($files, fn($f) => $f['question_id'] == $question['id']));
                    }
                ?>
                
                <div class="border-l-4 border-primary-500 pl-4">
                    <p class="text-sm font-medium text-gray-700 mb-2"><?= sanitize($config['label']) ?></p>
                    
                    <?php if (!empty($config['description'])): ?>
                        <p class="help-text"><?= sanitize($config['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="text-gray-900">
                        <?php if ($has_no_answer): ?>
                            <p class="bg-gray-50 p-3 rounded"><em class="text-gray-400">No answer provided</em></p>
                        <?php elseif ($question['type'] === 'textarea'): ?>
                            <p class="whitespace-pre-wrap bg-gray-50 p-3 rounded" dir="<?= detect_text_direction($answer) ?>"><?= sanitize($answer) ?></p>
                            
                        <?php elseif ($question['type'] === 'checkbox_group' || $question['type'] === 'multiselect'): ?>
                            <?php if (is_array($answer)): ?>
                                <ul class="list-disc list-inside bg-gray-50 p-3 rounded">
                                    <?php
                                    // Map values to labels
                                    foreach ($answer as $val):
                                        $display_value = $val;
                                        // Find the label for this value
                                        if (isset($config['options']) && is_array($config['options'])) {
                                            foreach ($config['options'] as $option) {
                                                if (isset($option['value']) && $option['value'] === $val) {
                                                    $display_value = $option['label'];
                                                    break;
                                                }
                                            }
                                        }
                                    ?>
                                        <li><?= sanitize($display_value) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                        <?php elseif ($question['type'] === 'checkbox'): ?>
                            <p class="bg-gray-50 p-3 rounded"><?= $answer ? '✓ Yes' : '✗ No' ?></p>
                            
                        <?php elseif ($question['type'] === 'file'): ?>
                            <ul class="space-y-2 bg-gray-50 p-3 rounded">
                                <?php
                                $previewable_mimes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
                                foreach ($question_files as $file):
                                    $can_preview = in_array($file['mime_type'], $previewable_mimes, true);
                                ?>
                                    <li class="flex items-center text-sm">
                                        <svg class="w-5 h-5 text-gray-400 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        <?php if ($can_preview): ?>
                                        <button type="button"
                                                class="text-primary-600 hover:underline text-left"
                                                data-preview-file
                                                data-file-id="<?= (int)$file['id'] ?>"
                                                data-file-name="<?= sanitize($file['original_name']) ?>"
                                                data-file-mime="<?= sanitize($file['mime_type']) ?>">
                                            <?= sanitize($file['original_name']) ?>
                                        </button>
                                        <?php else: ?>
                                        <a href="/files/<?= (int)$file['id'] ?>/download" class="text-primary-600 hover:underline">
                                            <?= sanitize($file['original_name']) ?>
                                        </a>
                                        <?php endif; ?>
                                        <span class="text-gray-500 ml-2">(<?= number_format($file['size_bytes'] / 1024, 1) ?> KB)</span>
                                        <?php if ($can_preview): ?>
                                        <a href="/files/<?= (int)$file['id'] ?>/download" class="ml-2 text-gray-400 hover:text-gray-600" title="Download">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                        </a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            
                        <?php elseif ($question['type'] === 'date'): ?>
                            <p class="bg-gray-50 p-3 rounded"><?= format_datetime($answer, 'date') ?></p>

                        <?php elseif ($question['type'] === 'datetime'): ?>
                            <p class="bg-gray-50 p-3 rounded"><?= format_datetime($answer, 'long') ?></p>
                            
                        <?php elseif (in_array($question['type'], ['select', 'radio'])): ?>
                            <?php
                            // Map value to label for select and radio
                            $display_value = $answer;
                            if (isset($config['options']) && is_array($config['options'])) {
                                foreach ($config['options'] as $option) {
                                    if (isset($option['value']) && $option['value'] === $answer) {
                                        $display_value = $option['label'];
                                        break;
                                    }
                                }
                            }
                            ?>
                            <p class="bg-gray-50 p-3 rounded"><?= sanitize($display_value) ?></p>
                            
                        <?php else: ?>
                            <p class="bg-gray-50 p-3 rounded" dir="<?= detect_text_direction((string)$answer) ?>"><?= sanitize($answer) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($scoring_enabled && $form_score && isset($form_score['per_question'][$question['id']])): ?>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Score: <?= $form_score['per_question'][$question['id']]['score'] ?><?php if ($form_score['per_question'][$question['id']]['max'] !== null): ?> / <?= $form_score['per_question'][$question['id']]['max'] ?><?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Display clarification requests and responses for this question
                    $question_clarifications = $clarification_items_by_question[$question['id']] ?? [];
                    if (!empty($question_clarifications)):
                    ?>
                    <div class="mt-3 space-y-2">
                        <?php foreach ($question_clarifications as $citem): 
                            $is_pending_response = ($citem['clarification_status'] === 'responded' && $citem['status'] === 'responded');
                            $is_resolved = ($citem['clarification_status'] === 'resolved');
                        ?>
                        <div class="text-sm <?= $is_pending_response ? 'bg-amber-50 border-l-4 border-amber-400' : ($is_resolved ? 'bg-gray-50 border-l-4 border-gray-300' : 'bg-blue-50 border-l-4 border-blue-400') ?> p-3 rounded-r">
                            <!-- Admin's Request -->
                            <div class="text-gray-600 mb-2">
                                <span class="font-medium">Request:</span> <?= sanitize($citem['guidance']) ?>
                            </div>
                            
                            <!-- For resolved rectifications, show what the original value was (the new value is already shown as the field answer above) -->
                            <?php if ($is_resolved && $citem['type'] === 'rectification'): ?>
                            <div class="text-gray-800">
                                <span class="font-medium">Was:</span>
                                <?php
                                $orig = $citem['original_value'] ?? null;
                                if ($orig === null || $orig === ''): ?>
                                    <em class="text-gray-400">No answer provided</em>
                                <?php elseif (is_array($orig)): ?>
                                    <?php
                                    $opt_map = [];
                                    foreach ($config['options'] ?? [] as $opt) {
                                        $opt_map[$opt['value']] = $opt['label'];
                                    }
                                    echo sanitize(implode(', ', array_map(fn($v) => $opt_map[$v] ?? $v, $orig)));
                                    ?>
                                <?php elseif (!empty($config['options'])): ?>
                                    <?php
                                    $opt_map = [];
                                    foreach ($config['options'] as $opt) {
                                        $opt_map[$opt['value']] = $opt['label'];
                                    }
                                    echo sanitize($opt_map[$orig] ?? $orig);
                                    ?>
                                <?php elseif ($question['type'] === 'datetime'): ?>
                                    <?= format_datetime($orig, 'long') ?>
                                <?php elseif ($question['type'] === 'date'): ?>
                                    <?= format_datetime($orig, 'date') ?>
                                <?php else: ?>
                                    <span dir="<?= detect_text_direction((string)$orig) ?>"><?= sanitize($orig) ?></span>
                                <?php endif; ?>
                                <?php if ($citem['responded_at']): ?>
                                <span class="help-text">(<?= format_datetime($citem['responded_at'], 'M j') ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php elseif (!empty($citem['response'])): ?>
                            <div class="<?= $is_pending_response ? 'text-amber-900 font-medium' : 'text-gray-800' ?>">
                                <span class="font-medium"><?= $is_pending_response ? (is_rtl() ? '← Response:' : '→ Response:') : 'Response:' ?></span>
                                <?php if (is_array($citem['response'])): ?>
                                    <?php
                                    // Map array values to labels for option-based fields
                                    $opt_map = [];
                                    foreach ($config['options'] ?? [] as $opt) {
                                        $opt_map[$opt['value']] = $opt['label'];
                                    }
                                    echo sanitize(implode(', ', array_map(fn($rv) => $opt_map[$rv] ?? $rv, $citem['response'])));
                                    ?>
                                <?php elseif ($citem['type'] === 'rectification' && !empty($config['options'])): ?>
                                    <?php
                                    // Map single value to label for select/radio rectifications
                                    $opt_map = [];
                                    foreach ($config['options'] as $opt) {
                                        $opt_map[$opt['value']] = $opt['label'];
                                    }
                                    echo sanitize($opt_map[$citem['response']] ?? $citem['response']);
                                    ?>
                                <?php elseif ($question['type'] === 'datetime'): ?>
                                    <?= format_datetime($citem['response'], 'long') ?>
                                <?php elseif ($question['type'] === 'date'): ?>
                                    <?= format_datetime($citem['response'], 'date') ?>
                                <?php else: ?>
                                    <span dir="<?= detect_text_direction((string)$citem['response']) ?>"><?= sanitize($citem['response']) ?></span>
                                <?php endif; ?>
                                <?php if ($citem['responded_at']): ?>
                                <span class="help-text">(<?= format_datetime($citem['responded_at'], 'M j') ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-gray-500 italic">Awaiting response...</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Actions -->
        <div class="card p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Actions</h3>
            
            <div class="space-y-3">
                <?php if ($has_active_clarification): ?>
                    <span class="block w-full px-4 py-2 bg-gray-300 text-gray-500 rounded-lg text-sm font-medium text-center cursor-not-allowed" title="A clarification request is already pending">
                        Request Clarification
                    </span>
                <?php else: ?>
                    <a href="/admin/submissions/<?= $submission['uuid'] ?>/clarify<?= (!empty($from_review) && $from_review === 'review' && !empty($review_ps_uuid)) ? '?ps=' . urlencode($review_ps_uuid) : '' ?>" class="btn btn-primary btn-full text-center">
                        Request Clarification
                    </a>
                <?php endif; ?>
                
                <?php if ($responded_clarification): ?>
                    <div class="pt-3 border-t border-gray-200">
                        <div class="flex gap-2">
                            <button onclick="resolveClarification('<?= $responded_clarification['uuid'] ?>')" class="btn flex-1 bg-green-600 hover:bg-green-700 text-white">
                                ✓ Resolve
                            </button>
                            <button onclick="openRejectModal('<?= $responded_clarification['uuid'] ?>')" class="btn btn-danger flex-1">
                                ✗ Reject
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Clarifications -->
        <?php if (!empty($clarifications)):
        ?>
        <div class="card p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Clarification Requests</h3>
            
            <div class="space-y-3">
                <?php foreach ($clarifications as $clarif_index => $clarification): ?>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <!-- Header (Always visible, clickable) -->
                        <button
                            type="button"
                            onclick="toggleClarificationDetails(<?= $clarif_index ?>)"
                            class="w-full px-3 py-2 flex items-center justify-between hover:bg-gray-50 transition-colors text-left"
                        >
                            <div class="flex items-center gap-2 flex-1 flex-wrap">
                                <span class="text-xs font-medium text-gray-900">
                                    <?= format_datetime($clarification['created_at'], 'date_short') ?>
                                </span>
                                <?php if (!empty($clarification['requester_name'])): ?>
                                    <span class="help-text">
                                        by <?= sanitize($clarification['requester_name']) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="px-2 py-0.5 rounded text-xs font-medium
                                    <?= $clarification['status'] == 'resolved' ? 'bg-green-100 text-green-800' :
                                       ($clarification['status'] == 'responded' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                    <?= ucfirst($clarification['status']) ?>
                                </span>
                                <span class="text-xs text-gray-600">
                                    <?= count($clarification['items']) ?> question(s)
                                </span>
                            </div>
                            <svg class="w-4 h-4 text-gray-500 transform transition-transform clarif-chevron-<?= $clarif_index ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <!-- Summary (Collapsed by default) -->
                        <div id="clarif-details-<?= $clarif_index ?>" class="hidden border-t border-gray-200">
                            <div class="px-3 py-3">
                                <?php if (!empty($clarification['message'])): ?>
                                    <div class="text-xs text-gray-600 mb-2">
                                        <span class="font-medium text-gray-700">Message:</span>
                                        <?= mb_strlen($clarification['message']) > 80 ? sanitize(mb_substr($clarification['message'], 0, 80)) . '...' : sanitize($clarification['message']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-xs text-gray-600 mb-3">
                                    <?php
                                    $clarif_count = count(array_filter($clarification['items'], fn($i) => $i['type'] === 'clarification'));
                                    $rectif_count = count(array_filter($clarification['items'], fn($i) => $i['type'] === 'rectification'));
                                    ?>
                                    <?php if ($clarif_count > 0): ?>
                                        <span class="text-blue-700"><?= $clarif_count ?> clarification<?= $clarif_count > 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                    <?php if ($clarif_count > 0 && $rectif_count > 0): ?>, <?php endif; ?>
                                    <?php if ($rectif_count > 0): ?>
                                        <span class="text-red-700"><?= $rectif_count ?> rectification<?= $rectif_count > 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <a href="/admin/clarifications/<?= $clarification['uuid'] ?>"
                                       class="inline-flex items-center text-xs font-medium text-primary-600 hover:text-primary-700"
                                       target="_blank">
                                        View Full Details
                                        <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                    
                                    <?php if ($clarification['status'] == 'open'): ?>
                                        <button
                                            onclick="cancelClarification('<?= $clarification['uuid'] ?>')"
                                            class="text-xs text-gray-500 hover:text-red-600 font-medium">
                                            Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activity Log -->
        <div class="card p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Activity</h3>
            
            <div class="space-y-3">
                <!-- Always show submitted event -->
                <div class="flex items-start">
                    <div class="w-2 h-2 mt-1.5 bg-green-500 rounded-full mr-3"></div>
                    <div class="flex-1 text-sm">
                        <div class="text-gray-900 font-medium">Submitted</div>
                        <div class="help-text">
                            <?= format_datetime($submission['submitted_at'], 'short') ?>
                        </div>
                    </div>
                </div>

                <!-- Audit trail events -->
                <?php
                $action_labels = [
                    'clarification_requested' => 'Clarification Requested',
                    'clarification_responded' => 'Clarification Response',
                    'clarification_resolved' => 'Clarification Resolved',
                    'clarification_rejected' => 'Response Rejected',
                    'clarification_cancelled' => 'Clarification Cancelled',
                    'submission_updated' => 'Submission Updated',
                ];
                $action_colors = [
                    'clarification_requested' => 'bg-amber-500',
                    'clarification_responded' => 'bg-blue-500',
                    'clarification_resolved' => 'bg-green-500',
                    'clarification_rejected' => 'bg-red-500',
                    'clarification_cancelled' => 'bg-gray-500',
                    'submission_updated' => 'bg-blue-500',
                ];
                foreach ($audit_trail as $event):
                    $label = $action_labels[$event['action']] ?? ucwords(str_replace('_', ' ', $event['action']));
                    $color = $action_colors[$event['action']] ?? 'bg-gray-400';
                ?>
                    <div class="flex items-start">
                        <div class="w-2 h-2 mt-1.5 <?= $color ?> rounded-full mr-3"></div>
                        <div class="flex-1 text-sm">
                            <div class="text-gray-900 font-medium"><?= sanitize($label) ?></div>
                            <div class="help-text">
                                <?= format_datetime($event['created_at'], 'short') ?>
                                <?php if (!empty($event['user_name'])): ?>
                                    · <?= sanitize($event['user_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($submission['updated_at'] !== $submission['submitted_at'] && empty($audit_trail)): ?>
                    <div class="flex items-start">
                        <div class="w-2 h-2 mt-1.5 bg-blue-500 rounded-full mr-3"></div>
                        <div class="flex-1 text-sm">
                            <div class="text-gray-900 font-medium">Updated</div>
                            <div class="help-text">
                                <?= format_datetime($submission['updated_at'], 'short') ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Metadata -->
        <div class="card">
            <h3 class="text-sm font-semibold text-gray-900 px-6 py-4 border-b border-gray-100">Metadata</h3>
            
            <dl class="divide-y divide-gray-100 text-sm">
                <?php if (isset($submission['metadata']['ip'])): ?>
                    <div class="px-6 py-3 flex justify-between items-center">
                        <dt class="text-gray-500">IP Address</dt>
                        <dd class="text-gray-900 font-mono text-xs"><?= sanitize($submission['metadata']['ip']) ?></dd>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($submission['metadata']['user_agent'])): ?>
                    <?php
                        $ua_info = parse_user_agent($submission['metadata']['user_agent']);
                        $has_parsed_data = $ua_info['browser'] || $ua_info['os'] || $ua_info['device_type'];
                    ?>
                    <?php if ($ua_info['browser']): ?>
                        <div class="px-6 py-3 flex justify-between items-center">
                            <dt class="text-gray-500">Browser</dt>
                            <dd class="text-gray-900"><?= sanitize($ua_info['browser']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($ua_info['os']): ?>
                        <div class="px-6 py-3 flex justify-between items-center">
                            <dt class="text-gray-500">OS</dt>
                            <dd class="text-gray-900"><?= sanitize($ua_info['os']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($ua_info['device_type']): ?>
                        <div class="px-6 py-3 flex justify-between items-center">
                            <dt class="text-gray-500">Device</dt>
                            <dd class="text-gray-900"><?= sanitize($ua_info['device_type']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!$has_parsed_data): ?>
                        <div class="px-6 py-3">
                            <dt class="text-gray-500 mb-1">User Agent</dt>
                            <dd class="text-gray-700 text-xs break-all font-mono"><?= sanitize($ua_info['raw']) ?></dd>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </dl>
            
            <?php if (isset($submission['metadata']['user_agent']) && !empty($has_parsed_data)): ?>
                <div class="px-6 py-3 border-t border-gray-100">
                    <button
                        type="button"
                        onclick="var el = document.getElementById('raw-ua'); el.classList.toggle('hidden'); this.querySelector('.ua-toggle-text').textContent = el.classList.contains('hidden') ? 'Show raw user agent' : 'Hide raw user agent';"
                        class="text-xs text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <span class="ua-toggle-text">Show raw user agent</span>
                    </button>
                    <div id="raw-ua" class="help-text hidden break-all font-mono leading-relaxed">
                        <?= sanitize($submission['metadata']['user_agent']) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Reject Clarification Modal -->
<div id="rejectModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" onclick="closeRejectModal()"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h3 class="section-title ml-3">Reject Clarification Response</h3>
            </div>
            <p class="body-text mb-4">
                The user's response will be sent back for revision. Please explain what needs to be improved.
            </p>
            <textarea
                id="rejectFeedback"
                rows="4"
                class="focus:ring-red-500"
                placeholder="Explain why the response is inadequate and what the user should revise..."
            ></textarea>
            <p id="rejectError" class="hidden mt-1 text-xs text-red-600">Please provide feedback before rejecting.</p>
            <div class="mt-4 flex justify-end gap-3">
                <button onclick="closeRejectModal()" class="btn btn-secondary">
                    Cancel
                </button>
                <button onclick="submitReject()" id="rejectSubmitBtn" class="btn btn-danger">
                    Reject &amp; Send Back
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let rejectClarificationUuid = null;

function resolveClarification(id) {
    if (!confirm('Mark this clarification request as resolved?')) {
        return;
    }
    
    fetch('/api/clarifications/' + id + '/resolve', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to resolve clarification'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function openRejectModal(uuid) {
    rejectClarificationUuid = uuid;
    document.getElementById('rejectFeedback').value = '';
    document.getElementById('rejectError').classList.add('hidden');
    document.getElementById('rejectModal').classList.remove('hidden');
    document.getElementById('rejectFeedback').focus();
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    rejectClarificationUuid = null;
}

function submitReject() {
    const feedback = document.getElementById('rejectFeedback').value.trim();
    if (!feedback) {
        document.getElementById('rejectError').classList.remove('hidden');
        return;
    }
    
    const btn = document.getElementById('rejectSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Rejecting...';
    
    fetch('/api/clarifications/' + rejectClarificationUuid + '/reject', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ feedback: feedback })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to reject clarification'));
            btn.disabled = false;
            btn.textContent = 'Reject & Send Back';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Reject & Send Back';
    });
}

function cancelClarification(uuid) {
    if (!confirm('Cancel this clarification request? This will permanently delete the request and any responses. The submission status will revert to "Submitted".')) {
        return;
    }
    
    fetch('/api/clarifications/' + uuid + '/cancel', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to cancel clarification'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

// Toggle clarification details
function toggleClarificationDetails(index) {
    const details = document.getElementById('clarif-details-' + index);
    const chevron = document.querySelector('.clarif-chevron-' + index);
    
    if (details.classList.contains('hidden')) {
        details.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        details.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('rejectModal').classList.contains('hidden')) {
        closeRejectModal();
    }
});
</script>

<!-- File Preview Modal -->
<script type="module">
import { openFilePreview } from '<?= asset('/js/file-preview.js') ?>';

document.querySelectorAll('[data-preview-file]').forEach(btn => {
    btn.addEventListener('click', () => {
        openFilePreview(
            parseInt(btn.dataset.fileId, 10),
            btn.dataset.fileName,
            btn.dataset.fileMime
        );
    });
});
</script>
