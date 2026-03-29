<!-- src/Views/assets/view.php -->
<div class="asset-detail">
    <h1><?= htmlspecialchars($asset['symbol']) ?> - <?= htmlspecialchars($asset['name']) ?></h1>
    
    <div class="info-grid">
        <div class="info-card">
            <strong>Тип:</strong> <?= htmlspecialchars($asset['type']) ?>
        </div>
        <div class="info-card">
            <strong>Сектор:</strong> <?= htmlspecialchars($asset['sector'] ?? '-') ?>
        </div>
        <div class="info-card">
            <strong>Валюта:</strong> <?= htmlspecialchars($asset['currency_code'] ?? '-') ?>
        </div>
        <div class="info-card">
            <strong>Статус:</strong> <?= $asset['is_active'] ? 'Активен' : 'Неактивен' ?>
        </div>
    </div>
    
    <a href="/?action=trades&asset_id=<?= $asset['id'] ?>" class="btn">Смотреть сделки</a>
    <a href="/?action=assets" class="btn-cancel">Назад к списку</a>
</div>