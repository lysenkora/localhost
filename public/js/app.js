// ============================================================================
// ОСНОВНОЙ JS ФАЙЛ ДЛЯ ДАШБОРДА
// ============================================================================

// Глобальные переменные
let currentOperationsPage = 1;
let allFilteredOperations = [];

// ============================================================================
// ФУНКЦИИ УВЕДОМЛЕНИЙ
// ============================================================================

function showNotification(type, title, message, duration = 5000) {
    let container = document.getElementById('notificationContainer');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        container.id = 'notificationContainer';
        document.body.appendChild(container);
    }
    
    const notificationId = 'notification-' + Date.now();
    let iconClass = '';
    switch(type) {
        case 'success': iconClass = 'fas fa-check-circle'; break;
        case 'warning': iconClass = 'fas fa-exclamation-triangle'; break;
        case 'error': iconClass = 'fas fa-times-circle'; break;
        default: iconClass = 'fas fa-info-circle';
    }
    
    const notification = document.createElement('div');
    notification.id = notificationId;
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-icon"><i class="${iconClass}"></i></div>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close" onclick="closeNotification('${notificationId}')">×</button>
        <div class="notification-progress">
            <div class="notification-progress-bar" style="width: 100%;"></div>
        </div>
    `;
    
    container.appendChild(notification);
    
    const progressBar = notification.querySelector('.notification-progress-bar');
    const startTime = Date.now();
    
    function updateProgress() {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, duration - elapsed);
        const width = (remaining / duration) * 100;
        if (progressBar) progressBar.style.width = width + '%';
        if (remaining > 0) {
            requestAnimationFrame(updateProgress);
        } else {
            closeNotification(notificationId);
        }
    }
    
    requestAnimationFrame(updateProgress);
    
    setTimeout(() => closeNotification(notificationId), duration);
}

function closeNotification(notificationId) {
    const notification = document.getElementById(notificationId);
    if (!notification) return;
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(100%)';
    setTimeout(() => notification.remove(), 300);
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ОПЕРАЦИЙ
// ============================================================================

async function loadOperations(page) {
    currentOperationsPage = page;
    const operationsList = document.getElementById('operationsList');
    if (operationsList) operationsList.style.opacity = '0.5';
    
    try {
        const response = await fetch(`index.php?page=get_operations&page=${page}&per_page=5`);
        const data = await response.json();
        
        if (data.success) {
            allFilteredOperations = filterOperations(data.operations);
            const totalPages = Math.ceil(allFilteredOperations.length / 5);
            updateOperationsList(allFilteredOperations, {
                current_page: page,
                total_pages: totalPages,
                total: allFilteredOperations.length,
                per_page: 5,
                from: (page - 1) * 5 + 1,
                to: Math.min(page * 5, allFilteredOperations.length),
                has_previous: page > 1,
                has_next: page < totalPages
            });
        }
    } catch (error) {
        console.error('Error loading operations:', error);
    } finally {
        if (operationsList) operationsList.style.opacity = '1';
    }
}

function filterOperations(operations) {
    const groupedOps = {};
    operations.forEach(op => {
        if (!groupedOps[op.operation_id]) groupedOps[op.operation_id] = [];
        groupedOps[op.operation_id].push(op);
    });
    
    const filteredOps = [];
    Object.values(groupedOps).forEach(group => {
        group.sort((a, b) => {
            if (a.operation_type.includes('asset')) return -1;
            if (b.operation_type.includes('asset')) return 1;
            return 0;
        });
        
        const mainOp = group[0];
        let shouldShow = true;
        
        if (mainOp.operation_type == 'buy_payment' && !group[1]) shouldShow = false;
        if (mainOp.operation_type == 'sell_income' && !group[1]) shouldShow = false;
        if (mainOp.operation_type == 'transfer_in' || mainOp.operation_type == 'transfer_out') shouldShow = true;
        
        if (shouldShow) filteredOps.push(mainOp);
    });
    
    filteredOps.sort((a, b) => new Date(b.date) - new Date(a.date));
    return filteredOps;
}

function updateOperationsList(operations, pagination) {
    const container = document.getElementById('operationsList');
    if (!container) return;
    
    const startIndex = (pagination.current_page - 1) * pagination.per_page;
    const pageOperations = operations.slice(startIndex, startIndex + pagination.per_page);
    
    let html = '';
    pageOperations.forEach(op => {
        let iconClass = '';
        let iconType = '';
        let displayText = '';
        let detailsLine = '';
        
        if (op.direction == 'in' || op.operation_type == 'buy_asset' || op.operation_type == 'sell_income' || op.operation_type == 'deposit' || op.operation_type == 'transfer_in') {
            iconClass = 'icon-buy';
            iconType = 'fa-arrow-down';
        } else {
            iconClass = 'icon-sell';
            iconType = 'fa-arrow-up';
        }
        
        if (op.operation_type == 'buy_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'buy_payment');
            
            const assetAmount = formatAmount(op.amount, op.currency);
            const price = formatAmount(op.price, op.price_currency);
            
            if (secondaryOp) {
                const moneyAmount = formatAmount(secondaryOp.amount_out, secondaryOp.currency);
                displayText = `Куплено ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(op.date)} · по ${price} ${op.price_currency} · ${op.platform} ← ${secondaryOp.platform}`;
            } else {
                const totalCost = formatAmount(op.amount * op.price, op.price_currency);
                displayText = `Куплено ${assetAmount} ${op.currency} за ${totalCost} ${op.price_currency}`;
                detailsLine = `${formatDate(op.date)} · по ${price} ${op.price_currency} · ${op.platform}`;
            }
        }
        else if (op.operation_type == 'sell_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'sell_income');
            
            const assetAmount = formatAmount(op.amount_out, op.currency);
            const price = formatAmount(op.price, op.price_currency);
            
            if (secondaryOp) {
                const moneyAmount = formatAmount(secondaryOp.amount, secondaryOp.currency);
                displayText = `Продано ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(op.date)} · по ${price} ${op.price_currency} · ${op.platform}`;
            } else {
                const totalIncome = formatAmount(op.amount_out * op.price, op.price_currency);
                displayText = `Продано ${assetAmount} ${op.currency} за ${totalIncome} ${op.price_currency}`;
                detailsLine = `${formatDate(op.date)} · по ${price} ${op.price_currency} · ${op.platform}`;
            }
        }
        else if (op.operation_type == 'deposit') {
            displayText = `Пополнение: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(op.date)} · ${op.platform}`;
        }
        else if (op.operation_type == 'transfer_in') {
            displayText = `Входящий перевод: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(op.date)} · ${op.platform}`;
            if (op.commission && op.commission > 0) {
                detailsLine += ` · комиссия ${formatAmount(op.commission, op.commission_currency || op.currency)} ${op.commission_currency || op.currency}`;
            }
            iconClass = 'icon-convert';
            iconType = 'fa-exchange-alt';
        }
        else if (op.operation_type == 'transfer_out') {
            displayText = `Исходящий перевод: ${formatAmount(op.amount_out, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(op.date)} · ${op.platform}`;
            if (op.commission && op.commission > 0) {
                detailsLine += ` · комиссия ${formatAmount(op.commission, op.commission_currency || op.currency)} ${op.commission_currency || op.currency}`;
            }
            iconClass = 'icon-convert';
            iconType = 'fa-exchange-alt';
        }
        
        if (displayText) {
            html += `
                <div class="operation-item">
                    <div class="operation-icon ${iconClass}">
                        <i class="fas ${iconType}"></i>
                    </div>
                    <div class="operation-details">
                        <div class="operation-title">${displayText}</div>
                        <div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">${detailsLine}</div>
                    </div>
                </div>
            `;
        }
    });
    
    if (html === '') {
        html = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет операций для отображения</div>';
    }
    
    container.innerHTML = html;
    
    const paginationContainer = document.getElementById('paginationControls');
    if (paginationContainer && pagination.total > pagination.per_page) {
        paginationContainer.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #edf2f7;">
                <div style="display: flex; gap: 5px;">
                    ${pagination.has_previous ? `<button onclick="loadOperations(${pagination.current_page - 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px;"><i class="fas fa-chevron-left"></i> Назад</button>` : ''}
                    ${pagination.has_next ? `<button onclick="loadOperations(${pagination.current_page + 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px;">Вперед <i class="fas fa-chevron-right"></i></button>` : ''}
                </div>
                <div style="color: #6b7a8f; font-size: 13px;">Страница ${pagination.current_page} из ${pagination.total_pages}</div>
            </div>
        `;
    }
}

