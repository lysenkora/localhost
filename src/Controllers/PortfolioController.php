<?php
// src/Controllers/PortfolioController.php

namespace Controllers;

use Models\PortfolioModel;
use Models\AssetsModel;

class PortfolioController {
    private $portfolioModel;
    private $assetsModel;
    
    public function __construct() {
        $this->portfolioModel = new PortfolioModel();
        $this->assetsModel = new AssetsModel();
    }
    
    /**
     * Главная страница - обзор портфеля
     */
    public function index() {
        $portfolio = $this->portfolioModel->getPortfolio();
        $summary = $this->portfolioModel->getSummaryByType();
        
        // Подсчет общей стоимости (упрощенно, без реальных курсов)
        $totalValue = 0;
        foreach ($portfolio as $item) {
            // Здесь нужна логика расчета стоимости с учетом курсов
            // Пока просто считаем количество
            $totalValue += $item['quantity'];
        }
        
        require_once __DIR__ . '/../Views/layout.php';
    }
    
    /**
     * Редактирование позиции портфеля
     */
    public function edit($id) {
        if (!$id) {
            header('Location: /');
            exit;
        }
        
        $item = $this->portfolioModel->getPortfolioItem($id);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $quantity = $_POST['quantity'] ?? 0;
            $avgPrice = $_POST['average_buy_price'] ?? null;
            
            $this->portfolioModel->updatePortfolioItem($id, $quantity, $avgPrice);
            header('Location: /');
            exit;
        }
        
        require_once __DIR__ . '/../Views/portfolio/edit.php';
    }
}