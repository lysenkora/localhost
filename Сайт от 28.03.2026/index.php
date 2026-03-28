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

// ============================================================================
// ПОЛУЧЕНИЕ НАСТРОЕК ПОЛЬЗОВАТЕЛЯ
// ============================================================================

// Получаем текущую тему из БД
$stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE setting_key = 'theme'");
$stmt->execute();
$theme_data = $stmt->fetch();
$current_theme = $theme_data ? $theme_data['setting_value'] : 'light';

// ============================================================================
// ПОЛУЧЕНИЕ КУРСА ВАЛЮТ (ПЕРЕНЕСЕНО НАВЕРХ)
// ============================================================================

// Получаем актуальный курс USD/RUB из БД
$stmt = $pdo->query("
    SELECT rate FROM exchange_rates 
    WHERE from_currency = 'USD' AND to_currency = 'RUB' 
    ORDER BY date DESC LIMIT 1
");
$rate_data = $stmt->fetch();
$usd_rub_rate = $rate_data ? $rate_data['rate'] : 92.50;

// ============================================================================
// ПОЛУЧЕНИЕ АКТИВОВ ПО ТИПАМ КРИПТОВАЛЮТ (Altcoins, Stablecoins)
// ============================================================================

// Получаем все криптоактивы с детализацией по типам
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN a.symbol IN ('USDT', 'USDC', 'DAI', 'BUSD') THEN 'stablecoins'
            WHEN a.symbol IN ('BTC', 'ETH') THEN 'major'
            ELSE 'altcoins'
        END as crypto_type,
        a.id as asset_id,
        a.symbol,
        a.name as asset_name,
        a.type as asset_type,
        a.currency_code,
        p.quantity,
        p.average_buy_price,
        pl.name as platform_name,
        pl.id as platform_id,
        CASE 
            WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN p.quantity
            ELSE p.quantity * COALESCE(p.average_buy_price, 0)
        END as value_usd
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
    JOIN platforms pl ON p.platform_id = pl.id
    WHERE a.type = 'crypto' AND p.quantity > 0
    ORDER BY crypto_type, value_usd DESC
");

$all_crypto_type_assets = $stmt->fetchAll();

// Группируем активы по типам криптовалют
$crypto_type_assets_grouped = [];
foreach ($all_crypto_type_assets as $asset) {
    $crypto_type = $asset['crypto_type'];
    
    // Пропускаем major (BTC и ETH) - они уже отображаются отдельно
    if ($crypto_type === 'major') continue;
    
    $display_name = $crypto_type === 'altcoins' ? 'Альткоины' : 'Стейблкоины';
    
    if (!isset($crypto_type_assets_grouped[$crypto_type])) {
        $crypto_type_assets_grouped[$crypto_type] = [
            'type' => $crypto_type,
            'display_name' => $display_name,
            'assets' => [],
            'total_value_usd' => 0
        ];
    }
    
    $crypto_type_assets_grouped[$crypto_type]['assets'][] = [
        'symbol' => $asset['symbol'],
        'asset_name' => $asset['asset_name'],
        'asset_type' => $asset['asset_type'],
        'quantity' => $asset['quantity'],
        'average_buy_price' => $asset['average_buy_price'],
        'currency_code' => $asset['currency_code'],
        'platform_name' => $asset['platform_name'],
        'platform_id' => $asset['platform_id'],
        'value_usd' => $asset['value_usd']
    ];
    
    $crypto_type_assets_grouped[$crypto_type]['total_value_usd'] += $asset['value_usd'];
}

// Сортируем
uasort($crypto_type_assets_grouped, function($a, $b) {
    return $b['total_value_usd'] <=> $a['total_value_usd'];
});

// Передаем данные в JavaScript
$crypto_type_assets_json = json_encode($crypto_type_assets_grouped);

// ============================================================================
// ПОЛУЧЕНИЕ АКТИВОВ ПО СЕКТОРАМ (для модального окна)
// ============================================================================

// Получаем все активы с детализацией по секторам
$stmt = $pdo->query("
    SELECT 
        COALESCE(a.sector, 'Другое') as sector_name,
        a.id as asset_id,
        a.symbol,
        a.name as asset_name,
        a.type as asset_type,
        a.currency_code,
        p.quantity,
        p.average_buy_price,
        pl.name as platform_name,
        pl.id as platform_id,
        CASE 
            WHEN a.symbol = 'RUB' THEN p.quantity / " . $usd_rub_rate . "
            WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN p.quantity
            ELSE p.quantity * COALESCE(p.average_buy_price, 0)
        END as value_usd
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
    JOIN platforms pl ON p.platform_id = pl.id
    WHERE a.type IN ('stock', 'etf') 
        AND (a.currency_code = 'USD' OR a.symbol LIKE '%.US')
        AND p.quantity > 0
    ORDER BY sector_name, value_usd DESC
");

$all_sector_assets = $stmt->fetchAll();

// Группируем активы по секторам
$sector_assets_grouped = [];
foreach ($all_sector_assets as $asset) {
    $sector_name = $asset['sector_name'];
    
    // Переводим название сектора для отображения
    $display_sector_name = translateSector($sector_name);
    
    if (!isset($sector_assets_grouped[$sector_name])) {
        $sector_assets_grouped[$sector_name] = [
            'sector_name' => $sector_name,
            'display_name' => $display_sector_name,
            'assets' => [],
            'total_value_usd' => 0
        ];
    }
    
    $sector_assets_grouped[$sector_name]['assets'][] = [
        'symbol' => $asset['symbol'],
        'asset_name' => $asset['asset_name'],
        'asset_type' => $asset['asset_type'],
        'quantity' => $asset['quantity'],
        'average_buy_price' => $asset['average_buy_price'],
        'currency_code' => $asset['currency_code'],
        'platform_name' => $asset['platform_name'],
        'platform_id' => $asset['platform_id'],
        'value_usd' => $asset['value_usd']
    ];
    
    $sector_assets_grouped[$sector_name]['total_value_usd'] += $asset['value_usd'];
}

// Сортируем сектора по общей стоимости
uasort($sector_assets_grouped, function($a, $b) {
    return $b['total_value_usd'] <=> $a['total_value_usd'];
});

// Передаем данные в JavaScript
$sector_assets_json = json_encode($sector_assets_grouped);

// ============================================================================
// ПОЛУЧЕНИЕ АКТИВОВ ПО СЕТЯМ ДЛЯ МОДАЛЬНОГО ОКНА (исправленная версия)
// ============================================================================

// Получаем все криптоактивы с детализацией по переводам
$stmt = $pdo->query("
    SELECT 
        a.id as asset_id,
        a.symbol,
        a.name as asset_name,
        a.type as asset_type,
        p.id as portfolio_id,
        p.platform_id,
        p.quantity,
        p.average_buy_price,
        p.currency_code,
        pl.name as platform_name
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
    JOIN platforms pl ON p.platform_id = pl.id
    WHERE a.type = 'crypto' AND p.quantity > 0
");
$crypto_portfolio = $stmt->fetchAll();

// Получаем все переводы с указанием сетей
$stmt = $pdo->query("
    SELECT 
        to_platform_id,
        asset_id,
        quantity,
        to_network as network,
        transfer_date
    FROM transfers
    WHERE to_network IS NOT NULL AND to_network != ''
    ORDER BY transfer_date ASC
");
$all_transfers = $stmt->fetchAll();

// Группируем переводы по (platform_id, asset_id)
$transfers_by_portfolio = [];
foreach ($all_transfers as $transfer) {
    $key = $transfer['to_platform_id'] . '_' . $transfer['asset_id'];
    if (!isset($transfers_by_portfolio[$key])) {
        $transfers_by_portfolio[$key] = [];
    }
    $transfers_by_portfolio[$key][] = $transfer;
}

// Распределяем активы по сетям
$network_assets_grouped = [];

foreach ($crypto_portfolio as $asset) {
    $key = $asset['platform_id'] . '_' . $asset['asset_id'];
    $total_quantity = floatval($asset['quantity']);
    
    // Ищем переводы для этой позиции
    $transfers = isset($transfers_by_portfolio[$key]) ? $transfers_by_portfolio[$key] : [];
    
    if (empty($transfers)) {
        // Нет переводов - сеть не определена
        $network = 'UNKNOWN';
        $quantity_in_network = $total_quantity;
    } else {
        // Суммируем количество по сетям
        $network_quantities = [];
        $total_transferred = 0;
        
        foreach ($transfers as $transfer) {
            $network = $transfer['network'];
            $quantity = floatval($transfer['quantity']);
            
            if (!isset($network_quantities[$network])) {
                $network_quantities[$network] = 0;
            }
            $network_quantities[$network] += $quantity;
            $total_transferred += $quantity;
        }
        
        // Если сумма переводов меньше общего количества, остаток относим к последней сети
        if ($total_transferred < $total_quantity && count($transfers) > 0) {
            $last_network = $transfers[count($transfers) - 1]['network'];
            $network_quantities[$last_network] += ($total_quantity - $total_transferred);
        }
        
        // Для каждой сети создаем запись
        foreach ($network_quantities as $network => $quantity_in_network) {
            if ($quantity_in_network <= 0) continue;
            
            // Рассчитываем стоимость для этого количества
            if ($asset['symbol'] == 'USDT' || $asset['symbol'] == 'USDC') {
                $value = $quantity_in_network;
            } else if ($asset['average_buy_price'] > 0) {
                $value = $quantity_in_network * $asset['average_buy_price'];
            } else {
                $value = 0;
            }
            
            if (!isset($network_assets_grouped[$network])) {
                $network_assets_grouped[$network] = [
                    'network' => $network,
                    'assets' => [],
                    'total_value_usd' => 0
                ];
            }
            
            $network_assets_grouped[$network]['assets'][] = [
                'symbol' => $asset['symbol'],
                'asset_name' => $asset['asset_name'],
                'quantity' => $quantity_in_network,
                'average_buy_price' => $asset['average_buy_price'],
                'currency_code' => $asset['currency_code'],
                'platform_name' => $asset['platform_name'],
                'value_usd' => $value
            ];
            
            $network_assets_grouped[$network]['total_value_usd'] += $value;
        }
        
        continue; // Пропускаем добавление для UNKNOWN
    }
    
    // Если нет переводов, добавляем в UNKNOWN
    if ($total_quantity > 0) {
        if ($asset['symbol'] == 'USDT' || $asset['symbol'] == 'USDC') {
            $value = $total_quantity;
        } else if ($asset['average_buy_price'] > 0) {
            $value = $total_quantity * $asset['average_buy_price'];
        } else {
            $value = 0;
        }
        
        if (!isset($network_assets_grouped['UNKNOWN'])) {
            $network_assets_grouped['UNKNOWN'] = [
                'network' => 'UNKNOWN',
                'assets' => [],
                'total_value_usd' => 0
            ];
        }
        
        $network_assets_grouped['UNKNOWN']['assets'][] = [
            'symbol' => $asset['symbol'],
            'asset_name' => $asset['asset_name'],
            'quantity' => $total_quantity,
            'average_buy_price' => $asset['average_buy_price'],
            'currency_code' => $asset['currency_code'],
            'platform_name' => $asset['platform_name'],
            'value_usd' => $value
        ];
        
        $network_assets_grouped['UNKNOWN']['total_value_usd'] += $value;
    }
}

// Сортируем сети по общей стоимости
uasort($network_assets_grouped, function($a, $b) {
    return $b['total_value_usd'] <=> $a['total_value_usd'];
});

// Передаем данные в JavaScript
$network_assets_json = json_encode($network_assets_grouped);

// ============================================================================
// ПОЛУЧЕНИЕ АКТИВОВ ПО ПЛОЩАДКЕ (для модального окна)
// ============================================================================

// Получаем все активы с детализацией по площадкам для модального окна
$stmt = $pdo->query("
    SELECT 
        p.id as platform_id,
        p.name as platform_name,
        p.type as platform_type,
        a.id as asset_id,
        a.symbol,
        a.name as asset_name,
        a.type as asset_type,
        pl.quantity,
        pl.average_buy_price,
        pl.currency_code,
        CASE 
            WHEN a.symbol = 'RUB' THEN pl.quantity / " . $usd_rub_rate . "
            WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN pl.quantity
            ELSE pl.quantity * COALESCE(pl.average_buy_price, 0)
        END as value_usd
    FROM portfolio pl
    JOIN assets a ON pl.asset_id = a.id
    JOIN platforms p ON pl.platform_id = p.id
    WHERE pl.quantity > 0
    ORDER BY p.name, value_usd DESC
");
$all_platform_assets = $stmt->fetchAll();

// Группируем активы по площадкам
$platform_assets_grouped = [];
foreach ($all_platform_assets as $asset) {
    $platform_id = $asset['platform_id'];
    if (!isset($platform_assets_grouped[$platform_id])) {
        $platform_assets_grouped[$platform_id] = [
            'platform_name' => $asset['platform_name'],
            'platform_type' => $asset['platform_type'],
            'assets' => []
        ];
    }
    $platform_assets_grouped[$platform_id]['assets'][] = [
        'symbol' => $asset['symbol'],
        'asset_name' => $asset['asset_name'],
        'asset_type' => $asset['asset_type'],
        'quantity' => $asset['quantity'],
        'average_buy_price' => $asset['average_buy_price'],
        'currency_code' => $asset['currency_code'],
        'value_usd' => $asset['value_usd']
    ];
}

// Передаем данные в JavaScript
$platform_assets_json = json_encode($platform_assets_grouped);

// ============================================================================
// ПОЛУЧЕНИЕ СЕТЕЙ ИЗ БД
// ============================================================================

// Получаем все активные сети из БД
$stmt = $pdo->query("
    SELECT id, name, icon, color, full_name 
    FROM networks 
    WHERE is_active = 1 
    ORDER BY 
        CASE name 
            WHEN 'ERC20' THEN 1
            WHEN 'BEP20' THEN 2
            WHEN 'TRC20' THEN 3
            WHEN 'SOL' THEN 4
            WHEN 'BTC' THEN 5
            ELSE 6
        END,
        name
");
$networks_db = $stmt->fetchAll();

// Передаем сети в JavaScript
$networks_json = json_encode($networks_db);

// ============================================================================
// ПОЛУЧЕНИЕ РАСПРЕДЕЛЕНИЯ ПО ПЛОЩАДКАМ
// ============================================================================

// Получаем распределение по площадкам (только с активами)
$stmt = $pdo->query("
    SELECT 
        p.id as platform_id,
        p.name as platform_name,
        p.type as platform_type,
        COALESCE(SUM(
            CASE 
                -- RUB конвертируем в USD
                WHEN a.symbol = 'RUB' THEN pl.quantity / " . $usd_rub_rate . "
                -- USD, USDT и USDC считаем как 1:1 (одинаковая стоимость)
                WHEN a.symbol IN ('USD', 'USDT', 'USDC') THEN pl.quantity
                -- Для остальных активов используем среднюю цену покупки
                WHEN pl.average_buy_price IS NOT NULL AND pl.average_buy_price > 0 THEN
                    CASE
                        -- Если цена в USD, просто умножаем
                        WHEN pl.currency_code = 'USD' THEN pl.quantity * pl.average_buy_price
                        -- Если цена в RUB, конвертируем в USD
                        WHEN pl.currency_code = 'RUB' THEN (pl.quantity * pl.average_buy_price) / " . $usd_rub_rate . "
                        -- Если цена в SOL, нужно конвертировать SOL в USD
                        WHEN pl.currency_code = 'SOL' THEN 
                            (pl.quantity * pl.average_buy_price) * 
                            (SELECT COALESCE(rate, 125) FROM exchange_rates 
                             WHERE from_currency = 'SOL' AND to_currency = 'USD' 
                             ORDER BY date DESC LIMIT 1)
                        ELSE pl.quantity * pl.average_buy_price
                    END
                -- Для криптовалют без средней цены пытаемся получить цену из trades
                WHEN a.type = 'crypto' THEN
                    COALESCE(
                        (SELECT price FROM trades t 
                         WHERE t.asset_id = a.id AND t.operation_type = 'buy' 
                         ORDER BY t.operation_date DESC LIMIT 1),
                        0
                    ) * pl.quantity
                -- Для всего остального
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
$platform_distribution = $stmt->fetchAll();

// ============================================================================
// РАСПРЕДЕЛЕНИЕ ПО СЕТЯМ (исправленная версия с учетом нескольких переводов)
// ============================================================================

// Получаем все криптоактивы с детализацией по переводам
$stmt = $pdo->query("
    SELECT 
        a.symbol,
        pl.quantity as total_quantity,
        pl.average_buy_price,
        pl.platform_id,
        p.name as platform_name,
        p.type as platform_type,
        -- Получаем все переводы для этого актива на эту площадку
        t.transfers_json
    FROM portfolio pl
    JOIN assets a ON pl.asset_id = a.id
    JOIN platforms p ON pl.platform_id = p.id
    LEFT JOIN (
        SELECT 
            to_platform_id,
            asset_id,
            JSON_ARRAYAGG(
                JSON_OBJECT(
                    'quantity', quantity,
                    'network', to_network,
                    'date', transfer_date
                )
            ) as transfers_json
        FROM transfers
        WHERE to_network IS NOT NULL AND to_network != ''
        GROUP BY to_platform_id, asset_id
    ) t ON t.to_platform_id = pl.platform_id AND t.asset_id = pl.asset_id
    WHERE a.type = 'crypto' AND pl.quantity > 0
");

$crypto_assets = $stmt->fetchAll();

// Рассчитываем стоимость по сетям
$network_values = [];

foreach ($crypto_assets as $asset) {
    // Получаем общее количество актива
    $total_quantity = floatval($asset['total_quantity']);
    
    // Если есть переводы с указанием сетей
    if ($asset['transfers_json']) {
        $transfers = json_decode($asset['transfers_json'], true);
        
        // Суммируем количество по переводам
        $transfer_quantities = [];
        $total_transferred = 0;
        
        foreach ($transfers as $transfer) {
            $network = $transfer['network'];
            $quantity = floatval($transfer['quantity']);
            
            if (!isset($transfer_quantities[$network])) {
                $transfer_quantities[$network] = 0;
            }
            $transfer_quantities[$network] += $quantity;
            $total_transferred += $quantity;
        }
        
        // Если сумма переводов меньше общего количества, остаток относим к последней сети
        if ($total_transferred < $total_quantity && count($transfers) > 0) {
            $last_network = $transfers[count($transfers) - 1]['network'];
            $transfer_quantities[$last_network] += ($total_quantity - $total_transferred);
        }
        
        // Распределяем стоимость по сетям
        foreach ($transfer_quantities as $network => $quantity_in_network) {
            if ($quantity_in_network <= 0) continue;
            
            // Рассчитываем стоимость для этого количества
            if ($asset['symbol'] == 'USDT' || $asset['symbol'] == 'USDC') {
                $value = $quantity_in_network;
            } else if ($asset['average_buy_price'] > 0) {
                $value = $quantity_in_network * $asset['average_buy_price'];
            } else {
                $value = 0;
            }
            
            if ($value > 0.01) {
                if (!isset($network_values[$network])) {
                    $network_values[$network] = 0;
                }
                $network_values[$network] += $value;
            }
        }
    } 
    // Если нет переводов, но есть сеть из trades или из portfolio.network
    else {
        // Здесь можно добавить логику определения сети другими способами
        // или пропустить, если сеть не определена
    }
}

// Используем те же данные, что и для модального окна
$network_distribution_array = [];
foreach ($network_assets_grouped as $network => $data) {
    if ($network != 'UNKNOWN' && $data['total_value_usd'] > 0.01) {
        $network_distribution_array[] = [
            'network' => $network,
            'total_value_usd' => $data['total_value_usd']
        ];
    }
}

// Сортируем
usort($network_distribution_array, function($a, $b) {
    return $b['total_value_usd'] <=> $a['total_value_usd'];
});

// Рассчитываем проценты
$total_crypto_value = array_sum(array_column($network_distribution_array, 'total_value_usd'));
if ($total_crypto_value > 0) {
    foreach ($network_distribution_array as &$item) {
        $item['percentage'] = round(($item['total_value_usd'] / $total_crypto_value) * 100, 1);
    }
}

$total_crypto = $total_crypto_value;

// ============================================================================
// ПОЛУЧЕНИЕ ДАННЫХ ИЗ БД
// ============================================================================

// Получаем все активы из портфеля для точного расчета
$stmt = $pdo->query("
    SELECT 
        a.symbol,
        a.type,
        p.quantity,
        p.average_buy_price,
        p.currency_code
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
");
$portfolio_assets = $stmt->fetchAll();

// Повторяем цикл с отладкой
$rub_amount = 0;
$usdt_amount = 0;
$usd_amount = 0;
$eur_amount = 0;
$investments_value = 0;
$liquidity_value = 0;  // ← ДОБАВЬТЕ ЭТУ СТРОКУ

$debug_log = [];

foreach ($portfolio_assets as $asset) {
    $value = 0;
    
    switch ($asset['symbol']) {
        case 'RUB':
            $rub_amount += $asset['quantity'];
            $value = $asset['quantity'] / $usd_rub_rate;
            $liquidity_value += $value;  // ← Добавляем в ликвидность
            break;
        case 'USDT':
            $usdt_amount += $asset['quantity'];
            $value = $asset['quantity'];
            $liquidity_value += $value;  // ← Добавляем в ликвидность
            break;
        case 'USD':
            $usd_amount += $asset['quantity'];
            $value = $asset['quantity'];
            $liquidity_value += $value;  // ← Добавляем в ликвидность
            break;
        case 'EUR':
            $eur_amount += $asset['quantity'];
            $value = $asset['quantity'];
            $liquidity_value += $value;  // ← Добавляем в ликвидность
            break;
        default:
            if ($asset['average_buy_price'] > 0) {
                $value = $asset['quantity'] * $asset['average_buy_price'];
                $investments_value += $value;  // ← ТОЛЬКО СЮДА
            }
            break;
    }
}

// Итоговая стоимость
$total_usd = $liquidity_value + $investments_value;

// Конвертируем RUB в USD по текущему курсу
$rub_in_usd = $rub_amount / $usd_rub_rate;

// Конвертируем в рубли по актуальному курсу
$total_rub = $total_usd * $usd_rub_rate;
$liquidity_rub = $liquidity_value * $usd_rub_rate;
$investments_rub = $investments_value * $usd_rub_rate;

// Сохраняем для отображения в шапке
$rub_amount_display = $rub_amount; // Для отображения количества RUB
$usdt_amount_display = $usdt_amount; // Для отображения количества USDT

// ============================================================================
// РАСЧЕТ ДОХОДНОСТИ ПОРТФЕЛЯ
// ============================================================================

// 1. ВЛОЖЕНО = СУММА ВСЕХ ПОПОЛНЕНИЙ (только фиатные валюты)
$total_invested_usd = 0;

$stmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN currency_code = 'RUB' THEN amount ELSE 0 END) as rub_deposits,
        SUM(CASE WHEN currency_code = 'USD' THEN amount ELSE 0 END) as usd_deposits,
        SUM(CASE WHEN currency_code = 'EUR' THEN amount ELSE 0 END) as eur_deposits
    FROM deposits
    WHERE currency_code IN ('RUB', 'USD', 'EUR')
");
$deposits_total = $stmt->fetch();

// Конвертируем все пополнения в USD
$total_invested_usd += $deposits_total['usd_deposits'];
$total_invested_usd += $deposits_total['eur_deposits']; // EUR ≈ USD
$total_invested_usd += $deposits_total['rub_deposits'] / $usd_rub_rate;

// Вложено в рублях (для отображения)
$total_invested_rub = $total_invested_usd * $usd_rub_rate;

// 2. ПРИБЫЛЬ = ТЕКУЩАЯ СТОИМОСТЬ - ВЛОЖЕНО
$profit_usd = $total_usd - $total_invested_usd;
$profit_rub = $total_rub - $total_invested_rub;

// 3. ДОХОДНОСТЬ В ПРОЦЕНТАХ
if ($total_invested_usd > 0) {
    $profit_percent = ($profit_usd / $total_invested_usd) * 100;
} else {
    $profit_percent = 0;
}

// Определяем класс для цвета
$profit_class = '';
$profit_icon = '';
if ($profit_usd > 0) {
    $profit_class = 'positive';
    $profit_icon = 'fa-arrow-up';
} elseif ($profit_usd < 0) {
    $profit_class = 'negative';
    $profit_icon = 'fa-arrow-down';
} else {
    $profit_class = 'neutral';
    $profit_icon = 'fa-minus';
}

// Получаем структуру криптопортфеля
$stmt = $pdo->query("
    SELECT 
        -- Считаем все покупки USDT
        COALESCE(SUM(CASE WHEN a.symbol = 'USDT' AND t.operation_type = 'buy' THEN t.quantity END), 0) as total_usdt_bought,
        
        -- Считаем сколько USDT потрачено на BTC
        COALESCE(SUM(CASE WHEN a.symbol = 'BTC' AND t.operation_type = 'buy' THEN t.quantity * t.price END), 0) as btc_cost,
        
        -- Считаем сколько USDT потрачено на ETH
        COALESCE(SUM(CASE WHEN a.symbol = 'ETH' AND t.operation_type = 'buy' THEN t.quantity * t.price END), 0) as eth_cost,
        
        -- Считаем сколько USDT потрачено на остальные альткоины
        COALESCE(SUM(CASE WHEN a.type = 'crypto' AND a.symbol NOT IN ('USDT', 'USDC', 'BTC', 'ETH') AND t.operation_type = 'buy' THEN t.quantity * t.price END), 0) as altcoins_cost,
        
        -- BTC и ETH (на будущее)
        COALESCE(SUM(CASE WHEN a.symbol = 'BTC' AND t.operation_type = 'buy' THEN t.quantity * t.price END), 0) as btc_value,
        COALESCE(SUM(CASE WHEN a.symbol = 'ETH' AND t.operation_type = 'buy' THEN t.quantity * t.price END), 0) as eth_value
    FROM trades t
    JOIN assets a ON t.asset_id = a.id
    WHERE a.type = 'crypto'
");
$crypto_data = $stmt->fetch();

$total_usdt_bought = $crypto_data['total_usdt_bought'] ?: 1;
$btc_cost = $crypto_data['btc_cost'] ?: 0;
$eth_cost = $crypto_data['eth_cost'] ?: 0;
$altcoins_cost = $crypto_data['altcoins_cost'] ?: 0;
$stablecoins_left = $total_usdt_bought - ($btc_cost + $eth_cost + $altcoins_cost);

// Рассчитываем проценты
//$btc_percent = round(($btc_cost / $total_usdt_bought) * 100, 1);
//$eth_percent = round(($eth_cost / $total_usdt_bought) * 100, 1);
//$altcoins_percent = round(($altcoins_cost / $total_usdt_bought) * 100, 1);
//$stablecoins_percent = round(($stablecoins_left / $total_usdt_bought) * 100, 1);

// Вместо обнуления, используйте полученные данные:
$btc_cost = $crypto_data['btc_cost'] ?: 0;
$eth_cost = $crypto_data['eth_cost'] ?: 0;
$altcoins_cost = $crypto_data['altcoins_cost'] ?: 0;
$stablecoins_left = $total_usdt_bought - ($btc_cost + $eth_cost + $altcoins_cost);

// И рассчитайте проценты:
$btc_percent = $total_usdt_bought > 0 ? round(($btc_cost / $total_usdt_bought) * 100, 1) : 0;
$eth_percent = $total_usdt_bought > 0 ? round(($eth_cost / $total_usdt_bought) * 100, 1) : 0;
$altcoins_percent = $total_usdt_bought > 0 ? round(($altcoins_cost / $total_usdt_bought) * 100, 1) : 0;
$stablecoins_percent = $total_usdt_bought > 0 ? round(($stablecoins_left / $total_usdt_bought) * 100, 1) : 0;

// Получаем активы пользователя (объединяем одинаковые активы с разных площадок)
$stmt = $pdo->query("
    SELECT 
        a.id,
        a.symbol,
        a.name,
        a.type,
        SUM(p.quantity) as total_quantity,
        -- Расчет средневзвешенной цены
        CASE 
            WHEN a.symbol IN ('USDT', 'USDC') THEN (
                SELECT COALESCE(SUM(quantity * price) / NULLIF(SUM(quantity), 0), 1)
                FROM trades t
                WHERE t.asset_id IN (
                    SELECT id FROM assets WHERE symbol IN ('USD', 'USDT', 'USDC')
                )
                AND t.operation_type = 'buy'
            )
            ELSE SUM(p.quantity * COALESCE(p.average_buy_price, 0)) / NULLIF(SUM(p.quantity), 0)
        END as avg_price,
        a.currency_code,
        GROUP_CONCAT(DISTINCT p.platform_id ORDER BY p.platform_id SEPARATOR ',') as platform_ids
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
    WHERE p.quantity > 0
    GROUP BY a.id, a.symbol, a.name, a.type, a.currency_code  -- ← ДОБАВЬТЕ ВСЕ ПОЛЯ ИЗ SELECT
    ORDER BY SUM(p.quantity) * COALESCE(
        (SELECT rate FROM exchange_rates 
         WHERE from_currency = a.currency_code 
         AND to_currency = 'USD' 
         AND date = CURDATE()
        ), 1
    ) DESC
");
$assets = $stmt->fetchAll();

// Получаем лимитные ордера
$stmt = $pdo->query("
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
    LIMIT 3
");
$orders = $stmt->fetchAll();

// Получаем план действий
$stmt = $pdo->query("
    SELECT * FROM action_plan 
    ORDER BY 
        CASE priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
        END,
        due_date ASC
    LIMIT 5
");
$action_plan = $stmt->fetchAll();

// Получаем заметки
$stmt = $pdo->query("
    SELECT * FROM notes 
    WHERE is_archived = FALSE 
    ORDER BY 
        CASE note_type
            WHEN 'important' THEN 1
            WHEN 'reminder' THEN 2
            WHEN 'idea' THEN 3
            ELSE 4
        END,
        created_at DESC 
    LIMIT 3
");
$notes = $stmt->fetchAll();

// Получаем списки для выпадающих списков
$platforms = $pdo->query("SELECT id, name, type FROM platforms WHERE is_active = TRUE ORDER BY name")->fetchAll();
$assets_list = $pdo->query("SELECT id, symbol, name, type FROM assets WHERE is_active = TRUE ORDER BY symbol")->fetchAll();

// ============================================================================
// АВТОМАТИЧЕСКОЕ ДОБАВЛЕНИЕ КРИПТОВАЛЮТ В ТАБЛИЦУ currencies
// ============================================================================

// Получаем все криптовалюты из assets
$stmt = $pdo->query("
    SELECT DISTINCT symbol, name 
    FROM assets 
    WHERE type = 'crypto'
");
$crypto_assets = $stmt->fetchAll();

foreach ($crypto_assets as $crypto) {
    // Проверяем, существует ли валюта в таблице currencies
    $check = $pdo->prepare("SELECT code FROM currencies WHERE code = ?");
    $check->execute([$crypto['symbol']]);
    $exists = $check->fetch();
    
    if (!$exists) {
        // Добавляем валюту
        $stmt = $pdo->prepare("
            INSERT INTO currencies (code, name, type) 
            VALUES (?, ?, 'crypto')
        ");
        $stmt->execute([$crypto['symbol'], $crypto['name']]);
    }
}

// Обновляем список валют после добавления
$all_currencies = $pdo->query("SELECT code, name, symbol, type FROM currencies ORDER BY code")->fetchAll();

// Фиатные валюты для выпадающих списков
$fiat_currencies = $pdo->query("
    SELECT code, name, symbol 
    FROM currencies 
    WHERE code IN ('RUB', 'USD', 'EUR', 'GBP', 'CNY', 'JPY', 'CHF', 'CAD', 'AUD')
    ORDER BY FIELD(code, 'RUB', 'USD', 'EUR', 'GBP', 'CNY', 'JPY', 'CHF', 'CAD', 'AUD')
")->fetchAll();

// Все валюты (включая крипто)
$all_currencies = $pdo->query("SELECT code, name, symbol, type FROM currencies ORDER BY code")->fetchAll();

// ============================================================================
// РАСЧЕТ СТРУКТУРЫ ПОРТФЕЛЯ (ПО ИСТОРИЧЕСКОЙ СТОИМОСТИ)
// ============================================================================

$total_portfolio = $total_usd; // Используем историческую стоимость из шапки
if ($total_portfolio <= 0) $total_portfolio = 1;

// Получаем все активы из портфеля для группировки по типам
$stmt = $pdo->query("
    SELECT 
        a.type,
        a.symbol,
        a.currency_code,
        p.quantity,
        p.average_buy_price
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
");
$all_assets = $stmt->fetchAll();

// Инициализируем категории
$categories = [
    'Рубли' => 0,           // Только RUB
    'Фиат (USD/EUR)' => 0,  // USD, EUR и другие фиаты (без RUB)
    'Крипто' => 0,          // Все криптоактивы (включая USDT)
    'Фондовый (EN)' => 0,
    'Фондовый (РФ)' => 0,
    'Фондовый (прочее)' => 0,
    'Облигации' => 0,
    'Вклады' => 0,
    'Другие' => 0
];

// Группируем активы по категориям
foreach ($all_assets as $asset) {
    $value = 0;
    
    // Обрабатываем валюты отдельно
    if ($asset['symbol'] == 'RUB') {
        $value = $asset['quantity'] / $usd_rub_rate;
        $categories['Рубли'] += $value;
    }
    elseif ($asset['symbol'] == 'USD' || $asset['symbol'] == 'EUR') {
        // Фиатные валюты (кроме RUB)
        $value = $asset['quantity'];
        $categories['Фиат (USD/EUR)'] += $value;
    }
    elseif ($asset['symbol'] == 'USDT' || $asset['symbol'] == 'USDC') {
        // Стейблкоины - относим к крипто
        // Пропускаем здесь, добавим позже через $total_usdt_bought
    }
    elseif ($asset['average_buy_price'] > 0) {
        $value = $asset['quantity'] * $asset['average_buy_price'];
        
        switch ($asset['type']) {
            case 'crypto':
                // Пропускаем крипто здесь, добавим позже
                break;
            case 'stock':
            case 'etf':
                if ($asset['currency_code'] == 'USD') {
                    $categories['Фондовый (EN)'] += $value;
                } elseif ($asset['currency_code'] == 'RUB') {
                    $categories['Фондовый (РФ)'] += $value;
                } else {
                    $categories['Фондовый (прочее)'] += $value;
                }
                break;
            case 'bond':
                $categories['Облигации'] += $value;
                break;
            case 'deposit':
                $categories['Вклады'] += $value;
                break;
            default:
                $categories['Другие'] += $value;
                break;
        }
    }
}

// Устанавливаем значение для крипто - ВСЕ криптоактивы (включая USDT)
$categories['Крипто'] = $total_usdt_bought; // Общая сумма всех покупок крипто

// Формируем структуру для отображения
$portfolio_structure = [];
foreach ($categories as $category => $value) {
    if ($value > 0) {
        $portfolio_structure[] = [
            'category' => $category,
            'value' => $value,
            'percentage' => round(($value / $total_portfolio) * 100, 2)
        ];
    }
}

// Сортируем по убыванию
usort($portfolio_structure, function($a, $b) {
    return $b['value'] <=> $a['value'];
});

// Корректируем проценты до 100%
$total_percentage = array_sum(array_column($portfolio_structure, 'percentage'));
if (abs($total_percentage - 100) > 0.1 && !empty($portfolio_structure)) {
    $portfolio_structure[0]['percentage'] += (100 - $total_percentage);
}

if (empty($portfolio_structure)) {
    $portfolio_structure = [['category' => 'Нет данных', 'value' => 0, 'percentage' => 100]];
}

$total_portfolio_value = $total_portfolio;

// ============================================================================
// ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ ГРАФИКОВ
// ============================================================================

// Функция для перевода названий секторов
function translateSector($sectorName) {
    $translations = [
        'Technology' => 'Технологии',
        'Healthcare' => 'Здравоохранение',
        'Financial' => 'Финансы',
        'Financial Services' => 'Финансовые услуги',
        'Energy' => 'Энергетика',
        'Consumer Cyclical' => 'Потребительский сектор',
        'Consumer Defensive' => 'Защитный сектор',
        'Consumer Goods' => 'Товары народного потребления',
        'Industrials' => 'Промышленность',
        'Communication Services' => 'Связь и медиа',
        'Utilities' => 'Коммунальные услуги',
        'Real Estate' => 'Недвижимость',
        'Basic Materials' => 'Сырьевые материалы',
        'Materials' => 'Материалы',
        'Другое' => 'Другое'
    ];
    
    return $translations[$sectorName] ?? $sectorName;
}

// Получаем распределение по секторам для иностранных акций
$stmt = $pdo->query("
    SELECT 
        COALESCE(a.sector, 'Другое') as sector_name,
        SUM(p.quantity * COALESCE(p.average_buy_price, 0)) as total_value
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
    WHERE a.type IN ('stock', 'etf') 
        AND (a.currency_code = 'USD' OR a.symbol LIKE '%.US')
        AND p.quantity > 0
    GROUP BY a.sector
    ORDER BY total_value DESC
");

$sector_data = $stmt->fetchAll();
$total = 0;
$en_sectors = [];

// Сначала считаем общую сумму
foreach ($sector_data as $row) {
    $total += $row['total_value'];
}

// Затем рассчитываем проценты и сохраняем стоимость
foreach ($sector_data as $row) {
    if ($total > 0) {
        $percentage = round(($row['total_value'] / $total) * 100, 2);
        if ($percentage > 0) {
            $en_sectors[] = [
                'original_name' => $row['sector_name'],  // <-- СОХРАНЯЕМ ОРИГИНАЛ
                'sector_name' => translateSector($row['sector_name']), // <-- ПЕРЕВЕДЕННОЕ
                'percentage' => $percentage,
                'value_usd' => $row['total_value']
            ];
        }
    }
}

$has_en_data = !empty($en_sectors);
if (!$has_en_data) {
    $en_sectors = [];
}

$stmt = $pdo->query("
    SELECT 
        sector_name,
        percentage
    FROM stock_sectors_ru 
    ORDER BY percentage DESC
");
$ru_sectors = $stmt->fetchAll();

$has_ru_data = false;
foreach ($ru_sectors as $sector) {
    if ($sector['percentage'] > 0) {
        $has_ru_data = true;
        break;
    }
}
if (!$has_ru_data) {
    $ru_sectors = [];
}

$stmt = $pdo->query("
    SELECT 
        dc.currency_code,
        COALESCE(c.name, dc.currency_code) as name,
        c.symbol,
        dc.percentage
    FROM deposit_currencies dc
    LEFT JOIN currencies c ON dc.currency_code = c.code COLLATE utf8mb4_unicode_ci
    ORDER BY dc.percentage DESC
");
$deposit_currencies = $stmt->fetchAll();

$has_deposit_data = false;
foreach ($deposit_currencies as $currency) {
    if ($currency['percentage'] > 0) {
        $has_deposit_data = true;
        break;
    }
}
if (!$has_deposit_data) {
    $deposit_currencies = [];
}

// ============================================================================
// ФУНКЦИИ ДЛЯ РАБОТЫ С ДАННЫМИ
// ============================================================================

function addTrade($pdo, $type, $platform_id, $from_platform_id, $asset_id, $quantity, $price, $price_currency, $commission, $commission_currency, $network, $date, $notes = '') {
    try {        
        $pdo->beginTransaction();
        
        // Преобразуем пустые строки в NULL
        $commission_currency = !empty($commission_currency) ? $commission_currency : null;
        $network = !empty($network) ? $network : null;
        
        // Добавляем запись о сделке
        $stmt = $pdo->prepare("
            INSERT INTO trades (
                operation_type, asset_id, platform_id, from_platform_id, quantity, price, 
                price_currency, commission, commission_currency, network, 
                operation_date, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$type, $asset_id, $platform_id, $from_platform_id, $quantity, $price, 
                        $price_currency, $commission, $commission_currency, 
                        $network, $date, $notes]);
        
        $trade_id = $pdo->lastInsertId();
        
        if ($type == 'buy') {
            // Получаем символ актива для коррекции цены USDT/USDC
            $stmt = $pdo->prepare("SELECT symbol FROM assets WHERE id = ?");
            $stmt->execute([$asset_id]);
            $asset_info = $stmt->fetch();
            $symbol = $asset_info['symbol'];

            // Корректируем цену для USDT и USDC, чтобы она равнялась средневзвешенной цене USD
            if ($symbol == 'USDT' || $symbol == 'USDC') {
                // Получаем средневзвешенную цену из всех долларовых активов
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(quantity * price) / NULLIF(SUM(quantity), 0), 1) as usd_avg_price
                    FROM trades 
                    WHERE asset_id IN (
                        SELECT id FROM assets WHERE symbol IN ('USD', 'USDT', 'USDC')
                    )
                    AND operation_type = 'buy'
                ");
                $stmt->execute();
                $usd_price = $stmt->fetch();
                
                if ($usd_price['usd_avg_price'] > 0) {
                    $price = $usd_price['usd_avg_price'];
                }
            }

            // ============================================================================
            // 1. СПИСЫВАЕМ СРЕДСТВА (валюту, которой оплачиваем)
            // ============================================================================

            // Находим актив для оплаты
            $stmt = $pdo->prepare("
                SELECT id FROM assets 
                WHERE symbol = ? AND (type = 'currency' OR type = 'crypto')
            ");
            $stmt->execute([$price_currency]);
            $payment_asset = $stmt->fetch();
            
            if (!$payment_asset) {
                // Пробуем найти без фильтра по типу
                $stmt = $pdo->prepare("
                    SELECT id FROM assets 
                    WHERE symbol = ?
                ");
                $stmt->execute([$price_currency]);
                $payment_asset = $stmt->fetch();
                
                if (!$payment_asset) {
                    throw new Exception("Актив для оплаты не найден: " . $price_currency . ". Проверьте, существует ли такая валюта в системе.");
                }
            }
            
            $payment_asset_id = $payment_asset['id'];
            
            // Рассчитываем общую стоимость покупки
            $total_cost = $quantity * $price;
            
            // Проверяем наличие средств
            $stmt = $pdo->prepare("
                SELECT id, quantity 
                FROM portfolio 
                WHERE asset_id = ? AND platform_id = ?
            ");
            $stmt->execute([$payment_asset_id, $from_platform_id]);
            $payment_portfolio = $stmt->fetch();
            
            if (!$payment_portfolio) {
                throw new Exception("Нет средств для оплаты на платформе. Актив: " . $price_currency . ". Возможно, у вас нет этого актива на выбранной площадке.");
            }
            
            if ($payment_portfolio['quantity'] < $total_cost) {
                throw new Exception("Недостаточно средств. Доступно: " . number_format($payment_portfolio['quantity'], 2) . " " . $price_currency . ", нужно: " . number_format($total_cost, 2) . " " . $price_currency);
            }
            
            // Списываем средства
            $new_payment_quantity = $payment_portfolio['quantity'] - $total_cost;
            
            if ($new_payment_quantity > 0) {
                $stmt = $pdo->prepare("
                    UPDATE portfolio 
                    SET quantity = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_payment_quantity, $payment_portfolio['id']]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                $stmt->execute([$payment_portfolio['id']]);
            }
            
            // ============================================================================
            // 2. ДОБАВЛЯЕМ КУПЛЕННЫЙ АКТИВ
            // ============================================================================
            
            // Проверяем наличие актива
            $stmt = $pdo->prepare("
                SELECT id, quantity, average_buy_price 
                FROM portfolio 
                WHERE asset_id = ? AND platform_id = ?
            ");
            $stmt->execute([$asset_id, $platform_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Пересчитываем среднюю цену
                $new_quantity = $existing['quantity'] + $quantity;
                $new_avg_price = (($existing['quantity'] * $existing['average_buy_price']) + ($quantity * $price)) / $new_quantity;
                
                $stmt = $pdo->prepare("
                    UPDATE portfolio 
                    SET quantity = ?, average_buy_price = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_quantity, $new_avg_price, $existing['id']]);
            } else {
                // Добавляем новый актив
                $stmt = $pdo->prepare("
                    INSERT INTO portfolio (asset_id, platform_id, quantity, average_buy_price, currency_code)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$asset_id, $platform_id, $quantity, $price, $price_currency]);
            }
            
        } elseif ($type == 'sell') {
            // ============================================================================
            // ЛОГИКА ПРОДАЖИ
            // ============================================================================
            
            // Проверяем наличие актива
            $stmt = $pdo->prepare("
                SELECT id, quantity 
                FROM portfolio 
                WHERE asset_id = ? AND platform_id = ?
            ");
            $stmt->execute([$asset_id, $platform_id]);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                throw new Exception("Нет актива для продажи. Возможно, вы не покупали этот актив или он находится на другой площадке.");
            }
            
            if ($existing['quantity'] < $quantity) {
                throw new Exception("Недостаточно актива для продажи. Доступно: " . number_format($existing['quantity'], 6) . " " . $existing['symbol'] . ", нужно: " . number_format($quantity, 6));
            }
            
            // Уменьшаем количество
            $new_quantity = $existing['quantity'] - $quantity;
            
            if ($new_quantity > 0) {
                $stmt = $pdo->prepare("
                    UPDATE portfolio 
                    SET quantity = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_quantity, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                $stmt->execute([$existing['id']]);
            }
            
            // Добавляем полученные средства
            $total_income = $quantity * $price;
            
            $stmt = $pdo->prepare("
                SELECT id FROM assets 
                WHERE symbol = ? AND (type = 'currency' OR type = 'crypto')
            ");
            $stmt->execute([$price_currency]);
            $currency_asset = $stmt->fetch();
            
            if ($currency_asset) {
                $stmt = $pdo->prepare("
                    INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ");
                $stmt->execute([$currency_asset['id'], $platform_id, $total_income, $price_currency]);
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => ''];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function addDeposit($pdo, $platform_id, $amount, $currency_code, $date, $notes = '') {
    try {
        $pdo->beginTransaction();
        
        // Добавляем запись о пополнении
        $stmt = $pdo->prepare("
            INSERT INTO deposits (platform_id, amount, currency_code, deposit_date, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$platform_id, $amount, $currency_code, $date, $notes]);
        
        $deposit_id = $pdo->lastInsertId();
        
        // Находим или создаем актив для валюты
        $stmt = $pdo->prepare("
            SELECT id FROM assets WHERE symbol = ?
        ");
        $stmt->execute([$currency_code]);
        $asset = $stmt->fetch();
        
        if (!$asset) {
            // Создаем актив, если его нет
            $stmt = $pdo->prepare("
                INSERT INTO assets (symbol, name, type, currency_code, is_active)
                VALUES (?, ?, 'currency', ?, 1)
            ");
            $stmt->execute([$currency_code, $currency_code, $currency_code]);
            $asset_id = $pdo->lastInsertId();
        } else {
            $asset_id = $asset['id'];
        }
        
        // Добавляем в портфель
        $stmt = $pdo->prepare("
            INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->execute([$asset_id, $platform_id, $amount, $currency_code]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function addTransfer($pdo, $from_platform_id, $to_platform_id, $asset_id, $quantity, 
                     $commission, $commission_currency, $from_network, $to_network, $date, $notes = '') {
    try {
        $pdo->beginTransaction();
        
        // Преобразуем пустые строки в NULL
        $commission_currency = !empty($commission_currency) ? $commission_currency : null;
        $from_network = !empty($from_network) ? $from_network : null;
        $to_network = !empty($to_network) ? $to_network : null;
        
        // Добавляем запись о переводе
        $stmt = $pdo->prepare("
            INSERT INTO transfers (
                from_platform_id, to_platform_id, asset_id, quantity,
                commission, commission_currency, from_network, to_network,
                transfer_date, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $from_platform_id, $to_platform_id, $asset_id, $quantity,
            $commission, $commission_currency, $from_network, $to_network,
            $date, $notes
        ]);
        
        $transfer_id = $pdo->lastInsertId();
        
        // Проверяем наличие актива у отправителя
        $stmt = $pdo->prepare("
            SELECT id, quantity FROM portfolio 
            WHERE asset_id = ? AND platform_id = ?
        ");
        $stmt->execute([$asset_id, $from_platform_id]);
        $from_portfolio = $stmt->fetch();
        
        if (!$from_portfolio) {
            throw new Exception("У отправителя нет этого актива на выбранной площадке");
        }
        
        if ($from_portfolio['quantity'] < $quantity) {
            throw new Exception("Недостаточно средств для перевода. Доступно: " . $from_portfolio['quantity'] . ", нужно: " . $quantity);
        }
        
        // Уменьшаем количество на платформе отправителя
        $new_quantity = $from_portfolio['quantity'] - $quantity;
        
        if ($new_quantity > 0) {
            $stmt = $pdo->prepare("
                UPDATE portfolio 
                SET quantity = ? 
                WHERE id = ?
            ");
            $stmt->execute([$new_quantity, $from_portfolio['id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
            $stmt->execute([$from_portfolio['id']]);
        }
        
        // Получаем валюту актива
        $stmt = $pdo->prepare("SELECT currency_code FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch();
        $currency_code = $asset['currency_code'] ?? null;
        
        // Добавляем на платформу получателя
        $stmt = $pdo->prepare("
            INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->execute([$asset_id, $to_platform_id, $quantity, $currency_code]);
        
        // Обрабатываем комиссию, если есть
        if ($commission > 0 && !empty($commission_currency)) {
            // Находим актив для комиссии
            $stmt = $pdo->prepare("SELECT id FROM assets WHERE symbol = ?");
            $stmt->execute([$commission_currency]);
            $commission_asset = $stmt->fetch();
            
            if ($commission_asset) {
                // Проверяем наличие комиссии у отправителя
                $stmt = $pdo->prepare("
                    SELECT id, quantity FROM portfolio 
                    WHERE asset_id = ? AND platform_id = ?
                ");
                $stmt->execute([$commission_asset['id'], $from_platform_id]);
                $commission_portfolio = $stmt->fetch();
                
                if ($commission_portfolio && $commission_portfolio['quantity'] >= $commission) {
                    $new_commission_quantity = $commission_portfolio['quantity'] - $commission;
                    
                    if ($new_commission_quantity > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE portfolio 
                            SET quantity = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$new_commission_quantity, $commission_portfolio['id']]);
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                        $stmt->execute([$commission_portfolio['id']]);
                    }
                }
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => ''];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ РАБОТЫ С КАТЕГОРИЯМИ РАСХОДОВ
// ============================================================================

function addExpenseCategory($pdo, $name, $name_ru, $icon, $color) {
    try {
        // Проверяем существование
        $check = $pdo->prepare("SELECT id FROM expense_categories WHERE name = ?");
        $check->execute([$name]);
        $existing = $check->fetch();
        
        if ($existing) {
            return ['success' => false, 'message' => 'Категория с таким названием уже существует'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO expense_categories (name, name_ru, icon, color, sort_order)
            VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM expense_categories))
        ");
        $stmt->execute([$name, $name_ru, $icon, $color]);
        
        return [
            'success' => true, 
            'message' => 'Категория добавлена',
            'category_id' => $pdo->lastInsertId()
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Ошибка при добавлении категории: ' . $e->getMessage()];
    }
}

function getExpenseCategories($pdo, $include_inactive = false) {
    try {
        $sql = "SELECT * FROM expense_categories";
        if (!$include_inactive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order, name_ru";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function updateExpenseCategory($pdo, $id, $data) {
    try {
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['name_ru'])) {
            $fields[] = "name_ru = ?";
            $params[] = $data['name_ru'];
        }
        if (isset($data['icon'])) {
            $fields[] = "icon = ?";
            $params[] = $data['icon'];
        }
        if (isset($data['color'])) {
            $fields[] = "color = ?";
            $params[] = $data['color'];
        }
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = $data['is_active'];
        }
        if (isset($data['sort_order'])) {
            $fields[] = "sort_order = ?";
            $params[] = $data['sort_order'];
        }
        
        if (empty($fields)) {
            return ['success' => false, 'message' => 'Нет данных для обновления'];
        }
        
        $params[] = $id;
        $sql = "UPDATE expense_categories SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return ['success' => true, 'message' => 'Категория обновлена'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Ошибка при обновлении: ' . $e->getMessage()];
    }
}

function deleteExpenseCategory($pdo, $id) {
    try {
        // Проверяем, есть ли расходы в этой категории
        $check = $pdo->prepare("SELECT COUNT(*) as count FROM expenses WHERE category_id = ?");
        $check->execute([$id]);
        $result = $check->fetch();
        
        if ($result['count'] > 0) {
            // Если есть расходы, просто деактивируем категорию
            $stmt = $pdo->prepare("UPDATE expense_categories SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Категория деактивирована (в ней есть расходы)'];
        } else {
            // Если расходов нет, удаляем
            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Категория удалена'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Ошибка при удалении: ' . $e->getMessage()];
    }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ РАБОТЫ С РАСХОДАМИ
// ============================================================================

function addExpense($pdo, $amount, $currency_code, $category_id, $description, $date) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO expenses (amount, currency_code, category_id, description, expense_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$amount, $currency_code, $category_id, $description, $date]);
        
        return ['success' => true, 'message' => 'Расход успешно добавлен', 'id' => $pdo->lastInsertId()];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Ошибка при добавлении расхода: ' . $e->getMessage()];
    }
}

function getExpenses($pdo, $limit = 10, $offset = 0, $category_id = null, $date_from = null, $date_to = null) {
    try {
        $sql = "
            SELECT e.*, c.name, c.name_ru, c.icon, c.color
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($category_id) {
            $sql .= " AND e.category_id = ?";
            $params[] = $category_id;
        }
        
        if ($date_from) {
            $sql .= " AND e.expense_date >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND e.expense_date <= ?";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY e.expense_date DESC, e.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();
        
        // Получаем общую сумму
        $sql_total = "SELECT SUM(amount) as total FROM expenses WHERE 1=1";
        $params_total = [];
        
        if ($category_id) {
            $sql_total .= " AND category_id = ?";
            $params_total[] = $category_id;
        }
        
        if ($date_from) {
            $sql_total .= " AND expense_date >= ?";
            $params_total[] = $date_from;
        }
        
        if ($date_to) {
            $sql_total .= " AND expense_date <= ?";
            $params_total[] = $date_to;
        }
        
        $stmt_total = $pdo->prepare($sql_total);
        $stmt_total->execute($params_total);
        $total = $stmt_total->fetch();
        
        // Получаем статистику по категориям
        $sql_stats = "
            SELECT 
                e.category_id,
                c.name,
                c.name_ru,
                c.icon,
                c.color,
                SUM(e.amount) as total_amount,
                COUNT(*) as count
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            WHERE 1=1
        ";
        $params_stats = [];
        
        if ($date_from) {
            $sql_stats .= " AND e.expense_date >= ?";
            $params_stats[] = $date_from;
        }
        
        if ($date_to) {
            $sql_stats .= " AND e.expense_date <= ?";
            $params_stats[] = $date_to;
        }
        
        $sql_stats .= " GROUP BY e.category_id ORDER BY total_amount DESC";
        
        $stmt_stats = $pdo->prepare($sql_stats);
        $stmt_stats->execute($params_stats);
        $stats = $stmt_stats->fetchAll();
        
        return [
            'success' => true,
            'expenses' => $expenses,
            'total' => $total['total'] ?? 0,
            'stats' => $stats
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteExpense($pdo, $expense_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$expense_id]);
        return ['success' => true, 'message' => 'Расход удален'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Ошибка при удалении: ' . $e->getMessage()];
    }
}

// ============================================================================
// ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ ИСТОРИИ ПОКУПОК АКТИВА НА ПЛОЩАДКЕ
// ============================================================================

function getPurchaseHistory($pdo, $asset_id, $platform_id) {
    try {
        $stmt = $pdo->prepare("
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
        $stmt->execute([$asset_id, $platform_id]);
        $purchases = $stmt->fetchAll();
        
        // Получаем текущий остаток актива на площадке
        $stmt = $pdo->prepare("
            SELECT quantity, average_buy_price
            FROM portfolio
            WHERE asset_id = ? AND platform_id = ?
        ");
        $stmt->execute([$asset_id, $platform_id]);
        $current = $stmt->fetch();
        
        return [
            'purchases' => $purchases,
            'current_quantity' => $current ? floatval($current['quantity']) : 0,
            'avg_buy_price' => $current ? floatval($current['average_buy_price']) : 0
        ];
    } catch (Exception $e) {
        return [
            'purchases' => [],
            'current_quantity' => 0,
            'avg_buy_price' => 0,
            'error' => $e->getMessage()
        ];
    }
}

// ============================================================================
// ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ БАЛАНСА АКТИВОВ НА ПЛОЩАДКЕ
// ============================================================================

function getPlatformBalance($pdo, $platform_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.id as asset_id,
                a.symbol,
                a.name as asset_name,
                a.type as asset_type,
                p.quantity,
                p.average_buy_price,
                p.currency_code,
                CASE 
                    WHEN a.symbol = 'RUB' THEN p.quantity / (SELECT rate FROM exchange_rates WHERE from_currency = 'USD' AND to_currency = 'RUB' ORDER BY date DESC LIMIT 1)
                    WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN p.quantity
                    ELSE p.quantity * COALESCE(p.average_buy_price, 0)
                END as value_usd
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            WHERE p.platform_id = ? AND p.quantity > 0
            ORDER BY value_usd DESC
        ");
        $stmt->execute([$platform_id]);
        $assets = $stmt->fetchAll();
        
        // Рассчитываем общую стоимость
        $total_value_usd = array_sum(array_column($assets, 'value_usd'));
        
        return [
            'success' => true,
            'assets' => $assets,
            'total_value_usd' => $total_value_usd,
            'total_value_rub' => $total_value_usd * (isset($GLOBALS['usd_rub_rate']) ? $GLOBALS['usd_rub_rate'] : 92.50)
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'assets' => [],
            'total_value_usd' => 0,
            'total_value_rub' => 0
        ];
    }
}

// ============================================================================
// ОБРАБОТКА POST ЗАПРОСОВ
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Очищаем буфер вывода перед отправкой JSON
    if (ob_get_length()) ob_clean();

    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_trade':
                $result = addTrade(
                    $pdo,
                    $_POST['operation_type'],
                    $_POST['platform_id'],
                    $_POST['from_platform_id'],
                    $_POST['asset_id'],
                    $_POST['quantity'],
                    $_POST['price'],
                    $_POST['price_currency'],
                    $_POST['commission'] ?? 0,
                    $_POST['commission_currency'] ?? null,
                    $_POST['network'] ?? null,
                    $_POST['operation_date'],
                    $_POST['notes'] ?? ''
                );
                $response['success'] = $result['success'];
                $response['message'] = $result['success'] ? 'Операция успешно добавлена' : 'Ошибка: ' . $result['message'];
                break;

            case 'add_deposit':
                $result = addDeposit(
                    $pdo,
                    $_POST['platform_id'],
                    $_POST['amount'],
                    $_POST['currency'],
                    $_POST['deposit_date'],
                    $_POST['notes'] ?? ''
                );
                $response['success'] = $result;
                $response['message'] = $result ? 'Пополнение успешно добавлено' : 'Ошибка при добавлении пополнения';
                break;
                
            case 'add_transfer':
                $result = addTransfer(
                    $pdo,
                    $_POST['from_platform_id'],
                    $_POST['to_platform_id'],
                    $_POST['asset_id'],
                    $_POST['quantity'],
                    $_POST['commission'] ?? 0,
                    $_POST['commission_currency'] ?? null,
                    $_POST['from_network'] ?? null,
                    $_POST['to_network'] ?? null,
                    $_POST['transfer_date'],
                    $_POST['notes'] ?? ''
                );
                $response['success'] = $result['success'];
                $response['message'] = $result['success'] ? 'Перевод успешно добавлен' : 'Ошибка: ' . $result['message'];
                break;
                
            case 'add_platform':
            case 'add_platform_full':
                $name = trim($_POST['name']);
                $type = $_POST['type'] ?? 'other';
                $country = $_POST['country'] ?? null;
                
                if (empty($name)) {
                    $response['message'] = 'Название площадки обязательно';
                    break;
                }
                
                try {
                    // Проверяем существование
                    $check = $pdo->prepare("SELECT id FROM platforms WHERE name = ?");
                    $check->execute([$name]);
                    $existing = $check->fetch();
                    
                    if ($existing) {
                        $response['success'] = true;
                        $response['message'] = 'Площадка уже существует';
                        $response['platform_id'] = $existing['id'];
                    } else {
                        $type_mapping = [
                            'exchange' => 'exchange',
                            'broker' => 'broker',
                            'bank' => 'bank',
                            'wallet' => 'wallet',
                            'other' => 'other'
                        ];
                        
                        $db_type = $type_mapping[$type] ?? 'other';
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO platforms (name, type, country, is_active) 
                            VALUES (?, ?, ?, 1)
                        ");
                        $stmt->execute([$name, $db_type, $country]);
                        
                        $response['success'] = true;
                        $response['message'] = 'Площадка успешно добавлена';
                        $response['platform_id'] = $pdo->lastInsertId();
                    }
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при добавлении площадки: ' . $e->getMessage();
                }
                break;
                
            case 'add_currency_full':
                $code = strtoupper(trim($_POST['code']));
                $name = trim($_POST['name']);
                $type = $_POST['type'] ?? 'fiat';
                $symbol = $_POST['symbol'] ?? null;
                
                if (empty($code) || empty($name)) {
                    $response['message'] = 'Код и название валюты обязательны';
                    break;
                }
                
                try {
                    $check = $pdo->prepare("SELECT code FROM currencies WHERE code = ?");
                    $check->execute([$code]);
                    $existing = $check->fetch();
                    
                    if ($existing) {
                        $response['success'] = true;
                        $response['message'] = 'Валюта уже существует';
                        $response['currency_id'] = $code;
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO currencies (code, name, type, symbol) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$code, $name, $type, $symbol]);
                        
                        // Также добавляем как актив
                        $asset_type = ($type == 'fiat') ? 'currency' : 'crypto';
                        $stmt = $pdo->prepare("
                            INSERT INTO assets (symbol, name, type, currency_code, is_active)
                            VALUES (?, ?, ?, ?, 1)
                            ON DUPLICATE KEY UPDATE name = VALUES(name)
                        ");
                        $stmt->execute([$code, $name, $asset_type, $code]);
                        
                        $response['success'] = true;
                        $response['message'] = 'Валюта успешно добавлена';
                        $response['currency_id'] = $code;
                    }
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при добавлении валюты: ' . $e->getMessage();                    
                }
                break;
                
            case 'add_asset':
            case 'add_asset_full':
                $symbol = $_POST['symbol'] ?? '';
                $name = $_POST['name'] ?? '';
                $type = $_POST['type'] ?? 'other';
                $currency_code = $_POST['currency_code'] ?? null;
                $sector = $_POST['sector'] ?? null;
                
                if (empty($symbol) || empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Символ и название обязательны']);
                    break;
                }
                
                // Проверяем, не существует ли уже такой актив
                $check = $pdo->prepare("SELECT id FROM assets WHERE symbol = ?");
                $check->execute([$symbol]);
                $existing = $check->fetch();
                
                if ($existing) {
                    echo json_encode(['success' => false, 'message' => 'Актив с таким символом уже существует']);
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO assets (symbol, name, type, currency_code, sector, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$symbol, $name, $type, $currency_code, $sector]);
                    
                    $asset_id = $pdo->lastInsertId();
                    
                    // Очищаем буфер и отправляем JSON
                    if (ob_get_length()) ob_clean();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Актив успешно добавлен',
                        'asset_id' => $asset_id
                    ]);
                    exit; // ВАЖНО: завершаем выполнение скрипта
                    
                } catch (PDOException $e) {
                    if (ob_get_length()) ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
                    exit; // ВАЖНО: завершаем выполнение скрипта
                }
                break;
            case 'save_theme':
                $theme = $_POST['theme'] ?? 'light';
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (setting_key, setting_value) 
                        VALUES ('theme', ?) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$theme]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Тема сохранена';
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при сохранении темы';                    
                }
                break;
            case 'add_limit_order':
                $operation_type = $_POST['operation_type'] ?? 'buy';
                $platform_id = $_POST['platform_id'] ?? 0;
                $asset_id = $_POST['asset_id'] ?? 0;
                $quantity = $_POST['quantity'] ?? 0;
                $limit_price = $_POST['limit_price'] ?? 0;
                $price_currency = $_POST['price_currency'] ?? 'USD';
                $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
                $notes = $_POST['notes'] ?? '';
                
                if (!$platform_id || !$asset_id || $quantity <= 0 || $limit_price <= 0) {
                    $response['message'] = 'Заполните все обязательные поля';
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO limit_orders (
                            operation_type, asset_id, platform_id, quantity, 
                            limit_price, price_currency, expiry_date, notes, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $operation_type, $asset_id, $platform_id, $quantity,
                        $limit_price, $price_currency, $expiry_date, $notes
                    ]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Лимитный ордер успешно создан';
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при создании ордера: ' . $e->getMessage();                    
                }
                break;
            case 'execute_limit_order':
                $order_id = $_POST['order_id'] ?? 0;
                
                try {
                    $pdo->beginTransaction();
                    
                    // Получаем данные ордера
                    $stmt = $pdo->prepare("
                        SELECT lo.*, a.symbol, a.type, a.id as asset_id,
                            p.id as platform_id, p.name as platform_name
                        FROM limit_orders lo
                        JOIN assets a ON lo.asset_id = a.id
                        JOIN platforms p ON lo.platform_id = p.id
                        WHERE lo.id = ? AND lo.status = 'active'
                    ");
                    $stmt->execute([$order_id]);
                    $order = $stmt->fetch();
                    
                    if (!$order) {
                        throw new Exception("Ордер не найден или уже не активен");
                    }
                    
                    // Проверяем наличие средств
                    if ($order['operation_type'] == 'buy') {
                        // Для покупки проверяем наличие валюты для оплаты
                        $stmt = $pdo->prepare("
                            SELECT id, quantity 
                            FROM portfolio 
                            WHERE asset_id = (
                                SELECT id FROM assets WHERE symbol = ?
                            ) AND platform_id = ?
                        ");
                        $stmt->execute([$order['price_currency'], $order['platform_id']]);
                        $payment_asset = $stmt->fetch();
                        
                        $total_cost = $order['quantity'] * $order['limit_price'];
                        
                        if (!$payment_asset || $payment_asset['quantity'] < $total_cost) {
                            throw new Exception("Недостаточно средств для исполнения ордера");
                        }
                        
                        // Исполняем ордер через существующую функцию addTrade
                        $result = addTrade(
                            $pdo,
                            'buy',
                            $order['platform_id'],
                            $order['platform_id'],
                            $order['asset_id'],
                            $order['quantity'],
                            $order['limit_price'],
                            $order['price_currency'],
                            0, // комиссия
                            null,
                            null,
                            date('Y-m-d'),
                            'Исполнение лимитного ордера #' . $order_id
                        );
                        
                    } else {
                        // Для продажи проверяем наличие актива
                        $stmt = $pdo->prepare("
                            SELECT id, quantity 
                            FROM portfolio 
                            WHERE asset_id = ? AND platform_id = ?
                        ");
                        $stmt->execute([$order['asset_id'], $order['platform_id']]);
                        $asset_portfolio = $stmt->fetch();
                        
                        if (!$asset_portfolio || $asset_portfolio['quantity'] < $order['quantity']) {
                            throw new Exception("Недостаточно актива для исполнения ордера");
                        }
                        
                        // Исполняем ордер через существующую функцию addTrade
                        $result = addTrade(
                            $pdo,
                            'sell',
                            $order['platform_id'],
                            $order['platform_id'],
                            $order['asset_id'],
                            $order['quantity'],
                            $order['limit_price'],
                            $order['price_currency'],
                            0, // комиссия
                            null,
                            null,
                            date('Y-m-d'),
                            'Исполнение лимитного ордера #' . $order_id
                        );
                    }
                    
                    if (!$result) {
                        throw new Exception("Ошибка при создании сделки");
                    }
                    
                    // Обновляем статус ордера
                    $stmt = $pdo->prepare("
                        UPDATE limit_orders 
                        SET status = 'executed', executed_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$order_id]);
                    
                    $pdo->commit();
                    
                    $response['success'] = true;
                    $response['message'] = 'Ордер успешно исполнен';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при исполнении ордера: ' . $e->getMessage();                    
                }
                break;
                
            case 'cancel_limit_order':
                // Временная отладка - покажем всё, что пришло                
                $order_id = $_POST['order_id'] ?? 0;
                
                try {
                    // Проверим, есть ли такой ордер
                    $check = $pdo->prepare("SELECT id, status FROM limit_orders WHERE id = ?");
                    $check->execute([$order_id]);
                    $order = $check->fetch();
                    
                    $stmt = $pdo->prepare("
                        UPDATE limit_orders 
                        SET status = 'cancelled' 
                        WHERE id = ? AND status = 'active'
                    ");
                    $stmt->execute([$order_id]);
                    
                    $affected = $stmt->rowCount();                    
                    
                    if ($affected > 0) {
                        $response['success'] = true;
                        $response['message'] = 'Ордер отменен';
                    } else {
                        $response['success'] = false;
                        $response['message'] = 'Ордер не найден или уже не активен';
                    }
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при отмене ордера: ' . $e->getMessage();
                }
                break;
            case 'add_note':
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $note_type = $_POST['note_type'] ?? 'general';
                $reminder_date = !empty($_POST['reminder_date']) ? $_POST['reminder_date'] : null;
                
                if (empty($content)) {
                    $response['message'] = 'Содержание заметки обязательно';
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO notes (title, content, note_type, reminder_date, is_archived, created_at)
                        VALUES (?, ?, ?, ?, 0, NOW())
                    ");
                    $stmt->execute([$title, $content, $note_type, $reminder_date]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Заметка успешно добавлена';
                    $response['note_id'] = $pdo->lastInsertId();
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при добавлении заметки: ' . $e->getMessage();                    
                }
                break;

            case 'update_note':
                $note_id = $_POST['note_id'] ?? 0;
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $note_type = $_POST['note_type'] ?? 'general';
                $reminder_date = !empty($_POST['reminder_date']) ? $_POST['reminder_date'] : null;
                
                if (!$note_id) {
                    $response['message'] = 'ID заметки не указан';
                    break;
                }
                
                if (empty($content)) {
                    $response['message'] = 'Содержание заметки обязательно';
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("
                        UPDATE notes 
                        SET title = ?, content = ?, note_type = ?, reminder_date = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $content, $note_type, $reminder_date, $note_id]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Заметка успешно обновлена';
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при обновлении заметки: ' . $e->getMessage();                    
                }
                break;

            case 'delete_note':
                $note_id = $_POST['note_id'] ?? 0;
                
                if (!$note_id) {
                    $response['message'] = 'ID заметки не указан';
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
                    $stmt->execute([$note_id]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Заметка успешно удалена';
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при удалении заметки: ' . $e->getMessage();                    
                }
                break;

            case 'archive_note':
                $note_id = $_POST['note_id'] ?? 0;
                $archive = $_POST['archive'] ?? 1; // 1 - архивировать, 0 - восстановить
                
                if (!$note_id) {
                    $response['message'] = 'ID заметки не указан';
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("UPDATE notes SET is_archived = ? WHERE id = ?");
                    $stmt->execute([$archive, $note_id]);
                    
                    $response['success'] = true;
                    $response['message'] = $archive ? 'Заметка архивирована' : 'Заметка восстановлена';
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при архивации заметки: ' . $e->getMessage();                    
                }
                break;
            case 'get_notes':
                $include_archived = $_POST['include_archived'] ?? 0;
                
                try {
                    $sql = "SELECT * FROM notes WHERE 1=1";
                    
                    // include_archived = 1 - показываем только архивированные
                    // include_archived = 0 - показываем только неархивированные
                    if ($include_archived == 1) {
                        $sql .= " AND is_archived = 1";
                    } else {
                        $sql .= " AND is_archived = 0";
                    }
                    
                    $sql .= " ORDER BY 
                                CASE note_type
                                    WHEN 'important' THEN 1
                                    WHEN 'reminder' THEN 2
                                    WHEN 'idea' THEN 3
                                    ELSE 4
                                END,
                                created_at DESC";
                    
                    $stmt = $pdo->query($sql);
                    $notes = $stmt->fetchAll();
                    
                    $response['success'] = true;
                    $response['notes'] = $notes;
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при загрузке заметок: ' . $e->getMessage();
                }
                break;
            case 'add_network':
                $name = strtoupper(trim($_POST['name']));
                $icon = $_POST['icon'] ?? 'fas fa-network-wired';
                $color = $_POST['color'] ?? '#ff9f4a';
                $full_name = trim($_POST['full_name'] ?? $name);
                
                if (empty($name)) {
                    $response['message'] = 'Название сети обязательно';
                    break;
                }
                
                try {
                    // Проверяем существование
                    $check = $pdo->prepare("SELECT id FROM networks WHERE name = ?");
                    $check->execute([$name]);
                    $existing = $check->fetch();
                    
                    if ($existing) {
                        $response['success'] = true;
                        $response['message'] = 'Сеть уже существует';
                        $response['network_id'] = $existing['id'];
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO networks (name, icon, color, full_name, is_active) 
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$name, $icon, $color, $full_name]);
                        
                        $response['success'] = true;
                        $response['message'] = 'Сеть успешно добавлена';
                        $response['network_id'] = $pdo->lastInsertId();
                    }
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Ошибка при добавлении сети: ' . $e->getMessage();                    
                }
                break;
            case 'get_purchase_history':
                $asset_id = $_POST['asset_id'] ?? 0;
                $platform_id = $_POST['platform_id'] ?? 0;
                
                if (!$asset_id || !$platform_id) {
                    $response = ['success' => false, 'message' => 'Не указан актив или площадка'];
                } else {
                    $history = getPurchaseHistory($pdo, $asset_id, $platform_id);
                    $response = [
                        'success' => true,
                        'data' => $history
                    ];
                }
                echo json_encode($response);
                exit;
                break;
            case 'get_platform_balance':
                $platform_id = $_POST['platform_id'] ?? 0;
                
                if (!$platform_id) {
                    $response = ['success' => false, 'message' => 'Не указана площадка'];
                } else {
                    $balance = getPlatformBalance($pdo, $platform_id);
                    $response = $balance;
                }
                echo json_encode($response);
                exit;
                break;
            case 'add_expense':
                $amount = floatval($_POST['amount'] ?? 0);
                $currency_code = strtoupper($_POST['currency_code'] ?? 'RUB');
                $category_id = intval($_POST['category_id'] ?? 0);  // ← Используем category_id
                $description = trim($_POST['description'] ?? '');
                $date = $_POST['expense_date'] ?? date('Y-m-d');
                
                if ($amount <= 0) {
                    $response = ['success' => false, 'message' => 'Сумма расхода должна быть больше 0'];
                    break;
                }
                
                if (!$category_id) {
                    $response = ['success' => false, 'message' => 'Выберите категорию расхода'];
                    break;
                }
                
                $result = addExpense($pdo, $amount, $currency_code, $category_id, $description, $date);
                $response = $result;
                break;
            case 'get_expenses':
                $limit = intval($_POST['limit'] ?? 10);
                $offset = intval($_POST['offset'] ?? 0);
                $category = $_POST['category'] ?? null;
                $date_from = $_POST['date_from'] ?? null;
                $date_to = $_POST['date_to'] ?? null;
                
                $result = getExpenses($pdo, $limit, $offset, $category, $date_from, $date_to);
                $response = $result;
                break;

            case 'delete_expense':
                $expense_id = intval($_POST['expense_id'] ?? 0);
                
                if (!$expense_id) {
                    $response = ['success' => false, 'message' => 'ID расхода не указан'];
                    break;
                }
                
                $result = deleteExpense($pdo, $expense_id);
                $response = $result;
                break;

            case 'get_expense_categories':
                $categories = getExpenseCategories($pdo);
                $response = ['success' => true, 'categories' => $categories];
                break;
            case 'add_expense_category':
                $name = strtolower(trim($_POST['name'] ?? ''));
                $name_ru = trim($_POST['name_ru'] ?? '');
                $icon = $_POST['icon'] ?? 'fas fa-tag';
                $color = $_POST['color'] ?? '#ff9f4a';
                
                if (empty($name) || empty($name_ru)) {
                    $response = ['success' => false, 'message' => 'Название категории обязательно'];
                    break;
                }
                
                $result = addExpenseCategory($pdo, $name, $name_ru, $icon, $color);
                $response = $result;
                break;

            case 'update_expense_category':
                $category_id = intval($_POST['category_id'] ?? 0);
                $data = [];
                
                if (isset($_POST['name'])) $data['name'] = strtolower(trim($_POST['name']));
                if (isset($_POST['name_ru'])) $data['name_ru'] = trim($_POST['name_ru']);
                if (isset($_POST['icon'])) $data['icon'] = $_POST['icon'];
                if (isset($_POST['color'])) $data['color'] = $_POST['color'];
                if (isset($_POST['is_active'])) $data['is_active'] = intval($_POST['is_active']);
                if (isset($_POST['sort_order'])) $data['sort_order'] = intval($_POST['sort_order']);
                
                if (!$category_id) {
                    $response = ['success' => false, 'message' => 'ID категории не указан'];
                    break;
                }
                
                $result = updateExpenseCategory($pdo, $category_id, $data);
                $response = $result;
                break;

            case 'delete_expense_category':
                $category_id = intval($_POST['category_id'] ?? 0);
                
                if (!$category_id) {
                    $response = ['success' => false, 'message' => 'ID категории не указан'];
                    break;
                }
                
                $result = deleteExpenseCategory($pdo, $category_id);
                $response = $result;
                break;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ============================================================================
// ПЕРЕДАЧА ДАННЫХ В JAVASCRIPT
// ============================================================================

$platforms_json = json_encode($platforms);
$assets_json = json_encode($assets_list);
$currencies_json = json_encode($all_currencies);
$fiat_currencies_json = json_encode($fiat_currencies);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Инвестиционный портфель | Дашборд</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
/* ============================================================================
   ОСНОВНЫЕ СТИЛИ
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

.dashboard {
    max-width: 1600px;
    margin: 0 auto;
}

/* ============================================================================
   HEADER - на всю ширину
   ============================================================================ */

.header {
    background: white;
    border-radius: 20px;
    padding: 24px 32px;
    margin-bottom: 24px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
    width: 100%;
}

.portfolio-value {
    display: flex;
    flex-direction: column;
}

.value-label {
    font-size: 14px;
    color: #6b7a8f;
    font-weight: 500;
}

.value-amount {
    font-size: 36px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1.2;
}

#rubValue {
    color: #1a5cff;
}

#usdValue {
    color: #00a86b;
}

.header-controls {
    display: flex;
    gap: 16px;
    align-items: center;
}

/* ============================================================================
   КОНТЕЙНЕР С КАРТОЧКАМИ (резиновая верстка)
   ============================================================================ */

.cards-container {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    width: 100%;
    margin-bottom: 24px;
}

/* Базовые стили для карточек */
.card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    transition: transform 0.2s, box-shadow 0.2s;
    flex: 0 1 auto;
    min-width: 280px;
    max-width: 100%;
}

.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-title i {
    color: #1a5cff;
}

.stat-badge {
    background: #f0f3f7;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

/* Специальные классы для карточек */
.card-structure {
    flex-basis: 380px;
}

.card-crypto {
    flex-basis: 320px;
}

.card-en-stocks {
    flex-basis: 340px;
}

.card-deposits {
    flex-basis: 300px;
}

.card-investments {
    flex-basis: 500px;
    flex-grow: 1;
}

.card-operations {
    flex-basis: 400px;
    flex-grow: 1;
}

.card-orders {
    flex-basis: 280px;
}

.card-plan {
    flex-basis: 350px;
}

.card-notes {
    flex-basis: 300px;
}

/* ============================================================================
   ДИАГРАММЫ
   ============================================================================ */

.pie-chart {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
}

.pie {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin: 15px auto;
    flex-shrink: 0;
}

.chart-legend {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 6px;
    width: 100%;
    margin-top: 10px;
    padding: 0 5px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    padding: 2px 0;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 4px;
    flex-shrink: 0;
}

.legend-value {
    font-weight: 600;
    margin-left: auto;
    color: #2c3e50;
}

/* ============================================================================
   ТАБЛИЦА АКТИВОВ
   ============================================================================ */

.investments-table {
    width: 100%;
    border-collapse: collapse;
}

.investments-table td {
    padding: 8px 4px;
    vertical-align: middle;
}

.investments-table tr {
    cursor: pointer;
    transition: background 0.2s;
}

.investments-table tr:hover {
    background: #f8fafd;
}

.investment-icon-cell {
    width: 50px;
}

.investment-name-cell {
    width: 80px;
}

.investment-amount-cell {
    text-align: right;
    padding-right: 12px !important;
}

.investment-change-cell {
    text-align: right;
}

.investment-icon {
    width: 36px;
    height: 36px;
    background: #f0f3f7;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #1a5cff;
}

.investment-name {
    font-weight: 500;
}

.investment-amount {
    font-weight: 600;
}

.investment-change {
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    display: inline-block;
    white-space: nowrap;
    background: #f0f3f7;
    color: #2c3e50;
}

/* ============================================================================
   ОРДЕРА
   ============================================================================ */

.order-card {
    background: #f8fafd;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    border-left: 4px solid;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    overflow: hidden;
}

.order-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.order-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, transparent 50%, rgba(255,255,255,0.1) 50%);
    pointer-events: none;
}

.order-exchange {
    font-size: 12px;
    color: #6b7a8f;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.order-exchange i {
    font-size: 10px;
}

.order-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
}

.order-action {
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.order-action i {
    font-size: 12px;
}

.order-price {
    font-weight: 600;
    color: #2c3e50;
    background: rgba(0,0,0,0.02);
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 13px;
}

.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
    font-size: 11px;
    color: #6b7a8f;
}

.order-progress {
    height: 4px;
    background: #edf2f7;
    border-radius: 2px;
    margin-top: 10px;
    overflow: hidden;
}

.order-progress-bar {
    height: 100%;
    background: #1a5cff;
    border-radius: 2px;
    transition: width 0.3s ease;
}

.order-progress-bar.warning {
    background: #ff9f4a;
}

.order-progress-bar.danger {
    background: #e53e3e;
}

.order-card[style*="border-left-color: #00a86b"] .order-action {
    color: #00a86b;
}

.order-card[style*="border-left-color: #00a86b"] .order-progress-bar {
    background: #00a86b;
}

.order-card[style*="border-left-color: #e53e3e"] .order-action {
    color: #e53e3e;
}

.order-card[style*="border-left-color: #e53e3e"] .order-progress-bar {
    background: #e53e3e;
}

.order-empty {
    text-align: center;
    padding: 30px 20px;
    color: #6b7a8f;
}

.order-empty i {
    font-size: 40px;
    opacity: 0.3;
    margin-bottom: 10px;
}

.order-empty p {
    font-size: 14px;
    margin-bottom: 15px;
}

.add-order-btn {
    background: #f0f3f7;
    border: 1px dashed #cbd5e0;
    border-radius: 30px;
    padding: 8px 16px;
    color: #2c3e50;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.add-order-btn:hover {
    background: #1a5cff;
    border-color: #1a5cff;
    color: white;
    transform: translateY(-1px);
}

/* ============================================================================
   ОПЕРАЦИИ
   ============================================================================ */

.operation-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
}

.operation-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.icon-buy {
    background: #e6f7e6;
    color: #00a86b;
}

.icon-sell {
    background: #ffe6e6;
    color: #e53e3e;
}

.icon-convert {
    background: #fff4e6;
    color: #ff9f4a;
}

.operation-details {
    flex: 1;
}

.operation-title {
    font-weight: 500;
    margin-bottom: 4px;
}

.operation-date {
    font-size: 12px;
    color: #6b7a8f;
}

/* ============================================================================
   ПЛАН ДЕЙСТВИЙ
   ============================================================================ */

.checklist-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
}

.checklist-checkbox {
    margin-right: 12px;
    width: 20px;
    height: 20px;
    border: 2px solid #d0d9e8;
    border-radius: 6px;
    cursor: pointer;
    position: relative;
}

.checklist-checkbox.checked {
    background: #1a5cff;
    border-color: #1a5cff;
}

.checklist-checkbox.checked::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    color: white;
    font-size: 12px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.checklist-text {
    flex: 1;
}

.checklist-text.completed {
    text-decoration: line-through;
    color: #95a5a6;
}

/* ============================================================================
   ЗАМЕТКИ
   ============================================================================ */

.note-item {
    background: #fef9e7;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    border-left: 4px solid #ffc107;
}

.note-date {
    font-size: 12px;
    color: #b88b16;
    font-weight: 600;
    margin-bottom: 4px;
}

.note-text {
    font-size: 14px;
}

/* ============================================================================
   КНОПКИ ОПЕРАЦИЙ И ТЕМЫ - ЕДИНЫЙ СТИЛЬ
   ============================================================================ */

.operation-type-btn,
.theme-toggle-btn {
    flex: 0 1 auto;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid transparent;
    background: #f0f3f7;
    color: #6b7a8f;
    font-weight: 500;
    padding: 10px 16px;
    border-radius: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    font-size: 14px;
    margin-left: 0;
}

.operation-type-btn:hover,
.theme-toggle-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(26, 92, 255, 0.15);
    background: white;
    border-color: #1a5cff;
    color: #1a5cff;
}

.operation-type-btn i,
.theme-toggle-btn i {
    transition: transform 0.3s ease, color 0.3s ease;
    font-size: 16px;
    display: inline-block;
}

.operation-type-btn[data-type="buy"]:hover i {
    transform: scale(1.2) translateY(2px);
}

.operation-type-btn[data-type="sell"]:hover i {
    transform: scale(1.2) translateY(-2px);
}

.operation-type-btn[data-type="transfer"]:hover i {
    transform: scale(1.2) rotate(180deg);
}

.operation-type-btn[data-type="deposit"]:hover i {
    transform: scale(1.2) rotate(90deg);
}

.theme-toggle-btn:hover i {
    transform: rotate(180deg);
}

/* ============================================================================
   БЫСТРЫЕ КНОПКИ
   ============================================================================ */

.quick-amount-btn,
.quick-platform-btn,
.quick-asset-btn {
    padding: 6px 12px;
    background: #f0f3f7;
    border: 1px solid #e0e6ed;
    border-radius: 20px;
    color: #2c3e50;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 90px;
    text-align: center;
    white-space: nowrap;
}

.quick-amount-btn:hover,
.quick-platform-btn:hover {
    background: #1a5cff;
    border-color: #1a5cff;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(26, 92, 255, 0.2);
}

.quick-asset-btn:hover {
    background: #ff9f4a;
    border-color: #ff9f4a;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(255, 159, 74, 0.2);
}

/* ============================================================================
   МОДАЛЬНЫЕ ОКНА
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
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.modal-header {
    padding: 24px 24px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.modal-header h2 {
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
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
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 16px 20px 20px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    border-top: 1px solid #edf2f7;
    flex-shrink: 0;
}

.modal-footer .btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-footer .btn-secondary {
    background: #f0f3f7;
    color: #2c3e50;
}

.modal-footer .btn-secondary:hover {
    background: #e0e6ed;
}

.modal-footer .btn-primary {
    background: #1a5cff;
    color: white;
}

.modal-footer .btn-primary:hover {
    background: #0044cc;
}

/* ============================================================================
   ФОРМЫ
   ============================================================================ */

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 8px;
    color: #2c3e50;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 16px;
    background: white;
    border: 1px solid #e0e6ed;
    border-radius: 12px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s;
    height: 48px;
}

.form-group textarea {
    height: auto;
    min-height: 100px;
}

.form-group select {
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7a8f' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 40px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* ============================================================================
   ГРУППА ВВОДА ВАЛЮТЫ
   ============================================================================ */

.currency-input-group {
    display: flex;
    width: 100%;
    position: relative;
}

.currency-input-group .form-input {
    flex: 1;
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
    border-right: none;
    text-align: right;
    padding-right: 15px;
    height: 48px;
}

.currency-input-group .currency-select-btn {
    width: 100px;
    background: #f8fafd;
    border: 1px solid #e0e6ed;
    border-left: none;
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 14px;
    font-weight: 500;
    color: #2c3e50;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 48px;
    padding: 0 10px;
    white-space: nowrap;
}

.currency-input-group .currency-select-btn:hover {
    background: #1a5cff;
    border-color: #1a5cff;
    color: white;
}

.currency-input-group .currency-select-btn:hover i {
    transform: rotate(180deg);
}

.currency-input-group .currency-select-btn i {
    transition: transform 0.3s ease;
    font-size: 12px;
}

.currency-input-group .form-input:focus {
    border-color: #1a5cff;
    border-right-color: #1a5cff;
    outline: none;
    box-shadow: none;
}

.currency-input-group .currency-select-btn:focus {
    outline: none;
    border-color: #1a5cff;
    background: #1a5cff;
    color: white;
}

.currency-input-group:focus-within .form-input,
.currency-input-group:focus-within .currency-select-btn {
    border-color: #1a5cff;
    box-shadow: 0 0 0 3px rgba(26, 92, 255, 0.1);
}

.currency-input-group:focus-within .currency-select-btn {
    background: #1a5cff;
    color: white;
}

/* ============================================================================
   ГРУППА ВЫБОРА ПЛОЩАДКИ
   ============================================================================ */

.platform-select-btn {
    width: 100%;
    padding: 12px 16px;
    background: #f8fafd;
    border: 1px solid #e0e6ed;
    border-radius: 12px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    color: #2c3e50;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s ease;
    height: 48px;
}

.platform-select-btn:hover {
    background: #1a5cff;
    border-color: #1a5cff;
    color: white;
}

.platform-select-btn:hover i {
    transform: rotate(180deg);
}

.platform-select-btn i {
    transition: transform 0.3s ease;
    font-size: 12px;
}

.platform-select-btn:focus {
    outline: none;
    border-color: #1a5cff;
    box-shadow: 0 0 0 3px rgba(26, 92, 255, 0.1);
}

/* ============================================================================
   СПИСКИ В МОДАЛЬНЫХ ОКНАХ
   ============================================================================ */

#allPlatformsList,
#allCurrenciesList {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f0f3f7;
    max-height: 250px;
    overflow-y: auto;
    margin-top: 8px;
    border: 1px solid #edf2f7;
    border-radius: 12px;
    padding: 8px;
}

#allPlatformsList::-webkit-scrollbar,
#allCurrenciesList::-webkit-scrollbar {
    width: 6px;
}

#allPlatformsList::-webkit-scrollbar-track,
#allCurrenciesList::-webkit-scrollbar-track {
    background: #f0f3f7;
    border-radius: 10px;
}

#allPlatformsList::-webkit-scrollbar-thumb,
#allCurrenciesList::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 10px;
}

#allPlatformsList::-webkit-scrollbar-thumb:hover,
#allCurrenciesList::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

#allPlatformsList > div,
#allCurrenciesList > div {
    padding: 10px;
    cursor: pointer;
    border-radius: 8px;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s;
}

#allPlatformsList > div:hover,
#allCurrenciesList > div:hover {
    background: #f0f3f7;
}

#allPlatformsList > div:last-child,
#allCurrenciesList > div:last-child {
    margin-bottom: 0;
}

/* ============================================================================
   КНОПКИ ВЫБОРА ТИПА
   ============================================================================ */

.platform-type-buttons,
.currency-type-buttons,
.asset-type-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 5px;
}

.platform-type-btn,
.currency-type-btn,
.asset-type-btn {
    flex: 1 1 auto;
    min-width: 80px;
    padding: 10px 12px;
    background: #f0f3f7;
    border: 1px solid #e0e6ed;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    color: #6b7a8f;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.platform-type-btn:hover {
    background: #e0e6ed;
    border-color: #1a5cff;
    color: #1a5cff;
    transform: translateY(-1px);
}

.platform-type-btn.active {
    background: #1a5cff;
    border-color: #1a5cff;
    color: white;
    box-shadow: 0 4px 8px rgba(26, 92, 255, 0.2);
}

.currency-type-btn:hover {
    background: #e0e6ed;
    border-color: #1a5cff;
    color: #1a5cff;
    transform: translateY(-1px);
}

.currency-type-btn.active {
    background: #1a5cff;
    border-color: #1a5cff;
    color: white;
    box-shadow: 0 4px 8px rgba(26, 92, 255, 0.2);
}

.asset-type-btn:hover {
    background: #e0e6ed;
    border-color: #ff9f4a;
    color: #ff9f4a;
    transform: translateY(-1px);
}

.asset-type-btn.active {
    background: #ff9f4a;
    border-color: #ff9f4a;
    color: white;
    box-shadow: 0 4px 8px rgba(255, 159, 74, 0.2);
}

.platform-type-btn.active:hover,
.currency-type-btn.active:hover,
.asset-type-btn.active:hover {
    transform: translateY(-1px);
}

/* ============================================================================
   УВЕДОМЛЕНИЯ
   ============================================================================ */

.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 350px;
}

.notification {
    background: white;
    border: 1px solid #e0e6ed;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    animation: notificationSlideIn 0.3s ease forwards;
    opacity: 0;
    transform: translateX(100%);
    display: flex;
    align-items: flex-start;
    gap: 12px;
    position: relative;
    overflow: hidden;
    z-index: 100000;
}

.notification.fade-out {
    animation: notificationSlideOut 0.3s ease forwards;
}

.notification::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
}

.notification.success {
    border-color: #c3e6cb;
}

.notification.success::before {
    background-color: #00a86b;
}

.notification.warning {
    border-color: #ffeeba;
    z-index: 100001;
}

.notification.warning::before {
    background-color: #ff9f4a;
}

.notification.error {
    border-color: #f5c6cb;
}

.notification.error::before {
    background-color: #e53e3e;
}

.notification.info {
    border-color: #bee5eb;
}

.notification.info::before {
    background-color: #1a5cff;
}

.notification-icon {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}

.notification.success .notification-icon {
    color: #00a86b;
}

.notification.warning .notification-icon {
    color: #ff9f4a;
}

.notification.error .notification-icon {
    color: #e53e3e;
}

.notification.info .notification-icon {
    color: #1a5cff;
}

.notification-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.notification-title {
    font-weight: 600;
    font-size: 14px;
    color: #2c3e50;
}

.notification.success .notification-title {
    color: #00a86b;
}

.notification.warning .notification-title {
    color: #ff9f4a;
}

.notification.error .notification-title {
    color: #e53e3e;
}

.notification.info .notification-title {
    color: #1a5cff;
}

.notification-message {
    font-size: 13px;
    color: #6b7a8f;
    line-height: 1.4;
}

.notification-close {
    background: none;
    border: none;
    color: #95a5a6;
    cursor: pointer;
    font-size: 18px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    flex-shrink: 0;
    transition: all 0.2s ease;
    border-radius: 4px;
}

.notification-close:hover {
    color: #2c3e50;
    background: #f0f3f7;
}

.notification-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #edf2f7;
    overflow: hidden;
}

.notification-progress-bar {
    height: 100%;
    transition: width 0.1s linear;
}

.notification.success .notification-progress-bar {
    background-color: #00a86b;
}

.notification.warning .notification-progress-bar {
    background-color: #ff9f4a;
}

.notification.error .notification-progress-bar {
    background-color: #e53e3e;
}

.notification.info .notification-progress-bar {
    background-color: #1a5cff;
}

.confirmation-buttons {
    display: flex;
    gap: 10px;
    margin-top: 10px;
    z-index: 100002;
}

.confirm-btn {
    flex: 1;
    padding: 8px 12px;
    background: #1a5cff;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.2s ease;
}

.confirm-btn:hover {
    background: #0044cc;
}

.cancel-btn {
    flex: 1;
    padding: 8px 12px;
    background: #f0f3f7;
    color: #2c3e50;
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.cancel-btn:hover {
    background: #e0e6ed;
}

/* ============================================================================
   АНИМАЦИИ
   ============================================================================ */

@keyframes notificationSlideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes notificationSlideOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
    20%, 40%, 60%, 80% { transform: translateX(2px); }
}

/* ============================================================================
   Z-INDEX УПРАВЛЕНИЕ
   ============================================================================ */

#platformSelectModal.modal-overlay,
#currencySelectModal.modal-overlay,
#assetSelectModal.modal-overlay {
    z-index: 10000 !important;
}

#platformSelectModal .modal,
#currencySelectModal .modal,
#assetSelectModal .modal {
    z-index: 10001 !important;
}

#addPlatformModal.modal-overlay,
#addCurrencyModal.modal-overlay,
#addAssetModal.modal-overlay {
    z-index: 20000 !important;
}

#addPlatformModal .modal,
#addCurrencyModal .modal,
#addAssetModal .modal {
    z-index: 20001 !important;
}

/* ============================================================================
   АДАПТИВНОСТЬ
   ============================================================================ */

@media (max-width: 1199px) and (min-width: 769px) {
    .cards-container {
        gap: 20px;
    }
    .card {
        flex-basis: calc(50% - 10px) !important;
    }
}

@media (max-width: 768px) {
    body { padding: 12px; }
    .cards-container {
        gap: 16px;
    }
    .card {
        flex-basis: 100% !important;
        min-width: auto;
    }
    .header {
        flex-direction: column;
        align-items: flex-start;
    }
    .pie { width: 120px; height: 120px; }
    .form-row { grid-template-columns: 1fr; }
    .quick-amount-btn,
    .quick-platform-btn,
    .quick-asset-btn {
        min-width: 70px !important;
        padding: 6px 8px;
        font-size: 11px;
    }
    .portfolio-value .value-amount { font-size: 24px; }
    .currency-input-group .currency-select-btn {
        width: 80px;
        font-size: 12px;
        padding: 0 5px;
    }
    .currency-input-group .form-input {
        padding-right: 10px;
    }
}

@media (max-width: 480px) {
    .portfolio-value .value-amount { font-size: 20px; }
    .operation-type-btn,
    .theme-toggle-btn {
        flex: 1 1 100%;
        padding: 8px 10px;
        font-size: 12px;
    }
    .modal-footer .btn {
        padding: 10px 16px;
        font-size: 13px;
    }
    .platform-type-btn,
    .currency-type-btn,
    .asset-type-btn {
        min-width: 60px;
        padding: 8px 6px;
        font-size: 11px;
    }
    .notification-container {
        left: 20px;
        right: 20px;
        max-width: none;
    }
    .notification {
        width: 100%;
    }
}

/* ============================================================================
   ИСТОРИЯ ПОКУПОК
   ============================================================================ */

.purchase-history-item {
    padding: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.purchase-history-item:last-child {
    border-bottom: none;
}

.purchase-history-date {
    font-size: 13px;
    color: #6b7a8f;
}

.purchase-history-details {
    text-align: right;
}

.purchase-history-quantity {
    font-weight: 600;
    color: #2c3e50;
}

.purchase-history-price {
    font-size: 12px;
    color: #6b7a8f;
}

.purchase-history-total {
    font-size: 13px;
    font-weight: 500;
    color: #e53e3e;
}

/* Стили для прокрутки */
.card::-webkit-scrollbar {
    width: 4px;
}

.card::-webkit-scrollbar-track {
    background: #f0f3f7;
    border-radius: 4px;
}

.card::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}

.card::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

/* Иконки для сетей */
.fa-ethereum { color: #627eea; }
.fa-bolt { color: #f3ba2f; }
.fa-t { color: #eb0029; }
.fa-bitcoin { color: #f7931a; }
.fa-moon { color: inherit; }

/* ============================================================================
   ЛОГОТИП И НАЗВАНИЕ САЙТА
   ============================================================================ */

.site-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #1a5cff 0%, #0044cc 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    box-shadow: 0 4px 12px rgba(26, 92, 255, 0.3);
    transition: transform 0.2s ease;
}

.logo-icon:hover {
    transform: scale(1.05);
}

.logo-text {
    display: flex;
    flex-direction: column;
}

.logo-title {
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1.2;
}

.logo-subtitle {
    font-size: 13px;
    color: #6b7a8f;
    font-weight: 500;
}

/* ============================================================================
   ЛИМИТНЫЕ ОРДЕРА - УЛУЧШЕННЫЕ СТИЛИ
   ============================================================================ */

.orders-container {
    grid-column: span 3;
}

.order-card {
    background: #f8fafd;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    border-left: 4px solid;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    overflow: hidden;
}

.order-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.order-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, transparent 50%, rgba(255,255,255,0.1) 50%);
    pointer-events: none;
}

.order-exchange {
    font-size: 12px;
    color: #6b7a8f;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.order-exchange i {
    font-size: 10px;
}

.order-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
}

.order-action {
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.order-action i {
    font-size: 12px;
}

.order-price {
    font-weight: 600;
    color: #2c3e50;
    background: rgba(0,0,0,0.02);
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 13px;
}

.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
    font-size: 11px;
    color: #6b7a8f;
}

.order-progress {
    height: 4px;
    background: #edf2f7;
    border-radius: 2px;
    margin-top: 10px;
    overflow: hidden;
}

.order-progress-bar {
    height: 100%;
    background: #1a5cff;
    border-radius: 2px;
    transition: width 0.3s ease;
}

.order-progress-bar.warning {
    background: #ff9f4a;
}

.order-progress-bar.danger {
    background: #e53e3e;
}

.order-card[style*="border-left-color: #00a86b"] .order-action {
    color: #00a86b;
}

.order-card[style*="border-left-color: #00a86b"] .order-progress-bar {
    background: #00a86b;
}

.order-card[style*="border-left-color: #e53e3e"] .order-action {
    color: #e53e3e;
}

.order-card[style*="border-left-color: #e53e3e"] .order-progress-bar {
    background: #e53e3e;
}

.order-empty {
    text-align: center;
    padding: 30px 20px;
    color: #6b7a8f;
}

.order-empty i {
    font-size: 40px;
    opacity: 0.3;
    margin-bottom: 10px;
}

.order-empty p {
    font-size: 14px;
    margin-bottom: 15px;
}

.add-order-btn {
    background: #f0f3f7;
    border: 1px dashed #cbd5e0;
    border-radius: 30px;
    padding: 8px 16px;
    color: #2c3e50;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.add-order-btn:hover {
    background: #1a5cff;
    border-color: #1a5cff;
    color: white;
    transform: translateY(-1px);
}

/* ============================================================================
   ТЕМНАЯ ТЕМА - ПОЛНОСТЬЮ ПЕРЕРАБОТАННАЯ
   ============================================================================ */

body.dark-theme {
    background: #0C0E12;
    color: #FFFFFF;
}

/* Цветовая палитра */
.dark-theme {
    --bg-primary: #0C0E12;
    --bg-secondary: #15181C;
    --bg-tertiary: #1E2228;
    --border-color: #2A2F36;
    --accent-primary: #2B6ED9;
    --accent-hover: #3C7FE9;
    --accent-success: #14B88B;
    --accent-danger: #E94F4F;
    --accent-warning: #F59E0B;
    --accent-purple: #8B5CF6;
    --text-primary: #FFFFFF;
    --text-secondary: #9AA5B5;
    --text-tertiary: #6B7A8F;
    --gradient-blue: linear-gradient(135deg, #2B6ED9, #1A4F9E);
    --gradient-green: linear-gradient(135deg, #14B88B, #0E8F6B);
    --gradient-card: linear-gradient(145deg, #15181C, #101317);
}

/* Основные элементы */
.dark-theme .header,
.dark-theme .card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}

.dark-theme .card {
    background: var(--gradient-card);
}

.dark-theme .card-header {
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 16px;
}

.dark-theme .card-title {
    color: var(--text-primary) !important;
    font-weight: 600;
}

.dark-theme .card-title i {
    color: var(--accent-primary);
}

.dark-theme .stat-badge {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    font-weight: 500;
}

/* Значения валют */
.dark-theme #usdValue {
    color: var(--accent-success);
    font-weight: 700;
}

.dark-theme #rubValue {
    color: var(--accent-primary);
    font-weight: 700;
}

.dark-theme .value-label {
    color: var(--text-secondary);
}

.dark-theme .value-amount {
    color: var(--text-primary) !important;
}

/* Карточки в header */
.dark-theme .header [style*="background: white"] {
    background: var(--bg-secondary) !important;
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
}

.dark-theme .header [style*="color: #1a2b3c"] {
    color: var(--text-primary) !important;
}

.dark-theme .header [style*="color: #6b7a8f"] {
    color: var(--text-secondary) !important;
}

.dark-theme .header h4 {
    color: var(--text-primary) !important;
}

.dark-theme .header .usd-value {
    color: var(--accent-success) !important;
    font-weight: 600;
}

.dark-theme .header .percentage-value {
    color: var(--text-secondary) !important;
}

/* Таблица активов */
.dark-theme .investments-table td {
    color: var(--text-primary);
    border-bottom-color: var(--border-color);
}

.dark-theme .investments-table tr:hover {
    background: var(--bg-tertiary);
}

.dark-theme .investment-icon {
    background: var(--bg-tertiary);
    color: var(--accent-primary);
}

.dark-theme .investment-name {
    color: var(--text-primary);
}

.dark-theme .investment-amount {
    color: var(--text-primary);
    font-weight: 600;
}

.dark-theme .investment-change {
    background: var(--bg-tertiary) !important;
    color: var(--text-secondary) !important;
    border: 1px solid var(--border-color);
}

/* Ордера */
.dark-theme .order-card {
    background: var(--bg-tertiary);
}

.dark-theme .order-card[style*="border-left-color: #00a86b"] {
    border-left-color: var(--accent-success) !important;
}

.dark-theme .order-card[style*="border-left-color: #e53e3e"] {
    border-left-color: var(--accent-danger) !important;
}

.dark-theme .order-exchange {
    color: var(--text-secondary);
}

.dark-theme .order-price {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.dark-theme .order-progress {
    background: var(--bg-secondary);
}

.dark-theme .add-order-btn {
    background: var(--bg-tertiary);
    border-color: var(--border-color);
    color: var(--text-primary);
}

.dark-theme .add-order-btn:hover {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
}

/* Операции */
.dark-theme .icon-buy {
    background: rgba(20, 184, 139, 0.15);
    color: var(--accent-success);
}

.dark-theme .icon-sell {
    background: rgba(233, 79, 79, 0.15);
    color: var(--accent-danger);
}

.dark-theme .icon-convert {
    background: rgba(139, 92, 246, 0.15);
    color: var(--accent-purple);
}

.dark-theme .operation-title {
    color: var(--text-primary);
}

.dark-theme .operation-date {
    color: var(--text-tertiary);
}

/* План действий */
.dark-theme .checklist-item {
    border-bottom-color: var(--border-color);
}

.dark-theme .checklist-text {
    color: var(--text-primary);
}

.dark-theme .checklist-text.completed {
    color: var(--text-tertiary);
}

.dark-theme .checklist-checkbox {
    border-color: var(--border-color);
}

.dark-theme .checklist-checkbox.checked {
    background: var(--accent-success);
    border-color: var(--accent-success);
}

/* Заметки */
.dark-theme .note-item {
    background: var(--bg-tertiary);
    border-left-color: var(--accent-warning);
}

.dark-theme .note-date {
    color: var(--accent-warning);
}

.dark-theme .note-text {
    color: var(--text-secondary);
}

/* Кнопки операций и темы в темной теме - ЕДИНЫЙ СТИЛЬ */
.dark-theme .operation-type-btn,
.dark-theme .theme-toggle-btn {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dark-theme .operation-type-btn:hover,
.dark-theme .theme-toggle-btn:hover {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(43, 110, 217, 0.3);
}

.dark-theme .operation-type-btn.active {
    background: var(--accent-primary);
    color: white;
}

.dark-theme .operation-type-btn i,
.dark-theme .theme-toggle-btn i {
    transition: transform 0.3s ease;
}

.dark-theme .theme-toggle-btn:hover i {
    transform: rotate(180deg);
}

/* Быстрые кнопки в темной теме */
.dark-theme .quick-amount-btn,
.dark-theme .quick-platform-btn,
.dark-theme .quick-asset-btn {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    transition: all 0.2s ease;
}

.dark-theme .quick-amount-btn:hover,
.dark-theme .quick-platform-btn:hover {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
}

.dark-theme .quick-asset-btn:hover {
    background: var(--accent-purple);
    border-color: var(--accent-purple);
    color: white;
}

/* Формы и инпуты в темной теме */
.dark-theme .form-group label {
    color: var(--text-primary) !important;
}

.dark-theme .form-input,
.dark-theme select,
.dark-theme textarea {
    background: var(--bg-tertiary);
    border-color: var(--border-color);
    color: var(--text-primary);
}

.dark-theme .form-input:focus,
.dark-theme select:focus,
.dark-theme textarea:focus {
    border-color: var(--accent-primary);
    background: var(--bg-secondary);
    box-shadow: 0 0 0 3px rgba(43, 110, 217, 0.2);
}

.dark-theme .currency-select-btn,
.dark-theme .platform-select-btn {
    background: var(--bg-tertiary);
    border-color: var(--border-color);
    color: var(--text-primary);
}

.dark-theme .currency-select-btn:hover,
.dark-theme .platform-select-btn:hover {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
}

/* Модальные окна в темной теме */
.dark-theme .modal {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
}

.dark-theme .modal-header {
    border-bottom-color: var(--border-color);
}

.dark-theme .modal-header h2 {
    color: var(--text-primary) !important;
}

.dark-theme .modal-close {
    color: var(--text-secondary);
}

.dark-theme .modal-close:hover {
    color: var(--text-primary);
    background: var(--bg-tertiary);
}

.dark-theme .modal-footer {
    border-top-color: var(--border-color);
}

.dark-theme .btn-secondary {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.dark-theme .btn-secondary:hover {
    background: var(--border-color);
}

.dark-theme .btn-primary {
    background: var(--gradient-blue);
    border: none;
    color: white;
}

.dark-theme .btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

/* Блоки показателей в темной теме */
.dark-theme [style*="background: #e3f2fd"] {
    background: linear-gradient(135deg, #0A2540, #0F2F4F) !important;
    border: 1px solid rgba(43, 110, 217, 0.2) !important;
}

.dark-theme [style*="background: #e8f5e9"] {
    background: linear-gradient(135deg, #0A3024, #0F3F2F) !important;
    border: 1px solid rgba(20, 184, 139, 0.2) !important;
}

.dark-theme [style*="background: #fff3e0"] {
    background: linear-gradient(135deg, #332211, #443322) !important;
    border: 1px solid rgba(245, 158, 11, 0.2) !important;
}

.dark-theme [style*="color: #1976d2"] {
    color: #5C9EFF !important;
}

.dark-theme [style*="color: #2e7d32"] {
    color: var(--accent-success) !important;
}

.dark-theme [style*="color: #ed6c02"] {
    color: var(--accent-warning) !important;
}

.dark-theme [style*="font-weight: 600"][style*="margin-top: 2px"] {
    color: var(--text-primary) !important;
}

/* Пагинация в темной теме */
.dark-theme #paginationControls button {
    background: var(--bg-tertiary) !important;
    border-color: var(--border-color) !important;
    color: var(--text-secondary) !important;
}

.dark-theme #paginationControls button:hover {
    background: var(--accent-primary) !important;
    border-color: var(--accent-primary) !important;
    color: white !important;
}

.dark-theme #paginationControls div[style*="color: #6b7a8f"] {
    color: var(--text-secondary) !important;
}

/* Диаграммы в темной теме */
.dark-theme .pie {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border-color);
}

.dark-theme .legend-item {
    color: var(--text-secondary);
}

.dark-theme .legend-value {
    color: var(--text-primary) !important;
}

/* Списки в модальных окнах в темной теме */
.dark-theme #allPlatformsList,
.dark-theme #allCurrenciesList {
    background: var(--bg-secondary);
    border-color: var(--border-color);
}

.dark-theme #allPlatformsList > div,
.dark-theme #allCurrenciesList > div {
    color: var(--text-primary) !important;
    transition: all 0.2s ease;
}

.dark-theme #allPlatformsList > div:hover,
.dark-theme #allCurrenciesList > div:hover {
    background: #2A2F36;
    color: var(--text-primary) !important;
}

.dark-theme #allPlatformsList > div:hover i,
.dark-theme #allCurrenciesList > div:hover i {
    color: var(--text-primary) !important;
}

/* Уведомления в темной теме */
.dark-theme .notification {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
}

.dark-theme .notification.success {
    border-left-color: var(--accent-success);
}

.dark-theme .notification.warning {
    border-left-color: var(--accent-warning);
}

.dark-theme .notification.error {
    border-left-color: var(--accent-danger);
}

.dark-theme .notification.info {
    border-left-color: var(--accent-primary);
}

.dark-theme .notification-title {
    color: var(--text-primary);
}

.dark-theme .notification-message {
    color: var(--text-secondary);
}

/* Логотип в темной теме */
.dark-theme .logo-title {
    color: var(--text-primary) !important;
}

.dark-theme .logo-subtitle {
    color: var(--text-secondary);
}

.dark-theme .logo-icon {
    background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

/* Скроллбар для темной темы */
.dark-theme ::-webkit-scrollbar-track {
    background: var(--bg-primary);
}

.dark-theme ::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 3px;
}

.dark-theme ::-webkit-scrollbar-thumb:hover {
    background: var(--text-tertiary);
}

.asset-symbol {
    font-weight: 600;
    color: #2c3e50 !important;
}

.dark-theme .asset-symbol {
    color: #FFFFFF !important;
}

/* Принудительное исправление цветов в модальных окнах */
.dark-theme #allCurrenciesList .asset-symbol,
.dark-theme #allCurrenciesList div[class*="asset-symbol"] {
    color: #FFFFFF !important;
}

#allCurrenciesList .asset-symbol {
    color: #2c3e50 !important;
}

/* МАКСИМАЛЬНО ПРИНУДИТЕЛЬНОЕ ИСПРАВЛЕНИЕ */
.dark-theme #allCurrenciesList > div:hover,
.dark-theme #allPlatformsList > div:hover {
    background-color: #2A2F36 !important;
    color: #FFFFFF !important;
}

.dark-theme #allCurrenciesList > div:hover *,
.dark-theme #allPlatformsList > div:hover * {
    color: #FFFFFF !important;
}

/* ============================================================================
   СТИЛИ КНОПОК ДЛЯ МОДАЛЬНЫХ ОКОН ПОДТВЕРЖДЕНИЯ
   ============================================================================ */

.modal-footer .btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-width: 120px;
}

.modal-footer .btn-secondary {
    background: var(--bg-tertiary, #f0f3f7);
    color: var(--text-primary, #2c3e50);
    border: 1px solid var(--border-color, #e0e6ed);
}

.modal-footer .btn-secondary:hover {
    background: var(--border-color, #e0e6ed);
    transform: translateY(-1px);
}

.modal-footer .btn-primary {
    background: var(--accent-primary, #1a5cff);
    color: white;
    box-shadow: 0 4px 8px rgba(26, 92, 255, 0.2);
}

.modal-footer .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(26, 92, 255, 0.3);
}

.modal-footer .btn-primary i {
    color: white;
}

/* Специфичные цвета для кнопок в модальных окнах */
#executeOrderModal .btn-primary {
    background: #00a86b;
    box-shadow: 0 4px 8px rgba(0, 168, 107, 0.2);
}

#executeOrderModal .btn-primary:hover {
    background: #008f5a;
    box-shadow: 0 6px 12px rgba(0, 168, 107, 0.3);
}

#cancelOrderModal .btn-primary {
    background: #e53e3e;
    box-shadow: 0 4px 8px rgba(229, 62, 62, 0.2);
}

#cancelOrderModal .btn-primary:hover {
    background: #c53030;
    box-shadow: 0 6px 12px rgba(229, 62, 62, 0.3);
}

/* Кнопки внутри модальных окон (не только в footer) */
.modal-body .btn {
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.modal-body .btn-secondary {
    background: var(--bg-tertiary, #f0f3f7);
    color: var(--text-primary, #2c3e50);
    border: 1px solid var(--border-color, #e0e6ed);
}

.modal-body .btn-secondary:hover {
    background: var(--border-color, #e0e6ed);
}

/* Темная тема */
.dark-theme .modal-footer .btn-secondary,
.dark-theme .modal-body .btn-secondary {
    background: var(--bg-tertiary);
    border-color: var(--border-color);
    color: var(--text-primary);
}

.dark-theme .modal-footer .btn-secondary:hover,
.dark-theme .modal-body .btn-secondary:hover {
    background: var(--border-color);
}

.dark-theme .modal-footer .btn-primary {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.dark-theme #executeOrderModal .btn-primary {
    background: #14B88B;
}

.dark-theme #executeOrderModal .btn-primary:hover {
    background: #0E8F6B;
}

.dark-theme #cancelOrderModal .btn-primary {
    background: #E94F4F;
}

.dark-theme #cancelOrderModal .btn-primary:hover {
    background: #C53030;
}

/* Добавьте в секцию стилей (в конец существующих стилей) */

.card-operations {
    display: flex;
    flex-direction: column;
    height: auto;
    min-height: 400px;
    max-height: 600px;
    overflow: hidden;
}

#operationsList {
    flex: 1;
    overflow-y: auto;
    min-height: 300px;
    max-height: calc(100% - 80px);
    scrollbar-width: thin;
}

#operationsList::-webkit-scrollbar {
    width: 6px;
}

#operationsList::-webkit-scrollbar-track {
    background: var(--bg-tertiary, #f0f3f7);
    border-radius: 3px;
}

#operationsList::-webkit-scrollbar-thumb {
    background: var(--border-color, #cbd5e0);
    border-radius: 3px;
}

.dark-theme #operationsList::-webkit-scrollbar-track {
    background: var(--bg-tertiary);
}

.dark-theme #operationsList::-webkit-scrollbar-thumb {
    background: var(--border-color);
}

.operation-item {
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color, #edf2f7);
}

.operation-item:last-child {
    border-bottom: none;
}

/* Адаптивность */
@media (max-width: 768px) {
    .card-operations {
        min-height: 350px;
        max-height: 500px;
    }
    
    #operationsList {
        min-height: 250px;
    }
}

/* Стили для заметок с кнопками */
.note-item {
    background: var(--bg-tertiary, #fef9e7);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    border-left: 4px solid;
    transition: all 0.2s ease;
    position: relative;
}

.note-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.note-item.important {
    border-left-color: #e53e3e;
    background: linear-gradient(135deg, rgba(229, 62, 62, 0.05), transparent);
}

.note-item.reminder {
    border-left-color: #ff9f4a;
    background: linear-gradient(135deg, rgba(255, 159, 74, 0.05), transparent);
}

.note-item.idea {
    border-left-color: #4a9eff;
    background: linear-gradient(135deg, rgba(74, 158, 255, 0.05), transparent);
}

.note-item.general {
    border-left-color: #6b7a8f;
}

.note-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.note-title {
    font-weight: 600;
    font-size: 16px;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.note-date {
    font-size: 11px;
    color: var(--text-tertiary);
    display: flex;
    align-items: center;
    gap: 4px;
}

.note-content {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.5;
    margin-top: 8px;
    margin-bottom: 12px;
}

.note-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px solid var(--border-color);
}

.note-action-btn {
    background: transparent;
    border: none;
    padding: 4px 8px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--text-tertiary);
}

.note-action-btn:hover {
    background: var(--bg-tertiary);
    transform: translateY(-1px);
}

.note-action-btn.edit:hover {
    color: #ff9f4a;
}

.note-action-btn.archive:hover {
    color: #6b7a8f;
}

.note-action-btn.delete:hover {
    color: #e53e3e;
}

.note-action-btn.restore:hover {
    color: #00a86b;
}

/* Кнопка добавления заметки */
.add-note-btn {
    background: #ff9f4a;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.add-note-btn:hover {
    background: #e8892c;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(255, 159, 74, 0.3);
}

/* Кнопка архива */
.view-archive-btn {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.view-archive-btn:hover {
    background: var(--accent-primary);
    color: white;
    border-color: var(--accent-primary);
}

/* Стили для архивных заметок в модальном окне */
.archived-note-item {
    background: var(--bg-tertiary);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    border-left: 4px solid #6b7a8f;
    opacity: 0.8;
    transition: all 0.2s ease;
}

.archived-note-item:hover {
    opacity: 1;
    transform: translateY(-1px);
}

.archived-note-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.archived-note-title {
    font-weight: 600;
    font-size: 15px;
    color: var(--text-primary);
}

.archived-note-date {
    font-size: 11px;
    color: var(--text-tertiary);
}

.archived-note-content {
    font-size: 13px;
    color: var(--text-secondary);
    margin: 8px 0;
}

.archived-note-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 12px;
}

/* Кнопки заметок в темной теме */
.dark-theme .add-note-btn {
    background: var(--accent-warning);
}

.dark-theme .add-note-btn:hover {
    background: #e68900;
}

.dark-theme .view-archive-btn {
    background: var(--bg-tertiary);
    border-color: var(--border-color);
    color: var(--text-secondary);
}

.dark-theme .view-archive-btn:hover {
    background: var(--accent-primary);
    color: white;
}

.dark-theme .note-action-btn:hover {
    background: var(--bg-secondary);
}

/* Кнопка "Все операции" в темной теме - единый стиль с кнопками шапки */
.dark-theme .all-ops-btn {
    background: var(--bg-tertiary) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-secondary) !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.dark-theme .all-ops-btn:hover {
    background: var(--accent-primary) !important;
    border-color: var(--accent-primary) !important;
    color: white !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 16px rgba(43, 110, 217, 0.3) !important;
}

.dark-theme .all-ops-btn i {
    transition: transform 0.3s ease !important;
}

.dark-theme .all-ops-btn:hover i {
    transform: rotate(180deg) !important;
}

/* Блок доходности в темной теме */
.dark-theme [style*="background: #e8f5e9"][style*="padding: 10px 16px"] {
    background: linear-gradient(135deg, #0A3024, #0F3F2F) !important;
    border: 1px solid rgba(20, 184, 139, 0.2) !important;
}

.dark-theme [style*="background: #e8f5e9"][style*="padding: 10px 16px"] div[style*="color: #2e7d32"] {
    color: var(--accent-success) !important;
}

.dark-theme [style*="background: #ffe6e6"][style*="padding: 10px 16px"] {
    background: linear-gradient(135deg, #331111, #441111) !important;
    border: 1px solid rgba(233, 79, 79, 0.2) !important;
}

.dark-theme [style*="background: #ffe6e6"][style*="padding: 10px 16px"] div[style*="color: #c62828"] {
    color: var(--accent-danger) !important;
}

.dark-theme .profit-positive {
    background: linear-gradient(135deg, #0A3024, #0F3F2F) !important;
}

.dark-theme .profit-negative {
    background: linear-gradient(135deg, #331111, #441111) !important;
}

/* Стили для модального окна активов площадки */
.platform-assets-table {
    width: 100%;
    border-collapse: collapse;
}

.platform-assets-table th {
    text-align: left;
    padding: 12px 8px;
    background: var(--bg-tertiary, #f8fafd);
    font-weight: 600;
    font-size: 13px;
    color: var(--text-secondary, #6b7a8f);
    border-bottom: 2px solid var(--border-color, #edf2f7);
}

.platform-assets-table td {
    padding: 12px 8px;
    border-bottom: 1px solid var(--border-color, #edf2f7);
    vertical-align: middle;
}

.platform-assets-table tr:hover {
    background: var(--bg-tertiary, #f8fafd);
}

.platform-assets-symbol {
    font-weight: 600;
    color: var(--text-primary, #2c3e50);
}

.platform-assets-quantity {
    font-family: monospace;
    text-align: right;
}

.platform-assets-value {
    text-align: right;
    font-weight: 500;
    color: var(--accent-success, #00a86b);
}

.platform-assets-summary {
    background: var(--bg-tertiary, #f0f3f7);
    border-radius: 12px;
    padding: 16px;
    margin-top: 16px;
}

.platform-assets-summary-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
}

.platform-assets-summary-row:first-child {
    border-bottom: 1px solid var(--border-color, #e0e6ed);
    margin-bottom: 8px;
    padding-bottom: 12px;
}

.platform-assets-total {
    font-weight: 700;
    font-size: 18px;
    color: var(--accent-primary, #1a5cff);
}

/* ============================================================================
   КНОПКИ СЕКТОРА - АНАЛОГИЧНЫ КНОПКАМ ТИПА АКТИВА
   ============================================================================ */

.sector-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 5px;
    padding: 5px;
}

.sector-option-btn {
    flex: 1 1 auto;
    min-width: 80px;
    padding: 10px 12px;
    background: #f0f3f7;
    border: 1px solid #e0e6ed;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    color: #6b7a8f;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.sector-option-btn:hover {
    background: #e0e6ed;
    border-color: #ff9f4a;
    color: #ff9f4a;
    transform: translateY(-1px);
}

.sector-option-btn.active {
    background: #ff9f4a;
    border-color: #ff9f4a;
    color: white;
    box-shadow: 0 4px 8px rgba(255, 159, 74, 0.2);
}

/* Для темной темы - переопределяем стили, чтобы они были как у asset-type-btn в светлой теме */
body.dark-theme .sector-option-btn {
    background: #f0f3f7 !important;
    border: 1px solid #e0e6ed !important;
    color: #6b7a8f !important;
}

body.dark-theme .sector-option-btn:hover {
    background: #e0e6ed !important;
    border-color: #ff9f4a !important;
    color: #ff9f4a !important;
}

body.dark-theme .sector-option-btn.active {
    background: #ff9f4a !important;
    border-color: #ff9f4a !important;
    color: white !important;
    box-shadow: 0 4px 8px rgba(255, 159, 74, 0.2) !important;
}

/* Стили для блока баланса площадки */
#transferFromPlatformBalance {
    animation: fadeIn 0.3s ease;
}

#transferPlatformAssetsList {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f0f3f7;
}

#transferPlatformAssetsList::-webkit-scrollbar {
    width: 4px;
}

#transferPlatformAssetsList::-webkit-scrollbar-track {
    background: var(--bg-tertiary, #f0f3f7);
    border-radius: 4px;
}

#transferPlatformAssetsList::-webkit-scrollbar-thumb {
    background: var(--border-color, #cbd5e0);
    border-radius: 4px;
}

#transferPlatformAssetsList::-webkit-scrollbar-thumb:hover {
    background: var(--text-tertiary, #a0aec0);
}

.platform-asset-item {
    transition: all 0.2s ease;
}

.platform-asset-item:hover {
    transform: translateX(2px);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Темная тема */
.dark-theme #transferFromPlatformBalance {
    background: var(--bg-tertiary);
    border-color: var(--border-color);
}

.dark-theme .platform-asset-item {
    border-bottom-color: var(--border-color) !important;
}

.dark-theme .platform-asset-item:hover {
    background: var(--bg-secondary) !important;
}

/* Добавляем стрелку между полями (для grid варианта) */
#transferModal .form-row {
    position: relative;
}

#transferModal .form-row::before {
    content: '→';
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    font-size: 20px;
    color: var(--accent-primary, #1a5cff);
    background: white;
    padding: 4px 8px;
    border-radius: 50%;
    z-index: 1;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Для темной темы */
.dark-theme #transferModal .form-row::before {
    background: var(--bg-secondary);
    color: var(--accent-primary);
}

/* На мобильных скрываем стрелку */
@media (max-width: 768px) {
    #transferModal .form-row::before {
        display: none;
    }
}

/* Скрыть популярные площадки во всех модальных окнах
,
#depositPopularPlatforms,
#tradePopularPlatforms,
#tradeFromPopularPlatforms,
#limitPopularPlatforms */
#transferFromPopularPlatforms,
#transferToPopularPlatforms {
    display: none !important;
}

/* Стили для кнопки расходов */
.operation-type-btn[data-type="expense"] i {
    transition: transform 0.3s ease;
}

.operation-type-btn[data-type="expense"]:hover i {
    transform: scale(1.2) rotate(-5deg);
}

/* Стили для элементов расходов */
.expense-item {
    transition: all 0.2s ease;
}

.expense-item:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.delete-expense-btn {
    transition: all 0.2s ease;
}

.delete-expense-btn:hover {
    color: #e53e3e !important;
    transform: scale(1.1);
}
    </style>
</head>
<body class="<?= $current_theme === 'dark' ? 'dark-theme' : '' ?>">
    <div class="dashboard">
        <div class="notification-container" id="notificationContainer"></div>

        <!-- Модальное окно добавления нового актива -->
        <div class="modal-overlay" id="addAssetModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #ff9f4a;"></i> Добавление актива</h2>
                    <button class="modal-close" id="closeAddAssetModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addAssetForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Символ актива *</label>
                            <input type="text" class="form-input" id="newAssetSymbol" placeholder="Например: BTC, ETH, AAPL" value="" readonly style="text-transform: uppercase;">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-font"></i> Название актива *</label>
                            <input type="text" class="form-input" id="newAssetName" placeholder="Например: Bitcoin, Ethereum, Apple Inc.">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Тип актива *</label>
                            <div class="asset-type-buttons">
                                <button type="button" class="asset-type-btn" data-type="crypto">Криптовалюта</button>
                                <button type="button" class="asset-type-btn" data-type="stock">Акция</button>
                                <button type="button" class="asset-type-btn" data-type="etf">ETF</button>
                                <button type="button" class="asset-type-btn" data-type="bond">Облигация</button>
                                <button type="button" class="asset-type-btn" data-type="currency">Валюта</button>
                                <button type="button" class="asset-type-btn" data-type="other">Другое</button>
                            </div>
                            <input type="hidden" id="newAssetType" value="">
                        </div>
                        
                        <!-- БЛОК ВЫБОРА СЕКТОРА (с отдельным классом) -->
                    <div class="form-group" id="sectorSelectGroup" style="display: none;">
                        <label><i class="fas fa-chart-line"></i> Сектор *</label>
                        <div class="sector-buttons" id="sectorButtons">
                            <?php
                            $stmt = $pdo->query("SELECT name_ru, name FROM sectors WHERE type IN ('stock', 'etf') AND is_active = 1 ORDER BY name_ru");
                            $sectors_list = $stmt->fetchAll();
                            foreach ($sectors_list as $sector):
                            ?>
                            <button type="button" class="sector-option-btn" data-sector="<?= htmlspecialchars($sector['name']) ?>">
                                <?= htmlspecialchars($sector['name_ru']) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="newAssetSector" value="">
                        <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> Выберите сектор для акции/ETF
                        </small>
                    </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddAssetBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddAssetBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> Сохранить актив
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно истории покупок -->
        <div class="modal-overlay" id="purchaseHistoryModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-history" style="color: #1a5cff;"></i> История покупок <span id="purchaseHistorySymbol"></span></h2>
                    <button class="modal-close" onclick="closePurchaseHistoryModal()">&times;</button>
                </div>
                <div class="modal-body" id="purchaseHistoryBody">
                    <!-- Данные будут вставлены через JavaScript -->
                </div>
            </div>
        </div>

        <!-- Модальное окно пополнения -->
        <div class="modal-overlay" id="depositModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #00a86b;"></i> Пополнение</h2>
                    <button class="modal-close" id="closeDepositModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="depositForm">
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Площадка *</label>
                            <button type="button" class="platform-select-btn" id="selectPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedPlatformDisplay">Выбрать площадку</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="depositPopularPlatforms">
                                <?php
                                $popular_platforms = array_slice($platforms, 0, 5);
                                foreach ($popular_platforms as $platform): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                    <?= htmlspecialchars($platform['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <div id="depositPlatformsList" style="max-height: 150px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 5px; display: none;"></div>
                            
                            <input type="hidden" id="depositPlatformId" value="">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Сумма пополнения *</label>
                            <div class="currency-input-group" style="margin-bottom: 10px;">
                                <input type="text" class="form-input" id="depositAmount" placeholder="0" inputmode="numeric">
                                <button type="button" class="currency-select-btn" id="selectCurrencyBtn">
                                    <span id="selectedCurrencyDisplay">RUB</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="depositPopularCurrencies">
                                <?php
                                $popular_currency_codes = ['RUB', 'USD', 'EUR', 'GBP', 'CNY'];
                                $popular_currencies = array_filter($fiat_currencies, function($c) use ($popular_currency_codes) {
                                    return in_array($c['code'], $popular_currency_codes);
                                });
                                foreach ($popular_currencies as $currency): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectCurrency('<?= $currency['code'] ?>', '<?= htmlspecialchars($currency['name']) ?>')">
                                    <?= $currency['code'] ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <div id="depositCurrenciesList" style="max-height: 150px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 5px; display: none;"></div>
                            
                            <input type="hidden" id="depositCurrency" value="RUB">
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Дата пополнения</label>
                            <input type="date" class="form-input" id="depositDate" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelDepositBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmDepositBtn" style="background: #00a86b;" onclick="confirmDeposit()">
                        <i class="fas fa-check-circle"></i> Пополнить
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно выбора валюты (актива) -->
        <div class="modal-overlay" id="currencySelectModal">
            <div class="modal" style="max-width: 400px;">
                <div class="modal-header">
                    <h2><i class="fas fa-coins" style="color: #1a5cff;"></i> Выберите валюту</h2>
                    <button class="modal-close" id="closeCurrencyModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <input type="text" class="form-input" id="currencySearch" placeholder="поиск или добавление валюты..." autocomplete="off" style="width: 100%;">
                    </div>
                    
                    <div>
                        <label><i class="fas fa-list" style="color: #1a5cff;"></i> Все валюты</label>
                        <div style="max-height: 250px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 8px;" id="allCurrenciesList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно выбора площадки -->
        <div class="modal-overlay" id="platformSelectModal">
            <div class="modal" style="max-width: 400px;">
                <div class="modal-header">
                    <h2><i class="fas fa-building" style="color: #1a5cff;"></i> Выберите площадку</h2>
                    <button class="modal-close" id="closePlatformModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <input type="text" class="form-input" id="platformSearch" placeholder="поиск или добавление площадки..." autocomplete="off" style="width: 100%;">
                    </div>
                    
                    <div>
                        <label><i class="fas fa-list" style="color: #1a5cff;"></i> Все площадки</label>
                        <div style="max-height: 250px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 8px;" id="allPlatformsList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления новой площадки -->
        <div class="modal-overlay" id="addPlatformModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #1a5cff;"></i> Добавление площадки</h2>
                    <button class="modal-close" id="closeAddPlatformModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addPlatformForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Название площадки *</label>
                            <input type="text" class="form-input" id="newPlatformName" placeholder="Например: Binance, Bybit, Т-Банк" value="" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Тип площадки *</label>
                            <div class="platform-type-buttons">
                                <button type="button" class="platform-type-btn" data-type="exchange">Биржа</button>
                                <button type="button" class="platform-type-btn" data-type="broker">Брокер</button>
                                <button type="button" class="platform-type-btn" data-type="bank">Банк</button>
                                <button type="button" class="platform-type-btn" data-type="wallet">Кошелек</button>
                                <button type="button" class="platform-type-btn" data-type="other">Другое</button>
                            </div>
                            <input type="hidden" id="newPlatformType" value="">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Выберите тип площадки
                            </small>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label><i class="fas fa-globe"></i> Страна</label>
                            <input type="text" class="form-input" id="newPlatformCountry" placeholder="Например: Россия, США, Китай">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddPlatformBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddPlatformBtn" style="background: #1a5cff;">
                        <i class="fas fa-save"></i> Сохранить площадку
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления новой валюты -->
        <div class="modal-overlay" id="addCurrencyModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #1a5cff;"></i> Добавление валюты</h2>
                    <button class="modal-close" id="closeAddCurrencyModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addCurrencyForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Код валюты *</label>
                            <input type="text" class="form-input" id="newCurrencyCode" placeholder="Например: RUB, USD, EUR, BTC" value="" readonly style="text-transform: uppercase;">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-font"></i> Название валюты *</label>
                            <input type="text" class="form-input" id="newCurrencyName" placeholder="Например: Российский рубль, Доллар США">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Тип валюты *</label>
                            <div class="currency-type-buttons">
                                <button type="button" class="currency-type-btn" data-type="fiat">Фиатная</button>
                                <button type="button" class="currency-type-btn" data-type="crypto">Криптовалюта</button>
                                <button type="button" class="currency-type-btn" data-type="stablecoin">Стейблкоин</button>
                                <button type="button" class="currency-type-btn" data-type="metal">Драгоценный металл</button>
                            </div>
                            <input type="hidden" id="newCurrencyType" value="">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Выберите тип валюты
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-symbol"></i> Символ валюты</label>
                            <input type="text" class="form-input" id="newCurrencySymbol" placeholder="Например: ₽, $, €, ₿" maxlength="5">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddCurrencyBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddCurrencyBtn" style="background: #1a5cff;">
                        <i class="fas fa-save"></i> Сохранить валюту
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно Покупка/Продажа -->
        <div class="modal-overlay" id="tradeModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2 id="tradeModalTitle"><i class="fas fa-arrow-down" style="color: #00a86b;"></i> <span id="tradeModalTitleText">Покупка</span></h2>
                    <button class="modal-close" id="closeTradeModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="tradeForm">
                        <input type="hidden" id="tradeOperationType" value="buy">

                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Площадка покупки *</label>
                            <button type="button" class="platform-select-btn" id="selectTradePlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedTradePlatformDisplay">Выбрать площадку</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="tradePopularPlatforms">
                                <?php
                                $popular_platforms = array_slice($platforms, 0, 5);
                                foreach ($popular_platforms as $platform): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectTradePlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                    <?= htmlspecialchars($platform['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="tradePlatformId" value="">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Актив и количество *</label>
                            <div class="currency-input-group" style="margin-bottom: 10px;">
                                <input type="text" class="form-input" id="tradeQuantity" placeholder="0" inputmode="numeric" style="text-align: right;">
                                <button type="button" class="currency-select-btn" id="selectTradeAssetBtn">
                                    <span id="selectedTradeAssetDisplay">Выбрать</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px;" id="tradePopularAssets">
                                <?php
                                $popular_assets = array_filter($assets_list, function($asset) {
                                    return in_array($asset['symbol'], ['BTC', 'ETH', 'USDT']);
                                });
                                foreach ($popular_assets as $asset): 
                                ?>
                                <button type="button" class="quick-asset-btn" onclick="selectTradeAsset('<?= $asset['id'] ?>', '<?= htmlspecialchars($asset['symbol']) ?>', '<?= $asset['type'] ?>')">
                                    <?= htmlspecialchars($asset['symbol']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="tradeAssetId" value="">
                            <input type="hidden" id="tradeAssetType" value="">
                        </div>

                        <div class="form-group" id="tradeFromPlatformGroup">
                            <label><i class="fas fa-arrow-right"></i> Площадка списания *</label>
                            <button type="button" class="platform-select-btn" id="selectTradeFromPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedTradeFromPlatformDisplay">Выбрать площадку</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="tradeFromPopularPlatforms">
                                <?php
                                $popular_platforms = array_slice($platforms, 0, 5);
                                foreach ($popular_platforms as $platform): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectTradeFromPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                    <?= htmlspecialchars($platform['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="tradeFromPlatformId" value="">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Выберите площадку, с которой будут списаны средства
                            </small>
                        </div>

                        <div id="tradeCryptoNetworkSection" style="display: none;">
                            <div class="form-group">
                                <label><i class="fas fa-network-wired"></i> Сеть (необязательно)</label>
                                <div class="currency-input-group">
                                    <button type="button" class="platform-select-btn" id="selectTradeNetworkBtn" style="width: 100%; justify-content: space-between;">
                                        <span id="selectedTradeNetworkDisplay">Выбрать сеть</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="tradeNetwork" value="">
                                
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px;" id="tradePopularNetworks">
                                    <!-- Популярные сети будут добавлены через JavaScript -->
                                </div>
                            </div>
                        </div>

                        <!-- Блок истории покупок для продажи -->
                        <div id="sellPurchaseHistory" style="display: none; margin-top: 15px;">
                            <div style="background: var(--bg-tertiary, #f8fafd); border-radius: 12px; padding: 12px; margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <span style="font-weight: 600; font-size: 13px;">
                                        <i class="fas fa-history"></i> История покупок
                                    </span>
                                    <span id="sellCurrentBalance" style="font-size: 12px; color: #00a86b;"></span>
                                </div>
                                
                                <div id="sellPurchaseList" style="max-height: 200px; overflow-y: auto;">
                                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                                    </div>
                                </div>
                                
                                <div id="sellQuickActions" style="margin-top: 12px; display: none;">
                                    <div style="border-top: 1px solid var(--border-color, #e0e6ed); margin: 10px 0;"></div>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <button type="button" id="sellQuickFillAllBtn" class="quick-platform-btn" style="background: #00a86b; color: white;">
                                            <i class="fas fa-arrow-up"></i> Продать всё
                                        </button>
                                        <button type="button" id="sellQuickFillAvgBtn" class="quick-platform-btn" style="background: #ff9f4a; color: white;">
                                            <i class="fas fa-chart-line"></i> По средней цене
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Цена за ед. *</label>
                            <div class="currency-input-group">
                                <input type="text" class="form-input" id="tradePrice" placeholder="0" inputmode="numeric" style="text-align: right;">
                                <button type="button" class="currency-select-btn" id="selectTradePriceCurrencyBtn">
                                    <span id="selectedTradePriceCurrencyDisplay">Выбрать</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="tradePopularPriceCurrencies">
                                <?php
                                $popular_currency_codes = ['RUB', 'USD', 'USDT'];
                                $popular_price_currencies = array_filter($all_currencies, function($c) use ($popular_currency_codes) {
                                    return in_array($c['code'], $popular_currency_codes);
                                });
                                foreach ($popular_price_currencies as $currency): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectTradePriceCurrency('<?= $currency['code'] ?>')">
                                    <?= $currency['code'] ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="tradePriceCurrency" value="">
                        </div>

                        <div class="form-row" style="margin-top: 10px;">
                            <div class="form-group">
                                <label><i class="fas fa-percent"></i> Комиссия</label>
                                <div class="currency-input-group">
                                    <input type="text" class="form-input" id="tradeCommission" placeholder="0" inputmode="numeric" style="text-align: right;">
                                    <button type="button" class="currency-select-btn" id="selectTradeCommissionCurrencyBtn">
                                        <span id="selectedTradeCommissionCurrencyDisplay">Выбрать</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                                
                                <input type="hidden" id="tradeCommissionCurrency" value="">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-calculator"></i> Итого</label>
                                <input type="text" class="form-input" id="tradeTotal" value="0" style="width: 100%; background: var(--bg-tertiary); text-align: center; font-weight: 600; font-size: 18px;" readonly>
                                <small style="color: #6b7a8f; display: block; margin-top: 5px; text-align: center;">
                                    <i class="fas fa-info-circle"></i> Сумма списания (количество × цена + комиссия)
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Дата операции</label>
                            <input type="date" class="form-input" id="tradeDate" required>
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-sticky-note"></i> Комментарий</label>
                            <textarea class="form-input" id="tradeNotes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelTradeBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmTradeBtn" onclick="confirmTrade()">
                        <i class="fas fa-check-circle"></i> <span id="confirmTradeBtnText">Купить</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно Перевода -->
        <div class="modal-overlay" id="transferModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-exchange-alt" style="color: #ff9f4a;"></i> Перевод</h2>
                    <button class="modal-close" id="closeTransferModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="transferForm">
                        <!-- Блок выбора площадок Откуда и Куда в одной строке -->
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label>Откуда *</label>
                                <button type="button" class="platform-select-btn" id="selectFromPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                    <span id="selectedFromPlatformDisplay">Выбрать площадку</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="transferFromPopularPlatforms">
                                    <?php
                                    $popular_platforms = array_slice($platforms, 0, 5);
                                    foreach ($popular_platforms as $platform): 
                                    ?>
                                    <button type="button" class="quick-platform-btn" onclick="selectFromPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                        <?= htmlspecialchars($platform['name']) ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div id="transferFromPlatformsList" style="max-height: 150px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 5px; display: none;"></div>
                                
                                <input type="hidden" id="transferFromPlatformId" value="">
                            </div>

                            <div class="form-group">
                                <label>Куда *</label>
                                <button type="button" class="platform-select-btn" id="selectToPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                    <span id="selectedToPlatformDisplay">Выбрать площадку</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="transferToPopularPlatforms">
                                    <?php
                                    $popular_platforms = array_slice($platforms, 0, 5);
                                    foreach ($popular_platforms as $platform): 
                                    ?>
                                    <button type="button" class="quick-platform-btn" onclick="selectToPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                        <?= htmlspecialchars($platform['name']) ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div id="transferToPlatformsList" style="max-height: 150px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 5px; display: none;"></div>
                                
                                <input type="hidden" id="transferToPlatformId" value="">
                            </div>
                        </div>

                        <!-- Блок баланса площадки отправителя -->
                        <div id="transferFromPlatformBalance" style="display: none; margin-top: 10px; margin-bottom: 15px;">
                            <div style="background: var(--bg-tertiary, #f8fafd); border-radius: 12px; padding: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <span id="platformBalanceTitle" style="font-weight: 600; font-size: 13px;">
                                        <i class="fas fa-wallet"></i> Баланс площадки
                                    </span>
                                    <span id="transferPlatformTotalValue" style="font-size: 12px; font-weight: 500; color: #ff9f4a;"></span>
                                </div>
                                
                                <div id="transferPlatformAssetsList" style="max-height: 200px; overflow-y: auto;">
                                    <div style="text-align: center; padding: 15px; color: #6b7a8f;">
                                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                                    </div>
                                </div>
                                
                                <div id="transferPlatformTotal" style="margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--border-color, #e0e6ed); display: none;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                                        <span>Всего:</span>
                                        <span id="transferPlatformTotalUsd" style="font-weight: 600;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Что переводим *</label>
                            <div class="currency-input-group" style="margin-bottom: 10px;">
                                <input type="text" class="form-input" id="transferAmount" placeholder="0" inputmode="numeric" style="text-align: right;">
                                <button type="button" class="currency-select-btn" id="selectAssetBtn">
                                    <span id="selectedAssetDisplay">Выбрать</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="transferPopularAssets">
                                <?php
                                $popular_assets = array_filter($assets_list, function($asset) {
                                    return in_array($asset['symbol'], ['BTC', 'ETH', 'USDT']);
                                });
                                foreach ($popular_assets as $asset): 
                                ?>
                                <button type="button" class="quick-asset-btn" onclick="selectAsset('<?= $asset['id'] ?>', '<?= htmlspecialchars($asset['symbol']) ?>')">
                                    <?= htmlspecialchars($asset['symbol']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="transferAssetId" value="">
                        </div>

                        <div id="transferCryptoNetworkSection" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-network-wired"></i> Сеть отправителя *</label>
                                    <button type="button" class="platform-select-btn" id="selectFromNetworkBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                        <span id="selectedFromNetworkDisplay">Выбрать сеть</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <input type="hidden" id="transferNetworkFrom" value="">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-network-wired"></i> Сеть получателя *</label>
                                    <button type="button" class="platform-select-btn" id="selectToNetworkBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                        <span id="selectedToNetworkDisplay">Выбрать сеть</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <input type="hidden" id="transferNetworkTo" value="">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-percent"></i> Комиссия</label>
                            <div class="currency-input-group" style="margin-bottom: 10px;">
                                <input type="text" class="form-input" id="transferCommission" placeholder="0" inputmode="numeric" style="text-align: right;">
                                <button type="button" class="currency-select-btn" id="selectCommissionCurrencyBtn">
                                    <span id="selectedCommissionCurrencyDisplay">Выбрать</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <input type="hidden" id="transferCommissionCurrency" value="">
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Дата перевода</label>
                            <input type="date" class="form-input" id="transferDate">
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-sticky-note"></i> Комментарий</label>
                            <textarea class="form-input" id="transferNotes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelTransferBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmTransferBtn" style="background: #ff9f4a;" onclick="confirmTransfer()">
                        <i class="fas fa-exchange-alt"></i> Перевести
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно создания лимитного ордера -->
        <div class="modal-overlay" id="limitOrderModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-clock" style="color: #ff9f4a;"></i> Лимитный ордер</h2>
                    <button class="modal-close" id="closeLimitOrderModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="limitOrderForm">
                        <input type="hidden" id="limitOrderOperationType" value="buy">
                        
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Тип операции</label>
                            <div style="display: flex; gap: 10px; margin-top: 5px;">
                                <button type="button" class="platform-type-btn limit-type-btn" data-type="buy" style="flex: 1; background: #00a86b; color: white; border: none;">Покупка</button>
                                <button type="button" class="platform-type-btn limit-type-btn" data-type="sell" style="flex: 1; background: #e53e3e; color: white; border: none;">Продажа</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Площадка *</label>
                            <button type="button" class="platform-select-btn" id="selectLimitPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedLimitPlatformDisplay">Выбрать площадку</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="limitPopularPlatforms">
                                <?php
                                $popular_platforms = array_slice($platforms, 0, 5);
                                foreach ($popular_platforms as $platform): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectLimitPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                    <?= htmlspecialchars($platform['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="limitPlatformId" value="">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Актив *</label>
                            <button type="button" class="platform-select-btn" id="selectLimitAssetBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedLimitAssetDisplay">Выбрать актив</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="limitPopularAssets">
                                <?php
                                $popular_assets = array_filter($assets_list, function($asset) {
                                    return in_array($asset['symbol'], ['BTC', 'ETH', 'USDT', 'SBER', 'GAZP']);
                                });
                                foreach ($popular_assets as $asset): 
                                ?>
                                <button type="button" class="quick-asset-btn" onclick="selectLimitAsset('<?= $asset['id'] ?>', '<?= htmlspecialchars($asset['symbol']) ?>')">
                                    <?= htmlspecialchars($asset['symbol']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="limitAssetId" value="">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-balance-scale"></i> Количество *</label>
                                <input type="text" class="form-input" id="limitQuantity" placeholder="0" inputmode="numeric">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Лимитная цена *</label>
                                <div class="currency-input-group">
                                    <input type="text" class="form-input" id="limitPrice" placeholder="0" inputmode="numeric" style="text-align: right;">
                                    <button type="button" class="currency-select-btn" id="selectLimitCurrencyBtn">
                                        <span id="selectedLimitCurrencyDisplay">Выбрать</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="limitCurrency" value="">
                                
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="limitPopularCurrencies">
                                    <button type="button" class="quick-platform-btn" onclick="selectLimitCurrency('USD')">USD</button>
                                    <button type="button" class="quick-platform-btn" onclick="selectLimitCurrency('RUB')">RUB</button>
                                    <button type="button" class="quick-platform-btn" onclick="selectLimitCurrency('EUR')">EUR</button>
                                    <button type="button" class="quick-platform-btn" onclick="selectLimitCurrency('USDT')">USDT</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Действителен до (необязательно)</label>
                            <input type="date" class="form-input" id="limitExpiryDate">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="far fa-sticky-note"></i> Комментарий</label>
                            <textarea class="form-input" id="limitNotes" rows="2"></textarea>
                        </div>
                        
                        <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 15px; margin-top: 10px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: #6b7a8f;">Ориентировочная сумма:</span>
                                <span style="font-weight: 600;" id="limitTotalEstimate">0 USD</span>
                            </div>
                            <div style="font-size: 12px; color: #6b7a8f;">
                                <i class="fas fa-info-circle"></i> Сумма будет заблокирована при размещении ордера
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelLimitOrderBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmLimitOrderBtn" style="background: #ff9f4a;">
                        <i class="fas fa-clock"></i> Разместить ордер
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно подтверждения исполнения ордера -->
        <div class="modal-overlay" id="executeOrderModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color, #e0e6ed);">
                    <h2><i class="fas fa-check-circle" style="color: #00a86b;"></i> Подтверждение исполнения</h2>
                    <button class="modal-close" id="closeExecuteModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="background: var(--bg-secondary, #f8fafd); padding: 20px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                            <div style="width: 48px; height: 48px; background: rgba(0, 168, 107, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chart-line" style="color: #00a86b; font-size: 24px;"></i>
                            </div>
                            <div>
                                <div style="font-size: 20px; font-weight: 600; color: var(--text-primary, #2c3e50);" id="executeOrderTitle">Покупка BTC</div>
                                <div style="font-size: 14px; color: var(--text-secondary, #6b7a8f);" id="executeOrderPlatform">Bybit</div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                            <div style="background: var(--bg-primary, white); padding: 12px; border-radius: 10px;">
                                <div style="font-size: 12px; color: var(--text-secondary, #6b7a8f); margin-bottom: 4px;">Количество</div>
                                <div style="font-size: 18px; font-weight: 600; color: var(--text-primary, #2c3e50);" id="executeOrderQuantity">1.0000 BTC</div>
                            </div>
                            <div style="background: var(--bg-primary, white); padding: 12px; border-radius: 10px;">
                                <div style="font-size: 12px; color: var(--text-secondary, #6b7a8f); margin-bottom: 4px;">Лимитная цена</div>
                                <div style="font-size: 18px; font-weight: 600; color: var(--text-primary, #2c3e50);" id="executeOrderPrice">$10,000.00</div>
                            </div>
                        </div>
                        
                        <div style="background: var(--bg-primary, white); border-radius: 10px; padding: 16px; border: 1px solid var(--border-color, #e0e6ed);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color, #e0e6ed);">
                                <span style="color: var(--text-secondary, #6b7a8f);">Общая сумма:</span>
                                <span style="font-weight: 700; font-size: 20px; color: #00a86b;" id="executeOrderTotal">$10,000.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: var(--text-secondary, #6b7a8f);">Создан:</span>
                                <span style="color: var(--text-primary, #2c3e50); font-weight: 500;" id="executeOrderCreated">19.03.2026 16:05</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary, #6b7a8f);">Действителен до:</span>
                                <span style="color: var(--text-primary, #2c3e50); font-weight: 500;" id="executeOrderExpiry">Бессрочно</span>
                            </div>
                        </div>
                        
                        <div style="margin-top: 16px; padding: 12px; background: rgba(255, 159, 74, 0.1); border-radius: 8px; border: 1px solid rgba(255, 159, 74, 0.3);">
                            <div style="display: flex; gap: 8px; align-items: flex-start;">
                                <i class="fas fa-info-circle" style="color: #ff9f4a; margin-top: 2px;"></i>
                                <div>
                                    <div style="font-weight: 600; margin-bottom: 4px; color: var(--text-primary, #2c3e50);">Подтвердите действие</div>
                                    <div style="font-size: 13px; color: var(--text-secondary, #6b7a8f);" id="executeOrderWarning">Будет создана сделка на покупку. Средства будут списаны автоматически.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button class="btn btn-secondary" id="cancelExecuteBtn">Отмена</button>
                        <button class="btn btn-primary" id="confirmExecuteBtn" style="background: #00a86b;">
                            <i class="fas fa-check-circle"></i> Подтвердить исполнение
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно подтверждения отмены ордера -->
        <div class="modal-overlay" id="cancelOrderModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color, #e0e6ed);">
                    <h2><i class="fas fa-times-circle" style="color: #e53e3e;"></i> Подтверждение отмены</h2>
                    <button class="modal-close" id="closeCancelModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="background: var(--bg-secondary, #f8fafd); padding: 24px; margin-bottom: 20px; text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="color: #e53e3e; font-size: 48px; margin-bottom: 16px;"></i>
                        <div style="font-size: 20px; font-weight: 600; margin-bottom: 8px; color: var(--text-primary, #2c3e50);" id="cancelOrderTitle">Отмена ордера</div>
                        <div style="color: var(--text-secondary, #6b7a8f); margin-bottom: 20px; font-size: 14px;" id="cancelOrderDescription">Вы уверены, что хотите отменить ордер?</div>
                        
                        <div style="background: var(--bg-primary, white); border-radius: 10px; padding: 16px; text-align: left; border: 1px solid var(--border-color, #e0e6ed);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color, #e0e6ed);">
                                <span style="color: var(--text-secondary, #6b7a8f);">Площадка:</span>
                                <span style="font-weight: 600; color: var(--text-primary, #2c3e50);" id="cancelOrderPlatform">Bybit</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span style="color: var(--text-secondary, #6b7a8f);">Цена:</span>
                                <span style="font-weight: 600; color: var(--text-primary, #2c3e50);" id="cancelOrderPrice">$10,000.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary, #6b7a8f);">Количество:</span>
                                <span style="font-weight: 600; color: var(--text-primary, #2c3e50);" id="cancelOrderQuantity">1.0000 BTC</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button class="btn btn-secondary" id="cancelCancelBtn">Нет, оставить</button>
                        <button class="btn btn-primary" id="confirmCancelBtn" style="background: #e53e3e;">
                            <i class="fas fa-times-circle"></i> Да, отменить
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления/редактирования заметки -->
        <div class="modal-overlay" id="noteModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2 id="noteModalTitle"><i class="fas fa-sticky-note" style="color: #ff9f4a;"></i> <span id="noteModalTitleText">Добавить заметку</span></h2>
                    <button class="modal-close" id="closeNoteModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="noteForm">
                        <input type="hidden" id="noteId" value="">
                        
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Заголовок (необязательно)</label>
                            <input type="text" class="form-input" id="noteTitle" placeholder="Краткий заголовок заметки">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Содержание *</label>
                            <textarea class="form-input" id="noteContent" rows="4" placeholder="Введите текст заметки..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Тип заметки</label>
                            <div class="note-type-buttons" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <button type="button" class="platform-type-btn note-type-option" data-type="general" style="flex: 1;">📝 Обычная</button>
                                <button type="button" class="platform-type-btn note-type-option" data-type="reminder" style="flex: 1;">📌 Напоминание</button>
                                <button type="button" class="platform-type-btn note-type-option" data-type="idea" style="flex: 1;">💡 Идея</button>
                                <button type="button" class="platform-type-btn note-type-option" data-type="important" style="flex: 1;">⚠️ Важное</button>
                            </div>
                            <input type="hidden" id="noteType" value="general">
                        </div>
                        
                        <div class="form-group" id="reminderDateGroup" style="display: none;">
                            <label><i class="far fa-calendar-alt"></i> Дата напоминания</label>
                            <input type="date" class="form-input" id="noteReminderDate">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelNoteBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmNoteBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> <span id="confirmNoteBtnText">Сохранить</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно просмотра архивных заметок -->
        <div class="modal-overlay" id="archivedNotesModal">
            <div class="modal" style="max-width: 600px; max-height: 80vh;">
                <div class="modal-header">
                    <h2><i class="fas fa-archive" style="color: #6b7a8f;"></i> Архивные заметки</h2>
                    <button class="modal-close" id="closeArchivedModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="archivedNotesList" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeArchivedModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно подтверждения удаления -->
        <div class="modal-overlay" id="confirmDeleteModal">
            <div class="modal" style="max-width: 400px;">
                <div class="modal-header">
                    <h2><i class="fas fa-trash-alt" style="color: #e53e3e;"></i> Подтверждение удаления</h2>
                    <button class="modal-close" id="closeConfirmDeleteBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="text-align: center; padding: 20px;">
                        Вы уверены, что хотите безвозвратно удалить эту заметку?
                    </p>
                    <div id="deleteNoteInfo" style="background: var(--bg-tertiary); padding: 12px; border-radius: 8px; margin-top: 10px;">
                        <!-- Информация о заметке -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelDeleteBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmDeleteBtn" style="background: #e53e3e;">
                        <i class="fas fa-trash-alt"></i> Удалить
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно выбора сети -->
        <div class="modal-overlay" id="networkSelectModal">
            <div class="modal" style="max-width: 400px;">
                <div class="modal-header">
                    <h2 id="networkModalTitle"><i class="fas fa-network-wired" style="color: #ff9f4a;"></i> Выберите сеть</h2>
                    <button class="modal-close" id="closeNetworkModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <input type="text" class="form-input" id="networkSearch" placeholder="поиск или добавление сети..." autocomplete="off" style="width: 100%;">
                    </div>
                    
                    <div>
                        <label><i class="fas fa-list"></i> Все сети</label>
                        <div style="max-height: 300px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 8px;" id="allNetworksList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления новой сети -->
        <div class="modal-overlay" id="addNetworkModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #ff9f4a;"></i> Добавление сети</h2>
                    <button class="modal-close" id="closeAddNetworkModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addNetworkForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Аббревиатура сети *</label>
                            <input type="text" class="form-input" id="newNetworkName" placeholder="Например: ERC20, BEP20, TRC20, SOL" value="" readonly style="text-transform: uppercase;">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Краткое название (будет отображаться в списке)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-font"></i> Полное название сети</label>
                            <input type="text" class="form-input" id="newNetworkFullName" placeholder="Например: Ethereum (ERC-20), Binance Smart Chain (BEP-20)">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Полное название (будет отображаться под аббревиатурой)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-palette"></i> Цвет сети (необязательно)</label>
                            <input type="color" class="form-input" id="newNetworkColor" value="#ff9f4a" style="height: 48px; padding: 6px;">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-icons"></i> Иконка (автоматически)</label>
                            <div style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; background: var(--bg-tertiary); border-radius: 12px;">
                                <div id="previewNetworkIcon" style="width: 32px; height: 32px; background: #ff9f4a20; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #ff9f4a;">
                                    <i class="fas fa-network-wired"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 500;" id="previewNetworkName">ERC20</div>
                                    <div style="font-size: 11px; color: #6b7a8f;" id="previewNetworkFullName">Ethereum (ERC-20)</div>
                                </div>
                            </div>
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Иконка определяется автоматически по названию сети
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddNetworkBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddNetworkBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> Сохранить сеть
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно активов площадки -->
        <div class="modal-overlay" id="platformAssetsModal">
            <div class="modal" style="max-width: 600px; max-height: 80vh;">
                <div class="modal-header">
                    <h2 id="platformAssetsModalTitle">
                        <i class="fas fa-building" style="color: #1a5cff;"></i> 
                        <span id="platformAssetsName">Активы площадки</span>
                    </h2>
                    <button class="modal-close" id="closePlatformAssetsModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="platformAssetsBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closePlatformAssetsModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно активов сети -->
        <div class="modal-overlay" id="networkAssetsModal">
            <div class="modal" style="max-width: 650px; max-height: 80vh;">
                <div class="modal-header">
                    <h2 id="networkAssetsModalTitle">
                        <i class="fas fa-network-wired" style="color: #ff9f4a;"></i> 
                        <span id="networkAssetsName">Активы сети</span>
                    </h2>
                    <button class="modal-close" id="closeNetworkAssetsModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="networkAssetsBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeNetworkAssetsModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно активов сектора -->
        <div class="modal-overlay" id="sectorAssetsModal">
            <div class="modal" style="max-width: 650px; max-height: 80vh;">
                <div class="modal-header">
                    <h2 id="sectorAssetsModalTitle">
                        <i class="fas fa-chart-pie" style="color: #4a9eff;"></i> 
                        <span id="sectorAssetsName">Активы сектора</span>
                    </h2>
                    <button class="modal-close" id="closeSectorAssetsModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="sectorAssetsBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeSectorAssetsModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно активов по типам криптовалют -->
        <div class="modal-overlay" id="cryptoTypeModal">
            <div class="modal" style="max-width: 650px; max-height: 80vh;">
                <div class="modal-header">
                    <h2 id="cryptoTypeModalTitle">
                        <i class="fas fa-coins" style="color: #ff9f4a;"></i> 
                        <span id="cryptoTypeName">Активы</span>
                    </h2>
                    <button class="modal-close" id="closeCryptoTypeModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="cryptoTypeBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeCryptoTypeModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно расходов -->
        <div class="modal-overlay" id="expenseModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-receipt" style="color: #ff9f4a;"></i> Добавить расход</h2>
                    <button class="modal-close" id="closeExpenseModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="expenseForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Сумма *</label>
                            <div class="currency-input-group">
                                <input type="text" class="form-input" id="expenseAmount" placeholder="0" inputmode="numeric">
                                <button type="button" class="currency-select-btn" id="selectExpenseCurrencyBtn">
                                    <span id="selectedExpenseCurrencyDisplay">RUB</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-category"></i> Категория *</label>
                            <div id="expenseCategoriesList" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px;">
                                <!-- Категории будут загружены через JavaScript -->
                            </div>
                            <!-- ДОБАВЬТЕ ЭТО СКРЫТОЕ ПОЛЕ -->
                            <input type="hidden" id="expenseCategoryId" value="">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Описание</label>
                            <textarea class="form-input" id="expenseDescription" rows="2" placeholder="Например: продукты, такси, ресторан..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Дата расхода</label>
                            <input type="date" class="form-input" id="expenseDate" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelExpenseBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmExpenseBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> Сохранить расход
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно просмотра расходов -->
        <div class="modal-overlay" id="expensesListModal">
            <div class="modal" style="max-width: 700px; max-height: 80vh;">
                <div class="modal-header">
                    <h2><i class="fas fa-chart-line" style="color: #ff9f4a;"></i> Мои расходы</h2>
                    <button class="modal-close" id="closeExpensesListModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="expensesListBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeExpensesListModalFooterBtn">Закрыть</button>
                    <button class="btn btn-primary" id="addNewExpenseBtn" style="background: #ff9f4a;">
                        <i class="fas fa-plus-circle"></i> Добавить расход
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления категории расходов -->
        <div class="modal-overlay" id="addExpenseCategoryModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #ff9f4a;"></i> Добавить категорию расходов</h2>
                    <button class="modal-close" id="closeAddCategoryModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addExpenseCategoryForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Название (англ) *</label>
                            <input type="text" class="form-input" id="newCategoryName" placeholder="Например: food, transport, shopping" required>
                            <small style="color: #6b7a8f;">Уникальный идентификатор категории</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-font"></i> Название (рус) *</label>
                            <input type="text" class="form-input" id="newCategoryNameRu" placeholder="Например: Продукты, Транспорт, Покупки" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-icons"></i> Иконка</label>
                            <div class="currency-input-group">
                                <input type="text" class="form-input" id="newCategoryIcon" placeholder="fas fa-tag" value="fas fa-tag">
                                <button type="button" class="currency-select-btn" id="selectIconBtn" style="width: 80px;">
                                    <i class="fas fa-search"></i> Выбрать
                                </button>
                            </div>
                            <div id="iconPreview" style="margin-top: 8px; padding: 8px; background: var(--bg-tertiary); border-radius: 8px; text-align: center;">
                                <i class="fas fa-tag" style="font-size: 24px; color: #ff9f4a;"></i>
                                <span id="iconPreviewText" style="margin-left: 8px;">fas fa-tag</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-palette"></i> Цвет</label>
                            <input type="color" class="form-input" id="newCategoryColor" value="#ff9f4a" style="height: 48px; padding: 6px;">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddCategoryBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddCategoryBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> Сохранить категорию
                    </button>
                </div>
            </div>
        </div>

        <!-- Простое модальное окно выбора иконки (можно расширить) -->
        <div class="modal-overlay" id="iconSelectModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-icons"></i> Выберите иконку</h2>
                    <button class="modal-close" id="closeIconModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <input type="text" class="form-input" id="iconSearch" placeholder="Поиск иконки...">
                    </div>
                    <div id="iconsList" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; max-height: 300px; overflow-y: auto;">
                        <!-- Популярные иконки -->
                        <div class="icon-option" data-icon="fas fa-utensils" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-utensils" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">food</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-car" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-car" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">transport</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-film" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-film" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">entertainment</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-shopping-bag" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-shopping-bag" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">shopping</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-heartbeat" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-heartbeat" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">health</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-graduation-cap" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-graduation-cap" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">education</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-home" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-home" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">utilities</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-coffee" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-coffee" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">coffee</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-plane" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-plane" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">travel</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-gift" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-gift" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">gift</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-wifi" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-wifi" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">internet</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-mobile-alt" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-mobile-alt" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">phone</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeIconModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Шапка сайта с логотипом и кнопками -->
        <div class="site-header">
            <div class="logo-container">
                <div class="logo-icon" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="logo-text">
                    <span class="logo-title">Планеро.Инвестиции</span>
                    <span class="logo-subtitle">Анализ инвестиций</span>
                </div>
            </div>
            
            <!-- Кнопки операций -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="currency-btn operation-type-btn" data-type="buy">
                    <i class="fas fa-arrow-down"></i> Покупка
                </button>
                <button type="button" class="currency-btn operation-type-btn" data-type="sell">
                    <i class="fas fa-arrow-up"></i> Продажа
                </button>
                <button type="button" class="currency-btn operation-type-btn" data-type="transfer">
                    <i class="fas fa-exchange-alt"></i> Перевод
                </button>
                <button type="button" class="currency-btn operation-type-btn" data-type="deposit">
                    <i class="fas fa-plus-circle"></i> Пополнить
                </button>
                <button type="button" class="operation-type-btn" data-type="expense">
                    <i class="fas fa-receipt"></i> Расходы
                </button>
                <button id="themeToggleBtn" class="theme-toggle-btn">
                    <i class="fas <?= $current_theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
                    <span id="themeToggleText"><?= $current_theme === 'dark' ? 'Светлая' : 'Темная' ?></span>
                </button>
            </div>
        </div>

        <!-- HEADER на всю ширину -->
        <div class="header">
            <div class="portfolio-value">                
                <div style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap; align-items: center;">
                    <div style="padding: 10px 16px; border-radius: 12px;">
                        <span class="value-label">Текущая стоимость портфеля</span>
                        <div style="display: flex; align-items: baseline; gap: 20px; flex-wrap: wrap;">
                            <div>
                                <span class="value-amount" id="usdValue"><?= number_format($total_usd, 2, '.', ' ') ?> $</span>
                                <br />
                                <span class="value-amount" id="rubValue"><?= number_format($total_rub, 0, '.', ' ') ?> ₽</span>
                            </div>  
                        </div>
                    </div>

                    <!-- Блок доходности - проценты под текстом -->
                    <div style="background: <?= $profit_usd >= 0 ? '#e8f5e9' : '#ffe6e6' ?>; padding: 10px 16px; border-radius: 12px; min-width: 200px;">
                        <div style="font-size: 12px; color: <?= $profit_usd >= 0 ? '#2e7d32' : '#c62828' ?>; font-weight: 500; display: flex; align-items: center; gap: 4px;">
                            <i class="fas <?= $profit_icon ?>" style="font-size: 10px;"></i>
                            ДОХОДНОСТЬ 
                        </div>
                        
                        <div style="font-weight: 600; font-size: 18px; color: <?= $profit_usd >= 0 ? '#2e7d32' : '#c62828' ?>;">
                            <?= $profit_usd >= 0 ? '+' : '' ?><?= number_format($profit_percent, 1, '.', ' ') ?>%
                        </div>
                        
                        <div style="display: flex; justify-content: space-between;  border-top: 1px solid rgba(0,0,0,0.05);">
                            <div>
                                <div style="font-size: 10px; color: #6b7a8f;">Прибыль</div>
                                <div style="font-weight: 600; font-size: 13px; color: <?= $profit_usd >= 0 ? '#2e7d32' : '#c62828' ?>;">
                                    <?= $profit_usd >= 0 ? '+' : '' ?><?= number_format($profit_usd, 2, '.', ' ') ?> $
                                </div>
                                <div style="font-size: 10px; color: #6b7a8f;">
                                    <?= $profit_rub >= 0 ? '+' : '' ?><?= number_format($profit_rub, 0, '.', ' ') ?> ₽
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 10px; color: #6b7a8f;">Вложено</div>
                                <div style="font-weight: 600; font-size: 13px;"><?= number_format($total_invested_usd, 2, '.', ' ') ?> $</div>
                                <div style="font-size: 10px; color: #6b7a8f;"><?= number_format($total_invested_rub, 0, '.', ' ') ?> ₽</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background: #e3f2fd; padding: 10px 16px; border-radius: 12px;">
                        <div style="font-size: 12px; color: #1976d2; font-weight: 500;">РУБЛИ</div>
                        <div style="font-weight: 600; margin-top: 2px;"><?= number_format($rub_in_usd, 2, '.', ' ') ?> $</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= number_format($rub_amount_display, 0, '.', ' ') ?> ₽</div>
                    </div>
                    
                    <div style="background: #e8f5e9; padding: 10px 16px; border-radius: 12px;">
                        <div style="font-size: 12px; color: #2e7d32; font-weight: 500;">ДОЛЛАРЫ</div>
                        <div style="font-weight: 600; margin-top: 2px;"><?= number_format($usd_amount + $usdt_amount, 2, '.', ' ') ?> $</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= number_format($usd_amount, 2, '.', ' ') ?> USD</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= number_format($usdt_amount, 2, '.', ' ') ?> USDT</div>
                    </div>
                    
                    <div style="background: #fff3e0; padding: 10px 16px; border-radius: 12px;">
                        <div style="font-size: 12px; color: #ed6c02; font-weight: 500;">ИНВЕСТИЦИИ</div>
                        <div style="font-weight: 600; margin-top: 2px;"><?= number_format($investments_value, 2, '.', ' ') ?> $</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= number_format($investments_rub, 0, '.', ' ') ?> ₽</div>
                    </div>
                </div>
            </div>
            
            <!-- Карточки аналитики в header -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px; width: 100%;">
                
                <!-- Карточка распределения по площадкам -->
                <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-building" style="color: #4a9eff; font-size: 18px;"></i>
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-secondary);; margin: 0;">Площадки</h4>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php 
                        if (isset($platform_distribution) && !empty($platform_distribution)):
                            $platform_colors = ['#4a9eff', '#1a5cff', '#ff9f4a', '#2ecc71', '#e74c3c'];
                            $top_platforms = $platform_distribution; // показать все
                            foreach ($top_platforms as $index => $platform): 
                                $percentage = $total_usd > 0 ? round(($platform['total_value_usd'] / $total_usd) * 100, 1) : 0;
                                $value_usd = $platform['total_value_usd'];
                                $value_rub = $value_usd * $usd_rub_rate;
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center; cursor: pointer;" 
                            onclick="openPlatformAssetsModal(<?= $platform['platform_id'] ?>, '<?= htmlspecialchars($platform['platform_name'], ENT_QUOTES) ?>')"
                            onmouseover="this.style.opacity='0.8'" 
                            onmouseout="this.style.opacity='1'">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="width: 8px; height: 8px; border-radius: 4px; background: <?= $platform_colors[$index % count($platform_colors)] ?>;"></div>
                                <span style="font-size: 13px; color: var(--text-secondary);; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($platform['platform_name']) ?>
                                </span>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary);; text-align: right;">$<?= number_format($value_usd, 0) ?></span>
                            <span style="font-size: 12px; color: #6b7a8f; text-align: right;">/ <?= number_format($value_rub, 0) ?> ₽</span>
                        </div>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                        <div style="color: #6b7a8f; font-size: 13px; text-align: center;">Нет данных</div>
                        <?php endif; ?>
                    </div>
                </div>
                                
                <!-- Карточка распределения по сетям -->
                <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-network-wired" style="color: #ff9f4a; font-size: 18px;"></i>
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-secondary);; margin: 0;">Сети</h4>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php 
                        if (isset($network_distribution_array) && !empty($network_distribution_array)):
                            $network_colors = ['#14b8a6', '#8b5cf6', '#ec4899', '#f59e0b', '#3b82f6', '#ef4444'];
                            $top_networks = array_slice($network_distribution_array, 0, 5);
                            foreach ($top_networks as $index => $network): 
                                $percentage = $total_crypto > 0 ? round(($network['total_value_usd'] / $total_crypto) * 100, 1) : 0;
                                $value_usd = $network['total_value_usd'];
                                $value_rub = $value_usd * $usd_rub_rate;
                                $network_icon = 'fa-network-wired';
                                $network_name = strtoupper($network['network']);
                                if (strpos($network_name, 'ERC') !== false) $network_icon = 'fa-ethereum';
                                else if (strpos($network_name, 'BEP') !== false) $network_icon = 'fa-bolt';
                                else if (strpos($network_name, 'TRC') !== false) $network_icon = 'fa-t';
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center; cursor: pointer;" 
                            onclick="openNetworkAssetsModal('<?= htmlspecialchars($network['network'], ENT_QUOTES) ?>')"
                            onmouseover="this.style.opacity='0.8'" 
                            onmouseout="this.style.opacity='1'">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="fab <?= $network_icon ?>" style="color: <?= $network_colors[$index % count($network_colors)] ?>; width: 16px; font-size: 12px;"></i>
                                <span style="font-size: 13px; color: var(--text-secondary);; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($network['network']) ?></span>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary);; text-align: right;">$<?= number_format($value_usd, 0) ?></span>
                            <span style="font-size: 12px; color: #6b7a8f; text-align: right;">/ <?= number_format($value_rub, 0) ?> ₽</span>
                        </div>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                        <div style="color: #6b7a8f; font-size: 13px; text-align: center;">Нет данных</div>
                        <?php endif; ?>
                    </div>
                </div>
                                
                <!-- Карточка типов платформ -->
                <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-chart-pie" style="color: #2ecc71; font-size: 18px;"></i>
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-secondary);; margin: 0;">Типы платформ</h4>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php
                        if (isset($platform_distribution) && !empty($platform_distribution)):
                            $platform_types = [];
                            foreach ($platform_distribution as $platform) {
                                $type = $platform['platform_type'];
                                if (!isset($platform_types[$type])) {
                                    $platform_types[$type] = 0;
                                }
                                $platform_types[$type] += $platform['total_value_usd'];
                            }
                            
                            $type_colors = [
                                'exchange' => '#4a9eff',
                                'bank' => '#2ecc71',
                                'wallet' => '#ff9f4a',
                                'broker' => '#9b59b6',
                                'other' => '#95a5a6'
                            ];
                            
                            $type_names = [
                                'exchange' => 'Биржи',
                                'bank' => 'Банки',
                                'wallet' => 'Кошельки',
                                'broker' => 'Брокеры',
                                'other' => 'Другое'
                            ];
                            
                            arsort($platform_types);
                            $top_types = array_slice($platform_types, 0, 3, true);
                            
                            foreach ($top_types as $type => $value_usd): 
                                $percentage = $total_usd > 0 ? round(($value_usd / $total_usd) * 100, 1) : 0;
                                $color = $type_colors[$type] ?? '#95a5a6';
                                $value_rub = $value_usd * $usd_rub_rate;
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="width: 8px; height: 8px; border-radius: 4px; background: <?= $color ?>;"></div>
                                <span style="font-size: 13px; color: var(--text-secondary);;"><?= $type_names[$type] ?? ucfirst($type) ?></span>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary);; text-align: right;">$<?= number_format($value_usd, 0) ?></span>
                            <span style="font-size: 12px; color: #6b7a8f; text-align: right;">/ <?= number_format($value_rub, 0) ?> ₽</span>
                        </div>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                        <div style="color: #6b7a8f; font-size: 13px; text-align: center;">Нет данных</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- КОНТЕЙНЕР С КАРТОЧКАМИ (резиновая верстка) -->
        <div class="cards-container">
            
            <!-- Карточка структуры портфеля -->
            <div class="card card-structure">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie"></i> Структура портфеля</h3>
                    <span class="stat-badge">По типам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    if ($portfolio_structure[0]['category'] !== 'Нет данных') {
                        $colors = ['#4a9eff', '#1a5cff', '#ff9f4a', '#2ecc71', '#95a5a6', '#e74c3c', '#9b59b6'];
                        $gradient = [];
                        $current = 0;
                        
                        foreach ($portfolio_structure as $index => $item) {
                            $gradient[] = $colors[$index % count($colors)] . ' ' . $current . '% ' . ($current + $item['percentage']) . '%';
                            $current += $item['percentage'];
                        }
                        ?>
                        <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                        <div class="chart-legend">
                            <?php foreach ($portfolio_structure as $index => $item): ?>
                            <div class="legend-item" style="align-items: flex-start;">
                                <span class="legend-color" style="width: 12px; height: 12px; background: <?= $colors[$index % count($colors)] ?>; border-radius: 4px; margin-top: 4px;"></span>
                                <span style="flex: 1;"><?= htmlspecialchars($item['category']) ?></span>
                                <span class="legend-value" style="text-align: right;">
                                    <div><?= $item['percentage'] ?>%</div>
                                    <?php if ($item['category'] == 'Рубли'): ?>
                                        <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($item['value'] ?? 0, 0, '.', ' ') ?> / <?= number_format($rub_amount_display, 0, '.', ' ') ?> ₽</div>
                                    <?php else: ?>
                                        <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($item['value'] ?? 0, 0, '.', ' ') ?></div>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php } else { ?>
                        <div class="pie" style="background: conic-gradient(#95a5a6 0% 100%);"></div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color" style="background: #95a5a6;"></span>
                                <span>Нет данных</span>
                                <span class="legend-value">0%</span>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Карточка крипто (только если есть данные) -->
            <?php if ($total_usdt_bought > 1 || $btc_cost > 0 || $eth_cost > 0 || $altcoins_cost > 0 || $stablecoins_left > 0): ?>
            <div class="card card-crypto">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #ff9f4a;"></i> Крипто</h3>
                    <span class="stat-badge">По активам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    $gradient_parts = [];
                    $current = 0;
                    $colors = ['#f7931a', '#627eea', '#14b8a6', '#a5a5a5'];
                    $segments = [];
                    
                    if ($btc_cost > 0) {
                        $segments[] = [
                            'name' => 'BTC', 
                            'value' => $btc_cost, 
                            'percent' => $btc_percent, 
                            'color' => '#f7931a', 
                            'icon' => 'fab fa-bitcoin',
                            'type' => 'btc'  // добавляем тип для идентификации
                        ];
                    }
                    if ($eth_cost > 0) {
                        $segments[] = [
                            'name' => 'ETH', 
                            'value' => $eth_cost, 
                            'percent' => $eth_percent, 
                            'color' => '#627eea', 
                            'icon' => 'fab fa-ethereum',
                            'type' => 'eth'
                        ];
                    }
                    if ($altcoins_cost > 0) {
                        $segments[] = [
                            'name' => 'Альткоины',  // переводим на русский
                            'value' => $altcoins_cost, 
                            'percent' => $altcoins_percent, 
                            'color' => '#14b8a6', 
                            'icon' => 'fas fa-chart-line',
                            'type' => 'altcoins'  // добавляем тип для идентификации
                        ];
                    }
                    if ($stablecoins_left > 0) {
                        $segments[] = [
                            'name' => 'Стейблкоины',  // переводим на русский
                            'value' => $stablecoins_left, 
                            'percent' => $stablecoins_percent, 
                            'color' => '#a5a5a5', 
                            'icon' => 'fas fa-coins',
                            'type' => 'stablecoins'  // добавляем тип для идентификации
                        ];
                    }
                    
                    foreach ($segments as $index => $segment) {
                        $gradient_parts[] = $segment['color'] . ' ' . $current . '% ' . ($current + $segment['percent']) . '%';
                        $current += $segment['percent'];
                    }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient_parts) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($segments as $segment): 
                            // Определяем, нужно ли делать элемент кликабельным
                            $isClickable = ($segment['type'] === 'altcoins' || $segment['type'] === 'stablecoins');
                            $onclickAttr = '';
                            $cursorStyle = '';
                            
                            if ($isClickable) {
                                $modalType = $segment['type'];
                                $modalName = $segment['name'];
                                $onclickAttr = "onclick=\"openCryptoTypeModal('{$modalType}', '{$modalName}')\"";
                                $cursorStyle = "cursor: pointer;";
                            }
                        ?>
                        <div class="legend-item" style="align-items: flex-start; <?= $cursorStyle ?>" 
                            <?= $onclickAttr ?>
                            <?php if ($isClickable): ?>
                            onmouseover="this.style.opacity='0.8'" 
                            onmouseout="this.style.opacity='1'"
                            <?php endif; ?>>
                            <span class="legend-color" style="width: 12px; height: 12px; background: <?= $segment['color'] ?>; margin-top: 4px;"></span>
                            <span style="flex: 1; display: flex; align-items: center; gap: 6px;">
                                <i class="<?= $segment['icon'] ?>" style="color: <?= $segment['color'] ?>; width: 16px;"></i>
                                <?= $segment['name'] ?>
                            </span>
                            <span class="legend-value" style="text-align: right;">
                                <div><?= $segment['percent'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($segment['value'], 0, '.', ' ') ?></div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <div class="legend-item" style="border-top: 1px solid #edf2f7; margin-top: 8px; padding-top: 8px;">
                            <span style="font-weight: 600;">Всего</span>
                            <span class="legend-value" style="font-weight: 600;">$<?= number_format($total_usdt_bought, 0, '.', ' ') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Карточка фондовый EN (только если есть данные) -->
            <?php if (!empty($en_sectors)): ?>
            <div class="card card-en-stocks">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #4a9eff;"></i> Фондовый (EN)</h3>
                    <span class="stat-badge">По секторам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    $colors = ['#4a9eff', '#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#6b7280'];
                    $gradient = [];
                    $current = 0;
                    
                    // Рассчитываем общую стоимость для фондового EN
                    $total_en_value = 0;
                    foreach ($en_sectors as $sector) {
                        $total_en_value += $sector['value_usd'];
                    }
                    
                    // Формируем градиент на основе процентов
                    foreach ($en_sectors as $index => $sector) {
                        $gradient[] = $colors[$index % count($colors)] . ' ' . $current . '% ' . ($current + $sector['percentage']) . '%';
                        $current += $sector['percentage'];
                    }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($en_sectors as $index => $sector): ?>
                        <div class="legend-item" style="align-items: flex-start; cursor: pointer;" 
                            onclick="openSectorAssetsModal('<?= htmlspecialchars($sector['original_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($sector['sector_name'], ENT_QUOTES) ?>')"
                            onmouseover="this.style.opacity='0.8'" 
                            onmouseout="this.style.opacity='1'">
                            <span class="legend-color" style="width: 12px; height: 12px; background: <?= $colors[$index % count($colors)] ?>; border-radius: 4px; margin-top: 4px;"></span>
                            <span style="flex: 1;"><?= htmlspecialchars($sector['sector_name']) ?></span>
                            <span class="legend-value" style="text-align: right;">
                                <div><?= $sector['percentage'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($sector['value_usd'], 0, '.', ' ') ?></div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- БЛОК "ВСЕГО" -->
                        <div class="legend-item" style="border-top: 1px solid #edf2f7; margin-top: 8px; padding-top: 8px;">
                            <span style="font-weight: 600;">Всего</span>
                            <span class="legend-value" style="font-weight: 600; text-align: right;">
                                $<?= number_format($total_en_value, 0, '.', ' ') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Карточка вклады (только если есть данные) -->
            <?php if (!empty($deposit_currencies)): ?>
            <div class="card card-deposits">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #2ecc71;"></i> Вклады</h3>
                    <span class="stat-badge">По валютам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    $colors = ['#2ecc71', '#3498db', '#f1c40f', '#e67e22', '#95a5a6'];
                    $gradient = [];
                    $current = 0;
                    
                    foreach ($deposit_currencies as $index => $currency) {
                        $gradient[] = $colors[$index % count($colors)] . ' ' . $current . '% ' . ($current + $currency['percentage']) . '%';
                        $current += $currency['percentage'];
                    }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($deposit_currencies as $index => $currency): ?>
                        <div class="legend-item" style="align-items: flex-start;">
                            <span class="legend-color" style="width: 12px; height: 12px; background: <?= $colors[$index % count($colors)] ?>; border-radius: 4px; margin-top: 4px;"></span>
                            <span style="flex: 1;"><?= htmlspecialchars($currency['name'] ?? $currency['currency_code']) ?> (<?= $currency['currency_code'] ?>)</span>
                            <span class="legend-value" style="text-align: right;">
                                <div><?= $currency['percentage'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$0</div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Карточка мои активы -->
            <div class="card card-investments">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-coins"></i> Мои активы</h3>
                    <span class="stat-badge"><?= count($assets) ?> активов</span>
                </div>
                <table class="investments-table">
                    <tbody>
                        <?php foreach ($assets as $asset): 
                            if ($asset['symbol'] == 'RUB') {
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        d.deposit_date as date,
                                        d.amount as quantity,
                                        NULL as price,
                                        d.currency_code as price_currency,
                                        p.name as platform,
                                        'deposit' as operation_type,
                                        CONCAT('Пополнение ', d.currency_code) as description
                                    FROM deposits d
                                    LEFT JOIN platforms p ON d.platform_id = p.id
                                    WHERE d.currency_code = 'RUB'
                                    ORDER BY d.deposit_date DESC
                                ");
                                $stmt->execute();
                                $asset_history = $stmt->fetchAll();
                                
                                $avg_price_display = '—';
                                $avg_currency = '';
                            } else {
                                // Используем среднюю цену из объединенного запроса
                                $avg_price = $asset['avg_price'];
                                
                                // Новая логика форматирования средней цены
                                // Убираем отображение валюты для USDT, USDC и фиатных валют (USD, RUB, EUR)
                                if ($asset['symbol'] == 'USDT' || $asset['symbol'] == 'USDC' || 
                                    $asset['symbol'] == 'USD' || $asset['symbol'] == 'RUB' || $asset['symbol'] == 'EUR') {
                                    // Для стейблкоинов и фиатных валют не показываем валюту
                                    $avg_price_display = number_format($avg_price, 2, '.', ' ');
                                    $avg_currency = '';
                                } elseif ($asset['type'] == 'crypto') {
                                    // Для криптовалют: максимум 4 знака после запятой, убираем лишние нули
                                    $formatted = number_format($avg_price, 4, '.', ' ');
                                    // Убираем лишние нули в конце дробной части
                                    $formatted = rtrim(rtrim($formatted, '0'), '.');
                                    $avg_price_display = $formatted;
                                    $avg_currency = ''; // Не показываем валюту для крипто
                                } else {
                                    // Для остальных активов (акции, ETF, облигации) показываем валюту
                                    $avg_price_display = number_format($avg_price, 2, '.', ' ');
                                    $avg_currency = $asset['currency_code'];
                                }
                                
                                $stmt = $pdo->prepare("
                                    (SELECT 
                                        t.operation_date as date,
                                        t.quantity,
                                        t.price,
                                        t.price_currency,
                                        p.name as platform,
                                        'buy' as operation_type,
                                        CONCAT('Покупка ', a.symbol) as description
                                    FROM trades t
                                    LEFT JOIN platforms p ON t.platform_id = p.id
                                    JOIN assets a ON t.asset_id = a.id
                                    WHERE t.asset_id = ? AND t.operation_type = 'buy')
                                    
                                    UNION ALL
                                    
                                    (SELECT 
                                        t.operation_date as date,
                                        t.quantity,
                                        t.price,
                                        t.price_currency,
                                        p.name as platform,
                                        'sell' as operation_type,
                                        CONCAT('Продажа ', a.symbol) as description
                                    FROM trades t
                                    LEFT JOIN platforms p ON t.platform_id = p.id
                                    JOIN assets a ON t.asset_id = a.id
                                    WHERE t.asset_id = ? AND t.operation_type = 'sell')
                                    
                                    ORDER BY date DESC
                                ");
                                
                                $stmt->execute([$asset['id'], $asset['id']]);
                                $asset_history = $stmt->fetchAll();
                            }
                        ?>
                        <tr onclick='showAssetHistory(<?= json_encode([
                            'symbol' => $asset['symbol'],
                            'history' => $asset_history
                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="cursor: pointer;">
                            <td class="investment-icon-cell">
                                <div class="investment-icon">
                                    <?php
                                    $symbols = [
                                        'RUB' => '₽', 'USD' => '$', 'EUR' => '€',
                                        'BTC' => '₿', 'ETH' => 'Ξ', 'USDT' => '₮'
                                    ];
                                    echo $symbols[$asset['symbol']] ?? substr($asset['symbol'], 0, 2);
                                    ?>
                                </div>
                            </td>
                            <td class="investment-name-cell">
                                <span class="investment-name"><?= htmlspecialchars($asset['symbol']) ?></span>
                                <?php if (strpos($asset['platform_ids'] ?? '', ',') !== false): ?>
                                    <span style="font-size: 10px; color: #6b7a8f; display: block;">на нескольких площадках</span>
                                <?php endif; ?>
                            </td>
                            <td class="investment-amount-cell">
                                <span class="investment-amount">
                                    <?php
                                    if ($asset['symbol'] == 'RUB') {
                                        echo number_format($asset['total_quantity'], 0, '.', ' ');
                                    } elseif ($asset['type'] == 'crypto') {
                                        if (floor($asset['total_quantity']) == $asset['total_quantity']) {
                                            echo number_format($asset['total_quantity'], 0, '.', ' ');
                                        } else {
                                            echo rtrim(rtrim(number_format($asset['total_quantity'], 8, '.', ' '), '0'), '.');
                                        }
                                    } elseif ($asset['type'] == 'stock') {
                                        echo number_format($asset['total_quantity'], 0, '.', ' ') . ' шт';
                                    } else {
                                        echo number_format($asset['total_quantity'], 0, '.', ' ');
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="investment-change-cell">
                                <span class="investment-change">
                                    <?php if ($avg_price_display !== '—' && $avg_price_display != ''): ?>
                                        <?= $avg_price_display ?> <?= $avg_currency ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Карточка последние операции -->
            <div class="card card-operations" id="operationsContainer">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history"></i> Последние операции</h3>
                    <a href="operations.php" class="all-ops-btn" style="
                        flex: 0 1 auto;
                        justify-content: center;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        border: 1px solid transparent;
                        background: #f0f3f7;
                        color: #6b7a8f;
                        font-weight: 500;
                        padding: 8px 16px;
                        border-radius: 12px;
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        transform: translateY(0);
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
                        font-size: 14px;
                        text-decoration: none;
                    " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 16px rgba(26, 92, 255, 0.15)'; this.style.background='white'; this.style.borderColor='#1a5cff'; this.style.color='#1a5cff';" 
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0, 0, 0, 0.02)'; this.style.background='#f0f3f7'; this.style.borderColor='transparent'; this.style.color='#6b7a8f';">
                        <i class="fas fa-list-ul"></i> Все операции
                    </a>
                </div>
                
                <div id="operationsList">
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                
                <div id="paginationControls"></div>
            </div>

            <!-- Карточка лимитные ордера -->
            <div class="card card-orders">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clock"></i> Лимитные ордера</h3>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="stat-badge"><?= count($orders) ?></span>
                    </div>
                </div>

                <!-- Кнопка добавления вверху списка -->
                <div style="margin-bottom: 15px; text-align: center;">
                    <button class="add-order-btn" onclick="openLimitOrderModal()">
                        <i class="fas fa-plus-circle"></i> Создать новый ордер
                    </button>
                </div>
                
                <div id="limitOrdersList">
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): 
                            // Рассчитываем прогресс (если нужно)
                            $progress = 0;
                            $progressClass = '';
                            $daysLeft = 0;
                            if ($order['expiry_date']) {
                                $daysLeft = (strtotime($order['expiry_date']) - time()) / (60 * 60 * 24);
                                if ($daysLeft < 1) {
                                    $progressClass = 'danger';
                                } elseif ($daysLeft < 3) {
                                    $progressClass = 'warning';
                                }
                            }
                        ?>
                        <div class="order-card" id="order-<?= $order['id'] ?>" style="border-left-color: <?= $order['operation_type'] == 'buy' ? '#00a86b' : '#e53e3e' ?>;">
                            <div class="order-exchange">
                                <i class="fas fa-<?= $order['platform_type'] == 'exchange' ? 'chart-line' : 'building' ?>"></i>
                                <?= strtoupper(htmlspecialchars($order['platform_name'])) ?>
                            </div>
                            <div class="order-details">
                                <span class="order-action">
                                    <i class="fas fa-<?= $order['operation_type'] == 'buy' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                    <?= $order['operation_type'] == 'buy' ? 'Покупка' : 'Продажа' ?> <?= htmlspecialchars($order['symbol']) ?>
                                </span>
                                <span class="order-price"><?= number_format($order['limit_price'], 2) ?> <?= $order['price_currency'] ?></span>
                            </div>
                            <div class="order-footer">
                                <span><i class="far fa-clock"></i> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></span>
                                <span><?= number_format($order['quantity'], 4) ?> шт</span>
                            </div>
                            <?php if ($order['expiry_date']): ?>
                            <div class="order-progress">
                                <div class="order-progress-bar <?= $progressClass ?>" style="width: <?= min(100, (1 - $daysLeft/30) * 100) ?>%;"></div>
                            </div>
                            <div style="font-size: 11px; color: #6b7a8f; margin-top: 5px; text-align: right;">
                                <i class="far fa-hourglass"></i> до <?= date('d.m.Y', strtotime($order['expiry_date'])) ?> (<?= round($daysLeft) ?> дн.)
                            </div>
                            <?php endif; ?>
                            
                            <!-- Кнопки действий -->
                            <div style="display: flex; gap: 8px; margin-top: 12px; justify-content: flex-end;">
                                <button class="quick-platform-btn" onclick="showExecuteConfirmation(<?= $order['id'] ?>)" 
                                        style="background: #00a86b; color: white; border: none; min-width: 100px;">
                                    <i class="fas fa-check-circle"></i> Исполнить
                                </button>
                                <button class="quick-platform-btn" onclick="showCancelConfirmation(<?= $order['id'] ?>)" 
                                        style="background: #e53e3e; color: white; border: none; min-width: 100px;">
                                    <i class="fas fa-times-circle"></i> Отменить
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="order-empty">
                            <i class="fas fa-clock"></i>
                            <p>Нет активных лимитных ордеров</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Карточка план действий -->
            <div class="card card-plan" style="display: none;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-check-circle"></i> План действий</h3>
                    <?php
                    $completed = 0;
                    foreach ($action_plan as $item) {
                        if ($item['is_completed']) $completed++;
                    }
                    $total = count($action_plan);
                    ?>
                    <span class="stat-badge"><?= $completed ?>/<?= $total ?> выполнено</span>
                </div>
                <?php foreach ($action_plan as $item): ?>
                <div class="checklist-item">
                    <div class="checklist-checkbox <?= $item['is_completed'] ? 'checked' : '' ?>"></div>
                    <span class="checklist-text <?= $item['is_completed'] ? 'completed' : '' ?>">
                        <?= htmlspecialchars($item['title']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Карточка заметки -->
            <div class="card card-notes">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sticky-note"></i> Заметки</h3>
                    <div style="display: flex; gap: 8px;">
                        <button class="view-archive-btn" onclick="openArchivedNotesModal()">
                            <i class="fas fa-archive"></i> Архив
                        </button>
                    </div>
                </div>
                
                <div id="notesList">
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
// ============================================================================
// ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ
// ============================================================================

const depositModal = document.getElementById('depositModal');
const tradeModal = document.getElementById('tradeModal');
const transferModal = document.getElementById('transferModal');
const tradeOperationType = document.getElementById('tradeOperationType');
const confirmTradeBtnText = document.getElementById('confirmTradeBtnText');

let selectedTransferAsset = { id: null, symbol: '' };
let selectedFromPlatform = { id: null, name: '' };
let selectedToPlatform = { id: null, name: '' };
let selectedCommissionCurrency = { code: '' };

let selectedTradePlatform = { id: null, name: '' };
let selectedTradeFromPlatform = { id: null, name: '' };
let selectedTradeAsset = { id: null, symbol: '', type: '' };
let selectedTradePriceCurrency = { code: '' };
let selectedTradeCommissionCurrency = { code: '' };

let selectedCurrency = { code: '', name: '' };
let selectedPlatform = { id: null, name: '' };
let selectedTradeNetwork = { name: '' };

// ============================================================================
// ДАННЫЕ ИЗ PHP
// ============================================================================

const platformsData = <?= $platforms_json ?>;
const assetsData = <?= $assets_json ?>;
const allCurrencies = <?= $currencies_json ?>;
const fiatCurrencies = <?= $fiat_currencies_json ?>;

// ============================================================================
// ЦВЕТОВЫЕ СХЕМЫ
// ============================================================================

const modalColorSchemes = {
    deposit: {
        platform: {
            headerIcon: '#00a86b',
            headerTitle: 'Выберите площадку',
            listItemColor: '#00a86b',
            addButtonColor: '#00a86b'
        },
        currency: {
            headerIcon: '#00a86b',
            headerTitle: 'Выберите валюту',
            listItemColor: '#00a86b',
            addButtonColor: '#00a86b'
        },
        addPlatform: {
            headerIcon: '#00a86b',
            confirmButton: '#00a86b'
        },
        addCurrency: {
            headerIcon: '#00a86b',
            confirmButton: '#00a86b'
        }
    },
    buy: {
        platform: {
            headerIcon: '#00a86b',
            headerTitle: 'Выберите площадку покупки',
            listItemColor: '#00a86b',
            addButtonColor: '#00a86b'
        },
        currency: {
            headerIcon: '#00a86b',
            headerTitle: 'Выберите валюту цены',
            listItemColor: '#00a86b',
            addButtonColor: '#00a86b'
        },
        addPlatform: {
            headerIcon: '#00a86b',
            confirmButton: '#00a86b'
        },
        addCurrency: {
            headerIcon: '#00a86b',
            confirmButton: '#00a86b'
        }
    },
    sell: {
        platform: {
            headerIcon: '#e53e3e',
            headerTitle: 'Выберите площадку продажи',
            listItemColor: '#e53e3e',
            addButtonColor: '#e53e3e'
        },
        currency: {
            headerIcon: '#e53e3e',
            headerTitle: 'Выберите валюту цены',
            listItemColor: '#e53e3e',
            addButtonColor: '#e53e3e'
        },
        addPlatform: {
            headerIcon: '#e53e3e',
            confirmButton: '#e53e3e'
        },
        addCurrency: {
            headerIcon: '#e53e3e',
            confirmButton: '#e53e3e'
        }
    },
    transfer: {
        platform: {
            headerIcon: '#ff9f4a',
            headerTitleFrom: 'Выберите площадку отправителя',
            headerTitleTo: 'Выберите площадку получателя',
            listItemColor: '#ff9f4a',
            addButtonColor: '#ff9f4a'
        },
        currency: {
            headerIcon: '#ff9f4a',
            headerTitleAsset: 'Выберите актив',
            headerTitleCommission: 'Выберите валюту комиссии',
            listItemColor: '#ff9f4a',
            addButtonColor: '#ff9f4a'
        },
        addPlatform: {
            headerIcon: '#ff9f4a',
            confirmButton: '#ff9f4a'
        },
        addCurrency: {
            headerIcon: '#ff9f4a',
            confirmButton: '#ff9f4a'
        }
    },
    buy_from: {
        platform: {
            headerIcon: '#4a9eff',
            headerTitle: 'Выберите площадку списания',
            listItemColor: '#4a9eff',
            addButtonColor: '#4a9eff'
        }
    },
    default: {
        platform: {
            headerIcon: '#1a5cff',
            headerTitle: 'Выберите площадку',
            listItemColor: '#1a5cff',
            addButtonColor: '#1a5cff'
        },
        currency: {
            headerIcon: '#1a5cff',
            headerTitle: 'Выберите валюту',
            listItemColor: '#1a5cff',
            addButtonColor: '#1a5cff'
        },
        addPlatform: {
            headerIcon: '#1a5cff',
            confirmButton: '#1a5cff'
        },
        addCurrency: {
            headerIcon: '#1a5cff',
            confirmButton: '#1a5cff'
        }
    }
};

let currentModalContext = {
    source: 'default',
    mode: null,
    subMode: null
};

// ============================================================================
// ФУНКЦИИ ДЛЯ БЛОКИРОВКИ СКРОЛЛА
// ============================================================================

function disableBodyScroll() {
    // Сохраняем текущую позицию скролла
    const scrollY = window.scrollY;
    document.body.style.position = 'fixed';
    document.body.style.top = `-${scrollY}px`;
    document.body.style.width = '100%';
    document.body.dataset.scrollPosition = scrollY;
}

function enableBodyScroll() {
    const scrollY = document.body.dataset.scrollPosition;
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    window.scrollTo(0, parseInt(scrollY || '0'));
    delete document.body.dataset.scrollPosition;
}

function setModalContext(source, mode, subMode = null) {
    currentModalContext = { source, mode, subMode };
}

function getColorScheme() {
    const source = currentModalContext.source || 'default';
    const subMode = currentModalContext.subMode;
    
    let scheme = modalColorSchemes[source] || modalColorSchemes.default;
    
    if (source === 'buy' && subMode === 'from') {
        return modalColorSchemes.buy_from;
    }
    
    return scheme;
}

// ============================================================================
// ФУНКЦИИ ФОРМАТИРОВАНИЯ ЧИСЕЛ
// ============================================================================

function formatNumberWithSpaces(value, decimals = null) {
    if (!value && value !== 0) return '';
    
    let numStr = String(value).replace(/\s/g, '').replace(',', '.');
    
    if (isNaN(parseFloat(numStr))) return value;
    
    let num = parseFloat(numStr);
    
    // Определяем количество знаков после запятой
    let decimalPlaces = decimals !== null ? decimals : 6;
    let formatted = num.toFixed(decimalPlaces);
    
    // Убираем лишние нули в конце
    formatted = formatted.replace(/\.?0+$/, '');
    
    // Разделяем целую и дробную части
    let parts = formatted.split('.');
    // Добавляем пробелы между разрядами в целой части
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Если есть дробная часть, возвращаем без пробелов в ней
    if (parts.length > 1 && parts[1]) {
        return parts[0] + '.' + parts[1];
    } else {
        return parts[0];
    }
}

function getNumericValue(formattedValue) {
    if (!formattedValue) return 0;
    const cleanValue = String(formattedValue).replace(/\s/g, '').replace(',', '.');
    const num = parseFloat(cleanValue);
    return isNaN(num) ? 0 : num;
}

function formatInput(input) {
    if (!input) return;
    
    const cursorPos = input.selectionStart;
    const value = input.value;
    const oldLength = value.length;
    
    if (value === '' || value === '-') {
        return;
    }
    
    // Заменяем запятую на точку для единообразия
    let rawValue = value.replace(/\s/g, '').replace(',', '.');
    
    // Проверяем на наличие букв
    if (/[a-zA-Zа-яА-Я]/.test(rawValue)) {
        return;
    }
    
    // Разрешаем временное состояние с точкой
    // Это ключевое условие - позволяет вводить точку
    if (rawValue === '.' || rawValue === '-.' || rawValue === '0.' || rawValue === '0.0') {
        input.value = rawValue;
        // Восстанавливаем позицию курсора после точки
        const newCursorPos = input.value.length;
        input.setSelectionRange(newCursorPos, newCursorPos);
        return;
    }
    
    // Проверяем, заканчивается ли строка на точку (например, "123.")
    if (rawValue.endsWith('.')) {
        // Сохраняем значение без точки для проверки
        const valueWithoutDot = rawValue.slice(0, -1);
        // Проверяем, что часть до точки - валидное число
        if (/^-?\d*$/.test(valueWithoutDot) || valueWithoutDot === '' || valueWithoutDot === '-') {
            input.value = rawValue;
            const newCursorPos = input.value.length;
            input.setSelectionRange(newCursorPos, newCursorPos);
            return;
        }
    }
    
    // Проверяем валидность числа
    if (!/^-?\d*\.?\d*$/.test(rawValue)) {
        return;
    }
    
    // Преобразуем в число
    let num = parseFloat(rawValue);
    if (isNaN(num)) {
        input.value = rawValue;
        return;
    }
    
    // Определяем, есть ли в исходной строке точка
    const hasDecimalPoint = rawValue.includes('.');
    
    // Определяем количество знаков после запятой, которые ввел пользователь
    let originalDecimalPlaces = 0;
    const decimalMatch = rawValue.match(/\.(\d+)$/);
    if (decimalMatch) {
        originalDecimalPlaces = decimalMatch[1].length;
    }
    
    // Если есть точка и есть цифры после нее - сохраняем исходный формат
    if (hasDecimalPoint && originalDecimalPlaces > 0) {
        let parts = rawValue.split('.');
        // Форматируем целую часть с пробелами
        if (parts[0] && parts[0] !== '' && parts[0] !== '-') {
            // Убираем ведущие нули, но сохраняем ноль если число начинается с 0
            let integerPart = parts[0];
            if (integerPart.length > 1 && integerPart.startsWith('0') && !integerPart.startsWith('0.')) {
                integerPart = integerPart.replace(/^0+/, '');
                if (integerPart === '') integerPart = '0';
            }
            integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            parts[0] = integerPart;
        } else if (parts[0] === '') {
            parts[0] = '0';
        } else if (parts[0] === '-') {
            parts[0] = '-0';
        }
        input.value = parts.join('.');
        
        const newLength = input.value.length;
        const lengthDiff = newLength - oldLength;
        const newCursorPos = Math.min(cursorPos + lengthDiff, newLength);
        input.setSelectionRange(newCursorPos, newCursorPos);
        return;
    }
    
    // Ноль без точки
    if (num === 0 && !hasDecimalPoint) {
        input.value = '0';
        return;
    }
    
    // Целые числа
    if (Number.isInteger(num) && !hasDecimalPoint) {
        input.value = num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const newLength = input.value.length;
        const lengthDiff = newLength - oldLength;
        const newCursorPos = Math.min(cursorPos + lengthDiff, newLength);
        input.setSelectionRange(newCursorPos, newCursorPos);
        return;
    }
    
    // Остальные числа с дробной частью
    // Сохраняем количество знаков после запятой, которое ввел пользователь
    let decimals = originalDecimalPlaces > 0 ? originalDecimalPlaces : 8;
    let formatted = num.toFixed(decimals);
    let parts = formatted.split('.');
    let integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    let decimalPart = parts[1].replace(/0+$/, '');
    
    let formattedValue = decimalPart.length > 0 ? integerPart + '.' + decimalPart : integerPart;
    input.value = formattedValue;
    
    const newLength = formattedValue.length;
    const lengthDiff = newLength - oldLength;
    let newCursorPos = Math.min(cursorPos + lengthDiff, newLength);
    
    // Корректируем позицию курсора, если вводили точку
    if (rawValue === '.') {
        newCursorPos = formattedValue.indexOf('.') + 1;
    }
    
    input.setSelectionRange(newCursorPos, newCursorPos);
}

// Функция для форматирования итоговой суммы
function formatTotalAmount(value) {
    if (!value && value !== 0) return '0';
    
    let num = parseFloat(value);
    if (isNaN(num)) return '0';
    
    // Форматируем с 6 знаками после запятой
    let formatted = num.toFixed(6);
    
    // Разделяем целую и дробную части
    let parts = formatted.split('.');
    let integerPart = parts[0];
    let decimalPart = parts[1];
    
    // Добавляем пробелы между разрядами в целой части
    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Убираем лишние нули в конце дробной части
    decimalPart = decimalPart.replace(/0+$/, '');
    
    // Формируем итоговую строку
    if (decimalPart.length > 0) {
        return integerPart + '.' + decimalPart;
    } else {
        return integerPart;
    }
}

// ============================================================================
// ФУНКЦИИ ПОИСКА
// ============================================================================

function findPlatformIdByName(name) {
    const platform = platformsData.find(p => p.name.toLowerCase() === name.toLowerCase());
    return platform ? platform.id : null;
}

function findAssetIdBySymbol(symbol) {
    const asset = assetsData.find(a => a.symbol.toUpperCase() === symbol.toUpperCase());
    return asset ? asset.id : null;
}

function getAssetTypeBySymbol(symbol) {
    const asset = assetsData.find(a => a.symbol.toUpperCase() === symbol.toUpperCase());
    return asset ? asset.type : null;
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА АКТИВА (НОВЫЕ)
// ============================================================================

function openAssetModal(context = 'default', subMode = null) {
    setModalContext(context, 'asset', subMode);
    
    const modalTitle = document.querySelector('#currencySelectModal .modal-header h2');
    modalTitle.innerHTML = '<i class="fas fa-coins" style="color: #ff9f4a;"></i> Выберите актив';
    
    const modal = document.getElementById('currencySelectModal');
    if (modal) {
        filterAssetsForSelect('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('currencySearch')?.focus();
        }, 100);
    }
}

function filterAssetsForSelect(searchText) {
    const listContainer = document.getElementById('allCurrenciesList');
    if (!listContainer) return;
    
    const searchTextLower = searchText.toLowerCase().trim();
    const originalSearchText = searchText.trim();
    
    let assetsToShow = assetsData;
    if (searchTextLower) {
        assetsToShow = assetsData.filter(a => 
            a.symbol.toLowerCase().includes(searchTextLower) || 
            (a.name && a.name.toLowerCase().includes(searchTextLower))
        );
    }
    
    assetsToShow.sort((a, b) => {
        const typeOrder = { 'crypto': 1, 'stock': 2, 'etf': 3, 'currency': 4, 'bond': 5, 'other': 6 };
        return (typeOrder[a.type] || 99) - (typeOrder[b.type] || 99);
    });
    
    if (assetsToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `
            <div onclick="addNewAssetFromCurrencyModal('${originalSearchText.replace(/'/g, "\\'")}')" 
                 style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: #ff9f4a; transition: all 0.2s;" 
                 onmouseover="this.style.background='#f0f3f7'" 
                 onmouseout="this.style.background='transparent'">
                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i>
                Добавить "${originalSearchText}"
            </div>
        `;
        return;
    }
    
    listContainer.innerHTML = assetsToShow.map(asset => {
        let iconColor = '#6b7a8f';
        let typeIcon = 'fa-coins';
        let typeText = '';
        
        switch(asset.type) {
            case 'crypto':
                iconColor = '#f7931a';
                typeIcon = 'fa-bitcoin';
                typeText = 'Крипто';
                break;
            case 'stock':
                iconColor = '#00a86b';
                typeIcon = 'fa-chart-line';
                typeText = 'Акция';
                break;
            case 'etf':
                iconColor = '#4a9eff';
                typeIcon = 'fa-chart-pie';
                typeText = 'ETF';
                break;
            case 'currency':
                iconColor = '#1a5cff';
                typeIcon = 'fa-money-bill';
                typeText = 'Валюта';
                break;
            case 'bond':
                iconColor = '#9b59b6';
                typeIcon = 'fa-file-invoice';
                typeText = 'Облигация';
                break;
            default:
                typeText = 'Другое';
        }
        
        return `
            <div onclick="selectAssetFromModal('${asset.id}', '${asset.symbol.replace(/'/g, "\\'")}', '${asset.type || 'other'}')" 
                 style="padding: 12px; cursor: pointer; border-radius: 10px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; border: 1px solid transparent;"
                 onmouseover="this.style.background='#f8fafd'; this.style.borderColor='#e0e6ed'" 
                 onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'">
                <div style="width: 36px; height: 36px; background: ${iconColor}15; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${iconColor};">
                    <i class="fas ${typeIcon}"></i>
                </div>
                <div style="flex: 1;">
                    <div class="asset-symbol">${asset.symbol}</div>
                    <div style="font-size: 12px; color: #6b7a8f; display: flex; gap: 8px; margin-top: 2px;">
                        <span>${asset.name || ''}</span>
                        <span style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 12px;">${typeText}</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right" style="color: #95a5a6; font-size: 12px;"></i>
            </div>
        `;
    }).join('');
}

function selectAssetFromModal(id, symbol, type) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    
    if (context === 'transfer' && subMode === 'asset') {
        selectAsset(id, symbol);
    } else if (context === 'buy' || context === 'sell') {
        selectTradeAsset(id, symbol, type);
    }
    
    closeCurrencyModal();
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ВАЛЮТЫ (ОРИГИНАЛЬНЫЕ, НО ИСПРАВЛЕННЫЕ)
// ============================================================================

function openCurrencyModal(context = 'default', subMode = null) {
    if (subMode === 'asset') {
        openAssetModal(context, subMode);
        return;
    }
    
    setModalContext(context, 'currency', subMode);
    const scheme = getColorScheme();
    const currencyScheme = scheme.currency || modalColorSchemes.default.currency;
    
    const modalTitle = document.querySelector('#currencySelectModal .modal-header h2');
    let titleText = currencyScheme.headerTitle;
    
    if (context === 'transfer') {
        if (subMode === 'commission') {
            titleText = currencyScheme.headerTitleCommission || 'Выберите валюту комиссии';
        }
    } else if (context === 'buy' || context === 'sell') {
        if (subMode === 'price') {
            titleText = 'Выберите валюту цены';
        } else if (subMode === 'commission') {
            titleText = 'Выберите валюту комиссии';
        }
    } else if (context === 'deposit') {
        titleText = 'Выберите валюту';
    }
    
    modalTitle.innerHTML = `<i class="fas fa-coins" style="color: ${currencyScheme.headerIcon};"></i> ${titleText}`;
    
    const modal = document.getElementById('currencySelectModal');
    if (modal) {
        filterCurrencies('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('currencySearch')?.focus();
        }, 100);
    }
}

function filterCurrencies(searchText) {
    // Если текущий режим - выбор актива, используем filterAssetsForSelect
    if (currentModalContext && currentModalContext.mode === 'asset') {
        filterAssetsForSelect(searchText);
        return;
    }
    
    const listContainer = document.getElementById('allCurrenciesList');
    if (!listContainer) return;
    
    const scheme = getColorScheme();
    const currencyScheme = scheme.currency || modalColorSchemes.default.currency;
    
    const searchTextLower = searchText.toLowerCase().trim();
    const originalSearchText = searchText.trim();
    
    let currenciesToShow = allCurrencies;
    if (searchTextLower) {
        currenciesToShow = allCurrencies.filter(c => 
            c.code.toLowerCase().includes(searchTextLower) || 
            (c.name && c.name.toLowerCase().includes(searchTextLower))
        );
    }
    
    if (currenciesToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `
            <div onclick="addNewCurrencyAndSelect('${originalSearchText.replace(/'/g, "\\'")}', '${currentModalContext.source}', '${currentModalContext.subMode || 'default'}')" 
                 style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: ${currencyScheme.addButtonColor}; transition: all 0.2s;" 
                 onmouseover="this.style.background='#f0f3f7'" 
                 onmouseout="this.style.background='transparent'">
                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i>
                Добавить "${originalSearchText.toUpperCase()}"
            </div>
        `;
        return;
    }
    
    listContainer.innerHTML = currenciesToShow.map(currency => `
        <div onclick="selectCurrencyFromList('${currency.code}', '${currency.name || currency.code}')" 
             style="padding: 12px; cursor: pointer; border-radius: 10px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; border: 1px solid transparent;"
             onmouseover="this.style.background='#f8fafd'; this.style.borderColor='#e0e6ed'" 
             onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'">
            <div style="width: 36px; height: 36px; background: ${currencyScheme.listItemColor}15; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${currencyScheme.listItemColor};">
                <i class="fas fa-coins"></i>
            </div>
            <div style="flex: 1;">
                <div class="asset-symbol">${currency.code}</div>
                <div style="font-size: 12px; color: #6b7a8f;">${currency.name || ''}</div>
            </div>
            <i class="fas fa-chevron-right" style="color: #95a5a6; font-size: 12px;"></i>
        </div>
    `).join('');
}

function closeCurrencyModal() {
    const modal = document.getElementById('currencySelectModal');
    if (modal) {
        modal.classList.remove('active');
        document.getElementById('currencySearch').value = '';
    }
}

function selectCurrencyFromList(code, name) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    
    if (context === 'transfer') {
        if (subMode === 'commission') {
            selectCommissionCurrency(code);
        }
    } else if (context === 'buy' || context === 'sell') {
        if (subMode === 'price') {
            selectTradePriceCurrency(code);
        } else if (subMode === 'commission') {
            selectTradeCommissionCurrency(code);
        }
    } else if (context === 'deposit') {
        selectCurrency(code, name);
    } else if (context === 'limit') {
        selectLimitCurrency(code);
    } else if (context === 'expense') {   // ← ДОБАВЬТЕ ЭТУ СТРОКУ
        // Для расходов - обновляем отображение валюты
        document.getElementById('selectedExpenseCurrencyDisplay').textContent = code;
    }
    
    closeCurrencyModal();
}

function selectCurrency(code, name) {
    selectedCurrency = { code, name };
    
    const display = document.getElementById('selectedCurrencyDisplay');
    if (display) {
        display.textContent = code;
    }
    
    const hiddenInput = document.getElementById('depositCurrency');
    if (hiddenInput) {
        hiddenInput.value = code;
    }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ПЛОЩАДКИ
// ============================================================================

function openPlatformModal(context = 'default', subMode = null) {
    setModalContext(context, 'platform', subMode);
    const scheme = getColorScheme();
    const platformScheme = scheme.platform || modalColorSchemes.default.platform;
    
    const modalTitle = document.querySelector('#platformSelectModal .modal-header h2');
    let titleText = platformScheme.headerTitle;
    
    if (context === 'transfer') {
        if (subMode === 'from') {
            titleText = platformScheme.headerTitleFrom || 'Выберите площадку отправителя';
        } else if (subMode === 'to') {
            titleText = platformScheme.headerTitleTo || 'Выберите площадку получателя';
        }
    } else if (context === 'buy' && subMode === 'from') {
        titleText = 'Выберите площадку списания';
    }
    
    modalTitle.innerHTML = `<i class="fas fa-building" style="color: ${platformScheme.headerIcon};"></i> ${titleText}`;
    
    const modal = document.getElementById('platformSelectModal');
    if (modal) {
        filterPlatforms('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('platformSearch')?.focus();
        }, 100);
    }
}

function closePlatformModal() {
    const modal = document.getElementById('platformSelectModal');
    if (modal) {
        modal.classList.remove('active');
        document.getElementById('platformSearch').value = '';
    }
}

function filterPlatforms(searchText) {
    const listContainer = document.getElementById('allPlatformsList');
    if (!listContainer) return;
    
    const scheme = getColorScheme();
    const platformScheme = scheme.platform || modalColorSchemes.default.platform;
    
    const searchTextLower = searchText.toLowerCase().trim();
    const originalSearchText = searchText.trim();
    
    // Используем platformsData (глобальный массив, который обновляется)
    let platformsToShow = platformsData;
    if (searchTextLower) {
        platformsToShow = platformsData.filter(p => 
            p.name.toLowerCase().includes(searchTextLower)
        );
    }
    
    if (platformsToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `
            <div onclick="addNewPlatformAndSelect('${originalSearchText.replace(/'/g, "\\'")}', '${currentModalContext.source}')" style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: ${platformScheme.addButtonColor}; transition: all 0.2s;" 
                 onmouseover="this.style.background='#f0f3f7'" 
                 onmouseout="this.style.background='transparent'">
                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i>
                Добавить "${originalSearchText}"
            </div>
        `;
        return;
    }
    
    listContainer.innerHTML = platformsToShow.map(platform => `
        <div onclick="selectPlatformFromList('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px; transition: background 0.2s;" 
             onmouseover="this.style.background='#f0f3f7'" 
             onmouseout="this.style.background='transparent'">
            <span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span>
            <span style="color: ${platformScheme.listItemColor}; font-size: 12px; margin-left: auto;"><i class="fas fa-chevron-right"></i></span>
        </div>
    `).join('');
}

function selectPlatformFromList(id, name) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    
    if (context === 'transfer') {
        if (subMode === 'from') {
            selectFromPlatform(id, name);
        } else if (subMode === 'to') {
            selectToPlatform(id, name);
        }
    } else if (context === 'buy' && subMode === 'from') {
        selectTradeFromPlatform(id, name);
    } else if (context === 'buy' || context === 'sell') {
        selectTradePlatform(id, name);
    } else if (context === 'deposit') {
        selectPlatform(id, name);
    }
    
    closePlatformModal();
}

function selectPlatform(id, name) {
    selectedPlatform = { id, name };
    window.currentPlatformMode = 'platform'; // Добавляем для отслеживания
    
    const display = document.getElementById('selectedPlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('depositPlatformId');
    if (hiddenInput) hiddenInput.value = id;
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ПЛОЩАДКИ В ТОРГОВЛЕ
// ============================================================================

function selectTradePlatform(id, name) {
    selectedTradePlatform = { id, name };
    window.currentPlatformMode = 'trade';
    
    const display = document.getElementById('selectedTradePlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('tradePlatformId');
    if (hiddenInput) hiddenInput.value = id;
    
    // НОВЫЙ КОД: Если это продажа и актив уже выбран, загружаем историю
    const operationType = document.getElementById('tradeOperationType').value;
    const assetId = document.getElementById('tradeAssetId').value;
    
    if (operationType === 'sell' && assetId) {
        loadPurchaseHistoryForSell(assetId, id);
    } else if (operationType === 'sell') {
        // Если актив еще не выбран, показываем сообщение
        const historyBlock = document.getElementById('sellPurchaseHistory');
        if (historyBlock) {
            historyBlock.style.display = 'block';
            document.getElementById('sellPurchaseList').innerHTML = 
                '<div style="text-align: center; padding: 20px; color: #ff9f4a;"><i class="fas fa-info-circle"></i> Сначала выберите актив</div>';
            document.getElementById('sellQuickActions').style.display = 'none';
        }
    }
}

function selectTradeFromPlatform(id, name) {
    selectedTradeFromPlatform = { id, name };
    window.currentPlatformMode = 'trade_from';
    
    const display = document.getElementById('selectedTradeFromPlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('tradeFromPlatformId');
    if (hiddenInput) hiddenInput.value = id;
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА АКТИВА (СТАНДАРТНЫЕ)
// ============================================================================

function selectAsset(id, symbol) {
    selectedTransferAsset = { id, symbol };
    
    const display = document.getElementById('selectedAssetDisplay');
    if (display) {
        display.textContent = symbol;
    }
    
    const hiddenInput = document.getElementById('transferAssetId');
    if (hiddenInput) {
        hiddenInput.value = id;
    }
    
    const asset = assetsData.find(a => a.id == id);
    const cryptoSection = document.getElementById('transferCryptoNetworkSection');
    if (cryptoSection) {
        cryptoSection.style.display = asset && asset.type === 'crypto' ? 'block' : 'none';
    }
}

function selectTradeAsset(id, symbol, type) {
    selectedTradeAsset = { id, symbol, type };
    
    const display = document.getElementById('selectedTradeAssetDisplay');
    if (display) display.textContent = symbol;
    
    const hiddenInput = document.getElementById('tradeAssetId');
    if (hiddenInput) hiddenInput.value = id;
    
    const typeInput = document.getElementById('tradeAssetType');
    if (typeInput) typeInput.value = type || 'other';
    
    // Пересчитываем итог
    calculateTradeTotal();
    
    const cryptoSection = document.getElementById('tradeCryptoNetworkSection');
    if (cryptoSection) {
        cryptoSection.style.display = type === 'crypto' ? 'block' : 'none';
    }
    
    // НОВЫЙ КОД: Если это продажа, загружаем историю покупок
    const operationType = document.getElementById('tradeOperationType').value;
    const platformId = document.getElementById('tradePlatformId').value;
    
    if (operationType === 'sell' && platformId) {
        loadPurchaseHistoryForSell(id, platformId);
    } else if (operationType === 'sell') {
        // Если площадка еще не выбрана, показываем сообщение
        const historyBlock = document.getElementById('sellPurchaseHistory');
        if (historyBlock) {
            historyBlock.style.display = 'block';
            document.getElementById('sellPurchaseList').innerHTML = 
                '<div style="text-align: center; padding: 20px; color: #ff9f4a;"><i class="fas fa-info-circle"></i> Сначала выберите площадку</div>';
            document.getElementById('sellQuickActions').style.display = 'none';
        }
    } else {
        // Если это покупка, скрываем блок истории
        const historyBlock = document.getElementById('sellPurchaseHistory');
        if (historyBlock) historyBlock.style.display = 'none';
    }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ВАЛЮТЫ ЦЕНЫ И КОМИССИИ
// ============================================================================

function selectTradePriceCurrency(code) {
    selectedTradePriceCurrency = { code };
    
    const display = document.getElementById('selectedTradePriceCurrencyDisplay');
    if (display) display.textContent = code;
    
    const hiddenInput = document.getElementById('tradePriceCurrency');
    if (hiddenInput) hiddenInput.value = code;
    
    // Копируем в комиссию, если там ничего не выбрано
    const commissionDisplay = document.getElementById('selectedTradeCommissionCurrencyDisplay');
    const commissionHidden = document.getElementById('tradeCommissionCurrency');
    
    if (commissionDisplay && commissionHidden && !commissionHidden.value) {
        commissionDisplay.textContent = code;
        commissionHidden.value = code;
        selectedTradeCommissionCurrency = { code };
    }
    
    // Пересчитываем итог
    calculateTradeTotal();
}

function selectTradeCommissionCurrency(code) {
    selectedTradeCommissionCurrency = { code };
    
    const display = document.getElementById('selectedTradeCommissionCurrencyDisplay');
    if (display) display.textContent = code;
    
    const hiddenInput = document.getElementById('tradeCommissionCurrency');
    if (hiddenInput) hiddenInput.value = code;
    
    calculateTradeTotal();
}

function selectCommissionCurrency(code) {
    selectedCommissionCurrency = { code };
    
    const display = document.getElementById('selectedCommissionCurrencyDisplay');
    if (display) display.textContent = code;
    
    const hiddenInput = document.getElementById('transferCommissionCurrency');
    if (hiddenInput) hiddenInput.value = code;
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ПЛОЩАДКИ ОТПРАВИТЕЛЯ/ПОЛУЧАТЕЛЯ
// ============================================================================

function selectFromPlatform(id, name) {
    selectedFromPlatform = { id, name };
    window.currentPlatformMode = 'from';
    
    const display = document.getElementById('selectedFromPlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('transferFromPlatformId');
    if (hiddenInput) hiddenInput.value = id;
    
    // НОВЫЙ КОД: Загружаем баланс выбранной площадки
    loadPlatformBalance(id, name);
}

function selectToPlatform(id, name) {
    selectedToPlatform = { id, name };
    window.currentPlatformMode = 'to'; // Добавляем для отслеживания
    
    const display = document.getElementById('selectedToPlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('transferToPlatformId');
    if (hiddenInput) hiddenInput.value = id;
}

// ============================================================================
// ФУНКЦИИ РАСЧЕТА
// ============================================================================

function calculateTradeTotal() {
    const quantity = getNumericValue(document.getElementById('tradeQuantity').value);
    const price = getNumericValue(document.getElementById('tradePrice').value);
    const commission = getNumericValue(document.getElementById('tradeCommission').value);
    
    // Получаем валюту оплаты (цену)
    const paymentCurrency = document.getElementById('tradePriceCurrency').value;
    
    // Рассчитываем общую сумму
    const total = quantity * price + commission;
    
    // Форматируем сумму
    let formattedTotal = '0';
    if (!isNaN(total) && isFinite(total) && total > 0) {
        // Для RUB и USD - 2 знака, для крипто - 6 знаков
        let decimals = 2;
        if (paymentCurrency === 'BTC' || paymentCurrency === 'ETH') {
            decimals = 6;
        }
        
        formattedTotal = total.toFixed(decimals);
        // Добавляем пробелы между разрядами в целой части
        let parts = formattedTotal.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        formattedTotal = parts.join('.');
    }
    
    // Формируем итоговую строку: "сумма валюта"
    const totalField = document.getElementById('tradeTotal');
    if (paymentCurrency && paymentCurrency !== '') {
        totalField.value = `${formattedTotal} ${paymentCurrency}`;
    } else {
        totalField.value = formattedTotal;
    }
}

function updateTradeTotalCurrency() {
    const currency = document.getElementById('tradePriceCurrency').value;
    const totalCurrencySpan = document.getElementById('tradeTotalCurrency');
    if (totalCurrencySpan) {
        totalCurrencySpan.textContent = currency || '—';
    }
}

// ============================================================================
// ФУНКЦИИ МОДАЛЬНЫХ ОКОН
// ============================================================================

function openDepositModal() {
    depositModal.classList.add('active');
    document.getElementById('depositDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('depositAmount').value = '';
    document.getElementById('depositCurrency').value = '';
    document.getElementById('selectedCurrencyDisplay').textContent = 'Выбрать';
    document.getElementById('depositPlatformId').value = '';
    document.getElementById('selectedPlatformDisplay').textContent = 'Выбрать площадку';
    
    setModalContext('deposit', null);
}

function closeDepositModal() {
    depositModal.classList.remove('active');
}

function openBuyModal() {
    openTradeModal('buy');
}

function openSellModal() {
    openTradeModal('sell');
}

function openTradeModal(type) {
    tradeOperationType.value = type;
    
    if (type === 'buy') {
        document.getElementById('tradeModalTitle').innerHTML = '<i class="fas fa-arrow-down" style="color: #00a86b;"></i> Покупка';
        document.getElementById('confirmTradeBtn').style.background = '#00a86b';
        confirmTradeBtnText.textContent = 'Купить';
        document.getElementById('tradeFromPlatformGroup').style.display = 'block';
        setModalContext('buy', null);
        
        // Скрываем блок истории покупок при покупке
        const historyBlock = document.getElementById('sellPurchaseHistory');
        if (historyBlock) historyBlock.style.display = 'none';
        
    } else {
        document.getElementById('tradeModalTitle').innerHTML = '<i class="fas fa-arrow-up" style="color: #e53e3e;"></i> Продажа';
        document.getElementById('confirmTradeBtn').style.background = '#e53e3e';
        confirmTradeBtnText.textContent = 'Продать';
        document.getElementById('tradeFromPlatformGroup').style.display = 'none';
        setModalContext('sell', null);
        
        // Показываем блок истории покупок (будет заполнен позже)
        const historyBlock = document.getElementById('sellPurchaseHistory');
        if (historyBlock) {
            historyBlock.style.display = 'block';
            document.getElementById('sellPurchaseList').innerHTML = 
                '<div style="text-align: center; padding: 20px; color: #ff9f4a;"><i class="fas fa-info-circle"></i> Выберите площадку и актив</div>';
            document.getElementById('sellQuickActions').style.display = 'none';
            document.getElementById('sellCurrentBalance').innerHTML = '';
        }
    }

    tradeModal.classList.add('active');
    
    document.getElementById('tradeDate').value = new Date().toISOString().split('T')[0];
    
    // Сбрасываем выбранную сеть
    selectedTradeNetwork = { name: '' };
    document.getElementById('selectedTradeNetworkDisplay').textContent = 'Выбрать сеть';
    document.getElementById('tradeNetwork').value = '';

    selectedTradePlatform = { id: null, name: '' };
    document.getElementById('selectedTradePlatformDisplay').textContent = 'Выбрать площадку';
    document.getElementById('tradePlatformId').value = '';
    
    selectedTradeFromPlatform = { id: null, name: '' };
    document.getElementById('selectedTradeFromPlatformDisplay').textContent = 'Выбрать площадку';
    document.getElementById('tradeFromPlatformId').value = '';
    
    selectedTradeAsset = { id: null, symbol: '', type: '' };
    document.getElementById('selectedTradeAssetDisplay').textContent = 'Выбрать';
    document.getElementById('tradeAssetId').value = '';
    document.getElementById('tradeAssetType').value = '';
    
    document.getElementById('tradeQuantity').value = '';
    document.getElementById('tradePrice').value = '';
    
    selectedTradePriceCurrency = { code: '' };
    document.getElementById('selectedTradePriceCurrencyDisplay').textContent = 'Выбрать';
    document.getElementById('tradePriceCurrency').value = '';
    
    document.getElementById('tradeCommission').value = '';
    selectedTradeCommissionCurrency = { code: '' };
    document.getElementById('selectedTradeCommissionCurrencyDisplay').textContent = 'Выбрать';
    document.getElementById('tradeCommissionCurrency').value = '';
    
    document.getElementById('tradeNetwork').value = '';
    document.getElementById('tradeNotes').value = '';
    
    // УБИРАЕМ ССЫЛКУ НА tradeTotalCurrency
    document.getElementById('tradeTotal').value = '0';
    
    document.getElementById('tradeCryptoNetworkSection').style.display = 'none';
    
    // Сбрасываем данные о продаже
    window.sellAssetData = null;
}

function closeTradeModal() {
    tradeModal.classList.remove('active');
}

function openTransferModal() {
    transferModal.classList.add('active');
    document.getElementById('transferDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('transferAmount').value = '';
    document.getElementById('transferCommission').value = '';
    document.getElementById('selectedAssetDisplay').textContent = 'Выбрать';
    document.getElementById('selectedFromPlatformDisplay').textContent = 'Выбрать площадку';
    document.getElementById('selectedToPlatformDisplay').textContent = 'Выбрать площадку';
    document.getElementById('selectedCommissionCurrencyDisplay').textContent = 'Выбрать';
    document.getElementById('transferAssetId').value = '';
    document.getElementById('transferFromPlatformId').value = '';
    document.getElementById('transferToPlatformId').value = '';
    document.getElementById('transferCommissionCurrency').value = '';
    document.getElementById('transferNetworkFrom').value = '';
    document.getElementById('transferNetworkTo').value = '';
    document.getElementById('transferNotes').value = '';
    
    document.getElementById('transferCryptoNetworkSection').style.display = 'none';
    
    // Скрываем блок баланса при открытии
    hidePlatformBalance();
    
    // Сбрасываем сохраненные данные
    currentPlatformBalanceData = null;
    
    setModalContext('transfer', null);
}

function closeTransferModal() {
    transferModal.classList.remove('active');
}

// ============================================================================
// ФУНКЦИИ ПОДТВЕРЖДЕНИЯ ОПЕРАЦИЙ
// ============================================================================

async function confirmDeposit() {
    const platformId = document.getElementById('depositPlatformId').value;
    const amount = getNumericValue(document.getElementById('depositAmount').value);
    const currency = document.getElementById('depositCurrency').value.toUpperCase();
    const date = document.getElementById('depositDate').value;

    if (!platformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку');
        return;
    }

    if (!amount || amount <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректную сумму');
        return;
    }

    if (!currency) {
        showNotification('error', 'Ошибка', 'Выберите валюту');
        return;
    }

    if (!date) {
        showNotification('error', 'Ошибка', 'Выберите дату пополнения');
        return;
    }

    //showNotification('info', 'Сохранение', 'Добавляем пополнение...');

    const formData = new FormData();
    formData.append('action', 'add_deposit');
    formData.append('platform_id', platformId);
    formData.append('amount', amount);
    formData.append('currency', currency);
    formData.append('deposit_date', date);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeDepositModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос');
    }
}

async function confirmTrade() {
    const operationType = document.getElementById('tradeOperationType').value;
    const platformId = document.getElementById('tradePlatformId').value;
    const fromPlatformId = operationType === 'buy' ? document.getElementById('tradeFromPlatformId').value : platformId;
    const assetId = document.getElementById('tradeAssetId').value;
    const quantity = getNumericValue(document.getElementById('tradeQuantity').value);
    const price = getNumericValue(document.getElementById('tradePrice').value);
    const priceCurrency = document.getElementById('tradePriceCurrency').value.toUpperCase();
    const commission = getNumericValue(document.getElementById('tradeCommission').value) || 0;
    const commissionCurrency = document.getElementById('tradeCommissionCurrency').value.toUpperCase() || '';
    const network = document.getElementById('tradeNetwork').value || '';
    const date = document.getElementById('tradeDate').value;
    const notes = document.getElementById('tradeNotes').value;

    // Проверка: выбранная площадка
    if (!platformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку для покупки/продажи');
        return;
    }

    // Проверка: выбранный актив
    if (!assetId) {
        showNotification('error', 'Ошибка', 'Выберите актив для покупки/продажи');
        return;
    }

    // Проверка: количество
    if (!quantity || quantity <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректное количество (должно быть больше 0)');
        return;
    }

    // Проверка: цена
    if (!price || price <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректную цену (должна быть больше 0)');
        return;
    }

    // Проверка: валюта цены
    if (!priceCurrency) {
        showNotification('error', 'Ошибка', 'Выберите валюту цены');
        return;
    }

    // Проверка: для покупки нужна площадка списания
    if (operationType === 'buy') {
        if (!fromPlatformId) {
            showNotification('error', 'Ошибка', 'Выберите площадку, с которой будут списаны средства');
            return;
        }
    }

    // Проверка: комиссия
    if (commission > 0 && !commissionCurrency) {
        showNotification('error', 'Ошибка', 'Если указана комиссия, выберите валюту комиссии');
        return;
    }

    // Показываем индикатор загрузки
    const confirmBtn = document.getElementById('confirmTradeBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
    confirmBtn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'add_trade');
    formData.append('operation_type', operationType);
    formData.append('platform_id', platformId);
    formData.append('from_platform_id', fromPlatformId);
    formData.append('asset_id', assetId);
    formData.append('quantity', quantity);
    formData.append('price', price);
    formData.append('price_currency', priceCurrency);
    formData.append('commission', commission);
    formData.append('commission_currency', commissionCurrency);
    formData.append('network', network);
    formData.append('operation_date', date);
    formData.append('notes', notes);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeTradeModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            // Показываем подробную ошибку
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос. Проверьте подключение к интернету.');
    } finally {
        // Восстанавливаем кнопку
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    }
}

async function confirmTransfer() {
    const fromPlatformId = document.getElementById('transferFromPlatformId').value;
    const toPlatformId = document.getElementById('transferToPlatformId').value;
    const assetId = document.getElementById('transferAssetId').value;
    const quantity = getNumericValue(document.getElementById('transferAmount').value);
    const commission = getNumericValue(document.getElementById('transferCommission').value) || 0;
    const commissionCurrency = document.getElementById('transferCommissionCurrency').value.toUpperCase() || '';
    const fromNetwork = document.getElementById('transferNetworkFrom')?.value || '';
    const toNetwork = document.getElementById('transferNetworkTo')?.value || '';
    const date = document.getElementById('transferDate').value;
    const notes = document.getElementById('transferNotes').value;

    // Проверки
    if (!fromPlatformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку отправителя');
        return;
    }

    if (!toPlatformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку получателя');
        return;
    }

    if (!assetId) {
        showNotification('error', 'Ошибка', 'Выберите актив');
        return;
    }

    if (!quantity || quantity <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректное количество');
        return;
    }

    if (commission > 0 && !commissionCurrency) {
        showNotification('error', 'Ошибка', 'Выберите валюту комиссии');
        return;
    }

    const asset = assetsData.find(a => a.id == assetId);
    const assetType = asset ? asset.type : null;
    
    // Для криптовалют проверяем сети
    if (assetType === 'crypto') {
        if (!fromNetwork) {
            showNotification('error', 'Ошибка', 'Укажите сеть отправителя');
            return;
        }
        if (!toNetwork) {
            showNotification('error', 'Ошибка', 'Укажите сеть получателя');
            return;
        }
    }

    // Показываем индикатор загрузки
    const confirmBtn = document.getElementById('confirmTransferBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
    confirmBtn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'add_transfer');
    formData.append('from_platform_id', fromPlatformId);
    formData.append('to_platform_id', toPlatformId);
    formData.append('asset_id', assetId);
    formData.append('quantity', quantity);
    formData.append('commission', commission);
    formData.append('commission_currency', commissionCurrency);
    formData.append('from_network', fromNetwork);
    formData.append('to_network', toNetwork);
    formData.append('transfer_date', date);
    formData.append('notes', notes);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        // Получаем текст ответа для отладки
        const responseText = await response.text();
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            showNotification('error', 'Ошибка сервера', 'Сервер вернул некорректный ответ: ' + responseText.substring(0, 200));
            return;
        }
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeTransferModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            // Показываем подробную ошибку
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос: ' + error.message);
    } finally {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    }
}

// ============================================================================
// ФУНКЦИИ УВЕДОМЛЕНИЙ
// ============================================================================

function showNotification(type, title, message, duration = 5000) {
    let container = document.getElementById('notificationContainer');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        container.id = 'notificationContainer';
        document.body.appendChild(container);
    }
    
    const notificationId = 'notification-' + Date.now();
    
    let iconClass = '';
    switch(type) {
        case 'success': iconClass = 'fas fa-check-circle'; break;
        case 'warning': iconClass = 'fas fa-exclamation-triangle'; break;
        case 'error': iconClass = 'fas fa-times-circle'; break;
        case 'info': iconClass = 'fas fa-info-circle'; break;
        default: iconClass = 'fas fa-info-circle';
    }
    
    const notification = document.createElement('div');
    notification.id = notificationId;
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-icon"><i class="${iconClass}"></i></div>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close" onclick="closeNotification('${notificationId}')">×</button>
        <div class="notification-progress">
            <div class="notification-progress-bar" id="progress-${notificationId}" style="width: 100%; height: 2px;"></div>
        </div>
    `;
    
    container.appendChild(notification);
    
    const progressBar = document.getElementById(`progress-${notificationId}`);
    const startTime = Date.now();
    
    function updateProgress() {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, duration - elapsed);
        const width = (remaining / duration) * 100;
        
        if (progressBar) {
            progressBar.style.width = width + '%';
        }
        
        if (remaining > 0) {
            requestAnimationFrame(updateProgress);
        } else {
            closeNotification(notificationId);
        }
    }
    
    requestAnimationFrame(updateProgress);
    
    notification.dataset.timeout = setTimeout(() => {
        closeNotification(notificationId);
    }, duration);
}

function closeNotification(notificationId) {
    const notification = document.getElementById(notificationId);
    if (!notification) return;
    
    if (notification.dataset.timeout) {
        clearTimeout(parseInt(notification.dataset.timeout));
    }
    
    notification.classList.add('fade-out');
    
    setTimeout(() => {
        if (notification && notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ДОБАВЛЕНИЯ НОВЫХ ЭЛЕМЕНТОВ
// ============================================================================

let pendingCurrencyCode = '';
let pendingCurrencyMode = 'default';
let pendingPlatformName = '';

async function addNewPlatformAndSelect(platformName, context = 'default') {
    if (!platformName) return;
    
    const newName = platformName.trim();
    
    const exists = platformsData.some(p => p.name.toLowerCase() === newName.toLowerCase());
    
    if (exists) {
        const platform = platformsData.find(p => p.name.toLowerCase() === newName.toLowerCase());
        
        if (context === 'transfer') {
            if (currentModalContext.subMode === 'from') {
                selectFromPlatform(platform.id, platform.name);
            } else if (currentModalContext.subMode === 'to') {
                selectToPlatform(platform.id, platform.name);
            }
        } else if (context === 'buy' && currentModalContext.subMode === 'from') {
            selectTradeFromPlatform(platform.id, platform.name);
        } else if (context === 'buy' || context === 'sell') {
            selectTradePlatform(platform.id, platform.name);
        } else if (context === 'deposit') {
            selectPlatform(platform.id, platform.name);
        }
        
        closePlatformModal();
        return;
    }
    
    openAddPlatformModal(newName, context);
}

function openAddPlatformModal(platformName, context = 'default') {
    setModalContext(context, 'addPlatform');
    const scheme = getColorScheme();
    const addScheme = scheme.addPlatform || modalColorSchemes.default.addPlatform;
    
    pendingPlatformName = platformName;
    
    const modalTitle = document.querySelector('#addPlatformModal .modal-header h2');
    modalTitle.innerHTML = `<i class="fas fa-plus-circle" style="color: ${addScheme.headerIcon};"></i> Добавление площадки`;
    
    const confirmBtn = document.getElementById('confirmAddPlatformBtn');
    if (confirmBtn) {
        confirmBtn.style.background = addScheme.confirmButton;
    }
    
    const nameInput = document.getElementById('newPlatformName');
    const countryInput = document.getElementById('newPlatformCountry');
    const typeHidden = document.getElementById('newPlatformType');
    
    if (!nameInput) {
        showNotification('error', 'Ошибка', 'Не найден элемент формы');
        return;
    }
    
    nameInput.value = platformName;
    if (countryInput) countryInput.value = '';
    
    document.querySelectorAll('.platform-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (typeHidden) typeHidden.value = '';
    
    const parentModal = document.getElementById('platformSelectModal');
    if (parentModal && parentModal.classList.contains('active')) {
        parentModal.classList.remove('active');
    }
    
    const modal = document.getElementById('addPlatformModal');
    if (modal) {
        modal.classList.add('active');
        
        setTimeout(() => {
            if (countryInput) countryInput.focus();
        }, 100);
    }
}

function closeAddPlatformModal() {
    const modal = document.getElementById('addPlatformModal');
    if (modal) {
        modal.classList.remove('active');
        pendingPlatformName = '';
    }
}

function setActivePlatformType(type) {
    document.querySelectorAll('.platform-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const selectedBtn = document.querySelector(`.platform-type-btn[data-type="${type}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    const hiddenInput = document.getElementById('newPlatformType');
    if (hiddenInput) {
        hiddenInput.value = type;
    }
}

function getSelectedPlatformType() {
    const hiddenInput = document.getElementById('newPlatformType');
    return hiddenInput ? hiddenInput.value : '';
}

async function saveNewPlatform() {
    const name = document.getElementById('newPlatformName').value;
    const type = getSelectedPlatformType();
    const country = document.getElementById('newPlatformCountry').value;

    if (!name.trim()) {
        showNotification('error', 'Ошибка', 'Название площадки обязательно');
        return;
    }

    if (!type) {
        showNotification('error', 'Ошибка', 'Выберите тип площадки');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_platform_full');
    formData.append('name', name);
    formData.append('type', type);
    formData.append('country', country);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();

        if (result.success && result.platform_id) {
            showNotification('success', 'Успешно', 'Площадка добавлена');
            
            // Добавляем в массив
            platformsData.push({
                id: result.platform_id,
                name: name,
                type: type
            });
            
            // ОБНОВЛЯЕМ ВСЕ СПИСКИ ПЛОЩАДОК
            refreshAllPlatformLists();
            
            // ДОПОЛНИТЕЛЬНО: обновляем конкретные элементы для transferModal
            // Обновляем популярные площадки для отправителя и получателя
            updateTransferModalPlatforms();
            
            // Определяем, в каком режиме мы добавляли площадку
            const context = currentModalContext.source;
            const subMode = currentModalContext.subMode;
            
            // Выбираем добавленную площадку в зависимости от контекста
            if (context === 'transfer') {
                if (subMode === 'from') {
                    selectFromPlatform(result.platform_id, name);
                } else if (subMode === 'to') {
                    selectToPlatform(result.platform_id, name);
                }
            } else if (context === 'buy' && subMode === 'from') {
                selectTradeFromPlatform(result.platform_id, name);
            } else if (context === 'buy' || context === 'sell') {
                selectTradePlatform(result.platform_id, name);
            } else if (context === 'deposit') {
                selectPlatform(result.platform_id, name);
            }
            
            closeAddPlatformModal();
            
            // Если модальное окно выбора площадки было открыто, закрываем его
            const platformSelectModal = document.getElementById('platformSelectModal');
            if (platformSelectModal && platformSelectModal.classList.contains('active')) {
                closePlatformModal();
            }
        } else {
            showNotification('error', 'Ошибка', result.message || 'Не удалось добавить площадку');
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось добавить площадку');
    }
}

function refreshAllPlatformLists() {
    // Обновляем список в модальном окне выбора площадки (если оно открыто)
    const platformModal = document.getElementById('platformSelectModal');
    if (platformModal && platformModal.classList.contains('active')) {
        const searchInput = document.getElementById('platformSearch');
        if (searchInput) {
            filterPlatforms(searchInput.value);
        } else {
            filterPlatforms('');
        }
    }
    
    // Обновляем популярные площадки во всех модальных окнах
    const popularPlatformsContainers = [
        'depositPopularPlatforms',
        'tradePopularPlatforms',
        'tradeFromPopularPlatforms',
        'transferFromPopularPlatforms',
        'transferToPopularPlatforms',
        'limitPopularPlatforms'
    ];
    
    popularPlatformsContainers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container) {
            const popularPlatforms = platformsData.slice(0, 5);
            let onclickHandler = '';
            
            // Определяем правильный обработчик для каждого контейнера
            if (containerId === 'transferFromPopularPlatforms') {
                onclickHandler = 'selectFromPlatform';
            } else if (containerId === 'transferToPopularPlatforms') {
                onclickHandler = 'selectToPlatform';
            } else if (containerId === 'tradeFromPopularPlatforms') {
                onclickHandler = 'selectTradeFromPlatform';
            } else if (containerId === 'tradePopularPlatforms') {
                onclickHandler = 'selectTradePlatform';
            } else if (containerId === 'depositPopularPlatforms') {
                onclickHandler = 'selectPlatform';
            } else if (containerId === 'limitPopularPlatforms') {
                onclickHandler = 'selectLimitPlatform';
            } else {
                onclickHandler = 'selectPlatformFromList';
            }
            
            container.innerHTML = popularPlatforms.map(platform => `
                <button type="button" class="quick-platform-btn" onclick="${onclickHandler}('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')">
                    ${platform.name}
                </button>
            `).join('');
        }
    });
    
    // Обновляем скрытые списки площадок в модальных окнах
    const platformsLists = [
        'depositPlatformsList',
        'transferFromPlatformsList',
        'transferToPlatformsList'
    ];
    
    platformsLists.forEach(listId => {
        const list = document.getElementById(listId);
        if (list && list.style.display !== 'none') {
            const scheme = getColorScheme();
            const platformScheme = scheme.platform || modalColorSchemes.default.platform;
            
            let onclickHandler = '';
            if (listId === 'transferFromPlatformsList') {
                onclickHandler = 'selectFromPlatform';
            } else if (listId === 'transferToPlatformsList') {
                onclickHandler = 'selectToPlatform';
            } else {
                onclickHandler = 'selectPlatform';
            }
            
            list.innerHTML = platformsData.map(platform => `
                <div onclick="${onclickHandler}('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" 
                     style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span>
                </div>
            `).join('');
        }
    });
    
    // Дополнительно обновляем для transferModal
    updateTransferModalPlatforms();
}

function updateTransferModalPlatforms() {
    // Обновляем популярные площадки для отправителя
    const fromContainer = document.getElementById('transferFromPopularPlatforms');
    if (fromContainer) {
        const popularPlatforms = platformsData.slice(0, 5);
        fromContainer.innerHTML = popularPlatforms.map(platform => `
            <button type="button" class="quick-platform-btn" onclick="selectFromPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')">
                ${platform.name}
            </button>
        `).join('');
    }
    
    // Обновляем популярные площадки для получателя
    const toContainer = document.getElementById('transferToPopularPlatforms');
    if (toContainer) {
        const popularPlatforms = platformsData.slice(0, 5);
        toContainer.innerHTML = popularPlatforms.map(platform => `
            <button type="button" class="quick-platform-btn" onclick="selectToPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')">
                ${platform.name}
            </button>
        `).join('');
    }
    
    // Обновляем скрытые списки площадок
    const fromList = document.getElementById('transferFromPlatformsList');
    if (fromList && fromList.style.display !== 'none') {
        const scheme = getColorScheme();
        const platformScheme = scheme.platform || modalColorSchemes.default.platform;
        fromList.innerHTML = platformsData.map(platform => `
            <div onclick="selectFromPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" 
                 style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px;">
                <span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span>
            </div>
        `).join('');
    }
    
    const toList = document.getElementById('transferToPlatformsList');
    if (toList && toList.style.display !== 'none') {
        const scheme = getColorScheme();
        const platformScheme = scheme.platform || modalColorSchemes.default.platform;
        toList.innerHTML = platformsData.map(platform => `
            <div onclick="selectToPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" 
                 style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px;">
                <span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span>
            </div>
        `).join('');
    }
}

function addNewCurrencyAndSelect(currencyCode, context = 'default', mode = 'default') {
    if (!currencyCode) return;
    
    const newCode = currencyCode.trim().toUpperCase();
    
    const exists = allCurrencies.some(c => c.code.toUpperCase() === newCode);
    
    if (exists) {
        const currency = allCurrencies.find(c => c.code.toUpperCase() === newCode);
        
        if (context === 'transfer' && mode === 'commission') {
            selectCommissionCurrency(currency.code);
        } else if ((context === 'buy' || context === 'sell') && mode === 'price') {
            selectTradePriceCurrency(currency.code);
        } else if ((context === 'buy' || context === 'sell') && mode === 'commission') {
            selectTradeCommissionCurrency(currency.code);
        } else if (context === 'deposit') {
            selectCurrency(currency.code, currency.name);
        }
        
        closeCurrencyModal();
        return;
    }
    
    openAddCurrencyModal(newCode, context, mode);
}

function openAddCurrencyModal(currencyCode, context = 'default', mode = 'default') {
    setModalContext(context, 'addCurrency', mode);
    const scheme = getColorScheme();
    const addScheme = scheme.addCurrency || modalColorSchemes.default.addCurrency;
    
    pendingCurrencyCode = currencyCode;
    pendingCurrencyMode = mode;
    
    const modalTitle = document.querySelector('#addCurrencyModal .modal-header h2');
    modalTitle.innerHTML = `<i class="fas fa-plus-circle" style="color: ${addScheme.headerIcon};"></i> Добавление валюты`;
    
    const confirmBtn = document.getElementById('confirmAddCurrencyBtn');
    if (confirmBtn) {
        confirmBtn.style.background = addScheme.confirmButton;
    }
    
    const codeInput = document.getElementById('newCurrencyCode');
    const nameInput = document.getElementById('newCurrencyName');
    const symbolInput = document.getElementById('newCurrencySymbol');
    const typeHidden = document.getElementById('newCurrencyType');
    
    if (!codeInput) {
        showNotification('error', 'Ошибка', 'Не найден элемент формы');
        return;
    }
    
    codeInput.value = currencyCode.toUpperCase();
    if (nameInput) nameInput.value = '';
    if (symbolInput) symbolInput.value = '';
    
    document.querySelectorAll('.currency-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (typeHidden) typeHidden.value = '';
    
    const parentModal = document.getElementById('currencySelectModal');
    if (parentModal && parentModal.classList.contains('active')) {
        parentModal.classList.remove('active');
    }
    
    const modal = document.getElementById('addCurrencyModal');
    if (modal) {
        modal.classList.add('active');
        
        setTimeout(() => {
            if (nameInput) nameInput.focus();
        }, 100);
    }
}

function closeAddCurrencyModal() {
    const modal = document.getElementById('addCurrencyModal');
    if (modal) {
        modal.classList.remove('active');
        pendingCurrencyCode = '';
        pendingCurrencyMode = 'default';
    }
}

function setActiveCurrencyType(type) {
    document.querySelectorAll('.currency-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const selectedBtn = document.querySelector(`.currency-type-btn[data-type="${type}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    const hiddenInput = document.getElementById('newCurrencyType');
    if (hiddenInput) {
        hiddenInput.value = type;
    }
}

function getSelectedCurrencyType() {
    const hiddenInput = document.getElementById('newCurrencyType');
    return hiddenInput ? hiddenInput.value : '';
}

async function saveNewCurrency() {
    const code = document.getElementById('newCurrencyCode').value.toUpperCase();
    const name = document.getElementById('newCurrencyName').value.trim();
    const type = getSelectedCurrencyType();
    const symbol = document.getElementById('newCurrencySymbol').value.trim();

    if (!code) {
        showNotification('error', 'Ошибка', 'Код валюты обязателен');
        return;
    }

    if (!name) {
        showNotification('error', 'Ошибка', 'Название валюты обязательно');
        return;
    }

    if (!type) {
        showNotification('error', 'Ошибка', 'Выберите тип валюты');
        return;
    }

    //showNotification('info', 'Сохранение', 'Добавляем валюту...');

    const formData = new FormData();
    formData.append('action', 'add_currency_full');
    formData.append('code', code);
    formData.append('name', name);
    formData.append('type', type);
    formData.append('symbol', symbol);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();

        if (result.success) {
            showNotification('success', 'Успешно', 'Валюта добавлена');
            
            allCurrencies.push({
                code: code,
                name: name,
                symbol: symbol,
                type: type
            });
            
            if (pendingCurrencyMode === 'commission') {
                selectCommissionCurrency(code);
            } else if (pendingCurrencyMode === 'price') {
                selectTradePriceCurrency(code);
            } else if (pendingCurrencyMode === 'commission_trade') {
                selectTradeCommissionCurrency(code);
            } else {
                selectCurrency(code, name);
            }
            
            closeAddCurrencyModal();
        } else {
            showNotification('error', 'Ошибка', result.message || 'Не удалось добавить валюту');
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось добавить валюту');
    }
}

async function addNewAssetFromCurrencyModal(assetSymbol) {
    if (!assetSymbol) return;
    
    const newSymbol = assetSymbol.trim().toUpperCase();
    
    const exists = assetsData.some(a => a.symbol.toUpperCase() === newSymbol);
    
    if (exists) {
        const asset = assetsData.find(a => a.symbol.toUpperCase() === newSymbol);
        selectAssetFromModal(asset.id, asset.symbol, asset.type);
        return;
    }
    
    openAddAssetModal(newSymbol);
}

async function addNewTradeAsset(assetSymbol) {
    if (!assetSymbol) return;
    
    const newSymbol = assetSymbol.trim().toUpperCase();
    
    const exists = assetsData.some(a => a.symbol.toUpperCase() === newSymbol);
    
    if (exists) {
        const asset = assetsData.find(a => a.symbol.toUpperCase() === newSymbol);
        selectTradeAssetFromModal(asset.id, asset.symbol, asset.type);
        return;
    }
    
    openAddAssetModal(newSymbol);
}

function openAddAssetModal(assetSymbol) {
    const symbolInput = document.getElementById('newAssetSymbol');
    const nameInput = document.getElementById('newAssetName');
    const typeHidden = document.getElementById('newAssetType');
    
    if (!symbolInput) {
        showNotification('error', 'Ошибка', 'Не найден элемент формы');
        return;
    }
    
    symbolInput.value = assetSymbol;
    if (nameInput) nameInput.value = '';
    
    document.querySelectorAll('.asset-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (typeHidden) typeHidden.value = '';
    
    const parentModal = document.getElementById('currencySelectModal');
    if (parentModal) {
        parentModal.classList.remove('active');
    }
    
    const modal = document.getElementById('addAssetModal');
    if (modal) {
        modal.classList.add('active');
        
        setTimeout(() => {
            if (nameInput) nameInput.focus();
        }, 100);
    }
}

function closeAddAssetModal() {
    const modal = document.getElementById('addAssetModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function setActiveAssetType(type) {
    // Убираем активный класс у всех кнопок типа актива
    document.querySelectorAll('.asset-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Добавляем активный класс выбранной кнопке
    const selectedBtn = document.querySelector(`.asset-type-btn[data-type="${type}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    // Сохраняем выбранный тип
    const hiddenInput = document.getElementById('newAssetType');
    if (hiddenInput) {
        hiddenInput.value = type;
    }
    
    // ПОКАЗЫВАЕМ/СКРЫВАЕМ ВЫБОР СЕКТОРА (не скрываем родителя!)
    const sectorGroup = document.getElementById('sectorSelectGroup');
    if (sectorGroup) {
        if (type === 'stock' || type === 'etf') {
            sectorGroup.style.display = 'block';
        } else {
            sectorGroup.style.display = 'none';
            // Сбрасываем выбранный сектор
            document.querySelectorAll('.sector-option-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById('newAssetSector').value = '';
        }
    }
}

function getSelectedAssetType() {
    const hiddenInput = document.getElementById('newAssetType');
    return hiddenInput ? hiddenInput.value : '';
}

async function saveNewAsset() {
    const symbol = document.getElementById('newAssetSymbol').value.toUpperCase();
    const name = document.getElementById('newAssetName').value.trim();
    const type = getSelectedAssetType();
    const sector = document.getElementById('newAssetSector').value;
    
    // АВТОМАТИЧЕСКОЕ ОПРЕДЕЛЕНИЕ ВАЛЮТЫ
    let currencyCode = null;
    
    if (type === 'stock') {
        // Иностранные акции (с .US или известные тикеры)
        if (symbol.endsWith('.US') || 
            ['TSLA', 'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META', 'NVDA', 'NFLX'].includes(symbol)) {
            currencyCode = 'USD';
        } 
        // Российские акции
        else if (['SBER', 'GAZP', 'LKOH', 'YNDX', 'ROSN', 'VTBR', 'TATN', 'NLMK'].includes(symbol)) {
            currencyCode = 'RUB';
        }
        // По умолчанию для акций - USD
        else {
            currencyCode = 'USD';
        }
    }
    else if (type === 'crypto') {
        currencyCode = 'USD';
    }
    else if (type === 'currency') {
        currencyCode = symbol;
    }
    else if (type === 'etf') {
        currencyCode = 'USD';
    }

    // ПРОВЕРКА: для акций и ETF сектор обязателен
    if ((type === 'stock' || type === 'etf') && !sector) {
        showNotification('error', 'Ошибка', 'Выберите сектор для акции/ETF');
        return;
    }

    if (!symbol) {
        showNotification('error', 'Ошибка', 'Символ актива обязателен');
        return;
    }

    if (!name) {
        showNotification('error', 'Ошибка', 'Название актива обязательно');
        return;
    }

    if (!type) {
        showNotification('error', 'Ошибка', 'Выберите тип актива');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_asset_full');
    formData.append('symbol', symbol);
    formData.append('name', name);
    formData.append('type', type);
    formData.append('currency_code', currencyCode || '');
    formData.append('sector', sector || '');

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        // Получаем текст ответа для отладки
        const responseText = await response.text();
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            showNotification('error', 'Ошибка сервера', 'Сервер вернул некорректный ответ: ' + responseText.substring(0, 200));
            return;
        }

        if (result.success && result.asset_id) {
            showNotification('success', 'Успешно', 'Актив добавлен');
            
            assetsData.push({
                id: result.asset_id,
                symbol: symbol,
                name: name,
                type: type,
                currency_code: currencyCode,
                sector: sector
            });
            
            if (currentModalContext.source === 'transfer') {
                selectAsset(result.asset_id, symbol);
            } else {
                selectTradeAsset(result.asset_id, symbol, type);
            }
            
            closeAddAssetModal();
            closeCurrencyModal();
        } else {
            showNotification('error', 'Ошибка', result.message || 'Не удалось добавить актив');
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось добавить актив: ' + error.message);
    }
}

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ И ОБРАБОТЧИКИ СОБЫТИЙ
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Обработчики для категорий расходов
    document.getElementById('closeAddCategoryModalBtn')?.addEventListener('click', closeAddExpenseCategoryModal);
    document.getElementById('cancelAddCategoryBtn')?.addEventListener('click', closeAddExpenseCategoryModal);
    document.getElementById('confirmAddCategoryBtn')?.addEventListener('click', saveExpenseCategory);

    // Обновление превью при изменении
    document.getElementById('newCategoryIcon')?.addEventListener('input', updateCategoryPreview);
    document.getElementById('newCategoryColor')?.addEventListener('input', updateCategoryPreview);

    // Выбор иконки
    document.getElementById('selectIconBtn')?.addEventListener('click', openIconSelectModal);
    document.getElementById('closeIconModalBtn')?.addEventListener('click', closeIconSelectModal);
    document.getElementById('closeIconModalFooterBtn')?.addEventListener('click', closeIconSelectModal);

    // Обработчики для выбора иконок
    document.querySelectorAll('.icon-option').forEach(icon => {
        icon.addEventListener('click', function() {
            selectIcon(this.dataset.icon);
        });
    });

    // Поиск иконок
    document.getElementById('iconSearch')?.addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.icon-option').forEach(icon => {
            const text = icon.textContent.toLowerCase();
            if (text.includes(search) || icon.dataset.icon.includes(search)) {
                icon.style.display = 'block';
            } else {
                icon.style.display = 'none';
            }
        });
    });

    // Кнопка расходов
    document.querySelectorAll('.operation-type-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.dataset.type;
            if (type === 'deposit') openDepositModal();
            else if (type === 'buy') openBuyModal();
            else if (type === 'sell') openSellModal();
            else if (type === 'transfer') openTransferModal();
            else if (type === 'expense') openExpenseModal(); // Добавляем обработчик расходов
        });
    });

    // Кнопка просмотра всех расходов (можно добавить в карточку или отдельную кнопку)
    document.getElementById('viewAllExpensesBtn')?.addEventListener('click', openExpensesListModal);

    // Обработчики для модального окна расходов
    document.getElementById('closeExpenseModalBtn')?.addEventListener('click', closeExpenseModal);
    document.getElementById('cancelExpenseBtn')?.addEventListener('click', closeExpenseModal);
    document.getElementById('confirmExpenseBtn')?.addEventListener('click', saveExpense);

    // Обработчики для списка расходов
    document.getElementById('closeExpensesListModalBtn')?.addEventListener('click', closeExpensesListModal);
    document.getElementById('closeExpensesListModalFooterBtn')?.addEventListener('click', closeExpensesListModal);
    document.getElementById('addNewExpenseBtn')?.addEventListener('click', function() {
        closeExpensesListModal();
        openExpenseModal();
    });

    // Выбор валюты для расходов
    document.getElementById('selectExpenseCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('expense', 'currency');
        openCurrencyModal('expense', 'price');
    });

    // Кнопка "Продать всё"
    document.getElementById('sellQuickFillAllBtn')?.addEventListener('click', fillSellAll);

    // Кнопка "По средней цене"
    document.getElementById('sellQuickFillAvgBtn')?.addEventListener('click', fillSellByAvgPrice);

    // Обработчики для модального окна типов криптовалют
    document.getElementById('closeCryptoTypeModalBtn')?.addEventListener('click', closeCryptoTypeModal);
    document.getElementById('closeCryptoTypeModalFooterBtn')?.addEventListener('click', closeCryptoTypeModal);

    // Закрытие по клику на overlay
    const cryptoTypeModal = document.getElementById('cryptoTypeModal');
    if (cryptoTypeModal) {
        cryptoTypeModal.addEventListener('click', (e) => {
            if (e.target === cryptoTypeModal) {
                closeCryptoTypeModal();
            }
        });
    }

    // Обработчики для модального окна активов сектора
    document.getElementById('closeSectorAssetsModalBtn')?.addEventListener('click', closeSectorAssetsModal);
    document.getElementById('closeSectorAssetsModalFooterBtn')?.addEventListener('click', closeSectorAssetsModal);

    // Закрытие по клику на overlay
    const sectorAssetsModal = document.getElementById('sectorAssetsModal');
    if (sectorAssetsModal) {
        sectorAssetsModal.addEventListener('click', (e) => {
            if (e.target === sectorAssetsModal) {
                closeSectorAssetsModal();
            }
        });
    }

    // Обработчики для кнопок выбора сектора (с классом sector-option-btn)
    document.querySelectorAll('.sector-option-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sector = this.dataset.sector;
            
            // Убираем активный класс у всех кнопок сектора
            document.querySelectorAll('.sector-option-btn').forEach(b => {
                b.classList.remove('active');
            });
            
            // Добавляем активный класс текущей кнопке
            this.classList.add('active');
            
            // Сохраняем выбранный сектор
            document.getElementById('newAssetSector').value = sector;
        });
    });

    // Обработчики для модального окна активов площадки
    document.getElementById('closePlatformAssetsModalBtn')?.addEventListener('click', closePlatformAssetsModal);
    document.getElementById('closePlatformAssetsModalFooterBtn')?.addEventListener('click', closePlatformAssetsModal);

    // Закрытие по клику на overlay
    const platformAssetsModal = document.getElementById('platformAssetsModal');
    if (platformAssetsModal) {
        platformAssetsModal.addEventListener('click', (e) => {
            if (e.target === platformAssetsModal) {
                closePlatformAssetsModal();
            }
        });
    }

    // Закрытие по ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (platformAssetsModal?.classList.contains('active')) {
                closePlatformAssetsModal();
            }
            if (cryptoTypeModal?.classList.contains('active')) {
                closeCryptoTypeModal();
            }
        }
    });

    // Кнопка выбора сети в модальном окне покупки/продажи
    document.getElementById('selectTradeNetworkBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openTradeNetworkModal();
    });

    // Рендерим популярные сети для торговли
    renderPopularNetworksForTrade();

    // Кнопки выбора сети в переводе
    document.getElementById('selectFromNetworkBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openNetworkModal('from');
    });

    document.getElementById('selectToNetworkBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openNetworkModal('to');
    });

    // Обработчики для модальных окон сетей
    document.getElementById('closeNetworkModalBtn')?.addEventListener('click', closeNetworkModal);
    document.getElementById('closeAddNetworkModalBtn')?.addEventListener('click', closeAddNetworkModal);
    document.getElementById('cancelAddNetworkBtn')?.addEventListener('click', closeAddNetworkModal);
    document.getElementById('confirmAddNetworkBtn')?.addEventListener('click', saveNewNetwork);

    // Закрытие по клику на overlay
    const networkModal = document.getElementById('networkSelectModal');
    if (networkModal) {
        networkModal.addEventListener('click', (e) => {
            if (e.target === networkModal) {
                closeNetworkModal();
            }
        });
    }

    const addNetworkModal = document.getElementById('addNetworkModal');
    if (addNetworkModal) {
        addNetworkModal.addEventListener('click', (e) => {
            if (e.target === addNetworkModal) {
                closeAddNetworkModal();
            }
        });
    }

    // Поиск сетей
    document.getElementById('networkSearch')?.addEventListener('input', function(e) {
        filterNetworksForSelect(e.target.value);
    });

    // Enter в поиске сетей
    document.getElementById('networkSearch')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const searchText = this.value.trim();
            if (searchText) {
                addNewNetworkFromModal(searchText);
            }
        }
    });

    // Рендерим популярные сети
    renderPopularNetworksForTransfer();

    // Закрытие по ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (document.getElementById('networkSelectModal')?.classList.contains('active')) {
                closeNetworkModal();
            }
            if (document.getElementById('addNetworkModal')?.classList.contains('active')) {
                closeAddNetworkModal();
            }
        }
    });

    // Обработчики для модальных окон заметок
    document.getElementById('closeNoteModalBtn')?.addEventListener('click', closeNoteModal);
    document.getElementById('cancelNoteBtn')?.addEventListener('click', closeNoteModal);
    document.getElementById('confirmNoteBtn')?.addEventListener('click', saveNote);

    document.getElementById('closeArchivedModalBtn')?.addEventListener('click', closeArchivedNotesModal);
    document.getElementById('closeArchivedModalFooterBtn')?.addEventListener('click', closeArchivedNotesModal);

    document.getElementById('closeConfirmDeleteBtn')?.addEventListener('click', closeConfirmDeleteModal);
    document.getElementById('cancelDeleteBtn')?.addEventListener('click', closeConfirmDeleteModal);
    document.getElementById('confirmDeleteBtn')?.addEventListener('click', confirmDeleteNote);

    // Загрузка заметок
    loadNotes();

    // Кнопка выбора площадки для лимитного ордера
    document.getElementById('selectLimitPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('limit', 'platform');
        openPlatformModal('limit', null);
    });

    // Кнопка выбора актива для лимитного ордера
    document.getElementById('selectLimitAssetBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openAssetModal('limit', 'asset');
    });

    // Кнопка выбора валюты для лимитного ордера
    document.getElementById('selectLimitCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('limit', 'currency');
        openCurrencyModal('limit', 'price');
    });

    // Переключение типа операции
    document.querySelectorAll('.limit-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.limit-type-btn').forEach(b => {
                b.style.opacity = '0.7';
                b.style.border = 'none';
            });
            this.style.opacity = '1';
            this.style.border = '2px solid white';
        });
    });

    // Расчет суммы при изменении полей
    document.getElementById('limitQuantity')?.addEventListener('input', updateLimitTotalEstimate);
    document.getElementById('limitPrice')?.addEventListener('input', updateLimitTotalEstimate);

    // Форматирование числовых полей
    const limitInputs = ['limitQuantity', 'limitPrice'];
    limitInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', function() { formatInput(this); });
            input.addEventListener('blur', function() { formatInput(this); });
        }
    });

    // Обработчики для модального окна исполнения
    document.getElementById('closeExecuteModalBtn')?.addEventListener('click', closeExecuteModal);
    document.getElementById('cancelExecuteBtn')?.addEventListener('click', closeExecuteModal);
    document.getElementById('confirmExecuteBtn')?.addEventListener('click', confirmExecuteOrder);

    // Обработчики для модального окна отмены
    document.getElementById('closeCancelModalBtn')?.addEventListener('click', closeCancelModal);
    document.getElementById('cancelCancelBtn')?.addEventListener('click', closeCancelModal);
    document.getElementById('confirmCancelBtn')?.addEventListener('click', confirmCancelOrder);

    // Закрытие по клику на overlay
    if (executeModal) {
        executeModal.addEventListener('click', (e) => {
            if (e.target === executeModal) closeExecuteModal();
        });
    }

    if (cancelModal) {
        cancelModal.addEventListener('click', (e) => {
            if (e.target === cancelModal) closeCancelModal();
        });
    }

    // Кнопка выбора площадки для торговли
    document.getElementById('selectTradePlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openPlatformModal(context, null);
    });

    // Кнопка выбора площадки списания
    document.getElementById('selectTradeFromPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('buy', 'from');
    });

    // ИСПРАВЛЕННАЯ КНОПКА выбора актива для торговли
    const tradeAssetBtn = document.getElementById('selectTradeAssetBtn');
    if (tradeAssetBtn) {
        tradeAssetBtn.removeAttribute('onclick');
        tradeAssetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const context = document.getElementById('tradeOperationType').value;
            openAssetModal(context, 'asset');
        });
    }

    // Кнопка выбора валюты цены
    document.getElementById('selectTradePriceCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openCurrencyModal(context, 'price');
    });

    // Кнопка выбора валюты комиссии
    document.getElementById('selectTradeCommissionCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openCurrencyModal(context, 'commission');
    });

    // Кнопка выбора валюты в пополнении
    document.getElementById('selectCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCurrencyModal('deposit', null);
    });

    // Кнопка выбора площадки в пополнении
    document.getElementById('selectPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('deposit', null);
    });

    // Кнопка выбора площадки отправителя
    document.getElementById('selectFromPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('transfer', 'from');
    });

    // Кнопка выбора площадки получателя
    document.getElementById('selectToPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('transfer', 'to');
    });

    // ИСПРАВЛЕННАЯ КНОПКА выбора актива в переводе
    const transferAssetBtn = document.getElementById('selectAssetBtn');
    if (transferAssetBtn) {
        transferAssetBtn.removeAttribute('onclick');
        transferAssetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openAssetModal('transfer', 'asset');
        });
    }

    // Кнопка выбора валюты комиссии в переводе
    document.getElementById('selectCommissionCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCurrencyModal('transfer', 'commission');
    });

    // Быстрые кнопки для выбора площадки в торговле
    document.querySelectorAll('#tradePopularPlatforms .quick-platform-btn, #tradeFromPopularPlatforms .quick-platform-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const idMatch = onclick.match(/'(\d+)'/);
                const nameMatch = onclick.match(/, '([^']+)'/);
                if (idMatch && nameMatch) {
                    if (this.closest('#tradeFromPopularPlatforms')) {
                        selectTradeFromPlatform(idMatch[1], nameMatch[1]);
                    } else {
                        selectTradePlatform(idMatch[1], nameMatch[1]);
                    }
                }
            }
        });
    });

    // Быстрые кнопки для выбора актива в торговле
    document.querySelectorAll('#tradePopularAssets .quick-asset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const idMatch = onclick.match(/'(\d+)'/);
                const symbolMatch = onclick.match(/, '([^']+)'/);
                const typeMatch = onclick.match(/, '([^']+)'\)$/);
                if (idMatch && symbolMatch) {
                    selectTradeAsset(idMatch[1], symbolMatch[1], typeMatch ? typeMatch[1] : 'other');
                }
            }
        });
    });

    // Быстрые кнопки для выбора валюты цены
    document.querySelectorAll('#tradePopularPriceCurrencies .quick-platform-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const codeMatch = onclick.match(/'([^']+)'/);
                if (codeMatch) {
                    selectTradePriceCurrency(codeMatch[1]);
                }
            }
        });
    });

    // Быстрые кнопки для выбора валюты комиссии
    document.querySelectorAll('#tradePopularCommissionCurrencies .quick-platform-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const codeMatch = onclick.match(/'([^']+)'/);
                if (codeMatch) {
                    selectTradeCommissionCurrency(codeMatch[1]);
                }
            }
        });
    });

    // Кнопки операций
    document.querySelectorAll('.operation-type-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.dataset.type;
            if (type === 'deposit') openDepositModal();
            else if (type === 'buy') openBuyModal();
            else if (type === 'sell') openSellModal();
            else if (type === 'transfer') openTransferModal();
        });
    });

    // Закрытие модальных окон
    document.getElementById('closeDepositModalBtn')?.addEventListener('click', closeDepositModal);
    document.getElementById('cancelDepositBtn')?.addEventListener('click', closeDepositModal);
    document.getElementById('closeTradeModalBtn')?.addEventListener('click', closeTradeModal);
    document.getElementById('cancelTradeBtn')?.addEventListener('click', closeTradeModal);
    document.getElementById('closeTransferModalBtn')?.addEventListener('click', closeTransferModal);
    document.getElementById('cancelTransferBtn')?.addEventListener('click', closeTransferModal);

    // Закрытие по клику на overlay
    [depositModal, tradeModal, transferModal].forEach(modal => {
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    if (modal === depositModal) closeDepositModal();
                    else if (modal === tradeModal) closeTradeModal();
                    else if (modal === transferModal) closeTransferModal();
                }
            });
        }
    });

    // Обработчики для модального окна добавления площадки
    document.getElementById('closeAddPlatformModalBtn')?.addEventListener('click', closeAddPlatformModal);
    document.getElementById('cancelAddPlatformBtn')?.addEventListener('click', closeAddPlatformModal);
    document.getElementById('confirmAddPlatformBtn')?.addEventListener('click', saveNewPlatform);

    // Обработчики для кнопок выбора типа площадки
    document.querySelectorAll('.platform-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            setActivePlatformType(type);
        });
    });

    // Закрытие по клику на overlay для добавления площадки
    const addPlatformModal = document.getElementById('addPlatformModal');
    if (addPlatformModal) {
        addPlatformModal.addEventListener('click', (e) => {
            if (e.target === addPlatformModal) {
                closeAddPlatformModal();
            }
        });
    }

    // Обработка Enter в форме добавления площадки
    document.getElementById('addPlatformForm')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveNewPlatform();
        }
    });

    // Обработчики для модального окна добавления валюты
    document.getElementById('closeAddCurrencyModalBtn')?.addEventListener('click', closeAddCurrencyModal);
    document.getElementById('cancelAddCurrencyBtn')?.addEventListener('click', closeAddCurrencyModal);
    document.getElementById('confirmAddCurrencyBtn')?.addEventListener('click', saveNewCurrency);

    // Обработчики для кнопок выбора типа валюты
    document.querySelectorAll('.currency-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            setActiveCurrencyType(type);
        });
    });

    // Закрытие по клику на overlay для добавления валюты
    const addCurrencyModal = document.getElementById('addCurrencyModal');
    if (addCurrencyModal) {
        addCurrencyModal.addEventListener('click', (e) => {
            if (e.target === addCurrencyModal) {
                closeAddCurrencyModal();
            }
        });
    }

    // Обработка Enter в форме добавления валюты
    document.getElementById('addCurrencyForm')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveNewCurrency();
        }
    });

    // Обработчики для модального окна добавления актива
    document.getElementById('closeAddAssetModalBtn')?.addEventListener('click', closeAddAssetModal);
    document.getElementById('cancelAddAssetBtn')?.addEventListener('click', closeAddAssetModal);
    document.getElementById('confirmAddAssetBtn')?.addEventListener('click', saveNewAsset);

    // Обработчики для кнопок выбора типа актива
    document.querySelectorAll('.asset-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            setActiveAssetType(type);
        });
    });

    // Закрытие по клику на overlay для добавления актива
    const addAssetModal = document.getElementById('addAssetModal');
    if (addAssetModal) {
        addAssetModal.addEventListener('click', (e) => {
            if (e.target === addAssetModal) {
                closeAddAssetModal();
            }
        });
    }

    // Обработка Enter в форме добавления актива
    document.getElementById('addAssetForm')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveNewAsset();
        }
    });

    // Быстрые кнопки сумм
    document.querySelectorAll('.quick-amount-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.form-group')?.querySelector('input[type="text"]');
            if (input) {
                input.value = this.dataset.amount;
                formatInput(input);
            }
        });
    });

    // Форматирование числовых полей
    const numberInputs = [
        'depositAmount',
        'tradeQuantity',
        'tradePrice',
        'tradeCommission',
        'transferAmount',
        'transferCommission'
    ];
    
    numberInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', function() {
                formatInput(this);
            });
            input.addEventListener('blur', function() {
                formatInput(this);
            });
        }
    });

    // Расчет итога для торгов
    ['tradeQuantity', 'tradePrice', 'tradeCommission'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', calculateTradeTotal);
    });

    // ИСПРАВЛЕННЫЙ обработчик поиска валют/активов
    const currencySearch = document.getElementById('currencySearch');
    if (currencySearch) {
        // Создаем новый элемент, чтобы удалить старые обработчики
        const newSearch = currencySearch.cloneNode(true);
        currencySearch.parentNode.replaceChild(newSearch, currencySearch);
        
        newSearch.addEventListener('input', function(e) {
            const searchText = e.target.value;
            
            if (currentModalContext && currentModalContext.mode === 'asset') {
                filterAssetsForSelect(searchText);
            } else {
                filterCurrencies(searchText);
            }
        });

        newSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchText = this.value.trim();
                if (searchText) {
                    if (currentModalContext && currentModalContext.mode === 'asset') {
                        addNewAssetFromCurrencyModal(searchText);
                    } else {
                        addNewCurrencyAndSelect(searchText, currentModalContext.source, currentModalContext.subMode);
                    }
                }
            }
        });
    }

    // Закрытие модального окна валют
    document.getElementById('closeCurrencyModalBtn')?.addEventListener('click', closeCurrencyModal);

    // Закрытие по клику на overlay для валют
    const currencyModal = document.getElementById('currencySelectModal');
    if (currencyModal) {
        currencyModal.addEventListener('click', (e) => {
            if (e.target === currencyModal) {
                closeCurrencyModal();
            }
        });
    }

    // Поиск площадки
    document.getElementById('platformSearch')?.addEventListener('input', function(e) {
        if (currentModalContext.mode === 'platform') {
            filterPlatforms(e.target.value);
        }
    });

    // Закрытие модального окна площадки
    document.getElementById('closePlatformModalBtn')?.addEventListener('click', closePlatformModal);

    // Закрытие по клику на overlay для площадок
    const platformModal = document.getElementById('platformSelectModal');
    if (platformModal) {
        platformModal.addEventListener('click', (e) => {
            if (e.target === platformModal) {
                closePlatformModal();
            }
        });
    }

    // Enter в поиске площадок
    document.getElementById('platformSearch')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const searchText = this.value.trim();
            if (searchText && currentModalContext.mode === 'platform') {
                addNewPlatformAndSelect(searchText, currentModalContext.source);
            }
        }
    });

    // Кнопки популярных активов в переводе
    document.querySelectorAll('#transferPopularAssets .quick-asset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const idMatch = onclick.match(/'(\d+)'/);
                const symbolMatch = onclick.match(/, '([^']+)'/);
                if (idMatch && symbolMatch) {
                    selectAsset(idMatch[1], symbolMatch[1]);
                }
            }
        });
    });

    // Кнопки популярных валют для комиссии в переводе
    document.querySelectorAll('#transferPopularCommissionCurrencies .quick-platform-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const codeMatch = onclick.match(/'([^']+)'/);
                if (codeMatch) {
                    selectCommissionCurrency(codeMatch[1]);
                }
            }
        });
    });

    // Закрытие по ESC
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (depositModal?.classList.contains('active')) closeDepositModal();
        if (tradeModal?.classList.contains('active')) closeTradeModal();
        if (transferModal?.classList.contains('active')) closeTransferModal();
        if (document.getElementById('platformSelectModal')?.classList.contains('active')) closePlatformModal();
        if (document.getElementById('currencySelectModal')?.classList.contains('active')) closeCurrencyModal();
        if (document.getElementById('addPlatformModal')?.classList.contains('active')) closeAddPlatformModal();
        if (document.getElementById('addCurrencyModal')?.classList.contains('active')) closeAddCurrencyModal();
        if (document.getElementById('addAssetModal')?.classList.contains('active')) closeAddAssetModal();
        if (executeModal?.classList.contains('active')) closeExecuteModal();
        if (cancelModal?.classList.contains('active')) closeCancelModal();
    });

    // Контейнер уведомлений
    if (!document.getElementById('notificationContainer')) {
        const container = document.createElement('div');
        container.className = 'notification-container';
        container.id = 'notificationContainer';
        document.body.appendChild(container);
    }

    // Загружаем операции
    loadOperations(1);
});

function showPurchaseHistory(data) {
    const modal = document.getElementById('purchaseHistoryModal');
    const symbolSpan = document.getElementById('purchaseHistorySymbol');
    const body = document.getElementById('purchaseHistoryBody');
    
    symbolSpan.textContent = data.symbol;
    
    if (data.history.length === 0) {
        body.innerHTML = '<div style="text-align: center; padding: 30px; color: #6b7a8f;">Нет истории покупок</div>';
    } else {
        let html = '';
        data.history.forEach(item => {
            // Исправленное форматирование даты без сдвига часового пояса
            let formattedDate = item.date;
            if (typeof item.date === 'string' && item.date.match(/^\d{4}-\d{2}-\d{2}/)) {
                const parts = item.date.split('T')[0].split('-');
                formattedDate = `${parts[2]}.${parts[1]}.${parts[0]}`;
            } else {
                const date = new Date(item.date);
                if (!isNaN(date.getTime())) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    formattedDate = `${day}.${month}.${year}`;
                }
            }
            
            const quantity = Number(item.quantity).toLocaleString('ru-RU', { 
                minimumFractionDigits: 0, 
                maximumFractionDigits: 8 
            });
            const price = Number(item.price).toLocaleString('ru-RU', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
            const total = (item.quantity * item.price).toLocaleString('ru-RU', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
            
            html += `
                <div class="purchase-history-item">
                    <div>
                        <div class="purchase-history-date">${formattedDate}</div>
                        <div style="font-size: 12px; color: #6b7a8f; margin-top: 2px;">${item.platform}</div>
                    </div>
                    <div class="purchase-history-details">
                        <div class="purchase-history-quantity">${quantity} ${data.symbol}</div>
                        <div class="purchase-history-price">по ${price} ${item.price_currency}</div>
                        <div class="purchase-history-total">${total} ${item.price_currency}</div>
                    </div>
                </div>
            `;
        });
        body.innerHTML = html;
    }
    
    modal.classList.add('active');
}

function closePurchaseHistoryModal() {
    document.getElementById('purchaseHistoryModal').classList.remove('active');
}

// Закрытие по клику на overlay
document.getElementById('purchaseHistoryModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closePurchaseHistoryModal();
    }
});

// Закрытие по ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePurchaseHistoryModal();
    }
});

function showAssetHistory(data) {
    const modal = document.getElementById('purchaseHistoryModal');
    const symbolSpan = document.getElementById('purchaseHistorySymbol');
    const body = document.getElementById('purchaseHistoryBody');
    
    symbolSpan.textContent = data.symbol;
    
    if (data.history.length === 0) {
        body.innerHTML = '<div style="text-align: center; padding: 30px; color: #6b7a8f;">Нет истории операций</div>';
    } else {
        let html = '';
        data.history.forEach(item => {
            // Форматирование даты
            let formattedDate = item.date;
            if (typeof item.date === 'string' && item.date.match(/^\d{4}-\d{2}-\d{2}/)) {
                const parts = item.date.split('T')[0].split('-');
                formattedDate = `${parts[2]}.${parts[1]}.${parts[0]}`;
            } else {
                const date = new Date(item.date);
                if (!isNaN(date.getTime())) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    formattedDate = `${day}.${month}.${year}`;
                }
            }
            
            // Функция для форматирования чисел
            const formatNumber = (num, isFiat = false) => {
                if (num === null || num === undefined) return '';
                
                // Преобразуем в строку
                let str = num.toString();
                // Если число в экспоненциальной форме
                if (str.includes('e')) {
                    str = num.toFixed(12);
                }
                
                let formatted;
                
                // Для фиатных валют (RUB, USD, EUR) - 2 знака после запятой
                if (isFiat) {
                    let rounded = parseFloat(num).toFixed(2);
                    // Убираем .00 если они есть
                    formatted = rounded.replace(/\.?0+$/, '');
                } else {
                    // Для криптовалют - до 8 знаков, убираем лишние нули
                    let rounded = parseFloat(num).toFixed(8);
                    formatted = rounded.replace(/\.?0+$/, '');
                }
                
                // Добавляем пробелы между разрядами в целой части
                let parts = formatted.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                
                if (parts.length > 1 && parts[1]) {
                    return parts[0] + '.' + parts[1];
                }
                return parts[0];
            };
            
            // Определяем, является ли валюта фиатной
            const isFiatCurrency = (curr) => {
                return curr === 'RUB' || curr === 'USD' || curr === 'EUR' || 
                       curr === 'GBP' || curr === 'CNY' || curr === 'JPY';
            };
            
            const isFiat = isFiatCurrency(item.price_currency);
            const quantity = formatNumber(item.quantity, false);
            const price = formatNumber(item.price, isFiat);
            const total = formatNumber(item.quantity * item.price, isFiat);
            
            let operationColor = 'var(--text-secondary);';
            let operationText = '';
            let priceText = '';
            let totalText = '';
            
            switch(item.operation_type) {
                case 'buy':
                    operationColor = '#00a86b';
                    operationText = `Покупка ${quantity} ${data.symbol}`;
                    priceText = `по ${price} ${item.price_currency}`;
                    totalText = `💰 ${total} ${item.price_currency}`;
                    break;
                case 'sell':
                    operationColor = '#e53e3e';
                    operationText = `Продажа ${quantity} ${data.symbol}`;
                    priceText = `по ${price} ${item.price_currency}`;
                    totalText = `💰 ${total} ${item.price_currency}`;
                    break;
                case 'payment':
                    operationColor = '#e53e3e';
                    operationText = `Списание ${quantity} ${item.price_currency}`;
                    priceText = '';
                    totalText = `💸 ${quantity} ${item.price_currency}`;
                    break;
                case 'income':
                    operationColor = '#00a86b';
                    operationText = `Поступление ${quantity} ${item.price_currency}`;
                    priceText = '';
                    totalText = `💰 ${quantity} ${item.price_currency}`;
                    break;
                case 'deposit':
                    operationColor = '#1a5cff';
                    operationText = `Пополнение ${quantity} ${data.symbol}`;
                    priceText = '';
                    totalText = `➕ ${quantity} ${data.symbol}`;
                    break;
                case 'transfer_in':
                    operationColor = '#ff9f4a';
                    operationText = `Входящий перевод ${quantity} ${data.symbol}`;
                    priceText = '';
                    totalText = `📥 ${quantity} ${data.symbol}`;
                    break;
                case 'transfer_out':
                    operationColor = '#ff9f4a';
                    operationText = `Исходящий перевод ${quantity} ${data.symbol}`;
                    priceText = '';
                    totalText = `📤 ${quantity} ${data.symbol}`;
                    break;
            }
            
            html += `
                <div class="purchase-history-item" style="padding: 12px; border-bottom: 1px solid var(--border-color, #edf2f7);">
                    <div style="flex: 1;">
                        <div class="purchase-history-date" style="font-size: 13px; color: #6b7a8f; margin-bottom: 4px;">${formattedDate}</div>
                        <div style="font-size: 12px; color: #6b7a8f;">${item.platform || '—'}</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="color: ${operationColor}; font-weight: 500; margin-bottom: 4px;">${operationText}</div>
                        ${priceText ? `<div style="font-size: 12px; color: #6b7a8f;">${priceText}</div>` : ''}
                        <div style="font-size: 13px; font-weight: 600; color: ${operationColor}; margin-top: 4px;">${totalText}</div>
                    </div>
                </div>
            `;
        });
        body.innerHTML = html;
    }
    
    modal.classList.add('active');
}

// ============================================================================
// ПАГИНАЦИЯ ОПЕРАЦИЙ
// ============================================================================

let currentOperationsPage = 1;
const operationsPerPage = 5;

// Глобальная переменная для хранения всех операций
let allFilteredOperations = [];

async function loadOperations(page) {
    currentOperationsPage = page;
    
    // Показываем индикатор загрузки
    const container = document.getElementById('operationsContainer');
    const operationsList = document.getElementById('operationsList');
    if (operationsList) {
        operationsList.style.opacity = '0.5';
    }
    
    try {
        // Загружаем все операции только один раз
        if (allFilteredOperations.length === 0) {
            const response = await fetch('get_operations.php?page=1&per_page=100');
            const data = await response.json();
            
            if (data.success) {
                // Фильтруем все операции один раз
                allFilteredOperations = filterOperations(data.operations);
            } else {
                if (operationsList) operationsList.style.opacity = '1';
                return;
            }
        }
        
        // Рассчитываем пагинацию на основе отфильтрованных операций
        const totalPages = Math.ceil(allFilteredOperations.length / 5);
        const pagination = {
            current_page: page,
            total_pages: totalPages,
            total: allFilteredOperations.length,
            per_page: 5,
            from: (page - 1) * 5 + 1,
            to: Math.min(page * 5, allFilteredOperations.length),
            has_previous: page > 1,
            has_next: page < totalPages
        };
        
        // Отображаем операции для текущей страницы
        updateOperationsList(allFilteredOperations, pagination);
        
    } catch (error) {
        showNotification('error', 'Ошибка', 'Не удалось загрузить операции');
    } finally {
        if (operationsList) {
            operationsList.style.opacity = '1';
        }
    }
}

function filterOperations(operations) {       
    // Группируем операции по ID
    const groupedOps = {};
    operations.forEach(op => {
        if (!groupedOps[op.operation_id]) {
            groupedOps[op.operation_id] = [];
        }
        groupedOps[op.operation_id].push(op);
    });
    
    // Оставляем только те группы, которые дадут одну запись
    const filteredOps = [];
    
    Object.values(groupedOps).forEach(group => {        
        // Сортируем группу, чтобы buy_asset/sell_asset были первыми
        group.sort((a, b) => {
            if (a.operation_type.includes('asset')) return -1;
            if (b.operation_type.includes('asset')) return 1;
            return 0;
        });
        
        const mainOp = group[0];
        const secondaryOp = group[1];
        
        // Определяем, показывать ли эту группу
        let shouldShow = true;
        let reason = '';
        
        if (mainOp.operation_type == 'buy_payment' && !secondaryOp) {
            shouldShow = false;
            reason = 'одиночный buy_payment';
        }
        else if (mainOp.operation_type == 'sell_income' && !secondaryOp) {
            shouldShow = false;
            reason = 'одиночный sell_income';
        }
        
        // ВАЖНО: ВСЕГДА показываем переводы, даже если они одиночные
        if (mainOp.operation_type == 'transfer_in' || mainOp.operation_type == 'transfer_out') {
            shouldShow = true;
            reason = 'перевод (всегда показываем)';
        }
        
        if (shouldShow) {
            filteredOps.push(mainOp);
        }
    });
    
    // Сортируем по дате (сначала новые)
    filteredOps.sort((a, b) => new Date(b.date) - new Date(a.date));
    
    return filteredOps;
}

function updateOperationsList(operations, pagination) {
    const container = document.getElementById('operationsList');
    const headerSpan = document.querySelector('#operationsContainer .stat-badge');
    
    // Получаем доступную высоту блока
    const operationsCard = document.querySelector('.card-operations');
    const operationsList = document.getElementById('operationsList');
    
    // Функция для расчета количества операций, помещающихся в блок
    function calculateVisibleOperationsCount() {
        if (!operationsList || operationsList.children.length === 0) return 5;
        
        const containerHeight = operationsList.clientHeight;
        const firstItem = operationsList.children[0];
        if (!firstItem) return 5;
        
        const itemHeight = firstItem.offsetHeight;
        const availableHeight = containerHeight - 20; // Отступы
        
        // Минимум 5 операций, максимум - сколько поместится
        let maxVisible = Math.max(5, Math.floor(availableHeight / itemHeight));
        
        // Ограничиваем максимальное количество (чтобы не было слишком много)
        maxVisible = Math.min(maxVisible, 15);
        
        return maxVisible;
    }
    
    // Рассчитываем количество операций для отображения
    const visibleCount = calculateVisibleOperationsCount();
    
    // Обрезаем операции для текущей страницы
    const startIndex = (pagination.current_page - 1) * pagination.per_page;
    const endIndex = Math.min(startIndex + pagination.per_page, operations.length);
    const pageOperations = operations.slice(startIndex, endIndex);
    
    // Если операций меньше visibleCount, берем больше с предыдущих/следующих страниц
    let displayOperations = [...pageOperations];
    let usedOperations = new Set(displayOperations.map(op => op.operation_id));
    
    // Если на текущей странице меньше visibleCount операций, добавляем из других страниц
    if (displayOperations.length < visibleCount && operations.length > displayOperations.length) {
        // Сначала добавляем из следующих страниц
        let nextPage = pagination.current_page + 1;
        while (displayOperations.length < visibleCount && nextPage <= pagination.total_pages) {
            const nextStart = (nextPage - 1) * pagination.per_page;
            const nextEnd = Math.min(nextStart + pagination.per_page, operations.length);
            const nextOps = operations.slice(nextStart, nextEnd);
            
            for (const op of nextOps) {
                if (!usedOperations.has(op.operation_id)) {
                    displayOperations.push(op);
                    usedOperations.add(op.operation_id);
                    if (displayOperations.length >= visibleCount) break;
                }
            }
            nextPage++;
        }
        
        // Если все еще мало, добавляем из предыдущих страниц
        let prevPage = pagination.current_page - 1;
        while (displayOperations.length < visibleCount && prevPage >= 1) {
            const prevStart = (prevPage - 1) * pagination.per_page;
            const prevEnd = Math.min(prevStart + pagination.per_page, operations.length);
            const prevOps = operations.slice(prevStart, prevEnd);
            
            for (const op of prevOps.reverse()) {
                if (!usedOperations.has(op.operation_id)) {
                    displayOperations.unshift(op);
                    usedOperations.add(op.operation_id);
                    if (displayOperations.length >= visibleCount) break;
                }
            }
            prevPage--;
        }
    }
    
    // Ограничиваем количество отображаемых операций
    displayOperations = displayOperations.slice(0, visibleCount);
    
    let html = '';
    
    // Обрабатываем операции
    displayOperations.forEach((op, index) => {
        let iconClass = '';
        let iconType = '';
        let displayText = '';
        let detailsLine = '';
        let displayDate = op.date;
        
        // Определяем иконку
        if (op.direction == 'in' || op.operation_type == 'buy_asset' || op.operation_type == 'sell_income' || op.operation_type == 'deposit' || op.operation_type == 'transfer_in') {
            iconClass = 'icon-buy';
            iconType = 'fa-arrow-down';
        } else {
            iconClass = 'icon-sell';
            iconType = 'fa-arrow-up';
        }
        
        // Формируем текст в зависимости от типа операции
        if (op.operation_type == 'buy_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'buy_payment');
            
            if (secondaryOp) {
                const assetAmount = formatAmount(op.amount, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const moneyAmount = formatAmount(secondaryOp.amount_out, secondaryOp.currency);
                
                displayText = `Куплено ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform} ← ${secondaryOp.platform}`;
            } else {
                const assetAmount = formatAmount(op.amount, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const totalCost = formatAmount(op.amount * op.price, op.price_currency);
                
                displayText = `Куплено ${assetAmount} ${op.currency} за ${totalCost} ${op.price_currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            }
        }
        else if (op.operation_type == 'sell_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'sell_income');
            
            if (secondaryOp) {
                const assetAmount = formatAmount(op.amount_out, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const moneyAmount = formatAmount(secondaryOp.amount, secondaryOp.currency);
                
                displayText = `Продано ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            } else {
                const assetAmount = formatAmount(op.amount_out, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const totalIncome = formatAmount(op.amount_out * op.price, op.price_currency);
                
                displayText = `Продано ${assetAmount} ${op.currency} за ${totalIncome} ${op.price_currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            }
        }
        else if (op.operation_type == 'deposit') {
            displayText = `Пополнение: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
        }
        else if (op.operation_type == 'transfer_in') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const outOp = relatedOps.find(o => o.operation_type === 'transfer_out');
            
            displayText = `Входящий перевод: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
            
            if (outOp && outOp.commission && outOp.commission > 0) {
                const commissionAmount = formatAmount(outOp.commission, outOp.commission_currency || outOp.currency);
                detailsLine += ` · комиссия ${commissionAmount} ${outOp.commission_currency || outOp.currency}`;
            }
        }
        else if (op.operation_type == 'transfer_out') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const inOp = relatedOps.find(o => o.operation_type === 'transfer_in');
            
            displayText = `Исходящий перевод: ${formatAmount(op.amount_out, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
            
            if (op.commission && op.commission > 0) {
                const commissionAmount = formatAmount(op.commission, op.commission_currency || op.currency);
                detailsLine += ` · комиссия ${commissionAmount} ${op.commission_currency || op.currency}`;
            }
            
            iconClass = 'icon-convert';
            iconType = 'fa-exchange-alt';
            
            html += `
                <div class="operation-item">
                    <div class="operation-icon ${iconClass}">
                        <i class="fas ${iconType}"></i>
                    </div>
                    <div class="operation-details">
                        <div class="operation-title">${displayText}</div>
                        <div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">
                            ${detailsLine}
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        
        if (displayText) {
            html += `
                <div class="operation-item">
                    <div class="operation-icon ${iconClass}">
                        <i class="fas ${iconType}"></i>
                    </div>
                    <div class="operation-details">
                        <div class="operation-title">${displayText}</div>
                        <div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">
                            ${detailsLine}
                        </div>
                    </div>
                </div>
            `;
        }
    });
    
    if (html === '') {
        html = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет операций для отображения</div>';
    }
    
    container.innerHTML = html;
    
    // Обновляем пагинацию
    updatePagination(pagination, displayOperations.length);
    
    // Добавляем обработчик изменения размера окна
    if (!window.operationsResizeHandler) {
        window.operationsResizeHandler = true;
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (allFilteredOperations.length > 0) {
                    loadOperations(currentOperationsPage);
                }
            }, 250);
        });
    }
}

function updatePagination(pagination, visibleCount) {
    const paginationHtml = document.getElementById('paginationControls');
    
    // Показываем пагинацию только если общее количество операций больше видимого
    if (pagination.total <= visibleCount) {
        paginationHtml.innerHTML = '';
        return;
    }
    
    let html = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #edf2f7;">
            <div style="display: flex; gap: 5px;">
    `;
    
    if (pagination.has_previous) {
        html += `
            <button onclick="loadOperations(${pagination.current_page - 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px; border: none; cursor: pointer;">
                <i class="fas fa-chevron-left"></i> Назад
            </button>
        `;
    }
    
    if (pagination.has_next) {
        html += `
            <button onclick="loadOperations(${pagination.current_page + 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px; border: none; cursor: pointer;">
                Вперед <i class="fas fa-chevron-right"></i>
            </button>
        `;
    }
    
    html += `
            </div>
            <div style="color: #6b7a8f; font-size: 13px;">
                Страница ${pagination.current_page} из ${pagination.total_pages}
            </div>
        </div>
    `;
    
    paginationHtml.innerHTML = html;
}

function updateOperationsList(operations, pagination) {
    const container = document.getElementById('operationsList');
    const headerSpan = document.querySelector('#operationsContainer .stat-badge');
    
    // Получаем доступную высоту блока
    const operationsCard = document.querySelector('.card-operations');
    const operationsList = document.getElementById('operationsList');
    
    // Функция для расчета количества операций, помещающихся в блок
    function calculateVisibleOperationsCount() {
        if (!operationsList || operationsList.children.length === 0) return 5;
        
        const containerHeight = operationsList.clientHeight;
        const firstItem = operationsList.children[0];
        if (!firstItem) return 5;
        
        const itemHeight = firstItem.offsetHeight;
        const availableHeight = containerHeight - 20; // Отступы
        
        // Минимум 5 операций, максимум - сколько поместится
        let maxVisible = Math.max(5, Math.floor(availableHeight / itemHeight));
        
        // Ограничиваем максимальное количество (чтобы не было слишком много)
        maxVisible = Math.min(maxVisible, 15);
        
        return maxVisible;
    }
    
    // Рассчитываем количество операций для отображения
    const visibleCount = calculateVisibleOperationsCount();
    
    // Обрезаем операции для текущей страницы
    const startIndex = (pagination.current_page - 1) * pagination.per_page;
    const endIndex = Math.min(startIndex + pagination.per_page, operations.length);
    const pageOperations = operations.slice(startIndex, endIndex);
    
    // Если операций меньше visibleCount, берем больше с предыдущих/следующих страниц
    let displayOperations = [...pageOperations];
    let usedOperations = new Set(displayOperations.map(op => op.operation_id));
    
    // Если на текущей странице меньше visibleCount операций, добавляем из других страниц
    if (displayOperations.length < visibleCount && operations.length > displayOperations.length) {
        // Сначала добавляем из следующих страниц
        let nextPage = pagination.current_page + 1;
        while (displayOperations.length < visibleCount && nextPage <= pagination.total_pages) {
            const nextStart = (nextPage - 1) * pagination.per_page;
            const nextEnd = Math.min(nextStart + pagination.per_page, operations.length);
            const nextOps = operations.slice(nextStart, nextEnd);
            
            for (const op of nextOps) {
                if (!usedOperations.has(op.operation_id)) {
                    displayOperations.push(op);
                    usedOperations.add(op.operation_id);
                    if (displayOperations.length >= visibleCount) break;
                }
            }
            nextPage++;
        }
        
        // Если все еще мало, добавляем из предыдущих страниц
        let prevPage = pagination.current_page - 1;
        while (displayOperations.length < visibleCount && prevPage >= 1) {
            const prevStart = (prevPage - 1) * pagination.per_page;
            const prevEnd = Math.min(prevStart + pagination.per_page, operations.length);
            const prevOps = operations.slice(prevStart, prevEnd);
            
            for (const op of prevOps.reverse()) {
                if (!usedOperations.has(op.operation_id)) {
                    displayOperations.unshift(op);
                    usedOperations.add(op.operation_id);
                    if (displayOperations.length >= visibleCount) break;
                }
            }
            prevPage--;
        }
    }
    
    // Ограничиваем количество отображаемых операций
    displayOperations = displayOperations.slice(0, visibleCount);
    
    let html = '';
    
    // Обрабатываем операции
    displayOperations.forEach((op, index) => {
        let iconClass = '';
        let iconType = '';
        let displayText = '';
        let detailsLine = '';
        let displayDate = op.date;
        
        // Определяем иконку
        if (op.direction == 'in' || op.operation_type == 'buy_asset' || op.operation_type == 'sell_income' || op.operation_type == 'deposit' || op.operation_type == 'transfer_in') {
            iconClass = 'icon-buy';
            iconType = 'fa-arrow-down';
        } else {
            iconClass = 'icon-sell';
            iconType = 'fa-arrow-up';
        }
        
        // Формируем текст в зависимости от типа операции
        if (op.operation_type == 'buy_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'buy_payment');
            
            if (secondaryOp) {
                const assetAmount = formatAmount(op.amount, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const moneyAmount = formatAmount(secondaryOp.amount_out, secondaryOp.currency);
                
                displayText = `Куплено ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform} ← ${secondaryOp.platform}`;
            } else {
                const assetAmount = formatAmount(op.amount, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const totalCost = formatAmount(op.amount * op.price, op.price_currency);
                
                displayText = `Куплено ${assetAmount} ${op.currency} за ${totalCost} ${op.price_currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            }
        }
        else if (op.operation_type == 'sell_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'sell_income');
            
            if (secondaryOp) {
                const assetAmount = formatAmount(op.amount_out, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const moneyAmount = formatAmount(secondaryOp.amount, secondaryOp.currency);
                
                displayText = `Продано ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            } else {
                const assetAmount = formatAmount(op.amount_out, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const totalIncome = formatAmount(op.amount_out * op.price, op.price_currency);
                
                displayText = `Продано ${assetAmount} ${op.currency} за ${totalIncome} ${op.price_currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            }
        }
        else if (op.operation_type == 'deposit') {
            displayText = `Пополнение: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
        }
        else if (op.operation_type == 'transfer_in') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const outOp = relatedOps.find(o => o.operation_type === 'transfer_out');
            
            displayText = `Входящий перевод: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
            
            if (outOp && outOp.commission && outOp.commission > 0) {
                const commissionAmount = formatAmount(outOp.commission, outOp.commission_currency || outOp.currency);
                detailsLine += ` · комиссия ${commissionAmount} ${outOp.commission_currency || outOp.currency}`;
            }
        }
        else if (op.operation_type == 'transfer_out') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const inOp = relatedOps.find(o => o.operation_type === 'transfer_in');
            
            displayText = `Исходящий перевод: ${formatAmount(op.amount_out, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
            
            if (op.commission && op.commission > 0) {
                const commissionAmount = formatAmount(op.commission, op.commission_currency || op.currency);
                detailsLine += ` · комиссия ${commissionAmount} ${op.commission_currency || op.currency}`;
            }
            
            iconClass = 'icon-convert';
            iconType = 'fa-exchange-alt';
            
            html += `
                <div class="operation-item">
                    <div class="operation-icon ${iconClass}">
                        <i class="fas ${iconType}"></i>
                    </div>
                    <div class="operation-details">
                        <div class="operation-title">${displayText}</div>
                        <div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">
                            ${detailsLine}
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        
        if (displayText) {
            html += `
                <div class="operation-item">
                    <div class="operation-icon ${iconClass}">
                        <i class="fas ${iconType}"></i>
                    </div>
                    <div class="operation-details">
                        <div class="operation-title">${displayText}</div>
                        <div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">
                            ${detailsLine}
                        </div>
                    </div>
                </div>
            `;
        }
    });
    
    if (html === '') {
        html = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет операций для отображения</div>';
    }
    
    container.innerHTML = html;
    
    // Обновляем пагинацию
    updatePagination(pagination, displayOperations.length);
    
    // Добавляем обработчик изменения размера окна
    if (!window.operationsResizeHandler) {
        window.operationsResizeHandler = true;
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (allFilteredOperations.length > 0) {
                    loadOperations(currentOperationsPage);
                }
            }, 250);
        });
    }
}

function updatePagination(pagination, visibleCount) {
    const paginationHtml = document.getElementById('paginationControls');
    
    // Показываем пагинацию только если общее количество операций больше видимого
    if (pagination.total <= visibleCount) {
        paginationHtml.innerHTML = '';
        return;
    }
    
    let html = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #edf2f7;">
            <div style="display: flex; gap: 5px;">
    `;
    
    if (pagination.has_previous) {
        html += `
            <button onclick="loadOperations(${pagination.current_page - 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px; border: none; cursor: pointer;">
                <i class="fas fa-chevron-left"></i> Назад
            </button>
        `;
    }
    
    if (pagination.has_next) {
        html += `
            <button onclick="loadOperations(${pagination.current_page + 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px; border: none; cursor: pointer;">
                Вперед <i class="fas fa-chevron-right"></i>
            </button>
        `;
    }
    
    html += `
            </div>
            <div style="color: #6b7a8f; font-size: 13px;">
                Страница ${pagination.current_page} из ${pagination.total_pages}
            </div>
        </div>
    `;
    
    paginationHtml.innerHTML = html;
}

function formatDate(dateString) {
    // Если дата уже в формате YYYY-MM-DD, парсим как локальную дату без учета часового пояса
    if (typeof dateString === 'string' && dateString.match(/^\d{4}-\d{2}-\d{2}/)) {
        // Разбираем дату как локальную, без преобразования в UTC
        const parts = dateString.split('T')[0].split('-');
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    
    // Для других форматов используем стандартный парсинг
    const date = new Date(dateString);
    // Проверяем, что дата валидна
    if (isNaN(date.getTime())) {
        return dateString;
    }
    // Форматируем с учетом локального времени, но без сдвига
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${day}.${month}.${year}`;
}

function formatNumber(num, decimals) {
    if (num === null || num === undefined) return '0';
    
    let formatted = num.toFixed(decimals);
    // Убираем лишние нули в конце
    formatted = formatted.replace(/\.?0+$/, '');
    
    // Разделяем целую и дробную части
    let parts = formatted.split('.');
    // Форматируем целую часть с пробелами
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Возвращаем без пробелов в дробной части
    if (parts.length > 1 && parts[1]) {
        return parts[0] + '.' + parts[1];
    }
    return parts[0];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatAmount(amount, currency) {
    if (!amount && amount !== 0) return '0';
    
    // Преобразуем в число
    let num = parseFloat(amount);
    if (isNaN(num)) return '0';
    
    // Проверяем, целое ли число
    if (Number.isInteger(num)) {
        return num.toLocaleString('ru-RU').replace(/,/g, ' ');
    }
    
    // Список криптовалют (все, что есть в БД)
    const cryptoList = ['USDT', 'USDC', 'BTC', 'ETH', 'SOL', 'BNB', 'LINK', 'STX', 'ZK', 'FIL', 'ONDO', 'RENDER', 'GRT', 'TWT', 'APE', 'CELO', 'GOAT', 'TRUMP', 'IMX', 'POL', 'ARKM'];
    
    // Для криптовалют показываем до 4-6 знаков, убирая лишние нули
    if (cryptoList.includes(currency)) {
        // Для BTC и ETH можно оставить 6 знаков, для остальных 4
        let decimals = (currency === 'BTC' || currency === 'ETH') ? 6 : 4;
        let rounded = num.toFixed(decimals);
        // Убираем лишние нули в конце
        rounded = rounded.replace(/\.?0+$/, '');
        
        // Разделяем целую и дробную части
        let parts = rounded.split('.');
        // Форматируем целую часть с пробелами
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        // Дробную часть оставляем без пробелов
        if (parts.length > 1 && parts[1]) {
            return parts[0] + '.' + parts[1];
        }
        return parts[0];
    }
    
    // Для фиата (RUB, USD, EUR) показываем 2 знака, убирая нули
    let rounded = num.toFixed(2);
    rounded = rounded.replace(/\.?0+$/, '');
    
    // Разделяем целую и дробную части
    let parts = rounded.split('.');
    // Форматируем целую часть с пробелами
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    // Дробную часть оставляем без пробелов
    if (parts.length > 1 && parts[1]) {
        return parts[0] + '.' + parts[1];
    }
    return parts[0];
}

// ============================================================================
// ПЕРЕКЛЮЧЕНИЕ ТЕМЫ
// ============================================================================

// Устанавливаем текущую тему при загрузке
document.body.className = '<?= $current_theme === 'dark' ? 'dark-theme' : '' ?>';

document.getElementById('themeToggleBtn').addEventListener('click', function() {
    const isDarkTheme = document.body.classList.contains('dark-theme');
    const newTheme = isDarkTheme ? 'light' : 'dark';
    const icon = this.querySelector('i');
    const text = this.querySelector('#themeToggleText');
    
    // Показываем индикатор загрузки
    this.style.opacity = '0.7';
    this.disabled = true;
    
    // Отправляем запрос на сохранение темы
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=save_theme&theme=' + newTheme
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Меняем тему
            if (newTheme === 'dark') {
                document.body.classList.add('dark-theme');
                icon.className = 'fas fa-sun';
                text.textContent = 'Светлая';
            } else {
                document.body.classList.remove('dark-theme');
                icon.className = 'fas fa-moon';
                text.textContent = 'Темная';
            }
            
            // Показываем уведомление
            //showNotification('success', 'Тема изменена', 
            //    newTheme === 'dark' ? 'Включена темная тема' : 'Включена светлая тема');
        } else {
            //showNotification('error', 'Ошибка', 'Не удалось сохранить тему');
        }
    })
    .catch(error => {
        showNotification('error', 'Ошибка', 'Не удалось сохранить тему');
    })
    .finally(() => {
        // Возвращаем кнопку в нормальное состояние
        this.style.opacity = '1';
        this.disabled = false;
    });
});

// ============================================================================
// ЛИМИТНЫЕ ОРДЕРА
// ============================================================================

const limitOrderModal = document.getElementById('limitOrderModal');
let selectedLimitPlatform = { id: null, name: '' };
let selectedLimitAsset = { id: null, symbol: '' };
let selectedLimitCurrency = 'USD';

function openLimitOrderModal() {
    limitOrderModal.classList.add('active');
    document.getElementById('limitQuantity').value = '';
    document.getElementById('limitPrice').value = '';
    document.getElementById('limitExpiryDate').value = '';
    document.getElementById('limitNotes').value = '';
    document.getElementById('selectedLimitPlatformDisplay').textContent = 'Выбрать площадку';
    document.getElementById('selectedLimitAssetDisplay').textContent = 'Выбрать актив';
    document.getElementById('selectedLimitCurrencyDisplay').textContent = 'Выбрать'; // Изменено с USD на Выбрать
    document.getElementById('limitPlatformId').value = '';
    document.getElementById('limitAssetId').value = '';
    document.getElementById('limitCurrency').value = ''; // Изменено с USD на пустую строку
    document.getElementById('limitTotalEstimate').textContent = '0';
    
    // По умолчанию выбираем покупку
    document.querySelectorAll('.limit-type-btn').forEach(btn => {
        btn.style.opacity = '0.7';
    });
    document.querySelector('.limit-type-btn[data-type="buy"]').style.opacity = '1';
    document.querySelector('.limit-type-btn[data-type="buy"]').style.border = '2px solid white';
}

function closeLimitOrderModal() {
    limitOrderModal.classList.remove('active');
}

function selectLimitPlatform(id, name) {
    selectedLimitPlatform = { id, name };
    document.getElementById('selectedLimitPlatformDisplay').textContent = name;
    document.getElementById('limitPlatformId').value = id;
}

function selectLimitAsset(id, symbol) {
    selectedLimitAsset = { id, symbol };
    document.getElementById('selectedLimitAssetDisplay').textContent = symbol;
    document.getElementById('limitAssetId').value = id;
    updateLimitTotalEstimate();
}

function selectLimitCurrency(code) {
    selectedLimitCurrency = code;
    document.getElementById('selectedLimitCurrencyDisplay').textContent = code;
    document.getElementById('limitCurrency').value = code;
    updateLimitTotalEstimate();
}

function updateLimitTotalEstimate() {
    const quantity = parseFloat(document.getElementById('limitQuantity').value.replace(/\s/g, '')) || 0;
    const price = parseFloat(document.getElementById('limitPrice').value.replace(/\s/g, '')) || 0;
    const currency = document.getElementById('limitCurrency').value;
    
    const total = quantity * price;
    
    if (total > 0) {
        let formattedTotal = total.toFixed(2);
        if (currency === 'BTC' || currency === 'ETH') {
            formattedTotal = total.toFixed(6);
        }
        document.getElementById('limitTotalEstimate').textContent = `${formattedTotal} ${currency}`;
    } else {
        document.getElementById('limitTotalEstimate').textContent = `0 ${currency}`;
    }
}

async function confirmLimitOrder() {
    const operationType = document.querySelector('.limit-type-btn[style*="opacity: 1"]')?.dataset.type || 'buy';
    const platformId = document.getElementById('limitPlatformId').value;
    const assetId = document.getElementById('limitAssetId').value;
    const quantity = parseFloat(document.getElementById('limitQuantity').value.replace(/\s/g, '')) || 0;
    const limitPrice = parseFloat(document.getElementById('limitPrice').value.replace(/\s/g, '')) || 0;
    const priceCurrency = document.getElementById('limitCurrency').value;
    const expiryDate = document.getElementById('limitExpiryDate').value;
    const notes = document.getElementById('limitNotes').value;

    if (!platformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку');
        return;
    }

    if (!assetId) {
        showNotification('error', 'Ошибка', 'Выберите актив');
        return;
    }

    if (!quantity || quantity <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректное количество');
        return;
    }

    if (!limitPrice || limitPrice <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректную цену');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_limit_order');
    formData.append('operation_type', operationType);
    formData.append('platform_id', platformId);
    formData.append('asset_id', assetId);
    formData.append('quantity', quantity);
    formData.append('limit_price', limitPrice);
    formData.append('price_currency', priceCurrency);
    formData.append('expiry_date', expiryDate);
    formData.append('notes', notes);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', 'Лимитный ордер создан');
            closeLimitOrderModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос');
    }
}

// Закрытие модального окна
document.getElementById('closeLimitOrderModalBtn')?.addEventListener('click', closeLimitOrderModal);
document.getElementById('cancelLimitOrderBtn')?.addEventListener('click', closeLimitOrderModal);

// КНОПКА ПОДТВЕРЖДЕНИЯ ЛИМИТНОГО ОРДЕРА
document.getElementById('confirmLimitOrderBtn')?.addEventListener('click', confirmLimitOrder);

// Закрытие по клику на overlay
if (limitOrderModal) {
    limitOrderModal.addEventListener('click', (e) => {
        if (e.target === limitOrderModal) {
            closeLimitOrderModal();
        }
    });
}

// ============================================================================
// МОДАЛЬНЫЕ ОКНА ПОДТВЕРЖДЕНИЯ ДЛЯ ЛИМИТНЫХ ОРДЕРОВ
// ============================================================================

const executeModal = document.getElementById('executeOrderModal');
const cancelModal = document.getElementById('cancelOrderModal');
let currentOrderId = null;

function openExecuteModal(orderId, orderData) {
    currentOrderId = orderId;
    
    // Заполняем данными
    document.getElementById('executeOrderTitle').textContent = 
        `${orderData.operation_type === 'buy' ? 'Покупка' : 'Продажа'} ${orderData.symbol}`;
    document.getElementById('executeOrderPlatform').textContent = orderData.platform_name;
    document.getElementById('executeOrderQuantity').textContent = 
        `${formatAmount(orderData.quantity, orderData.symbol)} ${orderData.symbol}`;
    document.getElementById('executeOrderPrice').textContent = 
        `${formatAmount(orderData.limit_price, orderData.price_currency)} ${orderData.price_currency}`;
    
    const total = orderData.quantity * orderData.limit_price;
    document.getElementById('executeOrderTotal').textContent = 
        `${formatAmount(total, orderData.price_currency)} ${orderData.price_currency}`;
    
    // Форматируем дату для отображения
    if (orderData.created_at instanceof Date && !isNaN(orderData.created_at)) {
        document.getElementById('executeOrderCreated').textContent = 
            orderData.created_at.toLocaleString('ru-RU');
    } else {
        document.getElementById('executeOrderCreated').textContent = 'Дата не указана';
    }
    
    if (orderData.expiry_date) {
        const expiryDate = new Date(orderData.expiry_date);
        if (!isNaN(expiryDate)) {
            document.getElementById('executeOrderExpiry').textContent = 
                expiryDate.toLocaleDateString('ru-RU');
        } else {
            document.getElementById('executeOrderExpiry').textContent = 'Бессрочно';
        }
    } else {
        document.getElementById('executeOrderExpiry').textContent = 'Бессрочно';
    }
    
    const warningText = orderData.operation_type === 'buy' 
        ? `Будет создана сделка на покупку. Средства (${formatAmount(total, orderData.price_currency)} ${orderData.price_currency}) будут списаны с площадки ${orderData.platform_name}.`
        : `Будет создана сделка на продажу. ${formatAmount(orderData.quantity, orderData.symbol)} ${orderData.symbol} будут списаны с площадки ${orderData.platform_name}.`;
    
    document.getElementById('executeOrderWarning').textContent = warningText;
    
    executeModal.classList.add('active');
}

function closeExecuteModal() {
    executeModal.classList.remove('active');
    currentOrderId = null;
}

function openCancelModal(orderId, orderData) {
    currentOrderId = orderId;
    
    document.getElementById('cancelOrderTitle').textContent = 
        `Отмена ордера на ${orderData.operation_type === 'buy' ? 'покупку' : 'продажу'}`;
    document.getElementById('cancelOrderDescription').textContent = 
        `Вы уверены, что хотите отменить ордер на ${orderData.operation_type === 'buy' ? 'покупку' : 'продажу'} ${formatAmount(orderData.quantity, orderData.symbol)} ${orderData.symbol}?`;
    document.getElementById('cancelOrderPlatform').textContent = orderData.platform_name;
    document.getElementById('cancelOrderPrice').textContent = 
        `${formatAmount(orderData.limit_price, orderData.price_currency)} ${orderData.price_currency}`;
    document.getElementById('cancelOrderQuantity').textContent = 
        `${formatAmount(orderData.quantity, orderData.symbol)} ${orderData.symbol}`;
    
    cancelModal.classList.add('active');
}

function closeCancelModal() {
    cancelModal.classList.remove('active');
    currentOrderId = null;
}

// Обновленные функции для работы с модальными окнами
function showExecuteConfirmation(orderId) {    
     // Находим карточку ордера и собираем данные
    const orderCard = document.getElementById(`order-${orderId}`);
    if (!orderCard) return;
    
    // Устанавливаем currentOrderId
    currentOrderId = orderId;

    // Получаем текст из элементов
    const orderActionText = orderCard.querySelector('.order-action').textContent;
    const orderExchangeText = orderCard.querySelector('.order-exchange').textContent.trim();
    const orderFooterSpans = orderCard.querySelectorAll('.order-footer span');
    const orderPriceText = orderCard.querySelector('.order-price').textContent;
    
    // Парсим количество (убираем "шт" и пробелы)
    const quantityText = orderFooterSpans[1]?.textContent.replace(' шт', '').replace(/\s/g, '') || '0';
    
    // Парсим цену
    const priceParts = orderPriceText.split(' ');
    const limitPrice = parseFloat(priceParts[0].replace(/\s/g, '')) || 0;
    const priceCurrency = priceParts[1] || '';
    
    // Парсим дату создания (формат: "🕒 19.03.2026 16:05")
    const createdText = orderFooterSpans[0]?.textContent.replace('🕒', '').trim() || '';
    
    // Парсим дату истечения
    let expiryDate = null;
    const expiryElement = orderCard.querySelector('div[style*="font-size: 11px"]');
    if (expiryElement) {
        const expiryText = expiryElement.textContent.replace('до', '').trim();
        // Пробуем распарсить дату в формате "19.03.2026"
        const dateParts = expiryText.split('.');
        if (dateParts.length === 3) {
            expiryDate = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`;
        }
    }
    
    // Преобразуем дату создания в формат ISO для корректного отображения
    let createdDate = new Date();
    if (createdText) {
        const dateParts = createdText.split(' ');
        if (dateParts.length >= 2) {
            const dayMonthYear = dateParts[0].split('.');
            const time = dateParts[1] || '00:00';
            if (dayMonthYear.length === 3) {
                // Формат: DD.MM.YYYY HH:MM
                createdDate = new Date(`${dayMonthYear[2]}-${dayMonthYear[1]}-${dayMonthYear[0]}T${time}`);
            }
        }
    }
    
    const orderData = {
        id: orderId,
        operation_type: orderActionText.includes('Покупка') ? 'buy' : 'sell',
        symbol: orderActionText.split(' ')[1] || '',
        platform_name: orderExchangeText,
        quantity: parseFloat(quantityText) || 0,
        limit_price: limitPrice,
        price_currency: priceCurrency,
        created_at: createdDate,
        expiry_date: expiryDate
    };
    
    openExecuteModal(orderId, orderData);
}

function showCancelConfirmation(orderId) {    
    // Находим карточку ордера и собираем данные
    const orderCard = document.getElementById(`order-${orderId}`);
    if (!orderCard) {
        return;
    }
    
    // Устанавливаем currentOrderId и testOrderId
    currentOrderId = orderId;
    testOrderId = orderId; // ДОБАВЛЯЕМ ТЕСТОВУЮ ПЕРЕМЕННУЮ
    
    const orderData = {
        operation_type: orderCard.querySelector('.order-action').textContent.includes('Покупка') ? 'buy' : 'sell',
        symbol: orderCard.querySelector('.order-action').textContent.split(' ')[1],
        platform_name: orderCard.querySelector('.order-exchange').textContent.trim(),
        quantity: parseFloat(orderCard.querySelector('.order-footer span:last-child').textContent),
        limit_price: parseFloat(orderCard.querySelector('.order-price').textContent.split(' ')[0]),
        price_currency: orderCard.querySelector('.order-price').textContent.split(' ')[1],
    };
    
    openCancelModal(orderId, orderData);
}

// Обновленные асинхронные функции
async function confirmExecuteOrder() {    
    if (!currentOrderId) {
        showNotification('error', 'Ошибка', 'ID ордера не указан');
        return;
    }
    
    closeExecuteModal();
    
    const formData = new FormData();
    formData.append('action', 'execute_limit_order');
    formData.append('order_id', String(currentOrderId)); // Принудительно преобразуем в строку
    
    for (let pair of formData.entries()) {
    }
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            
            const orderCard = document.getElementById(`order-${currentOrderId}`);
            if (orderCard) {
                orderCard.style.transition = 'all 0.3s ease';
                orderCard.style.opacity = '0';
                orderCard.style.transform = 'translateX(100px)';
                
                setTimeout(() => {
                    orderCard.remove();
                    
                    const ordersList = document.getElementById('limitOrdersList');
                    if (ordersList && ordersList.children.length === 0) {
                        ordersList.innerHTML = `
                            <div class="order-empty">
                                <i class="fas fa-clock"></i>
                                <p>Нет активных лимитных ордеров</p>
                                <button class="add-order-btn" onclick="openLimitOrderModal()">
                                    <i class="fas fa-plus-circle"></i> Создать ордер
                                </button>
                            </div>
                        `;
                    }
                    
                    const badge = document.querySelector('.card-orders .stat-badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        badge.textContent = currentCount - 1;
                    }
                }, 300);
            }
            
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось исполнить ордер');
    }
}

async function confirmCancelOrder() {    
    if (!currentOrderId) {
        showNotification('error', 'Ошибка', 'ID ордера не указан');
        return;
    }
    
    closeCancelModal();
    
    // Создаем FormData разными способами для теста
    const formData = new FormData();
    formData.append('action', 'cancel_limit_order');
    formData.append('order_id', currentOrderId); // Без преобразования в строку
    formData.append('order_id_str', String(currentOrderId)); // С преобразованием в строку
    formData.append('order_id_int', parseInt(currentOrderId)); // Как число
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            
            const orderCard = document.getElementById(`order-${currentOrderId}`);
            if (orderCard) {
                orderCard.style.transition = 'all 0.3s ease';
                orderCard.style.opacity = '0';
                orderCard.style.transform = 'translateX(-100px)';
                
                setTimeout(() => {
                    orderCard.remove();
                    
                    const ordersList = document.getElementById('limitOrdersList');
                    if (ordersList && ordersList.children.length === 0) {
                        ordersList.innerHTML = `
                            <div class="order-empty">
                                <i class="fas fa-clock"></i>
                                <p>Нет активных лимитных ордеров</p>
                                <button class="add-order-btn" onclick="openLimitOrderModal()">
                                    <i class="fas fa-plus-circle"></i> Создать ордер
                                </button>
                            </div>
                        `;
                    }
                    
                    const badge = document.querySelector('.card-orders .stat-badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        badge.textContent = currentCount - 1;
                    }
                }, 300);
            }
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отменить ордер');
    }
}

// ============================================================================
// УПРАВЛЕНИЕ ЗАМЕТКАМИ
// ============================================================================

let currentNoteId = null;
let currentDeleteNoteData = null;

// Функция открытия модального окна добавления заметки
function openAddNoteModal() {
    currentNoteId = null;
    document.getElementById('noteModalTitleText').textContent = 'Добавить заметку';
    document.getElementById('confirmNoteBtnText').textContent = 'Сохранить';
    document.getElementById('noteId').value = '';
    document.getElementById('noteTitle').value = '';
    document.getElementById('noteContent').value = '';
    document.getElementById('noteType').value = 'general';
    document.getElementById('noteReminderDate').value = '';
    document.getElementById('reminderDateGroup').style.display = 'none';
    
    // Сбрасываем активные кнопки типа
    document.querySelectorAll('.note-type-option').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector('.note-type-option[data-type="general"]').classList.add('active');
    
    document.getElementById('noteModal').classList.add('active');
}

// Функция открытия модального окна редактирования заметки
function openEditNoteModal(noteId, title, content, type, reminderDate) {
    currentNoteId = noteId;
    document.getElementById('noteModalTitleText').textContent = 'Редактировать заметку';
    document.getElementById('confirmNoteBtnText').textContent = 'Обновить';
    document.getElementById('noteId').value = noteId;
    document.getElementById('noteTitle').value = title || '';
    document.getElementById('noteContent').value = content;
    document.getElementById('noteType').value = type;
    document.getElementById('noteReminderDate').value = reminderDate || '';
    
    // Устанавливаем активную кнопку типа
    document.querySelectorAll('.note-type-option').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.querySelector(`.note-type-option[data-type="${type}"]`);
    if (activeBtn) activeBtn.classList.add('active');
    
    // Показываем поле даты если тип reminder
    document.getElementById('reminderDateGroup').style.display = type === 'reminder' ? 'block' : 'none';
    
    document.getElementById('noteModal').classList.add('active');
}

// Обработчик выбора типа заметки
document.querySelectorAll('.note-type-option').forEach(btn => {
    btn.addEventListener('click', function() {
        const type = this.dataset.type;
        document.querySelectorAll('.note-type-option').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('noteType').value = type;
        
        // Показываем поле даты только для напоминаний
        const reminderGroup = document.getElementById('reminderDateGroup');
        reminderGroup.style.display = type === 'reminder' ? 'block' : 'none';
    });
});

// Сохранение заметки
async function saveNote() {
    const noteId = document.getElementById('noteId').value;
    const title = document.getElementById('noteTitle').value;
    const content = document.getElementById('noteContent').value;
    const type = document.getElementById('noteType').value;
    const reminderDate = document.getElementById('noteReminderDate').value;
    
    if (!content.trim()) {
        showNotification('error', 'Ошибка', 'Введите содержание заметки');
        return;
    }
    
    const action = noteId ? 'update_note' : 'add_note';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('title', title);
    formData.append('content', content);
    formData.append('note_type', type);
    if (reminderDate) formData.append('reminder_date', reminderDate);
    if (noteId) formData.append('note_id', noteId);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeNoteModal();
            // Перезагружаем заметки
            loadNotes();
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось сохранить заметку');
    }
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.remove('active');
}

// Удаление заметки
async function deleteNote(noteId, noteTitle) {
    currentDeleteNoteData = { id: noteId, title: noteTitle };
    
    // Показываем информацию о заметке
    const infoDiv = document.getElementById('deleteNoteInfo');
    infoDiv.innerHTML = `
        <div style="display: flex; gap: 8px; align-items: center;">
            <i class="fas fa-sticky-note"></i>
            <strong>${escapeHtml(noteTitle || 'Без заголовка')}</strong>
        </div>
        <div style="font-size: 12px; color: var(--text-tertiary); margin-top: 4px;">
            Это действие нельзя отменить
        </div>
    `;
    
    document.getElementById('confirmDeleteModal').classList.add('active');
}

async function confirmDeleteNote() {
    if (!currentDeleteNoteData) return;
    
    // Находим элемент заметки в DOM (если он видим)
    const noteElement = document.querySelector(`.note-item[data-note-id="${currentDeleteNoteData.id}"], .archived-note-item[data-note-id="${currentDeleteNoteData.id}"]`);
    
    // Анимация удаления
    if (noteElement) {
        noteElement.style.transition = 'all 0.3s ease';
        noteElement.style.opacity = '0';
        noteElement.style.transform = 'translateX(-20px)';
        
        // Ждем анимацию, но не дольше 300ms
        await new Promise(resolve => setTimeout(resolve, 200));
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_note');
    formData.append('note_id', currentDeleteNoteData.id);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeConfirmDeleteModal();
            
            // Перезагружаем основные заметки
            await loadNotes();
            
            // Проверяем, открыто ли модальное окно архивов
            const archivedModal = document.getElementById('archivedNotesModal');
            if (archivedModal && archivedModal.classList.contains('active')) {
                // Если открыто, обновляем содержимое
                await loadArchivedNotes();
            }
        } else {
            showNotification('error', 'Ошибка', result.message);
            // Если ошибка, возвращаем элемент обратно
            if (noteElement) {
                noteElement.style.opacity = '1';
                noteElement.style.transform = 'translateX(0)';
            }
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось удалить заметку');
        // Если ошибка, возвращаем элемент обратно
        if (noteElement) {
            noteElement.style.opacity = '1';
            noteElement.style.transform = 'translateX(0)';
        }
    }
}

function closeConfirmDeleteModal() {
    document.getElementById('confirmDeleteModal').classList.remove('active');
    currentDeleteNoteData = null;
}

// Архивация/восстановление заметки
async function archiveNote(noteId, archive) {
    const formData = new FormData();
    formData.append('action', 'archive_note');
    formData.append('note_id', noteId);
    formData.append('archive', archive ? 1 : 0);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            
            // Перезагружаем основные заметки
            loadNotes();
            
            // Проверяем, открыто ли модальное окно архивов
            const archivedModal = document.getElementById('archivedNotesModal');
            if (archivedModal && archivedModal.classList.contains('active')) {
                // Если открыто, обновляем содержимое
                await loadArchivedNotes();
            } else {
                // Если не открыто, просто обновляем данные для следующего открытия
                // Можно очистить кэш или просто ничего не делать
            }
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось выполнить операцию');
    }
}

// Загрузка заметок
async function loadNotes() {
    const notesContainer = document.querySelector('.card-notes');
    const notesList = document.getElementById('notesList');
    
    if (!notesList) {
        const container = notesContainer.querySelector('.card-header').nextSibling;
        const newList = document.createElement('div');
        newList.id = 'notesList';
        notesContainer.insertBefore(newList, container);
    }
    
    const container = document.getElementById('notesList');
    if (container) container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_notes');
    formData.append('include_archived', 0); // 0 - только неархивированные
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && container) {
            displayNotes(result.notes, container, false);
        } else if (container) {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Не удалось загрузить заметки</div>';
        }
    } catch (error) {
        if (container) {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Ошибка загрузки заметок</div>';
        }
    }
}

// Загрузка архивных заметок
async function loadArchivedNotes() {
    const container = document.getElementById('archivedNotesList');
    if (!container) return;
    
    container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_notes');
    formData.append('include_archived', 1); // 1 - только архивированные
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            displayArchivedNotes(result.notes, container);
        } else {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Не удалось загрузить архивные заметки</div>';
        }
    } catch (error) {
        container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Ошибка загрузки</div>';
    }
}

// Отображение заметок
function displayNotes(notes, container, isArchived) {
    if (!notes || notes.length === 0) {
        container.innerHTML = `
                <div style="margin-bottom: 15px; text-align: center;">
                    <button class="add-order-btn" onclick="openAddNoteModal()" style="margin-top: 10px;">
                        <i class="fas fa-plus-circle"></i> Создать заметку
                    </button>
                </div>
                <div class="order-empty">
                    <i class="fas fa-sticky-note"></i>
                    <p>Нет заметок</p>
                </div>
        `;
        return;
    }
    
    let html = '';
    // Добавляем кнопку создания заметки
    html += `
        <div style="margin-bottom: 15px; text-align: center;">
            <button class="add-order-btn" onclick="openAddNoteModal()" style="margin-top: 10px;">
                <i class="fas fa-plus-circle"></i> Создать заметку
            </button>
        </div>
    `;
    notes.forEach(note => {
        const noteTypeClass = note.note_type || 'general';
        const icon = getNoteIcon(note.note_type);
        const date = new Date(note.created_at).toLocaleDateString('ru-RU');
        const reminderIcon = note.reminder_date ? `📅 ${new Date(note.reminder_date).toLocaleDateString('ru-RU')}` : '';
        
        html += `
            <div class="note-item ${noteTypeClass}" data-note-id="${note.id}">
                <div class="note-header">
                    <div>
                        ${note.title ? `<div class="note-title">${escapeHtml(note.title)}</div>` : ''}
                        <div class="note-date">
                            <i class="far fa-calendar-alt"></i> ${date}
                            ${reminderIcon ? `<span style="margin-left: 8px;">${reminderIcon}</span>` : ''}
                        </div>
                    </div>
                </div>
                <div class="note-content">${escapeHtml(note.content)}</div>
                <div class="note-actions">
                    <button class="note-action-btn edit" onclick="openEditNoteModal(${note.id}, '${escapeHtml(note.title || '').replace(/'/g, "\\'")}', '${escapeHtml(note.content).replace(/'/g, "\\'")}', '${note.note_type}', '${note.reminder_date || ''}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="note-action-btn archive" onclick="archiveNote(${note.id}, true)">
                        <i class="fas fa-archive"></i>
                    </button>
                    <button class="note-action-btn delete" onclick="deleteNote(${note.id}, '${escapeHtml(note.title || 'Без заголовка').replace(/'/g, "\\'")}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Отображение архивных заметок
function displayArchivedNotes(notes, container) {
    if (!notes || notes.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-archive" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">Нет архивных заметок</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    notes.forEach(note => {
        const date = new Date(note.created_at).toLocaleDateString('ru-RU');
        const archivedDate = note.updated_at ? new Date(note.updated_at).toLocaleDateString('ru-RU') : date;
        
        html += `
            <div class="archived-note-item">
                <div class="archived-note-header">
                    <div class="archived-note-title">
                        ${note.title ? escapeHtml(note.title) : 'Без заголовка'}
                    </div>
                    <div class="archived-note-date">
                        <i class="far fa-calendar-alt"></i> ${date}
                        <span style="margin-left: 8px;">📦 ${archivedDate}</span>
                    </div>
                </div>
                <div class="archived-note-content">
                    ${escapeHtml(note.content)}
                </div>
                <div class="archived-note-actions">
                    <button class="note-action-btn restore" onclick="archiveNote(${note.id}, false)">
                        <i class="fas fa-undo-alt"></i> Восстановить
                    </button>
                    <button class="note-action-btn delete" onclick="deleteNote(${note.id}, '${escapeHtml(note.title || 'Без заголовка').replace(/'/g, "\\'")}')">
                        <i class="fas fa-trash-alt"></i> Удалить
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Получение иконки для типа заметки
function getNoteIcon(type) {
    switch(type) {
        case 'important': return '⚠️';
        case 'reminder': return '📌';
        case 'idea': return '💡';
        default: return '📝';
    }
}

// Открытие модального окна архивных заметок
function openArchivedNotesModal() {
    const modal = document.getElementById('archivedNotesModal');
    if (!modal) return;
    
    modal.classList.add('active');
    
    // Принудительно перезагружаем архивные заметки при открытии
    loadArchivedNotes();
}

function closeArchivedNotesModal() {
    document.getElementById('archivedNotesModal').classList.remove('active');
}

// Функция экранирования HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================================
// ДАННЫЕ ДЛЯ СЕТЕЙ
// ============================================================================

// Сети из базы данных
const networksFromDB = <?= $networks_json ?>;

// Хранилище всех сетей (загружается из БД)
let allNetworks = [...networksFromDB];

// Предустановленные сети (для быстрого доступа - берем из БД)
const predefinedNetworks = networksFromDB;

// Переменные для выбранных сетей
let selectedFromNetwork = { name: '' };
let selectedToNetwork = { name: '' };

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА СЕТИ (АНАЛОГИЧНЫЕ ВЫБОРУ АКТИВОВ)
// ============================================================================

// Функция для получения сети по имени
function getNetworkByName(name) {
    return allNetworks.find(n => n.name === name);
}

// Функция для добавления новой сети (с сохранением в БД)
async function addNetworkToDatabase(networkData) {
    const formData = new FormData();
    formData.append('action', 'add_network');
    formData.append('name', networkData.name);
    formData.append('icon', networkData.icon);
    formData.append('color', networkData.color);
    formData.append('full_name', networkData.full_name);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && result.network_id) {
            // Добавляем в локальный массив
            allNetworks.push({
                id: result.network_id,
                name: networkData.name,
                icon: networkData.icon,
                color: networkData.color,
                full_name: networkData.full_name
            });
            return true;
        }
        return false;
    } catch (error) {
        return false;
    }
}

function openNetworkModal(context, currentNetwork = '') {
    setModalContext('transfer', 'network', context);
    
    const modalTitle = document.querySelector('#networkSelectModal .modal-header h2');
    let titleText = 'Выберите сеть';
    if (context === 'from') {
        titleText = 'Выберите сеть отправителя';
    } else if (context === 'to') {
        titleText = 'Выберите сеть получателя';
    }
    modalTitle.innerHTML = `<i class="fas fa-network-wired" style="color: #ff9f4a;"></i> ${titleText}`;
    
    const modal = document.getElementById('networkSelectModal');
    if (modal) {
        filterNetworksForSelect('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('networkSearch')?.focus();
        }, 100);
    }
}

function closeNetworkModal() {
    const modal = document.getElementById('networkSelectModal');
    if (modal) {
        modal.classList.remove('active');
        document.getElementById('networkSearch').value = '';
    }
}

function filterNetworksForSelect(searchText) {
    const listContainer = document.getElementById('allNetworksList');
    if (!listContainer) return;
    
    const searchTextLower = searchText.toLowerCase().trim();
    const originalSearchText = searchText.trim();
    
    let networksToShow = allNetworks;
    if (searchTextLower) {
        networksToShow = allNetworks.filter(n => 
            n.name.toLowerCase().includes(searchTextLower) || 
            (n.full_name && n.full_name.toLowerCase().includes(searchTextLower))
        );
    }
    
    // Сортируем: сначала предустановленные по порядку, потом пользовательские
    networksToShow.sort((a, b) => {
        const aIndex = networksFromDB.findIndex(p => p.name === a.name);
        const bIndex = networksFromDB.findIndex(p => p.name === b.name);
        if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex;
        if (aIndex !== -1) return -1;
        if (bIndex !== -1) return 1;
        return a.name.localeCompare(b.name);
    });
    
    if (networksToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `
            <div onclick="addNewNetworkFromModal('${originalSearchText.replace(/'/g, "\\'")}')" 
                 style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: #ff9f4a; transition: all 0.2s;" 
                 onmouseover="this.style.background='#f0f3f7'" 
                 onmouseout="this.style.background='transparent'">
                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i>
                Добавить сеть "${originalSearchText.toUpperCase()}"
            </div>
        `;
        return;
    }
    
    listContainer.innerHTML = networksToShow.map(network => {
        let iconHtml = `<i class="${network.icon}"></i>`;
        
        return `
            <div onclick="selectNetworkFromModal('${network.name.replace(/'/g, "\\'")}')" 
                 style="padding: 12px; cursor: pointer; border-radius: 10px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; border: 1px solid transparent;"
                 onmouseover="this.style.background='var(--bg-tertiary)'; this.style.borderColor='#e0e6ed'" 
                 onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'">
                <div style="width: 36px; height: 36px; background: ${network.color}20; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${network.color};">
                    ${iconHtml}
                </div>
                <div style="flex: 1;">
                    <div class="asset-symbol">${network.name}</div>
                    <div style="font-size: 12px; color: #6b7a8f;">${network.full_name || network.name}</div>
                </div>
                <i class="fas fa-chevron-right" style="color: #95a5a6; font-size: 12px;"></i>
            </div>
        `;
    }).join('');
}

function selectNetworkFromModal(networkName) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    
    if (context === 'transfer') {
        if (subMode === 'from') {
            selectFromNetwork(networkName);
        } else if (subMode === 'to') {
            selectToNetwork(networkName);
        }
    } else if (context === 'trade') {
        // Для модального окна покупки/продажи
        selectTradeNetwork(networkName);
    }
    
    closeNetworkModal();
}

function selectFromNetwork(name) {
    selectedFromNetwork = { name };
    
    const display = document.getElementById('selectedFromNetworkDisplay');
    if (display) {
        display.textContent = name;
    }
    
    const hiddenInput = document.getElementById('transferNetworkFrom');
    if (hiddenInput) {
        hiddenInput.value = name;
    }
}

function selectToNetwork(name) {
    selectedToNetwork = { name };
    
    const display = document.getElementById('selectedToNetworkDisplay');
    if (display) {
        display.textContent = name;
    }
    
    const hiddenInput = document.getElementById('transferNetworkTo');
    if (hiddenInput) {
        hiddenInput.value = name;
    }
}

function addNewNetworkFromModal(networkName) {
    if (!networkName) return;
    
    const newNetworkName = networkName.trim().toUpperCase();
    
    // Проверяем, существует ли уже
    const exists = allNetworks.some(n => n.name === newNetworkName);
    if (exists) {
        selectNetworkFromModal(newNetworkName);
        return;
    }
    
    openAddNetworkModal(newNetworkName);
}

function openAddNetworkModal(networkName) {
    const modal = document.getElementById('addNetworkModal');
    const nameInput = document.getElementById('newNetworkName');
    const fullNameInput = document.getElementById('newNetworkFullName');
    const colorInput = document.getElementById('newNetworkColor');
    
    if (!nameInput) return;
    
    nameInput.value = networkName.toUpperCase();
    if (fullNameInput) fullNameInput.value = '';
    if (colorInput) colorInput.value = '#ff9f4a';
    
    // Обновляем превью
    updateNetworkPreview(networkName.toUpperCase(), '');
    
    // Добавляем обработчики для превью
    if (fullNameInput) {
        fullNameInput.oninput = function() {
            updateNetworkPreview(nameInput.value, this.value);
        };
    }
    nameInput.oninput = function() {
        updateNetworkPreview(this.value, fullNameInput ? fullNameInput.value : '');
    };
    
    // Закрываем модальное окно выбора сети
    closeNetworkModal();
    
    if (modal) {
        modal.classList.add('active');
        
        setTimeout(() => {
            if (fullNameInput) fullNameInput.focus();
        }, 100);
    }
}

function updateNetworkPreview(name, fullName) {
    const previewIcon = document.getElementById('previewNetworkIcon');
    const previewName = document.getElementById('previewNetworkName');
    const previewFullName = document.getElementById('previewNetworkFullName');
    
    if (!previewName) return;
    
    // Определяем иконку по названию
    let icon = 'fas fa-network-wired';
    const upperName = name.toUpperCase();
    if (upperName.includes('ERC')) icon = 'fab fa-ethereum';
    else if (upperName.includes('BEP')) icon = 'fas fa-bolt';
    else if (upperName.includes('TRC')) icon = 'fab fa-t';
    else if (upperName === 'SOL') icon = 'fas fa-sun';
    else if (upperName === 'BTC') icon = 'fab fa-bitcoin';
    
    // Обновляем иконку
    if (previewIcon) {
        previewIcon.innerHTML = `<i class="${icon}"></i>`;
    }
    
    // Обновляем название
    previewName.textContent = name || 'Название сети';
    previewFullName.textContent = fullName || 'Полное название';
}

function closeAddNetworkModal() {
    const modal = document.getElementById('addNetworkModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

async function saveNewNetwork() {
    const networkName = document.getElementById('newNetworkName').value.toUpperCase();
    const networkFullName = document.getElementById('newNetworkFullName').value.trim();
    const networkColor = document.getElementById('newNetworkColor').value;
    
    if (!networkName) {
        showNotification('error', 'Ошибка', 'Введите аббревиатуру сети');
        return;
    }
    
    // Если полное название не указано, используем аббревиатуру
    const fullName = networkFullName || networkName;
    
    // Определяем иконку по названию
    let icon = 'fas fa-network-wired';
    const upperName = networkName.toUpperCase();
    if (upperName.includes('ERC')) icon = 'fab fa-ethereum';
    else if (upperName.includes('BEP')) icon = 'fas fa-bolt';
    else if (upperName.includes('TRC')) icon = 'fab fa-t';
    else if (upperName === 'SOL') icon = 'fas fa-sun';
    else if (upperName === 'BTC') icon = 'fab fa-bitcoin';
    
    // Сохраняем в базу данных
    const result = await addNetworkToDatabase({
        name: networkName,
        icon: icon,
        color: networkColor,
        full_name: fullName
    });
    
    if (result) {
        // Выбираем сеть в зависимости от контекста
        const context = currentModalContext.source;
        
        if (context === 'transfer') {
            if (currentModalContext.subMode === 'from') {
                selectFromNetwork(networkName);
            } else if (currentModalContext.subMode === 'to') {
                selectToNetwork(networkName);
            }
        } else if (context === 'trade') {
            selectTradeNetwork(networkName);
        }
        
        closeAddNetworkModal();
        showNotification('success', 'Успешно', `Сеть ${networkName} добавлена`);
    } else {
        showNotification('error', 'Ошибка', 'Не удалось добавить сеть');
    }
}

// ============================================================================
// ПОПУЛЯРНЫЕ СЕТИ ДЛЯ БЫСТРОГО ВЫБОРА
// ============================================================================

function renderPopularNetworksForTransfer() {
    const container = document.getElementById('transferPopularNetworks');
    if (!container) return;
    
    // Берем первые 6 сетей из БД для быстрого выбора
    const popular = networksFromDB.slice(0, 6);
    
    container.innerHTML = popular.map(network => `
        <button type="button" class="quick-asset-btn" onclick="selectFromNetwork('${network.name}')" 
                style="background: ${network.color}20; border-color: ${network.color}; color: ${network.color};">
            <i class="${network.icon}"></i> ${network.name}
        </button>
    `).join('');
}

// ============================================================================
// ПОПУЛЯРНЫЕ СЕТИ ДЛЯ БЫСТРОГО ВЫБОРА В ТОРГОВЛЕ
// ============================================================================

function renderPopularNetworksForTrade() {
    const container = document.getElementById('tradePopularNetworks');
    if (!container) return;
    
    // Берем первые 6 сетей из БД для быстрого выбора
    const popular = networksFromDB.slice(0, 6);
    
    container.innerHTML = popular.map(network => `
        <button type="button" class="quick-asset-btn" onclick="selectTradeNetwork('${network.name}')" 
                style="background: ${network.color}20; border-color: ${network.color}; color: ${network.color};">
            <i class="${network.icon}"></i> ${network.name}
        </button>
    `).join('');
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА СЕТИ В ТОРГОВЛЕ
// ============================================================================

function selectTradeNetwork(name) {
    selectedTradeNetwork = { name };
    
    const display = document.getElementById('selectedTradeNetworkDisplay');
    if (display) {
        display.textContent = name;
    }
    
    const hiddenInput = document.getElementById('tradeNetwork');
    if (hiddenInput) {
        hiddenInput.value = name;
    }
}

function openTradeNetworkModal() {
    setModalContext('trade', 'network');
    
    const modalTitle = document.querySelector('#networkSelectModal .modal-header h2');
    modalTitle.innerHTML = '<i class="fas fa-network-wired" style="color: #ff9f4a;"></i> Выберите сеть';
    
    const modal = document.getElementById('networkSelectModal');
    if (modal) {
        filterNetworksForSelect('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('networkSearch')?.focus();
        }, 100);
    }
}

// ============================================================================
// МОДАЛЬНОЕ ОКНО АКТИВОВ ПЛОЩАДКИ
// ============================================================================

// Данные по активам площадок из PHP
const platformAssetsData = <?= $platform_assets_json ?>;

function openPlatformAssetsModal(platformId, platformName) {
    const modal = document.getElementById('platformAssetsModal');
    const titleSpan = document.getElementById('platformAssetsName');
    const body = document.getElementById('platformAssetsBody');
    
    if (!modal || !body) return;
    
    // Устанавливаем заголовок
    titleSpan.textContent = platformName;
    
    // Показываем загрузку
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    
    modal.classList.add('active');
    
    // Получаем активы для этой площадки
    const platformData = platformAssetsData[platformId];
    
    if (!platformData || !platformData.assets || platformData.assets.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">На площадке "${platformName}" нет активов</p>
            </div>
        `;
        return;
    }
    
    // Сортируем активы по стоимости (от большей к меньшей)
    const assets = [...platformData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    
    // Рассчитываем общую стоимость
    let totalValueUsd = 0;
    
    assets.forEach(asset => {
        totalValueUsd += parseFloat(asset.value_usd) || 0;
    });
    
    // Получаем курс USD/RUB (используем глобальную переменную из PHP)
    const usdRubRate = <?= $usd_rub_rate ?>;
    const totalValueRub = totalValueUsd * usdRubRate;
    
    // Форматируем общую стоимость USD с пробелами только в целой части
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    
    // Форматируем общую стоимость RUB с пробелами только в целой части
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Формируем HTML
    let html = `
        <table class="platform-assets-table">
            <thead>
                <tr>
                    <th>Актив</th>
                    <th style="text-align: right;">Количество</th>
                    <th style="text-align: right;">Средняя цена</th>
                    <th style="text-align: right;">Стоимость</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        // Преобразуем quantity в число для форматирования
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        // ========== ФОРМАТИРОВАНИЕ КОЛИЧЕСТВА ==========
        let quantityFormatted = '';
        if (asset.asset_type === 'crypto') {
            if (Math.floor(quantityNum) === quantityNum) {
                quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
            } else {
                // Форматируем без пробелов в дробной части
                let str = quantityNum.toFixed(6).replace(/\.?0+$/, '');
                let parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            }
        } else if (asset.symbol === 'RUB' || asset.symbol === 'USD' || asset.symbol === 'EUR') {
            // Для фиата - 2 знака после запятой, пробелы только в целой части
            let str = quantityNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        } else {
            // Для остальных - до 4 знаков
            let str = quantityNum.toFixed(4).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        }
        
        // ========== ФОРМАТИРОВАНИЕ СРЕДНЕЙ ЦЕНЫ ==========
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            if (asset.asset_type === 'crypto' && asset.symbol !== 'USDT') {
                // Для криптовалют (кроме USDT) - до 4 знаков
                let str = avgPriceNum.toFixed(4).replace(/\.?0+$/, '');
                let parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            } else {
                // Для фиата и USDT - 2 знака
                let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
                let parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            }
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        // ========== ФОРМАТИРОВАНИЕ СТОИМОСТИ ==========
        const valueRubNum = valueUsdNum * usdRubRate;
        
        // Форматируем USD с пробелами только в целой части
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        
        // Форматируем RUB с пробелами только в целой части
        let rubStr = Math.round(valueRubNum).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        
        // Определяем иконку для актива
        let assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: var(--bg-tertiary, #f0f3f7); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div class="platform-assets-symbol">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.asset_name || asset.symbol}</div>
                        </div>
                    </div>
                </td>
                <td class="platform-assets-quantity">${quantityFormatted}</td>
                <td class="platform-assets-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td class="platform-assets-value">${valueFormatted}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
        
        <div class="platform-assets-summary">
            <div class="platform-assets-summary-row">
                <span style="font-weight: 600;">Всего активов:</span>
                <span style="font-weight: 600;">${assets.length}</span>
            </div>
            <div class="platform-assets-summary-row">
                <span>Общая стоимость:</span>
                <span class="platform-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span>
            </div>
        </div>
    `;
    
    body.innerHTML = html;
}

// Функция для получения иконки актива
function getAssetIcon(symbol) {
    const icons = {
        'BTC': { icon: 'fab fa-bitcoin', color: '#f7931a' },
        'ETH': { icon: 'fab fa-ethereum', color: '#627eea' },
        'USDT': { icon: 'fas fa-coins', color: '#26a17b' },
        'SOL': { icon: 'fas fa-sun', color: '#14f195' },
        'BNB': { icon: 'fas fa-chart-line', color: '#f3ba2f' },
        'RUB': { icon: 'fas fa-ruble-sign', color: '#1a5cff' },
        'USD': { icon: 'fas fa-dollar-sign', color: '#00a86b' },
        'EUR': { icon: 'fas fa-euro-sign', color: '#2ecc71' },
    };
    return icons[symbol] || { icon: 'fas fa-chart-line', color: '#6b7a8f' };
}

function closePlatformAssetsModal() {
    const modal = document.getElementById('platformAssetsModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// ============================================================================
// МОДАЛЬНОЕ ОКНО АКТИВОВ СЕТИ
// ============================================================================

// Данные по активам сетей из PHP
const networkAssetsData = <?= $network_assets_json ?>;

function openNetworkAssetsModal(networkName) {
    const modal = document.getElementById('networkAssetsModal');
    const titleSpan = document.getElementById('networkAssetsName');
    const body = document.getElementById('networkAssetsBody');
    
    if (!modal || !body) return;
    
    // Устанавливаем заголовок
    titleSpan.textContent = networkName;
    
    // Показываем загрузку
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    
    modal.classList.add('active');
    
    // Получаем активы для этой сети
    const networkData = networkAssetsData[networkName];
    
    if (!networkData || !networkData.assets || networkData.assets.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">В сети "${networkName}" нет активов</p>
            </div>
        `;
        return;
    }
    
    // Сортируем активы по стоимости (от большей к меньшей)
    const assets = [...networkData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    
    // Рассчитываем общую стоимость
    let totalValueUsd = networkData.total_value_usd;
    
    // Получаем курс USD/RUB
    const usdRubRate = <?= $usd_rub_rate ?>;
    const totalValueRub = totalValueUsd * usdRubRate;
    
    // Форматируем общую стоимость
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Формируем HTML
    let html = `
        <style>
            .network-assets-table {
                width: 100%;
                border-collapse: collapse;
            }
            .network-assets-table th {
                text-align: left;
                padding: 12px 8px;
                background: var(--bg-tertiary, #f8fafd);
                font-weight: 600;
                font-size: 13px;
                color: var(--text-secondary, #6b7a8f);
                border-bottom: 2px solid var(--border-color, #edf2f7);
            }
            .network-assets-table td {
                padding: 12px 8px;
                border-bottom: 1px solid var(--border-color, #edf2f7);
                vertical-align: middle;
            }
            .network-assets-table tr:hover {
                background: var(--bg-tertiary, #f8fafd);
            }
            .network-assets-symbol {
                font-weight: 600;
                color: var(--text-primary, #2c3e50);
            }
            .network-assets-quantity {
                font-family: monospace;
                text-align: right;
            }
            .network-assets-value {
                text-align: right;
                font-weight: 500;
                color: #ff9f4a;
            }
            .network-assets-summary {
                background: var(--bg-tertiary, #f0f3f7);
                border-radius: 12px;
                padding: 16px;
                margin-top: 16px;
            }
            .network-assets-summary-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
            }
            .network-assets-summary-row:first-child {
                border-bottom: 1px solid var(--border-color, #e0e6ed);
                margin-bottom: 8px;
                padding-bottom: 12px;
            }
            .network-assets-total {
                font-weight: 700;
                font-size: 18px;
                color: #ff9f4a;
            }
            .dark-theme .network-assets-summary {
                background: var(--bg-tertiary);
            }
        </style>
        
        <table class="network-assets-table">
            <thead>
                <tr>
                    <th>Актив</th>
                    <th style="text-align: right;">Количество</th>
                    <th style="text-align: right;">Средняя цена</th>
                    <th style="text-align: right;">Стоимость</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        // ========== ФОРМАТИРОВАНИЕ КОЛИЧЕСТВА (МАКСИМУМ 8 ЗНАКОВ) ==========
        let quantityFormatted = '';
        
        // Преобразуем число в строку
        let quantityStr = quantityNum.toString();
        
        // Если число в экспоненциальной форме
        if (quantityStr.includes('e')) {
            quantityStr = quantityNum.toFixed(12);
        }
        
        // Разделяем целую и дробную части
        let quantityParts = quantityStr.split('.');
        
        // Форматируем целую часть с пробелами
        quantityParts[0] = quantityParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        if (quantityParts.length > 1 && quantityParts[1]) {
            // Берем дробную часть, ограничиваем до 8 знаков, убираем лишние нули в конце
            let decimalPart = quantityParts[1];
            
            // Ограничиваем до 8 знаков
            if (decimalPart.length > 8) {
                decimalPart = decimalPart.substring(0, 8);
            }
            
            // Убираем лишние нули в конце (но не все, если это не целое число)
            decimalPart = decimalPart.replace(/0+$/, '');
            
            if (decimalPart.length > 0) {
                quantityFormatted = quantityParts[0] + '.' + decimalPart;
            } else {
                quantityFormatted = quantityParts[0];
            }
        } else {
            quantityFormatted = quantityParts[0];
        }
        
        // ========== ФОРМАТИРОВАНИЕ СРЕДНЕЙ ЦЕНЫ ==========
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            // Для цены оставляем до 6 знаков после запятой
            let priceStr = avgPriceNum.toFixed(8).replace(/\.?0+$/, '');
            let priceParts = priceStr.split('.');
            priceParts[0] = priceParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = priceParts[0] + (priceParts[1] ? '.' + priceParts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        // ========== ФОРМАТИРОВАНИЕ СТОИМОСТИ ==========
        const valueRubNum = valueUsdNum * usdRubRate;
        
        // Форматируем USD
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        
        // Форматируем RUB
        let rubStr = Math.round(valueRubNum).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        
        // Определяем иконку для актива
        let assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: var(--bg-tertiary, #f0f3f7); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div class="network-assets-symbol">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </td>
                <td class="network-assets-quantity" style="font-family: monospace; white-space: nowrap;">${quantityFormatted}</td>
                <td class="network-assets-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td class="network-assets-value">${valueFormatted}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
        
        <div class="network-assets-summary">
            <div class="network-assets-summary-row">
                <span style="font-weight: 600;">Всего активов:</span>
                <span style="font-weight: 600;">${assets.length}</span>
            </div>
            <div class="network-assets-summary-row">
                <span>Общая стоимость в сети ${networkName}:</span>
                <span class="network-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span>
            </div>
        </div>
    `;
    
    body.innerHTML = html;
}

function closeNetworkAssetsModal() {
    const modal = document.getElementById('networkAssetsModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Добавляем обработчики для модального окна активов сети
document.getElementById('closeNetworkAssetsModalBtn')?.addEventListener('click', closeNetworkAssetsModal);
document.getElementById('closeNetworkAssetsModalFooterBtn')?.addEventListener('click', closeNetworkAssetsModal);

// Закрытие по клику на overlay
const networkAssetsModal = document.getElementById('networkAssetsModal');
if (networkAssetsModal) {
    networkAssetsModal.addEventListener('click', (e) => {
        if (e.target === networkAssetsModal) {
            closeNetworkAssetsModal();
        }
    });
}

// ============================================================================
// МОДАЛЬНОЕ ОКНО АКТИВОВ ПО СЕКТОРАМ
// ============================================================================

// Данные по активам секторов из PHP
const sectorAssetsData = <?= $sector_assets_json ?>;

function openSectorAssetsModal(sectorName, displayName) {
    const modal = document.getElementById('sectorAssetsModal');
    const titleSpan = document.getElementById('sectorAssetsName');
    const body = document.getElementById('sectorAssetsBody');
    
    if (!modal || !body) return;
    
    // Устанавливаем заголовок
    titleSpan.textContent = displayName;
    
    // Показываем загрузку
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    
    modal.classList.add('active');
    
    // Получаем активы для этого сектора
    const sectorData = sectorAssetsData[sectorName];
    
    if (!sectorData || !sectorData.assets || sectorData.assets.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">В секторе "${displayName}" нет активов</p>
            </div>
        `;
        return;
    }
    
    // Сортируем активы по стоимости (от большей к меньшей)
    const assets = [...sectorData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    
    // Рассчитываем общую стоимость
    let totalValueUsd = sectorData.total_value_usd;
    
    // Получаем курс USD/RUB
    const usdRubRate = <?= $usd_rub_rate ?>;
    const totalValueRub = totalValueUsd * usdRubRate;
    
    // Форматируем общую стоимость
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Формируем HTML
    let html = `
        <style>
            .sector-assets-table {
                width: 100%;
                border-collapse: collapse;
            }
            .sector-assets-table th {
                text-align: left;
                padding: 12px 8px;
                background: var(--bg-tertiary, #f8fafd);
                font-weight: 600;
                font-size: 13px;
                color: var(--text-secondary, #6b7a8f);
                border-bottom: 2px solid var(--border-color, #edf2f7);
            }
            .sector-assets-table td {
                padding: 12px 8px;
                border-bottom: 1px solid var(--border-color, #edf2f7);
                vertical-align: middle;
            }
            .sector-assets-table tr:hover {
                background: var(--bg-tertiary, #f8fafd);
            }
            .sector-assets-symbol {
                font-weight: 600;
                color: var(--text-primary, #2c3e50);
            }
            .sector-assets-quantity {
                font-family: monospace;
                text-align: right;
            }
            .sector-assets-value {
                text-align: right;
                font-weight: 500;
                color: #4a9eff;
            }
            .sector-assets-summary {
                background: var(--bg-tertiary, #f0f3f7);
                border-radius: 12px;
                padding: 16px;
                margin-top: 16px;
            }
            .sector-assets-summary-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
            }
            .sector-assets-summary-row:first-child {
                border-bottom: 1px solid var(--border-color, #e0e6ed);
                margin-bottom: 8px;
                padding-bottom: 12px;
            }
            .sector-assets-total {
                font-weight: 700;
                font-size: 18px;
                color: #4a9eff;
            }
            .dark-theme .sector-assets-summary {
                background: var(--bg-tertiary);
            }
        </style>
        
        <table class="sector-assets-table">
            <thead>
                <tr>
                    <th>Актив</th>
                    <th style="text-align: right;">Количество</th>
                    <th style="text-align: right;">Средняя цена</th>
                    <th style="text-align: right;">Стоимость</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        // Форматирование количества
        let quantityFormatted = '';
        if (asset.asset_type === 'crypto') {
            if (Math.floor(quantityNum) === quantityNum) {
                quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
            } else {
                let str = quantityNum.toFixed(6).replace(/\.?0+$/, '');
                let parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            }
        } else {
            let str = quantityNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        }
        
        // Форматирование средней цены
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        // Форматирование стоимости
        const valueRubNum = valueUsdNum * usdRubRate;
        
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        
        let rubStr = Math.round(valueRubNum).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        
        // Определяем иконку для актива
        let assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: var(--bg-tertiary, #f0f3f7); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div class="sector-assets-symbol">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </td>
                <td class="sector-assets-quantity">${quantityFormatted}</td>
                <td class="sector-assets-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td class="sector-assets-value">${valueFormatted}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
        
        <div class="sector-assets-summary">
            <div class="sector-assets-summary-row">
                <span style="font-weight: 600;">Всего активов:</span>
                <span style="font-weight: 600;">${assets.length}</span>
            </div>
            <div class="sector-assets-summary-row">
                <span>Общая стоимость в секторе ${displayName}:</span>
                <span class="sector-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span>
            </div>
        </div>
    `;
    
    body.innerHTML = html;
}

function closeSectorAssetsModal() {
    const modal = document.getElementById('sectorAssetsModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// ============================================================================
// МОДАЛЬНОЕ ОКНО АКТИВОВ ПО ТИПАМ КРИПТОВАЛЮТ
// ============================================================================

// Данные по активам типов криптовалют из PHP
const cryptoTypeAssetsData = <?= $crypto_type_assets_json ?>;

function openCryptoTypeModal(type, displayName) {
    const modal = document.getElementById('cryptoTypeModal');
    const titleSpan = document.getElementById('cryptoTypeName');
    const body = document.getElementById('cryptoTypeBody');
    
    if (!modal || !body) return;
    
    // Устанавливаем заголовок
    titleSpan.textContent = displayName;
    
    // Показываем загрузку
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    
    modal.classList.add('active');
    
    // Получаем активы для этого типа
    const typeData = cryptoTypeAssetsData[type];
    
    if (!typeData || !typeData.assets || typeData.assets.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">В категории "${displayName}" нет активов</p>
            </div>
        `;
        return;
    }
    
    // Сортируем активы по стоимости (от большей к меньшей)
    const assets = [...typeData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    
    // Рассчитываем общую стоимость
    let totalValueUsd = typeData.total_value_usd;
    
    // Получаем курс USD/RUB
    const usdRubRate = <?= $usd_rub_rate ?>;
    const totalValueRub = totalValueUsd * usdRubRate;
    
    // Форматируем общую стоимость
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Формируем HTML
    let html = `
        <style>
            .crypto-type-table {
                width: 100%;
                border-collapse: collapse;
            }
            .crypto-type-table th {
                text-align: left;
                padding: 12px 8px;
                background: var(--bg-tertiary, #f8fafd);
                font-weight: 600;
                font-size: 13px;
                color: var(--text-secondary, #6b7a8f);
                border-bottom: 2px solid var(--border-color, #edf2f7);
            }
            .crypto-type-table td {
                padding: 12px 8px;
                border-bottom: 1px solid var(--border-color, #edf2f7);
                vertical-align: middle;
            }
            .crypto-type-table tr:hover {
                background: var(--bg-tertiary, #f8fafd);
            }
            .crypto-type-symbol {
                font-weight: 600;
                color: var(--text-primary, #2c3e50);
            }
            .crypto-type-quantity {
                font-family: monospace;
                text-align: right;
            }
            .crypto-type-value {
                text-align: right;
                font-weight: 500;
                color: #ff9f4a;
            }
            .crypto-type-summary {
                background: var(--bg-tertiary, #f0f3f7);
                border-radius: 12px;
                padding: 16px;
                margin-top: 16px;
            }
            .crypto-type-summary-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
            }
            .crypto-type-summary-row:first-child {
                border-bottom: 1px solid var(--border-color, #e0e6ed);
                margin-bottom: 8px;
                padding-bottom: 12px;
            }
            .crypto-type-total {
                font-weight: 700;
                font-size: 18px;
                color: #ff9f4a;
            }
            .dark-theme .crypto-type-summary {
                background: var(--bg-tertiary);
            }
        </style>
        
        <table class="crypto-type-table">
            <thead>
                <tr>
                    <th>Актив</th>
                    <th style="text-align: right;">Количество</th>
                    <th style="text-align: right;">Средняя цена</th>
                    <th style="text-align: right;">Стоимость</th>
                 </tr>
            </thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        // Форматирование количества
        let quantityFormatted = '';
        if (Math.floor(quantityNum) === quantityNum) {
            quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
        } else {
            let str = quantityNum.toFixed(6).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        }
        
        // Форматирование средней цены
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        // Форматирование стоимости
        const valueRubNum = valueUsdNum * usdRubRate;
        
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        
        let rubStr = Math.round(valueRubNum).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        
        // Определяем иконку для актива
        let assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: var(--bg-tertiary, #f0f3f7); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div class="crypto-type-symbol">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </td>
                <td class="crypto-type-quantity">${quantityFormatted}</td>
                <td class="crypto-type-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td class="crypto-type-value">${valueFormatted}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
        
        <div class="crypto-type-summary">
            <div class="crypto-type-summary-row">
                <span style="font-weight: 600;">Всего активов:</span>
                <span style="font-weight: 600;">${assets.length}</span>
            </div>
            <div class="crypto-type-summary-row">
                <span>Общая стоимость в категории ${displayName}:</span>
                <span class="crypto-type-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span>
            </div>
        </div>
    `;
    
    body.innerHTML = html;
}

function closeCryptoTypeModal() {
    const modal = document.getElementById('cryptoTypeModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Функция для загрузки истории покупок при продаже
async function loadPurchaseHistoryForSell(assetId, platformId) {
    if (!assetId || !platformId) return;
    
    const historyBlock = document.getElementById('sellPurchaseHistory');
    const purchaseList = document.getElementById('sellPurchaseList');
    const currentBalanceSpan = document.getElementById('sellCurrentBalance');
    const quickActions = document.getElementById('sellQuickActions');
    
    if (!historyBlock) return;
    
    // Показываем блок с загрузкой
    historyBlock.style.display = 'block';
    purchaseList.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;"><i class="fas fa-spinner fa-spin"></i> Загрузка истории...</div>';
    quickActions.style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'get_purchase_history');
    formData.append('asset_id', assetId);
    formData.append('platform_id', platformId);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            const currentQuantity = data.current_quantity;
            const avgPrice = data.avg_buy_price;
            const purchases = data.purchases;
            
            // Показываем текущий баланс
            const assetSymbol = document.getElementById('selectedTradeAssetDisplay').textContent;
            currentBalanceSpan.innerHTML = `<i class="fas fa-wallet"></i> Доступно: ${formatAmount(currentQuantity, assetSymbol)} ${assetSymbol}`;
            
            if (purchases.length === 0) {
                purchaseList.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-info-circle"></i> Нет истории покупок на этой площадке
                    </div>
                `;
                quickActions.style.display = 'none';
            } else {
                // Формируем список покупок
                let html = '<div style="font-size: 12px; margin-bottom: 8px; color: #6b7a8f;">История покупок:</div>';
                
                purchases.forEach(purchase => {
                    const date = new Date(purchase.operation_date).toLocaleDateString('ru-RU');
                    const quantity = formatAmount(purchase.quantity, assetSymbol);
                    const price = formatAmount(purchase.price, purchase.price_currency);
                    const total = formatAmount(purchase.quantity * purchase.price, purchase.price_currency);
                    
                    html += `
                        <div class="purchase-item" style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border-color, #edf2f7); cursor: pointer;" 
                             onclick="fillSellFromPurchase(${purchase.quantity}, ${purchase.price}, '${purchase.price_currency}')">
                            <div>
                                <div style="font-weight: 500;">${quantity} ${assetSymbol}</div>
                                <div style="font-size: 11px; color: #6b7a8f;">${date}</div>
                            </div>
                            <div style="text-align: right;">
                                <div>по ${price} ${purchase.price_currency}</div>
                                <div style="font-size: 11px; color: #00a86b;">${total} ${purchase.price_currency}</div>
                            </div>
                        </div>
                    `;
                });
                
                // Добавляем информацию о средней цене
                if (avgPrice > 0) {
                    html += `
                        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--border-color, #e0e6ed);">
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span><i class="fas fa-chart-line"></i> Средняя цена покупки:</span>
                                <span style="font-weight: 600;">${formatAmount(avgPrice, 'USD')} USD</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 4px;">
                                <span><i class="fas fa-coins"></i> Общая стоимость:</span>
                                <span style="font-weight: 600;">${formatAmount(currentQuantity * avgPrice, 'USD')} USD</span>
                            </div>
                        </div>
                    `;
                }
                
                purchaseList.innerHTML = html;
                quickActions.style.display = 'block';
                
                // Сохраняем данные для быстрых действий
                window.sellAssetData = {
                    assetId: assetId,
                    platformId: platformId,
                    currentQuantity: currentQuantity,
                    avgPrice: avgPrice,
                    symbol: document.getElementById('selectedTradeAssetDisplay').textContent
                };
            }
        } else {
            purchaseList.innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;">Ошибка загрузки истории</div>';
            quickActions.style.display = 'none';
        }
    } catch (error) {
        purchaseList.innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;">Ошибка загрузки</div>';
        quickActions.style.display = 'none';
    }
}

// Функция для заполнения формы из конкретной покупки
function fillSellFromPurchase(quantity, price, currency) {
    const quantityInput = document.getElementById('tradeQuantity');
    const priceInput = document.getElementById('tradePrice');
    const priceCurrencyBtn = document.getElementById('selectedTradePriceCurrencyDisplay');
    const priceCurrencyHidden = document.getElementById('tradePriceCurrency');
    
    if (quantityInput) {
        // Форматируем количество
        let quantityStr = quantity.toString();
        if (Number.isInteger(quantity)) {
            quantityStr = quantity.toLocaleString('ru-RU').replace(/,/g, ' ');
        } else {
            quantityStr = quantity.toFixed(6).replace(/\.?0+$/, '');
            let parts = quantityStr.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityStr = parts.join('.');
        }
        quantityInput.value = quantityStr;
        
        // Триггерим событие для пересчета итога
        quantityInput.dispatchEvent(new Event('input'));
    }
    
    if (priceInput) {
        // Форматируем цену
        let priceStr = price.toFixed(2);
        let parts = priceStr.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        priceInput.value = parts.join('.');
        
        // Триггерим событие для пересчета итога
        priceInput.dispatchEvent(new Event('input'));
    }
    
    // Устанавливаем валюту цены
    if (priceCurrencyBtn && priceCurrencyHidden) {
        priceCurrencyBtn.textContent = currency;
        priceCurrencyHidden.value = currency;
        
        // Копируем валюту в комиссию, если там ничего не выбрано
        const commissionDisplay = document.getElementById('selectedTradeCommissionCurrencyDisplay');
        const commissionHidden = document.getElementById('tradeCommissionCurrency');
        
        if (commissionDisplay && commissionHidden && !commissionHidden.value) {
            commissionDisplay.textContent = currency;
            commissionHidden.value = currency;
        }
    }
    
    // Показываем уведомление
    //showNotification('info', 'Заполнено', `Количество: ${quantity} ${window.sellAssetData?.symbol || ''}`);
}

// Функция для быстрого заполнения "Продать всё"
function fillSellAll() {
    if (!window.sellAssetData) return;
    
    const quantity = window.sellAssetData.currentQuantity;
    const avgPrice = window.sellAssetData.avgPrice;
    
    if (quantity <= 0) {
        showNotification('error', 'Ошибка', 'Нет доступного количества для продажи');
        return;
    }
    
    const quantityInput = document.getElementById('tradeQuantity');
    if (quantityInput) {
        let quantityStr = quantity.toString();
        if (Number.isInteger(quantity)) {
            quantityStr = quantity.toLocaleString('ru-RU').replace(/,/g, ' ');
        } else {
            quantityStr = quantity.toFixed(6).replace(/\.?0+$/, '');
            let parts = quantityStr.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityStr = parts.join('.');
        }
        quantityInput.value = quantityStr;
        quantityInput.dispatchEvent(new Event('input'));
    }
    
    showNotification('info', 'Заполнено', `Продажа всего количества: ${formatAmount(quantity, window.sellAssetData.symbol)} ${window.sellAssetData.symbol}`);
}

// Функция для быстрого заполнения по средней цене
function fillSellByAvgPrice() {
    if (!window.sellAssetData || window.sellAssetData.avgPrice <= 0) {
        showNotification('error', 'Ошибка', 'Нет данных о средней цене');
        return;
    }
    
    const priceInput = document.getElementById('tradePrice');
    const price = window.sellAssetData.avgPrice;
    
    if (priceInput) {
        let priceStr = price.toFixed(2);
        let parts = priceStr.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        priceInput.value = parts.join('.');
        priceInput.dispatchEvent(new Event('input'));
    }
    
    showNotification('info', 'Заполнено', `Цена установлена по средней: ${formatAmount(window.sellAssetData.avgPrice, 'USD')} USD`);
}

// Функция для загрузки баланса площадки
let currentPlatformBalanceData = null;

async function loadPlatformBalance(platformId, platformName) {
    if (!platformId) return;
    
    const balanceBlock = document.getElementById('transferFromPlatformBalance');
    const assetsList = document.getElementById('transferPlatformAssetsList');
    const totalValueSpan = document.getElementById('transferPlatformTotalValue');
    const totalDiv = document.getElementById('transferPlatformTotal');
    const totalUsdSpan = document.getElementById('transferPlatformTotalUsd');
    const balanceTitle = document.getElementById('platformBalanceTitle');
    
    if (!balanceBlock) return;
    
    // ИЗМЕНЕНИЕ: Обновляем заголовок с названием площадки
    if (balanceTitle) {
        balanceTitle.innerHTML = `<i class="fas fa-wallet"></i> Баланс: ${platformName}`;
    }
    
    // Показываем блок с загрузкой
    balanceBlock.style.display = 'block';
    assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #6b7a8f;"><i class="fas fa-spinner fa-spin"></i> Загрузка баланса...</div>';
    totalDiv.style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'get_platform_balance');
    formData.append('platform_id', platformId);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && result.assets) {
            const assets = result.assets;
            const totalUsd = result.total_value_usd;
            const totalRub = result.total_value_rub;
            
            // Получаем курс USD/RUB из PHP
            const usdRubRate = <?= $usd_rub_rate ?>;
            
            // Форматируем общую стоимость
            let totalUsdStr = totalUsd.toFixed(2);
            let totalUsdParts = totalUsdStr.split('.');
            totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
            
            let totalRubStr = Math.round(totalRub).toString();
            totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            
            totalValueSpan.innerHTML = `<i class="fas fa-chart-line"></i> ${totalUsdFormatted} $ / ${totalRubStr} ₽`;
            
            if (assets.length === 0) {
                assetsList.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-box-open"></i> Нет активов на площадке ${platformName}
                    </div>
                `;
                totalDiv.style.display = 'none';
            } else {
                // Формируем список активов (остается без изменений)
                let html = '';
                
                assets.forEach(asset => {
                    const quantity = parseFloat(asset.quantity);
                    const valueUsd = parseFloat(asset.value_usd);
                    const valueRub = valueUsd * usdRubRate;
                    
                    // Форматируем количество
                    let quantityFormatted = '';
                    if (asset.asset_type === 'crypto') {
                        if (Math.floor(quantity) === quantity) {
                            quantityFormatted = quantity.toLocaleString('ru-RU').replace(/,/g, ' ');
                        } else {
                            let str = quantity.toFixed(6).replace(/\.?0+$/, '');
                            let parts = str.split('.');
                            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
                        }
                    } else {
                        let str = quantity.toFixed(2).replace(/\.?0+$/, '');
                        let parts = str.split('.');
                        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                        quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
                    }
                    
                    // Форматируем стоимость
                    let usdStr = valueUsd.toFixed(2);
                    let usdParts = usdStr.split('.');
                    usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
                    
                    let rubStr = Math.round(valueRub).toString();
                    rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    
                    // Определяем иконку для актива
                    let assetIcon = getAssetIcon(asset.symbol);
                    
                    html += `
                        <div class="platform-asset-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; margin-right:20px; border-bottom: 1px solid var(--border-color, #edf2f7); cursor: pointer;" 
                             onclick="selectTransferAssetFromBalance('${asset.asset_id}', '${asset.symbol}', '${asset.asset_type}', '${quantityFormatted}')"
                             onmouseover="this.style.background='var(--bg-tertiary)'; this.style.borderRadius='8px'; this.style.paddingLeft='8px'; this.style.paddingRight='8px';" 
                             onmouseout="this.style.background='transparent'; this.style.paddingLeft='0'; this.style.paddingRight='0';">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 28px; height: 28px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="${assetIcon.icon}" style="color: ${assetIcon.color}; font-size: 14px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 500; font-size: 13px;">${asset.symbol}</div>
                                    <div style="font-size: 10px; color: #6b7a8f;">${quantityFormatted}</div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 12px; font-weight: 500;">$${usdFormatted}</div>
                                <div style="font-size: 10px; color: #6b7a8f;">${rubStr} ₽</div>
                            </div>
                        </div>
                    `;
                });
                
                assetsList.innerHTML = html;
                totalDiv.style.display = 'block';
                
                // Форматируем общую стоимость для отображения в нижней части
                totalUsdSpan.innerHTML = `$${totalUsdFormatted} (${totalRubStr} ₽)`;
            }
            
            // Сохраняем данные для быстрого доступа
            currentPlatformBalanceData = {
                platformId: platformId,
                platformName: platformName,
                assets: assets,
                totalUsd: totalUsd,
                totalRub: totalRub
            };
            
        } else {
            assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #e53e3e;">Ошибка загрузки баланса</div>';
            totalDiv.style.display = 'none';
        }
    } catch (error) {
        assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #e53e3e;">Ошибка загрузки</div>';
        totalDiv.style.display = 'none';
    }
}

// Функция для сброса заголовка баланса
function resetPlatformBalanceTitle() {
    const balanceTitle = document.getElementById('platformBalanceTitle');
    if (balanceTitle) {
        balanceTitle.innerHTML = '<i class="fas fa-wallet"></i> Баланс площадки';
    }
}

// Модифицируем функцию closeTransferModal для сброса заголовка
function closeTransferModal() {
    transferModal.classList.remove('active');
    hidePlatformBalance();
    resetPlatformBalanceTitle(); // Добавляем сброс заголовка
    currentPlatformBalanceData = null;
}

// Функция для быстрого выбора актива из баланса
function selectTransferAssetFromBalance(assetId, symbol, assetType, quantityFormatted) {
    // Выбираем актив
    selectAsset(assetId, symbol);
    
    // Показываем уведомление с количеством
    //showNotification('info', 'Актив выбран', `${symbol}: доступно ${quantityFormatted}`);
    
    // Опционально: можно автоматически заполнить количество для перевода
    const amountInput = document.getElementById('transferAmount');
    if (amountInput) {
        // Извлекаем числовое значение из отформатированной строки
        const numericValue = parseFloat(quantityFormatted.replace(/\s/g, '').replace(',', '.'));
        if (!isNaN(numericValue)) {
            // Не заполняем автоматически, чтобы пользователь сам решил сколько переводить
            // Но можно добавить кнопку "Перевести всё" позже
        }
    }
}

// Функция для скрытия блока баланса
function hidePlatformBalance() {
    const balanceBlock = document.getElementById('transferFromPlatformBalance');
    if (balanceBlock) {
        balanceBlock.style.display = 'none';
    }
}

// Переменные для расходов
let expenseCategories = [];

function closeExpenseModal() {
    const modal = document.getElementById('expenseModal');
    if (modal) {
        modal.classList.remove('active');
        enableBodyScroll();
    }
}

async function loadExpenseCategories() {
    const container = document.getElementById('expenseCategoriesList');
    console.log('loadExpenseCategories вызвана, container:', container);
    if (!container) {
        console.error('Контейнер expenseCategoriesList не найден');
        return;
    }
    
    container.innerHTML = '<div style="text-align: center; padding: 10px;"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
    
    try {
        console.log('Отправляем запрос get_expense_categories...');
        const formData = new FormData();
        formData.append('action', 'get_expense_categories');
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        console.log('Ответ от сервера:', result);
        
        if (result.success && result.categories) {
            expenseCategories = result.categories;
            console.log('Получено категорий:', result.categories.length);
            
            let html = '';
            result.categories.forEach(cat => {
                html += `
                    <button type="button" class="expense-category-btn" data-category-id="${cat.id}" 
                            style="flex: 1 1 auto; min-width: 80px; padding: 10px 12px; background: ${cat.color}20; border: 1px solid ${cat.color}; border-radius: 12px; font-size: 13px; font-weight: 500; color: ${cat.color}; cursor: pointer; transition: all 0.2s ease; text-align: center;"
                            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        <i class="${cat.icon}"></i> ${cat.name_ru}
                    </button>
                `;
            });
            
            container.innerHTML = html;
            console.log('HTML установлен, кнопок:', document.querySelectorAll('.expense-category-btn').length);
            
            // Добавляем обработчики для категорий
            document.querySelectorAll('.expense-category-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const categoryId = this.dataset.categoryId;
                    console.log('Выбрана категория:', categoryId);
                    
                    // Убираем активный класс у всех
                    document.querySelectorAll('.expense-category-btn').forEach(b => {
                        b.style.background = '';
                        b.style.fontWeight = '500';
                    });
                    
                    // Добавляем активный класс выбранной
                    this.style.background = this.style.borderColor + '30';
                    this.style.fontWeight = '600';
                    
                    document.getElementById('expenseCategoryId').value = categoryId;
                });
            });
        } else {
            console.error('Ошибка в ответе:', result);
            container.innerHTML = '<div style="text-align: center; padding: 10px; color: #e53e3e;">Нет категорий расходов</div>';
        }
    } catch (error) {
        console.error('Ошибка загрузки категорий:', error);
        container.innerHTML = '<div style="text-align: center; padding: 10px; color: #e53e3e;">Ошибка загрузки категорий</div>';
    }
}



// Функция открытия модального окна добавления категории
function openAddExpenseCategoryModal() {
    const modal = document.getElementById('addExpenseCategoryModal');
    if (modal) {
        modal.classList.add('active');
        disableBodyScroll();
        
        // Сбрасываем поля
        document.getElementById('newCategoryName').value = '';
        document.getElementById('newCategoryNameRu').value = '';
        document.getElementById('newCategoryIcon').value = 'fas fa-tag';
        document.getElementById('newCategoryColor').value = '#ff9f4a';
        
        // Обновляем превью
        updateCategoryPreview();
    }
}

function closeAddExpenseCategoryModal() {
    const modal = document.getElementById('addExpenseCategoryModal');
    if (modal) {
        modal.classList.remove('active');
        enableBodyScroll();
    }
}

// Обновление превью иконки и цвета
function updateCategoryPreview() {
    const icon = document.getElementById('newCategoryIcon').value;
    const color = document.getElementById('newCategoryColor').value;
    const previewIcon = document.querySelector('#iconPreview i');
    const previewText = document.getElementById('iconPreviewText');
    
    if (previewIcon) {
        // Разбиваем строку иконки (например "fas fa-tag")
        const iconParts = icon.split(' ');
        previewIcon.className = '';
        iconParts.forEach(part => {
            previewIcon.classList.add(part);
        });
        previewIcon.style.color = color;
    }
    if (previewText) {
        previewText.textContent = icon;
    }
}

// Функция сохранения категории
async function saveExpenseCategory() {
    const name = document.getElementById('newCategoryName').value.trim().toLowerCase();
    const name_ru = document.getElementById('newCategoryNameRu').value.trim();
    const icon = document.getElementById('newCategoryIcon').value.trim();
    const color = document.getElementById('newCategoryColor').value;
    
    if (!name || !name_ru) {
        showNotification('error', 'Ошибка', 'Заполните название категории');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_expense_category');
    formData.append('name', name);
    formData.append('name_ru', name_ru);
    formData.append('icon', icon);
    formData.append('color', color);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeAddExpenseCategoryModal();
            await loadExpenseCategories(); // Перезагружаем список категорий
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось добавить категорию');
    }
}

// Функция выбора иконки
function openIconSelectModal() {
    const modal = document.getElementById('iconSelectModal');
    if (modal) {
        modal.classList.add('active');
        disableBodyScroll();
    }
}

function closeIconSelectModal() {
    const modal = document.getElementById('iconSelectModal');
    if (modal) {
        modal.classList.remove('active');
        enableBodyScroll();
    }
}

function selectIcon(iconClass) {
    document.getElementById('newCategoryIcon').value = iconClass;
    updateCategoryPreview();
    closeIconSelectModal();
}

function openExpenseModal() {
    const modal = document.getElementById('expenseModal');
    if (modal) {
        modal.classList.add('active');
        disableBodyScroll();
        
        // Устанавливаем дату
        document.getElementById('expenseDate').value = new Date().toISOString().split('T')[0];
        
        // Сбрасываем поля
        document.getElementById('expenseAmount').value = '';
        document.getElementById('expenseDescription').value = '';
        document.getElementById('expenseCategoryId').value = '';
        
        // Сбрасываем активные кнопки категорий
        document.querySelectorAll('.expense-category-btn').forEach(btn => {
            btn.style.background = '';
            btn.style.fontWeight = '500';
        });
        
        // Загружаем категории
        loadExpenseCategories();
    }
}

// Правильная версия saveExpense (использует category_id)
async function saveExpense() {
    const amount = parseFloat(document.getElementById('expenseAmount').value.replace(/\s/g, '').replace(',', '.')) || 0;
    const currency_code = document.getElementById('selectedExpenseCurrencyDisplay').textContent;
    const category_id = document.getElementById('expenseCategoryId').value;
    const description = document.getElementById('expenseDescription').value;
    const expense_date = document.getElementById('expenseDate').value;
    
    if (amount <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректную сумму');
        return;
    }
    
    if (!category_id) {
        showNotification('error', 'Ошибка', 'Выберите категорию расхода');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_expense');
    formData.append('amount', amount);
    formData.append('currency_code', currency_code);
    formData.append('category_id', category_id);
    formData.append('description', description);
    formData.append('expense_date', expense_date);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeExpenseModal();
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось сохранить расход');
    }
}

// Функция открытия списка расходов
async function openExpensesListModal() {
    const modal = document.getElementById('expensesListModal');
    const body = document.getElementById('expensesListBody');
    
    if (!modal || !body) return;
    
    modal.classList.add('active');
    disableBodyScroll();
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка расходов...</div>';
    
    await loadExpensesList();
}

function closeExpensesListModal() {
    const modal = document.getElementById('expensesListModal');
    if (modal) {
        modal.classList.remove('active');
        enableBodyScroll();
    }
}

// Функция загрузки списка расходов
async function loadExpensesList() {
    const body = document.getElementById('expensesListBody');
    if (!body) return;
    
    const formData = new FormData();
    formData.append('action', 'get_expenses');
    formData.append('limit', 50);
    formData.append('offset', 0);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            displayExpensesList(result);
        } else {
            body.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки расходов</div>';
        }
    } catch (error) {
        body.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки</div>';
    }
}

// Функция отображения списка расходов
function displayExpensesList(data) {
    const body = document.getElementById('expensesListBody');
    if (!body) return;
    
    const expenses = data.expenses;
    const total = data.total;
    const stats = data.stats;
    
    if (expenses.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-receipt" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">Нет добавленных расходов</p>
                <button class="add-order-btn" onclick="openExpenseModal()" style="margin-top: 15px;">
                    <i class="fas fa-plus-circle"></i> Добавить расход
                </button>
            </div>
        `;
        return;
    }
    
    // Формируем статистику
    let statsHtml = `
        <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span style="font-weight: 600;">Всего расходов:</span>
                <span style="font-weight: 600; color: #ff9f4a;">${formatAmount(total, 'RUB')} ₽</span>
            </div>
            <div style="margin-top: 10px;">
                <div style="font-size: 12px; color: #6b7a8f; margin-bottom: 8px;">По категориям:</div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
    `;
    
    stats.forEach(stat => {
        const category = expenseCategories.find(c => c.name === stat.category);
        const color = category ? category.color : '#95a5a6';
        statsHtml += `
            <span style="background: ${color}20; color: ${color}; padding: 4px 10px; border-radius: 20px; font-size: 12px;">
                ${category?.name_ru || stat.category}: ${formatAmount(stat.total_amount, 'RUB')} ₽
            </span>
        `;
    });
    
    statsHtml += `
                </div>
            </div>
        </div>
    `;
    
    // Формируем список расходов
    let expensesHtml = `
        <div style="display: flex; flex-direction: column; gap: 10px;">
    `;
    
    expenses.forEach(expense => {
        const category = expenseCategories.find(c => c.name === expense.category);
        const icon = category?.icon || 'fas fa-receipt';
        const color = category?.color || '#95a5a6';
        const date = new Date(expense.expense_date).toLocaleDateString('ru-RU');
        
        expensesHtml += `
            <div class="expense-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-secondary); border-radius: 12px; border-left: 4px solid ${color};">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 36px; height: 36px; background: ${color}20; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="${icon}" style="color: ${color};"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500;">${category?.name_ru || expense.category}</div>
                        <div style="font-size: 12px; color: #6b7a8f;">${date}</div>
                        ${expense.description ? `<div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">${escapeHtml(expense.description)}</div>` : ''}
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 600; color: #e53e3e;">- ${formatAmount(expense.amount, expense.currency_code)} ${expense.currency_code}</div>
                    <button class="delete-expense-btn" data-id="${expense.id}" style="background: none; border: none; color: #95a5a6; cursor: pointer; margin-top: 4px; font-size: 12px;">
                        <i class="fas fa-trash-alt"></i> Удалить
                    </button>
                </div>
            </div>
        `;
    });
    
    expensesHtml += '</div>';
    
    body.innerHTML = statsHtml + expensesHtml;
    
    // Добавляем обработчики удаления
    document.querySelectorAll('.delete-expense-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.stopPropagation();
            const expenseId = this.dataset.id;
            if (confirm('Удалить этот расход?')) {
                await deleteExpense(expenseId);
            }
        });
    });
}

// Функция удаления расхода
async function deleteExpense(expenseId) {
    const formData = new FormData();
    formData.append('action', 'delete_expense');
    formData.append('expense_id', expenseId);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            await loadExpensesList(); // Перезагружаем список
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось удалить расход');
    }
}
</script>
</html>