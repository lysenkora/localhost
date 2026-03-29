<?php
class LimitOrderRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Получение активных лимитных ордеров
     */
    public function getActive($limit = 3) {
        // Исправлено: LIMIT подставляется напрямую в строку
        $limit = (int)$limit;
        $sql = "
            SELECT 
                lo.*,
                a.symbol,
                a.name,
                a.type,
                p.name as platform_name,
                p.type as platform_type
            FROM limit_orders lo
            JOIN assets a ON lo.asset_id = a.id
            JOIN platforms p ON lo.platform_id = p.id
            WHERE lo.status = 'active'
            ORDER BY lo.created_at DESC
            LIMIT {$limit}
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Получение ордера по ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT lo.*, a.symbol, a.type, a.id as asset_id,
                p.id as platform_id, p.name as platform_name
            FROM limit_orders lo
            JOIN assets a ON lo.asset_id = a.id
            JOIN platforms p ON lo.platform_id = p.id
            WHERE lo.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Создание ордера
     */
    public function create($operationType, $platformId, $assetId, $quantity, 
                          $limitPrice, $priceCurrency, $expiryDate = null, $notes = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO limit_orders (
                operation_type, asset_id, platform_id, quantity, 
                limit_price, price_currency, expiry_date, notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        return $stmt->execute([
            $operationType, $assetId, $platformId, $quantity,
            $limitPrice, $priceCurrency, $expiryDate, $notes
        ]);
    }
    
    /**
     * Обновление статуса ордера
     */
    public function updateStatus($id, $status) {
        $stmt = $this->pdo->prepare("
            UPDATE limit_orders 
            SET status = ? 
            WHERE id = ? AND status = 'active'
        ");
        return $stmt->execute([$status, $id]);
    }
    
    /**
     * Исполнение ордера
     */
    public function execute($id) {
        $stmt = $this->pdo->prepare("
            UPDATE limit_orders 
            SET status = 'executed', executed_at = NOW() 
            WHERE id = ? AND status = 'active'
        ");
        return $stmt->execute([$id]);
    }
}