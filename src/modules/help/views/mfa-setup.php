<!-- Help Topic: How do I set up two-factor authentication (2FA)? -->
<div class="mb-4">
    <a href="/help" class="text-primary-600 hover:text-primary-700 text-sm font-medium inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" /></svg>
        <?= t('help.back_to_help') ?>
    </a>
</div>

<div class="mb-6">
    <h1 class="page-title"><?= t('help.topics.mfa_setup') ?></h1>
</div>

<div class="space-y-4">

    <!-- What is Two-Factor Authentication? -->
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.mfa_what_heading') ?></h2>
        <p class="text-sm text-gray-600 mb-4"><?= t('help.mfa_what_desc') ?></p>
        <p class="text-sm font-medium text-gray-700 mb-2"><?= t('help.mfa_why_use') ?></p>
        <ul class="list-disc list-inside space-y-1 text-sm text-gray-600">
            <li><?= t('help.mfa_why1') ?></li>
            <li><?= t('help.mfa_why2') ?></li>
            <li><?= t('help.mfa_why3') ?></li>
        </ul>
    </div>

    <!-- How to Enable 2FA -->
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.mfa_enable_heading') ?></h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
            <li><?= t('help.mfa_enable_step1') ?></li>
            <li><?= t('help.mfa_enable_step2') ?></li>
            <li><?= t('help.mfa_enable_step3') ?></li>
            <li><?= t('help.mfa_enable_step4') ?></li>
        </ol>
    </div>

    <!-- Completing the Setup -->
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.mfa_setup_heading') ?></h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
            <li><?= t('help.mfa_setup_step1') ?></li>
            <li><?= t('help.mfa_setup_step2') ?></li>
            <li><?= t('help.mfa_setup_step3') ?></li>
            <li><?= t('help.mfa_setup_step4') ?></li>
            <li><?= t('help.mfa_setup_step5') ?></li>
            <li><?= t('help.mfa_setup_step6') ?></li>
        </ol>
    </div>

    <!-- Logging In With 2FA -->
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.mfa_login_heading') ?></h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 mb-4">
            <li><?= t('help.mfa_login_step1') ?></li>
            <li><?= t('help.mfa_login_step2') ?></li>
            <li><?= t('help.mfa_login_step3') ?></li>
        </ol>
        <div class="alert alert-info">
            <div class="flex gap-3">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                <p class="text-sm text-blue-800"><?= t('help.mfa_login_tip') ?></p>
            </div>
        </div>
    </div>

    <!-- Recovery Code -->
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.mfa_recovery_heading') ?></h2>
        <p class="text-sm text-gray-600 mb-4"><?= t('help.mfa_recovery_desc') ?></p>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <div class="flex gap-3">
                <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                <div>
                    <p class="font-medium text-amber-900 text-sm mb-1"><?= t('help.mfa_recovery_warning_title') ?></p>
                    <p class="text-sm text-amber-800"><?= t('help.mfa_recovery_warning_desc') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Changing Your Authenticator -->
    <div class="card p-6">
        <h2 class="section-title mb-3"><?= t('help.mfa_change_heading') ?></h2>
        <p class="text-sm text-gray-600"><?= t('help.mfa_change_desc') ?></p>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <h3 class="eyebrow mb-3"><?= t('help.related_topics') ?></h3>
        <ul class="space-y-2">
            <li><a href="/help/change-password" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.change_password') ?></a></li>
            <li><a href="/help/update-profile" class="text-sm text-primary-600 hover:underline"><?= t('help.topics.update_profile') ?></a></li>
        </ul>
    </div>

</div>
