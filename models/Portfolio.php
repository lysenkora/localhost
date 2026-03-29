<?php
require_once __DIR__ . '/BaseModel.php';

class Portfolio extends BaseModel {
    protected $table = 'portfolio';
    
    public function getUserPortfolio() {
        $stmt = $this->pdo->query("
            SELECT 
                a.id,
                a.symbol,
                a.name,
                a.type,
                a.currency_code as asset_currency,
                SUM(p.quantity) as total_quantity,
                AVG(p.average_buy_price) as avg_price,
                GROUP_CONCAT(DISTINCT p.platform_id) as platform_ids
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            WHERE p.quantity > 0
            GROUP BY a.id, a.symbol, a.name, a.type, a.currency_code
            ORDER BY total_quantity DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function getByPlatform($platformId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.id as asset_id,
                a.symbol,
                a.name,
                a.type,
                p.quantity,
                p.average_buy_price,
                p.currency_code
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            WHERE p.platform_id = ? AND p.quantity > 0
            ORDER BY p.quantity DESC
        ");
        $stmt->execute([$platformId]);
        return $stmt->fetchAll();
    }
    
    public function updateQuantity($assetId, $platformId, $quantity, $avgPrice = null) {
        $stmt = $this->pdo->prepare("
            SELECT id, quantity, average_buy_price FROM portfolio 
            WHERE asset_id = ? AND platform_id = ?
        ");
        $stmt->execute([$assetId, $platformId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $newQuantity = $existing['quantity'] + $quantity;
            
            if ($avgPrice && $existing['average_buy_price']) {
                $newAvgPrice = (($existing['quantity'] * $existing['average_buy_price']) + ($quantity * $avgPrice)) / $newQuantity;
            } else {
                $newAvgPrice = $existing['average_buy_price'];
            }
            
            if ($newQuantity <= 0) {
                return $this->delete($existing['id']);
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE portfolio 
                SET quantity = ?, average_buy_price = ? 
                WHERE id = ?
            ");
            return $stmt->execute([$newQuantity, $newAvgPrice, $existing['id']]);
        } elseif ($quantity > 0) {
            $stmt = $this->pdo->prepare("
                INSERT INTO portfolio (asset_id, platform_id, quantity, average_buy_price, currency_code)
                VALUES (?, ?, ?, ?, (SELECT currency_code FROM assets WHERE id = ?))
            ");
            return $stmt->execute([$assetId, $platformId, $quantity, $avgPrice, $assetId]);
        }
        
        return true;
    }
    
    public function getPlatformDistribution() {
        $stmt = $this->pdo->query("
            SELECT 
                p.id as platform_id,
                p.name as platform_name,
                p.type as platform_type,
                COALESCE(SUM(
                    CASE 
                        WHEN a.symbol = 'RUB' THEN pl.quantity / (SELECT rate FROM exchange_rates WHERE from_currency = 'USD' AND to_currency = 'RUB' ORDER BY date DESC LIMIT 1)
                        WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN pl.quantity
                        ELSE pl.quantity * COALESCE(pl.average_buy_price, 0)
                    END
                ), 0) as total_value_usd
            FROM platforms p
            INNER JOIN portfolio pl ON p.id = pl.platform_id AND pl.quantity > 0
            INNER JOIN assets a ON pl.asset_id = a.id
            WHERE p.is_active = 1
            GROUP BY p.id, p.name, p.type
            HAVING total_value_usd > 0
            ORDER BY total_value_usd DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function getTotalValue() {
        // Получаем курс USD/RUB
        $stmt = $this->pdo->query("
            SELECT rate FROM exchange_rates 
            WHERE from_currency = 'USD' AND to_currency = 'RUB' 
            ORDER BY date DESC LIMIT 1
        ");
        $rate_data = $stmt->fetch();
        $usd_rub_rate = $rate_data ? (float)$rate_data['rate'] : 92.50;
        
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN a.symbol = 'RUB' THEN p.quantity / {$usd_rub_rate}
                    WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN p.quantity
                    ELSE p.quantity * COALESCE(p.average_buy_price, 0)
                END
            ), 0) as total
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            WHERE p.quantity > 0
        ");
        $result = $stmt->fetch();
        return (float)$result['total'];
    }
}