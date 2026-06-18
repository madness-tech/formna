<?php

/**
 * Get PDO database connection (singleton)
 */
function db(): PDO {
    if (($GLOBALS['_test_pdo'] ?? null) instanceof PDO) {
        return $GLOBALS['_test_pdo'];
    }

    static $pdo = null;
    
    if ($pdo === null) {
        // Load config if not already loaded
        if (!isset($GLOBALS['config'])) {
            require_once __DIR__ . '/env.php';
            $GLOBALS['config'] = load_config();
        }
        
        $config = $GLOBALS['config'];
        
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['db']['host'],
            $config['db']['port'],
            $config['db']['name']
        );
        
        try {
            $pdo = new PDO($dsn, $config['db']['user'], $config['db']['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    return $pdo;
}

/**
 * Execute query and return all rows
 *
 * @param array<string|int, mixed> $params
 * @return array<int, array<string, mixed>>
 */
function db_query(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute query and return single row or null
 *
 * @param array<string|int, mixed> $params
 * @return array<string, mixed>|null
 */
function db_one(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Execute statement and return affected row count
 *
 * @param array<string|int, mixed> $params
 */
function db_exec(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Quote a MySQL identifier (table name, column name) with backticks.
 * Escapes any existing backticks within the identifier name.
 */
function db_quote_identifier(string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

/**
 * Insert row and return new ID
 *
 * @param array<string, mixed> $data
 */
function db_insert(string $table, array $data): int {
    $columns = array_keys($data);
    $quoted_columns = array_map('db_quote_identifier', $columns);
    $placeholders = array_map(fn($col) => ':' . $col, $columns);
    
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        db_quote_identifier($table),
        implode(', ', $quoted_columns),
        implode(', ', $placeholders)
    );
    
    $stmt = db()->prepare($sql);
    foreach ($data as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    
    return (int) db()->lastInsertId();
}

/**
 * Begin a database transaction
 */
function db_begin_transaction(): bool {
    return db()->beginTransaction();
}

/**
 * Commit a database transaction
 */
function db_commit(): bool {
    return db()->commit();
}

/**
 * Rollback a database transaction
 */
function db_rollback(): bool {
    return db()->rollBack();
}

/**
 * Check if currently in a transaction
 */
function db_in_transaction(): bool {
    return db()->inTransaction();
}

/**
 * Execute a callback within a transaction
 * Automatically commits on success, rolls back on exception
 *
 * @template T
 * @param callable():T $callback
 * @return T
 */
function db_transaction(callable $callback) {
    $pdo = db();
    
    // If already in a transaction, just run the callback (nested transaction support)
    if ($pdo->inTransaction()) {
        return $callback();
    }
    
    $pdo->beginTransaction();
    
    try {
        $result = $callback();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Atomically merge keys into a JSON column using a row lock
 *
 * Performs a read-modify-write with FOR UPDATE to prevent concurrent writes
 * from overwriting each other's keys. Only the specified keys are changed;
 * all other existing keys in the JSON are preserved.
 *
 * @param string $table Table name
 * @param string $column JSON column name
 * @param array<string, mixed> $changes Key-value pairs to merge into the existing JSON
 * @param string $where WHERE clause (e.g. 'id = ?')
 * @param array<string|int, mixed> $params Parameters for the WHERE clause
 */
function patch_json_field(string $table, string $column, array $changes, string $where, array $params): bool {
    return db_transaction(function() use ($table, $column, $changes, $where, $params) {
        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s FOR UPDATE',
            db_quote_identifier($column),
            db_quote_identifier($table),
            $where
        );
        $row = db_one($sql, $params);
        if (!$row) {
            return false;
        }

        $current = json_decode($row[$column] ?? '{}', true) ?: [];
        $merged = array_merge($current, $changes);

        return db_update($table, [
            $column => json_encode($merged),
            'updated_at' => now()
        ], $where, $params) >= 0;
    });
}

/**
 * Update rows and return affected count
 *
 * @param array<string, mixed> $data
 * @param array<string|int, mixed> $where_params
 */
function db_update(string $table, array $data, string $where, array $where_params = []): int {
    $sets = array_map(
        fn($col) => db_quote_identifier($col) . ' = ?',
        array_keys($data)
    );
    
    $sql = sprintf(
        'UPDATE %s SET %s WHERE %s',
        db_quote_identifier($table),
        implode(', ', $sets),
        $where
    );
    
    $params = array_merge(array_values($data), array_values($where_params));
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
