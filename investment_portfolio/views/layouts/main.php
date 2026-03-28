<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Планеро.Инвестиции' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/theme.css">
</head>
<body class="<?= $current_theme === 'dark' ? 'dark-theme' : '' ?>">
    <div class="notification-container" id="notificationContainer"></div>
    
    <?= $content ?? '' ?>
    
    <script src="/js/app.js"></script>
    <script src="/js/api.js"></script>
    <script src="/js/modules/notifications.js"></script>
    <script src="/js/modules/operations.js"></script>
    <script src="/js/modules/forms.js"></script>
    <script src="/js/modules/modals.js"></script>
    <script src="/js/modules/theme.js"></script>
    
    <script>
        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            const themeManager = new ThemeManager();
            themeManager.init('<?= $current_theme ?>');
            
            const operationsManager = new OperationsManager();
            operationsManager.init();
        });
    </script>
</body>
</html>