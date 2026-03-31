<?php
require_once __DIR__ . '/env.php';

if (!function_exists('dbStatementFetchAllAssoc')) {
    function dbStatementFetchAllAssoc($stmt) {
        if (!($stmt instanceof mysqli_stmt)) {
            return [];
        }

        if (method_exists($stmt, 'get_result')) {
            $result = $stmt->get_result();
            if ($result instanceof mysqli_result) {
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                $result->free();
                return $rows;
            }
        }

        $stmt->store_result();
        $metadata = $stmt->result_metadata();
        if ($metadata === false) {
            return [];
        }

        $row = [];
        $boundColumns = [];
        while ($field = $metadata->fetch_field()) {
            $row[$field->name] = null;
            $boundColumns[] = &$row[$field->name];
        }
        $metadata->free();

        if (!call_user_func_array([$stmt, 'bind_result'], $boundColumns)) {
            return [];
        }

        $rows = [];
        while ($stmt->fetch()) {
            $rows[] = array_map(static function ($value) {
                return $value;
            }, $row);
        }

        return $rows;
    }
}

if (!function_exists('dbStatementFetchOneAssoc')) {
    function dbStatementFetchOneAssoc($stmt) {
        $rows = dbStatementFetchAllAssoc($stmt);
        return $rows[0] ?? null;
    }
}

if (!function_exists('dbStatementHasRows')) {
    function dbStatementHasRows($stmt) {
        return dbStatementFetchOneAssoc($stmt) !== null;
    }
}

class Database {
    private $host;
    private $port;
    private $socket;
    private $username;
    private $password;
    private $database;
    private $connections = [];

    public function __construct($database = null) {
        $this->host = env('DB_HOST', 'localhost');
        $this->port = (int)env('DB_PORT', 3306);
        $this->socket = (string)env('DB_SOCKET', '');
        $this->username = env('DB_USERNAME', 'root');
        $this->password = (string)env('DB_PASSWORD', '');
        $this->database = $database ?: env('DB_DATABASE', 'student_record_system');
    }

    private function createConnection($databaseName) {
        try {
            $socket = $this->socket !== '' ? $this->socket : null;
            $connection = new mysqli(
                $this->host,
                $this->username,
                $this->password,
                $databaseName,
                $this->port > 0 ? $this->port : 3306,
                $socket
            );
        } catch (mysqli_sql_exception $exception) {
            error_log('Database connection failed for "' . $databaseName . '": ' . $exception->getMessage());
            return null;
        }

        if (!($connection instanceof mysqli) || $connection->connect_error) {
            error_log('Database connection failed for "' . $databaseName . '": ' . ($connection->connect_error ?? 'Unknown error'));
            return null;
        }

        $connection->set_charset('utf8mb4');
        return $connection;
    }

    public function getConnection($databaseName = null) {
        $databaseName = $databaseName ?: $this->database;

        if (!array_key_exists($databaseName, $this->connections)) {
            $this->connections[$databaseName] = $this->createConnection($databaseName);
        }

        return $this->connections[$databaseName];
    }

    public function getCentralConnection() {
        return $this->getConnection($this->database);
    }

    public function getConnectionForDatabase($databaseName) {
        return $this->getConnection($databaseName);
    }

    public function getDefaultDatabaseName() {
        return $this->database;
    }

    public function getCredentials() {
        return [
            'host' => $this->host,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database,
        ];
    }

    public function query($sql) {
        $connection = $this->getCentralConnection();
        return $connection instanceof mysqli ? $connection->query($sql) : false;
    }

    public function prepare($sql) {
        $connection = $this->getCentralConnection();
        return $connection instanceof mysqli ? $connection->prepare($sql) : false;
    }

    public function escape($string) {
        $connection = $this->getCentralConnection();
        return $connection instanceof mysqli ? $connection->real_escape_string($string) : '';
    }

    public function getLastInsertId() {
        $connection = $this->getCentralConnection();
        return $connection instanceof mysqli ? $connection->insert_id : 0;
    }

    public function __destruct() {
        foreach ($this->connections as $connection) {
            if ($connection instanceof mysqli) {
                $connection->close();
            }
        }
    }
}
?>
