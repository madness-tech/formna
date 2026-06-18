<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

require_once __DIR__ . '/../../src/core/middleware.php';

final class MiddlewareTest extends DatabaseTestCase
{
    #[Test]
    #[DataProvider('editableProvider')]
    public function is_submission_editable_applies_window_rules(array $submission, array $form, bool $expected): void
    {
        $this->assertSame($expected, is_submission_editable($submission, $form));
    }

    public static function editableProvider(): array
    {
        $recent = gmdate('Y-m-d H:i:s', time() - 3600);
        $old = gmdate('Y-m-d H:i:s', time() - (5 * 86400));

        return [
            'editing disabled' => [
                ['submitted_at' => $recent],
                ['settings' => ['is_editable_after_submit' => false]],
                false,
            ],
            'within editable days window' => [
                ['submitted_at' => $recent],
                ['settings' => ['is_editable_after_submit' => true, 'editable_days' => 2]],
                true,
            ],
            'expired editable days window' => [
                ['submitted_at' => $old],
                ['settings' => ['is_editable_after_submit' => true, 'editable_days' => 2]],
                false,
            ],
            'explicit until date expired' => [
                ['submitted_at' => $recent],
                ['settings' => [
                    'is_editable_after_submit' => true,
                    'editable_until_date' => gmdate('Y-m-d H:i:s', time() - 60),
                ]],
                false,
            ],
        ];
    }

    #[Test]
    public function check_prerequisites_returns_true_when_no_rules_are_configured(): void
    {
        $this->assertTrue(check_prerequisites(12, ['settings' => []]));
    }

    #[Test]
    public function check_form_deadline_returns_true_when_deadline_is_missing(): void
    {
        $this->assertTrue(check_form_deadline(['settings' => []]));
    }
}