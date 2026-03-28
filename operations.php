<?php
// operations.php - простая страница для отображения операций
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/Formatter.php';

$pdo = Database::getInstance();

// Получаем последние операции
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
    LIMIT 20
");

$operations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Операции</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>История операций</h1>
    <table>
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
                <td><?= Formatter::date($op['date']) ?></td>
                <td><?= htmlspecialchars($op['type']) ?></td>
                <td><?= htmlspecialchars($op['description']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><a href="index.php">← На главную</a></p>
</body>
</html>