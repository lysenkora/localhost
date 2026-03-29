<?php
class DashboardController {
    private $pdo;
    private $portfolioModel;
    private $statisticsService;
    private $operationRepo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->portfolioModel = new Portfolio();
        $this->statisticsService = new StatisticsService($pdo);
        $this->operationRepo = new OperationRepository($pdo);
    }
    
    public function index() {
        // Получаем данные
        $total_usd = $this->portfolioModel->getTotalValue();
        $usd_rub_rate = $this->statisticsService->getExchangeRate('USD', 'RUB');
        $total_rub = $total_usd * $usd_rub_rate;
        $operations = $this->operationRepo->getRecent(10);
        $deposit_stats = $this->statisticsService->getTotalDeposits();
        $trade_stats = $this->statisticsService->getTradeStats();
        
        // Получаем тему
        $stmt = $this->pdo->prepare("SELECT setting_value FROM user_settings WHERE setting_key = 'theme'");
        $stmt->execute();
        $theme_data = $stmt->fetch();
        $current_theme = $theme_data ? $theme_data['setting_value'] : 'light';
        
        // Подключаем представление
        require_once __DIR__ . '/../views/dashboard.php';
    }
}