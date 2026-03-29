<?php
// ============================================================================
// КОНСТАНТЫ
// ============================================================================

// Настройки по умолчанию
define('DEFAULT_CURRENCY', 'USD');
define('DEFAULT_THEME', 'light');
define('OPERATIONS_PER_PAGE', 5);
define('LIMIT_ORDERS_PER_PAGE', 3);
define('NOTES_PER_PAGE', 3);

// Цвета для графиков
define('CHART_COLORS', [
    '#4a9eff', '#1a5cff', '#ff9f4a', '#2ecc71', '#95a5a6', 
    '#e74c3c', '#9b59b6', '#f1c40f', '#1abc9c', '#e67e22'
]);

// Типы операций
define('OPERATION_TYPES', [
    'buy' => ['class' => 'badge-buy', 'icon' => 'fa-arrow-down', 'color' => '#00a86b'],
    'sell' => ['class' => 'badge-sell', 'icon' => 'fa-arrow-up', 'color' => '#e53e3e'],
    'deposit' => ['class' => 'badge-deposit', 'icon' => 'fa-plus-circle', 'color' => '#1a5cff'],
    'transfer' => ['class' => 'badge-transfer', 'icon' => 'fa-exchange-alt', 'color' => '#ff9f4a']
]);

// Типы активов
define('ASSET_TYPES', [
    'crypto' => ['icon' => 'fa-bitcoin', 'color' => '#f7931a'],
    'stock' => ['icon' => 'fa-chart-line', 'color' => '#00a86b'],
    'etf' => ['icon' => 'fa-chart-pie', 'color' => '#4a9eff'],
    'bond' => ['icon' => 'fa-file-invoice', 'color' => '#9b59b6'],
    'currency' => ['icon' => 'fa-money-bill', 'color' => '#1a5cff'],
    'other' => ['icon' => 'fa-coins', 'color' => '#6b7a8f']
]);