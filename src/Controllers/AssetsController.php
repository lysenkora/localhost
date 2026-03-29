<?php
// src/Controllers/AssetsController.php

namespace Controllers;

use Models\AssetsModel;

class AssetsController {
    private $assetsModel;
    
    public function __construct() {
        $this->assetsModel = new AssetsModel();
    }
    
    public function index() {
        $assets = $this->assetsModel->getAllAssets();
        require_once __DIR__ . '/../Views/layout.php';
    }
    
    public function view($id) {
        $asset = $this->assetsModel->getAsset($id);
        if (!$asset) {
            header('Location: /assets');
            exit;
        }
        require_once __DIR__ . '/../Views/assets/view.php';
    }
}