function formatAmount(amount, currency) {
    if (!amount && amount !== 0) return '0';
    let num = parseFloat(amount);
    if (isNaN(num)) return '0';
    
    const cryptoList = ['USDT', 'USDC', 'BTC', 'ETH', 'SOL', 'BNB', 'LINK', 'STX', 'ZK', 'FIL', 'ONDO', 'RENDER', 'GRT', 'TWT', 'APE', 'CELO', 'GOAT', 'TRUMP', 'IMX', 'POL', 'ARKM'];
    
    if (cryptoList.includes(currency)) {
        let decimals = (currency === 'BTC' || currency === 'ETH') ? 6 : 4;
        let rounded = num.toFixed(decimals);
        rounded = rounded.replace(/\.?0+$/, '');
        let parts = rounded.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return parts[0] + (parts[1] ? '.' + parts[1] : '');
    }
    
    let rounded = num.toFixed(2);
    rounded = rounded.replace(/\.?0+$/, '');
    let parts = rounded.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return parts[0] + (parts[1] ? '.' + parts[1] : '');
}

function formatDate(dateString) {
    if (typeof dateString === 'string' && dateString.match(/^\d{4}-\d{2}-\d{2}/)) {
        const parts = dateString.split('T')[0].split('-');
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${day}.${month}.${year}`;
}

// ============================================================================
// ПЕРЕКЛЮЧЕНИЕ ТЕМЫ
// ============================================================================

document.getElementById('themeToggleBtn')?.addEventListener('click', function() {
    const isDark = document.body.classList.contains('dark-theme');
    const newTheme = isDark ? 'light' : 'dark';
    const icon = this.querySelector('i');
    const text = this.querySelector('span');
    
    this.style.opacity = '0.7';
    this.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=save_theme&theme=' + newTheme
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (newTheme === 'dark') {
                document.body.classList.add('dark-theme');
                icon.className = 'fas fa-sun';
                text.textContent = 'Светлая';
            } else {
                document.body.classList.remove('dark-theme');
                icon.className = 'fas fa-moon';
                text.textContent = 'Темная';
            }
        }
    })
    .catch(error => console.error('Error saving theme:', error))
    .finally(() => {
        this.style.opacity = '1';
        this.disabled = false;
    });
});

// ============================================================================
// МОДАЛЬНЫЕ ОКНА ДЛЯ АКТИВОВ
// ============================================================================

function openPlatformAssetsModal(platformId, platformName) {
    const modal = document.getElementById('platformAssetsModal');
    if (!modal) return;
    
    const titleSpan = document.getElementById('platformAssetsName');
    const body = document.getElementById('platformAssetsBody');
    if (titleSpan) titleSpan.textContent = platformName;
    if (body) {
        body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    }
    modal.classList.add('active');
    
    const platformData = platformAssetsData ? platformAssetsData[platformId] : null;
    if (!platformData || !platformData.assets || platformData.assets.length === 0) {
        if (body) {
            body.innerHTML = `
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3;"></i>
                    <p style="color: #6b7a8f;">На площадке "${platformName}" нет активов</p>
                </div>
            `;
        }
        return;
    }
    
    const assets = [...platformData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    let totalValueUsd = assets.reduce((sum, a) => sum + (parseFloat(a.value_usd) || 0), 0);
    const totalValueRub = totalValueUsd * usdRubRate;
    
    let html = `
        <table class="platform-assets-table">
            <thead><tr><th>Актив</th><th style="text-align: right;">Количество</th><th style="text-align: right;">Средняя цена</th><th style="text-align: right;">Стоимость</th></tr></thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        let quantityFormatted = '';
        if (asset.asset_type === 'crypto') {
            if (Math.floor(quantityNum) === quantityNum) {
                quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
            } else {
                let str = quantityNum.toFixed(6).replace(/\.?0+$/, '');
                let parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            }
        } else {
            let str = quantityNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        }
        
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        const rubStr = Math.round(valueUsdNum * usdRubRate).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </td>
                <td style="text-align: right; font-family: monospace;">${quantityFormatted}</td>
                <td style="text-align: right;">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td style="text-align: right; font-weight: 500; color: #ff9f4a;">
                    $${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>
                </td>
            </tr>
        `;
    });
    
    const totalUsdFormatted = totalValueUsd.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalRubFormatted = Math.round(totalValueRub).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    html += `
            </tbody>
        </table>
        <div class="platform-assets-summary">
            <div class="platform-assets-summary-row"><span>Всего активов:</span><span>${assets.length}</span></div>
            <div class="platform-assets-summary-row"><span>Общая стоимость:</span><span class="platform-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubFormatted} ₽</span></span></div>
        </div>
    `;
    
    if (body) body.innerHTML = html;
}

function openNetworkAssetsModal(networkName) {
    const modal = document.getElementById('networkAssetsModal');
    if (!modal) return;
    
    const titleSpan = document.getElementById('networkAssetsName');
    const body = document.getElementById('networkAssetsBody');
    if (titleSpan) titleSpan.textContent = networkName;
    if (body) {
        body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    }
    modal.classList.add('active');
    
    const networkData = networkAssetsData ? networkAssetsData[networkName] : null;
    if (!networkData || !networkData.assets || networkData.assets.length === 0) {
        if (body) {
            body.innerHTML = `<div style="text-align: center; padding: 40px;"><i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3;"></i><p style="color: #6b7a8f;">В сети "${networkName}" нет активов</p></div>`;
        }
        return;
    }
    
    const assets = [...networkData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    let totalValueUsd = networkData.total_value_usd;
    const totalValueRub = totalValueUsd * usdRubRate;
    
    let html = `
        <table class="network-assets-table">
            <thead><tr><th>Актив</th><th style="text-align: right;">Количество</th><th style="text-align: right;">Средняя цена</th><th style="text-align: right;">Стоимость</th></tr></thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        let quantityFormatted = '';
        if (Math.floor(quantityNum) === quantityNum) {
            quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
        } else {
            let str = quantityNum.toFixed(6).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        }
        
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        const rubStr = Math.round(valueUsdNum * usdRubRate).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </td>
                <td style="text-align: right; font-family: monospace;">${quantityFormatted}</td>
                <td style="text-align: right;">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td style="text-align: right; font-weight: 500; color: #ff9f4a;">
                    $${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>
                </td>
            </tr>
        `;
    });
    
    const totalUsdFormatted = totalValueUsd.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalRubFormatted = Math.round(totalValueRub).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    html += `
            </tbody>
        </table>
        <div class="network-assets-summary">
            <div class="network-assets-summary-row"><span>Всего активов:</span><span>${assets.length}</span></div>
            <div class="network-assets-summary-row"><span>Общая стоимость в сети ${networkName}:</span><span class="network-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px;">${totalRubFormatted} ₽</span></span></div>
        </div>
    `;
    
    if (body) body.innerHTML = html;
}

