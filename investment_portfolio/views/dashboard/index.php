<?php
$title = 'Дашборд | Планеро.Инвестиции';
ob_start();
?>

<div class="dashboard">
    <?php view('dashboard.header', ['total_usd' => $total_usd, 'total_rub' => $total_rub, 'profit' => $profit]) ?>
    
    <div class="cards-container">
        <?php view('dashboard.cards.structure', ['portfolio_structure' => $portfolio_structure]) ?>
        <?php view('dashboard.cards.crypto', ['statistics' => $statistics['crypto'] ?? []]) ?>
        <?php view('dashboard.cards.stocks_en', ['statistics' => $statistics['stocks_en'] ?? []]) ?>
        <?php view('dashboard.cards.deposits', ['statistics' => $statistics['deposits'] ?? []]) ?>
        <?php view('dashboard.cards.assets', ['assets' => $assets ?? []]) ?>
        <?php view('dashboard.cards.operations', ['operations' => $recent_operations]) ?>
        <?php view('dashboard.cards.orders', ['orders' => $limit_orders]) ?>
        <?php view('dashboard.cards.notes', ['notes' => $recent_notes]) ?>
    </div>
</div>

<?php view('modals.trade', ['platforms' => $platforms, 'assets' => $assets, 'currencies' => $currencies]) ?>
<?php view('modals.deposit', ['platforms' => $platforms, 'fiat_currencies' => $fiat_currencies]) ?>
<?php view('modals.transfer', ['platforms' => $platforms, 'assets' => $assets, 'currencies' => $currencies]) ?>
<?php view('modals.limit_order', ['platforms' => $platforms, 'assets' => $assets, 'currencies' => $currencies]) ?>
<?php view('modals.note') ?>
<?php view('modals.expense', ['expense_categories' => $expense_categories ?? []]) ?>
<?php view('modals.platform') ?>
<?php view('modals.currency', ['currencies' => $currencies]) ?>
<?php view('modals.asset') ?>
<?php view('modals.network', ['networks' => $networks]) ?>

<script>
    // Передаем данные в JavaScript
    window.platformsData = <?= json_encode($platforms) ?>;
    window.assetsData = <?= json_encode($assets) ?>;
    window.allCurrencies = <?= json_encode($currencies) ?>;
    window.fiatCurrencies = <?= json_encode($fiat_currencies) ?>;
    window.networksData = <?= json_encode($networks) ?>;
</script>

<?php
$content = ob_get_clean();
view('layouts.main', ['content' => $content, 'current_theme' => $current_theme, 'title' => $title]);