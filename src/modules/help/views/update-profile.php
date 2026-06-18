<!-- Help Topic: How do I update my profile? -->
<div class="mb-4">
    <a href="/help" class="text-primary-600 hover:text-primary-700 text-sm font-medium inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" /></svg>
        <?= t('help.back_to_help') ?>
    </a>
</div>

<div class="mb-6">
    <h1 class="page-title"><?= t('help.topics.update_profile') ?></h1>
</div>

<div class="space-y-4">
    <div class="card p-6">
        <p class="body-text mb-4"><?= t('help.update_profile_intro') ?></p>
        
        <h2 class="section-title mb-3"><?= t('help.changing_your_name') ?></h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
            <li><?= t('help.update_name_step1') ?></li>
            <li><?= t('help.update_name_step2') ?></li>
            <li><?= t('help.update_name_step3') ?></li>
        </ol>
    </div>

    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.what_you_can_manage') ?></h2>
        <p class="body-text mb-3"><?= t('help.settings_page_contains') ?></p>
        <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.your_name_label') ?></strong> — <?= t('help.your_name_desc') ?></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.your_email_label') ?></strong> — <?= t('help.your_email_desc') ?></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.your_timezone_label') ?></strong> — <?= t('help.your_timezone_desc') ?></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-primary-600 font-bold mt-0.5">•</span>
                <span><strong><?= t('help.your_password_label') ?></strong> — <?= t('help.your_password_desc') ?></span>
            </li>
        </ul>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <h3 class="eyebrow mb-3"><?= t('help.related_topics') ?></h3>
        <ul class="space-y-2">
            <li><a href="/help/change-email" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.change_email') ?></a></li>
            <li><a href="/help/change-password" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.change_password') ?></a></li>
            <li><a href="/help/set-timezone" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.set_timezone') ?></a></li>
            <li><a href="/help/change-language" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.change_language') ?></a></li>
        </ul>
    </div>
</div>
