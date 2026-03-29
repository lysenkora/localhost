<?php
// ============================================================================
// ТОЧКА ВХОДА (РОУТЕР)
// ============================================================================

// Определяем корневую директорию проекта
define('ROOT_PATH', __DIR__);
define('VIEWS_PATH', ROOT_PATH . '/views');

// Подключаем все необходимые файлы вручную
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/helpers/Formatter.php';

// Подключаем репозитории
require_once ROOT_PATH . '/repositories/PlatformRepository.php';
require_once ROOT_PATH . '/repositories/AssetRepository.php';
require_once ROOT_PATH . '/repositories/TradeRepository.php';
require_once ROOT_PATH . '/repositories/DepositRepository.php';
require_once ROOT_PATH . '/repositories/TransferRepository.php';
require_once ROOT_PATH . '/repositories/OperationRepository.php';
require_once ROOT_PATH . '/repositories/PortfolioRepository.php';
require_once ROOT_PATH . '/repositories/NetworkRepository.php';
require_once ROOT_PATH . '/repositories/NoteRepository.php';
require_once ROOT_PATH . '/repositories/LimitOrderRepository.php';
require_once ROOT_PATH . '/repositories/ExpenseRepository.php';

// Подключаем сервисы
require_once ROOT_PATH . '/services/CalculationService.php';

// Подключаем контроллеры
require_once ROOT_PATH . '/controllers/DashboardController.php';
require_once ROOT_PATH . '/controllers/ApiController.php';
require_once ROOT_PATH . '/controllers/OperationController.php';

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
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

switch ($page) {
    case 'operations':
        $controller = new OperationController($pdo);
        $data = $controller->getOperationsData(
            isset($_GET['p']) ? (int)$_GET['p'] : 1,
            [
                'type' => isset($_GET['type']) ? $_GET['type'] : 'all',
                'platform_id' => isset($_GET['platform']) ? (int)$_GET['platform'] : 0,
                'asset_id' => isset($_GET['asset']) ? (int)$_GET['asset'] : 0,
                'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
                'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : ''
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
            'type' => isset($_GET['type']) ? $_GET['type'] : 'all',
            'platform_id' => isset($_GET['platform']) ? (int)$_GET['platform'] : 0,
            'asset_id' => isset($_GET['asset']) ? (int)$_GET['asset'] : 0,
            'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : ''
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