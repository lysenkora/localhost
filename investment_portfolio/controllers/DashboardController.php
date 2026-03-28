<?php
require_once __DIR__ . '/../services/PortfolioService.php';
require_once __DIR__ . '/../services/StatisticsService.php';
require_once __DIR__ . '/../services/ThemeService.php';
require_once __DIR__ . '/../repositories/OperationRepository.php';
require_once __DIR__ . '/../repositories/LimitOrderRepository.php';
require_once __DIR__ . '/../repositories/NoteRepository.php';

class DashboardController {
    private $pdo;
    private $portfolioService;
    private $statisticsService;
    private $themeService;
    private $operationRepo;
    private $limitOrderRepo;
    private $noteRepo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->portfolioService = new PortfolioService($pdo);
        $this->statisticsService = new StatisticsService($pdo);
        $this->themeService = new ThemeService($pdo);
        $this->operationRepo = new OperationRepository($pdo);
        $this->limitOrderRepo = new LimitOrderRepository($pdo);
        $this->noteRepo = new NoteRepository($pdo);
    }
    
    public function index() {
        $data = [
            'current_theme' => $this->themeService->getCurrentTheme(),
            'total_usd' => $this->portfolioService->getTotalValue(),
            'total_rub' => $this->portfolioService->getTotalValue() * $this->getUsdRubRate(),
            'portfolio_structure' => $this->portfolioService->getStructure(),
            'platform_distribution' => $this->portfolioService->getPlatformDistribution(),
            'profit' => $this->portfolioService->getProfit(),
            'recent_operations' => $this->operationRepo->getRecent(5),
            'limit_orders' => $this->limitOrderRepo->getActive(3),
            'recent_notes' => $this->noteRepo->getRecent(3),
            'statistics' => $this->statisticsService->getAll()
        ];
        
        // Получаем дополнительные данные для модальных окон
        $data['platforms'] = $this->getPlatforms();
        $data['assets'] = $this->getAssets();
        $data['currencies'] = $this->getCurrencies();
        $data['fiat_currencies'] = $this->getFiatCurrencies();
        $data['networks'] = $this->getNetworks();
        
        view('dashboard.index', $data);
    }
    
    private function getUsdRubRate() {
        $stmt = $this->pdo->query("
            SELECT rate FROM exchange_rates 
            WHERE from_currency = 'USD' AND to_currency = 'RUB' 
            ORDER BY date DESC LIMIT 1
        ");
        $rate = $stmt->fetch();
        return $rate ? (float)$rate['rate'] : 92.50;
    }
    
    private function getPlatforms() {
        $stmt = $this->pdo->query("SELECT id, name, type FROM platforms WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
    
    private function getAssets() {
        $stmt = $this->pdo->query("SELECT id, symbol, name, type FROM assets WHERE is_active = 1 ORDER BY symbol");
        return $stmt->fetchAll();
    }
    
    private function getCurrencies() {
        $stmt = $this->pdo->query("SELECT code, name, symbol, type FROM currencies ORDER BY code");
        return $stmt->fetchAll();
    }
    
    private function getFiatCurrencies() {
        $stmt = $this->pdo->query("
            SELECT code, name, symbol 
            FROM currencies 
            WHERE code IN ('RUB', 'USD', 'EUR', 'GBP', 'CNY', 'JPY', 'CHF', 'CAD', 'AUD')
            ORDER BY FIELD(code, 'RUB', 'USD', 'EUR', 'GBP', 'CNY', 'JPY', 'CHF', 'CAD', 'AUD')
        ");
        return $stmt->fetchAll();
    }
    
    private function getNetworks() {
        $stmt = $this->pdo->query("
            SELECT id, name, icon, color, full_name 
            FROM networks 
            WHERE is_active = 1 
            ORDER BY 
                CASE name 
                    WHEN 'ERC20' THEN 1
                    WHEN 'BEP20' THEN 2
                    WHEN 'TRC20' THEN 3
                    WHEN 'SOL' THEN 4
                    WHEN 'BTC' THEN 5
                    ELSE 6
                END,
                name
        ");
        return $stmt->fetchAll();
    }
}