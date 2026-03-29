<?php
// ============================================================================
// РЕПОЗИТОРИЙ ОПЕРАЦИЙ (ОБЪЕДИНЯЕТ ВСЕ ТИПЫ)
// ============================================================================

class OperationRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Получение всех операций с фильтрацией и пагинацией
     */
    public function getAll($filters = [], $page = 1, $perPage = 20) {
        $baseSql = $this->getBaseSql();
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['type']) && $filters['type'] != 'all') {
            $whereConditions[] = "operation_type LIKE ?";
            $params[] = $filters['type'] . '%';
        }
        
        if (!empty($filters['platform_id'])) {
            $stmt = $this->pdo->prepare("SELECT name FROM platforms WHERE id = ?");
            $stmt->execute([$filters['platform_id']]);
            $platformName = $stmt->fetchColumn();
            if ($platformName) {
                $whereConditions[] = "platform LIKE ?";
                $params[] = '%' . $platformName . '%';
            }
        }
        
        if (!empty($filters['asset_id'])) {
            $stmt = $this->pdo->prepare("SELECT symbol FROM assets WHERE id = ?");
            $stmt->execute([$filters['asset_id']]);
            $assetSymbol = $stmt->fetchColumn();
            if ($assetSymbol) {
                $whereConditions[] = "(currency = ? OR short_description LIKE ?)";
                $params[] = $assetSymbol;
                $params[] = '%' . $assetSymbol . '%';
            }
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereSql = empty($whereConditions) ? "" : " AND " . implode(" AND ", $whereConditions);
        
        $offset = ($page - 1) * $perPage;
        
        $sql = $baseSql . $whereSql . " ORDER BY date DESC, operation_id DESC LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $operations = $stmt->fetchAll();
        
        // Подсчет общего количества
        $countSql = "SELECT COUNT(*) as total FROM (" . $baseSql . $whereSql . ") as count_query";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        return [
            'operations' => $this->groupOperations($operations),
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Базовый SQL для получения операций
     */
    private function getBaseSql() {
        return "
            SELECT * FROM (
                SELECT 
                    'buy_asset' as operation_type,
                    t.operation_date as date,
                    CONCAT('Покупка ', a.symbol) as short_description,
                    t.quantity as amount,
                    0 as amount_out,
                    a.symbol as currency,
                    p.name as platform,
                    'in' as direction,
                    t.id as operation_id,
                    'trade' as source_table,
                    t.notes,
                    t.price,
                    t.price_currency,
                    t.commission,
                    t.commission_currency
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms p ON t.platform_id = p.id
                WHERE t.operation_type = 'buy'
                
                UNION ALL
                
                SELECT 
                    'buy_payment' as operation_type,
                    t.operation_date as date,
                    CONCAT('Оплата ', a.symbol) as short_description,
                    0 as amount,
                    t.quantity * t.price as amount_out,
                    t.price_currency as currency,
                    fp.name as platform,
                    'out' as direction,
                    t.id as operation_id,
                    'trade' as source_table,
                    t.notes,
                    t.price,
                    t.price_currency,
                    t.commission,
                    t.commission_currency
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms fp ON t.from_platform_id = fp.id
                WHERE t.operation_type = 'buy'
                
                UNION ALL
                
                SELECT 
                    'sell_asset' as operation_type,
                    t.operation_date as date,
                    CONCAT('Продажа ', a.symbol) as short_description,
                    0 as amount,
                    t.quantity as amount_out,
                    a.symbol as currency,
                    p.name as platform,
                    'out' as direction,
                    t.id as operation_id,
                    'trade' as source_table,
                    t.notes,
                    t.price,
                    t.price_currency,
                    t.commission,
                    t.commission_currency
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms p ON t.platform_id = p.id
                WHERE t.operation_type = 'sell'
                
                UNION ALL
                
                SELECT 
                    'sell_income' as operation_type,
                    t.operation_date as date,
                    CONCAT('Доход от продажи ', a.symbol) as short_description,
                    t.quantity * t.price as amount,
                    0 as amount_out,
                    t.price_currency as currency,
                    p.name as platform,
                    'in' as direction,
                    t.id as operation_id,
                    'trade' as source_table,
                    t.notes,
                    t.price,
                    t.price_currency,
                    t.commission,
                    t.commission_currency
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms p ON t.platform_id = p.id
                WHERE t.operation_type = 'sell'
                
                UNION ALL
                
                SELECT 
                    'deposit' as operation_type,
                    d.deposit_date as date,
                    CONCAT('Пополнение ', d.currency_code) as short_description,
                    d.amount as amount,
                    0 as amount_out,
                    d.currency_code as currency,
                    p.name as platform,
                    'in' as direction,
                    d.id as operation_id,
                    'deposit' as source_table,
                    d.notes,
                    NULL as price,
                    NULL as price_currency,
                    NULL as commission,
                    NULL as commission_currency
                FROM deposits d
                JOIN platforms p ON d.platform_id = p.id
                
                UNION ALL
                
                SELECT 
                    'transfer_out' as operation_type,
                    t.transfer_date as date,
                    CONCAT('Исходящий перевод ', a.symbol) as short_description,
                    0 as amount,
                    t.quantity as amount_out,
                    a.symbol as currency,
                    CONCAT(fp.name, ' → ', tp.name) as platform,
                    'out' as direction,
                    t.id as operation_id,
                    'transfer' as source_table,
                    t.notes,
                    NULL as price,
                    NULL as price_currency,
                    t.commission,
                    t.commission_currency
                FROM transfers t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms fp ON t.from_platform_id = fp.id
                JOIN platforms tp ON t.to_platform_id = tp.id
                
                UNION ALL
                
                SELECT 
                    'transfer_in' as operation_type,
                    t.transfer_date as date,
                    CONCAT('Входящий перевод ', a.symbol) as short_description,
                    t.quantity as amount,
                    0 as amount_out,
                    a.symbol as currency,
                    CONCAT(fp.name, ' → ', tp.name) as platform,
                    'in' as direction,
                    t.id as operation_id,
                    'transfer' as source_table,
                    t.notes,
                    NULL as price,
                    NULL as price_currency,
                    t.commission,
                    t.commission_currency
                FROM transfers t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms fp ON t.from_platform_id = fp.id
                JOIN platforms tp ON t.to_platform_id = tp.id
            ) AS all_operations
        ";
    }
    
    /**
     * Группировка операций (объединение buy_asset + buy_payment и т.д.)
     */
    private function groupOperations($operations) {
        $grouped = [];
        
        foreach ($operations as $op) {
            if ($op['operation_type'] == 'buy_asset') {
                $grouped[$op['operation_id']]['buy'] = $op;
            } elseif ($op['operation_type'] == 'buy_payment') {
                $grouped[$op['operation_id']]['payment'] = $op;
            } elseif ($op['operation_type'] == 'sell_asset') {
                $grouped[$op['operation_id']]['sell'] = $op;
            } elseif ($op['operation_type'] == 'sell_income') {
                $grouped[$op['operation_id']]['income'] = $op;
            } else {
                $grouped[$op['operation_id']]['other'] = $op;
            }
        }
        
        $result = [];
        foreach ($grouped as $group) {
            if (isset($group['buy']) && isset($group['payment'])) {
                $result[] = $group['buy'];
            } elseif (isset($group['sell']) && isset($group['income'])) {
                $result[] = $group['sell'];
            } elseif (isset($group['other'])) {
                $result[] = $group['other'];
            }
        }
        
        return $result;
    }
    
    /**
     * Удаление операции
     */
    public function delete($operationId, $sourceTable) {
        $this->pdo->beginTransaction();
        
        try {
            if ($sourceTable === 'trade') {
                $this->deleteTrade($operationId);
            } elseif ($sourceTable === 'deposit') {
                $this->deleteDeposit($operationId);
            } elseif ($sourceTable === 'transfer') {
                $this->deleteTransfer($operationId);
            } else {
                throw new Exception('Неизвестный тип операции');
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Операция удалена'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function deleteTrade($operationId) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, a.symbol, a.type as asset_type 
            FROM trades t
            JOIN assets a ON t.asset_id = a.id
            WHERE t.id = ?
        ");
        $stmt->execute([$operationId]);
        $operation = $stmt->fetch();
        
        if (!$operation) return;
        
        if ($operation['operation_type'] === 'buy') {
            $this->reverseBuyTrade($operation);
        } elseif ($operation['operation_type'] === 'sell') {
            $this->reverseSellTrade($operation);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM trades WHERE id = ?");
        $stmt->execute([$operationId]);
    }
    
    private function reverseBuyTrade($operation) {
        // Возвращаем списанные средства
        $totalCost = $operation['quantity'] * $operation['price'];
        $stmt = $this->pdo->prepare("
            INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
            VALUES ((SELECT id FROM assets WHERE symbol = ?), ?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->execute([$operation['price_currency'], $operation['from_platform_id'], $totalCost, $operation['price_currency']]);
        
        // Уменьшаем купленный актив
        $stmt = $this->pdo->prepare("
            SELECT id, quantity FROM portfolio 
            WHERE asset_id = ? AND platform_id = ?
        ");
        $stmt->execute([$operation['asset_id'], $operation['platform_id']]);
        $boughtAsset = $stmt->fetch();
        
        if ($boughtAsset) {
            $newQuantity = $boughtAsset['quantity'] - $operation['quantity'];
            if ($newQuantity > 0) {
                $stmt = $this->pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $boughtAsset['id']]);
            } else {
                $stmt = $this->pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                $stmt->execute([$boughtAsset['id']]);
            }
        }
    }
    
    private function reverseSellTrade($operation) {
        // Возвращаем проданный актив
        $stmt = $this->pdo->prepare("
            INSERT INTO portfolio (asset_id, platform_id, quantity, average_buy_price, currency_code)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->execute([
            $operation['asset_id'], $operation['platform_id'], 
            $operation['quantity'], $operation['price'], $operation['price_currency']
        ]);
        
        // Уменьшаем полученные средства
        $totalIncome = $operation['quantity'] * $operation['price'];
        $stmt = $this->pdo->prepare("
            SELECT id, quantity FROM portfolio 
            WHERE asset_id = (SELECT id FROM assets WHERE symbol = ?) 
            AND platform_id = ?
        ");
        $stmt->execute([$operation['price_currency'], $operation['platform_id']]);
        $incomeAsset = $stmt->fetch();
        
        if ($incomeAsset) {
            $newQuantity = $incomeAsset['quantity'] - $totalIncome;
            if ($newQuantity > 0) {
                $stmt = $this->pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $incomeAsset['id']]);
            } else {
                $stmt = $this->pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                $stmt->execute([$incomeAsset['id']]);
            }
        }
    }
    
    private function deleteDeposit($operationId) {
        $stmt = $this->pdo->prepare("SELECT * FROM deposits WHERE id = ?");
        $stmt->execute([$operationId]);
        $deposit = $stmt->fetch();
        
        if ($deposit) {
            // Уменьшаем баланс в портфеле
            $stmt = $this->pdo->prepare("
                SELECT id, quantity FROM portfolio 
                WHERE asset_id = (SELECT id FROM assets WHERE symbol = ?) 
                AND platform_id = ?
            ");
            $stmt->execute([$deposit['currency_code'], $deposit['platform_id']]);
            $portfolio = $stmt->fetch();
            
            if ($portfolio) {
                $newQuantity = $portfolio['quantity'] - $deposit['amount'];
                if ($newQuantity > 0) {
                    $stmt = $this->pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                    $stmt->execute([$newQuantity, $portfolio['id']]);
                } else {
                    $stmt = $this->pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                    $stmt->execute([$portfolio['id']]);
                }
            }
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM deposits WHERE id = ?");
        $stmt->execute([$operationId]);
    }
    
    private function deleteTransfer($operationId) {
        $stmt = $this->pdo->prepare("SELECT * FROM transfers WHERE id = ?");
        $stmt->execute([$operationId]);
        $transfer = $stmt->fetch();
        
        if ($transfer) {
            // Уменьшаем количество у получателя
            $stmt = $this->pdo->prepare("
                SELECT id, quantity FROM portfolio 
                WHERE asset_id = ? AND platform_id = ?
            ");
            $stmt->execute([$transfer['asset_id'], $transfer['to_platform_id']]);
            $toPortfolio = $stmt->fetch();
            
            if ($toPortfolio) {
                $newQuantity = $toPortfolio['quantity'] - $transfer['quantity'];
                if ($newQuantity > 0) {
                    $stmt = $this->pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                    $stmt->execute([$newQuantity, $toPortfolio['id']]);
                } else {
                    $stmt = $this->pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                    $stmt->execute([$toPortfolio['id']]);
                }
            }
            
            // Возвращаем количество отправителю
            $stmt = $this->pdo->prepare("
                INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
                VALUES (?, ?, ?, (SELECT currency_code FROM assets WHERE id = ?))
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmt->execute([$transfer['asset_id'], $transfer['from_platform_id'], $transfer['quantity'], $transfer['asset_id']]);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM transfers WHERE id = ?");
        $stmt->execute([$operationId]);
    }
}