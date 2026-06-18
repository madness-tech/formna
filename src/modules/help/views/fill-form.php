<!-- Help Topic: How do I fill out a form? -->
<div class="mb-4">
    <a href="/help" class="text-primary-600 hover:text-primary-700 text-sm font-medium inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" /></svg>
        <?= t('help.back_to_help') ?>
    </a>
</div>

<div class="mb-6">
    <h1 class="page-title"><?= t('help.topics.fill_form') ?></h1>
</div>

<div class="space-y-4">
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.fill_form_start_heading') ?></h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
            <li><?= t('help.fill_form_start_step1') ?></li>
            <li><?= t('help.fill_form_start_step2') ?></li>
            <li><?= t('help.fill_form_start_step3') ?></li>
        </ol>
    </div>

    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.fill_form_types_heading') ?></h2>
        <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.fill_form_type_text') ?></strong> — <?= t('help.fill_form_type_text_desc') ?></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.fill_form_type_select') ?></strong> — <?= t('help.fill_form_type_select_desc') ?></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.fill_form_type_file') ?></strong> — <?= t('help.fill_form_type_file_desc') ?></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.fill_form_type_date') ?></strong> — <?= t('help.fill_form_type_date_desc') ?></span>
            </li>
        </ul>
    </div>

    <div class="alert alert-info">
        <div class="flex gap-3">
            <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
            <p class="text-sm text-blue-800"><?= t('help.fill_form_required_tip') ?></p>
        </div>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <h3 class="eyebrow mb-3"><?= t('help.related_topics') ?></h3>
        <ul class="space-y-2">
            <li><a href="/help/save-draft" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.save_draft') ?></a></li>
            <li><a href="/help/upload-files" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.upload_files') ?></a></li>
            <li><a href="/help/submit-form" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.submit_form') ?></a></li>
        </ul>
    </div>
</div>
