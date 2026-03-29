<?php
// ============================================================================
// ГЛАВНАЯ СТРАНИЦА ДАШБОРДА
// ============================================================================

extract($data);

// Форматирование для отображения
$totalUsdFormatted = number_format($portfolio['total_usd'], 2, '.', ' ');
$totalRubFormatted = number_format($portfolio['total_rub'], 0, '.', ' ');
$rubInUsdFormatted = number_format($portfolio['rub_in_usd'], 2, '.', ' ');
$rubAmountFormatted = number_format($portfolio['rub_amount'], 0, '.', ' ');
$usdAmountFormatted = number_format($portfolio['usd_amount'], 2, '.', ' ');
$usdtAmountFormatted = number_format($portfolio['usdt_amount'], 2, '.', ' ');
$investmentsValueFormatted = number_format($portfolio['investments_value'], 2, '.', ' ');
$investmentsRubFormatted = number_format($portfolio['investments_value'] * $usd_rub_rate, 0, '.', ' ');

$profitClass = $profit['profit_class'];
$profitIcon = $profit['profit_icon'];
$profitPercentFormatted = number_format($profit['profit_percent'], 1, '.', ' ');
$profitUsdFormatted = number_format($profit['profit_usd'], 2, '.', ' ');
$profitRubFormatted = number_format($profit['profit_rub'], 0, '.', ' ');
$investedUsdFormatted = number_format($profit['invested_usd'], 2, '.', ' ');
$investedRubFormatted = number_format($profit['invested_rub'], 0, '.', ' ');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Инвестиционный портфель | Дашборд</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================================================
           СТИЛИ (полностью копируем из index.php)
           ============================================================================ */
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

        .dashboard {
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ============================================================================
           HEADER
           ============================================================================ */
        .header {
            background: white;
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            width: 100%;
        }

        .portfolio-value {
            display: flex;
            flex-direction: column;
        }

        .value-label {
            font-size: 14px;
            color: #6b7a8f;
            font-weight: 500;
        }

        .value-amount {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.2;
        }

        #rubValue {
            color: #1a5cff;
        }

        #usdValue {
            color: #00a86b;
        }

        .header-controls {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        /* ============================================================================
           КОНТЕЙНЕР С КАРТОЧКАМИ
           ============================================================================ */
        .cards-container {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            width: 100%;
            margin-bottom: 24px;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s, box-shadow 0.2s;
            flex: 0 1 auto;
            min-width: 280px;
            max-width: 100%;
        }

        .card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #1a5cff;
        }

        .stat-badge {
            background: #f0f3f7;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .card-structure { flex-basis: 380px; }
        .card-crypto { flex-basis: 320px; }
        .card-en-stocks { flex-basis: 340px; }
        .card-deposits { flex-basis: 300px; }
        .card-investments { flex-basis: 500px; flex-grow: 1; }
        .card-operations { flex-basis: 400px; flex-grow: 1; }
        .card-orders { flex-basis: 280px; }
        .card-notes { flex-basis: 300px; }

        /* ============================================================================
           ДИАГРАММЫ
           ============================================================================ */
        .pie-chart {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }

        .pie {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: 15px auto;
            flex-shrink: 0;
        }

        .chart-legend {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 6px;
            width: 100%;
            margin-top: 10px;
            padding: 0 5px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            padding: 2px 0;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 4px;
            flex-shrink: 0;
        }

        .legend-value {
            font-weight: 600;
            margin-left: auto;
            color: #2c3e50;
        }

        /* ============================================================================
           ТАБЛИЦА АКТИВОВ
           ============================================================================ */
        .investments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .investments-table td {
            padding: 8px 4px;
            vertical-align: middle;
        }

        .investments-table tr {
            cursor: pointer;
            transition: background 0.2s;
        }

        .investments-table tr:hover {
            background: #f8fafd;
        }

        .investment-icon-cell {
            width: 50px;
        }

        .investment-name-cell {
            width: 80px;
        }

        .investment-amount-cell {
            text-align: right;
            padding-right: 12px !important;
        }

        .investment-change-cell {
            text-align: right;
        }

        .investment-icon {
            width: 36px;
            height: 36px;
            background: #f0f3f7;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #1a5cff;
        }

        .investment-name {
            font-weight: 500;
        }

        .investment-amount {
            font-weight: 600;
        }

        .investment-change {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
            background: #f0f3f7;
            color: #2c3e50;
        }

        /* ============================================================================
           ОРДЕРА
           ============================================================================ */
        .order-card {
            background: #f8fafd;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border-left: 4px solid;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .order-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .order-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, transparent 50%, rgba(255,255,255,0.1) 50%);
            pointer-events: none;
        }

        .order-exchange {
            font-size: 12px;
            color: #6b7a8f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .order-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 4px;
        }

        .order-action {
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .order-price {
            font-weight: 600;
            color: #2c3e50;
            background: rgba(0,0,0,0.02);
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 13px;
        }

        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            font-size: 11px;
            color: #6b7a8f;
        }

        .order-progress {
            height: 4px;
            background: #edf2f7;
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
        }

        .order-progress-bar {
            height: 100%;
            background: #1a5cff;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .order-progress-bar.warning {
            background: #ff9f4a;
        }

        .order-progress-bar.danger {
            background: #e53e3e;
        }

        .order-card[style*="border-left-color: #00a86b"] .order-action {
            color: #00a86b;
        }

        .order-card[style*="border-left-color: #e53e3e"] .order-action {
            color: #e53e3e;
        }

        .order-empty {
            text-align: center;
            padding: 30px 20px;
            color: #6b7a8f;
        }

        .order-empty i {
            font-size: 40px;
            opacity: 0.3;
            margin-bottom: 10px;
        }

        .add-order-btn {
            background: #f0f3f7;
            border: 1px dashed #cbd5e0;
            border-radius: 30px;
            padding: 8px 16px;
            color: #2c3e50;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .add-order-btn:hover {
            background: #1a5cff;
            border-color: #1a5cff;
            color: white;
            transform: translateY(-1px);
        }

        /* ============================================================================
           ОПЕРАЦИИ
           ============================================================================ */
        .operation-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
        }

        .operation-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .icon-buy {
            background: #e6f7e6;
            color: #00a86b;
        }

        .icon-sell {
            background: #ffe6e6;
            color: #e53e3e;
        }

        .icon-convert {
            background: #fff4e6;
            color: #ff9f4a;
        }

        .operation-details {
            flex: 1;
        }

        .operation-title {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .operation-date {
            font-size: 12px;
            color: #6b7a8f;
        }

        /* ============================================================================
           ЗАМЕТКИ
           ============================================================================ */
        .note-item {
            background: #fef9e7;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border-left: 4px solid #ffc107;
        }

        .note-date {
            font-size: 12px;
            color: #b88b16;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .note-text {
            font-size: 14px;
        }

        /* ============================================================================
           КНОПКИ
           ============================================================================ */
        .operation-type-btn,
        .theme-toggle-btn,
        .all-ops-btn {
            flex: 0 1 auto;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            background: #f0f3f7;
            color: #6b7a8f;
            font-weight: 500;
            padding: 10px 16px;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            font-size: 14px;
            text-decoration: none;
        }

        .operation-type-btn:hover,
        .theme-toggle-btn:hover,
        .all-ops-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(26, 92, 255, 0.15);
            background: white;
            border-color: #1a5cff;
            color: #1a5cff;
        }

        .operation-type-btn i,
        .theme-toggle-btn i {
            transition: transform 0.3s ease;
            font-size: 16px;
        }

        .site-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
            transition: transform 0.2s ease;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.2;
        }

        .logo-subtitle {
            font-size: 13px;
            color: #6b7a8f;
            font-weight: 500;
        }

        /* ============================================================================
           ТЕМНАЯ ТЕМА (полностью копируем из index.php)
           ============================================================================ */
        body.dark-theme {
            background: #0C0E12;
            color: #FFFFFF;
        }

        .dark-theme {
            --bg-primary: #0C0E12;
            --bg-secondary: #15181C;
            --bg-tertiary: #1E2228;
            --border-color: #2A2F36;
            --accent-primary: #2B6ED9;
            --accent-success: #14B88B;
            --accent-danger: #E94F4F;
            --accent-warning: #F59E0B;
            --text-primary: #FFFFFF;
            --text-secondary: #9AA5B5;
            --text-tertiary: #6B7A8F;
        }

        .dark-theme .header,
        .dark-theme .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .dark-theme .card-title,
        .dark-theme .value-amount,
        .dark-theme .investment-name,
        .dark-theme .investment-amount,
        .dark-theme .operation-title {
            color: var(--text-primary);
        }

        .dark-theme .stat-badge,
        .dark-theme .investment-change,
        .dark-theme .operation-date {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
        }

        .dark-theme .order-card {
            background: var(--bg-tertiary);
        }

        .dark-theme .logo-title {
            color: var(--text-primary);
        }

        .dark-theme .logo-subtitle {
            color: var(--text-secondary);
        }

        .dark-theme .operation-type-btn,
        .dark-theme .theme-toggle-btn,
        .dark-theme .all-ops-btn {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-secondary);
        }

        .dark-theme .operation-type-btn:hover,
        .dark-theme .theme-toggle-btn:hover,
        .dark-theme .all-ops-btn:hover {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            color: white;
        }

        .dark-theme .note-item {
            background: var(--bg-tertiary);
            border-left-color: var(--accent-warning);
        }

        /* ============================================================================
           МОДАЛЬНЫЕ ОКНА (базовые стили, полный код из index.php)
           ============================================================================ */
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
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 24px 24px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7a8f;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 16px 20px 20px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-top: 1px solid #edf2f7;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: white;
            border: 1px solid #e0e6ed;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #1a5cff;
            color: white;
        }

        .btn-primary:hover {
            background: #0044cc;
        }

        .btn-secondary {
            background: #f0f3f7;
            color: #2c3e50;
        }

        .dark-theme .modal {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .dark-theme .form-group label {
            color: var(--text-primary);
        }

        .dark-theme .form-group input,
        .dark-theme .form-group select,
        .dark-theme .form-group textarea {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        /* ============================================================================
           УВЕДОМЛЕНИЯ
           ============================================================================ */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        }

        .notification {
            background: white;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            animation: notificationSlideIn 0.3s ease forwards;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            position: relative;
        }

        @keyframes notificationSlideIn {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* ============================================================================
           АДАПТИВНОСТЬ
           ============================================================================ */
        @media (max-width: 1199px) and (min-width: 769px) {
            .card { flex-basis: calc(50% - 10px) !important; }
        }

        @media (max-width: 768px) {
            body { padding: 12px; }
            .card { flex-basis: 100% !important; }
            .header { flex-direction: column; align-items: flex-start; }
            .pie { width: 120px; height: 120px; }
            .value-amount { font-size: 24px; }
        }

        @media (max-width: 480px) {
            .value-amount { font-size: 20px; }
            .operation-type-btn,
            .theme-toggle-btn,
            .all-ops-btn {
                flex: 1 1 100%;
                padding: 8px 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="<?= $current_theme === 'dark' ? 'dark-theme' : '' ?>">
    <div class="dashboard">
        <div class="notification-container" id="notificationContainer"></div>

        <!-- Шапка сайта -->
        <div class="site-header">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="logo-text">
                    <span class="logo-title">Планеро.Инвестиции</span>
                    <span class="logo-subtitle">Анализ инвестиций</span>
                </div>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="operation-type-btn" data-type="buy">
                    <i class="fas fa-arrow-down"></i> Покупка
                </button>
                <button type="button" class="operation-type-btn" data-type="sell">
                    <i class="fas fa-arrow-up"></i> Продажа
                </button>
                <button type="button" class="operation-type-btn" data-type="transfer">
                    <i class="fas fa-exchange-alt"></i> Перевод
                </button>
                <button type="button" class="operation-type-btn" data-type="deposit">
                    <i class="fas fa-plus-circle"></i> Пополнить
                </button>
                <button type="button" class="operation-type-btn" data-type="expense">
                    <i class="fas fa-receipt"></i> Расходы
                </button>
                <button id="themeToggleBtn" class="theme-toggle-btn">
                    <i class="fas <?= $current_theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
                    <span><?= $current_theme === 'dark' ? 'Светлая' : 'Темная' ?></span>
                </button>
            </div>
        </div>

        <!-- HEADER -->
        <div class="header">
            <div class="portfolio-value">
                <div style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap; align-items: center;">
                    <div style="padding: 10px 16px; border-radius: 12px;">
                        <span class="value-label">Текущая стоимость портфеля</span>
                        <div style="display: flex; align-items: baseline; gap: 20px; flex-wrap: wrap;">
                            <div>
                                <span class="value-amount" id="usdValue"><?= $totalUsdFormatted ?> $</span>
                                <br />
                                <span class="value-amount" id="rubValue"><?= $totalRubFormatted ?> ₽</span>
                            </div>
                        </div>
                    </div>

                    <!-- Блок доходности -->
                    <div style="background: <?= $profit['profit_usd'] >= 0 ? '#e8f5e9' : '#ffe6e6' ?>; padding: 10px 16px; border-radius: 12px; min-width: 200px;">
                        <div style="font-size: 12px; color: <?= $profit['profit_usd'] >= 0 ? '#2e7d32' : '#c62828' ?>; font-weight: 500; display: flex; align-items: center; gap: 4px;">
                            <i class="fas <?= $profitIcon ?>" style="font-size: 10px;"></i>
                            ДОХОДНОСТЬ 
                        </div>
                        <div style="font-weight: 600; font-size: 18px; color: <?= $profit['profit_usd'] >= 0 ? '#2e7d32' : '#c62828' ?>;">
                            <?= $profit['profit_usd'] >= 0 ? '+' : '' ?><?= $profitPercentFormatted ?>%
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 1px solid rgba(0,0,0,0.05); margin-top: 8px; padding-top: 8px;">
                            <div>
                                <div style="font-size: 10px; color: #6b7a8f;">Прибыль</div>
                                <div style="font-weight: 600; font-size: 13px; color: <?= $profit['profit_usd'] >= 0 ? '#2e7d32' : '#c62828' ?>;">
                                    <?= $profit['profit_usd'] >= 0 ? '+' : '' ?><?= $profitUsdFormatted ?> $
                                </div>
                                <div style="font-size: 10px; color: #6b7a8f;">
                                    <?= $profit['profit_rub'] >= 0 ? '+' : '' ?><?= $profitRubFormatted ?> ₽
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 10px; color: #6b7a8f;">Вложено</div>
                                <div style="font-weight: 600; font-size: 13px;"><?= $investedUsdFormatted ?> $</div>
                                <div style="font-size: 10px; color: #6b7a8f;"><?= $investedRubFormatted ?> ₽</div>
                            </div>
                        </div>
                    </div>

                    <div style="background: #e3f2fd; padding: 10px 16px; border-radius: 12px;">
                        <div style="font-size: 12px; color: #1976d2; font-weight: 500;">РУБЛИ</div>
                        <div style="font-weight: 600; margin-top: 2px;"><?= $rubInUsdFormatted ?> $</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= $rubAmountFormatted ?> ₽</div>
                    </div>

                    <div style="background: #e8f5e9; padding: 10px 16px; border-radius: 12px;">
                        <div style="font-size: 12px; color: #2e7d32; font-weight: 500;">ДОЛЛАРЫ</div>
                        <div style="font-weight: 600; margin-top: 2px;"><?= number_format($portfolio['usd_amount'] + $portfolio['usdt_amount'], 2, '.', ' ') ?> $</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= $usdAmountFormatted ?> USD</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= $usdtAmountFormatted ?> USDT</div>
                    </div>

                    <div style="background: #fff3e0; padding: 10px 16px; border-radius: 12px;">
                        <div style="font-size: 12px; color: #ed6c02; font-weight: 500;">ИНВЕСТИЦИИ</div>
                        <div style="font-weight: 600; margin-top: 2px;"><?= $investmentsValueFormatted ?> $</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= $investmentsRubFormatted ?> ₽</div>
                    </div>
                </div>
            </div>

            <!-- Карточки аналитики в header -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px; width: 100%;">
                <!-- Площадки -->
                <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-building" style="color: #4a9eff; font-size: 18px;"></i>
                        <h4 style="font-size: 14px; font-weight: 600; margin: 0;">Площадки</h4>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($platform_distribution as $index => $platform): 
                            $valueRub = $platform['total_value_usd'] * $usd_rub_rate;
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center; cursor: pointer;" 
                            onclick="openPlatformAssetsModal(<?= $platform['platform_id'] ?>, '<?= htmlspecialchars($platform['platform_name'], ENT_QUOTES) ?>')">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="width: 8px; height: 8px; border-radius: 4px; background: <?= CHART_COLORS[$index % count(CHART_COLORS)] ?>;"></div>
                                <span style="font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($platform['platform_name']) ?></span>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; text-align: right;">$<?= number_format($platform['total_value_usd'], 0) ?></span>
                            <span style="font-size: 12px; color: #6b7a8f; text-align: right;">/ <?= number_format($valueRub, 0) ?> ₽</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Сети -->
                <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-network-wired" style="color: #ff9f4a; font-size: 18px;"></i>
                        <h4 style="font-size: 14px; font-weight: 600; margin: 0;">Сети</h4>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php if (!empty($network_distribution)): 
                            $networkColors = ['#14b8a6', '#8b5cf6', '#ec4899', '#f59e0b', '#3b82f6', '#ef4444'];
                            foreach (array_slice($network_distribution, 0, 5) as $index => $network): 
                                $valueRub = $network['total_value_usd'] * $usd_rub_rate;
                                $networkIcon = getNetworkIcon($network['network']);
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center; cursor: pointer;" 
                            onclick="openNetworkAssetsModal('<?= htmlspecialchars($network['network'], ENT_QUOTES) ?>')">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="fab <?= $networkIcon ?>" style="color: <?= $networkColors[$index % count($networkColors)] ?>; width: 16px; font-size: 12px;"></i>
                                <span style="font-size: 13px;"><?= htmlspecialchars($network['network']) ?></span>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; text-align: right;">$<?= number_format($network['total_value_usd'], 0) ?></span>
                            <span style="font-size: 12px; color: #6b7a8f; text-align: right;">/ <?= number_format($valueRub, 0) ?> ₽</span>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div style="color: #6b7a8f; font-size: 13px; text-align: center;">Нет данных</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Типы платформ -->
                <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-chart-pie" style="color: #2ecc71; font-size: 18px;"></i>
                        <h4 style="font-size: 14px; font-weight: 600; margin: 0;">Типы платформ</h4>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($platform_types as $type): 
                            $valueRub = $type['value_usd'] * $usd_rub_rate;
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="width: 8px; height: 8px; border-radius: 4px; background: <?= $type['color'] ?>;"></div>
                                <span style="font-size: 13px;"><?= $type['name'] ?></span>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; text-align: right;">$<?= number_format($type['value_usd'], 0) ?></span>
                            <span style="font-size: 12px; color: #6b7a8f; text-align: right;">/ <?= number_format($valueRub, 0) ?> ₽</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- КОНТЕЙНЕР С КАРТОЧКАМИ -->
        <div class="cards-container">
            <!-- Структура портфеля -->
            <div class="card card-structure">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie"></i> Структура портфеля</h3>
                    <span class="stat-badge">По типам</span>
                </div>
                <div class="pie-chart">
                    <?php if ($portfolio_structure[0]['category'] !== 'Нет данных'): 
                        $colors = CHART_COLORS;
                        $gradient = [];
                        $current = 0;
                        foreach ($portfolio_structure as $index => $item) {
                            $gradient[] = $colors[$index % count($colors)] . ' ' . $current . '% ' . ($current + $item['percentage']) . '%';
                            $current += $item['percentage'];
                        }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($portfolio_structure as $index => $item): ?>
                        <div class="legend-item">
                            <span class="legend-color" style="background: <?= $colors[$index % count($colors)] ?>;"></span>
                            <span style="flex: 1;"><?= htmlspecialchars($item['category']) ?></span>
                            <span class="legend-value">
                                <div><?= $item['percentage'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($item['value'], 0, '.', ' ') ?></div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="pie" style="background: conic-gradient(#95a5a6 0% 100%);"></div>
                    <div class="chart-legend">
                        <div class="legend-item">
                            <span class="legend-color" style="background: #95a5a6;"></span>
                            <span>Нет данных</span>
                            <span class="legend-value">0%</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Крипто -->
            <?php if ($crypto_stats['total_usdt_bought'] > 1): ?>
            <div class="card card-crypto">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #ff9f4a;"></i> Крипто</h3>
                    <span class="stat-badge">По активам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    $segments = [];
                    if ($crypto_stats['btc_cost'] > 0) $segments[] = ['name' => 'BTC', 'value' => $crypto_stats['btc_cost'], 'percent' => $crypto_stats['btc_percent'], 'color' => '#f7931a', 'icon' => 'fab fa-bitcoin'];
                    if ($crypto_stats['eth_cost'] > 0) $segments[] = ['name' => 'ETH', 'value' => $crypto_stats['eth_cost'], 'percent' => $crypto_stats['eth_percent'], 'color' => '#627eea', 'icon' => 'fab fa-ethereum'];
                    if ($crypto_stats['altcoins_cost'] > 0) $segments[] = ['name' => 'Альткоины', 'value' => $crypto_stats['altcoins_cost'], 'percent' => $crypto_stats['altcoins_percent'], 'color' => '#14b8a6', 'icon' => 'fas fa-chart-line'];
                    if ($crypto_stats['stablecoins_left'] > 0) $segments[] = ['name' => 'Стейблкоины', 'value' => $crypto_stats['stablecoins_left'], 'percent' => $crypto_stats['stablecoins_percent'], 'color' => '#a5a5a5', 'icon' => 'fas fa-coins'];
                    
                    $gradient = [];
                    $current = 0;
                    foreach ($segments as $segment) {
                        $gradient[] = $segment['color'] . ' ' . $current . '% ' . ($current + $segment['percent']) . '%';
                        $current += $segment['percent'];
                    }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($segments as $segment): 
                            $isClickable = ($segment['name'] == 'Альткоины' || $segment['name'] == 'Стейблкоины');
                            $onclick = $isClickable ? "onclick=\"openCryptoTypeModal('" . ($segment['name'] == 'Альткоины' ? 'altcoins' : 'stablecoins') . "', '{$segment['name']}')\"" : '';
                        ?>
                        <div class="legend-item" <?= $onclick ?> style="<?= $isClickable ? 'cursor: pointer;' : '' ?>">
                            <span class="legend-color" style="background: <?= $segment['color'] ?>;"></span>
                            <span style="flex: 1; display: flex; align-items: center; gap: 6px;">
                                <i class="<?= $segment['icon'] ?>" style="color: <?= $segment['color'] ?>; width: 16px;"></i>
                                <?= $segment['name'] ?>
                            </span>
                            <span class="legend-value">
                                <div><?= $segment['percent'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($segment['value'], 0, '.', ' ') ?></div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <div class="legend-item" style="border-top: 1px solid #edf2f7; margin-top: 8px; padding-top: 8px;">
                            <span style="font-weight: 600;">Всего</span>
                            <span class="legend-value" style="font-weight: 600;">$<?= number_format($crypto_stats['total_usdt_bought'], 0, '.', ' ') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Фондовый EN -->
            <?php if (!empty($en_sectors)): ?>
            <div class="card card-en-stocks">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #4a9eff;"></i> Фондовый (EN)</h3>
                    <span class="stat-badge">По секторам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    $colors = CHART_COLORS;
                    $gradient = [];
                    $current = 0;
                    foreach ($en_sectors as $sector) {
                        $gradient[] = $colors[$current % count($colors)] . ' ' . $current . '% ' . ($current + $sector['percentage']) . '%';
                        $current += $sector['percentage'];
                    }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($en_sectors as $index => $sector): ?>
                        <div class="legend-item" style="cursor: pointer;" 
                            onclick="openSectorAssetsModal('<?= htmlspecialchars($sector['original_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($sector['sector_name'], ENT_QUOTES) ?>')">
                            <span class="legend-color" style="background: <?= $colors[$index % count($colors)] ?>;"></span>
                            <span style="flex: 1;"><?= htmlspecialchars($sector['sector_name']) ?></span>
                            <span class="legend-value">
                                <div><?= $sector['percentage'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($sector['value_usd'], 0, '.', ' ') ?></div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <div class="legend-item" style="border-top: 1px solid #edf2f7; margin-top: 8px; padding-top: 8px;">
                            <span style="font-weight: 600;">Всего</span>
                            <span class="legend-value" style="font-weight: 600;">$<?= number_format(array_sum(array_column($en_sectors, 'value_usd')), 0, '.', ' ') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Мои активы -->
            <div class="card card-investments">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-coins"></i> Мои активы</h3>
                    <span class="stat-badge"><?= count($assets) ?> активов</span>
                </div>
                <table class="investments-table">
                    <tbody>
                        <?php foreach ($assets as $asset): 
                            $avgPriceDisplay = '—';
                            $avgCurrency = '';
                            if ($asset['avg_price'] > 0) {
                                if ($asset['symbol'] == 'USDT' || $asset['symbol'] == 'USDC' || 
                                    $asset['symbol'] == 'USD' || $asset['symbol'] == 'RUB' || $asset['symbol'] == 'EUR') {
                                    $avgPriceDisplay = number_format($asset['avg_price'], 2, '.', ' ');
                                } elseif ($asset['type'] == 'crypto') {
                                    $formatted = number_format($asset['avg_price'], 4, '.', ' ');
                                    $avgPriceDisplay = rtrim(rtrim($formatted, '0'), '.');
                                } else {
                                    $avgPriceDisplay = number_format($asset['avg_price'], 2, '.', ' ');
                                    $avgCurrency = $asset['currency_code'];
                                }
                            }
                            
                            $quantityFormatted = $asset['symbol'] == 'RUB' ? number_format($asset['total_quantity'], 0, '.', ' ') :
                                ($asset['type'] == 'crypto' ? 
                                    (floor($asset['total_quantity']) == $asset['total_quantity'] ? 
                                        number_format($asset['total_quantity'], 0, '.', ' ') : 
                                        rtrim(rtrim(number_format($asset['total_quantity'], 8, '.', ' '), '0'), '.')) :
                                    number_format($asset['total_quantity'], 0, '.', ' ') . ($asset['type'] == 'stock' ? ' шт' : ''));
                        ?>
                        <tr onclick='showAssetHistory(<?= json_encode([
                            'symbol' => $asset['symbol'],
                            'history' => [] // Будет заполнено JS
                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="cursor: pointer;">
                            <td class="investment-icon-cell">
                                <div class="investment-icon">
                                    <?php
                                    $symbols = ['RUB' => '₽', 'USD' => '$', 'EUR' => '€', 'BTC' => '₿', 'ETH' => 'Ξ', 'USDT' => '₮'];
                                    echo $symbols[$asset['symbol']] ?? substr($asset['symbol'], 0, 2);
                                    ?>
                                </div>
                            </td>
                            <td class="investment-name-cell">
                                <span class="investment-name"><?= htmlspecialchars($asset['symbol']) ?></span>
                                <?php if (strpos($asset['platform_ids'] ?? '', ',') !== false): ?>
                                    <span style="font-size: 10px; color: #6b7a8f; display: block;">на нескольких площадках</span>
                                <?php endif; ?>
                            </td>
                            <td class="investment-amount-cell">
                                <span class="investment-amount"><?= $quantityFormatted ?></span>
                            </td>
                            <td class="investment-change-cell">
                                <span class="investment-change"><?= $avgPriceDisplay ?> <?= $avgCurrency ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Последние операции -->
            <div class="card card-operations" id="operationsContainer">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history"></i> Последние операции</h3>
                    <a href="index.php?page=operations" class="all-ops-btn">
                        <i class="fas fa-list-ul"></i> Все операции
                    </a>
                </div>
                <div id="operationsList">
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div id="paginationControls"></div>
            </div>

            <!-- Лимитные ордера -->
            <div class="card card-orders">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clock"></i> Лимитные ордера</h3>
                    <span class="stat-badge"><?= count($orders) ?></span>
                </div>
                <div style="margin-bottom: 15px; text-align: center;">
                    <button class="add-order-btn" onclick="openLimitOrderModal()">
                        <i class="fas fa-plus-circle"></i> Создать новый ордер
                    </button>
                </div>
                <div id="limitOrdersList">
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                        <div class="order-card" id="order-<?= $order['id'] ?>" style="border-left-color: <?= $order['operation_type'] == 'buy' ? '#00a86b' : '#e53e3e' ?>;">
                            <div class="order-exchange">
                                <i class="fas fa-<?= $order['platform_type'] == 'exchange' ? 'chart-line' : 'building' ?>"></i>
                                <?= strtoupper(htmlspecialchars($order['platform_name'])) ?>
                            </div>
                            <div class="order-details">
                                <span class="order-action">
                                    <i class="fas fa-<?= $order['operation_type'] == 'buy' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                    <?= $order['operation_type'] == 'buy' ? 'Покупка' : 'Продажа' ?> <?= htmlspecialchars($order['symbol']) ?>
                                </span>
                                <span class="order-price"><?= number_format($order['limit_price'], 2) ?> <?= $order['price_currency'] ?></span>
                            </div>
                            <div class="order-footer">
                                <span><i class="far fa-clock"></i> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></span>
                                <span><?= number_format($order['quantity'], 4) ?> шт</span>
                            </div>
                            <div style="display: flex; gap: 8px; margin-top: 12px; justify-content: flex-end;">
                                <button class="quick-platform-btn" onclick="showExecuteConfirmation(<?= $order['id'] ?>)" 
                                        style="background: #00a86b; color: white; border: none; min-width: 100px;">
                                    <i class="fas fa-check-circle"></i> Исполнить
                                </button>
                                <button class="quick-platform-btn" onclick="showCancelConfirmation(<?= $order['id'] ?>)" 
                                        style="background: #e53e3e; color: white; border: none; min-width: 100px;">
                                    <i class="fas fa-times-circle"></i> Отменить
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="order-empty">
                            <i class="fas fa-clock"></i>
                            <p>Нет активных лимитных ордеров</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Заметки -->
            <div class="card card-notes">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sticky-note"></i> Заметки</h3>
                    <button class="add-order-btn" onclick="openAddNoteModal()" style="padding: 4px 12px;">
                        <i class="fas fa-plus-circle"></i> Добавить
                    </button>
                </div>
                <div id="notesList">
                    <?php if (empty($notes)): ?>
                    <div class="order-empty">
                        <i class="fas fa-sticky-note"></i>
                        <p>Нет заметок</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                        <div class="note-item">
                            <div class="note-date">
                                <i class="far fa-calendar-alt"></i> <?= date('d.m.Y', strtotime($note['created_at'])) ?>
                            </div>
                            <?php if ($note['title']): ?>
                            <div class="note-title" style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($note['title']) ?></div>
                            <?php endif; ?>
                            <div class="note-text"><?= htmlspecialchars($note['content']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Подключение JavaScript -->
    <script>
        // Передаем данные из PHP в JS
        const platformsData = <?= json_encode($platforms) ?>;
        const assetsData = <?= json_encode($assets_list) ?>;
        const allCurrencies = <?= json_encode($all_currencies) ?>;
        const fiatCurrencies = <?= json_encode($fiat_currencies) ?>;
        const networksData = <?= json_encode($networks) ?>;
        const cryptoTypeAssetsData = <?= json_encode($crypto_by_types) ?>;
        const sectorAssetsData = <?= json_encode($sector_assets) ?>;
        const networkAssetsData = <?= json_encode($network_assets) ?>;
        const usdRubRate = <?= $usd_rub_rate ?>;
    </script>
    <script src="/public/js/app.js"></script>
</body>
</html>