<?php

namespace Telemedicall\OracleFhir\Database;

use Illuminate\Support\Facades\DB;
use mysqli;
use RuntimeException;

class OracleDbConnection
{
    protected array $config;

    public function __construct(array $overrides = [])
    {
        $this->config = array_replace_recursive(
            config('OracleFhir', []),
            $overrides
        );
    }

    /**
     * Returns the name of the Laravel database connection to use
     */
    public function getConnectionName(): string
    {
        return $this->config['db']['connection'] ?? 'mysql';
    }

    /**
     * Returns Laravel's query builder / connection instance
     */
    public function connection()
    {
        return DB::connection($this->getConnectionName());
    }

    /**
     * Returns raw mysqli connection (use only when absolutely necessary)
     */
    public function raw(): mysqli
    {
        $connName = $this->getConnectionName();
        $config = config("database.connections.{$connName}");

        if (!$config) {
            throw new RuntimeException("Database connection '{$connName}' not found in config/database.php");
        }

        $mysqli = new mysqli(
            $config['host'] ?? '127.0.0.1',
            $config['username'] ?? '',
            $config['password'] ?? '',
            $config['database'] ?? '',
            $config['port'] ?? 3306
        );

        if ($mysqli->connect_error) {
            throw new RuntimeException("MySQLi connection failed: " . $mysqli->connect_error);
        }

        $mysqli->set_charset($config['charset'] ?? 'utf8mb4');

        return $mysqli;
    }

    /**
     * Legacy-style conn() method - returns raw mysqli (for minimal code changes)
     * @deprecated  Use ->connection() + Laravel Query Builder instead
     */
    public function conn(): mysqli
    {
        return $this->raw();
    }

    /**
     * Quick helper: run a prepared statement with bindings
     * Returns mysqli_stmt on success
     */
    public function prepare(string $sql, array $typesAndValues = []): \mysqli_stmt
    {
        $mysqli = $this->raw();
        $stmt = $mysqli->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }

        if (!empty($typesAndValues)) {
            $types = '';
            $values = [];

            foreach ($typesAndValues as $i => $item) {
                if ($i % 2 === 0) {
                    $types .= $item; // type char: s,i,d,b
                } else {
                    $values[] = $item;
                }
            }

            if ($types) {
                $stmt->bind_param($types, ...$values);
            }
        }

        return $stmt;
    }

    /**
     * Quick helper: execute a query and get all rows as assoc array
     */
    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->prepare($sql);

        if (!empty($bindings)) {
            $types = str_repeat('s', count($bindings));
            $stmt->bind_param($types, ...$bindings);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();

        return $rows;
    }

    /**
     * Quick helper: execute an INSERT/UPDATE/DELETE and return affected rows
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $stmt = $this->prepare($sql);

        if (!empty($bindings)) {
            $types = str_repeat('s', count($bindings));
            $stmt->bind_param($types, ...$bindings);
        }

        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }
}