function openSectorAssetsModal(sectorName, displayName) {
    const modal = document.getElementById('sectorAssetsModal');
    if (!modal) return;
    
    const titleSpan = document.getElementById('sectorAssetsName');
    const body = document.getElementById('sectorAssetsBody');
    if (titleSpan) titleSpan.textContent = displayName;
    if (body) {
        body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    }
    modal.classList.add('active');
    
    const sectorData = sectorAssetsData ? sectorAssetsData[sectorName] : null;
    if (!sectorData || !sectorData.assets || sectorData.assets.length === 0) {
        if (body) {
            body.innerHTML = `<div style="text-align: center; padding: 40px;"><i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3;"></i><p style="color: #6b7a8f;">В секторе "${displayName}" нет активов</p></div>`;
        }
        return;
    }
    
    const assets = [...sectorData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    let totalValueUsd = sectorData.total_value_usd;
    const totalValueRub = totalValueUsd * usdRubRate;
    
    let html = `
        <table class="sector-assets-table">
            <thead><tr><th>Актив</th><th style="text-align: right;">Количество</th><th style="text-align: right;">Средняя цена</th><th style="text-align: right;">Стоимость</th></tr></thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        let quantityFormatted = '';
        if (asset.asset_type === 'crypto') {
            if (Math.floor(quantityNum) === quantityNum) {
                quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
            } else {
                let str = quantityNum.toFixed(6).replace(/\.?0+$/, '');
                let parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            }
        } else {
            let str = quantityNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        }
        
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        const rubStr = Math.round(valueUsdNum * usdRubRate).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </td>
                <td style="text-align: right; font-family: monospace;">${quantityFormatted}</td>
                <td style="text-align: right;">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td style="text-align: right; font-weight: 500; color: #4a9eff;">
                    $${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>
                </td>
            </tr>
        `;
    });
    
    const totalUsdFormatted = totalValueUsd.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalRubFormatted = Math.round(totalValueRub).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    html += `
            </tbody>
        </table>
        <div class="sector-assets-summary">
            <div class="sector-assets-summary-row"><span>Всего активов:</span><span>${assets.length}</span></div>
            <div class="sector-assets-summary-row"><span>Общая стоимость в секторе ${displayName}:</span><span class="sector-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px;">${totalRubFormatted} ₽</span></span></div>
        </div>
    `;
    
    if (body) body.innerHTML = html;
}

