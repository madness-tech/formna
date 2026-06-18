<!-- Help Topic: How do I set my timezone? -->
<div class="mb-4">
    <a href="/help" class="text-primary-600 hover:text-primary-700 text-sm font-medium inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" /></svg>
        <?= t('help.back_to_help') ?>
    </a>
</div>

<div class="mb-6">
    <h1 class="page-title"><?= t('help.topics.set_timezone') ?></h1>
</div>

<div class="space-y-4">
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.steps') ?></h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
            <li><?= t('help.set_tz_step1') ?></li>
            <li><?= t('help.set_tz_step2') ?></li>
            <li><?= t('help.set_tz_step3') ?></li>
        </ol>
    </div>

    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.set_tz_why_heading') ?></h2>
        <p class="body-text"><?= t('help.set_tz_why_desc') ?></p>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <h3 class="eyebrow mb-3"><?= t('help.related_topics') ?></h3>
        <ul class="space-y-2">
            <li><a href="/help/update-profile" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.update_profile') ?></a></li>
        </ul>
    </div>
</div>
