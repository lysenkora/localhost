<?php
// src/Models/AssetsModel.php

namespace Models;

class AssetsModel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Получить все активы
     */
    public function getAllAssets() {
        $sql = "SELECT * FROM assets ORDER BY type, symbol";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Получить актив по ID
     */
    public function getAsset($id) {
        $sql = "SELECT * FROM assets WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Получить активы по типу
     */
    public function getAssetsByType($type) {
        $sql = "SELECT * FROM assets WHERE type = ? ORDER BY symbol";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }
}