<?php
// ============================================================================
// РЕПОЗИТОРИЙ РАСХОДОВ
// ============================================================================

class ExpenseRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Получение категорий расходов
     */
    public function getCategories($includeInactive = false) {
        $sql = "SELECT * FROM expense_categories";
        if (!$includeInactive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order, name_ru";
        
        return $this->pdo->query($sql)->fetchAll();
    }
    
    /**
     * Добавление категории расходов
     */
    public function addCategory($name, $nameRu, $icon = 'fas fa-tag', $color = '#ff9f4a') {
        $stmt = $this->pdo->prepare("
            INSERT INTO expense_categories (name, name_ru, icon, color, sort_order)
            VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM expense_categories))
        ");
        return $stmt->execute([$name, $nameRu, $icon, $color]);
    }
    
    /**
     * Добавление расхода
     */
    public function addExpense($amount, $currencyCode, $categoryId, $description, $date) {
        $stmt = $this->pdo->prepare("
            INSERT INTO expenses (amount, currency_code, category_id, description, expense_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$amount, $currencyCode, $categoryId, $description, $date]);
    }
    
    /**
     * Получение расходов с фильтрацией
     */
    public function getExpenses($limit = 10, $offset = 0, $categoryId = null, $dateFrom = null, $dateTo = null) {
        $sql = "
            SELECT e.*, c.name, c.name_ru, c.icon, c.color
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($categoryId) {
            $sql .= " AND e.category_id = ?";
            $params[] = $categoryId;
        }
        
        if ($dateFrom) {
            $sql .= " AND e.expense_date >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND e.expense_date <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " ORDER BY e.expense_date DESC, e.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();
        
        // Получаем общую сумму
        $sqlTotal = "SELECT SUM(amount) as total FROM expenses WHERE 1=1";
        $paramsTotal = [];
        
        if ($categoryId) {
            $sqlTotal .= " AND category_id = ?";
            $paramsTotal[] = $categoryId;
        }
        
        if ($dateFrom) {
            $sqlTotal .= " AND expense_date >= ?";
            $paramsTotal[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sqlTotal .= " AND expense_date <= ?";
            $paramsTotal[] = $dateTo;
        }
        
        $stmtTotal = $this->pdo->prepare($sqlTotal);
        $stmtTotal->execute($paramsTotal);
        $total = $stmtTotal->fetch();
        
        // Получаем статистику по категориям
        $sqlStats = "
            SELECT 
                e.category_id,
                c.name,
                c.name_ru,
                c.icon,
                c.color,
                SUM(e.amount) as total_amount,
                COUNT(*) as count
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            WHERE 1=1
        ";
        $paramsStats = [];
        
        if ($dateFrom) {
            $sqlStats .= " AND e.expense_date >= ?";
            $paramsStats[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sqlStats .= " AND e.expense_date <= ?";
            $paramsStats[] = $dateTo;
        }
        
        $sqlStats .= " GROUP BY e.category_id ORDER BY total_amount DESC";
        
        $stmtStats = $this->pdo->prepare($sqlStats);
        $stmtStats->execute($paramsStats);
        $stats = $stmtStats->fetchAll();
        
        return [
            'success' => true,
            'expenses' => $expenses,
            'total' => $total['total'] ?? 0,
            'stats' => $stats
        ];
    }
    
    /**
     * Удаление расхода
     */
    public function deleteExpense($id) {
        $stmt = $this->pdo->prepare("DELETE FROM expenses WHERE id = ?");
        return $stmt->execute([$id]);
    }
}