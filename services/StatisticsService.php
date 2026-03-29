<?php
class StatisticsService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getExchangeRate($from, $to) {
        $stmt = $this->pdo->prepare("
            SELECT rate FROM exchange_rates 
            WHERE from_currency = ? AND to_currency = ? 
            ORDER BY date DESC LIMIT 1
        ");
        $stmt->execute([$from, $to]);
        $result = $stmt->fetch();
        return $result ? (float)$result['rate'] : ($from === 'USD' && $to === 'RUB' ? 92.50 : 1);
    }
    
    public function getTotalDeposits() {
        $stmt = $this->pdo->query("
            SELECT 
                COALESCE(SUM(CASE WHEN currency_code = 'RUB' THEN amount END), 0) as rub,
                COALESCE(SUM(CASE WHEN currency_code = 'USD' THEN amount END), 0) as usd,
                COALESCE(SUM(CASE WHEN currency_code = 'EUR' THEN amount END), 0) as eur,
                COUNT(*) as count
            FROM deposits
        ");
        return $stmt->fetch();
    }
    
    public function getTradeStats() {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(CASE WHEN operation_type = 'buy' THEN 1 END) as buy_count,
                COUNT(CASE WHEN operation_type = 'sell' THEN 1 END) as sell_count,
                COALESCE(SUM(CASE WHEN operation_type = 'buy' THEN quantity * price END), 0) as buy_volume,
                COALESCE(SUM(CASE WHEN operation_type = 'sell' THEN quantity * price END), 0) as sell_volume
            FROM trades
        ");
        return $stmt->fetch();
    }
}