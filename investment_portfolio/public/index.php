<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/Formatter.php';

// Автозагрузка классов
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../models/',
        __DIR__ . '/../repositories/',
        __DIR__ . '/../services/',
        __DIR__ . '/../controllers/',
        __DIR__ . '/../helpers/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

$pdo = Database::getInstance();

// Роутинг
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($uri, PHP_URL_PATH);

// API роуты
if (strpos($path, '/api/') === 0) {
    $action = substr($path, 5);
    $controller = new ApiController($pdo);
    $controller->handle($action);
    exit;
}

// Основные страницы
switch ($path) {
    case '/':
    case '/dashboard':
        $controller = new DashboardController($pdo);
        $controller->index();
        break;
    case '/operations':
        $controller = new OperationController($pdo);
        $controller->index();
        break;
    default:
        // 404
        http_response_code(404);
        echo "Page not found";
}