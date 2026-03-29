<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем файлы вручную
require_once 'config/database.php';
require_once 'helpers/Formatter.php';

echo "<pre>";
echo "=== Проверка загрузки классов ===\n\n";

echo "Class Database: ";
if (class_exists('Database')) {
    echo "OK\n";
} else {
    echo "FAILED - проверьте файл config/database.php\n";
}

echo "Class Formatter: ";
if (class_exists('Formatter')) {
    echo "OK\n";
} else {
    echo "FAILED - проверьте файл helpers/Formatter.php\n";
}

echo "\n=== Содержимое файла database.php ===\n";
echo file_get_contents('config/database.php');
echo "\n=== Содержимое файла Formatter.php ===\n";
echo file_get_contents('helpers/Formatter.php');

echo "</pre>";