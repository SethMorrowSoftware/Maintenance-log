<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Thin PDO wrapper.
 *
 * The one rule that matters: every table name in every SQL string is written
 * inside curly braces and expanded here.
 *
 *     db()->all("SELECT * FROM {assets} WHERE status = ?", ['in_service'])
 *
 * That keeps the configurable table prefix impossible to forget, because SQL
 * with a bare table name simply will not find the table.
 */
final class Database
{
    private static ?Database $instance = null;

    private PDO $pdo;

    private string $prefix;

    /** Nesting depth for transaction(). PDO has no nested transactions. */
    private int $txDepth = 0;

    /** Set when a nested transaction rolled back, so the outer one cannot commit. */
    private bool $txAborted = false;

    /** @var list<array{sql: string, params: array<int|string, mixed>, ms: float}> */
    private array $queryLog = [];

    private bool $logQueries = false;

    private function __construct(PDO $pdo, string $prefix)
    {
        $this->pdo    = $pdo;
        $this->prefix = $prefix;
    }

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    /**
     * The shared connection, built from Config on first use.
     */
    public static function instance(): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        self::$instance = self::connect([
            'host'    => (string) Config::get('db.host', 'localhost'),
            'port'    => (int) Config::get('db.port', 3306),
            'name'    => (string) Config::get('db.name', ''),
            'user'    => (string) Config::get('db.user', ''),
            'pass'    => (string) Config::get('db.pass', ''),
            'charset' => (string) Config::get('db.charset', 'utf8mb4'),
            'prefix'  => (string) Config::get('db.prefix', ''),
        ]);

