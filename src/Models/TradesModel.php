<?php
// src/Models/TradesModel.php

namespace Models;

class TradesModel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Получить все сделки
     */
    public function getAllTrades() {
        $sql = "SELECT 
                    t.*,
                    a.symbol,
                    a.name,
                    pl.name as platform_name
                FROM trades t
                JOIN assets a ON t.asset_id = a.id
                JOIN platforms pl ON t.platform_id = pl.id
                ORDER BY t.operation_date DESC
                LIMIT 100";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Получить сделки по активу
     */
    public function getTradesByAsset($assetId) {
        $sql = "SELECT * FROM trades WHERE asset_id = ? ORDER BY operation_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$assetId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Добавить сделку
     */
    public function addTrade($data) {
        $sql = "INSERT INTO trades (operation_type, asset_id, platform_id, quantity, price, price_currency, commission, commission_currency, operation_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['operation_type'],
            $data['asset_id'],
            $data['platform_id'],
            $data['quantity'],
            $data['price'],
            $data['price_currency'],
            $data['commission'] ?? 0,
            $data['commission_currency'] ?? null,
            $data['operation_date'],
            $data['notes'] ?? null
        ]);
    }
}