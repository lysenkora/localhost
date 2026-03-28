<?php
require_once __DIR__ . '/BaseModel.php';

class Asset extends BaseModel {
    protected $table = 'assets';
    
    public function findBySymbol($symbol) {
        $stmt = $this->pdo->prepare("SELECT * FROM assets WHERE symbol = ?");
        $stmt->execute([$symbol]);
        return $stmt->fetch();
    }
    
    public function getByType($type) {
        $stmt = $this->pdo->prepare("SELECT * FROM assets WHERE type = ? AND is_active = 1 ORDER BY symbol");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }
    
    public function getAllActive() {
        $stmt = $this->pdo->query("SELECT * FROM assets WHERE is_active = 1 ORDER BY symbol");
        return $stmt->fetchAll();
    }
    
    public function getCryptoTypes() {
        $stmt = $this->pdo->query("
            SELECT 
                CASE 
                    WHEN symbol IN ('USDT', 'USDC', 'DAI') THEN 'stablecoins'
                    WHEN symbol IN ('BTC', 'ETH') THEN 'major'
                    ELSE 'altcoins'
                END as crypto_type,
                symbol,
                name
            FROM assets
            WHERE type = 'crypto' AND is_active = 1
            ORDER BY crypto_type, symbol
        ");
        return $stmt->fetchAll();
    }
}