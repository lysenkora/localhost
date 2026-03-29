<!-- src/Views/portfolio/index.php -->
<div class="page-header">
    <h1>Инвестиционный портфель</h1>
    <div class="summary-cards">
        <div class="card">
            <h3>Всего позиций</h3>
            <p class="value"><?= count($portfolio) ?></p>
        </div>
        <div class="card">
            <h3>Типы активов</h3>
            <p class="value"><?= count($summary) ?></p>
        </div>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Актив</th>
            <th>Тип</th>
            <th>Количество</th>
            <th>Средняя цена</th>
            <th>Платформа</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($portfolio)): ?>
            <tr>
                <td colspan="6">Нет данных в портфеле</td>
            </tr>
        <?php else: ?>
            <?php foreach ($portfolio as $item): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($item['symbol']) ?></strong><br>
                        <small><?= htmlspecialchars($item['name']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($item['type']) ?></td>
                    <td><?= number_format($item['quantity'], 4) ?></td>
                    <td><?= $item['average_buy_price'] ? number_format($item['average_buy_price'], 4) : '-' ?></td>
                    <td><?= htmlspecialchars($item['platform_name']) ?></td>
                    <td>
                        <a href="/?action=portfolio&method=edit&id=<?= $item['id'] ?>" class="btn-small">✏️</a>
                     </td>
                 </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>