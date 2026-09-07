<?php

declare(strict_types=1);

namespace TinyIB;

use PDO;
use PDOStatement;

final readonly class Database
{
    private PDO $connection;
    private const array NUMERIC_FIELDS = ['id', 'parent', 'timestamp', 'bumped', 'role', 'lastactive', 'expire', 'account', 'post', 'file_size', 'image_width', 'image_height', 'thumb_width', 'thumb_height', 'moderated', 'stickied', 'locked'];

    public function __construct(private Config $config)
    {
        $dsn = $config->dbdsn !== '' ? $config->dbdsn : match ($config->dbdriver) {
            'sqlite' => 'sqlite:' . $config->dbpath,
            'mysql' => "mysql:host={$config->dbhost};port={$config->dbport};dbname={$config->dbname};charset=utf8mb4",
            'pgsql' => "pgsql:host={$config->dbhost};port={$config->dbport};dbname={$config->dbname}",
            default => throw new \InvalidArgumentException('Unsupported PDO database driver.'),
        };
        $this->connection = new PDO($dsn, $config->dbusername, $config->dbpassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        if ($config->dbdriver === 'mysql') {
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        if ($config->dbdriver === 'sqlite') {
            $this->connection->exec('PRAGMA busy_timeout = 60000');
            $this->connection->exec('PRAGMA foreign_keys = ON');
        }
        $this->initialize();
    }

    public function identifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
            throw new \InvalidArgumentException('Invalid database identifier.');
        }
        $quote = $this->config->dbdriver === 'mysql' ? '`' : '"';
        return $quote . $name . $quote;
    }

    /** @param array<int|string, scalar|null> $parameters */
    private function statement(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->connection->prepare($sql);
        foreach ($parameters as $key => $value) {
            $statement->bindValue(is_int($key) ? $key + 1 : $key, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }
        $statement->execute();
        return $statement;
    }

    /** @param array<int|string, scalar|null> $parameters */
    public function rows(string $sql, array $parameters = []): array
    {
        $rows = $this->statement($sql, $parameters)->fetchAll();
        return array_map(static function (array $row): array {
            foreach (self::NUMERIC_FIELDS as $field) {
                if (array_key_exists($field, $row)) {
                    $row[$field] = (int) $row[$field];
                }
            }
            return $row;
        }, $rows);
    }

    /** @param array<int|string, scalar|null> $parameters */
    public function row(string $sql, array $parameters = []): array
    {
        return array_first($this->rows($sql, $parameters)) ?? [];
    }

    /**
     * @param array<int|string, scalar|null> $parameters
     * @phpstan-impure Database contents may change between calls.
     */
    public function count(string $sql, array $parameters = []): int
    {
        return (int) $this->statement($sql, $parameters)->fetchColumn();
    }

    /** @param array<int|string, scalar|null> $parameters */
    public function execute(string $sql, array $parameters = []): void
    {
        $this->statement($sql, $parameters);
    }

    /** @param array<string, scalar|null> $values */
    public function insert(string $table, array $values): int
    {
        $columns = array_map($this->identifier(...), array_keys($values));
        $sql = 'INSERT INTO ' . $this->identifier($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')';
        if ($this->config->dbdriver === 'pgsql') {
            return $this->count($sql . ' RETURNING id', array_values($values));
        }
        $this->execute($sql, array_values($values));
        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, scalar|null> $values */
    public function update(string $table, int $id, array $values): void
    {
        $columns = array_map(fn(string $name): string => $this->identifier($name) . ' = ?', array_keys($values));
        $this->execute('UPDATE ' . $this->identifier($table) . ' SET ' . implode(', ', $columns) . ' WHERE id = ?', [...array_values($values), $id]);
    }

    private function initialize(): void
    {
        $id = match ($this->config->dbdriver) {
            'mysql' => 'BIGINT PRIMARY KEY AUTO_INCREMENT',
            'pgsql' => 'BIGSERIAL PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY',
        };
        $schemas = [
            $this->config->dbaccounts => ['username' => 'VARCHAR(255)', 'password' => 'TEXT', 'role' => 'INTEGER', 'lastactive' => 'BIGINT'],
            $this->config->dbbans => ['ip' => 'VARCHAR(255)', 'timestamp' => 'BIGINT', 'expire' => 'BIGINT', 'reason' => 'TEXT'],
            $this->config->dbkeywords => ['text' => 'VARCHAR(255)', 'action' => 'VARCHAR(255)'],
            $this->config->dblogs => ['timestamp' => 'BIGINT', 'account' => 'BIGINT', 'message' => 'TEXT'],
            $this->config->dbreports => ['ip' => 'VARCHAR(255)', 'post' => 'BIGINT'],
        ];
        $post = [];
        foreach (['parent','timestamp','bumped','file_size','image_width','image_height','thumb_width','thumb_height','moderated','stickied','locked'] as $field) {
            $post[$field] = 'BIGINT';
        }
        foreach (['ip','name','tripcode','email','nameblock','subject','message','password','file','file_hex','file_original','file_size_formatted','thumb'] as $field) {
            $post[$field] = 'TEXT';
        }
        $schemas[$this->config->dbposts] = $post;
        foreach ($schemas as $table => $columns) {
            $definitions = [$this->identifier('id') . ' ' . $id];
            foreach ($columns as $name => $type) {
                $definitions[] = $this->identifier($name) . ' ' . $type . ' NOT NULL';
            }
            $this->connection->exec('CREATE TABLE IF NOT EXISTS ' . $this->identifier($table) . ' (' . implode(', ', $definitions) . ')');
        }
        if ($this->config->dbdriver !== 'mysql') {
            foreach (['parent','bumped','moderated'] as $column) {
                $this->connection->exec('CREATE INDEX IF NOT EXISTS ' . $this->identifier($this->config->dbposts . '_' . $column) . ' ON ' . $this->identifier($this->config->dbposts) . ' (' . $this->identifier($column) . ')');
            }
        }
    }
}
