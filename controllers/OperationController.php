<?php
class OperationController {
    private $operationRepo;
    private $statisticsService;
    
    public function __construct($pdo) {
        $this->operationRepo = new OperationRepository($pdo);
        $this->statisticsService = new StatisticsService($pdo);
    }
    
    public function index() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = 20;
        
        $operations = $this->operationRepo->getPaginated($page, $per_page);
        $total = $this->operationRepo->getTotalCount();
        $total_pages = ceil($total / $per_page);
        
        // Получаем тему
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE setting_key = 'theme'");
        $stmt->execute();
        $theme_data = $stmt->fetch();
        $current_theme = $theme_data ? $theme_data['setting_value'] : 'light';
        
        require_once __DIR__ . '/../views/operations.php';
    }
}