function openCryptoTypeModal(type, displayName) {
    const modal = document.getElementById('cryptoTypeModal');
    if (!modal) return;
    
    const titleSpan = document.getElementById('cryptoTypeName');
    const body = document.getElementById('cryptoTypeBody');
    if (titleSpan) titleSpan.textContent = displayName;
    if (body) {
        body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    }
    modal.classList.add('active');
    
    const typeData = cryptoTypeAssetsData ? cryptoTypeAssetsData[type] : null;
    if (!typeData || !typeData.assets || typeData.assets.length === 0) {
        if (body) {
            body.innerHTML = `<div style="text-align: center; padding: 40px;"><i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3;"></i><p style="color: #6b7a8f;">В категории "${displayName}" нет активов</p></div>`;
        }
        return;
    }
    
    const assets = [...typeData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    let totalValueUsd = typeData.total_value_usd;
    const totalValueRub = totalValueUsd * usdRubRate;
    
    let html = `
        <table class="crypto-type-table">
            <thead><tr><th>Актив</th><th style="text-align: right;">Количество</th><th style="text-align: right;">Средняя цена</th><th style="text-align: right;">Стоимость</th> </thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        let quantityFormatted = '';
        if (Math.floor(quantityNum) === quantityNum) {
            quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
        } else {
            let str = quantityNum.toFixed(6).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        }
        
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        const rubStr = Math.round(valueUsdNum * usdRubRate).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </
                <td style="text-align: right; font-family: monospace;">${quantityFormatted}</
                <td style="text-align: right;">${avgPriceFormatted} ${avgPriceCurrency}</
                <td style="text-align: right; font-weight: 500; color: #ff9f4a;">
                    $${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>
                </
            </
        `;
    });
    
    const totalUsdFormatted = totalValueUsd.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalRubFormatted = Math.round(totalValueRub).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    html += `
            </tbody>
         </table>
        <div class="crypto-type-summary">
            <div class="crypto-type-summary-row"><span>Всего активов:</span><span>${assets.length}</span></div>
            <div class="crypto-type-summary-row"><span>Общая стоимость в категории ${displayName}:</span><span class="crypto-type-total">$${totalUsdFormatted}<br><span style="font-size: 12px;">${totalRubFormatted} ₽</span></span></div>
        </div>
    `;
    
    if (body) body.innerHTML = html;
}

