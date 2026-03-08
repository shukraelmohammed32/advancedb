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
                // Log error but don't expose details
                error_log("Database connection error: " . $e->getMessage());
                
                // Try to create database if it doesn't exist
                $this->createDatabaseIfNotExists();
                
                // Retry connection
                try {
                    $this->connection = new mysqli(
                        AppConfig::getDatabaseHost(), 
                        AppConfig::getDatabaseUsername(), 
                        AppConfig::getDatabasePassword(), 
                        AppConfig::getDatabaseName()
                    );
                    $this->connection->set_charset('utf8mb4');
                } catch (Exception $retry_e) {
                    die("Database unavailable. Please contact administrator.");
                }
            }
        }
        
        return $this->connection;
    }

    private function createDatabaseIfNotExists() {
        try {
            $conn = new mysqli(
                AppConfig::getDatabaseHost(), 
                AppConfig::getDatabaseUsername(), 
                AppConfig::getDatabasePassword()
            );
            
            $conn->query("CREATE DATABASE IF NOT EXISTS `" . AppConfig::getDatabaseName() . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->close();
        } catch (Exception $e) {
            error_log("Failed to create database: " . $e->getMessage());
        }
    }

    public function getCentralConnection() {
        if ($this->central_connection === null) {
            $this->central_connection = $this->getConnection();
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
                error_log("SQL Error: " . $conn->error . " Query: " . $sql);
                return false;
            }
            return $result;
        }
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error . " Query: " . $sql);
            return false;
        }
        
        if ($stmt->execute($params)) {
            return $stmt->get_result();
        }
        
        error_log("Execute failed: " . $stmt->error);
        return false;
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
