// ============================================================================
// ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ
// ============================================================================

let currentOperationsPage = 1;
let allFilteredOperations = [];
let currentModalContext = { source: 'default', mode: null, subMode: null };
let selectedTransferAsset = { id: null, symbol: '' };
let selectedFromPlatform = { id: null, name: '' };
let selectedToPlatform = { id: null, name: '' };
let selectedCommissionCurrency = { code: '' };
let selectedTradePlatform = { id: null, name: '' };
let selectedTradeFromPlatform = { id: null, name: '' };
let selectedTradeAsset = { id: null, symbol: '', type: '' };
let selectedTradePriceCurrency = { code: '' };
let selectedTradeCommissionCurrency = { code: '' };
let selectedCurrency = { code: '', name: '' };
let selectedPlatform = { id: null, name: '' };
let selectedTradeNetwork = { name: '' };
let selectedFromNetwork = { name: '' };
let selectedToNetwork = { name: '' };
let currentNoteId = null;
let currentOrderId = null;

// ============================================================================
// ДАННЫЕ ИЗ PHP
// ============================================================================

//const platformsData = typeof platformsData !== 'undefined' ? platformsData : [];
//const assetsData = typeof assetsData !== 'undefined' ? assetsData : [];
//const allCurrencies = typeof allCurrencies !== 'undefined' ? allCurrencies : [];
//const fiatCurrencies = typeof fiatCurrencies !== 'undefined' ? fiatCurrencies : [];
//const networksData = typeof networksData !== 'undefined' ? networksData : [];
//const cryptoTypeAssetsData = typeof cryptoTypeAssetsData !== 'undefined' ? cryptoTypeAssetsData : {};
//const sectorAssetsData = typeof sectorAssetsData !== 'undefined' ? sectorAssetsData : {};
//const networkAssetsData = typeof networkAssetsData !== 'undefined' ? networkAssetsData : {};
//1/const usdRubRate = typeof usdRubRate !== 'undefined' ? usdRubRate : 92.5;

// ============================================================================
// ЦВЕТОВЫЕ СХЕМЫ ДЛЯ МОДАЛЬНЫХ ОКОН
// ============================================================================

const modalColorSchemes = {
    deposit: {
        platform: { headerIcon: '#00a86b', headerTitle: 'Выберите площадку', listItemColor: '#00a86b', addButtonColor: '#00a86b' },
        currency: { headerIcon: '#00a86b', headerTitle: 'Выберите валюту', listItemColor: '#00a86b', addButtonColor: '#00a86b' },
        addPlatform: { headerIcon: '#00a86b', confirmButton: '#00a86b' },
        addCurrency: { headerIcon: '#00a86b', confirmButton: '#00a86b' }
    },
    buy: {
        platform: { headerIcon: '#00a86b', headerTitle: 'Выберите площадку покупки', listItemColor: '#00a86b', addButtonColor: '#00a86b' },
        currency: { headerIcon: '#00a86b', headerTitle: 'Выберите валюту цены', listItemColor: '#00a86b', addButtonColor: '#00a86b' },
        addPlatform: { headerIcon: '#00a86b', confirmButton: '#00a86b' },
        addCurrency: { headerIcon: '#00a86b', confirmButton: '#00a86b' }
    },
    sell: {
        platform: { headerIcon: '#e53e3e', headerTitle: 'Выберите площадку продажи', listItemColor: '#e53e3e', addButtonColor: '#e53e3e' },
        currency: { headerIcon: '#e53e3e', headerTitle: 'Выберите валюту цены', listItemColor: '#e53e3e', addButtonColor: '#e53e3e' },
        addPlatform: { headerIcon: '#e53e3e', confirmButton: '#e53e3e' },
        addCurrency: { headerIcon: '#e53e3e', confirmButton: '#e53e3e' }
    },
    transfer: {
        platform: { headerIcon: '#ff9f4a', headerTitleFrom: 'Выберите площадку отправителя', headerTitleTo: 'Выберите площадку получателя', listItemColor: '#ff9f4a', addButtonColor: '#ff9f4a' },
        currency: { headerIcon: '#ff9f4a', headerTitleAsset: 'Выберите актив', headerTitleCommission: 'Выберите валюту комиссии', listItemColor: '#ff9f4a', addButtonColor: '#ff9f4a' },
        addPlatform: { headerIcon: '#ff9f4a', confirmButton: '#ff9f4a' },
        addCurrency: { headerIcon: '#ff9f4a', confirmButton: '#ff9f4a' }
    },
    buy_from: {
        platform: { headerIcon: '#4a9eff', headerTitle: 'Выберите площадку списания', listItemColor: '#4a9eff', addButtonColor: '#4a9eff' }
    },
    default: {
        platform: { headerIcon: '#1a5cff', headerTitle: 'Выберите площадку', listItemColor: '#1a5cff', addButtonColor: '#1a5cff' },
        currency: { headerIcon: '#1a5cff', headerTitle: 'Выберите валюту', listItemColor: '#1a5cff', addButtonColor: '#1a5cff' },
        addPlatform: { headerIcon: '#1a5cff', confirmButton: '#1a5cff' },
        addCurrency: { headerIcon: '#1a5cff', confirmButton: '#1a5cff' }
    }
};

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
    setTimeout(() => closeNotification(notificationId), duration);
}

function closeNotification(notificationId) {
    const notification = document.getElementById(notificationId);
    if (notification) notification.remove();
}

// ============================================================================
// ФУНКЦИИ ФОРМАТИРОВАНИЯ
// ============================================================================

function formatNumberWithSpaces(value, decimals = null) {
    if (!value && value !== 0) return '';
    let numStr = String(value).replace(/\s/g, '').replace(',', '.');
    if (isNaN(parseFloat(numStr))) return value;
    let num = parseFloat(numStr);
    let decimalPlaces = decimals !== null ? decimals : 6;
    let formatted = num.toFixed(decimalPlaces);
    formatted = formatted.replace(/\.?0+$/, '');
    let parts = formatted.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return parts.length > 1 && parts[1] ? parts[0] + '.' + parts[1] : parts[0];
}

function getNumericValue(formattedValue) {
    if (!formattedValue) return 0;
    const cleanValue = String(formattedValue).replace(/\s/g, '').replace(',', '.');
    const num = parseFloat(cleanValue);
    return isNaN(num) ? 0 : num;
}

function formatInput(input) {
    if (!input) return;
    const cursorPos = input.selectionStart;
    const value = input.value;
    const oldLength = value.length;
    if (value === '' || value === '-') return;
    let rawValue = value.replace(/\s/g, '').replace(',', '.');
    if (/[a-zA-Zа-яА-Я]/.test(rawValue)) return;
    if (rawValue === '.' || rawValue === '-.' || rawValue === '0.' || rawValue === '0.0') {
        input.value = rawValue;
        input.setSelectionRange(input.value.length, input.value.length);
        return;
    }
    if (rawValue.endsWith('.')) {
        const valueWithoutDot = rawValue.slice(0, -1);
        if (/^-?\d*$/.test(valueWithoutDot) || valueWithoutDot === '' || valueWithoutDot === '-') {
            input.value = rawValue;
            input.setSelectionRange(input.value.length, input.value.length);
            return;
        }
    }
    if (!/^-?\d*\.?\d*$/.test(rawValue)) return;
    let num = parseFloat(rawValue);
    if (isNaN(num)) {
        input.value = rawValue;
        return;
    }
    const hasDecimalPoint = rawValue.includes('.');
    let originalDecimalPlaces = 0;
    const decimalMatch = rawValue.match(/\.(\d+)$/);
    if (decimalMatch) originalDecimalPlaces = decimalMatch[1].length;
    if (hasDecimalPoint && originalDecimalPlaces > 0) {
        let parts = rawValue.split('.');
        if (parts[0] && parts[0] !== '' && parts[0] !== '-') {
            let integerPart = parts[0];
            if (integerPart.length > 1 && integerPart.startsWith('0') && !integerPart.startsWith('0.')) {
                integerPart = integerPart.replace(/^0+/, '');
                if (integerPart === '') integerPart = '0';
            }
            integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            parts[0] = integerPart;
        } else if (parts[0] === '') {
            parts[0] = '0';
        } else if (parts[0] === '-') {
            parts[0] = '-0';
        }
        input.value = parts.join('.');
        const newLength = input.value.length;
        const lengthDiff = newLength - oldLength;
        input.setSelectionRange(Math.min(cursorPos + lengthDiff, newLength), Math.min(cursorPos + lengthDiff, newLength));
        return;
    }
    if (num === 0 && !hasDecimalPoint) {
        input.value = '0';
        return;
    }
    if (Number.isInteger(num) && !hasDecimalPoint) {
        input.value = num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const newLength = input.value.length;
        const lengthDiff = newLength - oldLength;
        input.setSelectionRange(Math.min(cursorPos + lengthDiff, newLength), Math.min(cursorPos + lengthDiff, newLength));
        return;
    }
    let decimals = originalDecimalPlaces > 0 ? originalDecimalPlaces : 8;
    let formatted = num.toFixed(decimals);
    let parts = formatted.split('.');
    let integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    let decimalPart = parts[1].replace(/0+$/, '');
    let formattedValue = decimalPart.length > 0 ? integerPart + '.' + decimalPart : integerPart;
    input.value = formattedValue;
    const newLength = formattedValue.length;
    const lengthDiff = newLength - oldLength;
    let newCursorPos = Math.min(cursorPos + lengthDiff, newLength);
    if (rawValue === '.') newCursorPos = formattedValue.indexOf('.') + 1;
    input.setSelectionRange(newCursorPos, newCursorPos);
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

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ОПЕРАЦИЙ (ЗАГРУЗКА И ОТОБРАЖЕНИЕ)
// ============================================================================

async function loadOperations(page) {
    currentOperationsPage = page;
    const operationsList = document.getElementById('operationsList');
    if (operationsList) operationsList.style.opacity = '0.5';
    try {
        const url = `/index.php?api=get_operations&page=${page}&per_page=5`;
        const response = await fetch(url);
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
        if (operationsList) operationsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;">Ошибка загрузки операций</div>';
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
            if (op.commission && op.commission > 0) detailsLine += ` · комиссия ${formatAmount(op.commission, op.commission_currency || op.currency)} ${op.commission_currency || op.currency}`;
            iconClass = 'icon-convert';
            iconType = 'fa-exchange-alt';
        }
        else if (op.operation_type == 'transfer_out') {
            displayText = `Исходящий перевод: ${formatAmount(op.amount_out, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(op.date)} · ${op.platform}`;
            if (op.commission && op.commission > 0) detailsLine += ` · комиссия ${formatAmount(op.commission, op.commission_currency || op.currency)} ${op.commission_currency || op.currency}`;
            iconClass = 'icon-convert';
            iconType = 'fa-exchange-alt';
        }
        if (displayText) {
            html += `<div class="operation-item"><div class="operation-icon ${iconClass}"><i class="fas ${iconType}"></i></div><div class="operation-details"><div class="operation-title">${displayText}</div><div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">${detailsLine}</div></div></div>`;
        }
    });
    if (html === '') html = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет операций для отображения</div>';
    container.innerHTML = html;
    const paginationContainer = document.getElementById('paginationControls');
    if (paginationContainer && pagination.total > pagination.per_page) {
        paginationContainer.innerHTML = `<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #edf2f7;"><div style="display: flex; gap: 5px;">${pagination.has_previous ? `<button onclick="loadOperations(${pagination.current_page - 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px;"><i class="fas fa-chevron-left"></i> Назад</button>` : ''}${pagination.has_next ? `<button onclick="loadOperations(${pagination.current_page + 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px;">Вперед <i class="fas fa-chevron-right"></i></button>` : ''}</div><div style="color: #6b7a8f; font-size: 13px;">Страница ${pagination.current_page} из ${pagination.total_pages}</div></div>`;
    }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ МОДАЛЬНЫХ ОКОН (ВЫБОР ПЛОЩАДКИ, ВАЛЮТЫ, АКТИВА)
