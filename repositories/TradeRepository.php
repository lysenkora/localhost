<?php
// ============================================================================
// РЕПОЗИТОРИЙ СДЕЛОК
// ============================================================================

class TradeRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Добавление сделки
     */
    public function create($type, $platformId, $fromPlatformId, $assetId, $quantity, $price, 
                           $priceCurrency, $commission = 0, $commissionCurrency = null, 
                           $network = null, $date, $notes = '') {
        
        $this->pdo->beginTransaction();
        
        try {
            // Добавляем запись о сделке
            $stmt = $this->pdo->prepare("
                INSERT INTO trades (
                    operation_type, asset_id, platform_id, from_platform_id, quantity, price, 
                    price_currency, commission, commission_currency, network, 
                    operation_date, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $type, $assetId, $platformId, $fromPlatformId, $quantity, $price,
                $priceCurrency, $commission, $commissionCurrency, $network, $date, $notes
            ]);
            
            $tradeId = $this->pdo->lastInsertId();
            
            // Обработка портфеля
            if ($type == 'buy') {
                $this->processBuyTrade($assetId, $platformId, $fromPlatformId, $quantity, $price, $priceCurrency);
            } elseif ($type == 'sell') {
                $this->processSellTrade($assetId, $platformId, $quantity, $price, $priceCurrency);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => ''];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Обработка покупки
     */
    private function processBuyTrade($assetId, $platformId, $fromPlatformId, $quantity, $price, $priceCurrency) {
        // Списываем средства
        $stmt = $this->pdo->prepare("
            SELECT id, quantity FROM portfolio 
            WHERE asset_id = (SELECT id FROM assets WHERE symbol = ?) 
            AND platform_id = ?
        ");
        $stmt->execute([$priceCurrency, $fromPlatformId]);
        $paymentAsset = $stmt->fetch();
        
        $totalCost = $quantity * $price;
        
        if (!$paymentAsset || $paymentAsset['quantity'] < $totalCost) {
            throw new Exception("Недостаточно средств для покупки");
        }
        
        $newQuantity = $paymentAsset['quantity'] - $totalCost;
        if ($newQuantity > 0) {
            $stmt = $this->pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $paymentAsset['id']]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM portfolio WHERE id = ?");
            $stmt->execute([$paymentAsset['id']]);
        }
        
        // Добавляем купленный актив
        $stmt = $this->pdo->prepare("
            SELECT id, quantity, average_buy_price FROM portfolio 
            WHERE asset_id = ? AND platform_id = ?
        ");
        $stmt->execute([$assetId, $platformId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $newQuantity = $existing['quantity'] + $quantity;
            $newAvgPrice = (($existing['quantity'] * $existing['average_buy_price']) + ($quantity * $price)) / $newQuantity;
            $stmt = $this->pdo->prepare("UPDATE portfolio SET quantity = ?, average_buy_price = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $newAvgPrice, $existing['id']]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO portfolio (asset_id, platform_id, quantity, average_buy_price, currency_code)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$assetId, $platformId, $quantity, $price, $priceCurrency]);
        }
    }
    
    /**
     * Обработка продажи
     */
    private function processSellTrade($assetId, $platformId, $quantity, $price, $priceCurrency) {
        // Списываем актив
        $stmt = $this->pdo->prepare("
            SELECT id, quantity FROM portfolio 
            WHERE asset_id = ? AND platform_id = ?
        ");
        $stmt->execute([$assetId, $platformId]);
        $existing = $stmt->fetch();
        
        if (!$existing || $existing['quantity'] < $quantity) {
            throw new Exception("Недостаточно актива для продажи");
        }
        
        $newQuantity = $existing['quantity'] - $quantity;
        if ($newQuantity > 0) {
            $stmt = $this->pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $existing['id']]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM portfolio WHERE id = ?");
            $stmt->execute([$existing['id']]);
        }
        
        // Добавляем полученные средства
        $totalIncome = $quantity * $price;
        $stmt = $this->pdo->prepare("
            INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
            VALUES ((SELECT id FROM assets WHERE symbol = ?), ?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->execute([$priceCurrency, $platformId, $totalIncome, $priceCurrency]);
    }
    
    /**
     * Получение истории покупок актива на площадке
     */
    public function getPurchaseHistory($assetId, $platformId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                t.operation_date,
                t.quantity,
                t.price,
                t.price_currency,
                p.name as platform_name,
                t.notes
            FROM trades t
            JOIN platforms p ON t.platform_id = p.id
            WHERE t.asset_id = ? 
                AND t.platform_id = ?
                AND t.operation_type = 'buy'
            ORDER BY t.operation_date DESC
        ");
        $stmt->execute([$assetId, $platformId]);
        $purchases = $stmt->fetchAll();
        
        $stmt = $this->pdo->prepare("
            SELECT quantity, average_buy_price
            FROM portfolio
            WHERE asset_id = ? AND platform_id = ?
        ");
        $stmt->execute([$assetId, $platformId]);
        $current = $stmt->fetch();
        
        return [
            'purchases' => $purchases,
            'current_quantity' => $current ? (float)$current['quantity'] : 0,
            'avg_buy_price' => $current ? (float)$current['average_buy_price'] : 0
        ];
    }
    
    /**
     * Получение статистики по сделкам
     */
    public function getStats() {
        $stmt = $this->pdo->query("
            SELECT 
                COALESCE(SUM(CASE WHEN operation_type = 'buy' THEN quantity * price END), 0) as total_buy_amount,
                COALESCE(SUM(CASE WHEN operation_type = 'sell' THEN quantity * price END), 0) as total_sell_amount,
                COUNT(CASE WHEN operation_type = 'buy' THEN 1 END) as buy_count,
                COUNT(CASE WHEN operation_type = 'sell' THEN 1 END) as sell_count
            FROM trades
        ");
        return $stmt->fetch();
    }
}