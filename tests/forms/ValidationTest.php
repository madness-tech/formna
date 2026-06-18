<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/modules/forms/validation.php';

final class ValidationTest extends TestCase
{
    #[Test]
    public function empty_answer_handles_scalars_and_multi_value_fields(): void
    {
        $this->assertTrue(empty_answer(null, 'text'));
        $this->assertTrue(empty_answer('', 'text'));
        $this->assertTrue(empty_answer([], 'multiselect'));
        $this->assertFalse(empty_answer('0', 'text'));
        $this->assertFalse(empty_answer(['0'], 'multiselect'));
    }

    #[Test]
    public function validate_text_applies_length_and_pattern_rules(): void
    {
        $this->assertSame(
            [1 => t('validation.min_length', ['min' => 5])],
            validate_text(1, 'abc', ['min_length' => 5])
        );

        $this->assertSame(
            [2 => t('validation.max_length', ['max' => 4])],
            validate_text(2, 'abcdef', ['max_length' => 4])
        );

        $this->assertSame(
            [3 => t('validation.invalid_format')],
            validate_text(3, 'abc123', ['pattern' => '^[A-Z]+$'])
        );

        $this->assertSame(
            [4 => t('validation.invalid_format')],
            validate_text(4, 'abc', ['pattern' => str_repeat('a', 501)])
        );

        $this->assertSame([], validate_text(5, 'ABC', ['pattern' => '^[A-Z]+$']));
    }

    #[Test]
    public function validate_number_enforces_numeric_minimum_and_maximum(): void
    {
        $this->assertSame(
            [1 => t('validation.invalid_number')],
            validate_number(1, 'abc', [])
        );

        $this->assertSame(
            [2 => t('validation.min_value', ['min' => 10])],
            validate_number(2, '9', ['min' => 10])
        );

        $this->assertSame(
            [3 => t('validation.max_value', ['max' => 20])],
            validate_number(3, '21', ['max' => 20])
        );

        $this->assertSame([], validate_number(4, '15', ['min' => 10, 'max' => 20]));
    }

    #[Test]
    public function validate_single_select_requires_valid_configuration_and_values(): void
    {
        $this->assertSame(
            [1 => 'Tier has invalid configuration.'],
            validate_single_select(1, 'gold', 'Tier', null)
        );

        $this->assertSame(
            [2 => 'Tier contains an invalid selection.'],
            validate_single_select(2, 'platinum', 'Tier', [
                ['label' => 'Gold', 'value' => 'gold'],
                ['label' => 'Silver', 'value' => 'silver'],
            ])
        );

        $this->assertSame([], validate_single_select(3, '2', 'Tier', [
            ['label' => 'One', 'value' => 1],
            ['label' => 'Two', 'value' => 2],
        ]));
    }

    #[Test]
    public function validate_multi_select_checks_allowed_values_and_count_constraints(): void
    {
        $this->assertSame(
            [1 => 'Topics contains one or more invalid selections.'],
            validate_multi_select(1, ['alpha', 'gamma'], 'Topics', [], [
                ['label' => 'Alpha', 'value' => 'alpha'],
                ['label' => 'Beta', 'value' => 'beta'],
            ])
        );

        $this->assertSame(
            [2 => t('validation.min_selections', ['min' => 2])],
            validate_multi_select(2, ['alpha'], 'Topics', ['min_selected' => 2], [
                ['label' => 'Alpha', 'value' => 'alpha'],
                ['label' => 'Beta', 'value' => 'beta'],
            ])
        );

        $this->assertSame(
            [3 => t('validation.max_selections', ['max' => 1])],
            validate_multi_select(3, ['alpha', 'beta'], 'Topics', ['max_selected' => 1], [
                ['label' => 'Alpha', 'value' => 'alpha'],
                ['label' => 'Beta', 'value' => 'beta'],
            ])
        );

        $this->assertSame([], validate_multi_select(4, ['alpha', 'beta'], 'Topics', [
            'min_selected' => 1,
            'max_selected' => 2,
        ], [
            ['label' => 'Alpha', 'value' => 'alpha'],
            ['label' => 'Beta', 'value' => 'beta'],
        ]));
    }

