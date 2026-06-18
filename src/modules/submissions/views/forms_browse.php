<?php
/**
 * Browse Available Forms (Paginated + Searchable)
 *
 * @var array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $result Paginated result from browse_available_forms()
 */
$title = t('forms_browse.title');
$result = $result;
$search = trim($_GET['q'] ?? '');

ob_start();
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="page-title"><?= t('forms_browse.title') ?></h1>
            <p class="body-text mt-1"><?= t('forms_browse.subtitle') ?></p>
        </div>
        <!-- Search -->
        <form method="GET" action="/forms" class="flex-shrink-0 w-full sm:w-72">
            <div class="relative">
                <svg class="pointer-events-none absolute top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" style="<?= is_rtl() ? 'right:0.75rem' : 'left:0.75rem' ?>" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                <input type="text" name="q" value="<?php echo sanitize($search); ?>" placeholder="<?= t('forms_browse.search_placeholder') ?>"
                       style="<?= is_rtl() ? 'padding-right:2.25rem;padding-left:0.75rem' : 'padding-left:2.25rem;padding-right:0.75rem' ?>">
            </div>
        </form>
    </div>
</div>

<?php if ($search && empty($result['rows'])): ?>
    <div class="card p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
        <h3 class="mt-3 text-sm font-medium text-gray-900"><?= t('forms_browse.no_search_results', ['query' => sanitize($search)]) ?></h3>
        <p class="mt-1 text-sm text-gray-500"><?= t('forms_browse.try_different_search') ?> <a href="/forms" class="text-primary-600 hover:text-primary-700 font-medium"><?= t('forms_browse.view_all_forms') ?></a>.</p>
    </div>
<?php elseif (empty($result['rows'])): ?>
    <div class="card p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
        <h3 class="mt-3 text-sm font-medium text-gray-900"><?= t('forms_browse.no_forms_available') ?></h3>
        <p class="mt-1 text-sm text-gray-500"><?= t('forms_browse.no_forms_message') ?></p>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($result['rows'] as $form): ?>
            <?php
            $settings = $form['settings'] ?? [];
            $has_deadline = !empty($settings['submission_deadline']);
            $deadline_passed = $has_deadline && is_past($settings['submission_deadline']);
            $has_limit = !empty($settings['submission_limit']) && $settings['submission_limit'] > 1;
            $prereq_status = $form['prerequisite_status'] ?? ['met' => true, 'forms' => []];
            $has_prereqs = !empty($prereq_status['forms']);
            $prereqs_met = $prereq_status['met'];
            $has_metadata = ($has_deadline && !$deadline_passed) || $has_limit;
            $show_prereqs = $has_prereqs && !$prereqs_met;
            $has_extra_content = !empty($form['description']) || $has_metadata || $show_prereqs;
            ?>
            <a href="/forms/<?php echo sanitize($form['uuid']); ?>"
               class="stat-card block hover:shadow-sm group<?php if ($show_prereqs) echo ' opacity-75'; ?>">
                <div class="px-5 py-4 flex <?php echo $has_extra_content ? 'items-start' : 'items-center'; ?> gap-4">
                    <!-- Icon -->
                    <div class="flex-shrink-0<?php echo $has_extra_content ? ' mt-0.5' : ''; ?>">
                        <?php if ($show_prereqs): ?>
                            <div class="h-10 w-10 rounded-lg bg-amber-50 flex items-center justify-center group-hover:bg-amber-100 transition">
                                <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </div>
                        <?php else: ?>
                            <div class="h-10 w-10 rounded-lg bg-primary-50 flex items-center justify-center group-hover:bg-primary-100 transition">
                                <svg class="h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900 group-hover:text-primary-600 transition"><?php echo sanitize($form['name']); ?></h3>
                            <svg class="h-5 w-5 text-gray-300 group-hover:text-primary-400 transition flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M15.75 19.5l-7.5-7.5 7.5-7.5' : 'M8.25 4.5l7.5 7.5-7.5 7.5' ?>" />
                            </svg>
                        </div>
                        <?php if (!empty($form['description'])): ?>
                            <p class="body-text mt-1 line-clamp-2"><?php echo sanitize($form['description']); ?></p>
                        <?php endif; ?>
                        <?php if ($has_metadata): ?>
                            <div class="help-text flex flex-wrap items-center gap-x-4 gap-y-1">
                                <?php if ($has_deadline && !$deadline_passed): ?>
                                    <span class="flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <?= t('forms_browse.deadline', ['date' => format_datetime($settings['submission_deadline'], 'date_short')]) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($has_limit): ?>
                                    <span class="flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.678 48.678 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 003.7 3.7 48.656 48.656 0 007.324 0 4.006 4.006 0 003.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3l-3 3" /></svg>
                                        <?= t('forms_browse.up_to_submissions', ['limit' => $settings['submission_limit']]) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_prereqs): ?>
                            <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                <div class="flex items-center gap-1.5 text-xs font-medium text-amber-700">
                                    <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                                    <?= t('forms_browse.requires') ?>
                                </div>
                                <div class="mt-1 space-y-0.5">
                                    <?php foreach ($prereq_status['forms'] as $prereq_form): ?>
                                        <div class="flex items-center gap-2 text-xs <?= $prereq_form['completed'] ? 'text-green-700' : 'text-amber-700' ?>">
                                            <?php if ($prereq_form['completed']): ?>
                                                <svg class="h-3 w-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                            <?php else: ?>
                                                <svg class="h-3 w-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" /></svg>
                                            <?php endif; ?>
                                            <?= sanitize($prereq_form['name']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        <?php
        $pagination = $result;
        $base_url = '/forms';
        $query_params = [];
        if ($search) $query_params['q'] = $search;
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
    </div>
<?php endif; ?>

<?php if (!empty($recently_closed_forms)): ?>
    <!-- Recently Closed Forms -->
    <div class="mt-10">
        <div class="flex items-center gap-2 mb-3">
            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide"><?= t('forms_browse.recently_closed_title') ?></h2>
        </div>
        <p class="body-text mb-4"><?= t('forms_browse.recently_closed_subtitle') ?></p>
        <div class="space-y-3 opacity-60">
            <?php foreach ($recently_closed_forms as $form): ?>
                <?php $settings = $form['settings'] ?? []; ?>
                <div class="stat-card block cursor-default">
                    <div class="px-5 py-4 flex items-start gap-4">
                        <!-- Icon (muted) -->
                        <div class="flex-shrink-0 mt-0.5">
                            <div class="h-10 w-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </div>
                        </div>
                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-500"><?= sanitize($form['name']) ?></h3>
                                <span class="badge bg-red-100 text-red-700 flex-shrink-0"><?= t('forms_browse.closed_badge') ?></span>
                            </div>
                            <?php if (!empty($form['description'])): ?>
                                <p class="text-sm text-gray-400 mt-1 line-clamp-2"><?= sanitize($form['description']) ?></p>
                            <?php endif; ?>
                            <div class="help-text flex items-center gap-1 mt-1">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <?= t('forms_browse.closed_on', ['date' => format_datetime($form['closed_at'], 'date_short')]) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/user.php';
