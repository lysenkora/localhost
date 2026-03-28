<?php
return [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'investment_portfolio',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'name' => 'Планеро.Инвестиции',
        'debug' => true,
        'timezone' => 'Europe/Moscow'
    ],
    'pagination' => [
        'operations_per_page' => 20,
        'recent_operations' => 5
    ],
    'currencies' => [
        'default' => 'USD',
        'popular' => ['RUB', 'USD', 'EUR', 'USDT']
    ]
];