// ============================================================================

function setModalContext(source, mode, subMode = null) {
    currentModalContext = { source, mode, subMode };
}

function getColorScheme() {
    const source = currentModalContext.source || 'default';
    const subMode = currentModalContext.subMode;
    let scheme = modalColorSchemes[source] || modalColorSchemes.default;
    if (source === 'buy' && subMode === 'from') return modalColorSchemes.buy_from;
    return scheme;
}

function openPlatformModal(context = 'default', subMode = null) {
    setModalContext(context, 'platform', subMode);
    const scheme = getColorScheme();
    const platformScheme = scheme.platform || modalColorSchemes.default.platform;
    const modalTitle = document.querySelector('#platformSelectModal .modal-header h2');
    let titleText = platformScheme.headerTitle;
    if (context === 'transfer') {
        if (subMode === 'from') titleText = platformScheme.headerTitleFrom || 'Выберите площадку отправителя';
        else if (subMode === 'to') titleText = platformScheme.headerTitleTo || 'Выберите площадку получателя';
    } else if (context === 'buy' && subMode === 'from') titleText = 'Выберите площадку списания';
    modalTitle.innerHTML = `<i class="fas fa-building" style="color: ${platformScheme.headerIcon};"></i> ${titleText}`;
    const modal = document.getElementById('platformSelectModal');
    if (modal) {
        filterPlatforms('');
        modal.classList.add('active');
        setTimeout(() => document.getElementById('platformSearch')?.focus(), 100);
    }
}

function closePlatformModal() {
    const modal = document.getElementById('platformSelectModal');
    if (modal) {
        modal.classList.remove('active');
        document.getElementById('platformSearch').value = '';
    }
}

function filterPlatforms(searchText) {
    const listContainer = document.getElementById('allPlatformsList');
    if (!listContainer) return;
    const scheme = getColorScheme();
    const platformScheme = scheme.platform || modalColorSchemes.default.platform;
    const searchTextLower = searchText.toLowerCase().trim();
    const originalSearchText = searchText.trim();
    let platformsToShow = platformsData;
    if (searchTextLower) platformsToShow = platformsData.filter(p => p.name.toLowerCase().includes(searchTextLower));
    if (platformsToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `<div onclick="addNewPlatformAndSelect('${originalSearchText.replace(/'/g, "\\'")}', '${currentModalContext.source}')" style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: ${platformScheme.addButtonColor}; transition: all 0.2s;" onmouseover="this.style.background='#f0f3f7'" onmouseout="this.style.background='transparent'"><i class="fas fa-plus-circle" style="margin-right: 8px;"></i>Добавить "${originalSearchText}"</div>`;
        return;
    }
    listContainer.innerHTML = platformsToShow.map(platform => `<div onclick="selectPlatformFromList('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px; transition: background 0.2s;" onmouseover="this.style.background='#f0f3f7'" onmouseout="this.style.background='transparent'"><span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span><span style="color: ${platformScheme.listItemColor}; font-size: 12px; margin-left: auto;"><i class="fas fa-chevron-right"></i></span></div>`).join('');
}

function selectPlatformFromList(id, name) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    if (context === 'transfer') {
        if (subMode === 'from') selectFromPlatform(id, name);
        else if (subMode === 'to') selectToPlatform(id, name);
    } else if (context === 'buy' && subMode === 'from') selectTradeFromPlatform(id, name);
    else if (context === 'buy' || context === 'sell') selectTradePlatform(id, name);
    else if (context === 'deposit') selectPlatform(id, name);
    closePlatformModal();
}

function openCurrencyModal(context = 'default', subMode = null) {
    if (subMode === 'asset') {
        openAssetModal(context, subMode);
        return;
    }
    setModalContext(context, 'currency', subMode);
    const scheme = getColorScheme();
    const currencyScheme = scheme.currency || modalColorSchemes.default.currency;
    const modalTitle = document.querySelector('#currencySelectModal .modal-header h2');
    let titleText = currencyScheme.headerTitle;
    if (context === 'transfer') {
        if (subMode === 'commission') titleText = currencyScheme.headerTitleCommission || 'Выберите валюту комиссии';
    } else if (context === 'buy' || context === 'sell') {
        if (subMode === 'price') titleText = 'Выберите валюту цены';
        else if (subMode === 'commission') titleText = 'Выберите валюту комиссии';
    }
    modalTitle.innerHTML = `<i class="fas fa-coins" style="color: ${currencyScheme.headerIcon};"></i> ${titleText}`;
    const modal = document.getElementById('currencySelectModal');
    if (modal) {
        filterCurrencies('');
        modal.classList.add('active');
        setTimeout(() => document.getElementById('currencySearch')?.focus(), 100);
    }
}

function closeCurrencyModal() {
    const modal = document.getElementById('currencySelectModal');
    if (modal) {
        modal.classList.remove('active');
        document.getElementById('currencySearch').value = '';
    }
}

function filterCurrencies(searchText) {
    if (currentModalContext && currentModalContext.mode === 'asset') {
        filterAssetsForSelect(searchText);
        return;
    }
    const listContainer = document.getElementById('allCurrenciesList');
    if (!listContainer) return;
    const scheme = getColorScheme();
    const currencyScheme = scheme.currency || modalColorSchemes.default.currency;
    const searchTextLower = searchText.toLowerCase().trim();
    const originalSearchText = searchText.trim();
    let currenciesToShow = allCurrencies;
    if (searchTextLower) currenciesToShow = allCurrencies.filter(c => c.code.toLowerCase().includes(searchTextLower) || (c.name && c.name.toLowerCase().includes(searchTextLower)));
    if (currenciesToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `<div onclick="addNewCurrencyAndSelect('${originalSearchText.replace(/'/g, "\\'")}', '${currentModalContext.source}', '${currentModalContext.subMode || 'default'}')" style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: ${currencyScheme.addButtonColor}; transition: all 0.2s;" onmouseover="this.style.background='#f0f3f7'" onmouseout="this.style.background='transparent'"><i class="fas fa-plus-circle" style="margin-right: 8px;"></i>Добавить "${originalSearchText.toUpperCase()}"</div>`;
        return;
    }
    listContainer.innerHTML = currenciesToShow.map(currency => `<div onclick="selectCurrencyFromList('${currency.code}', '${currency.name || currency.code}')" style="padding: 12px; cursor: pointer; border-radius: 10px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; border: 1px solid transparent;" onmouseover="this.style.background='#f8fafd'; this.style.borderColor='#e0e6ed'" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'"><div style="width: 36px; height: 36px; background: ${currencyScheme.listItemColor}15; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${currencyScheme.listItemColor};"><i class="fas fa-coins"></i></div><div style="flex: 1;"><div class="asset-symbol">${currency.code}</div><div style="font-size: 12px; color: #6b7a8f;">${currency.name || ''}</div></div><i class="fas fa-chevron-right" style="color: #95a5a6; font-size: 12px;"></i></div>`).join('');
}

function selectCurrencyFromList(code, name) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    if (context === 'transfer') {
        if (subMode === 'commission') selectCommissionCurrency(code);
    } else if (context === 'buy' || context === 'sell') {
        if (subMode === 'price') selectTradePriceCurrency(code);
        else if (subMode === 'commission') selectTradeCommissionCurrency(code);
    } else if (context === 'deposit') selectCurrency(code, name);
    else if (context === 'limit') selectLimitCurrency(code);
    else if (context === 'expense') document.getElementById('selectedExpenseCurrencyDisplay').textContent = code;
    closeCurrencyModal();
}

function openAssetModal(context = 'default', subMode = null) {
    setModalContext(context, 'asset', subMode);
    const modalTitle = document.querySelector('#currencySelectModal .modal-header h2');
    modalTitle.innerHTML = '<i class="fas fa-coins" style="color: #ff9f4a;"></i> Выберите актив';
    const modal = document.getElementById('currencySelectModal');
    if (modal) {
        filterAssetsForSelect('');
        modal.classList.add('active');
        setTimeout(() => document.getElementById('currencySearch')?.focus(), 100);
    }
}

function filterAssetsForSelect(searchText) {
    const listContainer = document.getElementById('allCurrenciesList');
    if (!listContainer) return;
    const searchTextLower = searchText.toLowerCase().trim();
    const originalSearchText = searchText.trim();
    let assetsToShow = assetsData;
    if (searchTextLower) assetsToShow = assetsData.filter(a => a.symbol.toLowerCase().includes(searchTextLower) || (a.name && a.name.toLowerCase().includes(searchTextLower)));
    assetsToShow.sort((a, b) => {
        const typeOrder = { 'crypto': 1, 'stock': 2, 'etf': 3, 'currency': 4, 'bond': 5, 'other': 6 };
        return (typeOrder[a.type] || 99) - (typeOrder[b.type] || 99);
    });
    if (assetsToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `<div onclick="addNewAssetFromCurrencyModal('${originalSearchText.replace(/'/g, "\\'")}')" style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: #ff9f4a; transition: all 0.2s;" onmouseover="this.style.background='#f0f3f7'" onmouseout="this.style.background='transparent'"><i class="fas fa-plus-circle" style="margin-right: 8px;"></i>Добавить "${originalSearchText}"</div>`;
        return;
    }
    listContainer.innerHTML = assetsToShow.map(asset => {
        let iconColor = '#6b7a8f';
        let typeIcon = 'fa-coins';
        let typeText = '';
        switch(asset.type) {
            case 'crypto': iconColor = '#f7931a'; typeIcon = 'fa-bitcoin'; typeText = 'Крипто'; break;
            case 'stock': iconColor = '#00a86b'; typeIcon = 'fa-chart-line'; typeText = 'Акция'; break;
            case 'etf': iconColor = '#4a9eff'; typeIcon = 'fa-chart-pie'; typeText = 'ETF'; break;
            case 'currency': iconColor = '#1a5cff'; typeIcon = 'fa-money-bill'; typeText = 'Валюта'; break;
            case 'bond': iconColor = '#9b59b6'; typeIcon = 'fa-file-invoice'; typeText = 'Облигация'; break;
            default: typeText = 'Другое';
        }
        return `<div onclick="selectAssetFromModal('${asset.id}', '${asset.symbol.replace(/'/g, "\\'")}', '${asset.type || 'other'}')" style="padding: 12px; cursor: pointer; border-radius: 10px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; border: 1px solid transparent;" onmouseover="this.style.background='#f8fafd'; this.style.borderColor='#e0e6ed'" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'"><div style="width: 36px; height: 36px; background: ${iconColor}15; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${iconColor};"><i class="fas ${typeIcon}"></i></div><div style="flex: 1;"><div class="asset-symbol">${asset.symbol}</div><div style="font-size: 12px; color: #6b7a8f; display: flex; gap: 8px; margin-top: 2px;"><span>${asset.name || ''}</span><span style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 12px;">${typeText}</span></div></div><i class="fas fa-chevron-right" style="color: #95a5a6; font-size: 12px;"></i></div>`;
    }).join('');
}

function selectAssetFromModal(id, symbol, type) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    if (context === 'transfer' && subMode === 'asset') selectAsset(id, symbol);
    else if (context === 'buy' || context === 'sell') selectTradeAsset(id, symbol, type);
    closeCurrencyModal();
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ДОБАВЛЕНИЯ НОВЫХ ЭЛЕМЕНТОВ
// ============================================================================

let pendingPlatformName = '';
let pendingCurrencyCode = '';
let pendingCurrencyMode = 'default';

