<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/modules/forms/scoring.php';

final class ScoringTest extends TestCase
{
    private static function question(int $id, string $type, array $scoring = []): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'config' => $scoring === [] ? [] : ['scoring' => $scoring],
        ];
    }

    public static function displayOnlyTypesProvider(): array
    {
        return [
            ['heading'],
            ['paragraph'],
            ['divider'],
        ];
    }

    #[Test]
    #[DataProvider('displayOnlyTypesProvider')]
    public function display_only_question_types_do_not_score(string $type): void
    {
        $question = self::question(1, $type, ['non_empty' => 5]);

        $this->assertNull(calculate_question_score($question, 'value'));
        $this->assertNull(get_question_max_score($question));
    }

    #[Test]
    public function input_questions_use_empty_and_non_empty_scores(): void
    {
        $question = self::question(1, 'text', ['empty' => 1, 'non_empty' => 8]);

        $this->assertSame(1, calculate_question_score($question, ''));
        $this->assertSame(8, calculate_question_score($question, 'Hello'));
        $this->assertSame(8, get_question_max_score($question));
    }

    #[Test]
    public function select_questions_score_selected_options_and_empty_state(): void
    {
        $question = self::question(2, 'select', [
            '__empty' => 1,
            'bronze' => 3,
            'silver' => 7,
            'gold' => 10,
        ]);

        $this->assertSame(1, calculate_question_score($question, ''));
        $this->assertSame(10, calculate_question_score($question, 'gold'));
        $this->assertSame(10, get_question_max_score($question));
    }

    #[Test]
    public function multi_select_questions_sum_unique_selected_scores(): void
    {
        $question = self::question(3, 'multiselect', [
            '__empty' => 2,
            'a' => 4,
            'b' => 6,
            'c' => -3,
        ]);

        $this->assertSame(2, calculate_question_score($question, []));
        $this->assertSame(7, calculate_question_score($question, ['a', 'b', 'a', 'c']));
        $this->assertSame(10, get_question_max_score($question));
    }

    #[Test]
    public function checkbox_questions_score_checked_and_unchecked_values(): void
    {
        $question = self::question(4, 'checkbox', ['checked' => 9, 'unchecked' => 2]);

        $this->assertSame(2, calculate_question_score($question, false));
        $this->assertSame(2, calculate_question_score($question, 'false'));
        $this->assertSame(9, calculate_question_score($question, '1'));
        $this->assertSame(9, get_question_max_score($question));
    }

    #[Test]
    public function calculate_form_score_aggregates_only_scored_questions(): void
    {
        $questions = [
            self::question(1, 'text', ['empty' => 0, 'non_empty' => 5]),
            self::question(2, 'radio', ['no' => 1, 'yes' => 9]),
            self::question(3, 'paragraph'),
        ];

        $result = calculate_form_score($questions, [
            1 => 'Provided',
            2 => 'yes',
            3 => 'ignored',
        ]);

        $this->assertTrue($result['has_scoring']);
        $this->assertSame(14, $result['total']);
        $this->assertSame(14, $result['max_possible']);
        $this->assertSame(['score' => 5, 'max' => 5], $result['per_question'][1]);
        $this->assertSame(['score' => 9, 'max' => 9], $result['per_question'][2]);
        $this->assertArrayNotHasKey(3, $result['per_question']);
    }

    #[Test]
    public function calculate_program_score_aggregates_only_forms_with_scoring(): void
    {
        $result = calculate_program_score([
            10 => ['has_scoring' => true, 'total' => 12, 'max_possible' => 20],
            11 => ['has_scoring' => false, 'total' => 99, 'max_possible' => 99],
            12 => ['has_scoring' => true, 'total' => 3, 'max_possible' => 5],
        ]);

        $this->assertTrue($result['has_scoring']);
        $this->assertSame(15, $result['total']);
        $this->assertSame(25, $result['max_possible']);
        $this->assertSame(['total' => 12, 'max_possible' => 20], $result['per_form'][10]);
        $this->assertArrayNotHasKey(11, $result['per_form']);
        $this->assertSame(['total' => 3, 'max_possible' => 5], $result['per_form'][12]);
    }

    #[Test]
    public function scoring_helpers_detect_enabled_forms_and_configured_questions(): void
    {
        $this->assertTrue(is_scoring_enabled(['settings' => ['scoring_enabled' => true]]));
        $this->assertFalse(is_scoring_enabled(['settings' => ['scoring_enabled' => false]]));

        $this->assertTrue(has_scoring_config([
            self::question(1, 'text', ['non_empty' => 5]),
            self::question(2, 'text'),
        ]));

        $this->assertFalse(has_scoring_config([
            self::question(3, 'text'),
            self::question(4, 'paragraph'),
        ]));
    }
}