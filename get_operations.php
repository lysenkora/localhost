<?php

// Временное логирование для отладки
file_put_contents('C:\OSPanel\domains\localhost\debug_test.log', date('Y-m-d H:i:s') . " - get_operations.php вызван\n", FILE_APPEND);

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

// Проверяем, какой asset_id у ETH
$check_eth = $pdo->query("SELECT id, symbol FROM assets WHERE symbol = 'ETH'");
$eth = $check_eth->fetch();
error_log("ETH asset_id: " . ($eth ? $eth['id'] : 'NOT FOUND'));

// ============================================================================
// ОБРАБОТКА POST ЗАПРОСОВ
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ========== ОБРАБОТЧИК ДЛЯ get_sell_data ==========
    if ($action === 'get_sell_data') {
        $asset_id = intval($_POST['asset_id'] ?? 0);
        $price_currency = $_POST['price_currency'] ?? 'USD';
        
        if (!$asset_id) {
            echo json_encode(['success' => false, 'message' => 'Актив не указан']);
            exit;
        }
        
        try {
            // 1. Получаем историю покупок (все сделки покупки)
            $stmt = $pdo->prepare("
                SELECT 
                    t.id as trade_id,
                    t.platform_id,
                    pl.name as platform_name,
                    t.quantity,
                    t.price,
                    t.price_currency,
                    t.operation_date,
                    t.notes
                FROM trades t
                JOIN platforms pl ON t.platform_id = pl.id
                WHERE t.asset_id = ? 
                    AND t.operation_type = 'buy'
                ORDER BY t.operation_date DESC
            ");
            $stmt->execute([$asset_id]);
            $purchase_history = $stmt->fetchAll();
            
            // 2. Получаем текущие остатки по площадкам
            $stmt = $pdo->prepare("
                SELECT 
                    p.id as portfolio_id,
                    p.platform_id,
                    pl.name as platform_name,
                    p.quantity,
                    p.average_buy_price,
                    p.currency_code
                FROM portfolio p
                JOIN platforms pl ON p.platform_id = pl.id
                WHERE p.asset_id = ? AND p.quantity > 0
                ORDER BY p.quantity DESC
            ");
            $stmt->execute([$asset_id]);
            $platform_balances = $stmt->fetchAll();
            
            // 3. Рассчитываем общую статистику
            $total_quantity = array_sum(array_column($platform_balances, 'quantity'));
            $total_value = 0;
            foreach ($platform_balances as $balance) {
                $total_value += $balance['quantity'] * $balance['average_buy_price'];
            }
            $avg_price = $total_quantity > 0 ? $total_value / $total_quantity : 0;
            
            echo json_encode([
                'success' => true,
                'purchase_history' => $purchase_history,
                'platform_balances' => $platform_balances,
                'total_quantity' => $total_quantity,
                'avg_price' => $avg_price
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Ошибка загрузки: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    // ========== ОБРАБОТЧИК ДЛЯ get_sell_lots (FIFO версия) ==========
    if ($action === 'get_sell_lots') {
        $asset_id = intval($_POST['asset_id'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $price_currency = $_POST['price_currency'] ?? 'USD';
        
        if (!$asset_id) {
            echo json_encode(['success' => false, 'message' => 'Актив не указан']);
            exit;
        }
        
        try {
            // Получаем все покупки с остатками (используя FIFO логику)
            $stmt = $pdo->prepare("
                SELECT 
                    t.id as trade_id,
                    t.platform_id,
                    pl.name as platform_name,
                    t.quantity as original_quantity,
                    t.price as purchase_price,
                    t.price_currency,
                    t.operation_date,
                    COALESCE(
                        (SELECT SUM(s.quantity) 
                        FROM trades s 
                        WHERE s.operation_type = 'sell' 
                        AND s.asset_id = t.asset_id 
                        AND s.operation_date >= t.operation_date),
                        0
                    ) as sold_quantity
                FROM trades t
                JOIN platforms pl ON t.platform_id = pl.id
                WHERE t.asset_id = ? 
                    AND t.operation_type = 'buy'
                ORDER BY t.operation_date ASC
            ");
            $stmt->execute([$asset_id]);
            $purchases = $stmt->fetchAll();
            
            $lots = [];
            
            foreach ($purchases as $purchase) {
                $remaining = $purchase['original_quantity'] - $purchase['sold_quantity'];
                
                if ($remaining > 0) {
                    $lots[] = [
                        'id' => 'trade_' . $purchase['trade_id'],
                        'platform_id' => $purchase['platform_id'],
                        'platform_name' => $purchase['platform_name'],
                        'quantity' => floatval($remaining),
                        'avg_price' => floatval($purchase['purchase_price']),
                        'price_currency' => $purchase['price_currency'],
                        'purchase_date' => $purchase['operation_date']
                    ];
                }
            }
            
            // Сортируем по дате (FIFO)
            usort($lots, function($a, $b) {
                return strcmp($a['purchase_date'], $b['purchase_date']);
            });
            
            echo json_encode([
                'success' => true,
                'lots' => $lots
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Ошибка загрузки: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    // ========== ОБРАБОТЧИК ДЛЯ sell_selected_lots ==========
    if ($action === 'sell_selected_lots') {
        $log_file = 'C:\OSPanel\domains\localhost\debug_sell.log';
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . " ========== НАЧАЛО ПРОДАЖИ ==========\n", FILE_APPEND);
        
        $asset_id = intval($_POST['asset_id'] ?? 0);
        $lots_json = $_POST['lots'] ?? '[]';
        $total_quantity = floatval($_POST['total_quantity'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $price_currency = $_POST['price_currency'] ?? 'USD';
        $commission = floatval($_POST['commission'] ?? 0);
        $commission_currency = !empty($_POST['commission_currency']) ? $_POST['commission_currency'] : null;
        $operation_date = $_POST['operation_date'] ?? date('Y-m-d');
        $notes = $_POST['notes'] ?? '';
        
        $lots = json_decode($lots_json, true);
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - asset_id: $asset_id\n", FILE_APPEND);
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - total_quantity: $total_quantity\n", FILE_APPEND);
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - price: $price\n", FILE_APPEND);
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - price_currency: $price_currency\n", FILE_APPEND);
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - lots: " . json_encode($lots) . "\n", FILE_APPEND);
        
        if (!$asset_id || empty($lots) || $total_quantity <= 0) {
            $error_msg = 'Нет данных для продажи';
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - ОШИБКА: $error_msg\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit;
        }
        
        try {
            // Определяем platform_id из первого лота (площадка продажи)
            $platform_id = $lots[0]['platform_id'] ?? null;
            
            if (!$platform_id) {
                throw new Exception("Не указана площадка продажи");
            }
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - platform_id: $platform_id\n", FILE_APPEND);
            
            // Проверяем подключение к БД
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Проверка подключения к БД...\n", FILE_APPEND);
            $pdo->query("SELECT 1");
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Подключение к БД OK\n", FILE_APPEND);
            
            $pdo->beginTransaction();
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Транзакция начата\n", FILE_APPEND);
            
            // 1. Создаем запись о продаже (общую для всех лотов)
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Вставляем запись в trades...\n", FILE_APPEND);
            
            $stmt = $pdo->prepare("
                INSERT INTO trades (
                    operation_type, asset_id, platform_id, quantity, price, 
                    price_currency, commission, commission_currency, operation_date, notes
                ) VALUES ('sell', ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $asset_id, $platform_id, $total_quantity, $price,
                $price_currency, $commission, $commission_currency,
                $operation_date, $notes
            ]);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Ошибка INSERT: " . $errorInfo[2]);
            }
            
            $trade_id = $pdo->lastInsertId();
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Создана запись trade_id=$trade_id\n", FILE_APPEND);
            
            // 2. Обрабатываем каждый лот
            foreach ($lots as $index => $lot) {
                $lot_platform_id = $lot['platform_id'];
                $quantity = floatval($lot['quantity']);
                
                if ($quantity <= 0) continue;
                
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - Обработка лота $index: platform_id=$lot_platform_id, quantity=$quantity\n", FILE_APPEND);
                
                // Получаем текущее количество
                $stmt = $pdo->prepare("
                    SELECT id, quantity FROM portfolio 
                    WHERE asset_id = ? AND platform_id = ?
                ");
                $stmt->execute([$asset_id, $lot_platform_id]);
                $portfolio = $stmt->fetch();
                
                if (!$portfolio) {
                    throw new Exception("Актив не найден на площадке ID: {$lot_platform_id}");
                }
                
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - Текущий остаток: {$portfolio['quantity']}\n", FILE_APPEND);
                
                if ($portfolio['quantity'] < $quantity) {
                    throw new Exception("Недостаточно актива. Доступно: {$portfolio['quantity']}, нужно: {$quantity}");
                }
                
                // Уменьшаем количество
                $new_quantity = $portfolio['quantity'] - $quantity;
                if ($new_quantity > 0) {
                    $stmt = $pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_quantity, $portfolio['id']]);
                    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Обновлен портфель, новое количество: $new_quantity\n", FILE_APPEND);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                    $stmt->execute([$portfolio['id']]);
                    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Удален портфель\n", FILE_APPEND);
                }
            }
            
            // 3. Добавляем полученные средства
            $main_platform_id = null;
            $max_quantity = 0;
            foreach ($lots as $lot) {
                if ($lot['quantity'] > $max_quantity) {
                    $max_quantity = $lot['quantity'];
                    $main_platform_id = $lot['platform_id'];
                }
            }
            
            $total_income = $total_quantity * $price;
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - total_income=$total_income, main_platform_id=$main_platform_id\n", FILE_APPEND);
            
            // Находим актив для зачисления
            $stmt = $pdo->prepare("SELECT id FROM assets WHERE symbol = ?");
            $stmt->execute([$price_currency]);
            $currency_asset = $stmt->fetch();
            
            if ($currency_asset && $main_platform_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ");
                $stmt->execute([$currency_asset['id'], $main_platform_id, $total_income, $price_currency]);
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - Добавлены средства\n", FILE_APPEND);
            } else {
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - ПРЕДУПРЕЖДЕНИЕ: не удалось добавить средства\n", FILE_APPEND);
            }
            
            $pdo->commit();
            file_put_contents($log_file, date('Y-m-d H:i:s') . " ========== ПРОДАЖА УСПЕШНА ==========\n\n", FILE_APPEND);
            
            echo json_encode([
                'success' => true,
                'message' => 'Продажа успешно выполнена',
                'trade_id' => $trade_id
            ]);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = $e->getMessage();
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - ОШИБКА: $error_msg\n", FILE_APPEND);
            file_put_contents($log_file, date('Y-m-d H:i:s') . " ========== ПРОДАЖА НЕ УДАЛАСЬ ==========\n\n", FILE_APPEND);
            
            echo json_encode([
                'success' => false,
                'message' => $error_msg
            ]);
            exit;
        }
    }
    
    // Если действие не распознано
    echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
    exit;
}

// ============================================================================
// ПОЛУЧЕНИЕ ПАРАМЕТРОВ ДЛЯ GET ЗАПРОСОВ
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
            SELECT id FROM trades
            UNION ALL
            SELECT id FROM deposits
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
            t.operation_date as sort_date,
            t.asset_id
        FROM trades t
        JOIN assets a ON t.asset_id = a.id
        JOIN platforms p ON t.platform_id = p.id
        WHERE t.operation_type = 'buy')
        
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
            COALESCE(t.commission, 0) as commission,
            t.commission_currency,
            t.operation_date as sort_date,
            t.asset_id
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
            d.deposit_date as sort_date,
            NULL as asset_id
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
            t.transfer_date as sort_date,
            NULL as asset_id
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
            t.transfer_date as sort_date,
            NULL as asset_id
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

    // Добавляем расчет прибыли для каждой операции продажи (FIFO)
    foreach ($operations as &$op) {
        if ($op['operation_type'] == 'sell_asset' && $op['asset_id']) {
            
            // Получаем все покупки этого актива ДО даты продажи
            $stmt_buys = $pdo->prepare("
                SELECT id, quantity, price, operation_date
                FROM trades
                WHERE asset_id = ? 
                    AND operation_type = 'buy'
                    AND operation_date <= ?
                ORDER BY operation_date ASC, id ASC
            ");
            $stmt_buys->execute([$op['asset_id'], $op['date']]);
            $buys = $stmt_buys->fetchAll();
            
            // Получаем все продажи этого актива ДО текущей продажи
            $stmt_sells = $pdo->prepare("
                SELECT id, quantity, operation_date
                FROM trades
                WHERE asset_id = ? 
                    AND operation_type = 'sell'
                    AND operation_date < ?
                    AND id != ?
                ORDER BY operation_date ASC, id ASC
            ");
            $stmt_sells->execute([$op['asset_id'], $op['date'], $op['operation_id']]);
            $sells = $stmt_sells->fetchAll();
            
            // Собираем все покупки в очередь
            $buy_queue = [];
            foreach ($buys as $buy) {
                $buy_queue[] = [
                    'id' => $buy['id'],
                    'quantity' => floatval($buy['quantity']),
                    'price' => floatval($buy['price']),
                    'remaining' => floatval($buy['quantity'])
                ];
            }
            
            // Вычитаем предыдущие продажи из очереди (FIFO)
            foreach ($sells as $sell) {
                $sell_qty = floatval($sell['quantity']);
                $sell_remaining = $sell_qty;
                
                for ($i = 0; $i < count($buy_queue) && $sell_remaining > 0; $i++) {
                    if ($buy_queue[$i]['remaining'] <= $sell_remaining) {
                        $sell_remaining -= $buy_queue[$i]['remaining'];
                        $buy_queue[$i]['remaining'] = 0;
                    } else {
                        $buy_queue[$i]['remaining'] -= $sell_remaining;
                        $sell_remaining = 0;
                    }
                }
            }
            
            // Для текущей продажи берем из оставшихся покупок
            $sell_qty = floatval($op['amount_out']);
            $total_cost = 0;
            $remaining_to_sell = $sell_qty;
            
            for ($i = 0; $i < count($buy_queue) && $remaining_to_sell > 0; $i++) {
                if ($buy_queue[$i]['remaining'] > 0) {
                    $take_qty = min($buy_queue[$i]['remaining'], $remaining_to_sell);
                    $total_cost += $take_qty * $buy_queue[$i]['price'];
                    $remaining_to_sell -= $take_qty;
                }
            }
            
            if ($total_cost > 0 && $remaining_to_sell == 0) {
                $total_sold = $op['amount_out'] * $op['price'];
                $profit = $total_sold - $total_cost;
                $profit_percent = ($profit / $total_cost) * 100;
                
                $op['total_cost'] = $total_cost;
                $op['profit'] = $profit;
                $op['profit_percent'] = $profit_percent;
                
                error_log("FIFO RESULT: asset_id={$op['asset_id']}, qty={$op['amount_out']}, total_sold=$total_sold, total_cost=$total_cost, profit=$profit, percent=$profit_percent");
            } else {
                $op['total_cost'] = 0;
                $op['profit'] = 0;
                $op['profit_percent'] = 0;
                error_log("FIFO FAILED: asset_id={$op['asset_id']}, remaining_to_sell=$remaining_to_sell, buys_count=" . count($buys) . ", sells_count=" . count($sells));
            }
        }
    }

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
    error_log("Error in get_operations: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>