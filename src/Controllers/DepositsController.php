<?php
// src/Controllers/DepositsController.php

namespace Controllers;

use Models\DepositsModel;

class DepositsController {
    private $depositsModel;
    
    public function __construct() {
        $this->depositsModel = new DepositsModel();
    }
    
    public function index() {
        $deposits = $this->depositsModel->getAllDeposits();
        $summary = $this->depositsModel->getTotalDepositsByPlatform();
        require_once __DIR__ . '/../Views/layout.php';
    }
}