<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Lightweight PDO double for database-facing unit tests.
 *
 * The project's current test environment does not have a live MySQL database,
 * so higher-level tests can inject this fake through core/db.php's
 * $GLOBALS['_test_pdo'] override.
 */
final class TestPdo extends PDO
{
    /** @var list<array{sql:string, params:array<string|int, mixed>}> */
    public array $queries = [];

    /** @var callable(string, array<string|int, mixed>, self): (array<string, mixed>|list<array<string, mixed>>|null)|null */
    private $queryHandler = null;

    private bool $inTransaction = false;
    private int $lastInsertIdValue = 0;

    public int $beginTransactionCalls = 0;
    public int $commitCalls = 0;
    public int $rollbackCalls = 0;

    public function __construct()
    {
    }

    public function setQueryHandler(callable $handler): void
    {
        $this->queryHandler = $handler;
    }

    public function clearQueryHandler(): void
    {
        $this->queryHandler = null;
    }

    public function setInTransaction(bool $state): void
    {
        $this->inTransaction = $state;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new TestPdoStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        $this->beginTransactionCalls++;
        $this->inTransaction = true;
        return true;
    }

    public function commit(): bool
    {
        $this->commitCalls++;
        $this->inTransaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->rollbackCalls++;
        $this->inTransaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) $this->lastInsertIdValue;
    }

    public function getAttribute(int $attribute): mixed
    {
        return match ($attribute) {
            PDO::ATTR_DRIVER_NAME => 'test-double',
            PDO::ATTR_SERVER_VERSION => 'test-double',
            default => null,
        };
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array{rows?: list<array<string, mixed>>, rowCount?: int, fetchColumn?: mixed, lastInsertId?: int}|null
     */
    public function executeStatement(string $sql, array $params): ?array
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];

        $result = $this->queryHandler ? ($this->queryHandler)($sql, $params, $this) : null;

        if ($result === null) {
            $normalizedSql = strtolower(ltrim($sql));
            if (str_starts_with($normalizedSql, 'insert into')) {
                $this->lastInsertIdValue++;
                return ['rows' => [], 'rowCount' => 1, 'lastInsertId' => $this->lastInsertIdValue];
            }

            if (str_starts_with($normalizedSql, 'delete') || str_starts_with($normalizedSql, 'update')) {
                return ['rows' => [], 'rowCount' => 1];
            }

            return ['rows' => [], 'rowCount' => 0, 'fetchColumn' => 0];
        }

        if (array_is_list($result)) {
            return ['rows' => $result, 'rowCount' => count($result)];
        }

        if (isset($result['lastInsertId']) && is_int($result['lastInsertId'])) {
            $this->lastInsertIdValue = $result['lastInsertId'];
        }

        return $result;
    }
}

final class TestPdoStatement extends PDOStatement
{
    /** @var array<string|int, mixed> */
    private array $boundValues = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    private int $rowIndex = 0;
    private int $rowCountValue = 0;
    private mixed $fetchColumnValue = 0;

    public function __construct(
        private readonly TestPdo $pdo,
        public readonly string $sql,
    ) {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $key = is_string($param) ? ltrim($param, ':') : $param;
        $this->boundValues[$key] = $value;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $normalizedParams = self::normalizeParams($params ?? []);
        $finalParams = $normalizedParams === [] ? $this->boundValues : array_replace($this->boundValues, $normalizedParams);

        $result = $this->pdo->executeStatement($this->sql, $finalParams) ?? [];

        $this->rows = $result['rows'] ?? [];
        $this->rowCountValue = (int) ($result['rowCount'] ?? count($this->rows));
        $this->fetchColumnValue = $result['fetchColumn'] ?? (($this->rows[0] ?? [])[0] ?? null);
        $this->rowIndex = 0;

        return true;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0,
    ): mixed {
        return $this->rows[$this->rowIndex++] ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->rows !== []) {
            $row = $this->rows[0];
            $values = array_values($row);
            return $values[$column] ?? false;
        }

        return $this->fetchColumnValue;
    }

    public function rowCount(): int
    {
        return $this->rowCountValue;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string|int, mixed>
     */
    private static function normalizeParams(array $params): array
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            $normalized[is_string($key) ? ltrim($key, ':') : $key] = $value;
        }

        return $normalized;
    }
}

abstract class DatabaseTestCase extends TestCase
{
    protected TestPdo $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new TestPdo();
        $GLOBALS['_test_pdo'] = $this->pdo;

        $_SESSION = [];
        $_SERVER = [];
        $_POST = [];
        $_GET = [];

        unset($GLOBALS['_mfa_role_flags_cache'], $GLOBALS['_branding_cache']);

        if (!isset($GLOBALS['config'])) {
            $GLOBALS['config'] = load_config();
        }

        $GLOBALS['config']['app']['base_url'] = $GLOBALS['config']['app']['base_url'] ?? 'http://localhost:8888';
        $GLOBALS['config']['app']['trusted_proxy_ips'] = $GLOBALS['config']['app']['trusted_proxy_ips'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_pdo'] = new TestPdo();
        unset($GLOBALS['_mfa_role_flags_cache'], $GLOBALS['_branding_cache']);
        parent::tearDown();
    }

    protected function setCurrentUser(?array $user): void
    {
        unset($_SESSION['user_id'], $_SESSION['user']);

        if ($user !== null) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{id:int,uuid:string,email:string,name:string,role:string,timezone:string}
     */
    protected function makeSessionUser(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'email' => 'user@example.com',
            'name' => 'Test User',
            'role' => 'user',
            'timezone' => 'Asia/Dubai',
        ], $overrides);
    }

    /**
     * @return list<array{sql:string, params:array<string|int, mixed>}>
     */
    protected function queriesMatching(string $needle): array
    {
        return array_values(array_filter(
            $this->pdo->queries,
            static fn(array $query): bool => str_contains($query['sql'], $needle)
        ));
    }
}