<?php
// ============================================================================
// РЕПОЗИТОРИЙ СЕТЕЙ
// ============================================================================

class NetworkRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Получение всех активных сетей
     */
    public function getAll($includeInactive = false) {
        $sql = "SELECT id, name, icon, color, full_name FROM networks WHERE 1=1";
        if (!$includeInactive) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY 
            CASE name 
                WHEN 'ERC20' THEN 1
                WHEN 'BEP20' THEN 2
                WHEN 'TRC20' THEN 3
                WHEN 'SOL' THEN 4
                WHEN 'BTC' THEN 5
                ELSE 6
            END,
            name";
        
        return $this->pdo->query($sql)->fetchAll();
    }
    
    /**
     * Получение сети по имени
     */
    public function getByName($name) {
        $stmt = $this->pdo->prepare("SELECT * FROM networks WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch();
    }
    
    /**
     * Добавление новой сети
     */
    public function create($name, $icon, $color, $fullName = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO networks (name, icon, color, full_name, is_active) 
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$name, $icon, $color, $fullName]);
        return $this->pdo->lastInsertId();
    }
}