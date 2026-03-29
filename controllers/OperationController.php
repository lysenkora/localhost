<?php
// ============================================================================
// КОНТРОЛЛЕР ОПЕРАЦИЙ
// ============================================================================

class OperationController {
    private $pdo;
    private $operationRepo;
    private $platformRepo;
    private $assetRepo;
    private $depositRepo;
    private $tradeRepo;
    private $usdRubRate;
    private $currentTheme;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->operationRepo = new OperationRepository($pdo);
        $this->platformRepo = new PlatformRepository($pdo);
        $this->assetRepo = new AssetRepository($pdo);
        $this->depositRepo = new DepositRepository($pdo);
        $this->tradeRepo = new TradeRepository($pdo);
        
        $this->usdRubRate = getUsdRubRate($pdo);
        $this->currentTheme = getUserTheme($pdo);
    }
    
    /**
     * Получение данных для страницы операций
     */
    public function getOperationsData($page = 1, $filters = []) {
        $perPage = 20;
        
        $operationsData = $this->operationRepo->getAll($filters, $page, $perPage);
        
        $depositStats = $this->depositRepo->getStats();
        $tradeStats = $this->tradeRepo->getStats();
        
        $platforms = $this->platformRepo->getAll();
        $assets = $this->assetRepo->getAll();
        
        return [
            'operations' => $operationsData['operations'],
            'total' => $operationsData['total'],
            'total_pages' => $operationsData['total_pages'],
            'deposit_stats' => $depositStats,
            'trade_stats' => $tradeStats,
            'platforms' => $platforms,
            'assets' => $assets,
            'usd_rub_rate' => $this->usdRubRate,
            'current_theme' => $this->currentTheme,
            'current_page' => $page,
            'filters' => $filters
        ];
    }
}