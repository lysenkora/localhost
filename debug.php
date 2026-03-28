<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Mode</h1>";

// Проверяем пути
echo "<h2>Paths:</h2>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "Current Directory: " . getcwd() . "<br>";

// Проверяем наличие файлов
$files_to_check = [
    'config/database.php',
    'config/config.php',
    'config/constants.php',
    'helpers/functions.php',
    'helpers/Formatter.php'
];

echo "<h2>File Check:</h2>";
foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    echo $file . ": " . (file_exists($full_path) ? "OK" : "NOT FOUND") . "<br>";
}

// Проверяем автозагрузку
echo "<h2>Autoload Test:</h2>";
spl_autoload_register(function ($class) {
    echo "Trying to load: $class<br>";
    $paths = [
        __DIR__ . '/models/',
        __DIR__ . '/repositories/',
        __DIR__ . '/services/',
        __DIR__ . '/controllers/',
        __DIR__ . '/helpers/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            echo "Found: $file<br>";
            require_once $file;
            return true;
        }
    }
    echo "NOT FOUND: $class<br>";
    return false;
});

// Пробуем загрузить классы
echo "<h2>Class Loading Test:</h2>";
$classes = ['Database', 'Formatter', 'PortfolioService'];
foreach ($classes as $class) {
    echo "Loading $class... ";
    if (class_exists($class)) {
        echo "OK<br>";
    } else {
        echo "FAILED<br>";
    }
}