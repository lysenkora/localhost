<?php
// ============================================================================
// КОНТРОЛЛЕР ДАШБОРДА
// ============================================================================

class DashboardController {
    private $pdo;
    private $platformRepo;
    private $assetRepo;
    private $portfolioRepo;
    private $operationRepo;
    private $limitOrderRepo;
    private $noteRepo;
    private $expenseRepo;
    private $networkRepo;
    private $calculationService;
    private $usdRubRate;
    private $currentTheme;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->platformRepo = new PlatformRepository($pdo);
        $this->assetRepo = new AssetRepository($pdo);
        $this->portfolioRepo = new PortfolioRepository($pdo);
        $this->operationRepo = new OperationRepository($pdo);
        $this->limitOrderRepo = new LimitOrderRepository($pdo);
        $this->noteRepo = new NoteRepository($pdo);
        $this->expenseRepo = new ExpenseRepository($pdo);
        $this->networkRepo = new NetworkRepository($pdo);
        $this->calculationService = new CalculationService($pdo);
        
        $this->usdRubRate = getUsdRubRate($pdo);
        $this->currentTheme = getUserTheme($pdo);
    }
    
    /**
     * Получение всех данных для дашборда
     */
    public function getDashboardData() {
        // Основные показатели портфеля
        $portfolio = $this->portfolioRepo->getPortfolioValue($this->usdRubRate);
        $totalUsd = $portfolio['total_usd'];
        $totalRub = $portfolio['total_rub'];
        
        // Доходность
        $profit = $this->calculationService->calculateProfit($totalUsd, $this->usdRubRate);
        
        // Структура портфеля
        $portfolioStructure = $this->calculationService->calculateStructure($totalUsd, $this->usdRubRate);
        
        // Крипто структура
        $cryptoStats = $this->calculationService->calculateCryptoStats();
        
        // Сектора EN
        $enSectors = $this->calculationService->calculateEnSectors($this->usdRubRate);
        
        // Крипто по типам
        $cryptoByTypes = $this->portfolioRepo->getCryptoByTypes($this->usdRubRate);
        
        // Активы по секторам
        $sectorAssets = $this->portfolioRepo->getAssetsBySectors($this->usdRubRate);
        
        // Активы по сетям
        $networkAssets = $this->portfolioRepo->getAssetsByNetworks();
        
        // Распределение по площадкам
        $platformDistribution = $this->platformRepo->getDistribution($this->usdRubRate);
        
        // Распределение по сетям для статистики
        $networkDistribution = $this->calculateNetworkDistribution($networkAssets);
        
        // Мои активы
        $assets = $this->assetRepo->getUserAssets($this->usdRubRate);
        
        // Лимитные ордера
        $orders = $this->limitOrderRepo->getActive(LIMIT_ORDERS_PER_PAGE);
        
        // Заметки
        $notes = $this->noteRepo->getAll(false, NOTES_PER_PAGE);
        
        // Списки для форм
        $platforms = $this->platformRepo->getAll();
        $assetsList = $this->assetRepo->getAll();
        
        // Валюты
        $allCurrencies = $this->getAllCurrencies();
        $fiatCurrencies = $this->getFiatCurrencies();
        
        // Сети
        $networks = $this->networkRepo->getAll();
        
        // Категории расходов
        $expenseCategories = $this->expenseRepo->getCategories();
        
        // Структура для отображения в header (типы платформ)
        $platformTypes = $this->calculatePlatformTypes($platformDistribution);
        
        return [
            'portfolio' => $portfolio,
            'profit' => $profit,
            'portfolio_structure' => $portfolioStructure,
            'crypto_stats' => $cryptoStats,
            'en_sectors' => $enSectors,
            'crypto_by_types' => $cryptoByTypes,
            'sector_assets' => $sectorAssets,
            'network_assets' => $networkAssets,
            'platform_distribution' => $platformDistribution,
            'network_distribution' => $networkDistribution,
            'platform_types' => $platformTypes,
            'assets' => $assets,
            'orders' => $orders,
            'notes' => $notes,
            'platforms' => $platforms,
            'assets_list' => $assetsList,
            'all_currencies' => $allCurrencies,
            'fiat_currencies' => $fiatCurrencies,
            'networks' => $networks,
            'expense_categories' => $expenseCategories,
            'usd_rub_rate' => $this->usdRubRate,
            'current_theme' => $this->currentTheme
        ];
    }
    
    /**
     * Расчет распределения по сетям
     */
    private function calculateNetworkDistribution($networkAssets) {
        $distribution = [];
        foreach ($networkAssets as $network => $data) {
            if ($network != 'UNKNOWN' && $data['total_value_usd'] > 0.01) {
                $distribution[] = [
                    'network' => $network,
                    'total_value_usd' => $data['total_value_usd']
                ];
            }
        }
        
        usort($distribution, function($a, $b) {
            return $b['total_value_usd'] <=> $a['total_value_usd'];
        });
        
        $totalCrypto = array_sum(array_column($distribution, 'total_value_usd'));
        if ($totalCrypto > 0) {
            foreach ($distribution as &$item) {
                $item['percentage'] = round(($item['total_value_usd'] / $totalCrypto) * 100, 1);
            }
        }
        
        return $distribution;
    }
    
    /**
     * Расчет типов платформ
     */
    private function calculatePlatformTypes($platformDistribution) {
        $types = [];
        foreach ($platformDistribution as $platform) {
            $type = $platform['platform_type'];
            if (!isset($types[$type])) {
                $types[$type] = 0;
            }
            $types[$type] += $platform['total_value_usd'];
        }
        
        $typeNames = [
            'exchange' => 'Биржи',
            'bank' => 'Банки',
            'wallet' => 'Кошельки',
            'broker' => 'Брокеры',
            'other' => 'Другое'
        ];
        
        $typeColors = [
            'exchange' => '#4a9eff',
            'bank' => '#2ecc71',
            'wallet' => '#ff9f4a',
            'broker' => '#9b59b6',
            'other' => '#95a5a6'
        ];
        
        $result = [];
        foreach ($types as $type => $value) {
            $result[] = [
                'type' => $type,
                'name' => $typeNames[$type] ?? ucfirst($type),
                'value_usd' => $value,
                'color' => $typeColors[$type] ?? '#95a5a6'
            ];
        }
        
        usort($result, function($a, $b) {
            return $b['value_usd'] <=> $a['value_usd'];
        });
        
        return array_slice($result, 0, 3);
    }
    
    /**
     * Получение всех валют
     */
    private function getAllCurrencies() {
        $stmt = $this->pdo->query("
            SELECT code, name, symbol, type FROM currencies ORDER BY code
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Получение фиатных валют
     */
    private function getFiatCurrencies() {
        $stmt = $this->pdo->query("
            SELECT code, name, symbol 
            FROM currencies 
            WHERE code IN ('RUB', 'USD', 'EUR', 'GBP', 'CNY', 'JPY', 'CHF', 'CAD', 'AUD')
            ORDER BY FIELD(code, 'RUB', 'USD', 'EUR', 'GBP', 'CNY', 'JPY', 'CHF', 'CAD', 'AUD')
        ");
        return $stmt->fetchAll();
    }
}