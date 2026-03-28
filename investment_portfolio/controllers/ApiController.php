<?php
require_once __DIR__ . '/../repositories/OperationRepository.php';
require_once __DIR__ . '/../services/PortfolioService.php';
require_once __DIR__ . '/../services/TradeService.php';
require_once __DIR__ . '/../services/TransferService.php';
require_once __DIR__ . '/../services/DepositService.php';
require_once __DIR__ . '/../services/NoteService.php';
require_once __DIR__ . '/../services/LimitOrderService.php';
require_once __DIR__ . '/../services/ExpenseService.php';

class ApiController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        header('Content-Type: application/json');
    }
    
    public function handle($action) {
        switch ($action) {
            case 'get_operations':
                $this->getOperations();
                break;
            case 'add_trade':
                $this->addTrade();
                break;
            case 'add_deposit':
                $this->addDeposit();
                break;
            case 'add_transfer':
                $this->addTransfer();
                break;
            case 'add_note':
                $this->addNote();
                break;
            case 'update_note':
                $this->updateNote();
                break;
            case 'delete_note':
                $this->deleteNote();
                break;
            case 'archive_note':
                $this->archiveNote();
                break;
            case 'get_notes':
                $this->getNotes();
                break;
            case 'add_limit_order':
                $this->addLimitOrder();
                break;
            case 'execute_limit_order':
                $this->executeLimitOrder();
                break;
            case 'cancel_limit_order':
                $this->cancelLimitOrder();
                break;
            case 'add_expense':
                $this->addExpense();
                break;
            case 'get_expenses':
                $this->getExpenses();
                break;
            case 'delete_expense':
                $this->deleteExpense();
                break;
            case 'get_expense_categories':
                $this->getExpenseCategories();
                break;
            case 'add_expense_category':
                $this->addExpenseCategory();
                break;
            case 'save_theme':
                $this->saveTheme();
                break;
            case 'add_platform_full':
                $this->addPlatform();
                break;
            case 'add_currency_full':
                $this->addCurrency();
                break;
            case 'add_asset_full':
                $this->addAsset();
                break;
            case 'add_network':
                $this->addNetwork();
                break;
            case 'get_purchase_history':
                $this->getPurchaseHistory();
                break;
            case 'get_platform_balance':
                $this->getPlatformBalance();
                break;
            default:
                $this->error('Unknown action');
        }
    }
    
    private function getOperations() {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;
        
        $filters = [
            'type' => $_GET['type'] ?? 'all',
            'platform' => $_GET['platform'] ?? null,
            'asset' => $_GET['asset'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null
        ];
        
        $repo = new OperationRepository($this->pdo);
        $operations = $repo->getAll($filters, $perPage, $offset);
        $total = $repo->getTotal($filters);
        
        echo json_encode([
            'success' => true,
            'operations' => $operations,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage),
                'total' => $total,
                'per_page' => $perPage,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ]);
    }
    
    private function addTrade() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new TradeService($this->pdo);
        $result = $service->createTrade($data);
        
        echo json_encode($result);
    }
    
    private function addDeposit() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new DepositService($this->pdo);
        $result = $service->createDeposit($data);
        
        echo json_encode($result);
    }
    
    private function addTransfer() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new TransferService($this->pdo);
        $result = $service->createTransfer($data);
        
        echo json_encode($result);
    }
    
    private function addNote() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new NoteService($this->pdo);
        $result = $service->createNote($data);
        
        echo json_encode($result);
    }
    
    private function updateNote() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new NoteService($this->pdo);
        $result = $service->updateNote($data);
        
        echo json_encode($result);
    }
    
    private function deleteNote() {
        $id = $_POST['note_id'] ?? 0;
        
        $service = new NoteService($this->pdo);
        $result = $service->deleteNote($id);
        
        echo json_encode($result);
    }
    
    private function archiveNote() {
        $id = $_POST['note_id'] ?? 0;
        $archive = (bool)($_POST['archive'] ?? 1);
        
        $service = new NoteService($this->pdo);
        $result = $service->archiveNote($id, $archive);
        
        echo json_encode($result);
    }
    
    private function getNotes() {
        $includeArchived = (int)($_POST['include_archived'] ?? 0);
        
        $service = new NoteService($this->pdo);
        $notes = $service->getNotes($includeArchived);
        
        echo json_encode(['success' => true, 'notes' => $notes]);
    }
    
    private function addLimitOrder() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new LimitOrderService($this->pdo);
        $result = $service->createOrder($data);
        
        echo json_encode($result);
    }
    
    private function executeLimitOrder() {
        $id = $_POST['order_id'] ?? 0;
        
        $service = new LimitOrderService($this->pdo);
        $result = $service->executeOrder($id);
        
        echo json_encode($result);
    }
    
    private function cancelLimitOrder() {
        $id = $_POST['order_id'] ?? 0;
        
        $service = new LimitOrderService($this->pdo);
        $result = $service->cancelOrder($id);
        
        echo json_encode($result);
    }
    
    private function addExpense() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new ExpenseService($this->pdo);
        $result = $service->createExpense($data);
        
        echo json_encode($result);
    }
    
    private function getExpenses() {
        $limit = (int)($_POST['limit'] ?? 50);
        $offset = (int)($_POST['offset'] ?? 0);
        $category = $_POST['category'] ?? null;
        $dateFrom = $_POST['date_from'] ?? null;
        $dateTo = $_POST['date_to'] ?? null;
        
        $service = new ExpenseService($this->pdo);
        $result = $service->getExpenses($limit, $offset, $category, $dateFrom, $dateTo);
        
        echo json_encode($result);
    }
    
    private function deleteExpense() {
        $id = $_POST['expense_id'] ?? 0;
        
        $service = new ExpenseService($this->pdo);
        $result = $service->deleteExpense($id);
        
        echo json_encode($result);
    }
    
    private function getExpenseCategories() {
        $service = new ExpenseService($this->pdo);
        $categories = $service->getCategories();
        
        echo json_encode(['success' => true, 'categories' => $categories]);
    }
    
    private function addExpenseCategory() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new ExpenseService($this->pdo);
        $result = $service->createCategory($data);
        
        echo json_encode($result);
    }
    
    private function saveTheme() {
        $theme = $_POST['theme'] ?? 'light';
        
        $service = new ThemeService($this->pdo);
        $result = $service->saveTheme($theme);
        
        echo json_encode($result);
    }
    
    private function addPlatform() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new PlatformService($this->pdo);
        $result = $service->createPlatform($data);
        
        echo json_encode($result);
    }
    
    private function addCurrency() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new CurrencyService($this->pdo);
        $result = $service->createCurrency($data);
        
        echo json_encode($result);
    }
    
    private function addAsset() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new AssetService($this->pdo);
        $result = $service->createAsset($data);
        
        echo json_encode($result);
    }
    
    private function addNetwork() {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $service = new NetworkService($this->pdo);
        $result = $service->createNetwork($data);
        
        echo json_encode($result);
    }
    
    private function getPurchaseHistory() {
        $assetId = $_POST['asset_id'] ?? 0;
        $platformId = $_POST['platform_id'] ?? 0;
        
        if (!$assetId || !$platformId) {
            $this->error('Asset ID and Platform ID are required');
            return;
        }
        
        $service = new TradeService($this->pdo);
        $history = $service->getPurchaseHistory($assetId, $platformId);
        
        echo json_encode(['success' => true, 'data' => $history]);
    }
    
    private function getPlatformBalance() {
        $platformId = $_POST['platform_id'] ?? 0;
        
        if (!$platformId) {
            $this->error('Platform ID is required');
            return;
        }
        
        $service = new PortfolioService($this->pdo);
        $balance = $service->getPlatformBalance($platformId);
        
        echo json_encode($balance);
    }
    
    private function error($message) {
        echo json_encode(['success' => false, 'message' => $message]);
    }
}