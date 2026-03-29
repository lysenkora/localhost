<?php
// ============================================================================
// СТРАНИЦА ИСТОРИИ ОПЕРАЦИЙ
// ============================================================================

extract($data);

$formatAmount = function($amount, $currency) {
    if (!$amount || $amount <= 0) return '';
    $decimals = (in_array($currency, ['BTC', 'ETH', 'USDT', 'USDC'])) ? 6 : 2;
    return number_format($amount, $decimals, '.', ' ') . ' ' . $currency;
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История операций | Планеро.Инвестиции</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Стили из operations.php (копируем из предоставленного файла) */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #2c3e50;
            line-height: 1.6;
            padding: 24px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f0f3f7;
            color: #6b7a8f;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .back-link:hover {
            transform: translateY(-2px);
            background: white;
            border-color: #1a5cff;
            color: #1a5cff;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: #6b7a8f;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }

        .filters {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 500;
            color: #6b7a8f;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 14px;
            border: 1px solid #e0e6ed;
            border-radius: 12px;
            font-size: 14px;
        }

        .filter-buttons {
            display: flex;
            gap: 12px;
            grid-column: 1 / -1;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: #1a5cff;
            color: white;
        }

        .btn-secondary {
            background: #f0f3f7;
            color: #2c3e50;
        }

        .operations-table-container {
            background: white;
            border-radius: 20px;
            overflow-x: auto;
        }

        .operations-table {
            width: 100%;
            border-collapse: collapse;
        }

        .operations-table th {
            text-align: left;
            padding: 16px;
            font-size: 13px;
            font-weight: 600;
            color: #6b7a8f;
            border-bottom: 1px solid #edf2f7;
        }

        .operations-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
        }

        .operations-table tr:hover td {
            background: #f8fafd;
        }

        .operation-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-buy { background: #e6f7e6; color: #00a86b; }
        .badge-sell { background: #ffe6e6; color: #e53e3e; }
        .badge-deposit { background: #e3f2fd; color: #1976d2; }
        .badge-transfer { background: #fff4e6; color: #ff9f4a; }

        .amount-positive { color: #00a86b; font-weight: 600; }
        .amount-negative { color: #e53e3e; font-weight: 600; }

        .operation-details-btn,
        .operation-delete-btn {
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
            margin: 0 4px;
        }

        .operation-details-btn { color: #1a5cff; }
        .operation-delete-btn { color: #e53e3e; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            padding: 20px;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border-radius: 10px;
            background: white;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            border: 1px solid #e0e6ed;
        }

        .page-link:hover,
        .page-link.active {
            background: #1a5cff;
            color: white;
            border-color: #1a5cff;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #edf2f7;
        }

        .detail-label {
            width: 100px;
            font-weight: 500;
            color: #6b7a8f;
        }

        .detail-value {
            flex: 1;
            font-weight: 500;
            color: #2c3e50;
        }

        /* Темная тема */
        body.dark-theme {
            background: #0C0E12;
            color: #FFFFFF;
        }

        .dark-theme .header,
        .dark-theme .stat-card,
        .dark-theme .filters,
        .dark-theme .operations-table-container,
        .dark-theme .modal {
            background: #15181C;
            border: 1px solid #2A2F36;
        }

        .dark-theme .header h1,
        .dark-theme .stat-card .stat-value {
            color: #FFFFFF;
        }

        .dark-theme .operations-table th {
            background: #15181C;
            color: #6B7A8F;
        }

        .dark-theme .operations-table td {
            color: #9AA5B5;
        }

        .dark-theme .filter-group select,
        .dark-theme .filter-group input {
            background: #1E2228;
            border-color: #2A2F36;
            color: #FFFFFF;
        }

        @media (max-width: 768px) {
            body { padding: 12px; }
            .filter-form { grid-template-columns: 1fr; }
            .operations-table th,
            .operations-table td { padding: 10px 8px; font-size: 12px; }
        }
    </style>
</head>
<body class="<?= $current_theme === 'dark' ? 'dark-theme' : '' ?>">
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-history"></i> История операций</h1>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Вернуться на дашборд</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-chart-bar"></i> Всего операций</div>
                <div class="stat-value"><?= number_format($total, 0, '.', ' ') ?></div>
                <div class="stat-detail">Покупок: <?= $trade_stats['buy_count'] ?? 0 ?> | Продаж: <?= $trade_stats['sell_count'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-download"></i> Пополнения</div>
                <div class="stat-value"><?= number_format($deposit_stats['total_count'], 0, '.', ' ') ?></div>
                <div class="stat-detail">RUB: <?= number_format($deposit_stats['total_rub_deposits'], 0, '.', ' ') ?> ₽<br>USD: <?= number_format($deposit_stats['total_usd_deposits'], 2, '.', ' ') ?> $</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-chart-line"></i> Объем торгов</div>
                <div class="stat-value">$<?= number_format(($trade_stats['total_buy_amount'] + $trade_stats['total_sell_amount']) / $usd_rub_rate, 0, '.', ' ') ?></div>
                <div class="stat-detail">Покупки: $<?= number_format($trade_stats['total_buy_amount'] / $usd_rub_rate, 0, '.', ' ') ?><br>Продажи: $<?= number_format($trade_stats['total_sell_amount'] / $usd_rub_rate, 0, '.', ' ') ?></div>
                <div class="stat-note">*по курсу <?= number_format($usd_rub_rate, 2, '.', ' ') ?> ₽/$</div>
            </div>
        </div>

        <div class="filters">
            <form method="GET" class="filter-form">
                <input type="hidden" name="page" value="operations">
                <div class="filter-group">
                    <label>Тип операции</label>
                    <select name="type">
                        <option value="all" <?= $filters['type'] == 'all' ? 'selected' : '' ?>>Все операции</option>
                        <option value="buy" <?= strpos($filters['type'], 'buy') !== false ? 'selected' : '' ?>>Покупки</option>
                        <option value="sell" <?= strpos($filters['type'], 'sell') !== false ? 'selected' : '' ?>>Продажи</option>
                        <option value="deposit" <?= $filters['type'] == 'deposit' ? 'selected' : '' ?>>Пополнения</option>
                        <option value="transfer" <?= strpos($filters['type'], 'transfer') !== false ? 'selected' : '' ?>>Переводы</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Площадка</label>
                    <select name="platform">
                        <option value="0">Все площадки</option>
                        <?php foreach ($platforms as $platform): ?>
                        <option value="<?= $platform['id'] ?>" <?= $filters['platform_id'] == $platform['id'] ? 'selected' : '' ?>><?= htmlspecialchars($platform['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Актив</label>
                    <select name="asset">
                        <option value="0">Все активы</option>
                        <?php foreach ($assets as $asset): ?>
                        <option value="<?= $asset['id'] ?>" <?= $filters['asset_id'] == $asset['id'] ? 'selected' : '' ?>><?= htmlspecialchars($asset['symbol']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Дата с</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                <div class="filter-group">
                    <label>Дата по</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Применить</button>
                    <a href="index.php?page=operations" class="btn btn-secondary"><i class="fas fa-times"></i> Сбросить</a>
                </div>
            </form>
        </div>

        <div class="operations-table-container">
            <?php if (empty($operations)): ?>
            <div style="text-align: center; padding: 60px; color: #6b7a8f;">
                <i class="fas fa-inbox" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                <p>Нет операций для отображения</p>
            </div>
            <?php else: ?>
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Тип</th>
                        <th>Актив / Площадка</th>
                        <th>Сумма</th>
                        <th style="text-align: center;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $groupedOps = [];
                    foreach ($operations as $op) {
                        if ($op['operation_type'] == 'buy_asset') $groupedOps[$op['operation_id']]['buy'] = $op;
                        elseif ($op['operation_type'] == 'buy_payment') $groupedOps[$op['operation_id']]['payment'] = $op;
                        elseif ($op['operation_type'] == 'sell_asset') $groupedOps[$op['operation_id']]['sell'] = $op;
                        elseif ($op['operation_type'] == 'sell_income') $groupedOps[$op['operation_id']]['income'] = $op;
                        else $groupedOps[$op['operation_id']]['other'] = $op;
                    }
                    
                    foreach ($groupedOps as $group):
                        if (isset($group['buy']) && isset($group['payment'])):
                            $buy = $group['buy'];
                            $payment = $group['payment'];
                    ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($buy['date'])) ?></td>
                        <td><span class="operation-badge badge-buy"><i class="fas fa-arrow-down"></i> Покупка</span></td>
                        <td>
                            <div class="amount-positive">+<?= $formatAmount($buy['amount'], $buy['currency']) ?></div>
                            <div style="font-size: 11px; color: #6b7a8f;"><i class="fas fa-building"></i> <?= htmlspecialchars($buy['platform']) ?></div>
                        </td>
                        <td>
                            <div class="amount-negative">-<?= $formatAmount($payment['amount_out'], $payment['currency']) ?></div>
                            <div style="font-size: 11px; color: #6b7a8f;"><i class="fas fa-credit-card"></i> <?= htmlspecialchars($payment['platform']) ?></div>
                        </td>
                        <td style="text-align: center;">
                            <i class="fas fa-info-circle operation-details-btn" onclick='showOperationDetails(<?= json_encode($buy, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'></i>
                            <i class="fas fa-trash-alt operation-delete-btn" onclick="confirmDeleteOperation(<?= $buy['operation_id'] ?>, '<?= $buy['operation_type'] ?>', '<?= $buy['source_table'] ?>')"></i>
                        </td>
                    </tr>
                    <?php elseif (isset($group['sell']) && isset($group['income'])):
                            $sell = $group['sell'];
                            $income = $group['income'];
                    ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($sell['date'])) ?></td>
                        <td><span class="operation-badge badge-sell"><i class="fas fa-arrow-up"></i> Продажа</span></td>
                        <td>
                            <div class="amount-negative">-<?= $formatAmount($sell['amount_out'], $sell['currency']) ?></div>
                            <div style="font-size: 11px; color: #6b7a8f;"><i class="fas fa-building"></i> <?= htmlspecialchars($sell['platform']) ?></div>
                        </td>
                        <td>
                            <div class="amount-positive">+<?= $formatAmount($income['amount'], $income['currency']) ?></div>
                            <div style="font-size: 11px; color: #6b7a8f;"><i class="fas fa-credit-card"></i> <?= htmlspecialchars($income['platform']) ?></div>
                        </td>
                        <td style="text-align: center;">
                            <i class="fas fa-info-circle operation-details-btn" onclick='showOperationDetails(<?= json_encode($sell, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'></i>
                            <i class="fas fa-trash-alt operation-delete-btn" onclick="confirmDeleteOperation(<?= $sell['operation_id'] ?>, '<?= $sell['operation_type'] ?>', '<?= $sell['source_table'] ?>')"></i>
                        </td>
                    </tr>
                    <?php elseif (isset($group['other']) && $group['other']['operation_type'] == 'deposit'):
                            $deposit = $group['other'];
                    ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($deposit['date'])) ?></td>
                        <td><span class="operation-badge badge-deposit"><i class="fas fa-plus-circle"></i> Пополнение</span></td>
                        <td>
                            <div class="amount-positive">+<?= $formatAmount($deposit['amount'], $deposit['currency']) ?></div>
                            <div style="font-size: 11px; color: #6b7a8f;"><i class="fas fa-building"></i> <?= htmlspecialchars($deposit['platform']) ?></div>
                        </td>
                        <td class="amount-positive">+<?= $formatAmount($deposit['amount'], $deposit['currency']) ?></td>
                        <td style="text-align: center;">
                            <i class="fas fa-info-circle operation-details-btn" onclick='showOperationDetails(<?= json_encode($deposit, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'></i>
                            <i class="fas fa-trash-alt operation-delete-btn" onclick="confirmDeleteOperation(<?= $deposit['operation_id'] ?>, '<?= $deposit['operation_type'] ?>', '<?= $deposit['source_table'] ?>')"></i>
                        </td>
                    </tr>
                    <?php elseif (isset($group['other']) && in_array($group['other']['operation_type'], ['transfer_in', 'transfer_out'])):
                            $transfer = $group['other'];
                            $isIn = ($transfer['operation_type'] == 'transfer_in');
                    ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($transfer['date'])) ?></td>
                        <td><span class="operation-badge badge-transfer"><i class="fas fa-exchange-alt"></i> <?= $isIn ? 'Входящий' : 'Исходящий' ?> перевод</span></td>
                        <td>
                            <div class="<?= $isIn ? 'amount-positive' : 'amount-negative' ?>">
                                <?= $isIn ? '+' : '-' ?><?= $formatAmount($transfer['amount'] > 0 ? $transfer['amount'] : $transfer['amount_out'], $transfer['currency']) ?>
                            </div>
                            <div style="font-size: 11px; color: #6b7a8f;"><i class="fas fa-building"></i> <?= htmlspecialchars($transfer['platform']) ?></div>
                        </td>
                        <td>—</td>
                        <td style="text-align: center;">
                            <i class="fas fa-info-circle operation-details-btn" onclick='showOperationDetails(<?= json_encode($transfer, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'></i>
                            <i class="fas fa-trash-alt operation-delete-btn" onclick="confirmDeleteOperation(<?= $transfer['operation_id'] ?>, '<?= $transfer['operation_type'] ?>', '<?= $transfer['source_table'] ?>')"></i>
                        </td>
                    </tr>
                    <?php endif; endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                <a href="?page=operations&p=<?= $current_page - 1 ?>&type=<?= urlencode($filters['type']) ?>&platform=<?= $filters['platform_id'] ?>&asset=<?= $filters['asset_id'] ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>" class="page-link"><i class="fas fa-chevron-left"></i> Назад</a>
                <?php endif; ?>
                <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                <a href="?page=operations&p=<?= $i ?>&type=<?= urlencode($filters['type']) ?>&platform=<?= $filters['platform_id'] ?>&asset=<?= $filters['asset_id'] ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>" class="page-link <?= $i == $current_page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages): ?>
                <a href="?page=operations&p=<?= $current_page + 1 ?>&type=<?= urlencode($filters['type']) ?>&platform=<?= $filters['platform_id'] ?>&asset=<?= $filters['asset_id'] ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>" class="page-link">Вперед <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="operationDetailsModal">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-info-circle"></i> Детали операции</h2>
                <button class="modal-close" onclick="closeOperationModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <script>
        function showOperationDetails(operation) {
            const modal = document.getElementById('operationDetailsModal');
            const modalBody = document.getElementById('modalBody');
            
            let html = `
                <div class="detail-row">
                    <div class="detail-label">Дата:</div>
                    <div class="detail-value">${new Date(operation.date).toLocaleDateString('ru-RU')}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Тип:</div>
                    <div class="detail-value">${operation.short_description}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Площадка:</div>
                    <div class="detail-value">${operation.platform}</div>
                </div>
            `;
            
            if (operation.amount > 0) {
                html += `<div class="detail-row"><div class="detail-label">Получено:</div><div class="detail-value amount-positive">+${Number(operation.amount).toLocaleString('ru-RU')} ${operation.currency}</div></div>`;
            }
            if (operation.amount_out > 0) {
                html += `<div class="detail-row"><div class="detail-label">Списано:</div><div class="detail-value amount-negative">-${Number(operation.amount_out).toLocaleString('ru-RU')} ${operation.currency}</div></div>`;
            }
            if (operation.price) {
                html += `<div class="detail-row"><div class="detail-label">Цена:</div><div class="detail-value">${Number(operation.price).toLocaleString('ru-RU')} ${operation.price_currency}</div></div>`;
            }
            if (operation.commission && operation.commission > 0) {
                html += `<div class="detail-row"><div class="detail-label">Комиссия:</div><div class="detail-value">${Number(operation.commission).toLocaleString('ru-RU')} ${operation.commission_currency || ''}</div></div>`;
            }
            if (operation.notes) {
                html += `<div class="detail-row"><div class="detail-label">Комментарий:</div><div class="detail-value">${escapeHtml(operation.notes)}</div></div>`;
            }
            
            modalBody.innerHTML = html;
            modal.classList.add('active');
        }

        function closeOperationModal() {
            document.getElementById('operationDetailsModal').classList.remove('active');
        }

        function confirmDeleteOperation(operationId, operationType, sourceTable) {
            if (confirm('Вы уверены, что хотите удалить эту операцию? Это действие нельзя отменить.')) {
                deleteOperation(operationId, operationType, sourceTable);
            }
        }

        async function deleteOperation(operationId, operationType, sourceTable) {
            const formData = new FormData();
            formData.append('action', 'delete_operation');
            formData.append('operation_id', operationId);
            formData.append('operation_type', operationType);
            formData.append('source_table', sourceTable);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    alert('Операция успешно удалена');
                    location.reload();
                } else {
                    alert('Ошибка при удалении: ' + result.message);
                }
            } catch (error) {
                alert('Ошибка сети при удалении операции');
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.getElementById('operationDetailsModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeOperationModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeOperationModal();
        });
    </script>
</body>
</html>