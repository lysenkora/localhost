<?php
// ============================================================================
// РЕПОЗИТОРИЙ АКТИВОВ
// ============================================================================

class AssetRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Получение всех активов
     */
    public function getAll($includeInactive = false) {
        $sql = "SELECT id, symbol, name, type, sector, currency_code FROM assets WHERE 1=1";
        if (!$includeInactive) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY symbol";
        
        return $this->pdo->query($sql)->fetchAll();
    }
    
    /**
     * Получение активов по типу
     */
    public function getByType($type) {
        $stmt = $this->pdo->prepare("
            SELECT id, symbol, name, type, sector, currency_code 
            FROM assets 
            WHERE type = ? AND is_active = 1 
            ORDER BY symbol
        ");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }
    
    /**
     * Получение актива по ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Получение актива по символу
     */
    public function getBySymbol($symbol) {
        $stmt = $this->pdo->prepare("SELECT * FROM assets WHERE symbol = ?");
        $stmt->execute([$symbol]);
        return $stmt->fetch();
    }
    
    /**
     * Добавление нового актива
     */
    public function create($symbol, $name, $type, $currencyCode = null, $sector = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO assets (symbol, name, type, currency_code, sector, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$symbol, $name, $type, $currencyCode, $sector]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Получение активов пользователя для таблицы "Мои активы"
     */
    public function getUserAssets($usdRubRate) {
        $stmt = $this->pdo->query("
            SELECT 
                a.id,
                a.symbol,
                a.name,
                a.type,
                SUM(p.quantity) as total_quantity,
                CASE 
                    WHEN a.symbol IN ('USDT', 'USDC') THEN (
                        SELECT COALESCE(SUM(quantity * price) / NULLIF(SUM(quantity), 0), 1)
                        FROM trades t
                        WHERE t.asset_id IN (SELECT id FROM assets WHERE symbol IN ('USD', 'USDT', 'USDC'))
                        AND t.operation_type = 'buy'
                    )
                    ELSE SUM(p.quantity * COALESCE(p.average_buy_price, 0)) / NULLIF(SUM(p.quantity), 0)
                END as avg_price,
                a.currency_code,
                GROUP_CONCAT(DISTINCT p.platform_id ORDER BY p.platform_id SEPARATOR ',') as platform_ids
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            WHERE p.quantity > 0
            GROUP BY a.id, a.symbol, a.name, a.type, a.currency_code
            ORDER BY SUM(p.quantity) * COALESCE(
                (SELECT rate FROM exchange_rates 
                 WHERE from_currency = a.currency_code 
                 AND to_currency = 'USD' 
                 AND date = CURDATE()
                ), 1
            ) DESC
        ");
        
        return $stmt->fetchAll();
    }
}