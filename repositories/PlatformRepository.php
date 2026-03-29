<?php
// ============================================================================
// РЕПОЗИТОРИЙ ПЛОЩАДОК
// ============================================================================

class PlatformRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Получение всех активных площадок
     */
    public function getAll($includeInactive = false) {
        $sql = "SELECT id, name, type, country FROM platforms WHERE 1=1";
        if (!$includeInactive) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY name";
        
        return $this->pdo->query($sql)->fetchAll();
    }
    
    /**
     * Получение площадки по ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM platforms WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Получение площадки по имени
     */
    public function getByName($name) {
        $stmt = $this->pdo->prepare("SELECT * FROM platforms WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch();
    }
    
    /**
     * Добавление новой площадки
     */
    public function create($name, $type, $country = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO platforms (name, type, country, is_active) 
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$name, $type, $country]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Обновление площадки
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['type'])) {
            $fields[] = "type = ?";
            $params[] = $data['type'];
        }
        if (isset($data['country'])) {
            $fields[] = "country = ?";
            $params[] = $data['country'];
        }
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = $data['is_active'];
        }
        
        if (empty($fields)) return false;
        
        $params[] = $id;
        $sql = "UPDATE platforms SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Получение распределения по площадкам
     */
    public function getDistribution($usdRubRate) {
        $stmt = $this->pdo->query("
            SELECT 
                p.id as platform_id,
                p.name as platform_name,
                p.type as platform_type,
                COALESCE(SUM(
                    CASE 
                        WHEN a.symbol = 'RUB' THEN pl.quantity / {$usdRubRate}
                        WHEN a.symbol IN ('USD', 'USDT', 'USDC') THEN pl.quantity
                        WHEN pl.average_buy_price IS NOT NULL AND pl.average_buy_price > 0 THEN
                            CASE
                                WHEN pl.currency_code = 'USD' THEN pl.quantity * pl.average_buy_price
                                WHEN pl.currency_code = 'RUB' THEN (pl.quantity * pl.average_buy_price) / {$usdRubRate}
                                ELSE pl.quantity * pl.average_buy_price
                            END
                        ELSE 0
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
    
    /**
     * Получение баланса площадки
     */
    public function getBalance($platformId) {
        $usdRubRate = getUsdRubRate($this->pdo);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                a.id as asset_id,
                a.symbol,
                a.name as asset_name,
                a.type as asset_type,
                p.quantity,
                p.average_buy_price,
                p.currency_code,
                CASE 
                    WHEN a.symbol = 'RUB' THEN p.quantity / {$usdRubRate}
                    WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN p.quantity
                    ELSE p.quantity * COALESCE(p.average_buy_price, 0)
                END as value_usd
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            WHERE p.platform_id = ? AND p.quantity > 0
            ORDER BY value_usd DESC
        ");
        $stmt->execute([$platformId]);
        $assets = $stmt->fetchAll();
        
        $totalValueUsd = array_sum(array_column($assets, 'value_usd'));
        
        return [
            'assets' => $assets,
            'total_value_usd' => $totalValueUsd,
            'total_value_rub' => $totalValueUsd * $usdRubRate
        ];
    }
}