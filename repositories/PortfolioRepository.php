<?php
// ============================================================================
// РЕПОЗИТОРИЙ ПОРТФЕЛЯ
// ============================================================================

class PortfolioRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Получение стоимости портфеля по типам
     */
    public function getPortfolioValue($usdRubRate) {
        $stmt = $this->pdo->query("
            SELECT 
                a.symbol,
                a.type,
                p.quantity,
                p.average_buy_price,
                p.currency_code
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
        ");
        $assets = $stmt->fetchAll();
        
        $rubAmount = 0;
        $usdtAmount = 0;
        $usdAmount = 0;
        $eurAmount = 0;
        $investmentsValue = 0;
        $liquidityValue = 0;
        
        foreach ($assets as $asset) {
            $value = 0;
            
            switch ($asset['symbol']) {
                case 'RUB':
                    $rubAmount += $asset['quantity'];
                    $value = $asset['quantity'] / $usdRubRate;
                    $liquidityValue += $value;
                    break;
                case 'USDT':
                case 'USDC':
                    $usdtAmount += $asset['quantity'];
                    $value = $asset['quantity'];
                    $liquidityValue += $value;
                    break;
                case 'USD':
                    $usdAmount += $asset['quantity'];
                    $value = $asset['quantity'];
                    $liquidityValue += $value;
                    break;
                case 'EUR':
                    $eurAmount += $asset['quantity'];
                    $value = $asset['quantity'];
                    $liquidityValue += $value;
                    break;
                default:
                    if ($asset['average_buy_price'] > 0) {
                        $value = $asset['quantity'] * $asset['average_buy_price'];
                        $investmentsValue += $value;
                    }
                    break;
            }
        }
        
        return [
            'total_usd' => $liquidityValue + $investmentsValue,
            'total_rub' => ($liquidityValue + $investmentsValue) * $usdRubRate,
            'liquidity_value' => $liquidityValue,
            'investments_value' => $investmentsValue,
            'rub_amount' => $rubAmount,
            'usdt_amount' => $usdtAmount,
            'usd_amount' => $usdAmount,
            'eur_amount' => $eurAmount,
            'rub_in_usd' => $rubAmount / $usdRubRate
        ];
    }
    
    /**
     * Получение криптоактивов по типам (альткоины, стейблкоины)
     */
    public function getCryptoByTypes($usdRubRate) {
        $stmt = $this->pdo->query("
            SELECT 
                CASE 
                    WHEN a.symbol IN ('USDT', 'USDC', 'DAI', 'BUSD') THEN 'stablecoins'
                    WHEN a.symbol IN ('BTC', 'ETH') THEN 'major'
                    ELSE 'altcoins'
                END as crypto_type,
                a.id as asset_id,
                a.symbol,
                a.name as asset_name,
                a.type as asset_type,
                a.currency_code,
                p.quantity,
                p.average_buy_price,
                pl.name as platform_name,
                pl.id as platform_id,
                CASE 
                    WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN p.quantity
                    ELSE p.quantity * COALESCE(p.average_buy_price, 0)
                END as value_usd
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            JOIN platforms pl ON p.platform_id = pl.id
            WHERE a.type = 'crypto' AND p.quantity > 0
            ORDER BY crypto_type, value_usd DESC
        ");
        
        $assets = $stmt->fetchAll();
        
        $grouped = [];
        foreach ($assets as $asset) {
            $cryptoType = $asset['crypto_type'];
            if ($cryptoType === 'major') continue;
            
            $displayName = $cryptoType === 'altcoins' ? 'Альткоины' : 'Стейблкоины';
            
            if (!isset($grouped[$cryptoType])) {
                $grouped[$cryptoType] = [
                    'type' => $cryptoType,
                    'display_name' => $displayName,
                    'assets' => [],
                    'total_value_usd' => 0
                ];
            }
            
            $grouped[$cryptoType]['assets'][] = [
                'symbol' => $asset['symbol'],
                'asset_name' => $asset['asset_name'],
                'asset_type' => $asset['asset_type'],
                'quantity' => $asset['quantity'],
                'average_buy_price' => $asset['average_buy_price'],
                'currency_code' => $asset['currency_code'],
                'platform_name' => $asset['platform_name'],
                'platform_id' => $asset['platform_id'],
                'value_usd' => $asset['value_usd']
            ];
            
            $grouped[$cryptoType]['total_value_usd'] += $asset['value_usd'];
        }
        
        uasort($grouped, function($a, $b) {
            return $b['total_value_usd'] <=> $a['total_value_usd'];
        });
        
        return $grouped;
    }
    
    /**
     * Получение активов по секторам (акции и ETF)
     */
    public function getAssetsBySectors($usdRubRate) {
        $stmt = $this->pdo->query("
            SELECT 
                COALESCE(a.sector, 'Другое') as sector_name,
                a.id as asset_id,
                a.symbol,
                a.name as asset_name,
                a.type as asset_type,
                a.currency_code,
                p.quantity,
                p.average_buy_price,
                pl.name as platform_name,
                pl.id as platform_id,
                CASE 
                    WHEN a.symbol = 'RUB' THEN p.quantity / {$usdRubRate}
                    WHEN a.symbol IN ('USDT', 'USDC', 'USD') THEN p.quantity
                    ELSE p.quantity * COALESCE(p.average_buy_price, 0)
                END as value_usd
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            JOIN platforms pl ON p.platform_id = pl.id
            WHERE a.type IN ('stock', 'etf') 
                AND (a.currency_code = 'USD' OR a.symbol LIKE '%.US')
                AND p.quantity > 0
            ORDER BY sector_name, value_usd DESC
        ");
        
        $assets = $stmt->fetchAll();
        
        $grouped = [];
        foreach ($assets as $asset) {
            $sectorName = $asset['sector_name'];
            $displayName = translateSector($sectorName);
            
            if (!isset($grouped[$sectorName])) {
                $grouped[$sectorName] = [
                    'sector_name' => $sectorName,
                    'display_name' => $displayName,
                    'assets' => [],
                    'total_value_usd' => 0
                ];
            }
            
            $grouped[$sectorName]['assets'][] = [
                'symbol' => $asset['symbol'],
                'asset_name' => $asset['asset_name'],
                'asset_type' => $asset['asset_type'],
                'quantity' => $asset['quantity'],
                'average_buy_price' => $asset['average_buy_price'],
                'currency_code' => $asset['currency_code'],
                'platform_name' => $asset['platform_name'],
                'platform_id' => $asset['platform_id'],
                'value_usd' => $asset['value_usd']
            ];
            
            $grouped[$sectorName]['total_value_usd'] += $asset['value_usd'];
        }
        
        uasort($grouped, function($a, $b) {
            return $b['total_value_usd'] <=> $a['total_value_usd'];
        });
        
        return $grouped;
    }
    
    /**
     * Получение активов по сетям
     */
    public function getAssetsByNetworks() {
        // Получаем все криптоактивы
        $stmt = $this->pdo->query("
            SELECT 
                a.id as asset_id,
                a.symbol,
                a.name as asset_name,
                a.type as asset_type,
                p.id as portfolio_id,
                p.platform_id,
                p.quantity,
                p.average_buy_price,
                p.currency_code,
                pl.name as platform_name
            FROM portfolio p
            JOIN assets a ON p.asset_id = a.id
            JOIN platforms pl ON p.platform_id = pl.id
            WHERE a.type = 'crypto' AND p.quantity > 0
        ");
        $cryptoPortfolio = $stmt->fetchAll();
        
        // Получаем все переводы с указанием сетей
        $stmt = $this->pdo->query("
            SELECT 
                to_platform_id,
                asset_id,
                quantity,
                to_network as network,
                transfer_date
            FROM transfers
            WHERE to_network IS NOT NULL AND to_network != ''
            ORDER BY transfer_date ASC
        ");
        $allTransfers = $stmt->fetchAll();
        
        // Группируем переводы
        $transfersByPortfolio = [];
        foreach ($allTransfers as $transfer) {
            $key = $transfer['to_platform_id'] . '_' . $transfer['asset_id'];
            if (!isset($transfersByPortfolio[$key])) {
                $transfersByPortfolio[$key] = [];
            }
            $transfersByPortfolio[$key][] = $transfer;
        }
        
        // Распределяем активы по сетям
        $networkGrouped = [];
        
        foreach ($cryptoPortfolio as $asset) {
            $key = $asset['platform_id'] . '_' . $asset['asset_id'];
            $totalQuantity = (float)$asset['quantity'];
            
            $transfers = isset($transfersByPortfolio[$key]) ? $transfersByPortfolio[$key] : [];
            
            if (empty($transfers)) {
                $network = 'UNKNOWN';
                $quantityInNetwork = $totalQuantity;
                $this->addToNetworkGroup($networkGrouped, $network, $asset, $quantityInNetwork);
            } else {
                $networkQuantities = [];
                $totalTransferred = 0;
                
                foreach ($transfers as $transfer) {
                    $network = $transfer['network'];
                    $quantity = (float)$transfer['quantity'];
                    
                    if (!isset($networkQuantities[$network])) {
                        $networkQuantities[$network] = 0;
                    }
                    $networkQuantities[$network] += $quantity;
                    $totalTransferred += $quantity;
                }
                
                if ($totalTransferred < $totalQuantity && count($transfers) > 0) {
                    $lastNetwork = $transfers[count($transfers) - 1]['network'];
                    $networkQuantities[$lastNetwork] += ($totalQuantity - $totalTransferred);
                }
                
                foreach ($networkQuantities as $network => $quantityInNetwork) {
                    if ($quantityInNetwork > 0) {
                        $this->addToNetworkGroup($networkGrouped, $network, $asset, $quantityInNetwork);
                    }
                }
            }
        }
        
        uasort($networkGrouped, function($a, $b) {
            return $b['total_value_usd'] <=> $a['total_value_usd'];
        });
        
        return $networkGrouped;
    }
    
    private function addToNetworkGroup(&$grouped, $network, $asset, $quantity) {
        if ($asset['symbol'] == 'USDT' || $asset['symbol'] == 'USDC') {
            $value = $quantity;
        } else if ($asset['average_buy_price'] > 0) {
            $value = $quantity * $asset['average_buy_price'];
        } else {
            $value = 0;
        }
        
        if (!isset($grouped[$network])) {
            $grouped[$network] = [
                'network' => $network,
                'assets' => [],
                'total_value_usd' => 0
            ];
        }
        
        $grouped[$network]['assets'][] = [
            'symbol' => $asset['symbol'],
            'asset_name' => $asset['asset_name'],
            'quantity' => $quantity,
            'average_buy_price' => $asset['average_buy_price'],
            'currency_code' => $asset['currency_code'],
            'platform_name' => $asset['platform_name'],
            'value_usd' => $value
        ];
        
        $grouped[$network]['total_value_usd'] += $value;
    }
}