<?php

require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $connection;
    private $central_connection;

    public function __construct() {
        $this->host = env('DB_HOST', 'localhost');
        $this->username = env('DB_USERNAME', 'root');
        $this->password = env('DB_PASSWORD', '');
        $this->database = env('DB_DATABASE', 'student_record_system');
    }

    public function getConnection() {
        if ($this->connection === null) {
            try {
                $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
                
                if ($this->connection->connect_error) {
                    throw new Exception("Connection failed: " . $this->connection->connect_error);
                }
                
                $this->connection->set_charset('utf8mb4');
            } catch (Exception $e) {
                die("Database connection error: " . $e->getMessage());
            }
        }
        
        return $this->connection;
    }

    public function getCentralConnection() {
        if ($this->central_connection === null) {
            try {
                $this->central_connection = new mysqli($this->host, $this->username, $this->password, $this->database);
                
                if ($this->central_connection->connect_error) {
                    throw new Exception("Central connection failed: " . $this->central_connection->connect_error);
                }
                
                $this->central_connection->set_charset('utf8mb4');
            } catch (Exception $e) {
                die("Central database connection error: " . $e->getMessage());
            }
        }
        
        return $this->central_connection;
    }

    public function close() {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
        
        if ($this->central_connection !== null) {
            $this->central_connection->close();
            $this->central_connection = null;
        }
    }

    public function beginTransaction() {
        $conn = $this->getConnection();
        $conn->begin_transaction();
    }

    public function commit() {
        $conn = $this->getConnection();
        $conn->commit();
    }

    public function rollback() {
        $conn = $this->getConnection();
        $conn->rollback();
    }

    public function query($sql, $params = []) {
        $conn = $this->getConnection();
        
        if (empty($params)) {
            return $conn->query($sql);
        }
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if ($stmt->execute($params)) {
            return $stmt->get_result();
        }
        
        throw new Exception("Execute failed: " . $stmt->error);
    }

    public function lastInsertId() {
        $conn = $this->getConnection();
        return $conn->insert_id;
    }

    public function escape($value) {
        $conn = $this->getConnection();
        return $conn->real_escape_string($value);
    }
}
