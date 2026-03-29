<?php
// ============================================================================
// СЕРВИС РАСЧЕТОВ
// ============================================================================

class CalculationService {
    private $pdo;
    private $depositRepo;
    private $tradeRepo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->depositRepo = new DepositRepository($pdo);
        $this->tradeRepo = new TradeRepository($pdo);
    }
    
    /**
     * Расчет доходности портфеля
     */
    public function calculateProfit($totalUsd, $usdRubRate) {
        $totalInvestedUsd = $this->depositRepo->getTotalInvestedUsd($usdRubRate);
        
        $profitUsd = $totalUsd - $totalInvestedUsd;
        $profitRub = $profitUsd * $usdRubRate;
        
        if ($totalInvestedUsd > 0) {
            $profitPercent = ($profitUsd / $totalInvestedUsd) * 100;
        } else {
            $profitPercent = 0;
        }
        
        return [
            'invested_usd' => $totalInvestedUsd,
            'invested_rub' => $totalInvestedUsd * $usdRubRate,
            'profit_usd' => $profitUsd,
            'profit_rub' => $profitRub,
            'profit_percent' => $profitPercent,
            'profit_class' => Formatter::profitClass($profitUsd),
            'profit_icon' => Formatter::profitIcon($profitUsd)
        ];
    }
    
    /**
     * Расчет структуры портфеля
     */
    public function calculateStructure($totalUsd, $usdRubRate) {
        $stmt = $this->pdo->query("
            SELECT 
                a.type,
                a.symbol,
                a.currency_code,
                p.quantity,
                p.average_buy_price
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
        ");
        $allAssets = $stmt->fetchAll();
        
        $categories = [
            'Рубли' => 0,
            'Фиат (USD/EUR)' => 0,
            'Крипто' => 0,
            'Фондовый (EN)' => 0,
            'Фондовый (РФ)' => 0,
            'Фондовый (прочее)' => 0,
            'Облигации' => 0,
            'Вклады' => 0,
            'Другие' => 0
        ];
        
        // Получаем общую сумму покупок крипто
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(quantity * price), 0) as total
            FROM trades t
            JOIN assets a ON t.asset_id = a.id
            WHERE a.type = 'crypto' AND t.operation_type = 'buy'
        ");
        $totalCryptoBought = $stmt->fetch()['total'];
        $categories['Крипто'] = $totalCryptoBought;
        
        foreach ($allAssets as $asset) {
            $value = 0;
            
            if ($asset['symbol'] == 'RUB') {
                $value = $asset['quantity'] / $usdRubRate;
                $categories['Рубли'] += $value;
            } elseif ($asset['symbol'] == 'USD' || $asset['symbol'] == 'EUR') {
                $value = $asset['quantity'];
                $categories['Фиат (USD/EUR)'] += $value;
            } elseif ($asset['average_buy_price'] > 0) {
                $value = $asset['quantity'] * $asset['average_buy_price'];
                
                switch ($asset['type']) {
                    case 'stock':
                    case 'etf':
                        if ($asset['currency_code'] == 'USD') {
                            $categories['Фондовый (EN)'] += $value;
                        } elseif ($asset['currency_code'] == 'RUB') {
                            $categories['Фондовый (РФ)'] += $value;
                        } else {
                            $categories['Фондовый (прочее)'] += $value;
                        }
                        break;
                    case 'bond':
                        $categories['Облигации'] += $value;
                        break;
                    case 'deposit':
                        $categories['Вклады'] += $value;
                        break;
                    default:
                        $categories['Другие'] += $value;
                        break;
                }
            }
        }
        
        $structure = [];
        foreach ($categories as $category => $value) {
            if ($value > 0) {
                $structure[] = [
                    'category' => $category,
                    'value' => $value,
                    'percentage' => round(($value / $totalUsd) * 100, 2)
                ];
            }
        }
        
        usort($structure, function($a, $b) {
            return $b['value'] <=> $a['value'];
        });
        
        // Корректируем проценты до 100%
        $totalPercentage = array_sum(array_column($structure, 'percentage'));
        if (abs($totalPercentage - 100) > 0.1 && !empty($structure)) {
            $structure[0]['percentage'] += (100 - $totalPercentage);
        }
        
        if (empty($structure)) {
            $structure = [['category' => 'Нет данных', 'value' => 0, 'percentage' => 100]];
        }
        
        return $structure;
    }
    
    /**
     * Расчет статистики по крипто
     */
    public function calculateCryptoStats() {
        $stmt = $this->pdo->query("
            SELECT 
                COALESCE(SUM(CASE WHEN a.symbol = 'USDT' AND t.operation_type = 'buy' THEN t.quantity END), 0) as total_usdt_bought,
                COALESCE(SUM(CASE WHEN a.symbol = 'BTC' AND t.operation_type = 'buy' THEN t.quantity * t.price END), 0) as btc_cost,
                COALESCE(SUM(CASE WHEN a.symbol = 'ETH' AND t.operation_type = 'buy' THEN t.quantity * t.price END), 0) as eth_cost,
                COALESCE(SUM(CASE WHEN a.type = 'crypto' AND a.symbol NOT IN ('USDT', 'USDC', 'BTC', 'ETH') AND t.operation_type = 'buy' THEN t.quantity * t.price END), 0) as altcoins_cost
            FROM trades t
            JOIN assets a ON t.asset_id = a.id
            WHERE a.type = 'crypto'
        ");
        $data = $stmt->fetch();
        
        $totalUsdtBought = $data['total_usdt_bought'] ?: 1;
        $btcCost = $data['btc_cost'] ?: 0;
        $ethCost = $data['eth_cost'] ?: 0;
        $altcoinsCost = $data['altcoins_cost'] ?: 0;
        $stablecoinsLeft = $totalUsdtBought - ($btcCost + $ethCost + $altcoinsCost);
        
        return [
            'total_usdt_bought' => $totalUsdtBought,
            'btc_cost' => $btcCost,
            'eth_cost' => $ethCost,
            'altcoins_cost' => $altcoinsCost,
            'stablecoins_left' => $stablecoinsLeft,
            'btc_percent' => $totalUsdtBought > 0 ? round(($btcCost / $totalUsdtBought) * 100, 1) : 0,
            'eth_percent' => $totalUsdtBought > 0 ? round(($ethCost / $totalUsdtBought) * 100, 1) : 0,
            'altcoins_percent' => $totalUsdtBought > 0 ? round(($altcoinsCost / $totalUsdtBought) * 100, 1) : 0,
            'stablecoins_percent' => $totalUsdtBought > 0 ? round(($stablecoinsLeft / $totalUsdtBought) * 100, 1) : 0
        ];
    }
    
    /**
     * Расчет распределения по секторам для EN акций
     */
    public function calculateEnSectors($usdRubRate) {
        $stmt = $this->pdo->query("
            SELECT 
                COALESCE(a.sector, 'Другое') as sector_name,
                SUM(p.quantity * COALESCE(p.average_buy_price, 0)) as total_value
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            WHERE a.type IN ('stock', 'etf') 
                AND (a.currency_code = 'USD' OR a.symbol LIKE '%.US')
                AND p.quantity > 0
            GROUP BY a.sector
            ORDER BY total_value DESC
        ");
        $sectorData = $stmt->fetchAll();
        
        $total = 0;
        $sectors = [];
        
        foreach ($sectorData as $row) {
            $total += $row['total_value'];
        }
        
        foreach ($sectorData as $row) {
            if ($total > 0) {
                $percentage = round(($row['total_value'] / $total) * 100, 2);
                if ($percentage > 0) {
                    $sectors[] = [
                        'original_name' => $row['sector_name'],
                        'sector_name' => translateSector($row['sector_name']),
                        'percentage' => $percentage,
                        'value_usd' => $row['total_value']
                    ];
                }
            }
        }
        
        return $sectors;
    }
}