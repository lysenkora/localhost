<?php
// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Определяем корневую директорию (теперь правильно)
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('HELPERS_PATH', ROOT_PATH . '/helpers');
define('VIEWS_PATH', ROOT_PATH . '/views');

// Проверяем наличие файлов
if (!file_exists(CONFIG_PATH . '/config.php')) {
    die("Config file not found at: " . CONFIG_PATH . '/config.php');
}
if (!file_exists(HELPERS_PATH . '/functions.php')) {
    die("Functions file not found at: " . HELPERS_PATH . '/functions.php');
}
if (!file_exists(HELPERS_PATH . '/Formatter.php')) {
    die("Formatter file not found at: " . HELPERS_PATH . '/Formatter.php');
}

require_once CONFIG_PATH . '/config.php';
require_once HELPERS_PATH . '/functions.php';
require_once HELPERS_PATH . '/Formatter.php';
require_once CONFIG_PATH . '/database.php';

// Получаем PDO соединение
try {
    $pdo = Database::getInstance();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Получаем текущую тему
$stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE setting_key = 'theme'");
$stmt->execute();
$theme_data = $stmt->fetch();
$current_theme = $theme_data ? $theme_data['setting_value'] : 'light';

// Получаем данные для дашборда
$total_usd = 0;
$total_rub = 0;

try {
    // Получаем общую стоимость
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(
            CASE 
                WHEN a.symbol = 'RUB' THEN p.quantity / (SELECT rate FROM exchange_rates WHERE from_currency = 'USD' AND to_currency = 'RUB' ORDER BY date DESC LIMIT 1)
                WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN p.quantity
                ELSE p.quantity * COALESCE(p.average_buy_price, 0)
            END
        ), 0) as total
        FROM portfolio p
        JOIN assets a ON p.asset_id = a.id
        WHERE p.quantity > 0
    ");
    $result = $stmt->fetch();
    $total_usd = (float)$result['total'];
    
    $stmt = $pdo->query("
        SELECT rate FROM exchange_rates 
        WHERE from_currency = 'USD' AND to_currency = 'RUB' 
        ORDER BY date DESC LIMIT 1
    ");
    $rate_data = $stmt->fetch();
    $usd_rub_rate = $rate_data ? (float)$rate_data['rate'] : 92.50;
    $total_rub = $total_usd * $usd_rub_rate;
    
} catch (Exception $e) {
    // Игнорируем ошибки БД для теста
}

// Получаем последние операции
$operations = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM (
            SELECT 'trade' as type, id, operation_date as date, 'Сделка' as description
            FROM trades
            UNION ALL
            SELECT 'deposit', id, deposit_date, 'Пополнение'
            FROM deposits
            UNION ALL
            SELECT 'transfer', id, transfer_date, 'Перевод'
            FROM transfers
        ) as ops
        ORDER BY date DESC
        LIMIT 5
    ");
    $operations = $stmt->fetchAll();
} catch (Exception $e) {
    // Игнорируем
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Планеро.Инвестиции</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: <?= $current_theme === 'dark' ? '#0C0E12' : '#f5f7fb' ?>;
            color: <?= $current_theme === 'dark' ? '#FFFFFF' : '#2c3e50' ?>;
            line-height: 1.6;
            padding: 24px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: <?= $current_theme === 'dark' ? '#15181C' : 'white' ?>;
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            border: <?= $current_theme === 'dark' ? '1px solid #2A2F36' : 'none' ?>;
        }
        
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: <?= $current_theme === 'dark' ? '#FFFFFF' : '#2c3e50' ?>;
        }
        
        .value-label {
            font-size: 14px;
            color: <?= $current_theme === 'dark' ? '#9AA5B5' : '#6b7a8f' ?>;
            font-weight: 500;
        }
        
        .value-amount {
            font-size: 36px;
            font-weight: 700;
        }
        
        #usdValue {
            color: #00a86b;
        }
        
        #rubValue {
            color: #1a5cff;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: <?= $current_theme === 'dark' ? '#15181C' : 'white' ?>;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            border: <?= $current_theme === 'dark' ? '1px solid #2A2F36' : 'none' ?>;
        }
        
        .stat-card h3 {
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .operations-table {
            width: 100%;
            border-collapse: collapse;
            background: <?= $current_theme === 'dark' ? '#15181C' : 'white' ?>;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            border: <?= $current_theme === 'dark' ? '1px solid #2A2F36' : 'none' ?>;
        }
        
        .operations-table th,
        .operations-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid <?= $current_theme === 'dark' ? '#2A2F36' : '#edf2f7' ?>;
        }
        
        .operations-table th {
            font-weight: 600;
            color: <?= $current_theme === 'dark' ? '#9AA5B5' : '#6b7a8f' ?>;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-trade {
            background: #e6f7e6;
            color: #00a86b;
        }
        
        .badge-deposit {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-transfer {
            background: #fff4e6;
            color: #ff9f4a;
        }
        
        .nav-links {
            margin-top: 20px;
            display: flex;
            gap: 16px;
        }
        
        .nav-links a {
            color: #1a5cff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Планеро.Инвестиции</h1>
            <div class="portfolio-value">
                <span class="value-label">Текущая стоимость портфеля</span>
                <div>
                    <span class="value-amount" id="usdValue"><?= number_format($total_usd, 2, '.', ' ') ?> $</span>
                    <br>
                    <span class="value-amount" id="rubValue"><?= number_format($total_rub, 0, '.', ' ') ?> ₽</span>
                </div>
            </div>
            <div class="nav-links">
                <a href="/operations.php">📋 Все операции</a>
                <a href="https://github.com/lysenkora/localhost" target="_blank">🐙 GitHub</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📊 Статус системы</h3>
                <p>✅ База данных: подключена</p>
                <p>✅ PHP: версия <?= phpversion() ?></p>
                <p>✅ Тема: <?= $current_theme === 'dark' ? 'Темная' : 'Светлая' ?></p>
            </div>
            <div class="stat-card">
                <h3>💰 Активы</h3>
                <p>Всего USD: <?= number_format($total_usd, 2, '.', ' ') ?> $</p>
                <p>Всего RUB: <?= number_format($total_rub, 0, '.', ' ') ?> ₽</p>
            </div>
        </div>
        
        <h3 style="margin-bottom: 16px;">📝 Последние операции</h3>
        
        <?php if (empty($operations)): ?>
        <div class="stat-card" style="text-align: center; padding: 40px;">
            <p>Нет операций для отображения</p>
        </div>
        <?php else: ?>
        <table class="operations-table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Тип</th>
                    <th>Описание</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($operations as $op): ?>
                <tr>
                    <td><?= date('d.m.Y', strtotime($op['date'])) ?></td>
                    <td>
                        <span class="badge badge-<?= $op['type'] ?>">
                            <?= $op['type'] == 'trade' ? 'Сделка' : ($op['type'] == 'deposit' ? 'Пополнение' : 'Перевод') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($op['description']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div style="margin-top: 24px; text-align: center; color: #6b7a8f; font-size: 12px;">
            <p>Планеро.Инвестиции v2.0 | Переход на новую архитектуру</p>
        </div>
    </div>
    
    <script>
        // Простое переключение темы
        document.addEventListener('DOMContentLoaded', function() {
            // Можно добавить кнопку переключения темы позже
        });
    </script>
</body>
</html>