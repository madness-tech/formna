<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;

require_once __DIR__ . '/../../src/core/verification.php';

final class VerificationTest extends DatabaseTestCase
{
    #[Test]
    public function generate_verification_token_returns_64_character_hex_string(): void
    {
        $token = generate_verification_token();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    #[Test]
    public function create_verification_token_hashes_token_and_json_encodes_payload(): void
    {
        $token = create_verification_token(5, 'email_change', 'user@example.com', ['new_email' => 'next@example.com']);

        $insertQueries = $this->queriesMatching('INSERT INTO verification_tokens');
        $this->assertCount(1, $insertQueries);
        $this->assertNotSame($token, $insertQueries[0]['params'][1]);
        $this->assertSame(hash('sha256', $token), $insertQueries[0]['params'][1]);
        $this->assertSame('{"new_email":"next@example.com"}', $insertQueries[0]['params'][4]);
    }

    #[Test]
    public function validate_verification_token_returns_data_decodes_payload_and_consumes_token(): void
    {
        $rawToken = generate_verification_token();
        $hashedToken = hash('sha256', $rawToken);

        $this->pdo->setQueryHandler(static function (string $sql, array $params) use ($hashedToken): ?array {
            if (str_contains($sql, 'FROM verification_tokens') && str_contains($sql, 'WHERE token = ?')) {
                return ['rows' => [[
                    'id' => 19,
                    'user_id' => 5,
                    'type' => 'email_change',
                    'email' => 'user@example.com',
                    'payload' => '{"new_email":"next@example.com"}',
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                ]]];
            }

            return null;
        });

        $result = validate_verification_token($rawToken);

        $this->assertSame('email_change', $result['type']);
        $this->assertSame(['new_email' => 'next@example.com'], $result['payload']);
        $deleteQueries = $this->queriesMatching('DELETE FROM verification_tokens WHERE id = ?');
        $this->assertCount(1, $deleteQueries);
        $this->assertSame([0 => 19], $deleteQueries[0]['params']);
    }

    #[Test]
    public function validate_verification_token_returns_null_for_unknown_token(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM verification_tokens')
            ? ['rows' => []]
            : null);

        $this->assertNull(validate_verification_token(generate_verification_token()));
    }

    #[Test]
    public function peek_verification_token_checks_validity_without_consuming_the_token(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT COUNT(*) as count')
            ? ['rows' => [['count' => 1]]]
            : null);

        $this->assertTrue(peek_verification_token(generate_verification_token()));
        $this->assertCount(0, $this->queriesMatching('DELETE FROM verification_tokens'));
    }

    #[Test]
    public function verify_token_exists_checks_user_and_type_scope(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM verification_tokens')
            ? ['rows' => [['count' => 1]]]
            : null);

        $this->assertTrue(verify_token_exists(7, 'password_reset'));
    }

    #[Test]
    public function resend_verification_stops_when_rate_limited(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM email_resend_log')
            ? ['rows' => [['count' => 3]]]
            : null);

        $this->assertFalse(resend_verification(9));
        $this->assertCount(0, $this->queriesMatching('INSERT INTO `job_queue`'));
    }

    #[Test]
    public function resend_verification_logs_attempt_and_queues_email_job(): void
    {
        $this->pdo->setQueryHandler(static function (string $sql, array $params): ?array {
            if (str_contains($sql, 'FROM email_resend_log')) {
                return ['rows' => [['count' => 0]]];
            }

            if (str_contains($sql, 'SELECT name, email FROM users WHERE id = ?')) {
                return ['rows' => [['name' => 'Test User', 'email' => 'user@example.com']]];
            }

            if (str_contains($sql, 'SELECT * FROM email_templates')) {
                return ['rows' => [[
                    'trigger' => 'registration_verification',
                    'subject' => 'Verify {user_name}',
                    'body' => 'Visit {verification_link}',
                    'is_active' => 1,
                ]]];
            }

            return null;
        });

        $this->assertTrue(resend_verification(4));

        $logInsert = $this->queriesMatching('INSERT INTO email_resend_log');
        $this->assertCount(1, $logInsert);

        $jobInsert = $this->queriesMatching('INSERT INTO `job_queue`');
        $this->assertCount(1, $jobInsert);
        $payload = json_decode($jobInsert[0]['params']['payload'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('user@example.com', $payload['to']);
        $this->assertStringContainsString('/verify-email?token=', $payload['body']);
    }

    #[Test]
    public function cleanup_expired_tokens_returns_deleted_row_count(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'DELETE FROM verification_tokens WHERE expires_at < UTC_TIMESTAMP()')
            ? ['rows' => [], 'rowCount' => 3]
            : null);

        $this->assertSame(3, cleanup_expired_tokens());
    }
}