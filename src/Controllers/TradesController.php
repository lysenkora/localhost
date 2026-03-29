<?php
// src/Controllers/TradesController.php

namespace Controllers;

use Models\TradesModel;
use Models\AssetsModel;

class TradesController {
    private $tradesModel;
    private $assetsModel;
    
    public function __construct() {
        $this->tradesModel = new TradesModel();
        $this->assetsModel = new AssetsModel();
    }
    
    public function index() {
        $trades = $this->tradesModel->getAllTrades();
        require_once __DIR__ . '/../Views/layout.php';
    }
    
    public function add() {
        $assets = $this->assetsModel->getAllAssets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->tradesModel->addTrade($_POST);
            if ($result) {
                header('Location: /trades');
                exit;
            }
        }
        
        require_once __DIR__ . '/../Views/trades/form.php';
    }
}