<?php

require_once __DIR__ . '/app_config.php';

class Database {
    private $connection;
    private $central_connection;

    public function __construct() {
        // Configuration loaded from environment via AppConfig
    }

    public function getConnection() {
        if ($this->connection === null) {
            try {
                $this->connection = new mysqli(
                    AppConfig::getDatabaseHost(), 
                    AppConfig::getDatabaseUsername(), 
                    AppConfig::getDatabasePassword(), 
                    AppConfig::getDatabaseName()
                );
                
                if ($this->connection->connect_error) {
                    throw new Exception("Database connection failed");
                }
                
                $this->connection->set_charset('utf8mb4');
            } catch (Exception $e) {
                error_log("Database connection error");
                throw new Exception("Unable to connect to database");
            }
        }
        
        return $this->connection;
    }

    public function getCentralConnection() {
        if ($this->central_connection === null) {
            try {
                $this->central_connection = new mysqli(
                    AppConfig::getDatabaseHost(), 
                    AppConfig::getDatabaseUsername(), 
                    AppConfig::getDatabasePassword(), 
                    AppConfig::getDatabaseName()
                );
                
                if ($this->central_connection->connect_error) {
                    throw new Exception("Central database connection failed");
                }
                
                $this->central_connection->set_charset('utf8mb4');
            } catch (Exception $e) {
                error_log("Central database connection error");
                throw new Exception("Unable to connect to central database");
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
            $result = $conn->query($sql);
            if ($result === false) {
                error_log("Database query failed");
                throw new Exception("Database query failed");
            }
            return $result;
        }
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Database prepare failed");
            throw new Exception("Failed to prepare database statement");
        }
        
        if ($stmt->execute($params)) {
            return $stmt->get_result();
        }
        
        error_log("Database execute failed");
        throw new Exception("Failed to execute database statement");
    }

    public function lastInsertId() {
        $conn = $this->getConnection();
        return $conn->insert_id;
    }

    public function escape($value) {
        $conn = $this->getConnection();
        return $conn->real_escape_string(htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8'));
    }
}
