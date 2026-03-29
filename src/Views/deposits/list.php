<!-- src/Views/deposits/list.php -->
<div class="page-header">
    <h1>Пополнения</h1>
</div>

<?php if (!empty($summary)): ?>
<div class="summary-cards">
    <?php foreach ($summary as $item): ?>
    <div class="card">
        <h3><?= htmlspecialchars($item['platform_name']) ?></h3>
        <p class="value"><?= number_format($item['total_amount'], 2) ?> <?= $item['currency_code'] ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<table class="data-table">
    <thead>
        <tr>
            <th>Дата</th>
            <th>Платформа</th>
            <th>Сумма</th>
            <th>Валюта</th>
            <th>Примечания</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($deposits)): ?>
            <tr>
                <td colspan="5">Нет пополнений</td>
            </tr>
        <?php else: ?>
            <?php foreach ($deposits as $deposit): ?>
                <tr>
                    <td><?= htmlspecialchars($deposit['deposit_date']) ?></td>
                    <td><?= htmlspecialchars($deposit['platform_name']) ?></td>
                    <td><?= number_format($deposit['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($deposit['currency_code']) ?></td>
                    <td><?= htmlspecialchars($deposit['notes'] ?? '-') ?></td>
                 </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>