<?php
// src/Models/PortfolioModel.php

namespace Models;

use PDO;

class PortfolioModel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Получить весь портфель с информацией об активах
     */
    public function getPortfolio() {
        $sql = "SELECT 
                    p.id,
                    p.asset_id,
                    p.platform_id,
                    p.quantity,
                    p.average_buy_price,
                    p.currency_code,
                    a.symbol,
                    a.name,
                    a.type,
                    a.sector,
                    pl.name as platform_name
                FROM portfolio p
                JOIN assets a ON p.asset_id = a.id
                JOIN platforms pl ON p.platform_id = pl.id
                WHERE a.is_active = 1
                ORDER BY a.type, a.symbol";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Получить сводку по типам активов
     */
    public function getSummaryByType() {
        $sql = "SELECT 
                    a.type,
                    COUNT(*) as count,
                    SUM(p.quantity) as total_quantity
                FROM portfolio p
                JOIN assets a ON p.asset_id = a.id
                GROUP BY a.type";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Получить позицию портфеля по ID
     */
    public function getPortfolioItem($id) {
        $sql = "SELECT * FROM portfolio WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Обновить позицию портфеля
     */
    public function updatePortfolioItem($id, $quantity, $avgPrice) {
        $sql = "UPDATE portfolio SET quantity = ?, average_buy_price = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$quantity, $avgPrice, $id]);
    }
}