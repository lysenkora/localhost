<div class="header">
    <div class="portfolio-value">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <span class="value-label">Текущая стоимость портфеля</span>
                <div style="display: flex; align-items: baseline; gap: 20px; flex-wrap: wrap;">
                    <div>
                        <span class="value-amount" id="usdValue"><?= Formatter::currency($total_usd, 'USD') ?></span>
                        <br />
                        <span class="value-amount" id="rubValue"><?= Formatter::currency($total_rub, 'RUB', 0) ?></span>
                    </div>
                </div>
            </div>
            
            <div style="background: <?= $profit['is_positive'] ? '#e8f5e9' : '#ffe6e6' ?>; padding: 10px 16px; border-radius: 12px; min-width: 200px;">
                <div style="font-size: 12px; color: <?= $profit['is_positive'] ? '#2e7d32' : '#c62828' ?>; font-weight: 500; display: flex; align-items: center; gap: 4px;">
                    <i class="fas <?= $profit['is_positive'] ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                    ДОХОДНОСТЬ
                </div>
                <div style="font-weight: 600; font-size: 18px; color: <?= $profit['is_positive'] ? '#2e7d32' : '#c62828' ?>;">
                    <?= $profit['is_positive'] ? '+' : '' ?><?= Formatter::number($profit['profit_percent'], 1) ?>%
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.05);">
                    <div>
                        <div style="font-size: 10px; color: #6b7a8f;">Прибыль</div>
                        <div style="font-weight: 600; font-size: 13px; color: <?= $profit['is_positive'] ? '#2e7d32' : '#c62828' ?>;">
                            <?= $profit['is_positive'] ? '+' : '' ?><?= Formatter::currency($profit['profit_usd'], 'USD') ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 10px; color: #6b7a8f;">Вложено</div>
                        <div style="font-weight: 600; font-size: 13px;"><?= Formatter::currency($profit['invested_usd'], 'USD') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>