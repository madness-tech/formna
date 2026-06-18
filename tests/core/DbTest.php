<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;

final class DbTest extends DatabaseTestCase
{
    #[Test]
    public function db_quote_identifier_wraps_and_escapes_backticks(): void
    {
        $this->assertSame('`odd``name`', db_quote_identifier('odd`name'));
    }

    #[Test]
    public function db_transaction_commits_and_returns_callback_result(): void
    {
        $result = db_transaction(static fn(): string => 'ok');

        $this->assertSame('ok', $result);
        $this->assertSame(1, $this->pdo->beginTransactionCalls);
        $this->assertSame(1, $this->pdo->commitCalls);
        $this->assertSame(0, $this->pdo->rollbackCalls);
    }

    #[Test]
    public function db_transaction_rolls_back_when_callback_throws(): void
    {
        $this->expectException(RuntimeException::class);

        try {
            db_transaction(static function (): void {
                throw new RuntimeException('boom');
            });
        } finally {
            $this->assertSame(1, $this->pdo->beginTransactionCalls);
            $this->assertSame(0, $this->pdo->commitCalls);
            $this->assertSame(1, $this->pdo->rollbackCalls);
        }
    }

    #[Test]
    public function nested_db_transaction_reuses_existing_transaction(): void
    {
        $this->pdo->setInTransaction(true);

        $result = db_transaction(static fn(): string => 'nested');

        $this->assertSame('nested', $result);
        $this->assertSame(0, $this->pdo->beginTransactionCalls);
        $this->assertSame(0, $this->pdo->commitCalls);
    }

    #[Test]
    public function patch_json_field_merges_existing_json_and_updates_the_row(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT `settings` FROM `forms` WHERE id = ? FOR UPDATE')
            ? ['rows' => [['settings' => '{"a":1,"b":2}']]]
            : null);

        $this->assertTrue(patch_json_field('forms', 'settings', ['c' => 3], 'id = ?', [9]));

        $updateQueries = $this->queriesMatching('UPDATE `forms` SET `settings` = ?, `updated_at` = ? WHERE id = ?');
        $this->assertCount(1, $updateQueries);
        $this->assertSame('{"a":1,"b":2,"c":3}', $updateQueries[0]['params'][0]);
        $this->assertSame(9, $updateQueries[0]['params'][2]);
    }

    #[Test]
    public function patch_json_field_returns_false_when_target_row_does_not_exist(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FOR UPDATE')
            ? ['rows' => []]
            : null);

        $this->assertFalse(patch_json_field('forms', 'settings', ['c' => 3], 'id = ?', [9]));
    }
}