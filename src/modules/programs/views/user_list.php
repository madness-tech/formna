<?php
/**
 * Browse Available Programs (Paginated + Searchable)
 *
 * @var array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $result Paginated result from browse_available_programs()
 */
$title = t('programs_browse.title');
$result = $result;
$search = trim($_GET['q'] ?? '');

ob_start();
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="page-title"><?= t('programs_browse.title') ?></h1>
            <p class="body-text mt-1"><?= t('programs_browse.subtitle') ?></p>
        </div>
        <!-- Search -->
        <form method="GET" action="/programs" class="flex-shrink-0 w-full sm:w-72">
            <div class="relative">
                <svg class="pointer-events-none absolute top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" style="<?= is_rtl() ? 'right:0.75rem' : 'left:0.75rem' ?>" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                <input type="text" name="q" value="<?php echo sanitize($search); ?>" placeholder="<?= t('programs_browse.search_placeholder') ?>"
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
        <h3 class="mt-3 text-sm font-medium text-gray-900"><?= t('programs_browse.no_search_results', ['query' => sanitize($search)]) ?></h3>
        <p class="mt-1 text-sm text-gray-500"><?= t('programs_browse.try_different_search') ?> <a href="/programs" class="text-primary-600 hover:text-primary-700 font-medium"><?= t('common.view_all') ?></a>.</p>
    </div>
<?php elseif (empty($result['rows'])): ?>
    <div class="card p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
        </svg>
        <h3 class="mt-3 text-sm font-medium text-gray-900"><?= t('programs_browse.no_programs') ?></h3>
        <p class="mt-1 text-sm text-gray-500"><?= t('programs_browse.no_programs_message') ?></p>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($result['rows'] as $prog): ?>
            <?php
            $maxSubs = (int)($prog['settings']['max_submissions_per_user'] ?? 1);
            $canSubmit = $maxSubs === 0 || $prog['user_submission_count'] < $maxSubs;
            $settings = $prog['settings'];
            $has_metadata = true; // Always show form count at minimum
            ?>
            <a href="/programs/<?php echo sanitize($prog['uuid']); ?>"
               class="stat-card block hover:shadow-sm group">
                <div class="px-5 py-4 flex items-start gap-4">
                    <!-- Icon -->
                    <div class="flex-shrink-0 mt-0.5">
                        <div class="h-10 w-10 rounded-lg bg-purple-50 flex items-center justify-center group-hover:bg-purple-100 transition">
                            <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900 group-hover:text-primary-600 transition"><?php echo sanitize($prog['name']); ?></h3>
                            <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                                <?php if ($prog['user_submission_count'] > 0): ?>
                                    <span class="badge badge-indigo">
                                        <?php if ($prog['user_submission_count'] === 1): ?>
                                            <?= t('programs_browse.applied_once') ?>
                                        <?php else: ?>
                                            <?= t('programs_browse.applied_multiple', ['count' => $prog['user_submission_count']]) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <svg class="h-5 w-5 text-gray-300 group-hover:text-primary-400 transition" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M15.75 19.5l-7.5-7.5 7.5-7.5' : 'M8.25 4.5l7.5 7.5-7.5 7.5' ?>" />
                                </svg>
                            </div>
                        </div>
                        <?php if (!empty($prog['description'])): ?>
                            <p class="body-text mt-1 line-clamp-2"><?php echo sanitize($prog['description']); ?></p>
                        <?php endif; ?>
                        <div class="help-text flex items-center gap-4">
                            <span class="flex items-center gap-1">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                <?= t('programs_browse.required_forms', ['count' => $prog['form_count']]) ?>
                            </span>
                            <?php if (!empty($settings['submission_period_end'])): ?>
                                <span class="flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <?= t('programs_browse.deadline', ['date' => format_datetime($settings['submission_period_end'], 'date_short')]) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!$canSubmit): ?>
                                <span class="flex items-center gap-1 text-amber-600">
                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                    <?= t('programs_browse.limit_reached') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        <?php
        $pagination = $result;
        $base_url = '/programs';
        $query_params = [];
        if ($search) $query_params['q'] = $search;
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
    </div>
<?php endif; ?>

<?php if (!empty($recently_closed_programs)): ?>
    <!-- Recently Closed Programs -->
    <div class="mt-10">
        <div class="flex items-center gap-2 mb-3">
            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide"><?= t('programs_browse.recently_closed_title') ?></h2>
        </div>
        <p class="body-text mb-4"><?= t('programs_browse.recently_closed_subtitle') ?></p>
        <div class="space-y-3 opacity-60">
            <?php foreach ($recently_closed_programs as $prog): ?>
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
                                <h3 class="text-sm font-semibold text-gray-500"><?= sanitize($prog['name']) ?></h3>
                                <span class="badge bg-red-100 text-red-700 flex-shrink-0"><?= t('programs_browse.closed_badge') ?></span>
                            </div>
                            <?php if (!empty($prog['description'])): ?>
                                <p class="text-sm text-gray-400 mt-1 line-clamp-2"><?= sanitize($prog['description']) ?></p>
                            <?php endif; ?>
                            <div class="help-text flex items-center gap-1 mt-1">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <?= t('programs_browse.closed_on', ['date' => format_datetime($prog['closed_at'], 'date_short')]) ?>
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
?>
