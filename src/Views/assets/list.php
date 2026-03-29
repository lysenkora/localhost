<!-- src/Views/assets/list.php -->
<div class="page-header">
    <h1>Активы</h1>
    <p>Всего активов: <?= count($assets) ?></p>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Символ</th>
            <th>Название</th>
            <th>Тип</th>
            <th>Сектор</th>
            <th>Валюта</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($assets as $asset): ?>
            <tr>
                <td><a href="/?action=assets&method=view&id=<?= $asset['id'] ?>"><?= htmlspecialchars($asset['symbol']) ?></a></td>
                <td><?= htmlspecialchars($asset['name']) ?></td>
                <td><?= htmlspecialchars($asset['type']) ?></td>
                <td><?= htmlspecialchars($asset['sector'] ?? '-') ?></td>
                <td><?= htmlspecialchars($asset['currency_code'] ?? '-') ?></td>
                <td><a href="/?action=trades&asset_id=<?= $asset['id'] ?>">Сделки</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>