<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Инвестиционный портфель</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <nav class="sidebar">
            <h2>📊 Меню</h2>
            <ul>
                <li><a href="/?action=portfolio">📈 Портфель</a></li>
                <li><a href="/?action=assets">💎 Активы</a></li>
                <li><a href="/?action=trades">🔄 Сделки</a></li>
                <li><a href="/?action=deposits">💰 Пополнения</a></li>
            </ul>
        </nav>
        
        <main class="content">
            <?php
            // Подключаем нужный view в зависимости от текущего action
            $action = $_GET['action'] ?? 'portfolio';
            $method = $_GET['method'] ?? 'index';
            $id = $_GET['id'] ?? null;
            
            switch ($action) {
                case 'assets':
                    if ($method === 'view' && $id) {
                        require_once __DIR__ . '/assets/view.php';
                    } else {
                        require_once __DIR__ . '/assets/list.php';
                    }
                    break;
                case 'trades':
                    if ($method === 'add') {
                        require_once __DIR__ . '/trades/form.php';
                    } else {
                        require_once __DIR__ . '/trades/list.php';
                    }
                    break;
                case 'deposits':
                    require_once __DIR__ . '/deposits/list.php';
                    break;
                case 'portfolio':
                default:
                    if ($method === 'edit' && $id) {
                        require_once __DIR__ . '/portfolio/edit.php';
                    } else {
                        require_once __DIR__ . '/portfolio/index.php';
                    }
                    break;
            }
            ?>
        </main>
    </div>
</body>
</html>