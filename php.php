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
// ПОЛУЧЕНИЕ АКТИВОВ ПО СЕТЯМ (используя поле network в portfolio)
// ============================================================================

$stmt = $pdo->query("
    SELECT 
        COALESCE(p.network, 'UNKNOWN') as network,
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
    WHERE a.type = 'crypto' AND p.quantity > 0
    ORDER BY network, value_usd DESC
");

$all_network_assets = $stmt->fetchAll();

// Группируем активы по сетям
$network_assets_grouped = [];
foreach ($all_network_assets as $asset) {
    $network = $asset['network'];
    
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
        'asset_type' => $asset['asset_type'],
        'quantity' => $asset['quantity'],
        'average_buy_price' => $asset['average_buy_price'],
        'currency_code' => $asset['currency_code'],
        'platform_name' => $asset['platform_name'],
        'platform_id' => $asset['platform_id'],
        'value_usd' => $asset['value_usd']
    ];
    
    $network_assets_grouped[$network]['total_value_usd'] += $asset['value_usd'];
}

// Сортируем сети по общей стоимости
uasort($network_assets_grouped, function($a, $b) {
    return $b['total_value_usd'] <=> $a['total_value_usd'];
});

$network_assets_json = json_encode($network_assets_grouped);

// ============================================================================
// ПОЛУЧЕНИЕ АКТИВОВ ПО ПЛОЩАДКЕ (для модального окна)
// ============================================================================

