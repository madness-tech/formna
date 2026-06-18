<!-- Help Topic: How do I view my submissions? -->
<div class="mb-4">
    <a href="/help" class="text-primary-600 hover:text-primary-700 text-sm font-medium inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" /></svg>
        <?= t('help.back_to_help') ?>
    </a>
</div>

<div class="mb-6">
    <h1 class="page-title"><?= t('help.topics.view_submissions') ?></h1>
</div>

<div class="space-y-4">
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.view_subs_heading') ?></h2>
        <p class="body-text mb-3"><?= t('help.view_subs_intro') ?></p>
        <p class="body-text"><?= t('help.view_subs_columns') ?></p>
    </div>

    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.view_subs_filter_heading') ?></h2>
        <p class="body-text mb-3"><?= t('help.view_subs_filter_desc') ?></p>
        <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.filter_all') ?></strong> — <?= t('help.filter_all_desc') ?></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.filter_draft') ?></strong> — <?= t('help.filter_draft_desc') ?></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.filter_submitted') ?></strong> — <?= t('help.filter_submitted_desc') ?></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.filter_clarification') ?></strong> — <?= t('help.filter_clarification_desc') ?></span>
            </li>
        </ul>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <h3 class="eyebrow mb-3"><?= t('help.related_topics') ?></h3>
        <ul class="space-y-2">
            <li><a href="/help/submission-statuses" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.submission_statuses') ?></a></li>
            <li><a href="/help/edit-submission" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.edit_submission') ?></a></li>
        </ul>
    </div>
</div>
