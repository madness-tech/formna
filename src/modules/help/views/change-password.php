<!-- Help Topic: How do I change my password? -->
<div class="mb-4">
    <a href="/help" class="text-primary-600 hover:text-primary-700 text-sm font-medium inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" /></svg>
        <?= t('help.back_to_help') ?>
    </a>
</div>

<div class="mb-6">
    <h1 class="page-title"><?= t('help.topics.change_password') ?></h1>
</div>

<div class="space-y-4">
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.steps') ?></h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
            <li><?= t('help.change_pw_step1') ?></li>
            <li><?= t('help.change_pw_step2') ?></li>
            <li><?= t('help.change_pw_step3') ?></li>
            <li><?= t('help.change_pw_step4') ?></li>
            <li><?= t('help.change_pw_step5') ?></li>
        </ol>
    </div>

    <div class="alert alert-info">
        <div class="flex gap-3">
            <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
            <div>
                <p class="font-medium text-blue-900"><?= t('help.password_tips_title') ?></p>
                <ul class="mt-1 space-y-1 text-sm text-blue-800">
                    <li>• <?= t('help.pw_tip_min_chars') ?></li>
                    <li>• <?= t('help.pw_tip_mix') ?></li>
                    <li>• <?= t('help.pw_tip_avoid_common') ?></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <h3 class="eyebrow mb-3"><?= t('help.related_topics') ?></h3>
        <ul class="space-y-2">
            <li><a href="/help/update-profile" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.update_profile') ?></a></li>
            <li><a href="/help/change-email" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.change_email') ?></a></li>
        </ul>
    </div>
</div>