// Получаем все активы с детализацией по площадкам для модального окна
$stmt = $pdo->query("
    SELECT 
        pf.id as platform_id,
        pf.name as platform_name,
        pf.type as platform_type,
        a.id as asset_id,
        a.symbol,
        a.name as asset_name,
        a.type as asset_type,
        pl.quantity,
        pl.average_buy_price,
        pl.currency_code,
        CASE 
            WHEN a.symbol = 'RUB' THEN pl.quantity / (SELECT rate FROM exchange_rates WHERE from_currency = 'USD' AND to_currency = 'RUB' ORDER BY date DESC LIMIT 1)
            WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN pl.quantity
            WHEN pl.currency_code = 'RUB' THEN (pl.quantity * COALESCE(pl.average_buy_price, 0)) / (SELECT rate FROM exchange_rates WHERE from_currency = 'USD' AND to_currency = 'RUB' ORDER BY date DESC LIMIT 1)
            ELSE pl.quantity * COALESCE(pl.average_buy_price, 0)
        END as value_usd
    FROM portfolio pl
    JOIN assets a ON pl.asset_id = a.id
    JOIN platforms pf ON pl.platform_id = pf.id
    WHERE pl.quantity > 0
    ORDER BY pf.name, value_usd DESC
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
// ПОЛУЧЕНИЕ ТЕКУЩЕЙ СТОИМОСТИ ПОРТФЕЛЯ
// ============================================================================

// Получаем актуальный курс USD/RUB
$stmt_rate = $pdo->query("
    SELECT rate FROM exchange_rates 
    WHERE from_currency = 'USD' AND to_currency = 'RUB' 
    ORDER BY date DESC LIMIT 1
");
$rate_data = $stmt_rate->fetch();
$usd_rub_rate = $rate_data ? $rate_data['rate'] : 92.50;

// Получаем все активы из портфеля с текущими рыночными ценами
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.symbol,
        a.type,
        a.currency_code as asset_currency,
        pl.quantity,
        pl.average_buy_price,
        pl.currency_code as portfolio_currency,
        -- Текущая рыночная цена (из последней покупки)
        COALESCE(
            (SELECT price FROM trades t 
             WHERE t.asset_id = a.id AND t.operation_type = 'buy' 
             ORDER BY t.operation_date DESC LIMIT 1), 0
        ) as current_price,
        -- Валюта текущей цены
        COALESCE(
            (SELECT price_currency FROM trades t 
             WHERE t.asset_id = a.id AND t.operation_type = 'buy' 
             ORDER BY t.operation_date DESC LIMIT 1), a.currency_code
        ) as current_price_currency
    FROM portfolio pl
    JOIN assets a ON pl.asset_id = a.id
    WHERE pl.quantity > 0
");
$stmt->execute();
$portfolio_assets = $stmt->fetchAll();

// Инициализируем переменные
$rub_amount = 0;
$usdt_amount = 0;
$usd_amount = 0;
$eur_amount = 0;
$investments_value = 0;
$liquidity_value = 0;
$total_usd = 0;

foreach ($portfolio_assets as $asset) {
    $quantity = floatval($asset['quantity']);
    $current_price = floatval($asset['current_price']);
    $current_price_currency = $asset['current_price_currency'];
    $symbol = $asset['symbol'];
    
    $value_usd = 0;
    
    // ========== ЛИКВИДНЫЕ АКТИВЫ (фиат и стейблкоины) ==========
    if ($symbol == 'RUB') {
        $rub_amount += $quantity;
        $value_usd = $quantity / $usd_rub_rate;
        $liquidity_value += $value_usd;
    }
    elseif ($symbol == 'USD') {
        $usd_amount += $quantity;
        $value_usd = $quantity;
        $liquidity_value += $value_usd;
    }
    elseif ($symbol == 'USDT' || $symbol == 'USDC') {
        $usdt_amount += $quantity;
        $value_usd = $quantity;
        $liquidity_value += $value_usd;
    }
    elseif ($symbol == 'EUR') {
        $eur_amount += $quantity;
        $value_usd = $quantity;
        $liquidity_value += $value_usd;
    }
    // ========== ИНВЕСТИЦИОННЫЕ АКТИВЫ ==========
    else {
        // Если есть текущая рыночная цена
        if ($current_price > 0) {
            $price_in_usd = $current_price;
            
            // Конвертируем цену в USD если нужно
            if ($current_price_currency == 'RUB') {
                $price_in_usd = $current_price / $usd_rub_rate;
            }
            elseif ($current_price_currency == 'EUR') {
                $price_in_usd = $current_price * 1.08;
            }
            
            $value_usd = $quantity * $price_in_usd;
        } 
        // Если нет рыночной цены, используем историческую
        elseif ($asset['average_buy_price'] > 0) {
            $avg_price = floatval($asset['average_buy_price']);
            $avg_currency = $asset['portfolio_currency'] ?: $asset['asset_currency'];
            
            if ($avg_currency == 'RUB') {
                $value_usd = ($quantity * $avg_price) / $usd_rub_rate;
            } else {
                $value_usd = $quantity * $avg_price;
            }
        }
        
        $investments_value += $value_usd;
    }
    
    $total_usd = $liquidity_value + $investments_value;
}

// Если нет данных или всё равно 0 - показываем 1
if ($total_usd <= 0) {
    $total_usd = 1;
}

// Конвертируем в рубли
$total_rub = $total_usd * $usd_rub_rate;
$rub_in_usd = $rub_amount / $usd_rub_rate;

// Сохраняем для отображения
$rub_amount_display = $rub_amount;
$usdt_amount_display = $usdt_amount;

// ============================================================================
// РАСЧЕТ ДОХОДНОСТИ ПОРТФЕЛЯ
// ============================================================================

// 1. ВЛОЖЕНО = СУММА ВСЕХ ПОПОЛНЕНИЙ
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

$total_invested_usd += $deposits_total['usd_deposits'];
$total_invested_usd += $deposits_total['eur_deposits'];
$total_invested_usd += $deposits_total['rub_deposits'] / $usd_rub_rate;

$total_invested_rub = $total_invested_usd * $usd_rub_rate;

// 2. ПРИБЫЛЬ
$profit_usd = $total_usd - $total_invested_usd;
$profit_rub = $total_rub - $total_invested_rub;

// 3. ДОХОДНОСТЬ В ПРОЦЕНТАХ
if ($total_invested_usd > 0) {
    $profit_percent = ($profit_usd / $total_invested_usd) * 100;
} else {
    $profit_percent = 0;
}

// Определяем иконку
$profit_icon = $profit_usd >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';

// ============================================================================
// ПОЛУЧАЕМ ТЕКУЩУЮ СТОИМОСТЬ КРИПТОАКТИВОВ ИЗ ПОРТФЕЛЯ
// ============================================================================

// Получаем текущие стоимости из портфеля
$stmt_current = $pdo->query("
    SELECT 
        -- Стейблкоины
        COALESCE(SUM(
            CASE 
                WHEN a.symbol IN ('USDT', 'USDC', 'DAI', 'BUSD') THEN p.quantity
                ELSE 0
            END
        ), 0) as stablecoins_balance,
        
        -- BTC - используем quantity, так как average_buy_price может быть NULL
        COALESCE(SUM(
            CASE 
                WHEN a.symbol = 'BTC' THEN p.quantity
                ELSE 0
            END
        ), 0) as btc_quantity,
        
        -- ETH - используем quantity
        COALESCE(SUM(
            CASE 
                WHEN a.symbol = 'ETH' THEN p.quantity
                ELSE 0
            END
        ), 0) as eth_quantity,
        
        -- Альткоины (все остальные крипто) - считаем количество
        COALESCE(SUM(
            CASE 
                WHEN a.type = 'crypto' 
                     AND a.symbol NOT IN ('USDT', 'USDC', 'DAI', 'BUSD', 'BTC', 'ETH') 
                THEN p.quantity
                ELSE 0
            END
        ), 0) as altcoins_quantity
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
    WHERE a.type = 'crypto' AND p.quantity > 0
");
$current_crypto = $stmt_current->fetch();

// Получаем цены BTC и ETH из последних покупок (для расчета стоимости)
$stmt_prices = $pdo->query("
    SELECT 
        a.symbol,
        t.price
    FROM trades t
    JOIN assets a ON t.asset_id = a.id
    WHERE a.symbol IN ('BTC', 'ETH') 
        AND t.operation_type = 'buy'
    ORDER BY t.operation_date DESC
    LIMIT 2
");
$prices = [];
while ($row = $stmt_prices->fetch()) {
    $prices[$row['symbol']] = $row['price'];
}

// Используем цены из trades или дефолтные
$btc_price = $prices['BTC'] ?? 80000;
$eth_price = $prices['ETH'] ?? 3000;

// Рассчитываем стоимость
$stablecoins_left = $current_crypto['stablecoins_balance'];
$btc_cost = $current_crypto['btc_quantity'] * $btc_price;
$eth_cost = $current_crypto['eth_quantity'] * $eth_price;

// Для альткоинов считаем стоимость по средней цене из portfolio или по последней цене из trades
$stmt_altcoins = $pdo->query("
    SELECT 
        a.symbol,
        p.quantity,
        COALESCE(p.average_buy_price, 
            (SELECT price FROM trades t2 
             WHERE t2.asset_id = a.id AND t2.operation_type = 'buy' 
             ORDER BY t2.operation_date DESC LIMIT 1), 0) as price
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
    WHERE a.type = 'crypto' 
        AND a.symbol NOT IN ('USDT', 'USDC', 'DAI', 'BUSD', 'BTC', 'ETH')
        AND p.quantity > 0
");
$altcoins_cost = 0;
while ($alt = $stmt_altcoins->fetch()) {
    $altcoins_cost += $alt['quantity'] * $alt['price'];
}

// Общая стоимость всех криптоактивов
$total_crypto_value = $stablecoins_left + $btc_cost + $eth_cost + $altcoins_cost;

// Если нет криптоактивов, ставим значение по умолчанию
if ($total_crypto_value <= 0) $total_crypto_value = 1;

// Рассчитываем проценты
$btc_percent = $btc_cost > 0 ? round(($btc_cost / $total_crypto_value) * 100, 1) : 0;
$eth_percent = $eth_cost > 0 ? round(($eth_cost / $total_crypto_value) * 100, 1) : 0;
$altcoins_percent = $altcoins_cost > 0 ? round(($altcoins_cost / $total_crypto_value) * 100, 1) : 0;
$stablecoins_percent = $stablecoins_left > 0 ? round(($stablecoins_left / $total_crypto_value) * 100, 1) : 0;

// ============================================================
// РАСЧЕТ СРЕДНЕЙ ЦЕНЫ ДЛЯ ВСЕХ СТЕЙБЛКОИНОВ И USD КАК ЕДИНОГО ЦЕЛОГО
// Группа: USD, USDT, USDC, DAI, BUSD и т.д.
// Формула: Σ(quantity × price) / Σ(quantity) для всех покупок группы
// ============================================================

// 1. Сначала определим, какие символы входят в группу стейблкоинов
$stablecoin_group = ['USD', 'USDT', 'USDC', 'DAI', 'BUSD'];

// 2. Получаем все активы с расчетом средней цены
$query = "
    SELECT 
        a.id,
        a.symbol,
        a.name,
        a.type,
        a.currency_code,
        
        -- Берем остаток ИЗ PORTFOLIO
        SUM(p.quantity) AS total_quantity,
        
        -- Рассчитываем среднюю цену из trades (только покупки)
        CASE 
            WHEN (
                SELECT COALESCE(SUM(t2.quantity), 0)
                FROM trades t2
                WHERE t2.asset_id = a.id
                AND t2.operation_type = 'buy'
            ) > 0 
            THEN (
                SELECT SUM(t2.quantity * t2.price) / SUM(t2.quantity)
                FROM trades t2
                WHERE t2.asset_id = a.id
                AND t2.operation_type = 'buy'
            )
            ELSE 0
        END AS avg_price,
        
        -- ВАЛЮТА СРЕДНЕЙ ЦЕНЫ (берем валюту из первой покупки)
        (
            SELECT price_currency
            FROM trades t6
            WHERE t6.asset_id = a.id
            AND t6.operation_type = 'buy'
            ORDER BY t6.operation_date ASC
            LIMIT 1
        ) AS avg_price_currency,
        
        -- Стоимость покупки (остаток * средняя цена)
        SUM(p.quantity) * CASE 
            WHEN (
                SELECT COALESCE(SUM(t2.quantity), 0)
                FROM trades t2
                WHERE t2.asset_id = a.id
                AND t2.operation_type = 'buy'
            ) > 0 
            THEN (
                SELECT SUM(t2.quantity * t2.price) / SUM(t2.quantity)
                FROM trades t2
                WHERE t2.asset_id = a.id
                AND t2.operation_type = 'buy'
            )
            ELSE 0
        END AS purchase_cost,
        
        -- Текущая цена (последняя покупка)
        (
            SELECT price 
            FROM trades t3 
            WHERE t3.asset_id = a.id 
            AND t3.operation_type = 'buy' 
            ORDER BY t3.operation_date DESC 
            LIMIT 1
        ) AS current_price,
        
        -- Валюта текущей цены
        (
            SELECT price_currency 
            FROM trades t4 
            WHERE t4.asset_id = a.id 
            AND t4.operation_type = 'buy' 
            ORDER BY t4.operation_date DESC 
            LIMIT 1
        ) AS current_price_currency
        
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
    WHERE p.quantity > 0
      AND a.is_active = 1
    GROUP BY a.id, a.symbol, a.name, a.type, a.currency_code
    ORDER BY 
        CASE 
            WHEN a.type = 'crypto' THEN 1
            WHEN a.type = 'stock' THEN 2
            WHEN a.type = 'etf' THEN 3
            WHEN a.type = 'currency' THEN 4
            WHEN a.type = 'bond' THEN 5
            ELSE 6
        END,
        SUM(p.quantity) DESC
";

$stmt = $pdo->query($query);
$my_assets = $stmt->fetchAll();

// Получаем курс USD/RUB
$usd_rub_rate = 92.5;
$rate_query = $pdo->query("
    SELECT rate 
    FROM exchange_rates 
    WHERE from_currency = 'USD' AND to_currency = 'RUB' 
    ORDER BY date DESC 
    LIMIT 1
");
if ($rate_row = $rate_query->fetch()) {
    $usd_rub_rate = floatval($rate_row['rate']);
}

// ============================================================
// РАСЧЕТ ОБЩЕЙ СРЕДНЕЙ ЦЕНЫ ДЛЯ ВСЕХ СТЕЙБЛКОИНОВ
// ============================================================

// Собираем все покупки стейблкоинов из trades
$stable_total_quantity = 0;
$stable_total_cost = 0;

foreach ($stablecoin_group as $stable_symbol) {
    // Ищем asset_id для каждого стейблкоина
    $asset_query = $pdo->prepare("SELECT id FROM assets WHERE symbol = ?");
    $asset_query->execute([$stable_symbol]);
    $asset_row = $asset_query->fetch();
    
    if ($asset_row) {
        $asset_id = $asset_row['id'];
        
        // Суммируем все покупки этого стейблкоина
        $trade_query = $pdo->prepare("
            SELECT 
                COALESCE(SUM(quantity), 0) as total_qty,
                COALESCE(SUM(quantity * price), 0) as total_cost
            FROM trades
            WHERE asset_id = ? AND operation_type = 'buy'
        ");
        $trade_query->execute([$asset_id]);
        $trade_data = $trade_query->fetch();
        
        $stable_total_quantity += floatval($trade_data['total_qty']);
        $stable_total_cost += floatval($trade_data['total_cost']);
    }
}

// Общая средняя цена для всех стейблкоинов
$stable_avg_price = $stable_total_quantity > 0 ? $stable_total_cost / $stable_total_quantity : 0;

// Определяем основную валюту для стейблкоинов (по большинству покупок)
$stable_currency = 'RUB'; // по умолчанию
if ($stable_avg_price > 0) {
    // Проверяем, в какой валюте больше всего покупок
    $currency_query = $pdo->query("
        SELECT price_currency, COUNT(*) as cnt
        FROM trades t
        JOIN assets a ON t.asset_id = a.id
        WHERE a.symbol IN ('" . implode("','", $stablecoin_group) . "')
        AND t.operation_type = 'buy'
        GROUP BY price_currency
        ORDER BY cnt DESC
        LIMIT 1
    ");
    $currency_row = $currency_query->fetch();
    if ($currency_row) {
        $stable_currency = $currency_row['price_currency'];
    }
}

// ============================================================
// ФОРМАТИРОВАНИЕ КАЖДОГО АКТИВА
// ============================================================

foreach ($my_assets as &$asset) {
    $quantity = floatval($asset['total_quantity']);
    $current_price = floatval($asset['current_price']);
    $current_price_currency = $asset['current_price_currency'];
    
    // ПРОВЕРКА: является ли актив стейблкоином
    $is_stablecoin = in_array($asset['symbol'], $stablecoin_group);
    
    if ($is_stablecoin) {
        // Для стейблкоинов используем ОБЩУЮ среднюю цену
        $avg_price = $stable_avg_price;
        $avg_price_currency = $stable_currency;
        
        // Стоимость покупки = остаток * общая средняя цена
        $purchase_cost = $quantity * $avg_price;
        
        // Текущая цена для стейблкоина всегда 1 в валюте привязки
        // Но для отображения используем последнюю цену из trades
        if ($asset['symbol'] == 'USD') {
            $current_price_display = 1.00;
            $current_price_currency_display = 'USD';
        } else {
            $current_price_display = $current_price;
            $current_price_currency_display = $current_price_currency;
        }
    } else {
        // Для обычных активов
        $avg_price = floatval($asset['avg_price']);
        $avg_price_currency = $asset['avg_price_currency'];
        $purchase_cost = floatval($asset['purchase_cost']);
        $current_price_display = $current_price;
        $current_price_currency_display = $current_price_currency;
    }
    
    // Для активов без цены
    if ($avg_price == 0 || $avg_price_currency == null) {
        $asset['avg_price_formatted'] = '—';
        $asset['purchase_cost_formatted'] = '—';
        $asset['current_price_formatted'] = '—';
        $asset['current_value_formatted'] = '—';
        $asset['profit_formatted'] = '—';
        $asset['profit_percent_formatted'] = '—';
        $asset['profit_class'] = 'profit-neutral';
        
        // Форматирование количества
        if ($asset['type'] == 'crypto') {
            if ($quantity < 1) {
                $formatted = rtrim(rtrim(number_format($quantity, 8, ',', ' '), '0'), ',');
                $asset['quantity_formatted'] = $formatted;
            } else {
                $formatted = number_format($quantity, 8, ',', ' ');
                $formatted = rtrim(rtrim($formatted, '0'), ',');
                $asset['quantity_formatted'] = $formatted;
            }
        } elseif ($asset['type'] == 'stock') {
            $asset['quantity_formatted'] = number_format($quantity, 0, '.', ' ') . ' шт';
        } else {
            $asset['quantity_formatted'] = number_format($quantity, 2, ',', ' ');
        }
        continue;
    }

// ============================================================
// ФОРМАТИРОВАНИЕ СРЕДНЕЙ ЦЕНЫ (ТОЛЬКО ЧИСЛО, без символа)
// ============================================================

// Форматируем только число (без валюты)
if ($avg_price_currency == 'RUB') {
    $asset['avg_price_formatted'] = number_format($avg_price, 2, '.', ' ');
} elseif ($asset['type'] == 'crypto' && ($avg_price_currency == 'USDT' || $avg_price_currency == 'USD')) {
    $asset['avg_price_formatted'] = number_format($avg_price, 4, '.', ' ');
} elseif ($asset['type'] == 'crypto') {
    $asset['avg_price_formatted'] = number_format($avg_price, 6, '.', ' ');
} else {
    $asset['avg_price_formatted'] = number_format($avg_price, 2, '.', ' ');
}

// Сохраняем валюту для отображения в span
$asset['avg_price_currency_display'] = $avg_price_currency;

// ============================================================
// ФОРМАТИРОВАНИЕ ТЕКУЩЕЙ ЦЕНЫ (ТОЛЬКО ЧИСЛО)
// ============================================================

if ($current_price_currency_display == 'RUB') {
    $asset['current_price_formatted'] = number_format($current_price_display, 2, '.', ' ');
} elseif ($asset['type'] == 'crypto' && ($current_price_currency_display == 'USDT' || $current_price_currency_display == 'USD')) {
    $asset['current_price_formatted'] = number_format($current_price_display, 4, '.', ' ');
} elseif ($asset['type'] == 'crypto') {
    $asset['current_price_formatted'] = number_format($current_price_display, 6, '.', ' ');
} else {
    $asset['current_price_formatted'] = number_format($current_price_display, 2, '.', ' ');
}

$asset['current_price_currency_display'] = $current_price_currency_display;

// ============================================================
// ФОРМАТИРОВАНИЕ ОСТАЛЬНЫХ ПОЛЕЙ (здесь оставляем символы)
// ============================================================

// Стоимость покупки и текущая стоимость - в долларах с символом
$asset['purchase_cost_formatted'] = $purchase_cost_usd > 0 ? number_format($purchase_cost_usd, 2, '.', ' ') . ' $' : '—';
$asset['current_value_formatted'] = $current_value_usd > 0 ? number_format($current_value_usd, 2, '.', ' ') . ' $' : '—';

// Прибыль в долларах с символом
if ($profit != 0) {
    $asset['profit_formatted'] = ($profit > 0 ? '+' : '') . number_format(abs($profit), 2, '.', ' ') . ' $';
    $asset['profit_percent_formatted'] = ($profit_percent > 0 ? '+' : '') . number_format(abs($profit_percent), 2, '.', ' ') . '%';
    $asset['profit_class'] = $profit > 0 ? 'profit-positive' : 'profit-negative';
} else {
    $asset['profit_formatted'] = '0 $';
    $asset['profit_percent_formatted'] = '0%';
    $asset['profit_class'] = 'profit-neutral';
}
    
    // ============================================================
    // РАСЧЕТ ПРИБЫЛИ (всегда в USD)
    // ============================================================
    
    // Конвертируем среднюю цену в USD
    $avg_price_in_usd = $avg_price;
    if ($avg_price_currency == 'RUB') {
        $avg_price_in_usd = $avg_price / $usd_rub_rate;
    } elseif ($avg_price_currency == 'EUR') {
        $avg_price_in_usd = $avg_price * 1.08;
    }
    
    // Конвертируем текущую цену в USD
    $current_price_in_usd = $current_price_display;
    if ($current_price_currency_display == 'RUB') {
        $current_price_in_usd = $current_price_display / $usd_rub_rate;
    } elseif ($current_price_currency_display == 'EUR') {
        $current_price_in_usd = $current_price_display * 1.08;
    }
    
    // Стоимость покупки в USD
    $purchase_cost_usd = $quantity * $avg_price_in_usd;
    
    // Текущая стоимость в USD
    $current_value_usd = $quantity * $current_price_in_usd;
    
    // Прибыль/убыток
    $profit = $current_value_usd - $purchase_cost_usd;
    $profit_percent = $purchase_cost_usd > 0 ? ($profit / $purchase_cost_usd) * 100 : 0;
    
    // ============================================================
    // ФОРМАТИРОВАНИЕ ОСТАЛЬНЫХ ПОЛЕЙ
    // ============================================================
    
    $asset['purchase_cost_formatted'] = $purchase_cost_usd > 0 ? number_format($purchase_cost_usd, 2, '.', ' ') . ' $' : '—';
    $asset['current_value_formatted'] = $current_value_usd > 0 ? number_format($current_value_usd, 2, '.', ' ') . ' $' : '—';
    
    if ($profit != 0) {
        $asset['profit_formatted'] = ($profit > 0 ? '+' : '') . number_format(abs($profit), 2, '.', ' ') . ' $';
        $asset['profit_percent_formatted'] = ($profit_percent > 0 ? '+' : '') . number_format(abs($profit_percent), 2, '.', ' ') . '%';
        $asset['profit_class'] = $profit > 0 ? 'profit-positive' : 'profit-negative';
    } else {
        $asset['profit_formatted'] = '0 $';
        $asset['profit_percent_formatted'] = '0%';
        $asset['profit_class'] = 'profit-neutral';
    }
    
    // ============================================================
    // ФОРМАТИРОВАНИЕ КОЛИЧЕСТВА
    // ============================================================
    
    if ($asset['type'] == 'crypto') {
        if ($asset['symbol'] == 'USDT' || $asset['symbol'] == 'USDC') {
            $asset['quantity_formatted'] = number_format($quantity, 0, '.', ' ');
        } else {
            if ($quantity < 1) {
                $formatted = rtrim(rtrim(number_format($quantity, 8, ',', ' '), '0'), ',');
                $asset['quantity_formatted'] = $formatted;
            } else {
                $formatted = number_format($quantity, 8, ',', ' ');
                $formatted = rtrim(rtrim($formatted, '0'), ',');
                $asset['quantity_formatted'] = $formatted;
            }
        }
    } elseif ($asset['type'] == 'stock') {
        $asset['quantity_formatted'] = number_format($quantity, 0, '.', ' ') . ' шт';
    } elseif ($asset['type'] == 'currency') {
        $asset['quantity_formatted'] = number_format($quantity, 0, '.', ' ');
    } elseif ($asset['type'] == 'bond') {
        $asset['quantity_formatted'] = number_format($quantity, 2, ',', ' ');
    } else {
        $asset['quantity_formatted'] = number_format($quantity, 2, ',', ' ');
    }
}
unset($asset);


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

// Функция для перевода названий секторов (для РФ)
function translateSectorRu($sectorName) {
    $translations = [
        'Energy' => 'Энергетика',
        'Financial' => 'Финансы',
        'Technology' => 'Технологии',
        'Healthcare' => 'Здравоохранение',
        'Consumer Cyclical' => 'Потребительский сектор',
        'Consumer Defensive' => 'Защитный сектор',
        'Industrials' => 'Промышленность',
        'Communication Services' => 'Связь и медиа',
        'Utilities' => 'Коммунальные услуги',
        'Real Estate' => 'Недвижимость',
        'Basic Materials' => 'Сырьевые материалы',
        'Materials' => 'Материалы',
        'Нефть и газ' => 'Нефть и газ',
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

// ============================================================================
// АВТОМАТИЧЕСКИЙ РАСЧЁТ РАСПРЕДЕЛЕНИЯ ПО СЕКТОРАМ ДЛЯ РОССИЙСКИХ АКЦИЙ
// ============================================================================

// Получаем все российские акции из портфеля с их секторами
$stmt = $pdo->query("
    SELECT 
        COALESCE(a.sector, 'Другое') as sector_name,
        a.id as asset_id,
        a.symbol,
        a.name,
        p.quantity,
        p.average_buy_price,
        p.currency_code,
        -- Рассчитываем стоимость в USD
        CASE 
            WHEN p.currency_code = 'RUB' THEN (p.quantity * COALESCE(p.average_buy_price, 0)) / " . $usd_rub_rate . "
            ELSE p.quantity * COALESCE(p.average_buy_price, 0)
        END as value_usd
    FROM portfolio p
    JOIN assets a ON p.asset_id = a.id
    WHERE a.type IN ('stock', 'etf') 
        AND a.currency_code = 'RUB'
        AND p.quantity > 0
    ORDER BY value_usd DESC
");

$ru_assets = $stmt->fetchAll();

// Группируем по секторам
$ru_sectors_data = [];
$total_ru_value_usd = 0;

foreach ($ru_assets as $asset) {
    $sector = $asset['sector_name'];
    $value_usd = floatval($asset['value_usd']);
    
    if (!isset($ru_sectors_data[$sector])) {
        $ru_sectors_data[$sector] = 0;
    }
    $ru_sectors_data[$sector] += $value_usd;
    $total_ru_value_usd += $value_usd;
}

// Формируем массив для отображения с процентами
$ru_sectors = [];
if ($total_ru_value_usd > 0) {
    foreach ($ru_sectors_data as $sector_name => $value_usd) {
        $percentage = round(($value_usd / $total_ru_value_usd) * 100, 1);
        if ($percentage > 0) {
            $ru_sectors[] = [
                'original_name' => $sector_name,           // сохраняем оригинал
                'sector_name' => translateSectorRu($sector_name), // переведённое название
                'percentage' => $percentage,
                'value_usd' => $value_usd
            ];
        }
    }
    // Сортируем по убыванию процента
    usort($ru_sectors, function($a, $b) {
        return $b['percentage'] <=> $a['percentage'];
    });
}

$has_ru_data = !empty($ru_sectors);

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
                $new_quantity = $existing['quantity'] + $quantity;
                $new_avg_price = (($existing['quantity'] * $existing['average_buy_price']) + ($quantity * $price)) / $new_quantity;
                
                $stmt = $pdo->prepare("
                    UPDATE portfolio 
                    SET quantity = ?, average_buy_price = ?, network = COALESCE(?, network)
                    WHERE id = ?
                ");
                $stmt->execute([$new_quantity, $new_avg_price, $network, $existing['id']]);
            } else {
                // Добавляем новый актив (с поддержкой сети)
                $stmt = $pdo->prepare("
                    INSERT INTO portfolio (asset_id, platform_id, quantity, average_buy_price, currency_code, network)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$asset_id, $platform_id, $quantity, $price, $price_currency, $network]);
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
        
        // ============================================================================
        // ДОБАВЛЯЕМ НА ПЛАТФОРМУ ПОЛУЧАТЕЛЯ (с поддержкой сети)
        // ============================================================================
        $stmt = $pdo->prepare("
            INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code, network)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                quantity = quantity + VALUES(quantity),
                network = COALESCE(VALUES(network), network)
        ");
        $stmt->execute([$asset_id, $to_platform_id, $quantity, $currency_code, $to_network]);
        
        // ============================================================================
        // ОБРАБАТЫВАЕМ КОМИССИЮ, если есть
        // ============================================================================
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
        
        // Получаем максимальный sort_order в отдельном запросе
        $stmt_max = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM expense_categories");
        $max_order = $stmt_max->fetch();
        $next_order = $max_order['next_order'];
        
        $stmt = $pdo->prepare("
            INSERT INTO expense_categories (name, name_ru, icon, color, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $name_ru, $icon, $color, $next_order]);
        
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
                p.network,
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
            case 'get_asset_distribution':
                $asset_id = intval($_POST['asset_id'] ?? 0);
                $symbol = $_POST['symbol'] ?? '';
                
                if (!$asset_id && !$symbol) {
                    echo json_encode(['success' => false, 'message' => 'Актив не указан']);
                    exit;
                }
                
                // Если передан symbol, найдем asset_id
                if (!$asset_id && $symbol) {
                    $stmt = $pdo->prepare("SELECT id FROM assets WHERE symbol = ?");
                    $stmt->execute([$symbol]);
                    $asset = $stmt->fetch();
                    $asset_id = $asset ? $asset['id'] : 0;
                }
                
                // Получаем распределение по площадкам
                $stmt = $pdo->prepare("
                    SELECT 
                        pl.name as platform_name,
                        p.quantity,
                        p.network
                    FROM portfolio p
                    JOIN platforms pl ON p.platform_id = pl.id
                    WHERE p.asset_id = ? AND p.quantity > 0
                    ORDER BY p.quantity DESC
                ");
                $stmt->execute([$asset_id]);
                $platforms = $stmt->fetchAll();
                
                // Получаем распределение по сетям (только для крипто)
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(p.network, 'Без сети') as network,
                        SUM(p.quantity) as quantity
                    FROM portfolio p
                    WHERE p.asset_id = ? AND p.quantity > 0
                    GROUP BY p.network
                    ORDER BY quantity DESC
                ");
                $stmt->execute([$asset_id]);
                $networks = $stmt->fetchAll();
                
                // Общее количество
                $total = array_sum(array_column($platforms, 'quantity'));
                
                echo json_encode([
                    'success' => true,
                    'total_quantity' => $total,
                    'platforms' => $platforms,
                    'networks' => $networks
                ]);
                exit;
                break;

            case 'get_asset_purchase_history':
                $asset_id = intval($_POST['asset_id'] ?? 0);
                
                if (!$asset_id) {
                    echo json_encode(['success' => false, 'message' => 'Актив не указан']);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    SELECT 
                        t.operation_date,
                        t.quantity,
                        t.price,
                        t.price_currency,
                        p.name as platform_name
                    FROM trades t
                    JOIN platforms p ON t.platform_id = p.id
                    WHERE t.asset_id = ? AND t.operation_type = 'buy'
                    ORDER BY t.operation_date DESC
                ");
                $stmt->execute([$asset_id]);
                $history = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'history' => $history
                ]);
                exit;
                break;
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
                    
                    // Также добавляем валюту в таблицу currencies, если это криптовалюта или новая фиатная валюта
                    if ($type === 'crypto') {
                        // Проверяем, существует ли валюта в таблице currencies
                        $check_currency = $pdo->prepare("SELECT code FROM currencies WHERE code = ?");
                        $check_currency->execute([$symbol]);
                        $currency_exists = $check_currency->fetch();
                        
                        if (!$currency_exists) {
                            $stmt_currency = $pdo->prepare("
                                INSERT INTO currencies (code, name, type) 
                                VALUES (?, ?, 'crypto')
                            ");
                            $stmt_currency->execute([$symbol, $name]);
                        }
                    } elseif ($type === 'currency') {
                        // Для валют также добавляем в таблицу currencies
                        $check_currency = $pdo->prepare("SELECT code FROM currencies WHERE code = ?");
                        $check_currency->execute([$symbol]);
                        $currency_exists = $check_currency->fetch();
                        
                        if (!$currency_exists) {
                            $stmt_currency = $pdo->prepare("
                                INSERT INTO currencies (code, name, type, symbol) 
                                VALUES (?, ?, 'fiat', ?)
                            ");
                            $stmt_currency->execute([$symbol, $name, $symbol]);
                        }
                    }
                    
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

            case 'add_expense_with_deduction':
                $result = addExpenseWithDeduction(
                    $pdo,
                    $_POST['platform_id'],
                    $_POST['asset_id'],
                    $_POST['amount'],
                    $_POST['description'] ?? '',
                    $_POST['category_id'],
                    $_POST['expense_date']
                );
                $response = $result;
                break;

            case 'sell_from_platform':
                $result = sellFromPlatform(
                    $pdo,
                    $_POST['asset_id'],
                    $_POST['platform_id'],
                    $_POST['quantity'],
                    $_POST['price'],
                    $_POST['price_currency'],
                    $_POST['commission'] ?? 0,
                    $_POST['commission_currency'] ?? null,
                    $_POST['operation_date'],
                    $_POST['notes'] ?? ''
                );
                $response['success'] = $result['success'];
                $response['message'] = $result['success'] ? 'Продажа успешно выполнена' : 'Ошибка: ' . $result['message'];
                break;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Функция продажи с выбранной площадки
function sellFromPlatform($pdo, $asset_id, $platform_id, $quantity, $price, $price_currency, $commission, $commission_currency, $date, $notes) {
    try {
        $pdo->beginTransaction();
        
        // 1. Проверяем наличие актива на площадке
        $stmt = $pdo->prepare("
            SELECT id, quantity, average_buy_price 
            FROM portfolio 
            WHERE asset_id = ? AND platform_id = ?
        ");
        $stmt->execute([$asset_id, $platform_id]);
        $portfolio = $stmt->fetch();
        
        if (!$portfolio) {
            throw new Exception("Актив не найден на выбранной площадке");
        }
        
        if ($portfolio['quantity'] < $quantity) {
            throw new Exception("Недостаточно актива. Доступно: " . $portfolio['quantity']);
        }
        
        // 2. Создаем запись о продаже
        $stmt = $pdo->prepare("
            INSERT INTO trades (
                operation_type, asset_id, platform_id, quantity, price, 
                price_currency, commission, commission_currency, operation_date, notes
            ) VALUES ('sell', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $asset_id, $platform_id, $quantity, $price,
            $price_currency, $commission, $commission_currency,
            $date, $notes
        ]);
        
        // 3. Уменьшаем количество в портфеле
        $new_quantity = $portfolio['quantity'] - $quantity;
        
        if ($new_quantity > 0) {
            $stmt = $pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $portfolio['id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
            $stmt->execute([$portfolio['id']]);
        }
        
        // 4. Добавляем полученные средства
        $total_income = $quantity * $price;
        
        $stmt = $pdo->prepare("SELECT id FROM assets WHERE symbol = ?");
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
        
        $pdo->commit();
        return ['success' => true, 'message' => ''];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Функция добавления расхода со списанием с площадки
function addExpenseWithDeduction($pdo, $platform_id, $asset_id, $amount, $description, $category_id, $date) {
    try {
        $pdo->beginTransaction();
        
        // Получаем символ актива
        $stmt = $pdo->prepare("SELECT symbol, currency_code FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch();
        
        if (!$asset) {
            throw new Exception("Актив не найден");
        }
        
        $currency_code = $asset['currency_code'] ?: $asset['symbol'];
        
        // Проверяем наличие средств на площадке
        $stmt = $pdo->prepare("
            SELECT id, quantity FROM portfolio 
            WHERE asset_id = ? AND platform_id = ?
        ");
        $stmt->execute([$asset_id, $platform_id]);
        $portfolio = $stmt->fetch();
        
        if (!$portfolio) {
            throw new Exception("На выбранной площадке нет актива " . $asset['symbol']);
        }
        
        if ($portfolio['quantity'] < $amount) {
            throw new Exception("Недостаточно средств. Доступно: " . $portfolio['quantity'] . " " . $asset['symbol'] . ", нужно: " . $amount);
        }
        
        // Списываем средства
        $new_quantity = $portfolio['quantity'] - $amount;
        
        if ($new_quantity > 0) {
            $stmt = $pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $portfolio['id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
            $stmt->execute([$portfolio['id']]);
        }
        
        // Добавляем запись о расходе
        $stmt = $pdo->prepare("
            INSERT INTO expenses (amount, currency_code, category_id, description, expense_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$amount, $currency_code, $category_id, $description, $date]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Расход успешно добавлен'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================================================
// ПЕРЕДАЧА ДАННЫХ В JAVASCRIPT
// ============================================================================

$platforms_json = json_encode($platforms);
$assets_json = json_encode($assets_list);
$currencies_json = json_encode($all_currencies);
$fiat_currencies_json = json_encode($fiat_currencies);
?>