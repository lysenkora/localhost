<?php
// fix_files.php - запустить один раз
$files = [
    'config/database.php',
    'config/constants.php',
    'helpers/functions.php',
    'helpers/Formatter.php',
    'repositories/PlatformRepository.php',
    'repositories/AssetRepository.php',
    'repositories/TradeRepository.php',
    'repositories/DepositRepository.php',
    'repositories/TransferRepository.php',
    'repositories/OperationRepository.php',
    'repositories/PortfolioRepository.php',
    'repositories/NetworkRepository.php',
    'repositories/NoteRepository.php',
    'repositories/LimitOrderRepository.php',
    'repositories/ExpenseRepository.php',
    'services/CalculationService.php',
    'controllers/DashboardController.php',
    'controllers/ApiController.php',
    'controllers/OperationController.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Проверяем, начинается ли с <?php
        if (strpos(trim($content), '<?php') !== 0) {
            echo "Fixing: $file\n";
            $content = "<?php\n" . $content;
            file_put_contents($file, $content);
        } else {
            echo "OK: $file\n";
        }
    } else {
        echo "MISSING: $file\n";
    }
}

echo "Done!";