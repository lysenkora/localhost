<?php
// ============================================================================
// РЕПОЗИТОРИЙ ПЕРЕВОДОВ
// ============================================================================

class TransferRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Добавление перевода
     */
    public function create($fromPlatformId, $toPlatformId, $assetId, $quantity, 
                          $commission = 0, $commissionCurrency = null, 
                          $fromNetwork = null, $toNetwork = null, $date, $notes = '') {
        
        $this->pdo->beginTransaction();
        
        try {
            // Добавляем запись о переводе
            $stmt = $this->pdo->prepare("
                INSERT INTO transfers (
                    from_platform_id, to_platform_id, asset_id, quantity,
                    commission, commission_currency, from_network, to_network,
                    transfer_date, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $fromPlatformId, $toPlatformId, $assetId, $quantity,
                $commission, $commissionCurrency, $fromNetwork, $toNetwork,
                $date, $notes
            ]);
            
            // Проверяем наличие актива у отправителя
            $stmt = $this->pdo->prepare("
                SELECT id, quantity FROM portfolio 
                WHERE asset_id = ? AND platform_id = ?
            ");
            $stmt->execute([$assetId, $fromPlatformId]);
            $fromPortfolio = $stmt->fetch();
            
            if (!$fromPortfolio) {
                throw new Exception("У отправителя нет этого актива на выбранной площадке");
            }
            
            if ($fromPortfolio['quantity'] < $quantity) {
                throw new Exception("Недостаточно средств для перевода");
            }
            
            // Уменьшаем количество на платформе отправителя
            $newQuantity = $fromPortfolio['quantity'] - $quantity;
            if ($newQuantity > 0) {
                $stmt = $this->pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $fromPortfolio['id']]);
            } else {
                $stmt = $this->pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                $stmt->execute([$fromPortfolio['id']]);
            }
            
            // Получаем валюту актива
            $stmt = $this->pdo->prepare("SELECT currency_code FROM assets WHERE id = ?");
            $stmt->execute([$assetId]);
            $asset = $stmt->fetch();
            $currencyCode = $asset['currency_code'] ?? null;
            
            // Добавляем на платформу получателя
            $stmt = $this->pdo->prepare("
                INSERT INTO portfolio (asset_id, platform_id, quantity, currency_code)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmt->execute([$assetId, $toPlatformId, $quantity, $currencyCode]);
            
            // Обрабатываем комиссию
            if ($commission > 0 && !empty($commissionCurrency)) {
                $this->processCommission($fromPlatformId, $commission, $commissionCurrency);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => ''];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Обработка комиссии
     */
    private function processCommission($platformId, $commission, $currency) {
        $stmt = $this->pdo->prepare("SELECT id FROM assets WHERE symbol = ?");
        $stmt->execute([$currency]);
        $commissionAsset = $stmt->fetch();
        
        if ($commissionAsset) {
            $stmt = $this->pdo->prepare("
                SELECT id, quantity FROM portfolio 
                WHERE asset_id = ? AND platform_id = ?
            ");
            $stmt->execute([$commissionAsset['id'], $platformId]);
            $commissionPortfolio = $stmt->fetch();
            
            if ($commissionPortfolio && $commissionPortfolio['quantity'] >= $commission) {
                $newQuantity = $commissionPortfolio['quantity'] - $commission;
                if ($newQuantity > 0) {
                    $stmt = $this->pdo->prepare("UPDATE portfolio SET quantity = ? WHERE id = ?");
                    $stmt->execute([$newQuantity, $commissionPortfolio['id']]);
                } else {
                    $stmt = $this->pdo->prepare("DELETE FROM portfolio WHERE id = ?");
                    $stmt->execute([$commissionPortfolio['id']]);
                }
            }
        }
    }
}