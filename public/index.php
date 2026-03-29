<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Определяем пути
define('ROOT_PATH', __DIR__);
define('CONFIG_PATH', ROOT_PATH . '/config');
define('HELPERS_PATH', ROOT_PATH . '/helpers');
define('MODELS_PATH', ROOT_PATH . '/models');
define('REPOSITORIES_PATH', ROOT_PATH . '/repositories');
define('SERVICES_PATH', ROOT_PATH . '/services');
define('CONTROLLERS_PATH', ROOT_PATH . '/controllers');
define('VIEWS_PATH', ROOT_PATH . '/views');

// Подключаем необходимые файлы
require_once CONFIG_PATH . '/database.php';
require_once HELPERS_PATH . '/functions.php';
require_once HELPERS_PATH . '/Formatter.php';

// Автозагрузка классов
spl_autoload_register(function ($class) {
    $paths = [
        MODELS_PATH,
        REPOSITORIES_PATH,
        SERVICES_PATH,
        CONTROLLERS_PATH
    ];
    
    foreach ($paths as $path) {
        $file = $path . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});

// Получаем PDO
$pdo = Database::getInstance();

// Роутинг
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

if ($path === '/' || $path === '/index.php') {
    $controller = new DashboardController($pdo);
    $controller->index();
} elseif ($path === '/operations.php') {
    $controller = new OperationController($pdo);
    $controller->index();
} else {
    http_response_code(404);
    echo "Page not found";
}