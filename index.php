<?php

// Для отладки - выводим информацию о запросе
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' - ' . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

// ============================================================================
// ТОЧКА ВХОДА (РОУТЕР)
// ============================================================================

// Определяем корневую директорию проекта
define('ROOT_PATH', __DIR__);
define('VIEWS_PATH', ROOT_PATH . '/views');

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/helpers/Formatter.php';

// Автозагрузка классов
spl_autoload_register(function ($class) {
    $paths = [
        ROOT_PATH . '/repositories/',
        ROOT_PATH . '/services/',
        ROOT_PATH . '/controllers/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Получаем подключение к БД
$pdo = getDbConnection();

// Обработка POST запросов (API)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiController = new ApiController($pdo);
    $response = $apiController->handleRequest();
    
    if (!empty($response)) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// GET запросы - определяем маршрут
$page = $_GET['page'] ?? 'dashboard';

switch ($page) {
    case 'operations':
        $controller = new OperationController($pdo);
        $data = $controller->getOperationsData(
            $_GET['p'] ?? 1,
            [
                'type' => $_GET['type'] ?? 'all',
                'platform_id' => $_GET['platform'] ?? 0,
                'asset_id' => $_GET['asset'] ?? 0,
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? ''
            ]
        );
        
        include VIEWS_PATH . '/operations/index.php';
        break;
        
    case 'get_operations':
        $pageNum = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
        
        $pageNum = max(1, $pageNum);
        $perPage = max(1, $perPage);
        
        $filters = [
            'type' => $_GET['type'] ?? 'all',
            'platform_id' => $_GET['platform'] ?? 0,
            'asset_id' => $_GET['asset'] ?? 0,
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        
        $operationRepo = new OperationRepository($pdo);
        $data = $operationRepo->getAll($filters, $pageNum, $perPage);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'operations' => $data['operations'],
            'pagination' => [
                'current_page' => $pageNum,
                'total_pages' => $data['total_pages'],
                'total' => $data['total'],
                'per_page' => $perPage
            ]
        ]);
        exit;
        break;
    case 'dashboard':
    default:
        $controller = new DashboardController($pdo);
        $data = $controller->getDashboardData();
        
        include VIEWS_PATH . '/dashboard/index.php';
        break;
}