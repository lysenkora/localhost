<?php
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $configFile = __DIR__ . '/config.php';
        if (!file_exists($configFile)) {
            die("Config file not found: " . $configFile);
        }
        
        $config = require $configFile;
        $db = $config['database'];
        
        try {
            $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";
            $this->pdo = new PDO($dsn, $db['username'], $db['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }
}