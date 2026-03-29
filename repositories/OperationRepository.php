<?php
class OperationRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getRecent($limit = 10) {
        $stmt = $this->pdo->prepare("
            (SELECT 'trade' as type, id, operation_date as date, 
                    CONCAT('Сделка ', a.symbol) as description,
                    quantity, price, price_currency
             FROM trades t
             JOIN assets a ON t.asset_id = a.id
             ORDER BY operation_date DESC LIMIT :limit)
            UNION ALL
            (SELECT 'deposit', id, deposit_date, 
                    CONCAT('Пополнение ', currency_code),
                    amount, NULL, NULL
             FROM deposits
             ORDER BY deposit_date DESC LIMIT :limit)
            UNION ALL
            (SELECT 'transfer', id, transfer_date, 
                    CONCAT('Перевод ', a.symbol),
                    quantity, NULL, NULL
             FROM transfers t
             JOIN assets a ON t.asset_id = a.id
             ORDER BY transfer_date DESC LIMIT :limit)
            ORDER BY date DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getPaginated($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->pdo->prepare("
            (SELECT 'trade' as type, id, operation_date as date, 
                    CONCAT('Сделка ', a.symbol) as description,
                    quantity, price, price_currency
             FROM trades t
             JOIN assets a ON t.asset_id = a.id
             ORDER BY operation_date DESC LIMIT :limit OFFSET :offset)
            UNION ALL
            (SELECT 'deposit', id, deposit_date, 
                    CONCAT('Пополнение ', currency_code),
                    amount, NULL, NULL
             FROM deposits
             ORDER BY deposit_date DESC LIMIT :limit OFFSET :offset)
            UNION ALL
            (SELECT 'transfer', id, transfer_date, 
                    CONCAT('Перевод ', a.symbol),
                    quantity, NULL, NULL
             FROM transfers t
             JOIN assets a ON t.asset_id = a.id
             ORDER BY transfer_date DESC LIMIT :limit OFFSET :offset)
            ORDER BY date DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getTotalCount() {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as total FROM (
                SELECT id FROM trades
                UNION ALL
                SELECT id FROM deposits
                UNION ALL
                SELECT id FROM transfers
            ) as ops
        ");
        $result = $stmt->fetch();
        return (int)$result['total'];
    }
}