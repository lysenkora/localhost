<!-- src/Views/trades/form.php -->
<div class="form-container">
    <h1>Добавить сделку</h1>
    
    <form method="POST">
        <div class="form-group">
            <label>Тип операции *</label>
            <select name="operation_type" required>
                <option value="buy">Покупка</option>
                <option value="sell">Продажа</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Актив *</label>
            <select name="asset_id" required>
                <option value="">Выберите актив</option>
                <?php foreach ($assets as $asset): ?>
                    <option value="<?= $asset['id'] ?>">
                        <?= htmlspecialchars($asset['symbol']) ?> - <?= htmlspecialchars($asset['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Платформа *</label>
            <select name="platform_id" required>
                <option value="1">Т-Банк</option>
                <option value="8">Bybit</option>
                <option value="14">MetaMask</option>
                <option value="19">Freedom Global</option>
            </select>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Количество *</label>
                <input type="number" name="quantity" step="0.00000001" required>
            </div>
            
            <div class="form-group">
                <label>Цена *</label>
                <input type="number" name="price" step="0.00000001" required>
            </div>
            
            <div class="form-group">
                <label>Валюта цены *</label>
                <select name="price_currency" required>
                    <option value="USD">USD</option>
                    <option value="RUB">RUB</option>
                    <option value="USDT">USDT</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Комиссия</label>
                <input type="number" name="commission" step="0.00000001" value="0">
            </div>
            
            <div class="form-group">
                <label>Валюта комиссии</label>
                <select name="commission_currency">
                    <option value="">Не указано</option>
                    <option value="USD">USD</option>
                    <option value="RUB">RUB</option>
                    <option value="USDT">USDT</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Дата операции *</label>
            <input type="date" name="operation_date" required>
        </div>
        
        <div class="form-group">
            <label>Примечания</label>
            <textarea name="notes" rows="3"></textarea>
        </div>
        
        <button type="submit" class="btn">Сохранить сделку</button>
        <a href="/?action=trades" class="btn-cancel">Отмена</a>
    </form>
</div>