function closePlatformAssetsModal() {
    document.getElementById('platformAssetsModal')?.classList.remove('active');
}

function closeNetworkAssetsModal() {
    document.getElementById('networkAssetsModal')?.classList.remove('active');
}

function closeSectorAssetsModal() {
    document.getElementById('sectorAssetsModal')?.classList.remove('active');
}

function closeCryptoTypeModal() {
    document.getElementById('cryptoTypeModal')?.classList.remove('active');
}

function getAssetIcon(symbol) {
    const icons = {
        'BTC': { icon: 'fab fa-bitcoin', color: '#f7931a' },
        'ETH': { icon: 'fab fa-ethereum', color: '#627eea' },
        'USDT': { icon: 'fas fa-coins', color: '#26a17b' },
        'SOL': { icon: 'fas fa-sun', color: '#14f195' },
        'BNB': { icon: 'fas fa-chart-line', color: '#f3ba2f' },
        'RUB': { icon: 'fas fa-ruble-sign', color: '#1a5cff' },
        'USD': { icon: 'fas fa-dollar-sign', color: '#00a86b' },
        'EUR': { icon: 'fas fa-euro-sign', color: '#2ecc71' },
    };
    return icons[symbol] || { icon: 'fas fa-chart-line', color: '#6b7a8f' };
}