async function addNewPlatformAndSelect(platformName, context = 'default') {
    if (!platformName) return;
    const newName = platformName.trim();
    const exists = platformsData.some(p => p.name.toLowerCase() === newName.toLowerCase());
    if (exists) {
        const platform = platformsData.find(p => p.name.toLowerCase() === newName.toLowerCase());
        if (context === 'transfer') {
            if (currentModalContext.subMode === 'from') selectFromPlatform(platform.id, platform.name);
            else if (currentModalContext.subMode === 'to') selectToPlatform(platform.id, platform.name);
        } else if (context === 'buy' && currentModalContext.subMode === 'from') selectTradeFromPlatform(platform.id, platform.name);
        else if (context === 'buy' || context === 'sell') selectTradePlatform(platform.id, platform.name);
        else if (context === 'deposit') selectPlatform(platform.id, platform.name);
        closePlatformModal();
        return;
    }
    openAddPlatformModal(newName, context);
}

function openAddPlatformModal(platformName, context = 'default') {
    setModalContext(context, 'addPlatform');
    const scheme = getColorScheme();
    const addScheme = scheme.addPlatform || modalColorSchemes.default.addPlatform;
    pendingPlatformName = platformName;
    const modalTitle = document.querySelector('#addPlatformModal .modal-header h2');
    modalTitle.innerHTML = `<i class="fas fa-plus-circle" style="color: ${addScheme.headerIcon};"></i> Добавление площадки`;
    const confirmBtn = document.getElementById('confirmAddPlatformBtn');
    if (confirmBtn) confirmBtn.style.background = addScheme.confirmButton;
    const nameInput = document.getElementById('newPlatformName');
    if (nameInput) nameInput.value = platformName;
    document.getElementById('newPlatformCountry').value = '';
    document.querySelectorAll('.platform-type-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('newPlatformType').value = '';
    const parentModal = document.getElementById('platformSelectModal');
    if (parentModal && parentModal.classList.contains('active')) parentModal.classList.remove('active');
    const modal = document.getElementById('addPlatformModal');
    if (modal) modal.classList.add('active');
}

function closeAddPlatformModal() {
    const modal = document.getElementById('addPlatformModal');
    if (modal) modal.classList.remove('active');
}

function setActivePlatformType(type) {
    document.querySelectorAll('.platform-type-btn').forEach(btn => btn.classList.remove('active'));
    const selectedBtn = document.querySelector(`.platform-type-btn[data-type="${type}"]`);
    if (selectedBtn) selectedBtn.classList.add('active');
    document.getElementById('newPlatformType').value = type;
}

function getSelectedPlatformType() {
    return document.getElementById('newPlatformType').value;
}

async function saveNewPlatform() {
    const name = document.getElementById('newPlatformName').value;
    const type = getSelectedPlatformType();
    const country = document.getElementById('newPlatformCountry').value;
    if (!name.trim()) { showNotification('error', 'Ошибка', 'Название площадки обязательно'); return; }
    if (!type) { showNotification('error', 'Ошибка', 'Выберите тип площадки'); return; }
    const formData = new FormData();
    formData.append('action', 'add_platform_full');
    formData.append('name', name);
    formData.append('type', type);
    formData.append('country', country);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success && result.platform_id) {
            showNotification('success', 'Успешно', 'Площадка добавлена');
            platformsData.push({ id: result.platform_id, name: name, type: type });
            refreshAllPlatformLists();
            const context = currentModalContext.source;
            const subMode = currentModalContext.subMode;
            if (context === 'transfer') {
                if (subMode === 'from') selectFromPlatform(result.platform_id, name);
                else if (subMode === 'to') selectToPlatform(result.platform_id, name);
            } else if (context === 'buy' && subMode === 'from') selectTradeFromPlatform(result.platform_id, name);
            else if (context === 'buy' || context === 'sell') selectTradePlatform(result.platform_id, name);
            else if (context === 'deposit') selectPlatform(result.platform_id, name);
            closeAddPlatformModal();
            closePlatformModal();
        } else showNotification('error', 'Ошибка', result.message || 'Не удалось добавить площадку');
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось добавить площадку'); }
}

function refreshAllPlatformLists() {
    const platformModal = document.getElementById('platformSelectModal');
    if (platformModal && platformModal.classList.contains('active')) filterPlatforms(document.getElementById('platformSearch')?.value || '');
    const popularPlatformsContainers = ['depositPopularPlatforms', 'tradePopularPlatforms', 'tradeFromPopularPlatforms', 'transferFromPopularPlatforms', 'transferToPopularPlatforms', 'limitPopularPlatforms'];
    popularPlatformsContainers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container) {
            const popularPlatforms = platformsData.slice(0, 5);
            let onclickHandler = '';
            if (containerId === 'transferFromPopularPlatforms') onclickHandler = 'selectFromPlatform';
            else if (containerId === 'transferToPopularPlatforms') onclickHandler = 'selectToPlatform';
            else if (containerId === 'tradeFromPopularPlatforms') onclickHandler = 'selectTradeFromPlatform';
            else if (containerId === 'tradePopularPlatforms') onclickHandler = 'selectTradePlatform';
            else if (containerId === 'depositPopularPlatforms') onclickHandler = 'selectPlatform';
            else if (containerId === 'limitPopularPlatforms') onclickHandler = 'selectLimitPlatform';
            else onclickHandler = 'selectPlatformFromList';
            container.innerHTML = popularPlatforms.map(platform => `<button type="button" class="quick-platform-btn" onclick="${onclickHandler}('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')">${platform.name}</button>`).join('');
        }
    });
    const platformsLists = ['depositPlatformsList', 'transferFromPlatformsList', 'transferToPlatformsList'];
    platformsLists.forEach(listId => {
        const list = document.getElementById(listId);
        if (list && list.style.display !== 'none') {
            const scheme = getColorScheme();
            const platformScheme = scheme.platform || modalColorSchemes.default.platform;
            let onclickHandler = '';
            if (listId === 'transferFromPlatformsList') onclickHandler = 'selectFromPlatform';
            else if (listId === 'transferToPlatformsList') onclickHandler = 'selectToPlatform';
            else onclickHandler = 'selectPlatform';
            list.innerHTML = platformsData.map(platform => `<div onclick="${onclickHandler}('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px;"><span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span></div>`).join('');
        }
    });
    updateTransferModalPlatforms();
}

function updateTransferModalPlatforms() {
    const fromContainer = document.getElementById('transferFromPopularPlatforms');
    if (fromContainer) {
        const popularPlatforms = platformsData.slice(0, 5);
        fromContainer.innerHTML = popularPlatforms.map(platform => `<button type="button" class="quick-platform-btn" onclick="selectFromPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')">${platform.name}</button>`).join('');
    }
    const toContainer = document.getElementById('transferToPopularPlatforms');
    if (toContainer) {
        const popularPlatforms = platformsData.slice(0, 5);
        toContainer.innerHTML = popularPlatforms.map(platform => `<button type="button" class="quick-platform-btn" onclick="selectToPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')">${platform.name}</button>`).join('');
    }
    const fromList = document.getElementById('transferFromPlatformsList');
    if (fromList && fromList.style.display !== 'none') {
        const scheme = getColorScheme();
        const platformScheme = scheme.platform || modalColorSchemes.default.platform;
        fromList.innerHTML = platformsData.map(platform => `<div onclick="selectFromPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px;"><span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span></div>`).join('');
    }
    const toList = document.getElementById('transferToPlatformsList');
    if (toList && toList.style.display !== 'none') {
        const scheme = getColorScheme();
        const platformScheme = scheme.platform || modalColorSchemes.default.platform;
        toList.innerHTML = platformsData.map(platform => `<div onclick="selectToPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px;"><span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span></div>`).join('');
    }
}

function addNewCurrencyAndSelect(currencyCode, context = 'default', mode = 'default') {
    if (!currencyCode) return;
    const newCode = currencyCode.trim().toUpperCase();
    const exists = allCurrencies.some(c => c.code.toUpperCase() === newCode);
    if (exists) {
        const currency = allCurrencies.find(c => c.code.toUpperCase() === newCode);
        if (context === 'transfer' && mode === 'commission') selectCommissionCurrency(currency.code);
        else if ((context === 'buy' || context === 'sell') && mode === 'price') selectTradePriceCurrency(currency.code);
        else if ((context === 'buy' || context === 'sell') && mode === 'commission') selectTradeCommissionCurrency(currency.code);
        else if (context === 'deposit') selectCurrency(currency.code, currency.name);
        closeCurrencyModal();
        return;
    }
    openAddCurrencyModal(newCode, context, mode);
}

function openAddCurrencyModal(currencyCode, context = 'default', mode = 'default') {
    setModalContext(context, 'addCurrency', mode);
    const scheme = getColorScheme();
    const addScheme = scheme.addCurrency || modalColorSchemes.default.addCurrency;
    pendingCurrencyCode = currencyCode;
    pendingCurrencyMode = mode;
    const modalTitle = document.querySelector('#addCurrencyModal .modal-header h2');
    modalTitle.innerHTML = `<i class="fas fa-plus-circle" style="color: ${addScheme.headerIcon};"></i> Добавление валюты`;
    const confirmBtn = document.getElementById('confirmAddCurrencyBtn');
    if (confirmBtn) confirmBtn.style.background = addScheme.confirmButton;
    document.getElementById('newCurrencyCode').value = currencyCode.toUpperCase();
    document.getElementById('newCurrencyName').value = '';
    document.getElementById('newCurrencySymbol').value = '';
    document.querySelectorAll('.currency-type-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('newCurrencyType').value = '';
    const parentModal = document.getElementById('currencySelectModal');
    if (parentModal && parentModal.classList.contains('active')) parentModal.classList.remove('active');
    const modal = document.getElementById('addCurrencyModal');
    if (modal) modal.classList.add('active');
}

function closeAddCurrencyModal() {
    const modal = document.getElementById('addCurrencyModal');
    if (modal) modal.classList.remove('active');
}

function setActiveCurrencyType(type) {
    document.querySelectorAll('.currency-type-btn').forEach(btn => btn.classList.remove('active'));
    const selectedBtn = document.querySelector(`.currency-type-btn[data-type="${type}"]`);
    if (selectedBtn) selectedBtn.classList.add('active');
    document.getElementById('newCurrencyType').value = type;
}

function getSelectedCurrencyType() {
    return document.getElementById('newCurrencyType').value;
}

async function saveNewCurrency() {
    const code = document.getElementById('newCurrencyCode').value.toUpperCase();
    const name = document.getElementById('newCurrencyName').value.trim();
    const type = getSelectedCurrencyType();
    const symbol = document.getElementById('newCurrencySymbol').value.trim();
    if (!code) { showNotification('error', 'Ошибка', 'Код валюты обязателен'); return; }
    if (!name) { showNotification('error', 'Ошибка', 'Название валюты обязательно'); return; }
    if (!type) { showNotification('error', 'Ошибка', 'Выберите тип валюты'); return; }
    const formData = new FormData();
    formData.append('action', 'add_currency_full');
    formData.append('code', code);
    formData.append('name', name);
    formData.append('type', type);
    formData.append('symbol', symbol);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showNotification('success', 'Успешно', 'Валюта добавлена');
            allCurrencies.push({ code: code, name: name, symbol: symbol, type: type });
            if (pendingCurrencyMode === 'commission') selectCommissionCurrency(code);
            else if (pendingCurrencyMode === 'price') selectTradePriceCurrency(code);
            else if (pendingCurrencyMode === 'commission_trade') selectTradeCommissionCurrency(code);
            else selectCurrency(code, name);
            closeAddCurrencyModal();
        } else showNotification('error', 'Ошибка', result.message || 'Не удалось добавить валюту');
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось добавить валюту'); }
}

async function addNewAssetFromCurrencyModal(assetSymbol) {
    if (!assetSymbol) return;
    const newSymbol = assetSymbol.trim().toUpperCase();
    const exists = assetsData.some(a => a.symbol.toUpperCase() === newSymbol);
    if (exists) {
        const asset = assetsData.find(a => a.symbol.toUpperCase() === newSymbol);
        selectAssetFromModal(asset.id, asset.symbol, asset.type);
        return;
    }
    openAddAssetModal(newSymbol);
}

