<?php
// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Форматирование числа с пробелами между разрядами
 */
function formatNumber($value, $decimals = null) {
    if ($value === null || $value === '') return '—';
    
    $num = (float)$value;
    
    if ($decimals === null) {
        // Автоматическое определение количества знаков
        if (floor($num) == $num) {
            $decimals = 0;
        } elseif (abs($num) < 0.01) {
            $decimals = 8;
        } elseif (abs($num) < 1) {
            $decimals = 6;
        } else {
            $decimals = 2;
        }
    }
    
    $formatted = number_format($num, $decimals, '.', ' ');
    // Убираем лишние нули в конце
    $formatted = preg_replace('/\.?0+$/', '', $formatted);
    
    return $formatted;
}

/**
 * Форматирование даты
 */
function formatDate($date, $format = 'd.m.Y') {
    if (!$date) return '—';
    $timestamp = is_string($date) ? strtotime($date) : $date;
    return date($format, $timestamp);
}

/**
 * Перевод названий секторов
 */
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

/**
 * Получение иконки для актива
 */
function getAssetIcon($symbol) {
    $icons = [
        'BTC' => ['icon' => 'fab fa-bitcoin', 'color' => '#f7931a'],
        'ETH' => ['icon' => 'fab fa-ethereum', 'color' => '#627eea'],
        'USDT' => ['icon' => 'fas fa-coins', 'color' => '#26a17b'],
        'USDC' => ['icon' => 'fas fa-coins', 'color' => '#26a17b'],
        'SOL' => ['icon' => 'fas fa-sun', 'color' => '#14f195'],
        'BNB' => ['icon' => 'fas fa-chart-line', 'color' => '#f3ba2f'],
        'RUB' => ['icon' => 'fas fa-ruble-sign', 'color' => '#1a5cff'],
        'USD' => ['icon' => 'fas fa-dollar-sign', 'color' => '#00a86b'],
        'EUR' => ['icon' => 'fas fa-euro-sign', 'color' => '#2ecc71'],
    ];
    return $icons[$symbol] ?? ['icon' => 'fas fa-chart-line', 'color' => '#6b7a8f'];
}

/**
 * Получение иконки для сети
 */
function getNetworkIcon($networkName) {
    $networkName = strtoupper($networkName);
    if (strpos($networkName, 'ERC') !== false) return 'fab fa-ethereum';
    if (strpos($networkName, 'BEP') !== false) return 'fas fa-bolt';
    if (strpos($networkName, 'TRC') !== false) return 'fab fa-t';
    if ($networkName === 'SOL') return 'fas fa-sun';
    if ($networkName === 'BTC') return 'fab fa-bitcoin';
    return 'fas fa-network-wired';
}

/**
 * Экранирование HTML
 */
function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Получение текущего курса USD/RUB
 */
function getUsdRubRate($pdo) {
    $stmt = $pdo->query("
        SELECT rate FROM exchange_rates 
        WHERE from_currency = 'USD' AND to_currency = 'RUB' 
        ORDER BY date DESC LIMIT 1
    ");
    $rate_data = $stmt->fetch();
    return $rate_data ? (float)$rate_data['rate'] : 92.50;
}

/**
 * Получение текущей темы пользователя
 */
function getUserTheme($pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE setting_key = 'theme'");
    $stmt->execute();
    $theme_data = $stmt->fetch();
    return $theme_data ? $theme_data['setting_value'] : 'light';
}

/**
 * Сохранение темы
 */
function saveUserTheme($pdo, $theme) {
    $stmt = $pdo->prepare("
        INSERT INTO user_settings (setting_key, setting_value) 
        VALUES ('theme', ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    return $stmt->execute([$theme]);
}