        return self::$instance;
    }

    /**
     * Build a connection from an explicit array. Used by the installer, which
     * must connect before config.php exists, and by the test harness.
     *
     * @param array{host?: string, port?: int|string, name?: string, user?: string,
     *              pass?: string, charset?: string, prefix?: string, socket?: string} $cfg
     *
     * @throws RuntimeException with a message safe to show a site administrator
     */
    public static function connect(array $cfg): self
    {
        $host    = (string) ($cfg['host'] ?? 'localhost');
        $port    = (int) ($cfg['port'] ?? 3306);
        $name    = (string) ($cfg['name'] ?? '');
        $user    = (string) ($cfg['user'] ?? '');
        $pass    = (string) ($cfg['pass'] ?? '');
        $charset = (string) ($cfg['charset'] ?? 'utf8mb4');
        $prefix  = (string) ($cfg['prefix'] ?? '');
        $socket  = (string) ($cfg['socket'] ?? '');

        // cPanel users often paste "localhost:3306" or a socket path into the
        // host box. Accept both rather than failing with a cryptic error.
        if ($socket === '' && strpos($host, '/') === 0) {
            $socket = $host;
            $host   = 'localhost';
        } elseif (strpos($host, ':') !== false && substr_count($host, ':') === 1) {
            [$maybeHost, $maybePort] = explode(':', $host, 2);
            if (ctype_digit($maybePort)) {
                $host = $maybeHost;
                $port = (int) $maybePort;
            }
        }

        if ($socket !== '') {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $name, $charset);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new RuntimeException(self::friendlyConnectionError($e), (int) $e->getCode(), $e);
        }

        // Store and read every timestamp in UTC so the application never has to
        // guess what zone the database server is in. Some shared hosts refuse
        // this; the app still works because it writes UTC strings from PHP.
        try {
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (Throwable $e) {
            // Non-fatal by design.
        }

        // Reject zero dates and silent truncation so bad data fails loudly.
        try {
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        } catch (Throwable $e) {
            // Non-fatal: some managed hosts lock sql_mode down.
        }

        return new self($pdo, $prefix);
    }

    /**
     * Turn a PDO connection failure into something a site owner can act on.
     */
    private static function friendlyConnectionError(PDOException $e): string
    {
        $code    = (string) $e->getCode();
        $message = $e->getMessage();

        if ($code === '1045' || stripos($message, 'access denied') !== false) {
            return 'The database rejected those credentials. Check the database '
                 . 'username and password, and confirm in cPanel that the user is '
                 . 'assigned to the database with ALL PRIVILEGES.';
        }

        if ($code === '1049' || stripos($message, 'unknown database') !== false) {
            return 'That database does not exist. Create it first in cPanel under '
                 . 'MySQL Databases, then use the full name including your account prefix.';
        }

        if ($code === '2002' || stripos($message, 'connection refused') !== false
            || stripos($message, "can't connect") !== false) {
            return 'Could not reach the database server. On most cPanel accounts the '
                 . 'host is "localhost". Check the host name and port.';
        }

        return 'Could not connect to the database: ' . $message;
    }

    /**
     * Replace the shared instance. Used by the installer once config is written.
     */
    public static function setInstance(?Database $db): void
    {
        self::$instance = $db;
    }

    public static function hasInstance(): bool
    {
        return self::$instance instanceof self;
    }

    // -------------------------------------------------------------------------
    // Table prefixing
    // -------------------------------------------------------------------------

    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Prefixed, backtick-free table name. Pass the bare name: table('assets').
     */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /**
     * Expand every {table} token in a SQL string to its prefixed name.
     *
     * Only lowercase identifiers are matched, which keeps the pattern away from
     * anything else that might legitimately contain braces.
     */
    public function expand(string $sql): string
    {
        if (strpos($sql, '{') === false) {
            return $sql;
        }

        $prefix = $this->prefix;

        return (string) preg_replace_callback(
            '/\{([a-z_][a-z0-9_]*)\}/',
            static function (array $m) use ($prefix): string {
                return $prefix . $m[1];
            },
            $sql
        );
    }

    // -------------------------------------------------------------------------
    // Query execution
    // -------------------------------------------------------------------------

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepare, bind and execute. Every other method goes through this one.
     *
     * @param array<int|string, mixed> $params
     */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $sql   = $this->expand($sql);
        $start = microtime(true);

        try {
            if ($params === []) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
            } else {
                $stmt = $this->pdo->prepare($sql);
                $this->bind($stmt, $params);
                $stmt->execute();
            }
        } catch (PDOException $e) {
            // Log the real SQL for the administrator, show a generic message.
            log_error('Database query failed: ' . $e->getMessage(), [
                'sql'    => $sql,
                'params' => self::redactParams($params),
            ]);

            throw $e;
        }

        if ($this->logQueries) {
            $this->queryLog[] = [
                'sql'    => $sql,
                'params' => $params,
                'ms'     => round((microtime(true) - $start) * 1000, 2),
            ];
        }

        return $stmt;
    }

    /**
     * Bind parameters with the right PDO type so integers stay integers.
     *
     * @param array<int|string, mixed> $params
     */
    private function bind(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $placeholder = is_int($key) ? $key + 1 : (strpos((string) $key, ':') === 0 ? $key : ':' . $key);

            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type  = PDO::PARAM_INT;
                $value = $value ? 1 : 0;
            } elseif ($value === null) {
                $type = PDO::PARAM_NULL;
            } else {
                $type  = PDO::PARAM_STR;
                $value = is_float($value) ? (string) $value : $value;
            }

            $stmt->bindValue($placeholder, $value, $type);
        }
    }

    /**
     * All matching rows.
     *
     * @param  array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        $rows = $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * The first row, or null.
     *
     * @param  array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * The first column of the first row.
     *
     * @param  array<int|string, mixed> $params
     * @param  mixed                    $default
     * @return mixed
     */
    public function value(string $sql, array $params = [], $default = null)
    {
        $value = $this->run($sql, $params)->fetchColumn(0);

        return $value === false ? $default : $value;
    }

    /**
     * A flat list built from the first column of every row.
     *
     * @param  array<int|string, mixed> $params
     * @return list<mixed>
     */
    public function column(string $sql, array $params = []): array
    {
        $values = $this->run($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);

        return is_array($values) ? array_values($values) : [];
    }

    /**
     * Key/value pairs from the first two columns.
     *
     * @param  array<int|string, mixed> $params
     * @return array<int|string, mixed>
     */
    public function pairs(string $sql, array $params = []): array
    {
        $out = [];

        foreach ($this->all($sql, $params) as $row) {
            $values = array_values($row);
            if (count($values) >= 2) {
                $out[(string) $values[0]] = $values[1];
            }
        }

        return $out;
    }

    /**
     * Run a COUNT query and return an int.
     *
     * @param array<int|string, mixed> $params
     */
    public function count(string $sql, array $params = []): int
    {
        return (int) $this->value($sql, $params, 0);
    }

    // -------------------------------------------------------------------------
    // Convenience writers
    //
    // These take a BARE table name (no braces) and prefix it themselves.
    // -------------------------------------------------------------------------

    /**
     * Insert a row and return its id.
     *
     * @param array<string, mixed> $data column => value
     */
    public function insert(string $table, array $data): int
    {
        if ($data === []) {
            throw new RuntimeException('Database::insert() called with no columns.');
        }

        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList   = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($this->table($table)),
            $columnList,
            $placeholders
        );

        $this->run($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert several rows in one statement.
     *
     * @param list<array<string, mixed>> $rows all rows must share the same keys
     */
    public function insertMany(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $columns    = array_keys($rows[0]);
        $columnList = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
        $tuple      = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $params = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $params[] = $row[$column] ?? null;
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->quoteIdentifier($this->table($table)),
            $columnList,
            implode(', ', array_fill(0, count($rows), $tuple))
        );

        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Update rows and return how many changed.
     *
     * @param array<string, mixed> $data  column => new value
     * @param array<string, mixed> $where column => value (null becomes IS NULL)
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($data === []) {
            return 0;
        }

        if ($where === []) {
            throw new RuntimeException('Database::update() refused: an empty WHERE would rewrite every row.');
        }

        $set    = [];
        $params = [];

        foreach ($data as $column => $value) {
            $set[]    = $this->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        [$whereSql, $whereParams] = $this->buildWhere($where);

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($this->table($table)),
            implode(', ', $set),
            $whereSql
        );

        return $this->run($sql, array_merge($params, $whereParams))->rowCount();
    }

    /**
     * Delete rows and return how many went.
     *
     * @param array<string, mixed> $where
     */
    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            throw new RuntimeException('Database::delete() refused: an empty WHERE would empty the table.');
        }

        [$whereSql, $params] = $this->buildWhere($where);

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($this->table($table)),
            $whereSql
        );

        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Does at least one matching row exist?
     *
     * @param array<string, mixed> $where
     */
    public function exists(string $table, array $where): bool
    {
        [$whereSql, $params] = $this->buildWhere($where);

        $sql = sprintf(
            'SELECT 1 FROM %s WHERE %s LIMIT 1',
            $this->quoteIdentifier($this->table($table)),
            $whereSql
        );

        return $this->value($sql, $params) !== null;
    }

    /**
     * Fetch a single row by primary key.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $table, int $id, string $key = 'id'): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ? LIMIT 1',
            $this->quoteIdentifier($this->table($table)),
            $this->quoteIdentifier($key)
        );

        return $this->one($sql, [$id]);
    }

    /**
     * Build a WHERE fragment from an assoc array, handling null and arrays.
     *
     * @param  array<string, mixed> $where
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildWhere(array $where): array
    {
        $clauses = [];
        $params  = [];

        foreach ($where as $column => $value) {
            $quoted = $this->quoteIdentifier($column);

            if ($value === null) {
                $clauses[] = $quoted . ' IS NULL';
                continue;
            }

            if (is_array($value)) {
                if ($value === []) {
                    // An IN () with nothing in it matches nothing.
                    $clauses[] = '1 = 0';
                    continue;
                }

                $clauses[] = $quoted . ' IN (' . implode(', ', array_fill(0, count($value), '?')) . ')';
                foreach ($value as $item) {
                    $params[] = $item;
                }
                continue;
            }

            $clauses[] = $quoted . ' = ?';
            $params[]  = $value;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Backtick-quote an identifier. Rejects anything that is not a plain
     * identifier, because identifiers can never come from user input.
     */
    public function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new RuntimeException('Refusing to use an unsafe SQL identifier: ' . $identifier);
        }

        return '`' . $identifier . '`';
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    /**
     * Run a callable inside a transaction, committing on success and rolling
     * back on any throwable. Safe to nest: only the outermost call commits.
     *
     * @param  callable(Database): mixed $fn
     * @return mixed whatever $fn returned
     */
    public function transaction(callable $fn)
    {
        $this->beginTransaction();

        try {
            $result = $fn($this);
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->txDepth === 0) {
            $this->pdo->beginTransaction();
            $this->txAborted = false;
        }

        $this->txDepth++;
    }

    public function commit(): void
    {
        if ($this->txDepth === 0) {
            return;
        }

        $this->txDepth--;

        if ($this->txDepth > 0) {
            return;
        }

        if ($this->txAborted) {
            // An inner scope rolled back, so the whole thing must roll back.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->txAborted = false;

            throw new RuntimeException('Transaction was rolled back by an inner operation and cannot be committed.');
        }

        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->txDepth === 0) {
            return;
        }

        $this->txDepth--;

        if ($this->txDepth > 0) {
            // Remember, so the outer commit cannot silently succeed.
            $this->txAborted = true;

            return;
        }

        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        $this->txAborted = false;
    }

    public function inTransaction(): bool
    {
        return $this->txDepth > 0;
    }

    // -------------------------------------------------------------------------
    // Introspection and debugging
    // -------------------------------------------------------------------------

    /**
     * Does a table exist? Takes a bare name.
     */
    public function tableExists(string $table): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM ' . $this->quoteIdentifier($this->table($table)) . ' LIMIT 1');

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Column names of a table, or an empty list if it does not exist.
     *
     * @return list<string>
     */
    public function columns(string $table): array
    {
        try {
            $rows = $this->all('SHOW COLUMNS FROM ' . $this->quoteIdentifier($this->table($table)));
        } catch (Throwable $e) {
            return [];
        }

        $names = [];
        foreach ($rows as $row) {
            if (isset($row['Field'])) {
                $names[] = (string) $row['Field'];
            }
        }

        return $names;
    }

    public function serverVersion(): string
    {
        try {
            return (string) $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Throwable $e) {
            return 'unknown';
        }
    }

    public function enableQueryLog(bool $on = true): void
    {
        $this->logQueries = $on;
    }

    /**
     * @return list<array{sql: string, params: array<int|string, mixed>, ms: float}>
     */
    public function queryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Strip anything that looks like a credential before a query reaches the log.
     *
     * @param  array<int|string, mixed> $params
     * @return array<int|string, mixed>
     */
    private static function redactParams(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_string($value) && strlen($value) > 40 && strpos($value, '$2y$') === 0) {
                $params[$key] = '[password hash]';
            }
        }

        return $params;
    }
}
