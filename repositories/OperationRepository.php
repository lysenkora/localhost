<?php
class OperationRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAll($filters = [], $limit = 20, $offset = 0) {
        $sql = $this->buildBaseQuery();
        $params = [];
        
        if (!empty($filters)) {
            $sql .= " AND " . $this->buildWhereClause($filters, $params);
        }
        
        $sql .= " ORDER BY sort_date DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $operations = $stmt->fetchAll();
        return $this->groupOperations($operations);
    }
    
    public function getTotal($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM ({$this->buildBaseQuery()}) as ops";
        $params = [];
        
        if (!empty($filters)) {
            $sql .= " WHERE " . $this->buildWhereClause($filters, $params);
        }
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetch()['total'];
    }
    
    public function getRecent($limit = 5) {
        return $this->getAll([], $limit, 0);
    }
    
    private function buildBaseQuery() {
        return "
            SELECT * FROM (
                SELECT 
                    'buy_asset' as operation_type,
                    t.operation_date as date,
                    CONCAT('+', FORMAT(t.quantity, 4), ' ', a.symbol) as description,
                    t.quantity as amount,
                    0 as amount_out,
                    a.symbol as currency,
                    p.name as platform,
                    'in' as direction,
                    t.id as operation_id,
                    'trade' as source_table,
                    t.price,
                    t.price_currency,
                    t.commission,
                    t.commission_currency,
                    t.operation_date as sort_date
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms p ON t.platform_id = p.id
                WHERE t.operation_type = 'buy'
                
                UNION ALL
                
                SELECT 
                    'buy_payment' as operation_type,
                    t.operation_date as date,
                    CONCAT('-', FORMAT(t.quantity * t.price, 2), ' ', t.price_currency) as description,
                    0 as amount,
                    t.quantity * t.price as amount_out,
                    t.price_currency as currency,
                    fp.name as platform,
                    'out' as direction,
                    t.id as operation_id,
                    'trade' as source_table,
                    t.price,
                    t.price_currency,
                    t.commission,
                    t.commission_currency,
                    t.operation_date as sort_date
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms fp ON t.from_platform_id = fp.id
                WHERE t.operation_type = 'buy' AND t.from_platform_id IS NOT NULL
                
                UNION ALL
                
                SELECT 
                    'sell_asset' as operation_type,
                    t.operation_date as date,
                    CONCAT('-', FORMAT(t.quantity, 4), ' ', a.symbol) as description,
                    0 as amount,
                    t.quantity as amount_out,
                    a.symbol as currency,
                    p.name as platform,
                    'out' as direction,
                    t.id as operation_id,
                    'trade' as source_table,
                    t.price,
                    t.price_currency,
                    t.commission,
                    t.commission_currency,
                    t.operation_date as sort_date
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms p ON t.platform_id = p.id
                WHERE t.operation_type = 'sell'
                
                UNION ALL
                
                SELECT 
                    'sell_income' as operation_type,
                    t.operation_date as date,
                    CONCAT('+', FORMAT(t.quantity * t.price, 2), ' ', t.price_currency) as description,
                    t.quantity * t.price as amount,
                    0 as amount_out,
                    t.price_currency as currency,
                    p.name as platform,
                    'in' as direction,
                    t.id as operation_id,
                    'trade' as source_table,
                    t.price,
                    t.price_currency,
                    t.commission,
                    t.commission_currency,
                    t.operation_date as sort_date
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms p ON t.platform_id = p.id
                WHERE t.operation_type = 'sell'
                
                UNION ALL
                
                SELECT 
                    'deposit' as operation_type,
                    d.deposit_date as date,
                    CONCAT('+', FORMAT(d.amount, 2), ' ', d.currency_code) as description,
                    d.amount as amount,
                    0 as amount_out,
                    d.currency_code as currency,
                    p.name as platform,
                    'in' as direction,
                    d.id as operation_id,
                    'deposit' as source_table,
                    NULL as price,
                    NULL as price_currency,
                    0 as commission,
                    NULL as commission_currency,
                    d.deposit_date as sort_date
                FROM deposits d
                JOIN platforms p ON d.platform_id = p.id
                
                UNION ALL
                
                SELECT 
                    'transfer_out' as operation_type,
                    t.transfer_date as date,
                    CONCAT('→ ', tp.name, ': -', t.quantity, ' ', a.symbol) as description,
                    0 as amount,
                    t.quantity as amount_out,
                    a.symbol as currency,
                    CONCAT(fp.name, ' → ', tp.name) as platform,
                    'out' as direction,
                    t.id as operation_id,
                    'transfer' as source_table,
                    NULL as price,
                    NULL as price_currency,
                    COALESCE(t.commission, 0) as commission,
                    t.commission_currency,
                    t.transfer_date as sort_date
                FROM transfers t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms fp ON t.from_platform_id = fp.id
                JOIN platforms tp ON t.to_platform_id = tp.id
                
                UNION ALL
                
                SELECT 
                    'transfer_in' as operation_type,
                    t.transfer_date as date,
                    CONCAT('← ', fp.name, ': +', t.quantity, ' ', a.symbol) as description,
                    t.quantity as amount,
                    0 as amount_out,
                    a.symbol as currency,
                    CONCAT(fp.name, ' → ', tp.name) as platform,
                    'in' as direction,
                    t.id as operation_id,
                    'transfer' as source_table,
                    NULL as price,
                    NULL as price_currency,
                    COALESCE(t.commission, 0) as commission,
                    t.commission_currency,
                    t.transfer_date as sort_date
                FROM transfers t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms fp ON t.from_platform_id = fp.id
                JOIN platforms tp ON t.to_platform_id = tp.id
            ) as ops
        ";
    }
    
    private function buildWhereClause($filters, &$params) {
        $conditions = [];
        
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $conditions[] = "operation_type LIKE :type";
            $params[':type'] = $filters['type'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $conditions[] = "date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['platform'])) {
            $conditions[] = "platform LIKE :platform";
            $params[':platform'] = '%' . $filters['platform'] . '%';
        }
        
        if (!empty($filters['asset'])) {
            $conditions[] = "(currency = :asset OR description LIKE :asset_like)";
            $params[':asset'] = $filters['asset'];
            $params[':asset_like'] = '%' . $filters['asset'] . '%';
        }
        
        return implode(' AND ', $conditions);
    }
    
    private function groupOperations($operations) {
        $grouped = [];
        foreach ($operations as $op) {
            if (!isset($grouped[$op['operation_id']])) {
                $grouped[$op['operation_id']] = [];
            }
            $grouped[$op['operation_id']][] = $op;
        }
        
        $result = [];
        foreach ($grouped as $group) {
            $main = $group[0];
            
            if (($main['operation_type'] == 'buy_payment' && count($group) == 1) ||
                ($main['operation_type'] == 'sell_income' && count($group) == 1)) {
                continue;
            }
            
            if ($main['operation_type'] == 'buy_asset' && isset($group[1])) {
                $payment = $group[1];
                $main['display_description'] = "Куплено {$main['amount']} {$main['currency']} за {$payment['amount_out']} {$payment['currency']}";
                $main['display_details'] = "по {$main['price']} {$main['price_currency']} · {$main['platform']} ← {$payment['platform']}";
            } elseif ($main['operation_type'] == 'sell_asset' && isset($group[1])) {
                $income = $group[1];
                $main['display_description'] = "Продано {$main['amount_out']} {$main['currency']} за {$income['amount']} {$income['currency']}";
                $main['display_details'] = "по {$main['price']} {$main['price_currency']} · {$main['platform']}";
            } elseif ($main['operation_type'] == 'deposit') {
                $main['display_description'] = "Пополнение: +{$main['amount']} {$main['currency']}";
                $main['display_details'] = "{$main['platform']}";
            } elseif (strpos($main['operation_type'], 'transfer') !== false) {
                $main['display_description'] = ($main['operation_type'] == 'transfer_in' ? 'Входящий перевод: +' : 'Исходящий перевод: ') . 
                                               ($main['amount'] > 0 ? $main['amount'] : $main['amount_out']) . " {$main['currency']}";
                $main['display_details'] = "{$main['platform']}" . 
                                          ($main['commission'] > 0 ? " · комиссия {$main['commission']} {$main['commission_currency']}" : "");
            }
            
            if (isset($main['display_description'])) {
                $result[] = $main;
            }
        }
        
        usort($result, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $result;
    }
}