<!-- Help Topic: How do I change my email address? -->
<div class="mb-4">
    <a href="/help" class="text-primary-600 hover:text-primary-700 text-sm font-medium inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" /></svg>
        <?= t('help.back_to_help') ?>
    </a>
</div>

<div class="mb-6">
    <h1 class="page-title"><?= t('help.topics.change_email') ?></h1>
</div>

<div class="space-y-4">
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.steps') ?></h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
            <li><?= t('help.change_email_step1') ?></li>
            <li><?= t('help.change_email_step2') ?></li>
            <li><?= t('help.change_email_step3') ?></li>
            <li><?= t('help.change_email_step4') ?></li>
            <li><?= t('help.change_email_step5') ?></li>
        </ol>
    </div>

    <div class="alert alert-warning">
        <div class="flex gap-3">
            <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
            <div>
                <p class="font-medium text-yellow-900"><?= t('help.good_to_know') ?></p>
                <ul class="mt-1 space-y-1 text-sm text-yellow-800">
                    <li>• <?= t('help.change_email_note1') ?></li>
                    <li>• <?= t('help.change_email_note2') ?></li>
                    <li>• <?= t('help.change_email_note3') ?></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <h3 class="eyebrow mb-3"><?= t('help.related_topics') ?></h3>
        <ul class="space-y-2">
            <li><a href="/help/update-profile" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.update_profile') ?></a></li>
            <li><a href="/help/change-password" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.change_password') ?></a></li>
        </ul>
    </div>
</div>
