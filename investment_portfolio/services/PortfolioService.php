<?php
require_once __DIR__ . '/../models/Portfolio.php';
require_once __DIR__ . '/../repositories/StatisticsRepository.php';
require_once __DIR__ . '/../helpers/Formatter.php';

class PortfolioService {
    private $portfolioModel;
    private $statisticsRepo;
    private $usdRubRate;
    
    public function __construct($pdo) {
        $this->portfolioModel = new Portfolio();
        $this->statisticsRepo = new StatisticsRepository($pdo);
        $this->usdRubRate = $this->getUsdRubRate($pdo);
    }
    
    private function getUsdRubRate($pdo) {
        $stmt = $pdo->query("
            SELECT rate FROM exchange_rates 
            WHERE from_currency = 'USD' AND to_currency = 'RUB' 
            ORDER BY date DESC LIMIT 1
        ");
        $rate = $stmt->fetch();
        return $rate ? (float)$rate['rate'] : 92.50;
    }
    
    public function getTotalValue() {
        $assets = $this->portfolioModel->getUserPortfolio();
        $total = 0;
        
        foreach ($assets as $asset) {
            if ($asset['symbol'] == 'RUB') {
                $total += $asset['total_quantity'] / $this->usdRubRate;
            } elseif (in_array($asset['symbol'], ['USD', 'USDT', 'USDC'])) {
                $total += $asset['total_quantity'];
            } elseif ($asset['avg_price'] > 0) {
                $total += $asset['total_quantity'] * $asset['avg_price'];
            }
        }
        
        return $total;
    }
    
    public function getStructure() {
        $assets = $this->portfolioModel->getUserPortfolio();
        $structure = [];
        $total = $this->getTotalValue();
        
        foreach ($assets as $asset) {
            $value = 0;
            $category = $this->getCategory($asset);
            
            if ($asset['symbol'] == 'RUB') {
                $value = $asset['total_quantity'] / $this->usdRubRate;
            } elseif (in_array($asset['symbol'], ['USD', 'USDT', 'USDC'])) {
                $value = $asset['total_quantity'];
            } elseif ($asset['avg_price'] > 0) {
                $value = $asset['total_quantity'] * $asset['avg_price'];
            }
            
            if (!isset($structure[$category])) {
                $structure[$category] = 0;
            }
            $structure[$category] += $value;
        }
        
        $result = [];
        foreach ($structure as $category => $value) {
            $result[] = [
                'category' => $category,
                'value' => $value,
                'percentage' => $total > 0 ? round(($value / $total) * 100, 2) : 0
            ];
        }
        
        usort($result, function($a, $b) {
            return $b['value'] <=> $a['value'];
        });
        
        return $result;
    }
    
    private function getCategory($asset) {
        if ($asset['symbol'] == 'RUB') return 'Рубли';
        if (in_array($asset['symbol'], ['USD', 'EUR'])) return 'Фиат (USD/EUR)';
        if ($asset['type'] == 'crypto') return 'Крипто';
        if ($asset['type'] == 'stock' || $asset['type'] == 'etf') {
            if ($asset['asset_currency'] == 'USD') return 'Фондовый (EN)';
            if ($asset['asset_currency'] == 'RUB') return 'Фондовый (РФ)';
            return 'Фондовый (прочее)';
        }
        if ($asset['type'] == 'bond') return 'Облигации';
        if ($asset['type'] == 'deposit') return 'Вклады';
        return 'Другие';
    }
    
    public function getPlatformDistribution() {
        return $this->portfolioModel->getPlatformDistribution();
    }
    
    public function getProfit() {
        $stats = $this->statisticsRepo->getTotalInvested();
        $currentValue = $this->getTotalValue();
        
        $invested = $stats['total_usd'];
        $profit = $currentValue - $invested;
        $profitPercent = $invested > 0 ? ($profit / $invested) * 100 : 0;
        
        return [
            'invested_usd' => $invested,
            'invested_rub' => $invested * $this->usdRubRate,
            'current_usd' => $currentValue,
            'current_rub' => $currentValue * $this->usdRubRate,
            'profit_usd' => $profit,
            'profit_rub' => $profit * $this->usdRubRate,
            'profit_percent' => $profitPercent,
            'is_positive' => $profit >= 0
        ];
    }
}