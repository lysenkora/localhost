<!-- src/Views/portfolio/edit.php -->
<div class="form-container">
    <h1>Редактирование позиции</h1>
    
    <form method="POST">
        <div class="form-group">
            <label>Количество</label>
            <input type="number" name="quantity" step="0.00000001" value="<?= htmlspecialchars($item['quantity'] ?? '') ?>" required>
        </div>
        
        <div class="form-group">
            <label>Средняя цена покупки</label>
            <input type="number" name="average_buy_price" step="0.00000001" value="<?= htmlspecialchars($item['average_buy_price'] ?? '') ?>">
        </div>
        
        <button type="submit" class="btn">Сохранить</button>
        <a href="/" class="btn-cancel">Отмена</a>
    </form>
</div>