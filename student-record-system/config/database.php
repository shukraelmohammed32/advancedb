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
    private $username;
    private $password;
    private $database;
    private $connections = [];

    public function __construct($database = null) {
        $this->host = getenv('DB_HOST') ?: 'sql303.infinityfree.com';
        $this->username = getenv('DB_USERNAME') ?: 'if0_41348166';
        $this->password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'RqMCVBiQVfEf4x';
        $this->database = $database ?: (getenv('DB_DATABASE') ?: 'if0_41348166_student_record_system');
    }

    private function createConnection($databaseName) {
        $connection = new mysqli($this->host, $this->username, $this->password, $databaseName);

        if ($connection->connect_error) {
            die('Connection failed: ' . $connection->connect_error);
        }

        $connection->set_charset('utf8mb4');
        return $connection;
    }

    public function getConnection($databaseName = null) {
        $databaseName = $databaseName ?: $this->database;

        if (!isset($this->connections[$databaseName])) {
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
        return $this->getCentralConnection()->query($sql);
    }

    public function prepare($sql) {
        return $this->getCentralConnection()->prepare($sql);
    }

    public function escape($string) {
        return $this->getCentralConnection()->real_escape_string($string);
    }

    public function getLastInsertId() {
        return $this->getCentralConnection()->insert_id;
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
