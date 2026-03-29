<?php
// ============================================================================
// РЕПОЗИТОРИЙ ПОПОЛНЕНИЙ
// ============================================================================

class DepositRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Добавление пополнения
     */
    public function create($platformId, $amount, $currencyCode, $date, $notes = '') {
        $this->pdo->beginTransaction();
        
        try {
            // Добавляем запись о пополнении
            $stmt = $this->pdo->prepare("
                INSERT INTO deposits (platform_id, amount, currency_code, deposit_date, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$platformId, $amount, $currencyCode, $date, $notes]);
            
            // Находим или создаем актив для валюты
            $stmt = $this->pdo->prepare("SELECT id FROM assets WHERE symbol = ?");
            $stmt->execute([$currencyCode]);
            $asset = $stmt->fetch();
            
            if (!$asset) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO assets (symbol, name, type, currency_code, is_active)
                    VALUES (?, ?, 'currency', ?, 1)
                ");
                $stmt->execute([$currencyCode, $currencyCode, $currencyCode]);
                $assetId = $this->pdo->lastInsertId();
            } else {
                $assetId = $asset['id'];
            }
            
            // Добавляем в портфель
            $stmt = $this->pdo->prepare("
                INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmt->execute([$assetId, $platformId, $amount, $currencyCode]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    /**
     * Получение статистики по пополнениям
     */
    public function getStats() {
        $stmt = $this->pdo->query("
            SELECT 
                COALESCE(SUM(CASE WHEN currency_code = 'RUB' THEN amount END), 0) as total_rub_deposits,
                COALESCE(SUM(CASE WHEN currency_code = 'USD' THEN amount END), 0) as total_usd_deposits,
                COALESCE(SUM(CASE WHEN currency_code = 'EUR' THEN amount END), 0) as total_eur_deposits,
                COUNT(*) as total_count
            FROM deposits
        ");
        return $stmt->fetch();
    }
    
    /**
     * Получение общей суммы пополнений в USD
     */
    public function getTotalInvestedUsd($usdRubRate) {
        $stats = $this->getStats();
        $total = $stats['total_usd_deposits'] + $stats['total_eur_deposits'];
        $total += $stats['total_rub_deposits'] / $usdRubRate;
        return $total;
    }
}