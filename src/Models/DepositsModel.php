<?php
// src/Models/DepositsModel.php

namespace Models;

class DepositsModel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Получить все пополнения
     */
    public function getAllDeposits() {
        $sql = "SELECT 
                    d.*,
                    pl.name as platform_name
                FROM deposits d
                JOIN platforms pl ON d.platform_id = pl.id
                ORDER BY d.deposit_date DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Получить сумму пополнений по платформам
     */
    public function getTotalDepositsByPlatform() {
        $sql = "SELECT 
                    pl.name as platform_name,
                    SUM(d.amount) as total_amount,
                    d.currency_code
                FROM deposits d
                JOIN platforms pl ON d.platform_id = pl.id
                GROUP BY pl.name, d.currency_code
                ORDER BY total_amount DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}