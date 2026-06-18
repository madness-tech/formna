<!-- Help Center Landing Page -->

<!-- Header -->
<div class="mb-6">
    <h1 class="page-title"><?= t('help.help_center') ?></h1>
    <p class="body-text mt-1"><?= t('help.help_center_subtitle') ?></p>
</div>

<!-- Help Topics Grid (2 Columns) -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <!-- Account & Settings -->
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="icon-box bg-indigo-100">
                <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <h2 class="section-title"><?= t('help.categories.account_settings') ?></h2>
        </div>
        <ul class="space-y-2">
            <li><a href="/help/update-profile" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.update_profile') ?></a></li>
            <li><a href="/help/change-email" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.change_email') ?></a></li>
            <li><a href="/help/change-password" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.change_password') ?></a></li>
            <li><a href="/help/set-timezone" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.set_timezone') ?></a></li>
            <li><a href="/help/change-language" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.change_language') ?></a></li>
            <li><a href="/help/mfa-setup" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.mfa_setup') ?></a></li>
        </ul>
    </div>

    <!-- Forms & Submissions -->
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="icon-box bg-blue-100">
                <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h2 class="section-title"><?= t('help.categories.forms_submissions') ?></h2>
        </div>
        <ul class="space-y-2">
            <li><a href="/help/find-forms" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.find_forms') ?></a></li>
            <li><a href="/help/fill-form" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.fill_form') ?></a></li>
            <li><a href="/help/save-draft" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.save_draft') ?></a></li>
            <li><a href="/help/upload-files" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.upload_files') ?></a></li>
            <li><a href="/help/submit-form" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.submit_form') ?></a></li>
            <li><a href="/help/edit-submission" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.edit_submission') ?></a></li>
        </ul>
    </div>

    <!-- Programs -->
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="icon-box bg-purple-100">
                <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
            </div>
            <h2 class="section-title"><?= t('help.categories.programs') ?></h2>
        </div>
        <ul class="space-y-2">
            <li><a href="/help/what-are-programs" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.what_are_programs') ?></a></li>
            <li><a href="/help/apply-to-program" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.apply_to_program') ?></a></li>
            <li><a href="/help/track-application" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.track_application') ?></a></li>
        </ul>
    </div>

    <!-- Managing Submissions -->
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="icon-box bg-green-100">
                <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
            </div>
            <h2 class="section-title"><?= t('help.categories.managing_submissions') ?></h2>
        </div>
        <ul class="space-y-2">
            <li><a href="/help/view-submissions" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.view_submissions') ?></a></li>
            <li><a href="/help/submission-statuses" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.submission_statuses') ?></a></li>
        </ul>
    </div>

    <!-- Clarification Requests -->
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="icon-box bg-amber-100">
                <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="section-title"><?= t('help.categories.clarification_requests') ?></h2>
        </div>
        <ul class="space-y-2">
            <li><a href="/help/what-is-clarification" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.what_is_clarification') ?></a></li>
            <li><a href="/help/respond-to-clarification" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.respond_to_clarification') ?></a></li>
            <li><a href="/help/clarification-types" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.clarification_types') ?></a></li>
        </ul>
    </div>

    <!-- Notifications -->
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="icon-box bg-red-100">
                <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </div>
            <h2 class="section-title"><?= t('help.categories.notifications') ?></h2>
        </div>
        <ul class="space-y-2">
            <li><a href="/help/view-notifications" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.view_notifications') ?></a></li>
            <li><a href="/help/email-not-received" class="text-sm text-primary-600 hover:text-primary-700 hover:underline"><?= t('help.topics.email_not_received') ?></a></li>
        </ul>
    </div>

</div>