    #[Test]
    public function calculate_visible_questions_removes_hidden_answers_across_cascading_rules(): void
    {
        $questions = [
            [
                'id' => 1,
                'uid' => 'q1',
                'config' => ['label' => 'Gate'],
            ],
            [
                'id' => 2,
                'uid' => 'q2',
                'config' => [
                    'label' => 'Child',
                    'visibility' => ['question_uid' => 'q1', 'values' => ['yes']],
                ],
            ],
            [
                'id' => 3,
                'uid' => 'q3',
                'config' => [
                    'label' => 'Grandchild',
                    'visibility' => ['question_uid' => 'q2', 'values' => ['go']],
                ],
            ],
        ];

        $answers = [
            1 => 'no',
            2 => 'go',
            3 => 'persisted-maliciously',
        ];

        $visible = calculate_visible_questions($questions, $answers);

        $this->assertSame(['q1'], $visible);
        $this->assertSame([1 => 'no'], $answers);
    }

    #[Test]
    public function validate_question_config_rejects_invalid_definitions(): void
    {
        $invalidType = validate_question_config('unknown_type', []);
        $this->assertFalse($invalidType['valid']);
        $this->assertSame(['type' => 'Invalid question type: unknown_type'], $invalidType['errors']);

        $invalidSelect = validate_question_config('select', [
            'label' => 'Plan',
            'options' => [
                ['label' => '', 'value' => ''],
                ['label' => 'Broken', 'value' => '[bad]'],
            ],
        ]);

        $this->assertFalse($invalidSelect['valid']);
        $this->assertContains('Option at index 0 has empty label', $invalidSelect['errors']);
        $this->assertContains('Option at index 0 has empty value', $invalidSelect['errors']);
        $this->assertContains('Option at index 1 has an invalid value. Brackets are not allowed.', $invalidSelect['errors']);

        $invalidRegex = validate_question_config('text', [
            'label' => 'Code',
            'validation' => ['pattern' => '[abc'],
        ]);

        $this->assertFalse($invalidRegex['valid']);
        $this->assertContains('Pattern is not a valid regular expression.', $invalidRegex['errors']);
    }

    #[Test]
    public function normalize_question_config_sanitizes_extensions_lengths_and_unknown_keys(): void
    {
        $normalizedFile = normalize_question_config('file', [
            'label' => str_repeat('L', MAX_LABEL_LENGTH + 10),
            'description' => str_repeat('D', MAX_DESCRIPTION_LENGTH + 10),
            'required' => true,
            'validation' => [
                'max_file_size_mb' => 999,
                'allowed_extensions' => ['php', 'jpg', 'docx', 'exe'],
            ],
            'unknown_key' => 'drop-me',
        ]);

        $this->assertSame(MAX_LABEL_LENGTH, mb_strlen($normalizedFile['label']));
        $this->assertSame(MAX_DESCRIPTION_LENGTH, mb_strlen($normalizedFile['description']));
        $this->assertEquals(50.0, $normalizedFile['validation']['max_file_size_mb']);
        $this->assertSame(['jpg', 'docx'], $normalizedFile['validation']['allowed_extensions']);
        $this->assertArrayNotHasKey('unknown_key', $normalizedFile);

        $normalizedSelect = normalize_question_config('select', [
            'label' => 'Pick one',
            'required' => true,
            'options' => [
                ['label' => 'Alpha'],
                ['label' => 'Beta', 'value' => 'beta-custom'],
            ],
            'visibility' => ['question_uid' => 'parent', 'values' => ['yes']],
        ]);

        $this->assertSame('option1', $normalizedSelect['options'][0]['value']);
        $this->assertSame('beta-custom', $normalizedSelect['options'][1]['value']);
        $this->assertTrue($normalizedSelect['required']);
        $this->assertSame(['question_uid' => 'parent', 'values' => ['yes']], $normalizedSelect['visibility']);
    }

    #[Test]
    public function get_client_validation_attrs_includes_expected_html_attributes(): void
    {
        $attrs = get_client_validation_attrs([
            'type' => 'text',
            'config' => [
                'required' => true,
                'validation' => [
                    'min_length' => 2,
                    'max_length' => 20,
                    'pattern' => '^[A-Z]+$'
                ],
            ],
        ]);

        $this->assertStringContainsString('required', $attrs);
        $this->assertStringContainsString('minlength="2"', $attrs);
        $this->assertStringContainsString('maxlength="20"', $attrs);
        $this->assertStringContainsString('pattern="^[A-Z]+$"', $attrs);
        $this->assertStringContainsString('data-required="true"', $attrs);
        $this->assertStringContainsString('data-type="text"', $attrs);
    }
}