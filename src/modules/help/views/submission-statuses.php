<!-- Help Topic: What do submission statuses mean? -->
<div class="mb-4">
    <a href="/help" class="text-primary-600 hover:text-primary-700 text-sm font-medium inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" /></svg>
        <?= t('help.back_to_help') ?>
    </a>
</div>

<div class="mb-6">
    <h1 class="page-title"><?= t('help.topics.submission_statuses') ?></h1>
</div>

<div class="space-y-4">
    <div class="card p-6">
        <p class="body-text mb-4"><?= t('help.statuses_intro') ?></p>
        
        <div class="space-y-3">
            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                <span class="badge badge-gray mt-0.5 flex-shrink-0"><?= t('help.status_draft') ?></span>
                <p class="body-text"><?= t('help.status_draft_desc') ?></p>
            </div>
            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                <span class="badge badge-blue mt-0.5 flex-shrink-0"><?= t('help.status_submitted') ?></span>
                <p class="body-text"><?= t('help.status_submitted_desc') ?></p>
            </div>
            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                <span class="badge badge-blue mt-0.5 flex-shrink-0"><?= t('help.status_in_review') ?></span>
                <p class="body-text"><?= t('help.status_in_review_desc') ?></p>
            </div>
            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                <span class="badge badge-green mt-0.5 flex-shrink-0"><?= t('help.status_approved') ?></span>
                <p class="body-text"><?= t('help.status_approved_desc') ?></p>
            </div>
            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                <span class="badge badge-red mt-0.5 flex-shrink-0"><?= t('help.status_rejected') ?></span>
                <p class="body-text"><?= t('help.status_rejected_desc') ?></p>
            </div>
            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                <span class="badge badge-yellow mt-0.5 flex-shrink-0"><?= t('help.status_clarification') ?></span>
                <p class="body-text"><?= t('help.status_clarification_desc') ?></p>
            </div>
        </div>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <h3 class="eyebrow mb-3"><?= t('help.related_topics') ?></h3>
        <ul class="space-y-2">
            <li><a href="/help/view-submissions" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.view_submissions') ?></a></li>
            <li><a href="/help/what-is-clarification" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.what_is_clarification') ?></a></li>
        </ul>
    </div>
</div>
