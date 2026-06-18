<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class AuthTest extends DatabaseTestCase
{
    #[Test]
    public function get_client_ip_ignores_forwarded_headers_from_untrusted_sources(): void
    {
        $GLOBALS['config']['app']['trusted_proxy_ips'] = ['127.0.0.1'];
        $_SERVER['REMOTE_ADDR'] = '8.8.4.4';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8';

        $this->assertSame('8.8.4.4', get_client_ip());
    }

    #[Test]
    public function get_client_ip_accepts_first_public_forwarded_ip_from_trusted_proxy(): void
    {
        $GLOBALS['config']['app']['trusted_proxy_ips'] = ['127.0.0.1'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8, 10.0.0.1';

        $this->assertSame('8.8.8.8', get_client_ip());
    }

    #[Test]
    public function check_login_rate_limit_returns_null_when_no_lock_exists(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM rate_limits')
            ? ['rows' => []]
            : null);

        $this->assertNull(check_login_rate_limit('8.8.8.8', 'user@example.com'));
    }

    #[Test]
    public function check_login_rate_limit_reports_remaining_time_for_active_lock(): void
    {
        $lockedUntil = gmdate('Y-m-d H:i:s', time() + 61);
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM rate_limits')
            ? ['rows' => [['identifier_type' => 'email', 'attempts' => 5, 'locked_until' => $lockedUntil]]]
            : null);

        $remaining = max(1, (int) ceil((strtotime($lockedUntil) - strtotime(now())) / 60));
        $label = $remaining === 1 ? 'minute' : 'minutes';

        $this->assertSame(
            "Too many failed login attempts. Please try again in {$remaining} {$label}.",
            check_login_rate_limit('8.8.8.8', 'USER@example.com')
        );
    }

    #[Test]
    public function record_failed_login_inserts_rows_for_both_ip_and_email_on_first_failure(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT id, attempts, locked_until FROM rate_limits')
            ? ['rows' => []]
            : null);

        record_failed_login('8.8.8.8', 'USER@example.com');

        $insertQueries = $this->queriesMatching('INSERT INTO `rate_limits`');
        $this->assertCount(2, $insertQueries);
        $this->assertSame('8.8.8.8', $insertQueries[0]['params']['identifier_value']);
        $this->assertSame('user@example.com', $insertQueries[1]['params']['identifier_value']);
    }

    #[Test]
    public function record_failed_login_updates_existing_rows_and_sets_lock_at_threshold(): void
    {
        $this->pdo->setQueryHandler(static function (string $sql, array $params): ?array {
            if (str_contains($sql, 'SELECT id, attempts, locked_until FROM rate_limits')) {
                return ['rows' => [[
                    'id' => $params['value'] === '8.8.8.8' ? 10 : 11,
                    'attempts' => 4,
                    'locked_until' => null,
                ]]];
            }

            return null;
        });

        record_failed_login('8.8.8.8', 'USER@example.com');

        $updates = $this->queriesMatching('UPDATE rate_limits');
        $this->assertCount(2, $updates);
        $this->assertSame(5, $updates[0]['params']['attempts']);
        $this->assertNotNull($updates[0]['params']['locked_until']);
        $this->assertSame(5, $updates[1]['params']['attempts']);
        $this->assertNotNull($updates[1]['params']['locked_until']);
    }

    #[Test]
    public function clear_login_attempts_deletes_both_ip_and_normalized_email_keys(): void
    {
        clear_login_attempts('8.8.8.8', 'USER@example.com');

        $deleteQueries = $this->queriesMatching('DELETE FROM rate_limits');
        $this->assertCount(1, $deleteQueries);
        $this->assertSame('8.8.8.8', $deleteQueries[0]['params']['ip']);
        $this->assertSame('user@example.com', $deleteQueries[0]['params']['email']);
    }

    #[Test]
    public function check_user_action_rate_limit_uses_uid_prefix_and_records_when_requested(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT COUNT(*) AS cnt FROM rate_limits')
            ? ['rows' => [['cnt' => 0]]]
            : null);

        $this->assertNull(check_user_action_rate_limit(42, 'pwd_chg', 3, 15, true));

        $insertQueries = $this->queriesMatching('INSERT INTO `rate_limits`');
        $this->assertCount(1, $insertQueries);
        $this->assertSame('uid:42', $insertQueries[0]['params']['identifier_value']);
        $this->assertSame('pwd_chg', $insertQueries[0]['params']['identifier_type']);
    }

    #[Test]
    public function check_user_action_rate_limit_returns_translated_message_at_threshold(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT COUNT(*) AS cnt FROM rate_limits')
            ? ['rows' => [['cnt' => 3]]]
            : null);

        $this->assertSame(
            t('auth.action_rate_limited', ['minutes' => 15, 'label' => t('time.minutes')]),
            check_user_action_rate_limit(7, 'email_chg', 3, 15)
        );
    }

    #[Test]
    public function record_user_action_attempt_stores_uid_prefixed_identifier(): void
    {
        record_user_action_attempt(9, 'email_chg');

        $insertQueries = $this->queriesMatching('INSERT INTO `rate_limits`');
        $this->assertCount(1, $insertQueries);
        $this->assertSame('uid:9', $insertQueries[0]['params']['identifier_value']);
    }

    #[Test]
    #[DataProvider('safeRedirectProvider')]
    public function is_safe_redirect_validates_paths_correctly(string $path, bool $expected): void
    {
        $this->assertSame($expected, is_safe_redirect($path));
    }

    public static function safeRedirectProvider(): array
    {
        return [
            ['/dashboard', true],
            ['/forms/123?tab=edit', true],
            ['', false],
            ['https://evil.example', false],
            ['//evil.example', false],
            ["/foo\nbar", false],
        ];
    }
}