<?php
class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $connections = [];

    public function __construct($database = null) {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->username = getenv('DB_USERNAME') ?: 'root';
        $this->password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
        $this->database = $database ?: (getenv('DB_DATABASE') ?: 'student_record_system');
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
