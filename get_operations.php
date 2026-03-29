<?php
// ============================================================================
// ПОДКЛЮЧЕНИЕ К БД
// ============================================================================

header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'investment_portfolio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

// ============================================================================
// ПОЛУЧЕНИЕ ПАРАМЕТРОВ
// ============================================================================

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$offset = ($page - 1) * $per_page;

// ============================================================================
// ПОЛУЧЕНИЕ ОБЩЕГО КОЛИЧЕСТВА ОПЕРАЦИЙ
// ============================================================================

try {
    // Считаем общее количество операций
    $stmt = $pdo->query("
        SELECT COUNT(*) as total FROM (
            SELECT id FROM trades WHERE operation_type = 'buy'
            UNION ALL
            SELECT id FROM trades WHERE operation_type = 'buy'
            UNION ALL
            SELECT id FROM trades WHERE operation_type = 'sell'
            UNION ALL
            SELECT id FROM trades WHERE operation_type = 'sell'
            UNION ALL
            SELECT id FROM deposits
            UNION ALL
            SELECT id FROM transfers
            UNION ALL
            SELECT id FROM transfers
        ) as ops
    ");
    $total = $stmt->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
    // Получаем операции для текущей страницы
    $stmt = $pdo->prepare("
        (SELECT 
            'buy_asset' as operation_type,
            t.operation_date as date,
            CONCAT('+', 
                CASE 
                    WHEN t.price_currency IN ('RUB', 'USD', 'EUR') THEN FORMAT(t.quantity, 0)
                    ELSE FORMAT(t.quantity, 4)
                END, 
                ' ', a.symbol) as description,
            t.quantity as amount,
            0 as amount_out,
            a.symbol as currency,
            p.name as platform,
            'in' as direction,
            t.id as operation_id,
            t.price,
            t.price_currency as price_currency,
            0 as commission,
            NULL as commission_currency,
            t.operation_date as sort_date
        FROM trades t
        JOIN assets a ON t.asset_id = a.id
        JOIN platforms p ON t.platform_id = p.id
        WHERE t.operation_type = 'buy')
        
        UNION ALL
        
        (SELECT 
            'buy_payment' as operation_type,
            t.operation_date as date,
            CONCAT('-', 
                CASE 
                    WHEN t.price_currency IN ('RUB', 'USD', 'EUR') THEN FORMAT(t.quantity * t.price, 0)
                    ELSE FORMAT(t.quantity * t.price, 2)
                END, 
                ' ', t.price_currency) as description,
            0 as amount,
            t.quantity * t.price as amount_out,
            t.price_currency as currency,
            fp.name as platform,
            'out' as direction,
            t.id as operation_id,
            t.price,
            t.price_currency as price_currency,
            0 as commission,
            NULL as commission_currency,
            t.operation_date as sort_date
        FROM trades t
        JOIN assets a ON t.asset_id = a.id
        LEFT JOIN platforms fp ON t.from_platform_id = fp.id
        WHERE t.operation_type = 'buy' AND t.from_platform_id IS NOT NULL)
        
        UNION ALL
        
        (SELECT 
            'sell_asset' as operation_type,
            t.operation_date as date,
            CONCAT('-', 
                CASE 
                    WHEN t.price_currency IN ('RUB', 'USD', 'EUR') THEN FORMAT(t.quantity, 0)
                    ELSE FORMAT(t.quantity, 4)
                END, 
                ' ', a.symbol) as description,
            0 as amount,
            t.quantity as amount_out,
            a.symbol as currency,
            p.name as platform,
            'out' as direction,
            t.id as operation_id,
            t.price,
            t.price_currency as price_currency,
            0 as commission,
            NULL as commission_currency,
            t.operation_date as sort_date
        FROM trades t
        JOIN assets a ON t.asset_id = a.id
        JOIN platforms p ON t.platform_id = p.id
        WHERE t.operation_type = 'sell')
        
        UNION ALL
        
        (SELECT 
            'sell_income' as operation_type,
            t.operation_date as date,
            CONCAT('+', 
                CASE 
                    WHEN t.price_currency IN ('RUB', 'USD', 'EUR') THEN FORMAT(t.quantity * t.price, 0)
                    ELSE FORMAT(t.quantity * t.price, 2)
                END, 
                ' ', t.price_currency) as description,
            t.quantity * t.price as amount,
            0 as amount_out,
            t.price_currency as currency,
            p.name as platform,
            'in' as direction,
            t.id as operation_id,
            t.price,
            t.price_currency as price_currency,
            0 as commission,
            NULL as commission_currency,
            t.operation_date as sort_date
        FROM trades t
        JOIN assets a ON t.asset_id = a.id
        JOIN platforms p ON t.platform_id = p.id
        WHERE t.operation_type = 'sell')
        
        UNION ALL
        
        (SELECT 
            'deposit' as operation_type,
            d.deposit_date as date,
            CONCAT('+', 
            CASE 
                WHEN d.currency_code IN ('RUB', 'USD', 'EUR') THEN REPLACE(FORMAT(d.amount, 0), ',', ' ')
                ELSE REPLACE(FORMAT(d.amount, 2), ',', '.')
            END, 
            ' ', d.currency_code) as description,
            d.amount as amount,
            0 as amount_out,
            d.currency_code as currency,
            p.name as platform,
            'in' as direction,
            d.id as operation_id,
            NULL as price,
            NULL as price_currency,
            0 as commission,
            NULL as commission_currency,
            d.deposit_date as sort_date
        FROM deposits d
        JOIN platforms p ON d.platform_id = p.id)
        
        UNION ALL
        
        (SELECT 
            'transfer_out' as operation_type,
            t.transfer_date as date,
            CONCAT('→ ', tp.name, ': -', t.quantity, ' ', a.symbol) as description,
            0 as amount,
            t.quantity as amount_out,
            a.symbol as currency,
            CONCAT(fp.name, ' → ', tp.name) as platform,
            'out' as direction,
            t.id as operation_id,
            NULL as price,
            NULL as price_currency,
            COALESCE(t.commission, 0) as commission,
            t.commission_currency,
            t.transfer_date as sort_date
        FROM transfers t
        JOIN assets a ON t.asset_id = a.id
        JOIN platforms fp ON t.from_platform_id = fp.id
        JOIN platforms tp ON t.to_platform_id = tp.id)
        
        UNION ALL
        
        (SELECT 
            'transfer_in' as operation_type,
            t.transfer_date as date,
            CONCAT('← ', fp.name, ': +', t.quantity, ' ', a.symbol) as description,
            t.quantity as amount,
            0 as amount_out,
            a.symbol as currency,
            CONCAT(fp.name, ' → ', tp.name) as platform,
            'in' as direction,
            t.id as operation_id,
            NULL as price,
            NULL as price_currency,
            COALESCE(t.commission, 0) as commission,
            t.commission_currency,
            t.transfer_date as sort_date
        FROM transfers t
        JOIN assets a ON t.asset_id = a.id
        JOIN platforms fp ON t.from_platform_id = fp.id
        JOIN platforms tp ON t.to_platform_id = tp.id)
        
        ORDER BY sort_date DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $operations = $stmt->fetchAll();
    
    // Формируем ответ
    echo json_encode([
        'success' => true,
        'operations' => $operations,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total' => $total,
            'per_page' => $per_page,
            'from' => $offset + 1,
            'to' => min($offset + $per_page, $total),
            'has_previous' => $page > 1,
            'has_next' => $page < $total_pages
        ]
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>