function openAddAssetModal(assetSymbol) {
    const symbolInput = document.getElementById('newAssetSymbol');
    if (symbolInput) symbolInput.value = assetSymbol;
    document.getElementById('newAssetName').value = '';
    document.querySelectorAll('.asset-type-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('newAssetType').value = '';
    const parentModal = document.getElementById('currencySelectModal');
    if (parentModal) parentModal.classList.remove('active');
    const modal = document.getElementById('addAssetModal');
    if (modal) modal.classList.add('active');
}

function closeAddAssetModal() {
    const modal = document.getElementById('addAssetModal');
    if (modal) modal.classList.remove('active');
}

function setActiveAssetType(type) {
    document.querySelectorAll('.asset-type-btn').forEach(btn => btn.classList.remove('active'));
    const selectedBtn = document.querySelector(`.asset-type-btn[data-type="${type}"]`);
    if (selectedBtn) selectedBtn.classList.add('active');
    document.getElementById('newAssetType').value = type;
    const sectorGroup = document.getElementById('sectorSelectGroup');
    if (sectorGroup) {
        if (type === 'stock' || type === 'etf') sectorGroup.style.display = 'block';
        else {
            sectorGroup.style.display = 'none';
            document.querySelectorAll('.sector-option-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('newAssetSector').value = '';
        }
    }
}

function getSelectedAssetType() {
    return document.getElementById('newAssetType').value;
}

async function saveNewAsset() {
    const symbol = document.getElementById('newAssetSymbol').value.toUpperCase();
    const name = document.getElementById('newAssetName').value.trim();
    const type = getSelectedAssetType();
    const sector = document.getElementById('newAssetSector').value;
    let currencyCode = null;
    if (type === 'stock') {
        if (symbol.endsWith('.US') || ['TSLA', 'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META', 'NVDA', 'NFLX'].includes(symbol)) currencyCode = 'USD';
        else if (['SBER', 'GAZP', 'LKOH', 'YNDX', 'ROSN', 'VTBR', 'TATN', 'NLMK'].includes(symbol)) currencyCode = 'RUB';
        else currencyCode = 'USD';
    } else if (type === 'crypto') currencyCode = 'USD';
    else if (type === 'currency') currencyCode = symbol;
    else if (type === 'etf') currencyCode = 'USD';
    if ((type === 'stock' || type === 'etf') && !sector) {
        showNotification('error', 'Ошибка', 'Выберите сектор для акции/ETF');
        return;
    }
    if (!symbol) { showNotification('error', 'Ошибка', 'Символ актива обязателен'); return; }
    if (!name) { showNotification('error', 'Ошибка', 'Название актива обязательно'); return; }
    if (!type) { showNotification('error', 'Ошибка', 'Выберите тип актива'); return; }
    const formData = new FormData();
    formData.append('action', 'add_asset_full');
    formData.append('symbol', symbol);
    formData.append('name', name);
    formData.append('type', type);
    formData.append('currency_code', currencyCode || '');
    formData.append('sector', sector || '');
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success && result.asset_id) {
            showNotification('success', 'Успешно', 'Актив добавлен');
            assetsData.push({ id: result.asset_id, symbol: symbol, name: name, type: type, currency_code: currencyCode, sector: sector });
            if (currentModalContext.source === 'transfer') selectAsset(result.asset_id, symbol);
            else selectTradeAsset(result.asset_id, symbol, type);
            closeAddAssetModal();
            closeCurrencyModal();
        } else showNotification('error', 'Ошибка', result.message || 'Не удалось добавить актив');
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось добавить актив'); }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ОСНОВНЫХ ОПЕРАЦИЙ (ПОКУПКА, ПРОДАЖА, ПЕРЕВОД, ПОПОЛНЕНИЕ)
// ============================================================================

function openDepositModal() {
    const modal = document.getElementById('depositModal');
    if (modal) {
        modal.classList.add('active');
        document.getElementById('depositDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('depositAmount').value = '';
        document.getElementById('selectedCurrencyDisplay').textContent = 'RUB';
        document.getElementById('depositCurrency').value = 'RUB';
        document.getElementById('selectedPlatformDisplay').textContent = 'Выбрать площадку';
        document.getElementById('depositPlatformId').value = '';
    }
}

function closeDepositModal() {
    const modal = document.getElementById('depositModal');
    if (modal) modal.classList.remove('active');
}

function selectPlatform(id, name) {
    selectedPlatform = { id, name };
    document.getElementById('selectedPlatformDisplay').textContent = name;
    document.getElementById('depositPlatformId').value = id;
}

function selectCurrency(code, name) {
    selectedCurrency = { code, name };
    document.getElementById('selectedCurrencyDisplay').textContent = code;
    document.getElementById('depositCurrency').value = code;
}

async function confirmDeposit() {
    const platformId = document.getElementById('depositPlatformId').value;
    const amount = getNumericValue(document.getElementById('depositAmount').value);
    const currency = document.getElementById('depositCurrency').value.toUpperCase();
    const date = document.getElementById('depositDate').value;
    if (!platformId) { showNotification('error', 'Ошибка', 'Выберите площадку'); return; }
    if (!amount || amount <= 0) { showNotification('error', 'Ошибка', 'Введите корректную сумму'); return; }
    if (!currency) { showNotification('error', 'Ошибка', 'Выберите валюту'); return; }
    const formData = new FormData();
    formData.append('action', 'add_deposit');
    formData.append('platform_id', platformId);
    formData.append('amount', amount);
    formData.append('currency', currency);
    formData.append('deposit_date', date);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeDepositModal();
            setTimeout(() => location.reload(), 1500);
        } else showNotification('error', 'Ошибка', result.message);
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос'); }
}

function openBuyModal() {
    openTradeModal('buy');
}

function openSellModal() {
    openTradeModal('sell');
}

function openTradeModal(type) {
    const modal = document.getElementById('tradeModal');
    if (!modal) return;
    document.getElementById('tradeOperationType').value = type;
    if (type === 'buy') {
        document.getElementById('tradeModalTitle').innerHTML = '<i class="fas fa-arrow-down" style="color: #00a86b;"></i> Покупка';
        document.getElementById('confirmTradeBtn').style.background = '#00a86b';
        document.getElementById('confirmTradeBtnText').textContent = 'Купить';
        document.getElementById('tradeFromPlatformGroup').style.display = 'block';
    } else {
        document.getElementById('tradeModalTitle').innerHTML = '<i class="fas fa-arrow-up" style="color: #e53e3e;"></i> Продажа';
        document.getElementById('confirmTradeBtn').style.background = '#e53e3e';
        document.getElementById('confirmTradeBtnText').textContent = 'Продать';
        document.getElementById('tradeFromPlatformGroup').style.display = 'none';
        const historyBlock = document.getElementById('sellPurchaseHistory');
        if (historyBlock) historyBlock.style.display = 'none';
    }
    modal.classList.add('active');
    document.getElementById('tradeDate').value = new Date().toISOString().split('T')[0];
    selectedTradeNetwork = { name: '' };
    document.getElementById('selectedTradeNetworkDisplay').textContent = 'Выбрать сеть';
    document.getElementById('tradeNetwork').value = '';
    selectedTradePlatform = { id: null, name: '' };
    document.getElementById('selectedTradePlatformDisplay').textContent = 'Выбрать площадку';
    document.getElementById('tradePlatformId').value = '';
    selectedTradeFromPlatform = { id: null, name: '' };
    document.getElementById('selectedTradeFromPlatformDisplay').textContent = 'Выбрать площадку';
    document.getElementById('tradeFromPlatformId').value = '';
    selectedTradeAsset = { id: null, symbol: '', type: '' };
    document.getElementById('selectedTradeAssetDisplay').textContent = 'Выбрать';
    document.getElementById('tradeAssetId').value = '';
    document.getElementById('tradeAssetType').value = '';
    document.getElementById('tradeQuantity').value = '';
    document.getElementById('tradePrice').value = '';
    selectedTradePriceCurrency = { code: '' };
    document.getElementById('selectedTradePriceCurrencyDisplay').textContent = 'Выбрать';
    document.getElementById('tradePriceCurrency').value = '';
    document.getElementById('tradeCommission').value = '';
    selectedTradeCommissionCurrency = { code: '' };
    document.getElementById('selectedTradeCommissionCurrencyDisplay').textContent = 'Выбрать';
    document.getElementById('tradeCommissionCurrency').value = '';
    document.getElementById('tradeNetwork').value = '';
    document.getElementById('tradeNotes').value = '';
    document.getElementById('tradeTotal').value = '0';
    document.getElementById('tradeCryptoNetworkSection').style.display = 'none';
}

function closeTradeModal() {
    const modal = document.getElementById('tradeModal');
    if (modal) modal.classList.remove('active');
}

function selectTradePlatform(id, name) {
    selectedTradePlatform = { id, name };
    document.getElementById('selectedTradePlatformDisplay').textContent = name;
    document.getElementById('tradePlatformId').value = id;
}

function selectTradeFromPlatform(id, name) {
    selectedTradeFromPlatform = { id, name };
    document.getElementById('selectedTradeFromPlatformDisplay').textContent = name;
    document.getElementById('tradeFromPlatformId').value = id;
}

function selectTradeAsset(id, symbol, type) {
    selectedTradeAsset = { id, symbol, type };
    document.getElementById('selectedTradeAssetDisplay').textContent = symbol;
    document.getElementById('tradeAssetId').value = id;
    document.getElementById('tradeAssetType').value = type || 'other';
    calculateTradeTotal();
    const cryptoSection = document.getElementById('tradeCryptoNetworkSection');
    if (cryptoSection) cryptoSection.style.display = type === 'crypto' ? 'block' : 'none';
}

function selectTradePriceCurrency(code) {
    selectedTradePriceCurrency = { code };
    document.getElementById('selectedTradePriceCurrencyDisplay').textContent = code;
    document.getElementById('tradePriceCurrency').value = code;
    const commissionDisplay = document.getElementById('selectedTradeCommissionCurrencyDisplay');
    const commissionHidden = document.getElementById('tradeCommissionCurrency');
    if (commissionDisplay && commissionHidden && !commissionHidden.value) {
        commissionDisplay.textContent = code;
        commissionHidden.value = code;
        selectedTradeCommissionCurrency = { code };
    }
    calculateTradeTotal();
}

function selectTradeCommissionCurrency(code) {
    selectedTradeCommissionCurrency = { code };
    document.getElementById('selectedTradeCommissionCurrencyDisplay').textContent = code;
    document.getElementById('tradeCommissionCurrency').value = code;
    calculateTradeTotal();
}

function calculateTradeTotal() {
    const quantity = getNumericValue(document.getElementById('tradeQuantity').value);
    const price = getNumericValue(document.getElementById('tradePrice').value);
    const commission = getNumericValue(document.getElementById('tradeCommission').value);
    const paymentCurrency = document.getElementById('tradePriceCurrency').value;
    const total = quantity * price + commission;
    let formattedTotal = '0';
    if (!isNaN(total) && isFinite(total) && total > 0) {
        let decimals = paymentCurrency === 'BTC' || paymentCurrency === 'ETH' ? 6 : 2;
        formattedTotal = total.toFixed(decimals);
        let parts = formattedTotal.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        formattedTotal = parts.join('.');
    }
    document.getElementById('tradeTotal').value = paymentCurrency ? `${formattedTotal} ${paymentCurrency}` : formattedTotal;
}

async function confirmTrade() {
    const operationType = document.getElementById('tradeOperationType').value;
    const platformId = document.getElementById('tradePlatformId').value;
    const fromPlatformId = operationType === 'buy' ? document.getElementById('tradeFromPlatformId').value : platformId;
    const assetId = document.getElementById('tradeAssetId').value;
    const quantity = getNumericValue(document.getElementById('tradeQuantity').value);
    const price = getNumericValue(document.getElementById('tradePrice').value);
    const priceCurrency = document.getElementById('tradePriceCurrency').value.toUpperCase();
    const commission = getNumericValue(document.getElementById('tradeCommission').value) || 0;
    const commissionCurrency = document.getElementById('tradeCommissionCurrency').value.toUpperCase() || '';
    const network = document.getElementById('tradeNetwork').value || '';
    const date = document.getElementById('tradeDate').value;
    const notes = document.getElementById('tradeNotes').value;
    if (!platformId) { showNotification('error', 'Ошибка', 'Выберите площадку'); return; }
    if (!assetId) { showNotification('error', 'Ошибка', 'Выберите актив'); return; }
    if (!quantity || quantity <= 0) { showNotification('error', 'Ошибка', 'Введите количество'); return; }
    if (!price || price <= 0) { showNotification('error', 'Ошибка', 'Введите цену'); return; }
    if (!priceCurrency) { showNotification('error', 'Ошибка', 'Выберите валюту цены'); return; }
    if (operationType === 'buy' && !fromPlatformId) { showNotification('error', 'Ошибка', 'Выберите площадку списания'); return; }
    if (commission > 0 && !commissionCurrency) { showNotification('error', 'Ошибка', 'Если указана комиссия, выберите валюту комиссии'); return; }
    const confirmBtn = document.getElementById('confirmTradeBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
    confirmBtn.disabled = true;
    const formData = new FormData();
    formData.append('action', 'add_trade');
    formData.append('operation_type', operationType);
    formData.append('platform_id', platformId);
    formData.append('from_platform_id', fromPlatformId);
    formData.append('asset_id', assetId);
    formData.append('quantity', quantity);
    formData.append('price', price);
    formData.append('price_currency', priceCurrency);
    formData.append('commission', commission);
    formData.append('commission_currency', commissionCurrency);
    formData.append('network', network);
    formData.append('operation_date', date);
    formData.append('notes', notes);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeTradeModal();
            setTimeout(() => location.reload(), 1500);
        } else showNotification('error', 'Ошибка', result.message);
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос'); }
    finally { confirmBtn.innerHTML = originalText; confirmBtn.disabled = false; }
}

function openTransferModal() {
    const modal = document.getElementById('transferModal');
    if (modal) {
        modal.classList.add('active');
        document.getElementById('transferDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('transferAmount').value = '';
        document.getElementById('transferCommission').value = '';
        document.getElementById('selectedAssetDisplay').textContent = 'Выбрать';
        document.getElementById('selectedFromPlatformDisplay').textContent = 'Выбрать площадку';
        document.getElementById('selectedToPlatformDisplay').textContent = 'Выбрать площадку';
        document.getElementById('selectedCommissionCurrencyDisplay').textContent = 'Выбрать';
        document.getElementById('transferAssetId').value = '';
        document.getElementById('transferFromPlatformId').value = '';
        document.getElementById('transferToPlatformId').value = '';
        document.getElementById('transferCommissionCurrency').value = '';
        document.getElementById('transferNetworkFrom').value = '';
        document.getElementById('transferNetworkTo').value = '';
        document.getElementById('transferNotes').value = '';
        document.getElementById('transferCryptoNetworkSection').style.display = 'none';
        hidePlatformBalance();
        currentPlatformBalanceData = null;
    }
}

function closeTransferModal() {
    const modal = document.getElementById('transferModal');
    if (modal) modal.classList.remove('active');
    hidePlatformBalance();
}

function selectFromPlatform(id, name) {
    selectedFromPlatform = { id, name };
    document.getElementById('selectedFromPlatformDisplay').textContent = name;
    document.getElementById('transferFromPlatformId').value = id;
    loadPlatformBalance(id, name);
}

function selectToPlatform(id, name) {
    selectedToPlatform = { id, name };
    document.getElementById('selectedToPlatformDisplay').textContent = name;
    document.getElementById('transferToPlatformId').value = id;
}

function selectAsset(id, symbol) {
    selectedTransferAsset = { id, symbol };
    document.getElementById('selectedAssetDisplay').textContent = symbol;
    document.getElementById('transferAssetId').value = id;
    const asset = assetsData.find(a => a.id == id);
    const cryptoSection = document.getElementById('transferCryptoNetworkSection');
    if (cryptoSection) cryptoSection.style.display = asset && asset.type === 'crypto' ? 'block' : 'none';
}

function selectCommissionCurrency(code) {
    selectedCommissionCurrency = { code };
    document.getElementById('selectedCommissionCurrencyDisplay').textContent = code;
    document.getElementById('transferCommissionCurrency').value = code;
}

async function confirmTransfer() {
    const fromPlatformId = document.getElementById('transferFromPlatformId').value;
    const toPlatformId = document.getElementById('transferToPlatformId').value;
    const assetId = document.getElementById('transferAssetId').value;
    const quantity = getNumericValue(document.getElementById('transferAmount').value);
    const commission = getNumericValue(document.getElementById('transferCommission').value) || 0;
    const commissionCurrency = document.getElementById('transferCommissionCurrency').value.toUpperCase() || '';
    const fromNetwork = document.getElementById('transferNetworkFrom')?.value || '';
    const toNetwork = document.getElementById('transferNetworkTo')?.value || '';
    const date = document.getElementById('transferDate').value;
    const notes = document.getElementById('transferNotes').value;
    if (!fromPlatformId) { showNotification('error', 'Ошибка', 'Выберите площадку отправителя'); return; }
    if (!toPlatformId) { showNotification('error', 'Ошибка', 'Выберите площадку получателя'); return; }
    if (!assetId) { showNotification('error', 'Ошибка', 'Выберите актив'); return; }
    if (!quantity || quantity <= 0) { showNotification('error', 'Ошибка', 'Введите количество'); return; }
    if (commission > 0 && !commissionCurrency) { showNotification('error', 'Ошибка', 'Выберите валюту комиссии'); return; }
    const asset = assetsData.find(a => a.id == assetId);
    const assetType = asset ? asset.type : null;
    if (assetType === 'crypto') {
        if (!fromNetwork) { showNotification('error', 'Ошибка', 'Укажите сеть отправителя'); return; }
        if (!toNetwork) { showNotification('error', 'Ошибка', 'Укажите сеть получателя'); return; }
    }
    const confirmBtn = document.getElementById('confirmTransferBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
    confirmBtn.disabled = true;
    const formData = new FormData();
    formData.append('action', 'add_transfer');
    formData.append('from_platform_id', fromPlatformId);
    formData.append('to_platform_id', toPlatformId);
    formData.append('asset_id', assetId);
    formData.append('quantity', quantity);
    formData.append('commission', commission);
    formData.append('commission_currency', commissionCurrency);
    formData.append('from_network', fromNetwork);
    formData.append('to_network', toNetwork);
    formData.append('transfer_date', date);
    formData.append('notes', notes);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeTransferModal();
            setTimeout(() => location.reload(), 1500);
        } else showNotification('error', 'Ошибка', result.message);
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос'); }
    finally { confirmBtn.innerHTML = originalText; confirmBtn.disabled = false; }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ БАЛАНСА ПЛОЩАДКИ
// ============================================================================

let currentPlatformBalanceData = null;

async function loadPlatformBalance(platformId, platformName) {
    if (!platformId) return;
    const balanceBlock = document.getElementById('transferFromPlatformBalance');
    const assetsList = document.getElementById('transferPlatformAssetsList');
    const totalValueSpan = document.getElementById('transferPlatformTotalValue');
    const totalDiv = document.getElementById('transferPlatformTotal');
    const totalUsdSpan = document.getElementById('transferPlatformTotalUsd');
    const balanceTitle = document.getElementById('platformBalanceTitle');
    if (!balanceBlock) return;
    if (balanceTitle) balanceTitle.innerHTML = `<i class="fas fa-wallet"></i> Баланс: ${platformName}`;
    balanceBlock.style.display = 'block';
    assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #6b7a8f;"><i class="fas fa-spinner fa-spin"></i> Загрузка баланса...</div>';
    totalDiv.style.display = 'none';
    const formData = new FormData();
    formData.append('action', 'get_platform_balance');
    formData.append('platform_id', platformId);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success && result.assets) {
            const assets = result.assets;
            const totalUsd = result.total_value_usd;
            const totalRub = result.total_value_rub;
            let totalUsdStr = totalUsd.toFixed(2);
            let totalUsdParts = totalUsdStr.split('.');
            totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
            let totalRubStr = Math.round(totalRub).toString();
            totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            if (totalValueSpan) totalValueSpan.innerHTML = `<i class="fas fa-chart-line"></i> ${totalUsdFormatted} $ / ${totalRubStr} ₽`;
            if (assets.length === 0) {
                assetsList.innerHTML = `<div style="text-align: center; padding: 20px; color: #6b7a8f;"><i class="fas fa-box-open"></i> Нет активов на площадке ${platformName}</div>`;
                if (totalDiv) totalDiv.style.display = 'none';
            } else {
                let html = '';
                assets.forEach(asset => {
                    const quantity = parseFloat(asset.quantity);
                    const valueUsd = parseFloat(asset.value_usd);
                    const valueRub = valueUsd * usdRubRate;
                    let quantityFormatted = '';
                    if (asset.asset_type === 'crypto') {
                        if (Math.floor(quantity) === quantity) quantityFormatted = quantity.toLocaleString('ru-RU').replace(/,/g, ' ');
                        else {
                            let str = quantity.toFixed(6).replace(/\.?0+$/, '');
                            let parts = str.split('.');
                            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
                        }
                    } else {
                        let str = quantity.toFixed(2).replace(/\.?0+$/, '');
                        let parts = str.split('.');
                        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                        quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
                    }
                    let usdStr = valueUsd.toFixed(2);
                    let usdParts = usdStr.split('.');
                    usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
                    let rubStr = Math.round(valueRub).toString();
                    rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    const assetIcon = getAssetIcon(asset.symbol);
                    html += `<div class="platform-asset-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; margin-right:20px; border-bottom: 1px solid var(--border-color, #edf2f7); cursor: pointer;" onclick="selectTransferAssetFromBalance('${asset.asset_id}', '${asset.symbol}', '${asset.asset_type}', '${quantityFormatted}')" onmouseover="this.style.background='var(--bg-tertiary)'; this.style.borderRadius='8px'; this.style.paddingLeft='8px'; this.style.paddingRight='8px';" onmouseout="this.style.background='transparent'; this.style.paddingLeft='0'; this.style.paddingRight='0';"><div style="display: flex; align-items: center; gap: 10px;"><div style="width: 28px; height: 28px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;"><i class="${assetIcon.icon}" style="color: ${assetIcon.color}; font-size: 14px;"></i></div><div><div style="font-weight: 500; font-size: 13px;">${asset.symbol}</div><div style="font-size: 10px; color: #6b7a8f;">${quantityFormatted}</div></div></div><div style="text-align: right;"><div style="font-size: 12px; font-weight: 500;">$${usdFormatted}</div><div style="font-size: 10px; color: #6b7a8f;">${rubStr} ₽</div></div></div>`;
                });
                assetsList.innerHTML = html;
                if (totalDiv) totalDiv.style.display = 'block';
                if (totalUsdSpan) totalUsdSpan.innerHTML = `$${totalUsdFormatted} (${totalRubStr} ₽)`;
            }
            currentPlatformBalanceData = { platformId: platformId, platformName: platformName, assets: assets, totalUsd: totalUsd, totalRub: totalRub };
        } else { assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #e53e3e;">Ошибка загрузки баланса</div>'; if (totalDiv) totalDiv.style.display = 'none'; }
    } catch (error) { assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #e53e3e;">Ошибка загрузки</div>'; if (totalDiv) totalDiv.style.display = 'none'; }
}

function selectTransferAssetFromBalance(assetId, symbol, assetType, quantityFormatted) {
    selectAsset(assetId, symbol);
}

function hidePlatformBalance() {
    const balanceBlock = document.getElementById('transferFromPlatformBalance');
    if (balanceBlock) balanceBlock.style.display = 'none';
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
// ФУНКЦИИ ДЛЯ СЕТЕЙ
// ============================================================================

function selectTradeNetwork(name) {
    selectedTradeNetwork = { name };
    document.getElementById('selectedTradeNetworkDisplay').textContent = name;
    document.getElementById('tradeNetwork').value = name;
}

function selectFromNetwork(name) {
    selectedFromNetwork = { name };
    document.getElementById('selectedFromNetworkDisplay').textContent = name;
    document.getElementById('transferNetworkFrom').value = name;
}

function selectToNetwork(name) {
    selectedToNetwork = { name };
    document.getElementById('selectedToNetworkDisplay').textContent = name;
    document.getElementById('transferNetworkTo').value = name;
}

function openNetworkModal(context, currentNetwork = '') {
    setModalContext('transfer', 'network', context);
    const modalTitle = document.querySelector('#networkSelectModal .modal-header h2');
    let titleText = 'Выберите сеть';
    if (context === 'from') titleText = 'Выберите сеть отправителя';
    else if (context === 'to') titleText = 'Выберите сеть получателя';
    modalTitle.innerHTML = `<i class="fas fa-network-wired" style="color: #ff9f4a;"></i> ${titleText}`;
    const modal = document.getElementById('networkSelectModal');
    if (modal) {
        filterNetworksForSelect('');
        modal.classList.add('active');
        setTimeout(() => document.getElementById('networkSearch')?.focus(), 100);
    }
}

function closeNetworkModal() {
    const modal = document.getElementById('networkSelectModal');
    if (modal) {
        modal.classList.remove('active');
        document.getElementById('networkSearch').value = '';
    }
}

function filterNetworksForSelect(searchText) {
    const listContainer = document.getElementById('allNetworksList');
    if (!listContainer) return;
    const searchTextLower = searchText.toLowerCase().trim();
    const originalSearchText = searchText.trim();
    let networksToShow = networksData;
    if (searchTextLower) networksToShow = networksData.filter(n => n.name.toLowerCase().includes(searchTextLower) || (n.full_name && n.full_name.toLowerCase().includes(searchTextLower)));
    if (networksToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `<div onclick="addNewNetworkFromModal('${originalSearchText.replace(/'/g, "\\'")}')" style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: #ff9f4a; transition: all 0.2s;" onmouseover="this.style.background='#f0f3f7'" onmouseout="this.style.background='transparent'"><i class="fas fa-plus-circle" style="margin-right: 8px;"></i>Добавить сеть "${originalSearchText.toUpperCase()}"</div>`;
        return;
    }
    listContainer.innerHTML = networksToShow.map(network => {
        let iconHtml = `<i class="${network.icon}"></i>`;
        return `<div onclick="selectNetworkFromModal('${network.name.replace(/'/g, "\\'")}')" style="padding: 12px; cursor: pointer; border-radius: 10px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; border: 1px solid transparent;" onmouseover="this.style.background='var(--bg-tertiary)'; this.style.borderColor='#e0e6ed'" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'"><div style="width: 36px; height: 36px; background: ${network.color}20; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${network.color};">${iconHtml}</div><div style="flex: 1;"><div class="asset-symbol">${network.name}</div><div style="font-size: 12px; color: #6b7a8f;">${network.full_name || network.name}</div></div><i class="fas fa-chevron-right" style="color: #95a5a6; font-size: 12px;"></i></div>`;
    }).join('');
}

function selectNetworkFromModal(networkName) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    if (context === 'transfer') {
        if (subMode === 'from') selectFromNetwork(networkName);
        else if (subMode === 'to') selectToNetwork(networkName);
    } else if (context === 'trade') selectTradeNetwork(networkName);
    closeNetworkModal();
}

function addNewNetworkFromModal(networkName) {
    if (!networkName) return;
    const newNetworkName = networkName.trim().toUpperCase();
    const exists = networksData.some(n => n.name === newNetworkName);
    if (exists) {
        selectNetworkFromModal(newNetworkName);
        return;
    }
    openAddNetworkModal(newNetworkName);
}

function openAddNetworkModal(networkName) {
    const modal = document.getElementById('addNetworkModal');
    const nameInput = document.getElementById('newNetworkName');
    const fullNameInput = document.getElementById('newNetworkFullName');
    const colorInput = document.getElementById('newNetworkColor');
    if (nameInput) nameInput.value = networkName.toUpperCase();
    if (fullNameInput) fullNameInput.value = '';
    if (colorInput) colorInput.value = '#ff9f4a';
    updateNetworkPreview(networkName.toUpperCase(), '');
    if (fullNameInput) fullNameInput.oninput = function() { updateNetworkPreview(nameInput.value, this.value); };
    if (nameInput) nameInput.oninput = function() { updateNetworkPreview(this.value, fullNameInput ? fullNameInput.value : ''); };
    closeNetworkModal();
    if (modal) modal.classList.add('active');
}

function closeAddNetworkModal() {
    const modal = document.getElementById('addNetworkModal');
    if (modal) modal.classList.remove('active');
}

function updateNetworkPreview(name, fullName) {
    const previewIcon = document.getElementById('previewNetworkIcon');
    const previewName = document.getElementById('previewNetworkName');
    const previewFullName = document.getElementById('previewNetworkFullName');
    if (!previewName) return;
    let icon = 'fas fa-network-wired';
    const upperName = name.toUpperCase();
    if (upperName.includes('ERC')) icon = 'fab fa-ethereum';
    else if (upperName.includes('BEP')) icon = 'fas fa-bolt';
    else if (upperName.includes('TRC')) icon = 'fab fa-t';
    else if (upperName === 'SOL') icon = 'fas fa-sun';
    else if (upperName === 'BTC') icon = 'fab fa-bitcoin';
    if (previewIcon) previewIcon.innerHTML = `<i class="${icon}"></i>`;
    previewName.textContent = name || 'Название сети';
    previewFullName.textContent = fullName || 'Полное название';
}

async function saveNewNetwork() {
    const networkName = document.getElementById('newNetworkName').value.toUpperCase();
    const networkFullName = document.getElementById('newNetworkFullName').value.trim();
    const networkColor = document.getElementById('newNetworkColor').value;
    if (!networkName) { showNotification('error', 'Ошибка', 'Введите аббревиатуру сети'); return; }
    const fullName = networkFullName || networkName;
    let icon = 'fas fa-network-wired';
    const upperName = networkName.toUpperCase();
    if (upperName.includes('ERC')) icon = 'fab fa-ethereum';
    else if (upperName.includes('BEP')) icon = 'fas fa-bolt';
    else if (upperName.includes('TRC')) icon = 'fab fa-t';
    else if (upperName === 'SOL') icon = 'fas fa-sun';
    else if (upperName === 'BTC') icon = 'fab fa-bitcoin';
    const formData = new FormData();
    formData.append('action', 'add_network');
    formData.append('name', networkName);
    formData.append('icon', icon);
    formData.append('color', networkColor);
    formData.append('full_name', fullName);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            networksData.push({ id: result.network_id, name: networkName, icon: icon, color: networkColor, full_name: fullName });
            const context = currentModalContext.source;
            if (context === 'transfer') {
                if (currentModalContext.subMode === 'from') selectFromNetwork(networkName);
                else if (currentModalContext.subMode === 'to') selectToNetwork(networkName);
            } else if (context === 'trade') selectTradeNetwork(networkName);
            closeAddNetworkModal();
            showNotification('success', 'Успешно', `Сеть ${networkName} добавлена`);
        } else showNotification('error', 'Ошибка', 'Не удалось добавить сеть');
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось добавить сеть'); }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ РАСХОДОВ
// ============================================================================

function openExpenseModal() {
    const modal = document.getElementById('expenseModal');
    if (modal) {
        modal.classList.add('active');
        document.getElementById('expenseDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('expenseAmount').value = '';
        document.getElementById('expenseDescription').value = '';
        document.getElementById('selectedExpenseCurrencyDisplay').textContent = 'RUB';
        document.getElementById('expenseCategoryId').value = '';
        loadExpenseCategories();
    }
}

function closeExpenseModal() {
    const modal = document.getElementById('expenseModal');
    if (modal) modal.classList.remove('active');
}

async function loadExpenseCategories() {
    const container = document.getElementById('expenseCategoriesList');
    if (!container) return;
    container.innerHTML = '<div style="text-align: center; padding: 10px;"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
    try {
        const formData = new FormData();
        formData.append('action', 'get_expense_categories');
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success && result.categories) {
            let html = '';
            result.categories.forEach(cat => {
                html += `<button type="button" class="expense-category-btn" data-category-id="${cat.id}" style="flex: 1 1 auto; min-width: 80px; padding: 10px 12px; background: ${cat.color}20; border: 1px solid ${cat.color}; border-radius: 12px; font-size: 13px; font-weight: 500; color: ${cat.color}; cursor: pointer; text-align: center;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'" onclick="selectExpenseCategory(${cat.id}, this)"><i class="${cat.icon}"></i> ${cat.name_ru}</button>`;
            });
            container.innerHTML = html;
        } else container.innerHTML = '<div style="text-align: center; padding: 10px; color: #e53e3e;">Нет категорий расходов</div>';
    } catch (error) { container.innerHTML = '<div style="text-align: center; padding: 10px; color: #e53e3e;">Ошибка загрузки категорий</div>'; }
}

function selectExpenseCategory(categoryId, btn) {
    document.querySelectorAll('.expense-category-btn').forEach(b => {
        b.style.background = '';
        b.style.fontWeight = '500';
    });
    btn.style.background = btn.style.borderColor + '30';
    btn.style.fontWeight = '600';
    document.getElementById('expenseCategoryId').value = categoryId;
}

async function saveExpense() {
    const amount = parseFloat(document.getElementById('expenseAmount').value.replace(/\s/g, '').replace(',', '.')) || 0;
    const currencyCode = document.getElementById('selectedExpenseCurrencyDisplay').textContent;
    const categoryId = document.getElementById('expenseCategoryId').value;
    const description = document.getElementById('expenseDescription').value;
    const expenseDate = document.getElementById('expenseDate').value;
    if (amount <= 0) { showNotification('error', 'Ошибка', 'Введите корректную сумму'); return; }
    if (!categoryId) { showNotification('error', 'Ошибка', 'Выберите категорию расхода'); return; }
    const formData = new FormData();
    formData.append('action', 'add_expense');
    formData.append('amount', amount);
    formData.append('currency_code', currencyCode);
    formData.append('category_id', categoryId);
    formData.append('description', description);
    formData.append('expense_date', expenseDate);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeExpenseModal();
        } else showNotification('error', 'Ошибка', result.message);
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось сохранить расход'); }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ЛИМИТНЫХ ОРДЕРОВ
// ============================================================================

let selectedLimitPlatform = { id: null, name: '' };
let selectedLimitAsset = { id: null, symbol: '' };
let selectedLimitCurrency = 'USD';

function openLimitOrderModal() {
    const modal = document.getElementById('limitOrderModal');
    if (modal) {
        modal.classList.add('active');
        document.getElementById('limitQuantity').value = '';
        document.getElementById('limitPrice').value = '';
        document.getElementById('limitExpiryDate').value = '';
        document.getElementById('limitNotes').value = '';
        document.getElementById('selectedLimitPlatformDisplay').textContent = 'Выбрать площадку';
        document.getElementById('selectedLimitAssetDisplay').textContent = 'Выбрать актив';
        document.getElementById('selectedLimitCurrencyDisplay').textContent = 'Выбрать';
        document.getElementById('limitPlatformId').value = '';
        document.getElementById('limitAssetId').value = '';
        document.getElementById('limitCurrency').value = '';
        document.getElementById('limitTotalEstimate').textContent = '0';
        document.querySelectorAll('.limit-type-btn').forEach(btn => { btn.style.opacity = '0.7'; });
        document.querySelector('.limit-type-btn[data-type="buy"]').style.opacity = '1';
        document.querySelector('.limit-type-btn[data-type="buy"]').style.border = '2px solid white';
    }
}

function closeLimitOrderModal() {
    const modal = document.getElementById('limitOrderModal');
    if (modal) modal.classList.remove('active');
}

function selectLimitPlatform(id, name) {
    selectedLimitPlatform = { id, name };
    document.getElementById('selectedLimitPlatformDisplay').textContent = name;
    document.getElementById('limitPlatformId').value = id;
}

function selectLimitAsset(id, symbol) {
    selectedLimitAsset = { id, symbol };
    document.getElementById('selectedLimitAssetDisplay').textContent = symbol;
    document.getElementById('limitAssetId').value = id;
    updateLimitTotalEstimate();
}

function selectLimitCurrency(code) {
    selectedLimitCurrency = code;
    document.getElementById('selectedLimitCurrencyDisplay').textContent = code;
    document.getElementById('limitCurrency').value = code;
    updateLimitTotalEstimate();
}

function updateLimitTotalEstimate() {
    const quantity = parseFloat(document.getElementById('limitQuantity').value.replace(/\s/g, '')) || 0;
    const price = parseFloat(document.getElementById('limitPrice').value.replace(/\s/g, '')) || 0;
    const currency = document.getElementById('limitCurrency').value;
    const total = quantity * price;
    if (total > 0) {
        let formattedTotal = total.toFixed(2);
        if (currency === 'BTC' || currency === 'ETH') formattedTotal = total.toFixed(6);
        document.getElementById('limitTotalEstimate').textContent = `${formattedTotal} ${currency}`;
    } else document.getElementById('limitTotalEstimate').textContent = `0 ${currency}`;
}

async function confirmLimitOrder() {
    const operationType = document.querySelector('.limit-type-btn[style*="opacity: 1"]')?.dataset.type || 'buy';
    const platformId = document.getElementById('limitPlatformId').value;
    const assetId = document.getElementById('limitAssetId').value;
    const quantity = parseFloat(document.getElementById('limitQuantity').value.replace(/\s/g, '')) || 0;
    const limitPrice = parseFloat(document.getElementById('limitPrice').value.replace(/\s/g, '')) || 0;
    const priceCurrency = document.getElementById('limitCurrency').value;
    const expiryDate = document.getElementById('limitExpiryDate').value;
    const notes = document.getElementById('limitNotes').value;
    if (!platformId) { showNotification('error', 'Ошибка', 'Выберите площадку'); return; }
    if (!assetId) { showNotification('error', 'Ошибка', 'Выберите актив'); return; }
    if (!quantity || quantity <= 0) { showNotification('error', 'Ошибка', 'Введите количество'); return; }
    if (!limitPrice || limitPrice <= 0) { showNotification('error', 'Ошибка', 'Введите цену'); return; }
    const formData = new FormData();
    formData.append('action', 'add_limit_order');
    formData.append('operation_type', operationType);
    formData.append('platform_id', platformId);
    formData.append('asset_id', assetId);
    formData.append('quantity', quantity);
    formData.append('limit_price', limitPrice);
    formData.append('price_currency', priceCurrency);
    formData.append('expiry_date', expiryDate);
    formData.append('notes', notes);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showNotification('success', 'Успешно', 'Лимитный ордер создан');
            closeLimitOrderModal();
            setTimeout(() => location.reload(), 1500);
        } else showNotification('error', 'Ошибка', result.message);
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос'); }
}

function showExecuteConfirmation(orderId) {
    showNotification('info', 'В разработке', 'Исполнение ордера будет добавлено позже');
}

function showCancelConfirmation(orderId) {
    showNotification('info', 'В разработке', 'Отмена ордера будет добавлена позже');
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ЗАМЕТОК
// ============================================================================

function openAddNoteModal() {
    const modal = document.getElementById('noteModal');
    if (modal) {
        document.getElementById('noteModalTitleText').textContent = 'Добавить заметку';
        document.getElementById('confirmNoteBtnText').textContent = 'Сохранить';
        document.getElementById('noteId').value = '';
        document.getElementById('noteTitle').value = '';
        document.getElementById('noteContent').value = '';
        document.getElementById('noteType').value = 'general';
        document.getElementById('noteReminderDate').value = '';
        document.getElementById('reminderDateGroup').style.display = 'none';
        document.querySelectorAll('.note-type-option').forEach(btn => btn.classList.remove('active'));
        document.querySelector('.note-type-option[data-type="general"]')?.classList.add('active');
        modal.classList.add('active');
    }
}

function openEditNoteModal(noteId, title, content, type, reminderDate) {
    currentNoteId = noteId;
    document.getElementById('noteModalTitleText').textContent = 'Редактировать заметку';
    document.getElementById('confirmNoteBtnText').textContent = 'Обновить';
    document.getElementById('noteId').value = noteId;
    document.getElementById('noteTitle').value = title || '';
    document.getElementById('noteContent').value = content;
    document.getElementById('noteType').value = type;
    document.getElementById('noteReminderDate').value = reminderDate || '';
    document.querySelectorAll('.note-type-option').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.querySelector(`.note-type-option[data-type="${type}"]`);
    if (activeBtn) activeBtn.classList.add('active');
    document.getElementById('reminderDateGroup').style.display = type === 'reminder' ? 'block' : 'none';
    document.getElementById('noteModal').classList.add('active');
}

function closeNoteModal() {
    const modal = document.getElementById('noteModal');
    if (modal) modal.classList.remove('active');
}

async function saveNote() {
    const noteId = document.getElementById('noteId').value;
    const title = document.getElementById('noteTitle').value;
    const content = document.getElementById('noteContent').value;
    const type = document.getElementById('noteType').value;
    const reminderDate = document.getElementById('noteReminderDate').value;
    if (!content.trim()) { showNotification('error', 'Ошибка', 'Введите содержание заметки'); return; }
    const action = noteId ? 'update_note' : 'add_note';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('title', title);
    formData.append('content', content);
    formData.append('note_type', type);
    if (reminderDate) formData.append('reminder_date', reminderDate);
    if (noteId) formData.append('note_id', noteId);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeNoteModal();
            loadNotes();
        } else showNotification('error', 'Ошибка', result.message);
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось сохранить заметку'); }
}

async function loadNotes() {
    const container = document.getElementById('notesList');
    if (!container) return;
    container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
    const formData = new FormData();
    formData.append('action', 'get_notes');
    formData.append('include_archived', 0);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success && container) {
            if (result.notes.length === 0) {
                container.innerHTML = `<div style="margin-bottom: 15px; text-align: center;"><button class="add-order-btn" onclick="openAddNoteModal()" style="margin-top: 10px;"><i class="fas fa-plus-circle"></i> Создать заметку</button></div><div class="order-empty"><i class="fas fa-sticky-note"></i><p>Нет заметок</p></div>`;
            } else {
                let html = `<div style="margin-bottom: 15px; text-align: center;"><button class="add-order-btn" onclick="openAddNoteModal()" style="margin-top: 10px;"><i class="fas fa-plus-circle"></i> Создать заметку</button></div>`;
                result.notes.forEach(note => {
                    const date = new Date(note.created_at).toLocaleDateString('ru-RU');
                    const reminderIcon = note.reminder_date ? `📅 ${new Date(note.reminder_date).toLocaleDateString('ru-RU')}` : '';
                    html += `<div class="note-item ${note.note_type || 'general'}" data-note-id="${note.id}"><div class="note-header"><div>${note.title ? `<div class="note-title">${escapeHtml(note.title)}</div>` : ''}<div class="note-date"><i class="far fa-calendar-alt"></i> ${date}${reminderIcon ? `<span style="margin-left: 8px;">${reminderIcon}</span>` : ''}</div></div></div><div class="note-content">${escapeHtml(note.content)}</div><div class="note-actions"><button class="note-action-btn edit" onclick="openEditNoteModal(${note.id}, '${escapeHtml(note.title || '').replace(/'/g, "\\'")}', '${escapeHtml(note.content).replace(/'/g, "\\'")}', '${note.note_type}', '${note.reminder_date || ''}')"><i class="fas fa-edit"></i></button><button class="note-action-btn archive" onclick="archiveNote(${note.id}, true)"><i class="fas fa-archive"></i></button><button class="note-action-btn delete" onclick="deleteNote(${note.id}, '${escapeHtml(note.title || 'Без заголовка').replace(/'/g, "\\'")}')"><i class="fas fa-trash-alt"></i></button></div></div>`;
                });
                container.innerHTML = html;
            }
        } else container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Не удалось загрузить заметки</div>';
    } catch (error) { container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Ошибка загрузки заметок</div>'; }
}

async function archiveNote(noteId, archive) {
    const formData = new FormData();
    formData.append('action', 'archive_note');
    formData.append('note_id', noteId);
    formData.append('archive', archive ? 1 : 0);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            loadNotes();
        } else showNotification('error', 'Ошибка', result.message);
    } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось выполнить операцию'); }
}

