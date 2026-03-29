<!-- src/Views/trades/list.php -->
<div class="page-header">
    <h1>Сделки</h1>
    <a href="/?action=trades&method=add" class="btn">+ Добавить сделку</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Дата</th>
            <th>Тип</th>
            <th>Актив</th>
            <th>Количество</th>
            <th>Цена</th>
            <th>Валюта</th>
            <th>Платформа</th>
            <th>Комиссия</th>
            <th>Примечания</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($trades)): ?>
            <tr>
                <td colspan="9">Нет сделок</td>
            </tr>
        <?php else: ?>
            <?php foreach ($trades as $trade): ?>
                <tr>
                    <td><?= htmlspecialchars($trade['operation_date']) ?></td>
                    <td class="type-<?= $trade['operation_type'] ?>">
                        <?= $trade['operation_type'] === 'buy' ? 'Покупка' : 'Продажа' ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($trade['symbol']) ?></strong><br>
                        <small><?= htmlspecialchars($trade['name']) ?></small>
                    </td>
                    <td><?= number_format($trade['quantity'], 4) ?></td>
                    <td><?= number_format($trade['price'], 4) ?></td>
                    <td><?= htmlspecialchars($trade['price_currency']) ?></td>
                    <td><?= htmlspecialchars($trade['platform_name']) ?></td>
                    <td><?= number_format($trade['commission'], 4) ?> <?= $trade['commission_currency'] ?></td>
                    <td><?= htmlspecialchars($trade['notes'] ?? '-') ?></td>
                 </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>