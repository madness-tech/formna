<!-- Help Topic: What are the types of clarification requests? -->
<div class="mb-4">
    <a href="/help" class="text-primary-600 hover:text-primary-700 text-sm font-medium inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" /></svg>
        <?= t('help.back_to_help') ?>
    </a>
</div>

<div class="mb-6">
    <h1 class="page-title"><?= t('help.topics.clarification_types') ?></h1>
</div>

<div class="space-y-4">
    <div class="card p-6">
        <p class="body-text mb-4"><?= t('help.clar_types_intro') ?></p>
        
        <div class="space-y-4">
            <div>
                <h2 class="section-title mb-2"><?= t('help.clar_type_info_heading') ?></h2>
                <p class="body-text mb-3"><?= t('help.clar_type_info_desc') ?></p>
                <ul class="space-y-1 text-sm text-gray-600">
                    <li class="flex items-start gap-2"><span class="text-primary-600 font-bold mt-0.5">•</span> <span><?= t('help.clar_type_info_ex1') ?></span></li>
                    <li class="flex items-start gap-2"><span class="text-primary-600 font-bold mt-0.5">•</span> <span><?= t('help.clar_type_info_ex2') ?></span></li>
                </ul>
            </div>

            <div>
                <h2 class="section-title mb-2"><?= t('help.clar_type_doc_heading') ?></h2>
                <p class="body-text mb-3"><?= t('help.clar_type_doc_desc') ?></p>
                <ul class="space-y-1 text-sm text-gray-600">
                    <li class="flex items-start gap-2"><span class="text-primary-600 font-bold mt-0.5">•</span> <span><?= t('help.clar_type_doc_ex1') ?></span></li>
                    <li class="flex items-start gap-2"><span class="text-primary-600 font-bold mt-0.5">•</span> <span><?= t('help.clar_type_doc_ex2') ?></span></li>
                </ul>
            </div>

            <div>
                <h2 class="section-title mb-2"><?= t('help.clar_type_correction_heading') ?></h2>
                <p class="body-text mb-3"><?= t('help.clar_type_correction_desc') ?></p>
                <ul class="space-y-1 text-sm text-gray-600">
                    <li class="flex items-start gap-2"><span class="text-primary-600 font-bold mt-0.5">•</span> <span><?= t('help.clar_type_correction_ex1') ?></span></li>
                    <li class="flex items-start gap-2"><span class="text-primary-600 font-bold mt-0.5">•</span> <span><?= t('help.clar_type_correction_ex2') ?></span></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <h3 class="eyebrow mb-3"><?= t('help.related_topics') ?></h3>
        <ul class="space-y-2">
            <li><a href="/help/what-is-clarification" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.what_is_clarification') ?></a></li>
            <li><a href="/help/respond-to-clarification" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.respond_to_clarification') ?></a></li>
        </ul>
    </div>
</div>
