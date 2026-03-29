<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'helpers/functions.php';
require_once 'helpers/Formatter.php';
require_once 'repositories/OperationRepository.php';
require_once 'controllers/DashboardController.php';
require_once 'controllers/ApiController.php';
require_once 'services/CalculationService.php';

echo "<pre>";
echo "Database: " . (class_exists('Database') ? 'OK' : 'NOT FOUND') . "\n";
echo "Formatter: " . (class_exists('Formatter') ? 'OK' : 'NOT FOUND') . "\n";
echo "OperationRepository: " . (class_exists('OperationRepository') ? 'OK' : 'NOT FOUND') . "\n";
echo "DashboardController: " . (class_exists('DashboardController') ? 'OK' : 'NOT FOUND') . "\n";
echo "ApiController: " . (class_exists('ApiController') ? 'OK' : 'NOT FOUND') . "\n";
echo "</pre>";