async function deleteNote(noteId, noteTitle) {
    if (confirm('Вы уверены, что хотите безвозвратно удалить эту заметку?')) {
        const formData = new FormData();
        formData.append('action', 'delete_note');
        formData.append('note_id', noteId);
        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                showNotification('success', 'Успешно', result.message);
                loadNotes();
            } else showNotification('error', 'Ошибка', result.message);
        } catch (error) { showNotification('error', 'Ошибка сети', 'Не удалось удалить заметку'); }
    }
}

function openArchivedNotesModal() {
    const modal = document.getElementById('archivedNotesModal');
    if (modal) {
        modal.classList.add('active');
        loadArchivedNotes();
    }
}

function closeArchivedNotesModal() {
    const modal = document.getElementById('archivedNotesModal');
    if (modal) modal.classList.remove('active');
}

async function loadArchivedNotes() {
    const container = document.getElementById('archivedNotesList');
    if (!container) return;
    container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
    const formData = new FormData();
    formData.append('action', 'get_notes');
    formData.append('include_archived', 1);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            if (result.notes.length === 0) {
                container.innerHTML = `<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-archive" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i><p style="color: #6b7a8f;">Нет архивных заметок</p></div>`;
            } else {
                let html = '';
                result.notes.forEach(note => {
                    const date = new Date(note.created_at).toLocaleDateString('ru-RU');
                    const archivedDate = note.updated_at ? new Date(note.updated_at).toLocaleDateString('ru-RU') : date;
                    html += `<div class="archived-note-item" data-note-id="${note.id}"><div class="archived-note-header"><div class="archived-note-title">${note.title ? escapeHtml(note.title) : 'Без заголовка'}</div><div class="archived-note-date"><i class="far fa-calendar-alt"></i> ${date}<span style="margin-left: 8px;">📦 ${archivedDate}</span></div></div><div class="archived-note-content">${escapeHtml(note.content)}</div><div class="archived-note-actions"><button class="note-action-btn restore" onclick="archiveNote(${note.id}, false)"><i class="fas fa-undo-alt"></i> Восстановить</button><button class="note-action-btn delete" onclick="deleteNote(${note.id}, '${escapeHtml(note.title || 'Без заголовка').replace(/'/g, "\\'")}')"><i class="fas fa-trash-alt"></i> Удалить</button></div></div>`;
                });
                container.innerHTML = html;
            }
        } else container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Не удалось загрузить архивные заметки</div>';
    } catch (error) { container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Ошибка загрузки</div>'; }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ МОДАЛЬНЫХ ОКОН АКТИВОВ (ПЛОЩАДКИ, СЕТИ, СЕКТОРА, КРИПТО)
// ============================================================================

function openPlatformAssetsModal(platformId, platformName) {
    const modal = document.getElementById('platformAssetsModal');
    const titleSpan = document.getElementById('platformAssetsName');
    const body = document.getElementById('platformAssetsBody');
    if (!modal || !body) return;
    titleSpan.textContent = platformName;
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    modal.classList.add('active');
    const platformData = platformAssetsData ? platformAssetsData[platformId] : null;
    if (!platformData || !platformData.assets || platformData.assets.length === 0) {
        body.innerHTML = `<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i><p style="color: #6b7a8f;">На площадке "${platformName}" нет активов</p></div>`;
        return;
    }
    const assets = [...platformData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    let totalValueUsd = 0;
    assets.forEach(asset => totalValueUsd += parseFloat(asset.value_usd) || 0);
    const totalValueRub = totalValueUsd * usdRubRate;
    let html = `<table class="platform-assets-table"><thead><tr><th>Актив</th><th style="text-align: right;">Количество</th><th style="text-align: right;">Средняя цена</th><th style="text-align: right;">Стоимость</th></tr></thead><tbody>`;
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        let quantityFormatted = '';
        if (asset.asset_type === 'crypto') {
            if (Math.floor(quantityNum) === quantityNum) quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
            else {
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
        let rubStr = Math.round(valueUsdNum * usdRubRate).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        const assetIcon = getAssetIcon(asset.symbol);
        html += `<tr><td><div style="display: flex; align-items: center; gap: 10px;"><div style="width: 32px; height: 32px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;"><i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i></div><div><div class="platform-assets-symbol">${asset.symbol}</div><div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div></div></div></td><td class="platform-assets-quantity">${quantityFormatted}</td><td class="platform-assets-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td><td class="platform-assets-value">${valueFormatted}</td></tr>`;
    });
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    html += `</tbody></table><div class="platform-assets-summary"><div class="platform-assets-summary-row"><span style="font-weight: 600;">Всего активов:</span><span style="font-weight: 600;">${assets.length}</span></div><div class="platform-assets-summary-row"><span>Общая стоимость:</span><span class="platform-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span></div></div>`;
    body.innerHTML = html;
}

function closePlatformAssetsModal() {
    const modal = document.getElementById('platformAssetsModal');
    if (modal) modal.classList.remove('active');
}

function openNetworkAssetsModal(networkName) {
    const modal = document.getElementById('networkAssetsModal');
    const titleSpan = document.getElementById('networkAssetsName');
    const body = document.getElementById('networkAssetsBody');
    if (!modal || !body) return;
    titleSpan.textContent = networkName;
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    modal.classList.add('active');
    const networkData = networkAssetsData ? networkAssetsData[networkName] : null;
    if (!networkData || !networkData.assets || networkData.assets.length === 0) {
        body.innerHTML = `<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i><p style="color: #6b7a8f;">В сети "${networkName}" нет активов</p></div>`;
        return;
    }
    const assets = [...networkData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    let totalValueUsd = networkData.total_value_usd;
    const totalValueRub = totalValueUsd * usdRubRate;
    let html = `<table class="network-assets-table"><thead><tr><th>Актив</th><th style="text-align: right;">Количество</th><th style="text-align: right;">Средняя цена</th><th style="text-align: right;">Стоимость</th></tr></thead><tbody>`;
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        let quantityFormatted = '';
        if (Math.floor(quantityNum) === quantityNum) quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
        else {
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
        let rubStr = Math.round(valueUsdNum * usdRubRate).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        const assetIcon = getAssetIcon(asset.symbol);
        html += `<tr><td><div style="display: flex; align-items: center; gap: 10px;"><div style="width: 32px; height: 32px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;"><i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i></div><div><div class="network-assets-symbol">${asset.symbol}</div><div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div></div></div></td><td class="network-assets-quantity" style="font-family: monospace; white-space: nowrap;">${quantityFormatted}</td><td class="network-assets-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td><td class="network-assets-value">${valueFormatted}</td></tr>`;
    });
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    html += `</tbody></table><div class="network-assets-summary"><div class="network-assets-summary-row"><span style="font-weight: 600;">Всего активов:</span><span style="font-weight: 600;">${assets.length}</span></div><div class="network-assets-summary-row"><span>Общая стоимость в сети ${networkName}:</span><span class="network-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span></div></div>`;
    body.innerHTML = html;
}

function closeNetworkAssetsModal() {
    const modal = document.getElementById('networkAssetsModal');
    if (modal) modal.classList.remove('active');
}

function openSectorAssetsModal(sectorName, displayName) {
    const modal = document.getElementById('sectorAssetsModal');
    const titleSpan = document.getElementById('sectorAssetsName');
    const body = document.getElementById('sectorAssetsBody');
    if (!modal || !body) return;
    titleSpan.textContent = displayName;
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    modal.classList.add('active');
    const sectorData = sectorAssetsData ? sectorAssetsData[sectorName] : null;
    if (!sectorData || !sectorData.assets || sectorData.assets.length === 0) {
        body.innerHTML = `<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i><p style="color: #6b7a8f;">В секторе "${displayName}" нет активов</p></div>`;
        return;
    }
    const assets = [...sectorData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    let totalValueUsd = sectorData.total_value_usd;
    const totalValueRub = totalValueUsd * usdRubRate;
    let html = `<table class="sector-assets-table"><thead><tr><th>Актив</th><th style="text-align: right;">Количество</th><th style="text-align: right;">Средняя цена</th><th style="text-align: right;">Стоимость</th></tr></thead><tbody>`;
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        let quantityFormatted = '';
        if (asset.asset_type === 'crypto') {
            if (Math.floor(quantityNum) === quantityNum) quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
            else {
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
        let rubStr = Math.round(valueUsdNum * usdRubRate).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        const assetIcon = getAssetIcon(asset.symbol);
        html += `<tr><td><div style="display: flex; align-items: center; gap: 10px;"><div style="width: 32px; height: 32px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;"><i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i></div><div><div class="sector-assets-symbol">${asset.symbol}</div><div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div></div></div></td><td class="sector-assets-quantity">${quantityFormatted}</td><td class="sector-assets-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td><td class="sector-assets-value">${valueFormatted}</td></tr>`;
    });
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    html += `</tbody></table><div class="sector-assets-summary"><div class="sector-assets-summary-row"><span style="font-weight: 600;">Всего активов:</span><span style="font-weight: 600;">${assets.length}</span></div><div class="sector-assets-summary-row"><span>Общая стоимость в секторе ${displayName}:</span><span class="sector-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span></div></div>`;
    body.innerHTML = html;
}

function closeSectorAssetsModal() {
    const modal = document.getElementById('sectorAssetsModal');
    if (modal) modal.classList.remove('active');
}

function openCryptoTypeModal(type, displayName) {
    const modal = document.getElementById('cryptoTypeModal');
    const titleSpan = document.getElementById('cryptoTypeName');
    const body = document.getElementById('cryptoTypeBody');
    if (!modal || !body) return;
    titleSpan.textContent = displayName;
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    modal.classList.add('active');
    const typeData = cryptoTypeAssetsData ? cryptoTypeAssetsData[type] : null;
    if (!typeData || !typeData.assets || typeData.assets.length === 0) {
        body.innerHTML = `<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i><p style="color: #6b7a8f;">В категории "${displayName}" нет активов</p></div>`;
        return;
    }
    const assets = [...typeData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    let totalValueUsd = typeData.total_value_usd;
    const totalValueRub = totalValueUsd * usdRubRate;
    let html = `<table class="crypto-type-table"><thead><tr><th>Актив</th><th style="text-align: right;">Количество</th><th style="text-align: right;">Средняя цена</th><th style="text-align: right;">Стоимость</th></tr></thead><tbody>`;
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        let quantityFormatted = '';
        if (Math.floor(quantityNum) === quantityNum) quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
        else {
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
        let rubStr = Math.round(valueUsdNum * usdRubRate).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        const assetIcon = getAssetIcon(asset.symbol);
        html += `<tr><td><div style="display: flex; align-items: center; gap: 10px;"><div style="width: 32px; height: 32px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;"><i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i></div><div><div class="crypto-type-symbol">${asset.symbol}</div><div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div></div></div></td><td class="crypto-type-quantity">${quantityFormatted}</td><td class="crypto-type-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td><td class="crypto-type-value">${valueFormatted}</td></tr>`;
    });
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    html += `</tbody></table><div class="crypto-type-summary"><div class="crypto-type-summary-row"><span style="font-weight: 600;">Всего активов:</span><span style="font-weight: 600;">${assets.length}</span></div><div class="crypto-type-summary-row"><span>Общая стоимость в категории ${displayName}:</span><span class="crypto-type-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span></div></div>`;
    body.innerHTML = html;
}

function closeCryptoTypeModal() {
    const modal = document.getElementById('cryptoTypeModal');
    if (modal) modal.classList.remove('active');
}

function showAssetHistory(data) {
    showNotification('info', 'История', `История операций для ${data.symbol} будет доступна позже`);
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
// ИНИЦИАЛИЗАЦИЯ
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    loadOperations(1);
    loadNotes();
    
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
    
    document.querySelectorAll('.modal-close, .btn-secondary').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if (modal) modal.classList.remove('active');
        });
    });
    
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });
    
    const numberInputs = ['depositAmount', 'tradeQuantity', 'tradePrice', 'tradeCommission', 'transferAmount', 'transferCommission', 'limitQuantity', 'limitPrice', 'expenseAmount'];
    numberInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', function() { formatInput(this); });
            input.addEventListener('blur', function() { formatInput(this); });
        }
    });
    
    ['tradeQuantity', 'tradePrice', 'tradeCommission'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', calculateTradeTotal);
    });
    
    document.getElementById('selectTradePlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openPlatformModal(context, null);
    });
    
    document.getElementById('selectTradeFromPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('buy', 'from');
    });
    
    document.getElementById('selectTradeAssetBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openAssetModal(context, 'asset');
    });
    
    document.getElementById('selectTradePriceCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openCurrencyModal(context, 'price');
    });
    
    document.getElementById('selectTradeCommissionCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openCurrencyModal(context, 'commission');
    });
    
    document.getElementById('selectCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCurrencyModal('deposit', null);
    });
    
    document.getElementById('selectPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('deposit', null);
    });
    
    document.getElementById('selectFromPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('transfer', 'from');
    });
    
    document.getElementById('selectToPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('transfer', 'to');
    });
    
    document.getElementById('selectAssetBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openAssetModal('transfer', 'asset');
    });
    
    document.getElementById('selectCommissionCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCurrencyModal('transfer', 'commission');
    });
    
    document.getElementById('selectExpenseCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('expense', 'currency');
        openCurrencyModal('expense', 'price');
    });
    
    document.getElementById('selectTradeNetworkBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('trade', 'network');
        openNetworkModal('trade');
    });
    
    document.getElementById('selectFromNetworkBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openNetworkModal('from');
    });
    
    document.getElementById('selectToNetworkBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openNetworkModal('to');
    });
    
    document.getElementById('selectLimitPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('limit', 'platform');
        openPlatformModal('limit', null);
    });
    
    document.getElementById('selectLimitAssetBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openAssetModal('limit', 'asset');
    });
    
    document.getElementById('selectLimitCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('limit', 'currency');
        openCurrencyModal('limit', 'price');
    });
    
    document.getElementById('confirmLimitOrderBtn')?.addEventListener('click', confirmLimitOrder);
    
    document.getElementById('sellQuickFillAllBtn')?.addEventListener('click', function() {
        if (window.sellAssetData) {
            const quantity = window.sellAssetData.currentQuantity;
            const quantityInput = document.getElementById('tradeQuantity');
            if (quantityInput) {
                quantityInput.value = quantity.toString();
                quantityInput.dispatchEvent(new Event('input'));
            }
        }
    });
    
    document.getElementById('sellQuickFillAvgBtn')?.addEventListener('click', function() {
        if (window.sellAssetData && window.sellAssetData.avgPrice > 0) {
            const priceInput = document.getElementById('tradePrice');
            if (priceInput) {
                priceInput.value = window.sellAssetData.avgPrice.toString();
                priceInput.dispatchEvent(new Event('input'));
            }
        }
    });
});