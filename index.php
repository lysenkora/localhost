<?php
// Временный минимальный index.php для теста
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Очищаем буфер
if (ob_get_level()) ob_end_clean();
ob_start();

// Обработка API запроса - проверяем наличие параметра api
if (isset($_GET['api']) && $_GET['api'] === 'get_operations') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'test' => true,
        'message' => 'API handler reached',
        'get' => $_GET
    ]);
    exit;
}

// Обработка обычных запросов
echo "<h1>Dashboard</h1>";
echo "<p>Page parameter: " . htmlspecialchars($_GET['page'] ?? 'none') . "</p>";
echo "<p>Test API: <a href='?api=get_operations&page=1'>Click here</a></p>";