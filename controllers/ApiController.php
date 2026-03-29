<?php
// ============================================================================
// API КОНТРОЛЛЕР
// ============================================================================

class ApiController {
    private $pdo;
    private $platformRepo;
    private $assetRepo;
    private $tradeRepo;
    private $depositRepo;
    private $transferRepo;
    private $operationRepo;
    private $limitOrderRepo;
    private $noteRepo;
    private $expenseRepo;
    private $networkRepo;
    private $portfolioRepo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->platformRepo = new PlatformRepository($pdo);
        $this->assetRepo = new AssetRepository($pdo);
        $this->tradeRepo = new TradeRepository($pdo);
        $this->depositRepo = new DepositRepository($pdo);
        $this->transferRepo = new TransferRepository($pdo);
        $this->operationRepo = new OperationRepository($pdo);
        $this->limitOrderRepo = new LimitOrderRepository($pdo);
        $this->noteRepo = new NoteRepository($pdo);
        $this->expenseRepo = new ExpenseRepository($pdo);
        $this->networkRepo = new NetworkRepository($pdo);
        $this->portfolioRepo = new PortfolioRepository($pdo);
    }
    
    /**
     * Обработка POST запросов
     */
    public function handleRequest() {
        $response = ['success' => false, 'message' => ''];
        
        if (!isset($_POST['action'])) {
            return $response;
        }
        
        switch ($_POST['action']) {
            case 'add_trade':
                $response = $this->addTrade();
                break;
                
            case 'add_deposit':
                $response = $this->addDeposit();
                break;
                
            case 'add_transfer':
                $response = $this->addTransfer();
                break;
                
            case 'add_platform_full':
                $response = $this->addPlatform();
                break;
                
            case 'add_currency_full':
                $response = $this->addCurrency();
                break;
                
            case 'add_asset_full':
                $response = $this->addAsset();
                break;
                
            case 'save_theme':
                $response = $this->saveTheme();
                break;
                
            case 'add_limit_order':
                $response = $this->addLimitOrder();
                break;
                
            case 'execute_limit_order':
                $response = $this->executeLimitOrder();
                break;
                
            case 'cancel_limit_order':
                $response = $this->cancelLimitOrder();
                break;
                
            case 'add_note':
                $response = $this->addNote();
                break;
                
            case 'update_note':
                $response = $this->updateNote();
                break;
                
            case 'delete_note':
                $response = $this->deleteNote();
                break;
                
            case 'archive_note':
                $response = $this->archiveNote();
                break;
                
            case 'get_notes':
                $response = $this->getNotes();
                break;
                
            case 'add_network':
                $response = $this->addNetwork();
                break;
                
            case 'get_purchase_history':
                $response = $this->getPurchaseHistory();
                break;
                
            case 'get_platform_balance':
                $response = $this->getPlatformBalance();
                break;
                
            case 'add_expense':
                $response = $this->addExpense();
                break;
                
            case 'get_expenses':
                $response = $this->getExpenses();
                break;
                
            case 'delete_expense':
                $response = $this->deleteExpense();
                break;
                
            case 'get_expense_categories':
                $response = $this->getExpenseCategories();
                break;
                
            case 'add_expense_category':
                $response = $this->addExpenseCategory();
                break;
                
            case 'delete_operation':
                $response = $this->deleteOperation();
                break;
        }
        
        return $response;
    }
    
    private function addTrade() {
        $result = $this->tradeRepo->create(
            $_POST['operation_type'],
            $_POST['platform_id'],
            $_POST['from_platform_id'] ?? $_POST['platform_id'],
            $_POST['asset_id'],
            $_POST['quantity'],
            $_POST['price'],
            $_POST['price_currency'],
            $_POST['commission'] ?? 0,
            $_POST['commission_currency'] ?? null,
            $_POST['network'] ?? null,
            $_POST['operation_date'],
            $_POST['notes'] ?? ''
        );
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Операция успешно добавлена' : 'Ошибка: ' . $result['message']
        ];
    }
    
    private function addDeposit() {
        $result = $this->depositRepo->create(
            $_POST['platform_id'],
            $_POST['amount'],
            $_POST['currency'],
            $_POST['deposit_date'],
            $_POST['notes'] ?? ''
        );
        
        return [
            'success' => $result,
            'message' => $result ? 'Пополнение успешно добавлено' : 'Ошибка при добавлении пополнения'
        ];
    }
    
    private function addTransfer() {
        $result = $this->transferRepo->create(
            $_POST['from_platform_id'],
            $_POST['to_platform_id'],
            $_POST['asset_id'],
            $_POST['quantity'],
            $_POST['commission'] ?? 0,
            $_POST['commission_currency'] ?? null,
            $_POST['from_network'] ?? null,
            $_POST['to_network'] ?? null,
            $_POST['transfer_date'],
            $_POST['notes'] ?? ''
        );
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Перевод успешно добавлен' : 'Ошибка: ' . $result['message']
        ];
    }
    
    private function addPlatform() {
        $name = trim($_POST['name']);
        $type = $_POST['type'] ?? 'other';
        $country = $_POST['country'] ?? null;
        
        if (empty($name)) {
            return ['success' => false, 'message' => 'Название площадки обязательно'];
        }
        
        $existing = $this->platformRepo->getByName($name);
        
        if ($existing) {
            return ['success' => true, 'message' => 'Площадка уже существует', 'platform_id' => $existing['id']];
        }
        
        $typeMapping = [
            'exchange' => 'exchange',
            'broker' => 'broker',
            'bank' => 'bank',
            'wallet' => 'wallet',
            'other' => 'other'
        ];
        
        $dbType = $typeMapping[$type] ?? 'other';
        $platformId = $this->platformRepo->create($name, $dbType, $country);
        
        return [
            'success' => true,
            'message' => 'Площадка успешно добавлена',
            'platform_id' => $platformId
        ];
    }
    
    private function addCurrency() {
        $code = strtoupper(trim($_POST['code']));
        $name = trim($_POST['name']);
        $type = $_POST['type'] ?? 'fiat';
        $symbol = $_POST['symbol'] ?? null;
        
        if (empty($code) || empty($name)) {
            return ['success' => false, 'message' => 'Код и название валюты обязательны'];
        }
        
        $stmt = $this->pdo->prepare("SELECT code FROM currencies WHERE code = ?");
        $stmt->execute([$code]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return ['success' => true, 'message' => 'Валюта уже существует', 'currency_id' => $code];
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO currencies (code, name, type, symbol) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$code, $name, $type, $symbol]);
        
        $assetType = ($type == 'fiat') ? 'currency' : 'crypto';
        $stmt = $this->pdo->prepare("
            INSERT INTO assets (symbol, name, type, currency_code, is_active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
        $stmt->execute([$code, $name, $assetType, $code]);
        
        return [
            'success' => true,
            'message' => 'Валюта успешно добавлена',
            'currency_id' => $code
        ];
    }
    
    private function addAsset() {
        $symbol = $_POST['symbol'] ?? '';
        $name = $_POST['name'] ?? '';
        $type = $_POST['type'] ?? 'other';
        $currencyCode = $_POST['currency_code'] ?? null;
        $sector = $_POST['sector'] ?? null;
        
        if (empty($symbol) || empty($name)) {
            return ['success' => false, 'message' => 'Символ и название обязательны'];
        }
        
        $existing = $this->assetRepo->getBySymbol($symbol);
        
        if ($existing) {
            return ['success' => false, 'message' => 'Актив с таким символом уже существует'];
        }
        
        $assetId = $this->assetRepo->create($symbol, $name, $type, $currencyCode, $sector);
        
        return [
            'success' => true,
            'message' => 'Актив успешно добавлен',
            'asset_id' => $assetId
        ];
    }
    
    private function saveTheme() {
        $theme = $_POST['theme'] ?? 'light';
        $result = saveUserTheme($this->pdo, $theme);
        
        return [
            'success' => $result,
            'message' => $result ? 'Тема сохранена' : 'Ошибка при сохранении темы'
        ];
    }
    
    private function addLimitOrder() {
        $operationType = $_POST['operation_type'] ?? 'buy';
        $platformId = $_POST['platform_id'] ?? 0;
        $assetId = $_POST['asset_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 0;
        $limitPrice = $_POST['limit_price'] ?? 0;
        $priceCurrency = $_POST['price_currency'] ?? 'USD';
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $notes = $_POST['notes'] ?? '';
        
        if (!$platformId || !$assetId || $quantity <= 0 || $limitPrice <= 0) {
            return ['success' => false, 'message' => 'Заполните все обязательные поля'];
        }
        
        $result = $this->limitOrderRepo->create(
            $operationType, $platformId, $assetId, $quantity,
            $limitPrice, $priceCurrency, $expiryDate, $notes
        );
        
        return [
            'success' => $result,
            'message' => $result ? 'Лимитный ордер успешно создан' : 'Ошибка при создании ордера'
        ];
    }
    
    private function executeLimitOrder() {
        $orderId = $_POST['order_id'] ?? 0;
        
        if (!$orderId) {
            return ['success' => false, 'message' => 'ID ордера не указан'];
        }
        
        $order = $this->limitOrderRepo->getById($orderId);
        
        if (!$order || $order['status'] != 'active') {
            return ['success' => false, 'message' => 'Ордер не найден или уже не активен'];
        }
        
        $this->pdo->beginTransaction();
        
        try {
            if ($order['operation_type'] == 'buy') {
                $result = $this->tradeRepo->create(
                    'buy',
                    $order['platform_id'],
                    $order['platform_id'],
                    $order['asset_id'],
                    $order['quantity'],
                    $order['limit_price'],
                    $order['price_currency'],
                    0, null, null, date('Y-m-d'),
                    'Исполнение лимитного ордера #' . $orderId
                );
            } else {
                $result = $this->tradeRepo->create(
                    'sell',
                    $order['platform_id'],
                    $order['platform_id'],
                    $order['asset_id'],
                    $order['quantity'],
                    $order['limit_price'],
                    $order['price_currency'],
                    0, null, null, date('Y-m-d'),
                    'Исполнение лимитного ордера #' . $orderId
                );
            }
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $this->limitOrderRepo->execute($orderId);
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'Ордер успешно исполнен'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Ошибка при исполнении ордера: ' . $e->getMessage()];
        }
    }
    
    private function cancelLimitOrder() {
        $orderId = $_POST['order_id'] ?? 0;
        
        if (!$orderId) {
            return ['success' => false, 'message' => 'ID ордера не указан'];
        }
        
        $result = $this->limitOrderRepo->updateStatus($orderId, 'cancelled');
        
        return [
            'success' => $result,
            'message' => $result ? 'Ордер отменен' : 'Ордер не найден или уже не активен'
        ];
    }
    
    private function addNote() {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $noteType = $_POST['note_type'] ?? 'general';
        $reminderDate = !empty($_POST['reminder_date']) ? $_POST['reminder_date'] : null;
        
        if (empty($content)) {
            return ['success' => false, 'message' => 'Содержание заметки обязательно'];
        }
        
        $noteId = $this->noteRepo->create($title, $content, $noteType, $reminderDate);
        
        return [
            'success' => true,
            'message' => 'Заметка успешно добавлена',
            'note_id' => $noteId
        ];
    }
    
    private function updateNote() {
        $noteId = $_POST['note_id'] ?? 0;
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $noteType = $_POST['note_type'] ?? 'general';
        $reminderDate = !empty($_POST['reminder_date']) ? $_POST['reminder_date'] : null;
        
        if (!$noteId) {
            return ['success' => false, 'message' => 'ID заметки не указан'];
        }
        
        if (empty($content)) {
            return ['success' => false, 'message' => 'Содержание заметки обязательно'];
        }
        
        $result = $this->noteRepo->update($noteId, $title, $content, $noteType, $reminderDate);
        
        return [
            'success' => $result,
            'message' => $result ? 'Заметка успешно обновлена' : 'Ошибка при обновлении заметки'
        ];
    }
    
    private function deleteNote() {
        $noteId = $_POST['note_id'] ?? 0;
        
        if (!$noteId) {
            return ['success' => false, 'message' => 'ID заметки не указан'];
        }
        
        $result = $this->noteRepo->delete($noteId);
        
        return [
            'success' => $result,
            'message' => $result ? 'Заметка успешно удалена' : 'Ошибка при удалении заметки'
        ];
    }
    
    private function archiveNote() {
        $noteId = $_POST['note_id'] ?? 0;
        $archive = $_POST['archive'] ?? 1;
        
        if (!$noteId) {
            return ['success' => false, 'message' => 'ID заметки не указан'];
        }
        
        $result = $this->noteRepo->archive($noteId, $archive == 1);
        
        return [
            'success' => $result,
            'message' => $result ? ($archive ? 'Заметка архивирована' : 'Заметка восстановлена') : 'Ошибка при архивации'
        ];
    }
    
    private function getNotes() {
        $includeArchived = $_POST['include_archived'] ?? 0;
        $archivedMode = $includeArchived == 1 ? 'archived' : false;
        
        $notes = $this->noteRepo->getAll($archivedMode);
        
        return [
            'success' => true,
            'notes' => $notes
        ];
    }
    
    private function addNetwork() {
        $name = strtoupper(trim($_POST['name']));
        $icon = $_POST['icon'] ?? 'fas fa-network-wired';
        $color = $_POST['color'] ?? '#ff9f4a';
        $fullName = trim($_POST['full_name'] ?? $name);
        
        if (empty($name)) {
            return ['success' => false, 'message' => 'Название сети обязательно'];
        }
        
        $existing = $this->networkRepo->getByName($name);
        
        if ($existing) {
            return ['success' => true, 'message' => 'Сеть уже существует', 'network_id' => $existing['id']];
        }
        
        $networkId = $this->networkRepo->create($name, $icon, $color, $fullName);
        
        return [
            'success' => true,
            'message' => 'Сеть успешно добавлена',
            'network_id' => $networkId
        ];
    }
    
    private function getPurchaseHistory() {
        $assetId = $_POST['asset_id'] ?? 0;
        $platformId = $_POST['platform_id'] ?? 0;
        
        if (!$assetId || !$platformId) {
            return ['success' => false, 'message' => 'Не указан актив или площадка'];
        }
        
        $history = $this->tradeRepo->getPurchaseHistory($assetId, $platformId);
        
        return [
            'success' => true,
            'data' => $history
        ];
    }
    
    private function getPlatformBalance() {
        $platformId = $_POST['platform_id'] ?? 0;
        
        if (!$platformId) {
            return ['success' => false, 'message' => 'Не указана площадка'];
        }
        
        return $this->platformRepo->getBalance($platformId);
    }
    
    private function addExpense() {
        $amount = floatval($_POST['amount'] ?? 0);
        $currencyCode = strtoupper($_POST['currency_code'] ?? 'RUB');
        $categoryId = intval($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['expense_date'] ?? date('Y-m-d');
        
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Сумма расхода должна быть больше 0'];
        }
        
        if (!$categoryId) {
            return ['success' => false, 'message' => 'Выберите категорию расхода'];
        }
        
        $result = $this->expenseRepo->addExpense($amount, $currencyCode, $categoryId, $description, $date);
        
        return [
            'success' => $result,
            'message' => $result ? 'Расход успешно добавлен' : 'Ошибка при добавлении расхода'
        ];
    }
    
    private function getExpenses() {
        $limit = intval($_POST['limit'] ?? 10);
        $offset = intval($_POST['offset'] ?? 0);
        $category = $_POST['category'] ?? null;
        $dateFrom = $_POST['date_from'] ?? null;
        $dateTo = $_POST['date_to'] ?? null;
        
        return $this->expenseRepo->getExpenses($limit, $offset, $category, $dateFrom, $dateTo);
    }
    
    private function deleteExpense() {
        $expenseId = intval($_POST['expense_id'] ?? 0);
        
        if (!$expenseId) {
            return ['success' => false, 'message' => 'ID расхода не указан'];
        }
        
        $result = $this->expenseRepo->deleteExpense($expenseId);
        
        return [
            'success' => $result,
            'message' => $result ? 'Расход удален' : 'Ошибка при удалении расхода'
        ];
    }
    
    private function getExpenseCategories() {
        $categories = $this->expenseRepo->getCategories();
        
        return [
            'success' => true,
            'categories' => $categories
        ];
    }
    
    private function addExpenseCategory() {
        $name = strtolower(trim($_POST['name'] ?? ''));
        $nameRu = trim($_POST['name_ru'] ?? '');
        $icon = $_POST['icon'] ?? 'fas fa-tag';
        $color = $_POST['color'] ?? '#ff9f4a';
        
        if (empty($name) || empty($nameRu)) {
            return ['success' => false, 'message' => 'Название категории обязательно'];
        }
        
        $result = $this->expenseRepo->addCategory($name, $nameRu, $icon, $color);
        
        return [
            'success' => $result,
            'message' => $result ? 'Категория добавлена' : 'Ошибка при добавлении категории'
        ];
    }
    
    private function deleteOperation() {
        $operationId = isset($_POST['operation_id']) ? (int)$_POST['operation_id'] : 0;
        $sourceTable = isset($_POST['source_table']) ? $_POST['source_table'] : '';
        
        if (!$operationId || !$sourceTable) {
            return ['success' => false, 'message' => 'Недостаточно данных для удаления'];
        }
        
        return $this->operationRepo->delete($operationId, $sourceTable);
    }
}