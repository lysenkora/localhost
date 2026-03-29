<?php
// public/index.php

require_once __DIR__ . '/../src/Models/Database.php';
require_once __DIR__ . '/../src/Models/PortfolioModel.php';
require_once __DIR__ . '/../src/Models/AssetsModel.php';
require_once __DIR__ . '/../src/Models/TradesModel.php';
require_once __DIR__ . '/../src/Models/DepositsModel.php';
require_once __DIR__ . '/../src/Controllers/PortfolioController.php';
require_once __DIR__ . '/../src/Controllers/AssetsController.php';
require_once __DIR__ . '/../src/Controllers/TradesController.php';
require_once __DIR__ . '/../src/Controllers/DepositsController.php';

use Controllers\PortfolioController;
use Controllers\AssetsController;
use Controllers\TradesController;
use Controllers\DepositsController;

$action = $_GET['action'] ?? 'portfolio';
$method = $_GET['method'] ?? 'index';
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'assets':
        $controller = new AssetsController();
        if ($method === 'view' && $id) {
            $controller->view($id);
        } else {
            $controller->index();
        }
        break;
        
    case 'trades':
        $controller = new TradesController();
        if ($method === 'add') {
            $controller->add();
        } else {
            $controller->index();
        }
        break;
        
    case 'deposits':
        $controller = new DepositsController();
        $controller->index();
        break;
        
    case 'portfolio':
    default:
        $controller = new PortfolioController();
        if ($method === 'edit' && $id) {
            $controller->edit($id);
        } else {
            $controller->index();
        }
        break;
}