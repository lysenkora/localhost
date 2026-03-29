<?php
// ============================================================================
// КОНФИГУРАЦИЯ БАЗЫ ДАННЫХ
// ============================================================================

class Database {
    private static $instance = null;
    private $pdo;
    
    private $host = 'localhost';
    private $dbname = 'investment_portfolio';
    private $username = 'root';
    private $password = '';
    
    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Ошибка подключения к БД: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

// Для обратной совместимости с существующим кодом
function getDbConnection() {
    return Database::getInstance()->getConnection();
}