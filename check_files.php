<?php
echo "<pre>";

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
    $exists = file_exists($file);
    echo $file . " - " . ($exists ? "OK" : "MISSING") . "\n";
}

echo "</pre>";