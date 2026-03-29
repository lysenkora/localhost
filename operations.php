<?php
// ============================================================================
// ПОДКЛЮЧЕНИЕ К БД
// ============================================================================

$host = 'localhost';
$dbname = 'investment_portfolio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Получаем текущую тему из БД
$stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE setting_key = 'theme'");
$stmt->execute();
$theme_data = $stmt->fetch();
$current_theme = $theme_data ? $theme_data['setting_value'] : 'light';

// ============================================================================
// ОБРАБОТКА УДАЛЕНИЯ ОПЕРАЦИИ
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_operation') {
    $response = ['success' => false, 'message' => ''];
    
    $operation_id = isset($_POST['operation_id']) ? (int)$_POST['operation_id'] : 0;
    $operation_type = isset($_POST['operation_type']) ? $_POST['operation_type'] : '';
    $source_table = isset($_POST['source_table']) ? $_POST['source_table'] : '';
    
    if (!$operation_id || !$operation_type || !$source_table) {
        $response['message'] = 'Недостаточно данных для удаления';
        echo json_encode($response);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $table = '';
        $id_field = 'id';
        
        switch ($source_table) {
            case 'trade':
                $table = 'trades';
                break;
            case 'deposit':
                $table = 'deposits';
                break;
            case 'transfer':
                $table = 'transfers';
                break;
            default:
                throw new Exception('Неизвестный тип операции');
        }
        
        if ($source_table === 'trade') {
            $stmt = $pdo->prepare("
                SELECT t.*, a.symbol, a.type as asset_type, a.currency_code as asset_currency 
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                WHERE t.id = ?
            ");
            $stmt->execute([$operation_id]);
            $operation_data = $stmt->fetch();
            
            if (!$operation_data) {
                throw new Exception('Операция не найдена');
            }
            
            if ($operation_data['operation_type'] === 'buy') {
                $stmt = $pdo->prepare("
                    SELECT id, quantity FROM portfolio 
                    WHERE asset_id = ? AND platform_id = ?
                ");
                $stmt->execute([$operation_data['asset_id'], $operation_data['platform_id']]);
                $bought_asset = $stmt->fetch();
                
                if ($bought_asset) {
                    $new_quantity = $bought_asset['quantity'] - $operation_data['quantity'];
                    
                    if ($new_quantity > 0) {
                        $stmt = $pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                        $stmt->execute([$new_quantity, $bought_asset['id']]);
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                        $stmt->execute([$bought_asset['id']]);
                    }
                }
                
                $total_cost = $operation_data['quantity'] * $operation_data['price'];
                
                $stmt = $pdo->prepare("
                    SELECT id FROM assets WHERE symbol = ? AND (type = 'currency' OR type = 'crypto')
                ");
                $stmt->execute([$operation_data['price_currency']]);
                $payment_asset = $stmt->fetch();
                
                if ($payment_asset) {
                    $stmt = $pdo->prepare("
                        INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                    ");
                    $stmt->execute([
                        $payment_asset['id'], 
                        $operation_data['from_platform_id'], 
                        $total_cost, 
                        $operation_data['price_currency']
                    ]);
                }
                
            } elseif ($operation_data['operation_type'] === 'sell') {
                $stmt = $pdo->prepare("
                    SELECT id, quantity FROM portfolio 
                    WHERE asset_id = ? AND platform_id = ?
                ");
                $stmt->execute([$operation_data['asset_id'], $operation_data['platform_id']]);
                $sold_asset = $stmt->fetch();
                
                if ($sold_asset) {
                    $new_quantity = $sold_asset['quantity'] + $operation_data['quantity'];
                    $stmt = $pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_quantity, $sold_asset['id']]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO portfolio (asset_id, platform_id, quantity, average_buy_price, currency_code)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $operation_data['asset_id'], 
                        $operation_data['platform_id'], 
                        $operation_data['quantity'],
                        $operation_data['price'],
                        $operation_data['price_currency']
                    ]);
                }
                
                $total_income = $operation_data['quantity'] * $operation_data['price'];
                
                $stmt = $pdo->prepare("
                    SELECT id FROM assets WHERE symbol = ? AND (type = 'currency' OR type = 'crypto')
                ");
                $stmt->execute([$operation_data['price_currency']]);
                $income_asset = $stmt->fetch();
                
                if ($income_asset) {
                    $stmt = $pdo->prepare("
                        SELECT id, quantity FROM portfolio 
                        WHERE asset_id = ? AND platform_id = ?
                    ");
                    $stmt->execute([$income_asset['id'], $operation_data['platform_id']]);
                    $income_portfolio = $stmt->fetch();
                    
                    if ($income_portfolio) {
                        $new_quantity = $income_portfolio['quantity'] - $total_income;
                        
                        if ($new_quantity > 0) {
                            $stmt = $pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                            $stmt->execute([$new_quantity, $income_portfolio['id']]);
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                            $stmt->execute([$income_portfolio['id']]);
                        }
                    }
                }
            }
            
        } elseif ($source_table === 'deposit') {
            $stmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ?");
            $stmt->execute([$operation_id]);
            $deposit_data = $stmt->fetch();
            
            if ($deposit_data) {
                $stmt = $pdo->prepare("
                    SELECT id FROM assets WHERE symbol = ?
                ");
                $stmt->execute([$deposit_data['currency_code']]);
                $asset = $stmt->fetch();
                
                if ($asset) {
                    $stmt = $pdo->prepare("
                        SELECT id, quantity FROM portfolio 
                        WHERE asset_id = ? AND platform_id = ?
                    ");
                    $stmt->execute([$asset['id'], $deposit_data['platform_id']]);
                    $portfolio_item = $stmt->fetch();
                    
                    if ($portfolio_item) {
                        $new_quantity = $portfolio_item['quantity'] - $deposit_data['amount'];
                        
                        if ($new_quantity > 0) {
                            $stmt = $pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                            $stmt->execute([$new_quantity, $portfolio_item['id']]);
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                            $stmt->execute([$portfolio_item['id']]);
                        }
                    }
                }
            }
            
        } elseif ($source_table === 'transfer') {
            $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
            $stmt->execute([$operation_id]);
            $transfer_data = $stmt->fetch();
            
            if ($transfer_data) {
                $stmt = $pdo->prepare("
                    SELECT id, quantity FROM portfolio 
                    WHERE asset_id = ? AND platform_id = ?
                ");
                $stmt->execute([$transfer_data['asset_id'], $transfer_data['to_platform_id']]);
                $to_portfolio = $stmt->fetch();
                
                if ($to_portfolio) {
                    $new_quantity = $to_portfolio['quantity'] - $transfer_data['quantity'];
                    
                    if ($new_quantity > 0) {
                        $stmt = $pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                        $stmt->execute([$new_quantity, $to_portfolio['id']]);
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                        $stmt->execute([$to_portfolio['id']]);
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
                    VALUES (?, ?, ?, (SELECT currency_code FROM assets WHERE id = ?))
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ");
                $stmt->execute([
                    $transfer_data['asset_id'], 
                    $transfer_data['from_platform_id'], 
                    $transfer_data['quantity'],
                    $transfer_data['asset_id']
                ]);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $id_field = ?");
        $stmt->execute([$operation_id]);
        
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Операция успешно удалена';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = 'Ошибка при удалении: ' . $e->getMessage();
        error_log("Error deleting operation: " . $e->getMessage());
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ============================================================================
// ПАРАМЕТРЫ ФИЛЬТРАЦИИ И ПАГИНАЦИИ
// ============================================================================

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_platform = isset($_GET['platform']) ? (int)$_GET['platform'] : 0;
$filter_asset = isset($_GET['asset']) ? (int)$_GET['asset'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ============================================================================
// ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ ФИЛЬТРОВ
// ============================================================================

$platforms = $pdo->query("SELECT id, name FROM platforms WHERE is_active = TRUE ORDER BY name")->fetchAll();
$assets = $pdo->query("SELECT id, symbol, name FROM assets WHERE is_active = TRUE ORDER BY symbol")->fetchAll();

// Получаем курс USD/RUB для статистики
$stmt = $pdo->query("
    SELECT rate FROM exchange_rates 
    WHERE from_currency = 'USD' AND to_currency = 'RUB' 
    ORDER BY date DESC LIMIT 1
");
$rate_data = $stmt->fetch();
$usd_rub_rate = $rate_data ? (float)$rate_data['rate'] : 92.50;

// ============================================================================
// ПОСТРОЕНИЕ БАЗОВОГО ЗАПРОСА
// ============================================================================

$base_sql = "
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
    WHERE 1=1
";

// Добавляем условия фильтрации
$params = [];
$where_conditions = [];

if ($filter_type != 'all') {
    $where_conditions[] = "operation_type LIKE ?";
    $params[] = $filter_type . '%';
}

if ($filter_platform > 0) {
    $platform_stmt = $pdo->prepare("SELECT name FROM platforms WHERE id = ?");
    $platform_stmt->execute([$filter_platform]);
    $platform_name = $platform_stmt->fetchColumn();
    if ($platform_name) {
        $where_conditions[] = "platform LIKE ?";
        $params[] = '%' . $platform_name . '%';
    }
}

if ($filter_asset > 0) {
    $asset_stmt = $pdo->prepare("SELECT symbol FROM assets WHERE id = ?");
    $asset_stmt->execute([$filter_asset]);
    $asset_symbol = $asset_stmt->fetchColumn();
    if ($asset_symbol) {
        $where_conditions[] = "(currency = ? OR short_description LIKE ?)";
        $params[] = $asset_symbol;
        $params[] = '%' . $asset_symbol . '%';
    }
}

if ($date_from) {
    $where_conditions[] = "date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "date <= ?";
    $params[] = $date_to;
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = " AND " . implode(" AND ", $where_conditions);
}

$full_sql = $base_sql . $where_sql . " ORDER BY date DESC, operation_id DESC";

$count_sql = "SELECT COUNT(*) as total FROM (" . $full_sql . ") as count_query";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_operations = $stmt->fetch()['total'];
$total_pages = ceil($total_operations / $per_page);

$page_sql = $full_sql . " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($page_sql);
$stmt->execute($params);
$operations = $stmt->fetchAll();

// ============================================================================
// ПОЛУЧЕНИЕ СТАТИСТИКИ
// ============================================================================

$stmt = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN currency_code = 'RUB' THEN amount END), 0) as total_rub_deposits,
        COALESCE(SUM(CASE WHEN currency_code = 'USD' THEN amount END), 0) as total_usd_deposits,
        COALESCE(SUM(CASE WHEN currency_code = 'EUR' THEN amount END), 0) as total_eur_deposits,
        COUNT(*) as total_count
    FROM deposits
");
$deposit_stats = $stmt->fetch();

$stmt = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN operation_type = 'buy' THEN quantity * price END), 0) as total_buy_amount,
        COALESCE(SUM(CASE WHEN operation_type = 'sell' THEN quantity * price END), 0) as total_sell_amount,
        COUNT(CASE WHEN operation_type = 'buy' THEN 1 END) as buy_count,
        COUNT(CASE WHEN operation_type = 'sell' THEN 1 END) as sell_count
    FROM trades
");
$trade_stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История операций | Планеро.Инвестиции</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================================================
           ОСНОВНЫЕ СТИЛИ (как в index.php)
           ============================================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #2c3e50;
            line-height: 1.6;
            padding: 24px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ============================================================================
           HEADER (как в index.php)
           ============================================================================ */
        .header {
            background: white;
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
        }

        .header h1 i {
            color: #1a5cff;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f0f3f7;
            color: #6b7a8f;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }

        .back-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(26, 92, 255, 0.15);
            background: white;
            border-color: #1a5cff;
            color: #1a5cff;
        }

        /* ============================================================================
           СТАТИСТИКА
           ============================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: #6b7a8f;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-card .stat-detail {
            font-size: 13px;
            color: #6b7a8f;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #edf2f7;
        }

        .stat-note {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 5px;
        }

        /* ============================================================================
           ФИЛЬТРЫ
           ============================================================================ */
        .filters {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .filters h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
        }

        .filters h3 i {
            color: #1a5cff;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 500;
            color: #6b7a8f;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 14px;
            border: 1px solid #e0e6ed;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            background: white;
            transition: all 0.2s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #1a5cff;
            box-shadow: 0 0 0 3px rgba(26, 92, 255, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            grid-column: 1 / -1;
            justify-content: flex-end;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .filter-buttons {
                justify-content: stretch;
            }
            
            .filter-buttons .btn {
                flex: 1;
                justify-content: center;
            }
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: #1a5cff;
            color: white;
        }

        .btn-primary:hover {
            background: #0044cc;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f0f3f7;
            color: #2c3e50;
        }

        .btn-secondary:hover {
            background: #e0e6ed;
            transform: translateY(-1px);
        }

        /* ============================================================================
           ТАБЛИЦА ОПЕРАЦИЙ
           ============================================================================ */
        .operations-table-container {
            background: white;
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            overflow-x: auto;
        }

        .operations-table {
            width: 100%;
            border-collapse: collapse;
        }

        .operations-table th {
            text-align: left;
            padding: 16px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #6b7a8f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #edf2f7;
            background: white;
        }

        .operations-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            vertical-align: middle;
        }

        .operations-table tr:hover td {
            background: #f8fafd;
        }

        .operation-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-buy {
            background: #e6f7e6;
            color: #00a86b;
        }

        .badge-sell {
            background: #ffe6e6;
            color: #e53e3e;
        }

        .badge-deposit {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-transfer {
            background: #fff4e6;
            color: #ff9f4a;
        }

        .amount-positive {
            color: #00a86b;
            font-weight: 600;
        }

        .amount-negative {
            color: #e53e3e;
            font-weight: 600;
        }

        .operation-details-btn {
            color: #1a5cff;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
        }

        .operation-details-btn:hover {
            color: #0044cc;
            transform: scale(1.1);
        }

        .operation-delete-btn {
            color: #e53e3e;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
            margin-left: 12px;
        }

        .operation-delete-btn:hover {
            color: #c53030;
            transform: scale(1.1);
        }

        /* ============================================================================
           ПАГИНАЦИЯ
           ============================================================================ */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            padding: 20px;
            flex-wrap: wrap;
            border-top: 1px solid #edf2f7;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border-radius: 10px;
            background: white;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #e0e6ed;
        }

        .page-link:hover,
        .page-link.active {
            background: #1a5cff;
            color: white;
            border-color: #1a5cff;
            transform: translateY(-1px);
        }

        .page-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* ============================================================================
           МОДАЛЬНОЕ ОКНО
           ============================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7a8f;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #2c3e50;
        }

        .modal-body {
            padding: 20px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #edf2f7;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            width: 100px;
            font-weight: 500;
            color: #6b7a8f;
        }

        .detail-value {
            flex: 1;
            font-weight: 500;
            color: #2c3e50;
        }

        /* ============================================================================
           ТЕМНАЯ ТЕМА
           ============================================================================ */
        body.dark-theme {
            background: #0C0E12;
            color: #FFFFFF;
        }

        .dark-theme {
            --bg-primary: #0C0E12;
            --bg-secondary: #15181C;
            --bg-tertiary: #1E2228;
            --border-color: #2A2F36;
            --accent-primary: #2B6ED9;
            --text-primary: #FFFFFF;
            --text-secondary: #9AA5B5;
            --text-tertiary: #6B7A8F;
        }

        .dark-theme .header,
        .dark-theme .stat-card,
        .dark-theme .filters,
        .dark-theme .operations-table-container,
        .dark-theme .modal {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .dark-theme .header h1 {
            color: var(--text-primary);
        }

        .dark-theme .stat-card .stat-value {
            color: var(--text-primary);
        }

        .dark-theme .operations-table th {
            background: var(--bg-secondary);
            color: var(--text-tertiary);
            border-bottom-color: var(--border-color);
        }

        .dark-theme .operations-table td {
            border-bottom-color: var(--border-color);
            color: var(--text-secondary);
        }

        .dark-theme .operations-table tr:hover td {
            background: var(--bg-tertiary);
        }

        .dark-theme .filter-group select,
        .dark-theme .filter-group input {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .dark-theme .filter-group select:focus,
        .dark-theme .filter-group input:focus {
            border-color: var(--accent-primary);
        }

        .dark-theme .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .dark-theme .btn-secondary:hover {
            background: var(--accent-primary);
            color: white;
            border-color: var(--accent-primary);
        }

        .dark-theme .page-link {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-secondary);
        }

        .dark-theme .page-link:hover,
        .dark-theme .page-link.active {
            background: var(--accent-primary);
            color: white;
        }

        .dark-theme .detail-value {
            color: var(--text-primary);
        }

        .dark-theme .modal-header {
            border-bottom-color: var(--border-color);
        }

        .dark-theme .modal-header h2 {
            color: var(--text-primary);
        }

        .dark-theme .detail-row {
            border-bottom-color: var(--border-color);
        }

        /* ============================================================================
           АДАПТИВНОСТЬ
           ============================================================================ */
        @media (max-width: 768px) {
            body { padding: 12px; }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .operations-table th,
            .operations-table td {
                padding: 10px 8px;
                font-size: 12px;
            }
            
            .operation-badge {
                padding: 2px 6px;
                font-size: 10px;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 4px;
            }
            
            .detail-label {
                width: 100%;
            }
            
            .stat-card .stat-value {
                font-size: 22px;
            }
        }

        @media (max-width: 480px) {
            .operations-table th,
            .operations-table td {
                padding: 8px 4px;
                font-size: 11px;
            }
            
            .operation-badge {
                padding: 2px 4px;
                font-size: 9px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="<?= $current_theme === 'dark' ? 'dark-theme' : '' ?>">
    <div class="container">
        <!-- Шапка -->
        <div class="header">
            <h1>
                <i class="fas fa-history"></i>
                История операций
            </h1>
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Вернуться на дашборд
            </a>
        </div>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">
                    <i class="fas fa-chart-bar"></i> Всего операций
                </div>
                <div class="stat-value"><?= number_format($total_operations, 0, '.', ' ') ?></div>
                <div class="stat-detail">
                    Покупок: <?= $trade_stats['buy_count'] ?? 0 ?> | 
                    Продаж: <?= $trade_stats['sell_count'] ?? 0 ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <i class="fas fa-download"></i> Пополнения
                </div>
                <div class="stat-value"><?= number_format($deposit_stats['total_count'], 0, '.', ' ') ?></div>
                <div class="stat-detail">
                    RUB: <?= number_format($deposit_stats['total_rub_deposits'], 0, '.', ' ') ?> ₽<br>
                    USD: <?= number_format($deposit_stats['total_usd_deposits'], 2, '.', ' ') ?> $
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <i class="fas fa-chart-line"></i> Объем торгов
                </div>
                <div class="stat-value"><?= number_format(($trade_stats['total_buy_amount'] + $trade_stats['total_sell_amount']) / $usd_rub_rate, 0, '.', ' ') ?> $</div>
                <div class="stat-detail">
                    Покупки: $ <?= number_format($trade_stats['total_buy_amount'] / $usd_rub_rate, 0, '.', ' ') ?><br>
                    Продажи: $ <?= number_format($trade_stats['total_sell_amount'] / $usd_rub_rate, 0, '.', ' ') ?>
                </div>
                <div class="stat-note">
                    *по курсу <?= number_format($usd_rub_rate, 2, '.', ' ') ?> ₽/$
                </div>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="filters">
            <h3><i class="fas fa-filter"></i> Фильтры</h3>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Тип операции</label>
                    <select name="type">
                        <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>Все операции</option>
                        <option value="buy" <?= strpos($filter_type, 'buy') !== false ? 'selected' : '' ?>>Покупки</option>
                        <option value="sell" <?= strpos($filter_type, 'sell') !== false ? 'selected' : '' ?>>Продажи</option>
                        <option value="deposit" <?= $filter_type == 'deposit' ? 'selected' : '' ?>>Пополнения</option>
                        <option value="transfer" <?= strpos($filter_type, 'transfer') !== false ? 'selected' : '' ?>>Переводы</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Площадка</label>
                    <select name="platform">
                        <option value="0">Все площадки</option>
                        <?php foreach ($platforms as $platform): ?>
                        <option value="<?= $platform['id'] ?>" <?= $filter_platform == $platform['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($platform['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Актив</label>
                    <select name="asset">
                        <option value="0">Все активы</option>
                        <?php foreach ($assets as $asset): ?>
                        <option value="<?= $asset['id'] ?>" <?= $filter_asset == $asset['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($asset['symbol']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Дата с</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                
                <div class="filter-group">
                    <label>Дата по</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Применить
                    </button>
                    <a href="operations.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Сбросить
                    </a>
                </div>
            </form>
        </div>

        <!-- Таблица операций -->
        <div class="operations-table-container">
            <?php if (empty($operations)): ?>
            <div style="text-align: center; padding: 60px; color: #6b7a8f;">
                <i class="fas fa-inbox" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                <p>Нет операций для отображения</p>
            </div>
            <?php else: ?>
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Тип</th>
                        <th>Актив / Площадка</th>
                        <th>Сумма</th>
                        <th style="text-align: center;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grouped_ops = [];
                    foreach ($operations as $op) {
                        if ($op['operation_type'] == 'buy_asset') {
                            $grouped_ops[$op['operation_id']]['buy'] = $op;
                        } elseif ($op['operation_type'] == 'buy_payment') {
                            $grouped_ops[$op['operation_id']]['payment'] = $op;
                        } elseif ($op['operation_type'] == 'sell_asset') {
                            $grouped_ops[$op['operation_id']]['sell'] = $op;
                        } elseif ($op['operation_type'] == 'sell_income') {
                            $grouped_ops[$op['operation_id']]['income'] = $op;
                        } else {
                            $grouped_ops[$op['operation_id']]['other'] = $op;
                        }
                    }
                    
                    foreach ($grouped_ops as $group):
                        if (isset($group['buy']) && isset($group['payment'])):
                            $buy = $group['buy'];
                            $payment = $group['payment'];
                            
                            $format_amount = function($amount, $currency) {
                                if (!$amount || $amount <= 0) return '';
                                $decimals = (in_array($currency, ['BTC', 'ETH', 'USDT', 'USDC'])) ? 6 : 2;
                                return number_format($amount, $decimals, '.', ' ') . ' ' . $currency;
                            };
                    ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($buy['date'])) ?></td>
                        <td>
                            <span class="operation-badge badge-buy">
                                <i class="fas fa-arrow-down"></i> Покупка
                            </span>
                        </td>
                        <td>
                            <div style="color: #00a86b; font-weight: 500;">
                                +<?= $format_amount($buy['amount'], $buy['currency']) ?>
                            </div>
                            <div style="font-size: 11px; color: #6b7a8f; margin-top: 4px;">
                                <i class="fas fa-building" style="margin-right: 4px;"></i> <?= htmlspecialchars($buy['platform']) ?>
                            </div>
                        </td>
                        <td>
                            <div class="amount-negative">
                                -<?= $format_amount($payment['amount_out'], $payment['currency']) ?>
                            </div>
                            <div style="font-size: 11px; color: #6b7a8f; margin-top: 4px;">
                                <i class="fas fa-credit-card" style="margin-right: 4px;"></i> <?= htmlspecialchars($payment['platform']) ?>
                            </div>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <i class="fas fa-info-circle operation-details-btn" 
                               onclick='showOperationDetails(<?= json_encode($buy, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'></i>
                            <i class="fas fa-trash-alt operation-delete-btn" 
                               onclick="confirmDeleteOperation(<?= $buy['operation_id'] ?>, '<?= $buy['operation_type'] ?>', '<?= $buy['source_table'] ?>')"></i>
                        </td>
                    </tr>
                    <?php 
                        elseif (isset($group['sell']) && isset($group['income'])):
                            $sell = $group['sell'];
                            $income = $group['income'];
                    ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($sell['date'])) ?></td>
                        <td>
                            <span class="operation-badge badge-sell">
                                <i class="fas fa-arrow-up"></i> Продажа
                            </span>
                        </td>
                        <td>
                            <div class="amount-negative">
                                -<?= $format_amount($sell['amount_out'], $sell['currency']) ?>
                            </div>
                            <div style="font-size: 11px; color: #6b7a8f; margin-top: 4px;">
                                <i class="fas fa-building" style="margin-right: 4px;"></i> <?= htmlspecialchars($sell['platform']) ?>
                            </div>
                        </td>
                        <td>
                            <div class="amount-positive">
                                +<?= $format_amount($income['amount'], $income['currency']) ?>
                            </div>
                            <div style="font-size: 11px; color: #6b7a8f; margin-top: 4px;">
                                <i class="fas fa-credit-card" style="margin-right: 4px;"></i> <?= htmlspecialchars($income['platform']) ?>
                            </div>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <i class="fas fa-info-circle operation-details-btn" 
                               onclick='showOperationDetails(<?= json_encode($sell, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'></i>
                            <i class="fas fa-trash-alt operation-delete-btn" 
                               onclick="confirmDeleteOperation(<?= $sell['operation_id'] ?>, '<?= $sell['operation_type'] ?>', '<?= $sell['source_table'] ?>')"></i>
                        </td>
                    </tr>
                    <?php 
                        elseif (isset($group['other']) && $group['other']['operation_type'] == 'deposit'):
                            $deposit = $group['other'];
                    ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($deposit['date'])) ?></td>
                        <td>
                            <span class="operation-badge badge-deposit">
                                <i class="fas fa-plus-circle"></i> Пополнение
                            </span>
                        </td>
                        <td>
                            <div class="amount-positive">
                                +<?= $format_amount($deposit['amount'], $deposit['currency']) ?>
                            </div>
                            <div style="font-size: 11px; color: #6b7a8f; margin-top: 4px;">
                                <i class="fas fa-building" style="margin-right: 4px;"></i> <?= htmlspecialchars($deposit['platform']) ?>
                            </div>
                        </td>
                        <td class="amount-positive">
                            +<?= $format_amount($deposit['amount'], $deposit['currency']) ?>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <i class="fas fa-info-circle operation-details-btn" 
                               onclick='showOperationDetails(<?= json_encode($deposit, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'></i>
                            <i class="fas fa-trash-alt operation-delete-btn" 
                               onclick="confirmDeleteOperation(<?= $deposit['operation_id'] ?>, '<?= $deposit['operation_type'] ?>', '<?= $deposit['source_table'] ?>')"></i>
                        </td>
                    </tr>
                    <?php 
                        elseif (isset($group['other']) && in_array($group['other']['operation_type'], ['transfer_in', 'transfer_out'])):
                            $transfer = $group['other'];
                            $is_in = ($transfer['operation_type'] == 'transfer_in');
                    ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($transfer['date'])) ?></td>
                        <td>
                            <span class="operation-badge badge-transfer">
                                <i class="fas fa-exchange-alt"></i> <?= $is_in ? 'Входящий' : 'Исходящий' ?> перевод
                            </span>
                        </td>
                        <td>
                            <div class="<?= $is_in ? 'amount-positive' : 'amount-negative' ?>">
                                <?= $is_in ? '+' : '-' ?><?= $format_amount($transfer['amount'] > 0 ? $transfer['amount'] : $transfer['amount_out'], $transfer['currency']) ?>
                            </div>
                            <div style="font-size: 11px; color: #6b7a8f; margin-top: 4px;">
                                <i class="fas fa-building" style="margin-right: 4px;"></i> <?= htmlspecialchars($transfer['platform']) ?>
                            </div>
                        </td>
                        <td>—</td>
                        <td style="text-align: center; white-space: nowrap;">
                            <i class="fas fa-info-circle operation-details-btn" 
                               onclick='showOperationDetails(<?= json_encode($transfer, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'></i>
                            <i class="fas fa-trash-alt operation-delete-btn" 
                               onclick="confirmDeleteOperation(<?= $transfer['operation_id'] ?>, '<?= $transfer['operation_type'] ?>', '<?= $transfer['source_table'] ?>')"></i>
                        </td>
                    </tr>
                    <?php endif; endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&type=<?= urlencode($filter_type) ?>&platform=<?= $filter_platform ?>&asset=<?= $filter_asset ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i> Назад
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <a href="?page=<?= $i ?>&type=<?= urlencode($filter_type) ?>&platform=<?= $filter_platform ?>&asset=<?= $filter_asset ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&type=<?= urlencode($filter_type) ?>&platform=<?= $filter_platform ?>&asset=<?= $filter_asset ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="page-link">
                    Вперед <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Модальное окно деталей операции -->
    <div class="modal-overlay" id="operationDetailsModal">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-info-circle"></i> Детали операции</h2>
                <button class="modal-close" onclick="closeOperationModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Данные будут вставлены через JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function showOperationDetails(operation) {
            const modal = document.getElementById('operationDetailsModal');
            const modalBody = document.getElementById('modalBody');
            
            let detailsHtml = '';
            
            detailsHtml += `
                <div class="detail-row">
                    <div class="detail-label">Дата:</div>
                    <div class="detail-value">${new Date(operation.date).toLocaleDateString('ru-RU')}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Тип:</div>
                    <div class="detail-value">${operation.short_description}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Площадка:</div>
                    <div class="detail-value">${operation.platform}</div>
                </div>
            `;
            
            if (operation.amount > 0) {
                detailsHtml += `
                    <div class="detail-row">
                        <div class="detail-label">Получено:</div>
                        <div class="detail-value amount-positive">+${Number(operation.amount).toLocaleString('ru-RU')} ${operation.currency}</div>
                    </div>
                `;
            }
            if (operation.amount_out > 0) {
                detailsHtml += `
                    <div class="detail-row">
                        <div class="detail-label">Списано:</div>
                        <div class="detail-value amount-negative">-${Number(operation.amount_out).toLocaleString('ru-RU')} ${operation.currency}</div>
                    </div>
                `;
            }
            
            if (operation.price) {
                detailsHtml += `
                    <div class="detail-row">
                        <div class="detail-label">Цена:</div>
                        <div class="detail-value">${Number(operation.price).toLocaleString('ru-RU')} ${operation.price_currency}</div>
                    </div>
                `;
            }
            
            if (operation.commission && operation.commission > 0) {
                detailsHtml += `
                    <div class="detail-row">
                        <div class="detail-label">Комиссия:</div>
                        <div class="detail-value">${Number(operation.commission).toLocaleString('ru-RU')} ${operation.commission_currency || ''}</div>
                    </div>
                `;
            }
            
            if (operation.notes) {
                detailsHtml += `
                    <div class="detail-row">
                        <div class="detail-label">Комментарий:</div>
                        <div class="detail-value">${escapeHtml(operation.notes)}</div>
                    </div>
                `;
            }
            
            modalBody.innerHTML = detailsHtml;
            modal.classList.add('active');
        }

        function closeOperationModal() {
            document.getElementById('operationDetailsModal').classList.remove('active');
        }

        document.getElementById('operationDetailsModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeOperationModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeOperationModal();
            }
        });

        function confirmDeleteOperation(operationId, operationType, sourceTable) {
            if (confirm('Вы уверены, что хотите удалить эту операцию? Это действие нельзя отменить.')) {
                deleteOperation(operationId, operationType, sourceTable);
            }
        }

        async function deleteOperation(operationId, operationType, sourceTable) {
            const formData = new FormData();
            formData.append('action', 'delete_operation');
            formData.append('operation_id', operationId);
            formData.append('operation_type', operationType);
            formData.append('source_table', sourceTable);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('Операция успешно удалена');
                    location.reload();
                } else {
                    alert('Ошибка при удалении: ' + result.message);
                }
            } catch (error) {
                alert('Ошибка сети при удалении операции');
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>