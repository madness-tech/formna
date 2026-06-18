<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HelpersI18nTest extends TestCase
{
    protected function setUp(): void
    {
        global $_i18n_locale, $_i18n_language, $_i18n_translations;

        $_SESSION = [];
        $_i18n_locale = 'en';
        $_i18n_language = [
            'code' => 'en',
            'direction' => 'ltr',
            'translations' => null,
            'help_translations' => null,
        ];
        $_i18n_translations = null;
        i18n_clear_file_cache();
    }

    #[Test]
    public function base_url_and_full_url_normalize_slashes(): void
    {
        global $config;

        $config['app']['base_url'] = 'https://example.test/base/';

        $this->assertSame('https://example.test/base/forms', base_url('/forms'));
        $this->assertSame('https://example.test/base/forms', full_url('forms'));
    }

    #[Test]
    public function csrf_field_uses_session_token(): void
    {
        $_SESSION['_csrf_token'] = 'csrf-token-value';

        $this->assertSame('csrf-token-value', csrf_token());
        $this->assertSame(
            '<input type="hidden" name="_csrf" value="csrf-token-value">',
            csrf_field()
        );
    }

    #[Test]
    public function detect_text_direction_handles_ltr_and_rtl_text(): void
    {
        $this->assertSame('ltr', detect_text_direction('<p>Hello world</p>'));
        $this->assertSame('rtl', detect_text_direction('&lt;strong&gt;مرحبا&lt;/strong&gt;'));
    }

    #[Test]
    public function translation_helper_loads_files_substitutes_placeholders_and_falls_back(): void
    {
        $this->assertSame('Sign in', t('auth.sign_in'));
        $this->assertSame(
            'Too many attempts. Please wait 5 minutes.',
            t('auth.action_rate_limited', ['minutes' => 5, 'label' => 'minutes'])
        );
        $this->assertSame('missing.translation.key', t('missing.translation.key'));
    }

    #[Test]
    public function i18n_flatten_skips_meta_and_flattens_nested_keys(): void
    {
        $flat = i18n_flatten([
            '_meta' => ['version' => '1.0'],
            'nav' => ['dashboard' => 'Dashboard'],
            'auth' => ['sign_in' => 'Sign in'],
        ]);

        $this->assertSame([
            'nav.dashboard' => 'Dashboard',
            'auth.sign_in' => 'Sign in',
        ], $flat);
    }

    #[Test]
    public function i18n_file_helpers_expose_known_bundled_content(): void
    {
        $ui = i18n_load_ui_file_translations('en');
        $all = i18n_load_file_translations('en');

        $this->assertSame('Sign in', $ui['auth.sign_in']);
        $this->assertArrayHasKey('auth.sign_in', $all);
        $this->assertTrue(i18n_help_exists('en'));
        $this->assertFalse(i18n_help_exists('zz-no-such-lang'));
    }

    #[Test]
    public function i18n_clear_file_cache_resets_cached_state(): void
    {
        global $_i18n_translations, $_i18n_file_cache, $_i18n_ui_file_cache;

        $_i18n_translations = ['cached' => 'value'];
        $_i18n_file_cache = ['en' => ['cached' => 'value']];
        $_i18n_ui_file_cache = ['en' => ['cached' => 'value']];

        i18n_clear_file_cache();

        $this->assertNull($_i18n_translations);
        $this->assertSame([], $_i18n_file_cache);
        $this->assertSame([], $_i18n_ui_file_cache);
    }
}