// ============================================================================
// ЛИМИТНЫЕ ОРДЕРА
// ============================================================================

function openLimitOrderModal() {
    const modal = document.getElementById('limitOrderModal');
    if (modal) modal.classList.add('active');
}

function closeLimitOrderModal() {
    const modal = document.getElementById('limitOrderModal');
    if (modal) modal.classList.remove('active');
}

function showExecuteConfirmation(orderId) {
    const modal = document.getElementById('executeOrderModal');
    if (modal) modal.classList.add('active');
}

function showCancelConfirmation(orderId) {
    const modal = document.getElementById('cancelOrderModal');
    if (modal) modal.classList.add('active');
}

// ============================================================================
// ЗАМЕТКИ
// ============================================================================

function openAddNoteModal() {
    const modal = document.getElementById('noteModal');
    if (modal) modal.classList.add('active');
}

function closeNoteModal() {
    const modal = document.getElementById('noteModal');
    if (modal) modal.classList.remove('active');
}

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Загружаем операции
    loadOperations(1);
    
    // Добавляем обработчики для кнопок операций
    document.querySelectorAll('.operation-type-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.dataset.type;
            if (type === 'deposit') openDepositModal();
            else if (type === 'buy') openBuyModal();
            else if (type === 'sell') openSellModal();
            else if (type === 'transfer') openTransferModal();
            else if (type === 'expense') openExpenseModal();
        });
    });
    
    // Добавляем обработчики для закрытия модальных окон
    const modals = ['platformAssetsModal', 'networkAssetsModal', 'sectorAssetsModal', 'cryptoTypeModal', 
                    'limitOrderModal', 'executeOrderModal', 'cancelOrderModal', 'noteModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    const closeFn = {
                        'platformAssetsModal': closePlatformAssetsModal,
                        'networkAssetsModal': closeNetworkAssetsModal,
                        'sectorAssetsModal': closeSectorAssetsModal,
                        'cryptoTypeModal': closeCryptoTypeModal,
                        'limitOrderModal': closeLimitOrderModal,
                        'executeOrderModal': () => modal.classList.remove('active'),
                        'cancelOrderModal': () => modal.classList.remove('active'),
                        'noteModal': closeNoteModal
                    }[modalId];
                    if (closeFn) closeFn();
                }
            });
        }
    });
    
    // Закрытие по ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && modal.classList.contains('active')) {
                    modal.classList.remove('active');
                }
            });
        }
    });
});

// Заглушки для модальных окон (будут реализованы позже)
function openDepositModal() { console.log('openDepositModal'); }
function openBuyModal() { console.log('openBuyModal'); }
function openSellModal() { console.log('openSellModal'); }
function openTransferModal() { console.log('openTransferModal'); }
function openExpenseModal() { console.log('openExpenseModal'); }
function showAssetHistory(data) { console.log('showAssetHistory', data); }