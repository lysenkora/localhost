// ============================================================================
// ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ
// ============================================================================

const API_URL = 'get_operations.php';
const API_URL_PHP = 'php.php';
const depositModal = document.getElementById('depositModal');
const tradeModal = document.getElementById('tradeModal');
const transferModal = document.getElementById('transferModal');
const tradeOperationType = document.getElementById('tradeOperationType');
const confirmTradeBtnText = document.getElementById('confirmTradeBtnText');

// Глобальные переменные для расходов
let expenseCategories = [];

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

// ============================================================================
// ЦВЕТОВЫЕ СХЕМЫ
// ============================================================================

const modalColorSchemes = {
    deposit: {
        platform: {
            headerIcon: '#00a86b',
            headerTitle: 'Выберите площадку',
            listItemColor: '#00a86b',
            addButtonColor: '#00a86b'
        },
        currency: {
            headerIcon: '#00a86b',
            headerTitle: 'Выберите валюту',
            listItemColor: '#00a86b',
            addButtonColor: '#00a86b'
        },
        addPlatform: {
            headerIcon: '#00a86b',
            confirmButton: '#00a86b'
        },
        addCurrency: {
            headerIcon: '#00a86b',
            confirmButton: '#00a86b'
        }
    },
    buy: {
        platform: {
            headerIcon: '#00a86b',
            headerTitle: 'Выберите площадку покупки',
            listItemColor: '#00a86b',
            addButtonColor: '#00a86b'
        },
        currency: {
            headerIcon: '#00a86b',
            headerTitle: 'Выберите валюту цены',
            listItemColor: '#00a86b',
            addButtonColor: '#00a86b'
        },
        addPlatform: {
            headerIcon: '#00a86b',
            confirmButton: '#00a86b'
        },
        addCurrency: {
            headerIcon: '#00a86b',
            confirmButton: '#00a86b'
        }
    },
    sell: {
        platform: {
            headerIcon: '#e53e3e',
            headerTitle: 'Выберите площадку продажи',
            listItemColor: '#e53e3e',
            addButtonColor: '#e53e3e'
        },
        currency: {
            headerIcon: '#e53e3e',
            headerTitle: 'Выберите валюту цены',
            listItemColor: '#e53e3e',
            addButtonColor: '#e53e3e'
        },
        addPlatform: {
            headerIcon: '#e53e3e',
            confirmButton: '#e53e3e'
        },
        addCurrency: {
            headerIcon: '#e53e3e',
            confirmButton: '#e53e3e'
        }
    },
    transfer: {
        platform: {
            headerIcon: '#ff9f4a',
            headerTitleFrom: 'Выберите площадку отправителя',
            headerTitleTo: 'Выберите площадку получателя',
            listItemColor: '#ff9f4a',
            addButtonColor: '#ff9f4a'
        },
        currency: {
            headerIcon: '#ff9f4a',
            headerTitleAsset: 'Выберите актив',
            headerTitleCommission: 'Выберите валюту комиссии',
            listItemColor: '#ff9f4a',
            addButtonColor: '#ff9f4a'
        },
        addPlatform: {
            headerIcon: '#ff9f4a',
            confirmButton: '#ff9f4a'
        },
        addCurrency: {
            headerIcon: '#ff9f4a',
            confirmButton: '#ff9f4a'
        }
    },
    buy_from: {
        platform: {
            headerIcon: '#4a9eff',
            headerTitle: 'Выберите площадку списания',
            listItemColor: '#4a9eff',
            addButtonColor: '#4a9eff'
        }
    },
    default: {
        platform: {
            headerIcon: '#1a5cff',
            headerTitle: 'Выберите площадку',
            listItemColor: '#1a5cff',
            addButtonColor: '#1a5cff'
        },
        currency: {
            headerIcon: '#1a5cff',
            headerTitle: 'Выберите валюту',
            listItemColor: '#1a5cff',
            addButtonColor: '#1a5cff'
        },
        addPlatform: {
            headerIcon: '#1a5cff',
            confirmButton: '#1a5cff'
        },
        addCurrency: {
            headerIcon: '#1a5cff',
            confirmButton: '#1a5cff'
        }
    }
};

let currentModalContext = {
    source: 'default',
    mode: null,
    subMode: null
};

// ============================================================================
// ФУНКЦИИ ДЛЯ БЛОКИРОВКИ СКРОЛЛА
// ============================================================================

function disableBodyScroll() {
    // Сохраняем текущую позицию скролла
    const scrollY = window.scrollY;
    document.body.style.position = 'fixed';
    document.body.style.top = `-${scrollY}px`;
    document.body.style.width = '100%';
    document.body.dataset.scrollPosition = scrollY;
}

function enableBodyScroll() {
    const scrollY = document.body.dataset.scrollPosition;
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    window.scrollTo(0, parseInt(scrollY || '0'));
    delete document.body.dataset.scrollPosition;
}

function setModalContext(source, mode, subMode = null) {
    currentModalContext = { source, mode, subMode };
}

function getColorScheme() {
    const source = currentModalContext.source || 'default';
    const subMode = currentModalContext.subMode;
    
    let scheme = modalColorSchemes[source] || modalColorSchemes.default;
    
    if (source === 'buy' && subMode === 'from') {
        return modalColorSchemes.buy_from;
    }
    
    return scheme;
}

// ============================================================================
// ФУНКЦИИ ФОРМАТИРОВАНИЯ ЧИСЕЛ
// ============================================================================

function formatNumberWithSpaces(value, decimals = null) {
    if (!value && value !== 0) return '';
    
    let numStr = String(value).replace(/\s/g, '').replace(',', '.');
    
    if (isNaN(parseFloat(numStr))) return value;
    
    let num = parseFloat(numStr);
    
    // Определяем количество знаков после запятой
    let decimalPlaces = decimals !== null ? decimals : 6;
    let formatted = num.toFixed(decimalPlaces);
    
    // Убираем лишние нули в конце
    formatted = formatted.replace(/\.?0+$/, '');
    
    // Разделяем целую и дробную части
    let parts = formatted.split('.');
    // Добавляем пробелы между разрядами в целой части
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Если есть дробная часть, возвращаем без пробелов в ней
    if (parts.length > 1 && parts[1]) {
        return parts[0] + '.' + parts[1];
    } else {
        return parts[0];
    }
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
    
    if (value === '' || value === '-') {
        return;
    }
    
    // Заменяем запятую на точку для единообразия
    let rawValue = value.replace(/\s/g, '').replace(',', '.');
    
    // Проверяем на наличие букв
    if (/[a-zA-Zа-яА-Я]/.test(rawValue)) {
        return;
    }
    
    // Разрешаем временное состояние с точкой
    // Это ключевое условие - позволяет вводить точку
    if (rawValue === '.' || rawValue === '-.' || rawValue === '0.' || rawValue === '0.0') {
        input.value = rawValue;
        // Восстанавливаем позицию курсора после точки
        const newCursorPos = input.value.length;
        input.setSelectionRange(newCursorPos, newCursorPos);
        return;
    }
    
    // Проверяем, заканчивается ли строка на точку (например, "123.")
    if (rawValue.endsWith('.')) {
        // Сохраняем значение без точки для проверки
        const valueWithoutDot = rawValue.slice(0, -1);
        // Проверяем, что часть до точки - валидное число
        if (/^-?\d*$/.test(valueWithoutDot) || valueWithoutDot === '' || valueWithoutDot === '-') {
            input.value = rawValue;
            const newCursorPos = input.value.length;
            input.setSelectionRange(newCursorPos, newCursorPos);
            return;
        }
    }
    
    // Проверяем валидность числа
    if (!/^-?\d*\.?\d*$/.test(rawValue)) {
        return;
    }
    
    // Преобразуем в число
    let num = parseFloat(rawValue);
    if (isNaN(num)) {
        input.value = rawValue;
        return;
    }
    
    // Определяем, есть ли в исходной строке точка
    const hasDecimalPoint = rawValue.includes('.');
    
    // Определяем количество знаков после запятой, которые ввел пользователь
    let originalDecimalPlaces = 0;
    const decimalMatch = rawValue.match(/\.(\d+)$/);
    if (decimalMatch) {
        originalDecimalPlaces = decimalMatch[1].length;
    }
    
    // Если есть точка и есть цифры после нее - сохраняем исходный формат
    if (hasDecimalPoint && originalDecimalPlaces > 0) {
        let parts = rawValue.split('.');
        // Форматируем целую часть с пробелами
        if (parts[0] && parts[0] !== '' && parts[0] !== '-') {
            // Убираем ведущие нули, но сохраняем ноль если число начинается с 0
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
        const newCursorPos = Math.min(cursorPos + lengthDiff, newLength);
        input.setSelectionRange(newCursorPos, newCursorPos);
        return;
    }
    
    // Ноль без точки
    if (num === 0 && !hasDecimalPoint) {
        input.value = '0';
        return;
    }
    
    // Целые числа
    if (Number.isInteger(num) && !hasDecimalPoint) {
        input.value = num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const newLength = input.value.length;
        const lengthDiff = newLength - oldLength;
        const newCursorPos = Math.min(cursorPos + lengthDiff, newLength);
        input.setSelectionRange(newCursorPos, newCursorPos);
        return;
    }
    
    // Остальные числа с дробной частью
    // Сохраняем количество знаков после запятой, которое ввел пользователь
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
    
    // Корректируем позицию курсора, если вводили точку
    if (rawValue === '.') {
        newCursorPos = formattedValue.indexOf('.') + 1;
    }
    
    input.setSelectionRange(newCursorPos, newCursorPos);
}

// Функция для форматирования итоговой суммы
function formatTotalAmount(value) {
    if (!value && value !== 0) return '0';
    
    let num = parseFloat(value);
    if (isNaN(num)) return '0';
    
    // Форматируем с 6 знаками после запятой
    let formatted = num.toFixed(6);
    
    // Разделяем целую и дробную части
    let parts = formatted.split('.');
    let integerPart = parts[0];
    let decimalPart = parts[1];
    
    // Добавляем пробелы между разрядами в целой части
    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Убираем лишние нули в конце дробной части
    decimalPart = decimalPart.replace(/0+$/, '');
    
    // Формируем итоговую строку
    if (decimalPart.length > 0) {
        return integerPart + '.' + decimalPart;
    } else {
        return integerPart;
    }
}

// ============================================================================
// ФУНКЦИИ ПОИСКА
// ============================================================================

function findPlatformIdByName(name) {
    const platform = platformsData.find(p => p.name.toLowerCase() === name.toLowerCase());
    return platform ? platform.id : null;
}

function findAssetIdBySymbol(symbol) {
    const asset = assetsData.find(a => a.symbol.toUpperCase() === symbol.toUpperCase());
    return asset ? asset.id : null;
}

function getAssetTypeBySymbol(symbol) {
    const asset = assetsData.find(a => a.symbol.toUpperCase() === symbol.toUpperCase());
    return asset ? asset.type : null;
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА АКТИВА (НОВЫЕ)
// ============================================================================

function openAssetModal(context = 'default', subMode = null) {
    setModalContext(context, 'asset', subMode);
    
    const modalTitle = document.querySelector('#currencySelectModal .modal-header h2');
    modalTitle.innerHTML = '<i class="fas fa-coins" style="color: #ff9f4a;"></i> Выберите актив';
    
    const modal = document.getElementById('currencySelectModal');
    if (modal) {
        filterAssetsForSelect('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('currencySearch')?.focus();
        }, 100);
    }
}

function filterAssetsForSelect(searchText) {
    const listContainer = document.getElementById('allCurrenciesList');
    if (!listContainer) return;
    
    const searchTextLower = searchText.toLowerCase().trim();
    const originalSearchText = searchText.trim();
    
    let assetsToShow = assetsData;
    if (searchTextLower) {
        assetsToShow = assetsData.filter(a => 
            a.symbol.toLowerCase().includes(searchTextLower) || 
            (a.name && a.name.toLowerCase().includes(searchTextLower))
        );
    }
    
    assetsToShow.sort((a, b) => {
        const typeOrder = { 'crypto': 1, 'stock': 2, 'etf': 3, 'currency': 4, 'bond': 5, 'other': 6 };
        return (typeOrder[a.type] || 99) - (typeOrder[b.type] || 99);
    });
    
    if (assetsToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `
            <div onclick="addNewAssetFromCurrencyModal('${originalSearchText.replace(/'/g, "\\'")}')" 
                 style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: #ff9f4a; transition: all 0.2s;" 
                 onmouseover="this.style.background='#f0f3f7'" 
                 onmouseout="this.style.background='transparent'">
                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i>
                Добавить "${originalSearchText}"
            </div>
        `;
        return;
    }
    
    listContainer.innerHTML = assetsToShow.map(asset => {
        let iconColor = '#6b7a8f';
        let typeIcon = 'fa-coins';
        let typeText = '';
        
        switch(asset.type) {
            case 'crypto':
                iconColor = '#f7931a';
                typeIcon = 'fa-bitcoin';
                typeText = 'Крипто';
                break;
            case 'stock':
                iconColor = '#00a86b';
                typeIcon = 'fa-chart-line';
                typeText = 'Акция';
                break;
            case 'etf':
                iconColor = '#4a9eff';
                typeIcon = 'fa-chart-pie';
                typeText = 'ETF';
                break;
            case 'currency':
                iconColor = '#1a5cff';
                typeIcon = 'fa-money-bill';
                typeText = 'Валюта';
                break;
            case 'bond':
                iconColor = '#9b59b6';
                typeIcon = 'fa-file-invoice';
                typeText = 'Облигация';
                break;
            default:
                typeText = 'Другое';
        }
        
        return `
            <div onclick="selectAssetFromModal('${asset.id}', '${asset.symbol.replace(/'/g, "\\'")}', '${asset.type || 'other'}')" 
                 style="padding: 12px; cursor: pointer; border-radius: 10px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; border: 1px solid transparent;"
                 onmouseover="this.style.background='#f8fafd'; this.style.borderColor='#e0e6ed'" 
                 onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'">
                <div style="width: 36px; height: 36px; background: ${iconColor}15; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${iconColor};">
                    <i class="fas ${typeIcon}"></i>
                </div>
                <div style="flex: 1;">
                    <div class="asset-symbol">${asset.symbol}</div>
                    <div style="font-size: 12px; color: #6b7a8f; display: flex; gap: 8px; margin-top: 2px;">
                        <span>${asset.name || ''}</span>
                        <span style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 12px;">${typeText}</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right" style="color: #95a5a6; font-size: 12px;"></i>
            </div>
        `;
    }).join('');
}

function selectAssetFromModal(id, symbol, type) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    
    if (context === 'transfer' && subMode === 'asset') {
        selectAsset(id, symbol);
    } else if (context === 'sell') {
        selectSellAsset(id, symbol, type);
    } else if (context === 'buy') {
        selectTradeAsset(id, symbol, type);
    }
    
    closeCurrencyModal();
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ВАЛЮТЫ (ОРИГИНАЛЬНЫЕ, НО ИСПРАВЛЕННЫЕ)
// ============================================================================

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
        if (subMode === 'commission') {
            titleText = currencyScheme.headerTitleCommission || 'Выберите валюту комиссии';
        }
    } else if (context === 'buy') {
        if (subMode === 'price') {
            titleText = 'Выберите валюту цены';
        } else if (subMode === 'commission') {
            titleText = 'Выберите валюту комиссии';
        }
    } else if (context === 'sell') {  // ← ДОБАВЬТЕ ЭТОТ БЛОК
        if (subMode === 'price') {
            titleText = 'Выберите валюту цены';
        } else if (subMode === 'commission') {
            titleText = 'Выберите валюту комиссии';
        }
    } else if (context === 'deposit') {
        titleText = 'Выберите валюту';
    }
    
    modalTitle.innerHTML = `<i class="fas fa-coins" style="color: ${currencyScheme.headerIcon};"></i> ${titleText}`;
    
    const modal = document.getElementById('currencySelectModal');
    if (modal) {
        filterCurrencies('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('currencySearch')?.focus();
        }, 100);
    }
}

function filterCurrencies(searchText) {
    // Если текущий режим - выбор актива, используем filterAssetsForSelect
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
    if (searchTextLower) {
        currenciesToShow = allCurrencies.filter(c => 
            c.code.toLowerCase().includes(searchTextLower) || 
            (c.name && c.name.toLowerCase().includes(searchTextLower))
        );
    }
    
    if (currenciesToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `
            <div onclick="addNewCurrencyAndSelect('${originalSearchText.replace(/'/g, "\\'")}', '${currentModalContext.source}', '${currentModalContext.subMode || 'default'}')" 
                 style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: ${currencyScheme.addButtonColor}; transition: all 0.2s;" 
                 onmouseover="this.style.background='#f0f3f7'" 
                 onmouseout="this.style.background='transparent'">
                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i>
                Добавить "${originalSearchText.toUpperCase()}"
            </div>
        `;
        return;
    }
    
    listContainer.innerHTML = currenciesToShow.map(currency => `
        <div onclick="selectCurrencyFromList('${currency.code}', '${currency.name || currency.code}')" 
             style="padding: 12px; cursor: pointer; border-radius: 10px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; border: 1px solid transparent;"
             onmouseover="this.style.background='#f8fafd'; this.style.borderColor='#e0e6ed'" 
             onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'">
            <div style="width: 36px; height: 36px; background: ${currencyScheme.listItemColor}15; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${currencyScheme.listItemColor};">
                <i class="fas fa-coins"></i>
            </div>
            <div style="flex: 1;">
                <div class="asset-symbol">${currency.code}</div>
                <div style="font-size: 12px; color: #6b7a8f;">${currency.name || ''}</div>
            </div>
            <i class="fas fa-chevron-right" style="color: #95a5a6; font-size: 12px;"></i>
        </div>
    `).join('');
}

function closeCurrencyModal() {
    const modal = document.getElementById('currencySelectModal');
    if (modal) {
        modal.classList.remove('active');
        document.getElementById('currencySearch').value = '';
    }
}

function selectCurrencyFromList(code, name) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    
    if (context === 'transfer') {
        if (subMode === 'commission') {
            selectCommissionCurrency(code);
        }
    } else if (context === 'buy') {
        if (subMode === 'price') {
            selectTradePriceCurrency(code);
        } else if (subMode === 'commission') {
            selectTradeCommissionCurrency(code);
        }
    } else if (context === 'sell') {
        if (subMode === 'price') {
            selectSellPriceCurrency(code);
        } else if (subMode === 'commission') {
            selectSellCommissionCurrency(code);
        }
    } else if (context === 'deposit') {
        selectCurrency(code, name);
    } else if (context === 'limit') {
        selectLimitCurrency(code);
    } else if (context === 'expense') {
        document.getElementById('selectedExpenseCurrencyDisplay').textContent = code;
    }
    
    closeCurrencyModal();
}

function selectCurrency(code, name) {
    selectedCurrency = { code, name };
    
    const display = document.getElementById('selectedCurrencyDisplay');
    if (display) {
        display.textContent = code;
    }
    
    const hiddenInput = document.getElementById('depositCurrency');
    if (hiddenInput) {
        hiddenInput.value = code;
    }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ПЛОЩАДКИ
// ============================================================================

function openPlatformModal(context = 'default', subMode = null) {
    setModalContext(context, 'platform', subMode);
    const scheme = getColorScheme();
    const platformScheme = scheme.platform || modalColorSchemes.default.platform;
    
    const modalTitle = document.querySelector('#platformSelectModal .modal-header h2');
    let titleText = platformScheme.headerTitle;
    
    if (context === 'transfer') {
        if (subMode === 'from') {
            titleText = platformScheme.headerTitleFrom || 'Выберите площадку отправителя';
        } else if (subMode === 'to') {
            titleText = platformScheme.headerTitleTo || 'Выберите площадку получателя';
        }
    } else if (context === 'buy' && subMode === 'from') {
        titleText = 'Выберите площадку списания';
    }
    
    modalTitle.innerHTML = `<i class="fas fa-building" style="color: ${platformScheme.headerIcon};"></i> ${titleText}`;
    
    const modal = document.getElementById('platformSelectModal');
    if (modal) {
        filterPlatforms('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('platformSearch')?.focus();
        }, 100);
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
    
    // Используем platformsData (глобальный массив, который обновляется)
    let platformsToShow = platformsData;
    if (searchTextLower) {
        platformsToShow = platformsData.filter(p => 
            p.name.toLowerCase().includes(searchTextLower)
        );
    }
    
    if (platformsToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `
            <div onclick="addNewPlatformAndSelect('${originalSearchText.replace(/'/g, "\\'")}', '${currentModalContext.source}')" style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: ${platformScheme.addButtonColor}; transition: all 0.2s;" 
                 onmouseover="this.style.background='#f0f3f7'" 
                 onmouseout="this.style.background='transparent'">
                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i>
                Добавить "${originalSearchText}"
            </div>
        `;
        return;
    }
    
    listContainer.innerHTML = platformsToShow.map(platform => `
        <div onclick="selectPlatformFromList('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px; transition: background 0.2s;" 
             onmouseover="this.style.background='#f0f3f7'" 
             onmouseout="this.style.background='transparent'">
            <span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span>
            <span style="color: ${platformScheme.listItemColor}; font-size: 12px; margin-left: auto;"><i class="fas fa-chevron-right"></i></span>
        </div>
    `).join('');
}

function selectPlatformFromList(id, name) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    
    if (context === 'transfer') {
        if (subMode === 'from') {
            selectFromPlatform(id, name);
        } else if (subMode === 'to') {
            selectToPlatform(id, name);
        }
    } else if (context === 'buy' && subMode === 'from') {
        selectTradeFromPlatform(id, name);
    } else if (context === 'buy' || context === 'sell') {
        selectTradePlatform(id, name);
    } else if (context === 'deposit') {
        selectPlatform(id, name);
    } else if (context === 'expense') {  // ← ДОБАВЬТЕ ЭТОТ БЛОК
        selectExpensePlatform(id, name);
    }
    
    closePlatformModal();
}

function selectPlatform(id, name) {
    selectedPlatform = { id, name };
    window.currentPlatformMode = 'platform'; // Добавляем для отслеживания
    
    const display = document.getElementById('selectedPlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('depositPlatformId');
    if (hiddenInput) hiddenInput.value = id;
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ПЛОЩАДКИ В ТОРГОВЛЕ
// ============================================================================

function selectTradePlatform(id, name) {
    selectedTradePlatform = { id, name };
    window.currentPlatformMode = 'trade';
    
    const display = document.getElementById('selectedTradePlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('tradePlatformId');
    if (hiddenInput) hiddenInput.value = id;
    
    const operationType = document.getElementById('tradeOperationType').value;
    const assetId = document.getElementById('tradeAssetId').value;
    
    if (operationType === 'sell' && assetId) {
        loadPurchaseHistoryForSell(assetId, id);
        // ДОБАВИТЬ: Загружаем лоты для продажи
        if (selectedSellAsset.id && selectedSellPriceCurrency.code) {
            loadSellLots();
        }
    } else if (operationType === 'sell') {
        // Если актив еще не выбран, показываем сообщение
        const historyBlock = document.getElementById('sellPurchaseHistory');
        if (historyBlock) {
            historyBlock.style.display = 'block';
            document.getElementById('sellPurchaseList').innerHTML = 
                '<div style="text-align: center; padding: 20px; color: #ff9f4a;"><i class="fas fa-info-circle"></i> Сначала выберите актив</div>';
            document.getElementById('sellQuickActions').style.display = 'none';
        }
    }
}

function selectTradeFromPlatform(id, name) {
    selectedTradeFromPlatform = { id, name };
    window.currentPlatformMode = 'trade_from';
    
    const display = document.getElementById('selectedTradeFromPlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('tradeFromPlatformId');
    if (hiddenInput) hiddenInput.value = id;
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА АКТИВА (СТАНДАРТНЫЕ)
// ============================================================================

function selectAsset(id, symbol) {
    selectedTransferAsset = { id, symbol };
    
    const display = document.getElementById('selectedAssetDisplay');
    if (display) {
        display.textContent = symbol;
    }
    
    const hiddenInput = document.getElementById('transferAssetId');
    if (hiddenInput) {
        hiddenInput.value = id;
    }
    
    const asset = assetsData.find(a => a.id == id);
    const cryptoSection = document.getElementById('transferCryptoNetworkSection');
    if (cryptoSection) {
        cryptoSection.style.display = asset && asset.type === 'crypto' ? 'block' : 'none';
    }
}

function selectTradeAsset(id, symbol, type) {
    selectedTradeAsset = { id, symbol, type };
    
    const display = document.getElementById('selectedTradeAssetDisplay');
    if (display) display.textContent = symbol;
    
    const hiddenInput = document.getElementById('tradeAssetId');
    if (hiddenInput) hiddenInput.value = id;
    
    const typeInput = document.getElementById('tradeAssetType');
    if (typeInput) typeInput.value = type || 'other';
    
    // Пересчитываем итог
    calculateTradeTotal();
    
    const cryptoSection = document.getElementById('tradeCryptoNetworkSection');
    if (cryptoSection) {
        cryptoSection.style.display = type === 'crypto' ? 'block' : 'none';
    }
    
    // НОВЫЙ КОД: Если это продажа, загружаем историю покупок
    const operationType = document.getElementById('tradeOperationType').value;
    const platformId = document.getElementById('tradePlatformId').value;
    
    if (operationType === 'sell' && platformId) {
        loadPurchaseHistoryForSell(id, platformId);
        // ДОБАВИТЬ: Загружаем лоты для продажи
        if (selectedSellAsset.id && selectedSellPriceCurrency.code) {
            loadSellLots();
        }
    } else if (operationType === 'sell') {
        // Если площадка еще не выбрана, показываем сообщение
        const historyBlock = document.getElementById('sellPurchaseHistory');
        if (historyBlock) {
            historyBlock.style.display = 'block';
            document.getElementById('sellPurchaseList').innerHTML = 
                '<div style="text-align: center; padding: 20px; color: #ff9f4a;"><i class="fas fa-info-circle"></i> Сначала выберите площадку</div>';
            document.getElementById('sellQuickActions').style.display = 'none';
        }
    } else {
        // Если это покупка, скрываем блок истории
        const historyBlock = document.getElementById('sellPurchaseHistory');
        if (historyBlock) historyBlock.style.display = 'none';
    }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ВАЛЮТЫ ЦЕНЫ И КОМИССИИ
// ============================================================================

function selectTradePriceCurrency(code) {
    selectedTradePriceCurrency = { code };
    
    const display = document.getElementById('selectedTradePriceCurrencyDisplay');
    if (display) display.textContent = code;
    
    const hiddenInput = document.getElementById('tradePriceCurrency');
    if (hiddenInput) hiddenInput.value = code;
    
    // Копируем в комиссию, если там ничего не выбрано
    const commissionDisplay = document.getElementById('selectedTradeCommissionCurrencyDisplay');
    const commissionHidden = document.getElementById('tradeCommissionCurrency');
    
    if (commissionDisplay && commissionHidden && !commissionHidden.value) {
        commissionDisplay.textContent = code;
        commissionHidden.value = code;
        selectedTradeCommissionCurrency = { code };
    }
    
    // Пересчитываем итог
    calculateTradeTotal();
}

function selectTradeCommissionCurrency(code) {
    selectedTradeCommissionCurrency = { code };
    
    const display = document.getElementById('selectedTradeCommissionCurrencyDisplay');
    if (display) display.textContent = code;
    
    const hiddenInput = document.getElementById('tradeCommissionCurrency');
    if (hiddenInput) hiddenInput.value = code;
    
    calculateTradeTotal();
}

function selectCommissionCurrency(code) {
    selectedCommissionCurrency = { code };
    
    const display = document.getElementById('selectedCommissionCurrencyDisplay');
    if (display) display.textContent = code;
    
    const hiddenInput = document.getElementById('transferCommissionCurrency');
    if (hiddenInput) hiddenInput.value = code;
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА ПЛОЩАДКИ ОТПРАВИТЕЛЯ/ПОЛУЧАТЕЛЯ
// ============================================================================

function selectFromPlatform(id, name) {
    selectedFromPlatform = { id, name };
    window.currentPlatformMode = 'from';
    
    const display = document.getElementById('selectedFromPlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('transferFromPlatformId');
    if (hiddenInput) hiddenInput.value = id;
    
    // НОВЫЙ КОД: Загружаем баланс выбранной площадки
    loadPlatformBalance(id, name);
}

function selectToPlatform(id, name) {
    selectedToPlatform = { id, name };
    window.currentPlatformMode = 'to'; // Добавляем для отслеживания
    
    const display = document.getElementById('selectedToPlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('transferToPlatformId');
    if (hiddenInput) hiddenInput.value = id;
}

// ============================================================================
// ФУНКЦИИ РАСЧЕТА
// ============================================================================

function calculateTradeTotal() {
    const quantity = getNumericValue(document.getElementById('tradeQuantity').value);
    const price = getNumericValue(document.getElementById('tradePrice').value);
    const commission = getNumericValue(document.getElementById('tradeCommission').value);
    
    // Получаем валюту оплаты (цену)
    const paymentCurrency = document.getElementById('tradePriceCurrency').value;
    
    // Рассчитываем общую сумму
    const total = quantity * price + commission;
    
    // Форматируем сумму
    let formattedTotal = '0';
    if (!isNaN(total) && isFinite(total) && total > 0) {
        // Для RUB и USD - 2 знака, для крипто - 6 знаков
        let decimals = 2;
        if (paymentCurrency === 'BTC' || paymentCurrency === 'ETH') {
            decimals = 6;
        }
        
        formattedTotal = total.toFixed(decimals);
        // Добавляем пробелы между разрядами в целой части
        let parts = formattedTotal.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        formattedTotal = parts.join('.');
    }
    
    // Формируем итоговую строку: "сумма валюта"
    const totalField = document.getElementById('tradeTotal');
    if (paymentCurrency && paymentCurrency !== '') {
        totalField.value = `${formattedTotal} ${paymentCurrency}`;
    } else {
        totalField.value = formattedTotal;
    }

    const totalQuantitySpan = document.getElementById('tradeTotalQuantity');
    if (totalQuantitySpan) {
        let resultDisplay = '0';
        if (!isNaN(total) && isFinite(total) && total > 0) {
            // Форматируем результат без округления и без валюты
            let resultStr = total.toString();
            if (resultStr.includes('e')) {
                resultStr = total.toFixed(12);
            }
            let resultParts = resultStr.split('.');
            resultParts[0] = resultParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            if (resultParts.length > 1 && resultParts[1]) {
                let decimalPart = resultParts[1].replace(/0+$/, '');
                resultDisplay = decimalPart.length > 0 ? resultParts[0] + '.' + decimalPart : resultParts[0];
            } else {
                resultDisplay = resultParts[0];
            }
        }
        totalQuantitySpan.textContent = resultDisplay;
    }
}

function updateTradeTotalCurrency() {
    const currency = document.getElementById('tradePriceCurrency').value;
    const totalCurrencySpan = document.getElementById('tradeTotalCurrency');
    if (totalCurrencySpan) {
        totalCurrencySpan.textContent = currency || '—';
    }
}

// ============================================================================
// ФУНКЦИИ МОДАЛЬНЫХ ОКОН
// ============================================================================

function openDepositModal() {
    depositModal.classList.add('active');
    document.getElementById('depositDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('depositAmount').value = '';
    document.getElementById('depositCurrency').value = '';
    document.getElementById('selectedCurrencyDisplay').textContent = 'Выбрать';
    document.getElementById('depositPlatformId').value = '';
    document.getElementById('selectedPlatformDisplay').textContent = 'Выбрать площадку';
    document.getElementById('depositNotes').value = '';  // ← ДОБАВЬТЕ
    
    setModalContext('deposit', null);
}

function closeDepositModal() {
    depositModal.classList.remove('active');
}

function openBuyModal() {
    openTradeModal('buy');
}

function openSellModalFromTrade() {
    openTradeModal('sell');
}

// ============================================================================
// НОВОЕ МОДАЛЬНОЕ ОКНО ПРОДАЖИ
// ============================================================================

let selectedSellAsset = { id: null, symbol: '', type: '' };
let selectedSellPriceCurrency = { code: '' };
let sellLots = []; // Все доступные лоты (сгруппированные по площадкам)
let selectedLots = new Map(); // Выбранные лоты (id => lot)

// В начале файла, после объявления переменных, добавим переменную для отслеживания состояния
let sellModalScrollLocked = false;

function openSellModal() {
    const modal = document.getElementById('sellModal');
    if (modal) {
        modal.classList.add('active');
        
        // Блокируем скролл только если он еще не заблокирован
        if (!sellModalScrollLocked) {
            disableBodyScroll();
            sellModalScrollLocked = true;
        }
        
        // Устанавливаем дату
        document.getElementById('sellDate').value = new Date().toISOString().split('T')[0];
        
        // Сбрасываем поля
        document.getElementById('sellPrice').value = '';
        document.getElementById('sellCommission').value = '';
        document.getElementById('sellNotes').value = '';
        document.getElementById('sellAssetId').value = '';
        document.getElementById('selectedSellAssetDisplay').textContent = 'Выбрать актив';
        document.getElementById('selectedSellPriceCurrencyDisplay').textContent = 'Выбрать';
        document.getElementById('sellPriceCurrency').value = '';
        document.getElementById('sellCommissionCurrency').value = '';
        document.getElementById('selectedSellCommissionCurrencyDisplay').textContent = 'Выбрать';
        
        // Скрываем блоки
        document.getElementById('sellLotsContainer').style.display = 'none';
        document.getElementById('sellTransactionDetails').style.display = 'none';
        
        // Сбрасываем данные
        selectedSellAsset = { id: null, symbol: '', type: '' };
        selectedSellPriceCurrency = { code: '' };
        sellLots = [];
        selectedLots.clear();
        selectedPurchases.clear();
        window.manualSelectedQuantity = 0;
        sellPurchaseHistory = [];
        sellPlatformBalances = [];
        selectedSellPlatformId = null;
        selectedSellPlatformName = '';
        selectedSellPlatformAvgPrice = 0;
        
        // Сбрасываем скрытое поле площадки
        const platformInput = document.getElementById('sellPlatformId');
        if (platformInput) platformInput.value = '';
    }
}

function closeSellModal() {
    const modal = document.getElementById('sellModal');
    if (modal) {
        modal.classList.remove('active');
        
        // Разблокируем скролл только если он был заблокирован
        if (sellModalScrollLocked) {
            enableBodyScroll();
            sellModalScrollLocked = false;
        }
    }
}

// Выбор актива для продажи
function selectSellAsset(id, symbol, type) {
    selectedSellAsset = { id, symbol, type };
    
    const display = document.getElementById('selectedSellAssetDisplay');
    if (display) display.textContent = symbol;
    
    const hiddenInput = document.getElementById('sellAssetId');
    if (hiddenInput) hiddenInput.value = id;
    
    const typeInput = document.getElementById('sellAssetType');
    if (typeInput) typeInput.value = type;
    
    // ИСПРАВЛЕНИЕ: используем loadSellData вместо loadSellLots
    if (selectedSellPriceCurrency.code) {
        loadSellData();  // ← изменено
    }
}

// Выбор валюты цены
function selectSellPriceCurrency(code) {
    selectedSellPriceCurrency = { code };
    
    const display = document.getElementById('selectedSellPriceCurrencyDisplay');
    if (display) display.textContent = code;
    
    const hiddenInput = document.getElementById('sellPriceCurrency');
    if (hiddenInput) hiddenInput.value = code;
    
    // ИСПРАВЛЕНИЕ: используем loadSellData вместо loadSellLots
    if (selectedSellAsset.id) {
        loadSellData();  // ← изменено
    }
}

// Выбор валюты комиссии
function selectSellCommissionCurrency(code) {
    const display = document.getElementById('selectedSellCommissionCurrencyDisplay');
    if (display) display.textContent = code;
    
    const hiddenInput = document.getElementById('sellCommissionCurrency');
    if (hiddenInput) hiddenInput.value = code;
    
    // Обновляем детали сделки, если есть выбранная площадка
    if (selectedSellPlatformId) {
        const selectedBalance = sellPlatformBalances.find(b => b.platform_id === selectedSellPlatformId);
        if (selectedBalance) {
            const price = parseFloat(document.getElementById('sellPrice').value.replace(/\s/g, '')) || 0;
            updateSellTransactionDetailsFromSelected(selectedBalance, price);
        }
    }
}
// Загрузка лотов (со всех площадок, сгруппированных по площадкам)
async function loadSellLots() {
    if (!selectedSellAsset.id || !selectedSellPriceCurrency.code) return;
    
    const price = parseFloat(document.getElementById('sellPrice').value.replace(/\s/g, '')) || 0;
    if (price <= 0) {
        //showNotification('warning', 'Внимание', 'Введите цену продажи');
        return;
    }
    
    const lotsContainer = document.getElementById('sellLotsContainer');
    const lotsList = document.getElementById('sellLotsList');
    const totalStats = document.getElementById('sellTotalStats');
    
    lotsContainer.style.display = 'block';
    lotsList.innerHTML = '<div style="text-align: center; padding: 30px; color: #6b7a8f;"><i class="fas fa-spinner fa-spin"></i> Загрузка балансов...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_sell_lots');
    formData.append('asset_id', selectedSellAsset.id);
    formData.append('price', price);
    formData.append('price_currency', selectedSellPriceCurrency.code);
    
    try {
        // ИСПРАВЛЕНИЕ: Отправляем запрос на get_operations.php, а не на index.php
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && result.lots) {
            sellLots = result.lots.map(lot => ({
                id: lot.id,
                platform_id: lot.platform_id,
                platform_name: lot.platform_name,
                quantity: parseFloat(lot.quantity),
                avg_price: parseFloat(lot.avg_price),
                price_currency: lot.price_currency
            }));
            
            // Рассчитываем общую статистику
            let totalQuantity = 0;
            let totalCost = 0;
            sellLots.forEach(lot => {
                totalQuantity += lot.quantity;
                totalCost += lot.quantity * lot.avg_price;
            });
            const avgPrice = totalQuantity > 0 ? totalCost / totalQuantity : 0;
            const totalValue = totalQuantity * price;
            const profit = totalValue - totalCost;
            const profitPercent = totalCost > 0 ? (profit / totalCost) * 100 : 0;
            
            totalStats.innerHTML = `
                <span>Всего доступно: ${formatAmount(totalQuantity, selectedSellAsset.symbol)} ${selectedSellAsset.symbol}</span>
                <span style="margin-left: 12px;">Средняя цена: ${formatAmount(avgPrice, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}</span>
                <span style="margin-left: 12px; color: ${profit >= 0 ? '#00a86b' : '#e53e3e'}">
                    ${profit >= 0 ? '+' : ''}${profitPercent.toFixed(1)}%
                </span>
            `;
            
            if (sellLots.length === 0) {
                lotsList.innerHTML = '<div style="text-align: center; padding: 30px; color: #6b7a8f;">Нет доступных лотов для продажи</div>';
                return;
            }
            
            // Отображаем лоты, сгруппированные по площадкам
            let html = '';
            sellLots.forEach(lot => {
                const isSelected = selectedLots.has(lot.id);
                const quantity = lot.quantity;
                const costPrice = lot.avg_price;
                const currentPrice = price;
                const profitPerUnit = currentPrice - costPrice;
                const totalProfit = profitPerUnit * quantity;
                const profitPercentLot = costPrice > 0 ? (profitPerUnit / costPrice) * 100 : 0;
                const totalCost = quantity * costPrice;
                const totalCostFormatted = formatAmount(totalCost, selectedSellPriceCurrency.code);
                
                html += `
                    <div class="sell-lot-item" data-lot-id="${lot.id}" 
                         style="background: ${isSelected ? 'var(--bg-tertiary)' : 'transparent'}; border: 1px solid var(--border-color); border-radius: 12px; padding: 12px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s;"
                         onclick="toggleSellLot('${lot.id}')">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 600;">
                                    <i class="fas fa-building" style="color: #ff9f4a; font-size: 12px; margin-right: 6px;"></i>
                                    ${lot.platform_name}
                                </div>
                                <div style="font-size: 13px; margin-top: 4px;">
                                    ${formatAmount(quantity, selectedSellAsset.symbol)} ${selectedSellAsset.symbol}
                                </div>
                                <div style="font-size: 11px; color: #6b7a8f;">Средняя цена: ${formatAmount(costPrice, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}</div>
                                <div style="font-size: 11px; color: #6b7a8f;">
                                    <i class="fas fa-shopping-cart"></i> Итого покупка: ${totalCostFormatted} ${selectedSellPriceCurrency.code}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 14px; font-weight: 500;">
                                    ${formatAmount(totalValue, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}
                                </div>
                                <div style="color: ${profitPerUnit >= 0 ? '#00a86b' : '#e53e3e'}; font-weight: 500; margin-top: 4px;">
                                    ${profitPerUnit >= 0 ? '+' : ''}${profitPercentLot.toFixed(1)}% 
                                    (${profitPerUnit >= 0 ? '+' : ''}${formatAmount(totalProfit, selectedSellPriceCurrency.code)})
                                </div>
                                ${isSelected ? '<div class="selected-indicator" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border-color); font-size: 12px; color: #00a86b;"><i class="fas fa-check-circle"></i> Выбран для продажи</div>' : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            lotsList.innerHTML = html;
            updateSellSummary();
            
        } else {
            lotsList.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">' + (result.message || 'Ошибка загрузки балансов') + '</div>';
        }
    } catch (error) {
        console.error('Error loading sell lots:', error);
        lotsList.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки: ' + error.message + '</div>';
    }
}

// Переключение выбора лота
function toggleSellLot(lotId) {
    const lotIdStr = String(lotId);
    
    if (selectedLots.has(lotIdStr)) {
        selectedLots.delete(lotIdStr);
    } else {
        const lot = sellLots.find(l => String(l.id) === lotIdStr);
        if (lot) {
            selectedLots.set(lotIdStr, lot);
        }
    }
    
    // Обновляем визуальное состояние
    const lotElement = document.querySelector(`.sell-lot-item[data-lot-id="${lotIdStr}"]`);
    if (lotElement) {
        const isSelected = selectedLots.has(lotIdStr);
        lotElement.style.background = isSelected ? 'var(--bg-tertiary)' : 'transparent';
        
        // Удаляем старую надпись, если она есть
        const existingSelectedDiv = lotElement.querySelector('.selected-indicator');
        if (existingSelectedDiv) {
            existingSelectedDiv.remove();
        }
        
        // Добавляем новую надпись, если выбран
        if (isSelected) {
            const newDiv = document.createElement('div');
            newDiv.className = 'selected-indicator';
            newDiv.style.marginTop = '8px';
            newDiv.style.paddingTop = '8px';
            newDiv.style.borderTop = '1px solid var(--border-color)';
            newDiv.style.fontSize = '12px';
            newDiv.style.color = '#00a86b';
            newDiv.innerHTML = '<i class="fas fa-check-circle"></i> Выбран для продажи';
            lotElement.appendChild(newDiv);
        }
    }
    
    updateSellSummary();
    updateSellTransactionDetails();
}

// ============================================================================
// ФУНКЦИИ ПОДТВЕРЖДЕНИЯ ОПЕРАЦИЙ
// ============================================================================

async function confirmDeposit() {
    const platformId = document.getElementById('depositPlatformId').value;
    const amount = getNumericValue(document.getElementById('depositAmount').value);
    const currency = document.getElementById('depositCurrency').value.toUpperCase();
    const date = document.getElementById('depositDate').value;
    const notes = document.getElementById('depositNotes').value;  // ← ДОБАВЬТЕ

    if (!platformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку');
        return;
    }

    if (!amount || amount <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректную сумму');
        return;
    }

    if (!currency) {
        showNotification('error', 'Ошибка', 'Выберите валюту');
        return;
    }

    if (!date) {
        showNotification('error', 'Ошибка', 'Выберите дату пополнения');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_deposit');
    formData.append('platform_id', platformId);
    formData.append('amount', amount);
    formData.append('currency', currency);
    formData.append('deposit_date', date);
    formData.append('notes', notes);  // ← ДОБАВЬТЕ

    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeDepositModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос');
    }
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

    // Проверка: выбранная площадка
    if (!platformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку для покупки/продажи');
        return;
    }

    // Проверка: выбранный актив
    if (!assetId) {
        showNotification('error', 'Ошибка', 'Выберите актив для покупки/продажи');
        return;
    }

    // Проверка: количество
    if (!quantity || quantity <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректное количество (должно быть больше 0)');
        return;
    }

    // Проверка: цена
    if (!price || price <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректную цену (должна быть больше 0)');
        return;
    }

    // Проверка: валюта цены
    if (!priceCurrency) {
        showNotification('error', 'Ошибка', 'Выберите валюту цены');
        return;
    }

    // Проверка: для покупки нужна площадка списания
    if (operationType === 'buy') {
        if (!fromPlatformId) {
            showNotification('error', 'Ошибка', 'Выберите площадку, с которой будут списаны средства');
            return;
        }
    }

    // Проверка: комиссия
    if (commission > 0 && !commissionCurrency) {
        showNotification('error', 'Ошибка', 'Если указана комиссия, выберите валюту комиссии');
        return;
    }

    // Показываем индикатор загрузки
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
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeTradeModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            // Показываем подробную ошибку
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос. Проверьте подключение к интернету.');
    } finally {
        // Восстанавливаем кнопку
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    }
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

    // Проверки
    if (!fromPlatformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку отправителя');
        return;
    }

    if (!toPlatformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку получателя');
        return;
    }

    if (!assetId) {
        showNotification('error', 'Ошибка', 'Выберите актив');
        return;
    }

    if (!quantity || quantity <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректное количество');
        return;
    }

    if (commission > 0 && !commissionCurrency) {
        showNotification('error', 'Ошибка', 'Выберите валюту комиссии');
        return;
    }

    const asset = assetsData.find(a => a.id == assetId);
    const assetType = asset ? asset.type : null;
    
    // Для криптовалют проверяем сети
    if (assetType === 'crypto') {
        if (!fromNetwork) {
            showNotification('error', 'Ошибка', 'Укажите сеть отправителя');
            return;
        }
        if (!toNetwork) {
            showNotification('error', 'Ошибка', 'Укажите сеть получателя');
            return;
        }
    }

    // Показываем индикатор загрузки
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
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        
        // Получаем текст ответа для отладки
        const responseText = await response.text();
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            showNotification('error', 'Ошибка сервера', 'Сервер вернул некорректный ответ: ' + responseText.substring(0, 200));
            return;
        }
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeTransferModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            // Показываем подробную ошибку
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос: ' + error.message);
    } finally {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    }
}

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
        case 'info': iconClass = 'fas fa-info-circle'; break;
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
            <div class="notification-progress-bar" id="progress-${notificationId}" style="width: 100%; height: 2px;"></div>
        </div>
    `;
    
    container.appendChild(notification);
    
    const progressBar = document.getElementById(`progress-${notificationId}`);
    const startTime = Date.now();
    
    function updateProgress() {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, duration - elapsed);
        const width = (remaining / duration) * 100;
        
        if (progressBar) {
            progressBar.style.width = width + '%';
        }
        
        if (remaining > 0) {
            requestAnimationFrame(updateProgress);
        } else {
            closeNotification(notificationId);
        }
    }
    
    requestAnimationFrame(updateProgress);
    
    notification.dataset.timeout = setTimeout(() => {
        closeNotification(notificationId);
    }, duration);
}

function closeNotification(notificationId) {
    const notification = document.getElementById(notificationId);
    if (!notification) return;
    
    if (notification.dataset.timeout) {
        clearTimeout(parseInt(notification.dataset.timeout));
    }
    
    notification.classList.add('fade-out');
    
    setTimeout(() => {
        if (notification && notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ДОБАВЛЕНИЯ НОВЫХ ЭЛЕМЕНТОВ
// ============================================================================

let pendingCurrencyCode = '';
let pendingCurrencyMode = 'default';
let pendingPlatformName = '';

async function addNewPlatformAndSelect(platformName, context = 'default') {
    if (!platformName) return;
    
    const newName = platformName.trim();
    
    const exists = platformsData.some(p => p.name.toLowerCase() === newName.toLowerCase());
    
    if (exists) {
        const platform = platformsData.find(p => p.name.toLowerCase() === newName.toLowerCase());
        
        if (context === 'transfer') {
            if (currentModalContext.subMode === 'from') {
                selectFromPlatform(platform.id, platform.name);
            } else if (currentModalContext.subMode === 'to') {
                selectToPlatform(platform.id, platform.name);
            }
        } else if (context === 'buy' && currentModalContext.subMode === 'from') {
            selectTradeFromPlatform(platform.id, platform.name);
        } else if (context === 'buy' || context === 'sell') {
            selectTradePlatform(platform.id, platform.name);
        } else if (context === 'deposit') {
            selectPlatform(platform.id, platform.name);
        }
        
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
    if (confirmBtn) {
        confirmBtn.style.background = addScheme.confirmButton;
    }
    
    const nameInput = document.getElementById('newPlatformName');
    const countryInput = document.getElementById('newPlatformCountry');
    const typeHidden = document.getElementById('newPlatformType');
    
    if (!nameInput) {
        showNotification('error', 'Ошибка', 'Не найден элемент формы');
        return;
    }
    
    nameInput.value = platformName;
    if (countryInput) countryInput.value = '';
    
    document.querySelectorAll('.platform-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (typeHidden) typeHidden.value = '';
    
    const parentModal = document.getElementById('platformSelectModal');
    if (parentModal && parentModal.classList.contains('active')) {
        parentModal.classList.remove('active');
    }
    
    const modal = document.getElementById('addPlatformModal');
    if (modal) {
        modal.classList.add('active');
        
        setTimeout(() => {
            if (countryInput) countryInput.focus();
        }, 100);
    }
}

function closeAddPlatformModal() {
    const modal = document.getElementById('addPlatformModal');
    if (modal) {
        modal.classList.remove('active');
        pendingPlatformName = '';
    }
}

function setActivePlatformType(type) {
    document.querySelectorAll('.platform-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const selectedBtn = document.querySelector(`.platform-type-btn[data-type="${type}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    const hiddenInput = document.getElementById('newPlatformType');
    if (hiddenInput) {
        hiddenInput.value = type;
    }
}

function getSelectedPlatformType() {
    const hiddenInput = document.getElementById('newPlatformType');
    return hiddenInput ? hiddenInput.value : '';
}

async function saveNewPlatform() {
    const name = document.getElementById('newPlatformName').value;
    const type = getSelectedPlatformType();
    const country = document.getElementById('newPlatformCountry').value;

    if (!name.trim()) {
        showNotification('error', 'Ошибка', 'Название площадки обязательно');
        return;
    }

    if (!type) {
        showNotification('error', 'Ошибка', 'Выберите тип площадки');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_platform_full');
    formData.append('name', name);
    formData.append('type', type);
    formData.append('country', country);

    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();

        if (result.success && result.platform_id) {
            showNotification('success', 'Успешно', 'Площадка добавлена');
            
            // Добавляем в массив
            platformsData.push({
                id: result.platform_id,
                name: name,
                type: type
            });
            
            // ОБНОВЛЯЕМ ВСЕ СПИСКИ ПЛОЩАДОК
            refreshAllPlatformLists();
            
            // ДОПОЛНИТЕЛЬНО: обновляем конкретные элементы для transferModal
            // Обновляем популярные площадки для отправителя и получателя
            updateTransferModalPlatforms();
            
            // Определяем, в каком режиме мы добавляли площадку
            const context = currentModalContext.source;
            const subMode = currentModalContext.subMode;
            
            // Выбираем добавленную площадку в зависимости от контекста
            if (context === 'transfer') {
                if (subMode === 'from') {
                    selectFromPlatform(result.platform_id, name);
                } else if (subMode === 'to') {
                    selectToPlatform(result.platform_id, name);
                }
            } else if (context === 'buy' && subMode === 'from') {
                selectTradeFromPlatform(result.platform_id, name);
            } else if (context === 'buy' || context === 'sell') {
                selectTradePlatform(result.platform_id, name);
            } else if (context === 'deposit') {
                selectPlatform(result.platform_id, name);
            }
            
            closeAddPlatformModal();
            
            // Если модальное окно выбора площадки было открыто, закрываем его
            const platformSelectModal = document.getElementById('platformSelectModal');
            if (platformSelectModal && platformSelectModal.classList.contains('active')) {
                closePlatformModal();
            }
        } else {
            showNotification('error', 'Ошибка', result.message || 'Не удалось добавить площадку');
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось добавить площадку');
    }
}

function refreshAllPlatformLists() {
    // Обновляем список в модальном окне выбора площадки (если оно открыто)
    const platformModal = document.getElementById('platformSelectModal');
    if (platformModal && platformModal.classList.contains('active')) {
        const searchInput = document.getElementById('platformSearch');
        if (searchInput) {
            filterPlatforms(searchInput.value);
        } else {
            filterPlatforms('');
        }
    }
    
    // Обновляем популярные площадки во всех модальных окнах
    const popularPlatformsContainers = [
        'depositPopularPlatforms',
        'tradePopularPlatforms',
        'tradeFromPopularPlatforms',
        'transferFromPopularPlatforms',
        'transferToPopularPlatforms',
        'limitPopularPlatforms'
    ];
    
    popularPlatformsContainers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container) {
            const popularPlatforms = platformsData.slice(0, 5);
            let onclickHandler = '';
            
            // Определяем правильный обработчик для каждого контейнера
            if (containerId === 'transferFromPopularPlatforms') {
                onclickHandler = 'selectFromPlatform';
            } else if (containerId === 'transferToPopularPlatforms') {
                onclickHandler = 'selectToPlatform';
            } else if (containerId === 'tradeFromPopularPlatforms') {
                onclickHandler = 'selectTradeFromPlatform';
            } else if (containerId === 'tradePopularPlatforms') {
                onclickHandler = 'selectTradePlatform';
            } else if (containerId === 'depositPopularPlatforms') {
                onclickHandler = 'selectPlatform';
            } else if (containerId === 'limitPopularPlatforms') {
                onclickHandler = 'selectLimitPlatform';
            } else {
                onclickHandler = 'selectPlatformFromList';
            }
            
            container.innerHTML = popularPlatforms.map(platform => `
                <button type="button" class="quick-platform-btn" onclick="${onclickHandler}('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')">
                    ${platform.name}
                </button>
            `).join('');
        }
    });
    
    // Обновляем скрытые списки площадок в модальных окнах
    const platformsLists = [
        'depositPlatformsList',
        'transferFromPlatformsList',
        'transferToPlatformsList'
    ];
    
    platformsLists.forEach(listId => {
        const list = document.getElementById(listId);
        if (list && list.style.display !== 'none') {
            const scheme = getColorScheme();
            const platformScheme = scheme.platform || modalColorSchemes.default.platform;
            
            let onclickHandler = '';
            if (listId === 'transferFromPlatformsList') {
                onclickHandler = 'selectFromPlatform';
            } else if (listId === 'transferToPlatformsList') {
                onclickHandler = 'selectToPlatform';
            } else {
                onclickHandler = 'selectPlatform';
            }
            
            list.innerHTML = platformsData.map(platform => `
                <div onclick="${onclickHandler}('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" 
                     style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span>
                </div>
            `).join('');
        }
    });
    
    // Дополнительно обновляем для transferModal
    updateTransferModalPlatforms();
}

function updateTransferModalPlatforms() {
    // Обновляем популярные площадки для отправителя
    const fromContainer = document.getElementById('transferFromPopularPlatforms');
    if (fromContainer) {
        const popularPlatforms = platformsData.slice(0, 5);
        fromContainer.innerHTML = popularPlatforms.map(platform => `
            <button type="button" class="quick-platform-btn" onclick="selectFromPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')">
                ${platform.name}
            </button>
        `).join('');
    }
    
    // Обновляем популярные площадки для получателя
    const toContainer = document.getElementById('transferToPopularPlatforms');
    if (toContainer) {
        const popularPlatforms = platformsData.slice(0, 5);
        toContainer.innerHTML = popularPlatforms.map(platform => `
            <button type="button" class="quick-platform-btn" onclick="selectToPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')">
                ${platform.name}
            </button>
        `).join('');
    }
    
    // Обновляем скрытые списки площадок
    const fromList = document.getElementById('transferFromPlatformsList');
    if (fromList && fromList.style.display !== 'none') {
        const scheme = getColorScheme();
        const platformScheme = scheme.platform || modalColorSchemes.default.platform;
        fromList.innerHTML = platformsData.map(platform => `
            <div onclick="selectFromPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" 
                 style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px;">
                <span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span>
            </div>
        `).join('');
    }
    
    const toList = document.getElementById('transferToPlatformsList');
    if (toList && toList.style.display !== 'none') {
        const scheme = getColorScheme();
        const platformScheme = scheme.platform || modalColorSchemes.default.platform;
        toList.innerHTML = platformsData.map(platform => `
            <div onclick="selectToPlatform('${platform.id}', '${platform.name.replace(/'/g, "\\'")}')" 
                 style="padding: 10px; cursor: pointer; border-radius: 8px; margin-bottom: 2px; display: flex; align-items: center; gap: 10px;">
                <span style="font-weight: 600; color: ${platformScheme.listItemColor};">${platform.name}</span>
            </div>
        `).join('');
    }
}

function addNewCurrencyAndSelect(currencyCode, context = 'default', mode = 'default') {
    if (!currencyCode) return;
    
    const newCode = currencyCode.trim().toUpperCase();
    
    const exists = allCurrencies.some(c => c.code.toUpperCase() === newCode);
    
    if (exists) {
        const currency = allCurrencies.find(c => c.code.toUpperCase() === newCode);
        
        if (context === 'transfer' && mode === 'commission') {
            selectCommissionCurrency(currency.code);
        } else if ((context === 'buy' || context === 'sell') && mode === 'price') {
            selectTradePriceCurrency(currency.code);
        } else if ((context === 'buy' || context === 'sell') && mode === 'commission') {
            selectTradeCommissionCurrency(currency.code);
        } else if (context === 'deposit') {
            selectCurrency(currency.code, currency.name);
        }
        
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
    if (confirmBtn) {
        confirmBtn.style.background = addScheme.confirmButton;
    }
    
    const codeInput = document.getElementById('newCurrencyCode');
    const nameInput = document.getElementById('newCurrencyName');
    const symbolInput = document.getElementById('newCurrencySymbol');
    const typeHidden = document.getElementById('newCurrencyType');
    
    if (!codeInput) {
        showNotification('error', 'Ошибка', 'Не найден элемент формы');
        return;
    }
    
    codeInput.value = currencyCode.toUpperCase();
    if (nameInput) nameInput.value = '';
    if (symbolInput) symbolInput.value = '';
    
    document.querySelectorAll('.currency-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (typeHidden) typeHidden.value = '';
    
    const parentModal = document.getElementById('currencySelectModal');
    if (parentModal && parentModal.classList.contains('active')) {
        parentModal.classList.remove('active');
    }
    
    const modal = document.getElementById('addCurrencyModal');
    if (modal) {
        modal.classList.add('active');
        
        setTimeout(() => {
            if (nameInput) nameInput.focus();
        }, 100);
    }
}

function closeAddCurrencyModal() {
    const modal = document.getElementById('addCurrencyModal');
    if (modal) {
        modal.classList.remove('active');
        pendingCurrencyCode = '';
        pendingCurrencyMode = 'default';
    }
}

function setActiveCurrencyType(type) {
    document.querySelectorAll('.currency-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const selectedBtn = document.querySelector(`.currency-type-btn[data-type="${type}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    const hiddenInput = document.getElementById('newCurrencyType');
    if (hiddenInput) {
        hiddenInput.value = type;
    }
}

function getSelectedCurrencyType() {
    const hiddenInput = document.getElementById('newCurrencyType');
    return hiddenInput ? hiddenInput.value : '';
}

async function saveNewCurrency() {
    const code = document.getElementById('newCurrencyCode').value.toUpperCase();
    const name = document.getElementById('newCurrencyName').value.trim();
    const type = getSelectedCurrencyType();
    const symbol = document.getElementById('newCurrencySymbol').value.trim();

    if (!code) {
        showNotification('error', 'Ошибка', 'Код валюты обязателен');
        return;
    }

    if (!name) {
        showNotification('error', 'Ошибка', 'Название валюты обязательно');
        return;
    }

    if (!type) {
        showNotification('error', 'Ошибка', 'Выберите тип валюты');
        return;
    }

    //showNotification('info', 'Сохранение', 'Добавляем валюту...');

    const formData = new FormData();
    formData.append('action', 'add_currency_full');
    formData.append('code', code);
    formData.append('name', name);
    formData.append('type', type);
    formData.append('symbol', symbol);

    try {
        const response = await fetch(API_URL_PHP, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();

        if (result.success) {
            showNotification('success', 'Успешно', 'Валюта добавлена');
            
            allCurrencies.push({
                code: code,
                name: name,
                symbol: symbol,
                type: type
            });
            
            if (pendingCurrencyMode === 'commission') {
                selectCommissionCurrency(code);
            } else if (pendingCurrencyMode === 'price') {
                selectTradePriceCurrency(code);
            } else if (pendingCurrencyMode === 'commission_trade') {
                selectTradeCommissionCurrency(code);
            } else {
                selectCurrency(code, name);
            }
            
            closeAddCurrencyModal();
        } else {
            showNotification('error', 'Ошибка', result.message || 'Не удалось добавить валюту');
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось добавить валюту');
    }
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

async function addNewTradeAsset(assetSymbol) {
    if (!assetSymbol) return;
    
    const newSymbol = assetSymbol.trim().toUpperCase();
    
    const exists = assetsData.some(a => a.symbol.toUpperCase() === newSymbol);
    
    if (exists) {
        const asset = assetsData.find(a => a.symbol.toUpperCase() === newSymbol);
        selectTradeAssetFromModal(asset.id, asset.symbol, asset.type);
        return;
    }
    
    openAddAssetModal(newSymbol);
}

function openAddAssetModal(assetSymbol) {
    const symbolInput = document.getElementById('newAssetSymbol');
    const nameInput = document.getElementById('newAssetName');
    const typeHidden = document.getElementById('newAssetType');
    
    if (!symbolInput) {
        showNotification('error', 'Ошибка', 'Не найден элемент формы');
        return;
    }
    
    symbolInput.value = assetSymbol;
    if (nameInput) nameInput.value = '';
    
    document.querySelectorAll('.asset-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (typeHidden) typeHidden.value = '';
    
    const parentModal = document.getElementById('currencySelectModal');
    if (parentModal) {
        parentModal.classList.remove('active');
    }
    
    const modal = document.getElementById('addAssetModal');
    if (modal) {
        modal.classList.add('active');
        
        setTimeout(() => {
            if (nameInput) nameInput.focus();
        }, 100);
    }
}

function closeAddAssetModal() {
    const modal = document.getElementById('addAssetModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function setActiveAssetType(type) {
    // Убираем активный класс у всех кнопок типа актива
    document.querySelectorAll('.asset-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Добавляем активный класс выбранной кнопке
    const selectedBtn = document.querySelector(`.asset-type-btn[data-type="${type}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    // Сохраняем выбранный тип
    const hiddenInput = document.getElementById('newAssetType');
    if (hiddenInput) {
        hiddenInput.value = type;
    }
    
    // ПОКАЗЫВАЕМ/СКРЫВАЕМ ВЫБОР СЕКТОРА
    const sectorGroup = document.getElementById('sectorSelectGroup');
    if (sectorGroup) {
        if (type === 'stock' || type === 'etf') {
            sectorGroup.style.display = 'block';
        } else {
            sectorGroup.style.display = 'none';
            // Сбрасываем выбранный сектор
            document.querySelectorAll('.sector-option-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById('newAssetSector').value = '';
        }
    }
    
    // ========== НОВЫЙ КОД: ПОКАЗЫВАЕМ/СКРЫВАЕМ ВЫБОР РЫНКА ==========
    const marketGroup = document.getElementById('marketSelectGroup');
    if (marketGroup) {
        if (type === 'stock' || type === 'etf' || type === 'bond') {
            marketGroup.style.display = 'block';
        } else {
            marketGroup.style.display = 'none';
            // Сбрасываем выбранный рынок
            document.querySelectorAll('.market-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById('newAssetMarket').value = '';
        }
    }
}

function setActiveMarket(market) {
    // Убираем активный класс у всех кнопок рынка
    document.querySelectorAll('.market-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Добавляем активный класс выбранной кнопке
    const selectedBtn = document.querySelector(`.market-type-btn[data-market="${market}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    // Сохраняем выбранный рынок
    const hiddenInput = document.getElementById('newAssetMarket');
    if (hiddenInput) {
        hiddenInput.value = market;
    }
}

function getSelectedAssetType() {
    const hiddenInput = document.getElementById('newAssetType');
    return hiddenInput ? hiddenInput.value : '';
}

async function saveNewAsset() {
    const symbol = document.getElementById('newAssetSymbol').value.toUpperCase();
    const name = document.getElementById('newAssetName').value.trim();
    const type = getSelectedAssetType();
    const sector = document.getElementById('newAssetSector').value;
    const market = document.getElementById('newAssetMarket').value; // Добавлено: выбранный рынок (ru/foreign)
    
    // АВТОМАТИЧЕСКОЕ ОПРЕДЕЛЕНИЕ ВАЛЮТЫ НА ОСНОВЕ РЫНКА
    let currencyCode = null;
    
    if (type === 'stock' || type === 'etf' || type === 'bond') {
        if (market === 'ru') {
            currencyCode = 'RUB';
        } else if (market === 'foreign') {
            currencyCode = 'USD';
        }
    } else if (type === 'crypto') {
        currencyCode = 'USD';
    } else if (type === 'currency') {
        currencyCode = symbol;
    }
    
    // ПРОВЕРКА: для акций, ETF и облигаций рынок обязателен
    if ((type === 'stock' || type === 'etf' || type === 'bond') && !market) {
        showNotification('error', 'Ошибка', 'Выберите рынок (РФ или Иностранный)');
        return;
    }
    
    // ПРОВЕРКА: для акций и ETF сектор обязателен
    if ((type === 'stock' || type === 'etf') && !sector) {
        showNotification('error', 'Ошибка', 'Выберите сектор для акции/ETF');
        return;
    }

    if (!symbol) {
        showNotification('error', 'Ошибка', 'Символ актива обязателен');
        return;
    }

    if (!name) {
        showNotification('error', 'Ошибка', 'Название актива обязательно');
        return;
    }

    if (!type) {
        showNotification('error', 'Ошибка', 'Выберите тип актива');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_asset_full');
    formData.append('symbol', symbol);
    formData.append('name', name);
    formData.append('type', type);
    formData.append('currency_code', currencyCode || '');
    formData.append('sector', sector || '');
    formData.append('market', market); // для отладки/логирования

    try {
        const response = await fetch(API_URL_PHP, {
            method: 'POST',
            body: formData
        });
        
        // Получаем текст ответа для отладки
        const responseText = await response.text();
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            showNotification('error', 'Ошибка сервера', 'Сервер вернул некорректный ответ: ' + responseText.substring(0, 200));
            return;
        }

        if (result.success && result.asset_id) {
            showNotification('success', 'Успешно', 'Актив добавлен');
            
            // Добавляем в глобальный массив
            assetsData.push({
                id: result.asset_id,
                symbol: symbol,
                name: name,
                type: type,
                currency_code: currencyCode,
                sector: sector
            });
            
            // Выбираем актив в зависимости от контекста
            if (currentModalContext.source === 'transfer') {
                selectAsset(result.asset_id, symbol);
            } else {
                selectTradeAsset(result.asset_id, symbol, type);
            }
            
            closeAddAssetModal();
            closeCurrencyModal();
        } else {
            showNotification('error', 'Ошибка', result.message || 'Не удалось добавить актив');
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось добавить актив: ' + error.message);
    }
}

function setActiveMarket(market) {
    // Убираем активный класс у всех кнопок рынка
    document.querySelectorAll('.market-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Добавляем активный класс выбранной кнопке
    const selectedBtn = document.querySelector(`.market-type-btn[data-market="${market}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    // Сохраняем выбранный рынок
    const hiddenInput = document.getElementById('newAssetMarket');
    if (hiddenInput) {
        hiddenInput.value = market;
    }
}

function closeTradeModal() {
    tradeModal.classList.remove('active');
}

function closeExpenseModal() {
    const modal = document.getElementById('expenseModal');
    if (modal) {
        modal.classList.remove('active');
        enableBodyScroll();
    }
    // Сбрасываем заголовок баланса
    resetExpenseBalanceTitle();
    hideExpensePlatformBalance();
    expensePlatformBalanceData = null;
}

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ И ОБРАБОТЧИКИ СОБЫТИЙ
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Обработчики для кнопок выбора рынка
    document.querySelectorAll('.market-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const market = this.getAttribute('data-market');
            setActiveMarket(market);
        });
    });

    // Закрытие модального окна продажи по клику на оверлей
    const sellModalElement = document.getElementById('sellModal');
    if (sellModalElement) {
        sellModalElement.addEventListener('click', (e) => {
            if (e.target === sellModalElement) {
                closeSellModal();
            }
        });
    }

    // Обработчики для нового модального окна
    document.getElementById('closeSellModalBtn')?.addEventListener('click', closeSellModal);
    document.getElementById('cancelSellBtn')?.addEventListener('click', closeSellModal);
    document.getElementById('confirmSellBtn')?.addEventListener('click', confirmSell);

    document.getElementById('selectSellAssetBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openAssetModal('sell', 'asset');
    });

    document.getElementById('selectSellPriceCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCurrencyModal('sell', 'price');
    });

    document.getElementById('selectSellCommissionCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCurrencyModal('sell', 'commission');
    });

    // Обработчики для полей
    document.getElementById('sellPrice')?.addEventListener('input', function() {
    formatInput(this);
        if (selectedSellAsset.id && selectedSellPriceCurrency.code) {
            loadSellData();  // ← изменено
        }
    });

    document.getElementById('sellCommission')?.addEventListener('input', function() {
        formatInput(this);
        updateSellTransactionDetails();
    });

    // Кнопка выбора площадки для расходов
    document.getElementById('selectExpensePlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('expense', null);
    });

    // Кнопка выбора актива для расходов
    document.getElementById('selectExpenseAssetBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openAssetModal('expense', 'asset');
    });

    // Кнопка добавления категории
    document.getElementById('addExpenseCategoryBtn')?.addEventListener('click', openAddExpenseCategoryModal);

    // Обработчики для категорий расходов
    document.getElementById('closeAddCategoryModalBtn')?.addEventListener('click', closeAddExpenseCategoryModal);
    document.getElementById('cancelAddCategoryBtn')?.addEventListener('click', closeAddExpenseCategoryModal);
    document.getElementById('confirmAddCategoryBtn')?.addEventListener('click', saveExpenseCategory);

    // Обновление превью при изменении
    document.getElementById('newCategoryIcon')?.addEventListener('input', updateCategoryPreview);
    document.getElementById('newCategoryColor')?.addEventListener('input', updateCategoryPreview);

    // Выбор иконки
    document.getElementById('selectIconBtn')?.addEventListener('click', openIconSelectModal);
    document.getElementById('closeIconModalBtn')?.addEventListener('click', closeIconSelectModal);
    document.getElementById('closeIconModalFooterBtn')?.addEventListener('click', closeIconSelectModal);

    // Обработчики для выбора иконок
    document.querySelectorAll('.icon-option').forEach(icon => {
        icon.addEventListener('click', function() {
            selectIcon(this.dataset.icon);
        });
    });

    // Поиск иконок
    document.getElementById('iconSearch')?.addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.icon-option').forEach(icon => {
            const text = icon.textContent.toLowerCase();
            if (text.includes(search) || icon.dataset.icon.includes(search)) {
                icon.style.display = 'block';
            } else {
                icon.style.display = 'none';
            }
        });
    });

    // Кнопка расходов
    document.querySelectorAll('.operation-type-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.dataset.type;
            if (type === 'deposit') openDepositModal();
            else if (type === 'buy') openBuyModal();
            else if (type === 'sell') openSellModal();
            else if (type === 'transfer') openTransferModal();
            else if (type === 'expense') openExpenseModal(); // Добавляем обработчик расходов
        });
    });

    // Кнопка просмотра всех расходов (можно добавить в карточку или отдельную кнопку)
    document.getElementById('viewAllExpensesBtn')?.addEventListener('click', openExpensesListModal);

    // Обработчики для модального окна расходов
    document.getElementById('closeExpenseModalBtn')?.addEventListener('click', closeExpenseModal);
    document.getElementById('cancelExpenseBtn')?.addEventListener('click', closeExpenseModal);
    document.getElementById('confirmExpenseBtn')?.addEventListener('click', saveExpense);

    // Обработчики для списка расходов
    document.getElementById('closeExpensesListModalBtn')?.addEventListener('click', closeExpensesListModal);
    document.getElementById('closeExpensesListModalFooterBtn')?.addEventListener('click', closeExpensesListModal);
    document.getElementById('addNewExpenseBtn')?.addEventListener('click', function() {
        closeExpensesListModal();
        openExpenseModal();
    });

    // Выбор валюты для расходов
    document.getElementById('selectExpenseCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('expense', 'currency');
        openCurrencyModal('expense', 'price');
    });

    // Кнопка "Продать всё"
    document.getElementById('sellQuickFillAllBtn')?.addEventListener('click', fillSellAll);

    // Кнопка "По средней цене"
    document.getElementById('sellQuickFillAvgBtn')?.addEventListener('click', fillSellByAvgPrice);

    // Обработчики для модального окна типов криптовалют
    document.getElementById('closeCryptoTypeModalBtn')?.addEventListener('click', closeCryptoTypeModal);
    document.getElementById('closeCryptoTypeModalFooterBtn')?.addEventListener('click', closeCryptoTypeModal);

    // Закрытие по клику на overlay
    const cryptoTypeModal = document.getElementById('cryptoTypeModal');
    if (cryptoTypeModal) {
        cryptoTypeModal.addEventListener('click', (e) => {
            if (e.target === cryptoTypeModal) {
                closeCryptoTypeModal();
            }
        });
    }

    // Обработчики для модального окна активов сектора
    document.getElementById('closeSectorAssetsModalBtn')?.addEventListener('click', closeSectorAssetsModal);
    document.getElementById('closeSectorAssetsModalFooterBtn')?.addEventListener('click', closeSectorAssetsModal);

    // Закрытие по клику на overlay
    const sectorAssetsModal = document.getElementById('sectorAssetsModal');
    if (sectorAssetsModal) {
        sectorAssetsModal.addEventListener('click', (e) => {
            if (e.target === sectorAssetsModal) {
                closeSectorAssetsModal();
            }
        });
    }

    // Обработчики для кнопок выбора сектора (с классом sector-option-btn)
    document.querySelectorAll('.sector-option-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sector = this.dataset.sector;
            
            // Убираем активный класс у всех кнопок сектора
            document.querySelectorAll('.sector-option-btn').forEach(b => {
                b.classList.remove('active');
            });
            
            // Добавляем активный класс текущей кнопке
            this.classList.add('active');
            
            // Сохраняем выбранный сектор
            document.getElementById('newAssetSector').value = sector;
        });
    });

    // Обработчики для модального окна активов площадки
    document.getElementById('closePlatformAssetsModalBtn')?.addEventListener('click', closePlatformAssetsModal);
    document.getElementById('closePlatformAssetsModalFooterBtn')?.addEventListener('click', closePlatformAssetsModal);

    // Закрытие по клику на overlay
    const platformAssetsModal = document.getElementById('platformAssetsModal');
    if (platformAssetsModal) {
        platformAssetsModal.addEventListener('click', (e) => {
            if (e.target === platformAssetsModal) {
                closePlatformAssetsModal();
            }
        });
    }

    // Закрытие по ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            // Закрываем модальное окно расходов
            const expenseModal = document.getElementById('expenseModal');
            if (expenseModal && expenseModal.classList.contains('active')) {
                closeExpenseModal();
            }
            // Закрываем модальное окно добавления категории
            const addCategoryModal = document.getElementById('addExpenseCategoryModal');
            if (addCategoryModal && addCategoryModal.classList.contains('active')) {
                closeAddExpenseCategoryModal();
            }
            const sellModal = document.getElementById('sellModal');
            if (sellModal && sellModal.classList.contains('active')) {
                closeSellModal();
            }
        }
    });

    // Закрытие по ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (platformAssetsModal?.classList.contains('active')) {
                closePlatformAssetsModal();
            }
            if (cryptoTypeModal?.classList.contains('active')) {
                closeCryptoTypeModal();
            }
        }
    });

    // Кнопка выбора сети в модальном окне покупки/продажи
    document.getElementById('selectTradeNetworkBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openTradeNetworkModal();
    });

    // Рендерим популярные сети для торговли
    renderPopularNetworksForTrade();

    // Кнопки выбора сети в переводе
    document.getElementById('selectFromNetworkBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openNetworkModal('from');
    });

    document.getElementById('selectToNetworkBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openNetworkModal('to');
    });

    // Обработчики для модальных окон сетей
    document.getElementById('closeNetworkModalBtn')?.addEventListener('click', closeNetworkModal);
    document.getElementById('closeAddNetworkModalBtn')?.addEventListener('click', closeAddNetworkModal);
    document.getElementById('cancelAddNetworkBtn')?.addEventListener('click', closeAddNetworkModal);
    document.getElementById('confirmAddNetworkBtn')?.addEventListener('click', saveNewNetwork);

    // Закрытие по клику на overlay
    const networkModal = document.getElementById('networkSelectModal');
    if (networkModal) {
        networkModal.addEventListener('click', (e) => {
            if (e.target === networkModal) {
                closeNetworkModal();
            }
        });
    }

    const addNetworkModal = document.getElementById('addNetworkModal');
    if (addNetworkModal) {
        addNetworkModal.addEventListener('click', (e) => {
            if (e.target === addNetworkModal) {
                closeAddNetworkModal();
            }
        });
    }

    // Поиск сетей
    document.getElementById('networkSearch')?.addEventListener('input', function(e) {
        filterNetworksForSelect(e.target.value);
    });

    // Enter в поиске сетей
    document.getElementById('networkSearch')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const searchText = this.value.trim();
            if (searchText) {
                addNewNetworkFromModal(searchText);
            }
        }
    });

    // Рендерим популярные сети
    renderPopularNetworksForTransfer();

    // Закрытие по ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (document.getElementById('networkSelectModal')?.classList.contains('active')) {
                closeNetworkModal();
            }
            if (document.getElementById('addNetworkModal')?.classList.contains('active')) {
                closeAddNetworkModal();
            }
        }
    });

    // Обработчики для модальных окон заметок
    document.getElementById('closeNoteModalBtn')?.addEventListener('click', closeNoteModal);
    document.getElementById('cancelNoteBtn')?.addEventListener('click', closeNoteModal);
    document.getElementById('confirmNoteBtn')?.addEventListener('click', saveNote);

    document.getElementById('closeArchivedModalBtn')?.addEventListener('click', closeArchivedNotesModal);
    document.getElementById('closeArchivedModalFooterBtn')?.addEventListener('click', closeArchivedNotesModal);

    document.getElementById('closeConfirmDeleteBtn')?.addEventListener('click', closeConfirmDeleteModal);
    document.getElementById('cancelDeleteBtn')?.addEventListener('click', closeConfirmDeleteModal);
    document.getElementById('confirmDeleteBtn')?.addEventListener('click', confirmDeleteNote);

    // Загрузка заметок
    loadNotes();

    // Кнопка выбора площадки для лимитного ордера
    document.getElementById('selectLimitPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('limit', 'platform');
        openPlatformModal('limit', null);
    });

    // Кнопка выбора актива для лимитного ордера
    document.getElementById('selectLimitAssetBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openAssetModal('limit', 'asset');
    });

    // Кнопка выбора валюты для лимитного ордера
    document.getElementById('selectLimitCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        setModalContext('limit', 'currency');
        openCurrencyModal('limit', 'price');
    });

    // Переключение типа операции
    document.querySelectorAll('.limit-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.limit-type-btn').forEach(b => {
                b.style.opacity = '0.7';
                b.style.border = 'none';
            });
            this.style.opacity = '1';
            this.style.border = '2px solid white';
        });
    });

    // Расчет суммы при изменении полей
    document.getElementById('limitQuantity')?.addEventListener('input', updateLimitTotalEstimate);
    document.getElementById('limitPrice')?.addEventListener('input', updateLimitTotalEstimate);

    // Форматирование числовых полей
    const limitInputs = ['limitQuantity', 'limitPrice'];
    limitInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', function() { formatInput(this); });
            input.addEventListener('blur', function() { formatInput(this); });
        }
    });

    // Обработчики для модального окна исполнения
    document.getElementById('closeExecuteModalBtn')?.addEventListener('click', closeExecuteModal);
    document.getElementById('cancelExecuteBtn')?.addEventListener('click', closeExecuteModal);
    document.getElementById('confirmExecuteBtn')?.addEventListener('click', confirmExecuteOrder);

    // Обработчики для модального окна отмены
    document.getElementById('closeCancelModalBtn')?.addEventListener('click', closeCancelModal);
    document.getElementById('cancelCancelBtn')?.addEventListener('click', closeCancelModal);
    document.getElementById('confirmCancelBtn')?.addEventListener('click', confirmCancelOrder);

    // Закрытие по клику на overlay
    if (executeModal) {
        executeModal.addEventListener('click', (e) => {
            if (e.target === executeModal) closeExecuteModal();
        });
    }

    if (cancelModal) {
        cancelModal.addEventListener('click', (e) => {
            if (e.target === cancelModal) closeCancelModal();
        });
    }

    // Кнопка выбора площадки для торговли
    document.getElementById('selectTradePlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openPlatformModal(context, null);
    });

    // Кнопка выбора площадки списания
    document.getElementById('selectTradeFromPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('buy', 'from');
    });

    // ИСПРАВЛЕННАЯ КНОПКА выбора актива для торговли
    const tradeAssetBtn = document.getElementById('selectTradeAssetBtn');
    if (tradeAssetBtn) {
        tradeAssetBtn.removeAttribute('onclick');
        tradeAssetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const context = document.getElementById('tradeOperationType').value;
            openAssetModal(context, 'asset');
        });
    }

    // Кнопка выбора валюты цены
    document.getElementById('selectTradePriceCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openCurrencyModal(context, 'price');
    });

    // Кнопка выбора валюты комиссии
    document.getElementById('selectTradeCommissionCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const context = document.getElementById('tradeOperationType').value;
        openCurrencyModal(context, 'commission');
    });

    // Кнопка выбора валюты в пополнении
    document.getElementById('selectCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCurrencyModal('deposit', null);
    });

    // Кнопка выбора площадки в пополнении
    document.getElementById('selectPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('deposit', null);
    });

    // Кнопка выбора площадки отправителя
    document.getElementById('selectFromPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('transfer', 'from');
    });

    // Кнопка выбора площадки получателя
    document.getElementById('selectToPlatformBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openPlatformModal('transfer', 'to');
    });

    // ИСПРАВЛЕННАЯ КНОПКА выбора актива в переводе
    const transferAssetBtn = document.getElementById('selectAssetBtn');
    if (transferAssetBtn) {
        transferAssetBtn.removeAttribute('onclick');
        transferAssetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openAssetModal('transfer', 'asset');
        });
    }

    // Кнопка выбора валюты комиссии в переводе
    document.getElementById('selectCommissionCurrencyBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCurrencyModal('transfer', 'commission');
    });

    // Быстрые кнопки для выбора площадки в торговле
    document.querySelectorAll('#tradePopularPlatforms .quick-platform-btn, #tradeFromPopularPlatforms .quick-platform-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const idMatch = onclick.match(/'(\d+)'/);
                const nameMatch = onclick.match(/, '([^']+)'/);
                if (idMatch && nameMatch) {
                    if (this.closest('#tradeFromPopularPlatforms')) {
                        selectTradeFromPlatform(idMatch[1], nameMatch[1]);
                    } else {
                        selectTradePlatform(idMatch[1], nameMatch[1]);
                    }
                }
            }
        });
    });

    // Быстрые кнопки для выбора актива в торговле
    document.querySelectorAll('#tradePopularAssets .quick-asset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const idMatch = onclick.match(/'(\d+)'/);
                const symbolMatch = onclick.match(/, '([^']+)'/);
                const typeMatch = onclick.match(/, '([^']+)'\)$/);
                if (idMatch && symbolMatch) {
                    selectTradeAsset(idMatch[1], symbolMatch[1], typeMatch ? typeMatch[1] : 'other');
                }
            }
        });
    });

    // Быстрые кнопки для выбора валюты цены
    document.querySelectorAll('#tradePopularPriceCurrencies .quick-platform-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const codeMatch = onclick.match(/'([^']+)'/);
                if (codeMatch) {
                    selectTradePriceCurrency(codeMatch[1]);
                }
            }
        });
    });

    // Быстрые кнопки для выбора валюты комиссии
    document.querySelectorAll('#tradePopularCommissionCurrencies .quick-platform-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const codeMatch = onclick.match(/'([^']+)'/);
                if (codeMatch) {
                    selectTradeCommissionCurrency(codeMatch[1]);
                }
            }
        });
    });

    // Кнопки операций
    document.querySelectorAll('.operation-type-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.dataset.type;
            if (type === 'deposit') openDepositModal();
            else if (type === 'buy') openBuyModal();
            else if (type === 'sell') openSellModal();
            else if (type === 'transfer') openTransferModal();
        });
    });

    // Закрытие модальных окон
    document.getElementById('closeDepositModalBtn')?.addEventListener('click', closeDepositModal);
    document.getElementById('cancelDepositBtn')?.addEventListener('click', closeDepositModal);
    document.getElementById('closeTradeModalBtn')?.addEventListener('click', closeTradeModal);
    document.getElementById('cancelTradeBtn')?.addEventListener('click', closeTradeModal);
    document.getElementById('closeTransferModalBtn')?.addEventListener('click', closeTransferModal);
    document.getElementById('cancelTransferBtn')?.addEventListener('click', closeTransferModal);

    // Закрытие по клику на overlay
    [depositModal, tradeModal, transferModal].forEach(modal => {
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    if (modal === depositModal) closeDepositModal();
                    else if (modal === tradeModal) closeTradeModal();
                    else if (modal === transferModal) closeTransferModal();
                }
            });
        }
    });

    // Обработчики для модального окна добавления площадки
    document.getElementById('closeAddPlatformModalBtn')?.addEventListener('click', closeAddPlatformModal);
    document.getElementById('cancelAddPlatformBtn')?.addEventListener('click', closeAddPlatformModal);
    document.getElementById('confirmAddPlatformBtn')?.addEventListener('click', saveNewPlatform);

    // Обработчики для кнопок выбора типа площадки
    document.querySelectorAll('.platform-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            setActivePlatformType(type);
        });
    });

    // Закрытие по клику на overlay для добавления площадки
    const addPlatformModal = document.getElementById('addPlatformModal');
    if (addPlatformModal) {
        addPlatformModal.addEventListener('click', (e) => {
            if (e.target === addPlatformModal) {
                closeAddPlatformModal();
            }
        });
    }

    // Обработка Enter в форме добавления площадки
    document.getElementById('addPlatformForm')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveNewPlatform();
        }
    });

    // Обработчики для модального окна добавления валюты
    document.getElementById('closeAddCurrencyModalBtn')?.addEventListener('click', closeAddCurrencyModal);
    document.getElementById('cancelAddCurrencyBtn')?.addEventListener('click', closeAddCurrencyModal);
    document.getElementById('confirmAddCurrencyBtn')?.addEventListener('click', saveNewCurrency);

    // Обработчики для кнопок выбора типа валюты
    document.querySelectorAll('.currency-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            setActiveCurrencyType(type);
        });
    });

    // Закрытие по клику на overlay для добавления валюты
    const addCurrencyModal = document.getElementById('addCurrencyModal');
    if (addCurrencyModal) {
        addCurrencyModal.addEventListener('click', (e) => {
            if (e.target === addCurrencyModal) {
                closeAddCurrencyModal();
            }
        });
    }

    // Обработка Enter в форме добавления валюты
    document.getElementById('addCurrencyForm')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveNewCurrency();
        }
    });

    // Обработчики для модального окна добавления актива
    document.getElementById('closeAddAssetModalBtn')?.addEventListener('click', closeAddAssetModal);
    document.getElementById('cancelAddAssetBtn')?.addEventListener('click', closeAddAssetModal);
    document.getElementById('confirmAddAssetBtn')?.addEventListener('click', saveNewAsset);

    // Обработчики для кнопок выбора типа актива
    document.querySelectorAll('.asset-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            setActiveAssetType(type);
        });
    });

    // Закрытие по клику на overlay для добавления актива
    const addAssetModal = document.getElementById('addAssetModal');
    if (addAssetModal) {
        addAssetModal.addEventListener('click', (e) => {
            if (e.target === addAssetModal) {
                closeAddAssetModal();
            }
        });
    }

    // Обработка Enter в форме добавления актива
    document.getElementById('addAssetForm')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveNewAsset();
        }
    });

    // Быстрые кнопки сумм
    document.querySelectorAll('.quick-amount-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.form-group')?.querySelector('input[type="text"]');
            if (input) {
                input.value = this.dataset.amount;
                formatInput(input);
            }
        });
    });

    // Форматирование числовых полей
    const numberInputs = [
        'depositAmount',
        'tradeQuantity',
        'tradePrice',
        'tradeCommission',
        'transferAmount',
        'transferCommission'
    ];
    
    numberInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', function() {
                formatInput(this);
            });
            input.addEventListener('blur', function() {
                formatInput(this);
            });
        }
    });

    // Расчет итога для торгов
    ['tradeQuantity', 'tradePrice', 'tradeCommission'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', calculateTradeTotal);
    });

    // ИСПРАВЛЕННЫЙ обработчик поиска валют/активов
    const currencySearch = document.getElementById('currencySearch');
    if (currencySearch) {
        // Создаем новый элемент, чтобы удалить старые обработчики
        const newSearch = currencySearch.cloneNode(true);
        currencySearch.parentNode.replaceChild(newSearch, currencySearch);
        
        newSearch.addEventListener('input', function(e) {
            const searchText = e.target.value;
            
            if (currentModalContext && currentModalContext.mode === 'asset') {
                filterAssetsForSelect(searchText);
            } else {
                filterCurrencies(searchText);
            }
        });

        newSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchText = this.value.trim();
                if (searchText) {
                    if (currentModalContext && currentModalContext.mode === 'asset') {
                        addNewAssetFromCurrencyModal(searchText);
                    } else {
                        addNewCurrencyAndSelect(searchText, currentModalContext.source, currentModalContext.subMode);
                    }
                }
            }
        });
    }

    // Закрытие модального окна валют
    document.getElementById('closeCurrencyModalBtn')?.addEventListener('click', closeCurrencyModal);

    // Закрытие по клику на overlay для валют
    const currencyModal = document.getElementById('currencySelectModal');
    if (currencyModal) {
        currencyModal.addEventListener('click', (e) => {
            if (e.target === currencyModal) {
                closeCurrencyModal();
            }
        });
    }

    // Поиск площадки
    document.getElementById('platformSearch')?.addEventListener('input', function(e) {
        if (currentModalContext.mode === 'platform') {
            filterPlatforms(e.target.value);
        }
    });

    // Закрытие модального окна площадки
    document.getElementById('closePlatformModalBtn')?.addEventListener('click', closePlatformModal);

    // Закрытие по клику на overlay для площадок
    const platformModal = document.getElementById('platformSelectModal');
    if (platformModal) {
        platformModal.addEventListener('click', (e) => {
            if (e.target === platformModal) {
                closePlatformModal();
            }
        });
    }

    // Enter в поиске площадок
    document.getElementById('platformSearch')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const searchText = this.value.trim();
            if (searchText && currentModalContext.mode === 'platform') {
                addNewPlatformAndSelect(searchText, currentModalContext.source);
            }
        }
    });

    // Кнопки популярных активов в переводе
    document.querySelectorAll('#transferPopularAssets .quick-asset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const idMatch = onclick.match(/'(\d+)'/);
                const symbolMatch = onclick.match(/, '([^']+)'/);
                if (idMatch && symbolMatch) {
                    selectAsset(idMatch[1], symbolMatch[1]);
                }
            }
        });
    });

    // Кнопки популярных валют для комиссии в переводе
    document.querySelectorAll('#transferPopularCommissionCurrencies .quick-platform-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const codeMatch = onclick.match(/'([^']+)'/);
                if (codeMatch) {
                    selectCommissionCurrency(codeMatch[1]);
                }
            }
        });
    });

    // Закрытие по ESC
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (depositModal?.classList.contains('active')) closeDepositModal();
        if (tradeModal?.classList.contains('active')) closeTradeModal();
        if (transferModal?.classList.contains('active')) closeTransferModal();
        if (document.getElementById('platformSelectModal')?.classList.contains('active')) closePlatformModal();
        if (document.getElementById('currencySelectModal')?.classList.contains('active')) closeCurrencyModal();
        if (document.getElementById('addPlatformModal')?.classList.contains('active')) closeAddPlatformModal();
        if (document.getElementById('addCurrencyModal')?.classList.contains('active')) closeAddCurrencyModal();
        if (document.getElementById('addAssetModal')?.classList.contains('active')) closeAddAssetModal();
        if (executeModal?.classList.contains('active')) closeExecuteModal();
        if (cancelModal?.classList.contains('active')) closeCancelModal();
    });

    // Контейнер уведомлений
    if (!document.getElementById('notificationContainer')) {
        const container = document.createElement('div');
        container.className = 'notification-container';
        container.id = 'notificationContainer';
        document.body.appendChild(container);
    }

    // Загружаем операции
    loadOperations(1);

    // Настройка закрытия модальных окон по клику на overlay
    setupModalCloseOnOverlay('expenseModal');
    setupModalCloseOnOverlay('addExpenseCategoryModal');
});

function showPurchaseHistory(data) {
    const modal = document.getElementById('purchaseHistoryModal');
    const symbolSpan = document.getElementById('purchaseHistorySymbol');
    const body = document.getElementById('purchaseHistoryBody');
    
    symbolSpan.textContent = data.symbol;
    
    if (data.history.length === 0) {
        body.innerHTML = '<div style="text-align: center; padding: 30px; color: #6b7a8f;">Нет истории покупок</div>';
    } else {
        let html = '';
        data.history.forEach(item => {
            // Исправленное форматирование даты без сдвига часового пояса
            let formattedDate = item.date;
            if (typeof item.date === 'string' && item.date.match(/^\d{4}-\d{2}-\d{2}/)) {
                const parts = item.date.split('T')[0].split('-');
                formattedDate = `${parts[2]}.${parts[1]}.${parts[0]}`;
            } else {
                const date = new Date(item.date);
                if (!isNaN(date.getTime())) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    formattedDate = `${day}.${month}.${year}`;
                }
            }
            
            const quantity = Number(item.quantity).toLocaleString('ru-RU', { 
                minimumFractionDigits: 0, 
                maximumFractionDigits: 8 
            });
            const price = Number(item.price).toLocaleString('ru-RU', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
            const total = (item.quantity * item.price).toLocaleString('ru-RU', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
            
            html += `
                <div class="purchase-history-item">
                    <div>
                        <div class="purchase-history-date">${formattedDate}</div>
                        <div style="font-size: 12px; color: #6b7a8f; margin-top: 2px;">${item.platform}</div>
                    </div>
                    <div class="purchase-history-details">
                        <div class="purchase-history-quantity">${quantity} ${data.symbol}</div>
                        <div class="purchase-history-price">по ${price} ${item.price_currency}</div>
                        <div class="purchase-history-total">${total} ${item.price_currency}</div>
                    </div>
                </div>
            `;
        });
        body.innerHTML = html;
    }
    
    modal.classList.add('active');
}

function closePurchaseHistoryModal() {
    document.getElementById('purchaseHistoryModal').classList.remove('active');
}

// Закрытие по клику на overlay
document.getElementById('purchaseHistoryModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closePurchaseHistoryModal();
    }
});

// Закрытие по ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePurchaseHistoryModal();
    }
});

function showAssetHistory(data) {
    const modal = document.getElementById('purchaseHistoryModal');
    const symbolSpan = document.getElementById('purchaseHistorySymbol');
    const body = document.getElementById('purchaseHistoryBody');
    
    symbolSpan.textContent = data.symbol;
    
    if (data.history.length === 0) {
        body.innerHTML = '<div style="text-align: center; padding: 30px; color: #6b7a8f;">Нет истории операций</div>';
    } else {
        let html = '';
        data.history.forEach(item => {
            // Форматирование даты
            let formattedDate = item.date;
            if (typeof item.date === 'string' && item.date.match(/^\d{4}-\d{2}-\d{2}/)) {
                const parts = item.date.split('T')[0].split('-');
                formattedDate = `${parts[2]}.${parts[1]}.${parts[0]}`;
            } else {
                const date = new Date(item.date);
                if (!isNaN(date.getTime())) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    formattedDate = `${day}.${month}.${year}`;
                }
            }
            
            // Функция для форматирования чисел
            const formatNumber = (num, isFiat = false) => {
                if (num === null || num === undefined) return '';
                
                // Преобразуем в строку
                let str = num.toString();
                // Если число в экспоненциальной форме
                if (str.includes('e')) {
                    str = num.toFixed(12);
                }
                
                let formatted;
                
                // Для фиатных валют (RUB, USD, EUR) - 2 знака после запятой
                if (isFiat) {
                    let rounded = parseFloat(num).toFixed(2);
                    // Убираем .00 если они есть
                    formatted = rounded.replace(/\.?0+$/, '');
                } else {
                    // Для криптовалют - до 8 знаков, убираем лишние нули
                    let rounded = parseFloat(num).toFixed(8);
                    formatted = rounded.replace(/\.?0+$/, '');
                }
                
                // Добавляем пробелы между разрядами в целой части
                let parts = formatted.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                
                if (parts.length > 1 && parts[1]) {
                    return parts[0] + '.' + parts[1];
                }
                return parts[0];
            };
            
            // Определяем, является ли валюта фиатной
            const isFiatCurrency = (curr) => {
                return curr === 'RUB' || curr === 'USD' || curr === 'EUR' || 
                       curr === 'GBP' || curr === 'CNY' || curr === 'JPY';
            };
            
            const isFiat = isFiatCurrency(item.price_currency);
            const quantity = formatNumber(item.quantity, false);
            const price = formatNumber(item.price, isFiat);
            const total = formatNumber(item.quantity * item.price, isFiat);
            
            let operationColor = 'var(--text-secondary);';
            let operationText = '';
            let priceText = '';
            let totalText = '';
            
            switch(item.operation_type) {
                case 'buy':
                    operationColor = '#00a86b';
                    operationText = `Покупка ${quantity} ${data.symbol}`;
                    priceText = `по ${price} ${item.price_currency}`;
                    totalText = `💰 ${total} ${item.price_currency}`;
                    break;
                case 'sell':
                    operationColor = '#e53e3e';
                    operationText = `Продажа ${quantity} ${data.symbol}`;
                    priceText = `по ${price} ${item.price_currency}`;
                    totalText = `💰 ${total} ${item.price_currency}`;
                    break;
                case 'payment':
                    operationColor = '#e53e3e';
                    operationText = `Списание ${quantity} ${item.price_currency}`;
                    priceText = '';
                    totalText = `💸 ${quantity} ${item.price_currency}`;
                    break;
                case 'income':
                    operationColor = '#00a86b';
                    operationText = `Поступление ${quantity} ${item.price_currency}`;
                    priceText = '';
                    totalText = `💰 ${quantity} ${item.price_currency}`;
                    break;
                case 'deposit':
                    operationColor = '#1a5cff';
                    operationText = `Пополнение ${quantity} ${data.symbol}`;
                    priceText = '';
                    totalText = `➕ ${quantity} ${data.symbol}`;
                    break;
                case 'transfer_in':
                    operationColor = '#ff9f4a';
                    operationText = `Входящий перевод ${quantity} ${data.symbol}`;
                    priceText = '';
                    totalText = `📥 ${quantity} ${data.symbol}`;
                    break;
                case 'transfer_out':
                    operationColor = '#ff9f4a';
                    operationText = `Исходящий перевод ${quantity} ${data.symbol}`;
                    priceText = '';
                    totalText = `📤 ${quantity} ${data.symbol}`;
                    break;
            }
            
            html += `
                <div class="purchase-history-item" style="padding: 12px; border-bottom: 1px solid var(--border-color, #edf2f7);">
                    <div style="flex: 1;">
                        <div class="purchase-history-date" style="font-size: 13px; color: #6b7a8f; margin-bottom: 4px;">${formattedDate}</div>
                        <div style="font-size: 12px; color: #6b7a8f;">${item.platform || '—'}</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="color: ${operationColor}; font-weight: 500; margin-bottom: 4px;">${operationText}</div>
                        ${priceText ? `<div style="font-size: 12px; color: #6b7a8f;">${priceText}</div>` : ''}
                        <div style="font-size: 13px; font-weight: 600; color: ${operationColor}; margin-top: 4px;">${totalText}</div>
                    </div>
                </div>
            `;
        });
        body.innerHTML = html;
    }
    
    modal.classList.add('active');
}

// ============================================================================
// ПАГИНАЦИЯ ОПЕРАЦИЙ
// ============================================================================

let currentOperationsPage = 1;
const operationsPerPage = 5;

// Глобальная переменная для хранения всех операций
let allFilteredOperations = [];

async function loadOperations(page) {
    currentOperationsPage = page;
    
    // Показываем индикатор загрузки
    const container = document.getElementById('operationsContainer');
    const operationsList = document.getElementById('operationsList');
    if (operationsList) {
        operationsList.style.opacity = '0.5';
    }
    
    try {
        // Загружаем все операции только один раз
        if (allFilteredOperations.length === 0) {
            const response = await fetch('get_operations.php?page=1&per_page=100');
            const data = await response.json();
            
            if (data.success) {
                // Фильтруем все операции один раз
                allFilteredOperations = filterOperations(data.operations);
            } else {
                if (operationsList) operationsList.style.opacity = '1';
                return;
            }
        }
        
        // Рассчитываем пагинацию на основе отфильтрованных операций
        const totalPages = Math.ceil(allFilteredOperations.length / 5);
        const pagination = {
            current_page: page,
            total_pages: totalPages,
            total: allFilteredOperations.length,
            per_page: 5,
            from: (page - 1) * 5 + 1,
            to: Math.min(page * 5, allFilteredOperations.length),
            has_previous: page > 1,
            has_next: page < totalPages
        };
        
        // Отображаем операции для текущей страницы
        updateOperationsList(allFilteredOperations, pagination);
        
    } catch (error) {
        showNotification('error', 'Ошибка', 'Не удалось загрузить операции');
    } finally {
        if (operationsList) {
            operationsList.style.opacity = '1';
        }
    }
}

function filterOperations(operations) {       
    // Группируем операции по ID
    const groupedOps = {};
    operations.forEach(op => {
        if (!groupedOps[op.operation_id]) {
            groupedOps[op.operation_id] = [];
        }
        groupedOps[op.operation_id].push(op);
    });
    
    // Оставляем только те группы, которые дадут одну запись
    const filteredOps = [];
    
    Object.values(groupedOps).forEach(group => {        
        // Сортируем группу, чтобы buy_asset/sell_asset были первыми
        group.sort((a, b) => {
            if (a.operation_type.includes('asset')) return -1;
            if (b.operation_type.includes('asset')) return 1;
            return 0;
        });
        
        const mainOp = group[0];
        const secondaryOp = group[1];
        
        // Определяем, показывать ли эту группу
        let shouldShow = true;
        let reason = '';
        
        if (mainOp.operation_type == 'buy_payment' && !secondaryOp) {
            shouldShow = false;
            reason = 'одиночный buy_payment';
        }
        else if (mainOp.operation_type == 'sell_income' && !secondaryOp) {
            shouldShow = false;
            reason = 'одиночный sell_income';
        }
        
        // ВАЖНО: ВСЕГДА показываем переводы, даже если они одиночные
        if (mainOp.operation_type == 'transfer_in' || mainOp.operation_type == 'transfer_out') {
            shouldShow = true;
            reason = 'перевод (всегда показываем)';
        }
        
        if (shouldShow) {
            filteredOps.push(mainOp);
        }
    });
    
    // Сортируем по дате (сначала новые)
    filteredOps.sort((a, b) => new Date(b.date) - new Date(a.date));
    
    return filteredOps;
}

function updateOperationsList(operations, pagination) {
    const container = document.getElementById('operationsList');
    const headerSpan = document.querySelector('#operationsContainer .stat-badge');
    
    // Получаем доступную высоту блока
    const operationsCard = document.querySelector('.card-operations');
    const operationsList = document.getElementById('operationsList');
    
    // Функция для расчета количества операций, помещающихся в блок
    function calculateVisibleOperationsCount() {
        if (!operationsList || operationsList.children.length === 0) return 5;
        
        const containerHeight = operationsList.clientHeight;
        const firstItem = operationsList.children[0];
        if (!firstItem) return 5;
        
        const itemHeight = firstItem.offsetHeight;
        const availableHeight = containerHeight - 20; // Отступы
        
        // Минимум 5 операций, максимум - сколько поместится
        let maxVisible = Math.max(5, Math.floor(availableHeight / itemHeight));
        
        // Ограничиваем максимальное количество (чтобы не было слишком много)
        maxVisible = Math.min(maxVisible, 15);
        
        return maxVisible;
    }
    
    // Рассчитываем количество операций для отображения
    const visibleCount = calculateVisibleOperationsCount();
    
    // Обрезаем операции для текущей страницы
    const startIndex = (pagination.current_page - 1) * pagination.per_page;
    const endIndex = Math.min(startIndex + pagination.per_page, operations.length);
    const pageOperations = operations.slice(startIndex, endIndex);
    
    // Если операций меньше visibleCount, берем больше с предыдущих/следующих страниц
    let displayOperations = [...pageOperations];
    let usedOperations = new Set(displayOperations.map(op => op.operation_id));
    
    // Если на текущей странице меньше visibleCount операций, добавляем из других страниц
    if (displayOperations.length < visibleCount && operations.length > displayOperations.length) {
        // Сначала добавляем из следующих страниц
        let nextPage = pagination.current_page + 1;
        while (displayOperations.length < visibleCount && nextPage <= pagination.total_pages) {
            const nextStart = (nextPage - 1) * pagination.per_page;
            const nextEnd = Math.min(nextStart + pagination.per_page, operations.length);
            const nextOps = operations.slice(nextStart, nextEnd);
            
            for (const op of nextOps) {
                if (!usedOperations.has(op.operation_id)) {
                    displayOperations.push(op);
                    usedOperations.add(op.operation_id);
                    if (displayOperations.length >= visibleCount) break;
                }
            }
            nextPage++;
        }
        
        // Если все еще мало, добавляем из предыдущих страниц
        let prevPage = pagination.current_page - 1;
        while (displayOperations.length < visibleCount && prevPage >= 1) {
            const prevStart = (prevPage - 1) * pagination.per_page;
            const prevEnd = Math.min(prevStart + pagination.per_page, operations.length);
            const prevOps = operations.slice(prevStart, prevEnd);
            
            for (const op of prevOps.reverse()) {
                if (!usedOperations.has(op.operation_id)) {
                    displayOperations.unshift(op);
                    usedOperations.add(op.operation_id);
                    if (displayOperations.length >= visibleCount) break;
                }
            }
            prevPage--;
        }
    }
    
    // Ограничиваем количество отображаемых операций
    displayOperations = displayOperations.slice(0, visibleCount);
    
    let html = '';
    
    // Обрабатываем операции
    displayOperations.forEach((op, index) => {
        let iconClass = '';
        let iconType = '';
        let displayText = '';
        let detailsLine = '';
        let displayDate = op.date;
        
        // Определяем иконку
        if (op.direction == 'in' || op.operation_type == 'buy_asset' || op.operation_type == 'sell_income' || op.operation_type == 'deposit' || op.operation_type == 'transfer_in') {
            iconClass = 'icon-buy';
            iconType = 'fa-arrow-down';
        } else {
            iconClass = 'icon-sell';
            iconType = 'fa-arrow-up';
        }
        
        // Формируем текст в зависимости от типа операции
        if (op.operation_type == 'buy_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'buy_payment');
            
            if (secondaryOp) {
                const assetAmount = formatAmount(op.amount, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const moneyAmount = formatAmount(secondaryOp.amount_out, secondaryOp.currency);
                
                displayText = `Куплено ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform} ← ${secondaryOp.platform}`;
            } else {
                const assetAmount = formatAmount(op.amount, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const totalCost = formatAmount(op.amount * op.price, op.price_currency);
                
                displayText = `Куплено ${assetAmount} ${op.currency} за ${totalCost} ${op.price_currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            }
        }
        else if (op.operation_type == 'sell_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'sell_income');
            
            if (secondaryOp) {
                const assetAmount = formatAmount(op.amount_out, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const moneyAmount = formatAmount(secondaryOp.amount, secondaryOp.currency);
                
                displayText = `Продано ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            } else {
                const assetAmount = formatAmount(op.amount_out, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const totalIncome = formatAmount(op.amount_out * op.price, op.price_currency);
                
                displayText = `Продано ${assetAmount} ${op.currency} за ${totalIncome} ${op.price_currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            }
        }
        else if (op.operation_type == 'deposit') {
            displayText = `Пополнение: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
        }
        else if (op.operation_type == 'transfer_in') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const outOp = relatedOps.find(o => o.operation_type === 'transfer_out');
            
            displayText = `Входящий перевод: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
            
            if (outOp && outOp.commission && outOp.commission > 0) {
                const commissionAmount = formatAmount(outOp.commission, outOp.commission_currency || outOp.currency);
                detailsLine += ` · комиссия ${commissionAmount} ${outOp.commission_currency || outOp.currency}`;
            }
        }
        else if (op.operation_type == 'transfer_out') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const inOp = relatedOps.find(o => o.operation_type === 'transfer_in');
            
            displayText = `Исходящий перевод: ${formatAmount(op.amount_out, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
            
            if (op.commission && op.commission > 0) {
                const commissionAmount = formatAmount(op.commission, op.commission_currency || op.currency);
                detailsLine += ` · комиссия ${commissionAmount} ${op.commission_currency || op.currency}`;
            }
            
            iconClass = 'icon-convert';
            iconType = 'fa-exchange-alt';
            
            html += `
                <div class="operation-item">
                    <div class="operation-icon ${iconClass}">
                        <i class="fas ${iconType}"></i>
                    </div>
                    <div class="operation-details">
                        <div class="operation-title">${displayText}</div>
                        <div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">
                            ${detailsLine}
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        
        if (displayText) {
            html += `
                <div class="operation-item">
                    <div class="operation-icon ${iconClass}">
                        <i class="fas ${iconType}"></i>
                    </div>
                    <div class="operation-details">
                        <div class="operation-title">${displayText}</div>
                        <div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">
                            ${detailsLine}
                        </div>
                    </div>
                </div>
            `;
        }
    });
    
    if (html === '') {
        html = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет операций для отображения</div>';
    }
    
    container.innerHTML = html;
    
    // Обновляем пагинацию
    updatePagination(pagination, displayOperations.length);
    
    // Добавляем обработчик изменения размера окна
    if (!window.operationsResizeHandler) {
        window.operationsResizeHandler = true;
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (allFilteredOperations.length > 0) {
                    loadOperations(currentOperationsPage);
                }
            }, 250);
        });
    }
}

function updatePagination(pagination, visibleCount) {
    const paginationHtml = document.getElementById('paginationControls');
    
    // Показываем пагинацию только если общее количество операций больше видимого
    if (pagination.total <= visibleCount) {
        paginationHtml.innerHTML = '';
        return;
    }
    
    let html = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #edf2f7;">
            <div style="display: flex; gap: 5px;">
    `;
    
    if (pagination.has_previous) {
        html += `
            <button onclick="loadOperations(${pagination.current_page - 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px; border: none; cursor: pointer;">
                <i class="fas fa-chevron-left"></i> Назад
            </button>
        `;
    }
    
    if (pagination.has_next) {
        html += `
            <button onclick="loadOperations(${pagination.current_page + 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px; border: none; cursor: pointer;">
                Вперед <i class="fas fa-chevron-right"></i>
            </button>
        `;
    }
    
    html += `
            </div>
            <div style="color: #6b7a8f; font-size: 13px;">
                Страница ${pagination.current_page} из ${pagination.total_pages}
            </div>
        </div>
    `;
    
    paginationHtml.innerHTML = html;
}

function updateOperationsList(operations, pagination) {
    const container = document.getElementById('operationsList');
    const headerSpan = document.querySelector('#operationsContainer .stat-badge');
    
    // Получаем доступную высоту блока
    const operationsCard = document.querySelector('.card-operations');
    const operationsList = document.getElementById('operationsList');
    
    // Функция для расчета количества операций, помещающихся в блок
    function calculateVisibleOperationsCount() {
        if (!operationsList || operationsList.children.length === 0) return 5;
        
        const containerHeight = operationsList.clientHeight;
        const firstItem = operationsList.children[0];
        if (!firstItem) return 5;
        
        const itemHeight = firstItem.offsetHeight;
        const availableHeight = containerHeight - 20; // Отступы
        
        // Минимум 5 операций, максимум - сколько поместится
        let maxVisible = Math.max(5, Math.floor(availableHeight / itemHeight));
        
        // Ограничиваем максимальное количество (чтобы не было слишком много)
        maxVisible = Math.min(maxVisible, 15);
        
        return maxVisible;
    }
    
    // Рассчитываем количество операций для отображения
    const visibleCount = calculateVisibleOperationsCount();
    
    // Обрезаем операции для текущей страницы
    const startIndex = (pagination.current_page - 1) * pagination.per_page;
    const endIndex = Math.min(startIndex + pagination.per_page, operations.length);
    const pageOperations = operations.slice(startIndex, endIndex);
    
    // Если операций меньше visibleCount, берем больше с предыдущих/следующих страниц
    let displayOperations = [...pageOperations];
    let usedOperations = new Set(displayOperations.map(op => op.operation_id));
    
    // Если на текущей странице меньше visibleCount операций, добавляем из других страниц
    if (displayOperations.length < visibleCount && operations.length > displayOperations.length) {
        // Сначала добавляем из следующих страниц
        let nextPage = pagination.current_page + 1;
        while (displayOperations.length < visibleCount && nextPage <= pagination.total_pages) {
            const nextStart = (nextPage - 1) * pagination.per_page;
            const nextEnd = Math.min(nextStart + pagination.per_page, operations.length);
            const nextOps = operations.slice(nextStart, nextEnd);
            
            for (const op of nextOps) {
                if (!usedOperations.has(op.operation_id)) {
                    displayOperations.push(op);
                    usedOperations.add(op.operation_id);
                    if (displayOperations.length >= visibleCount) break;
                }
            }
            nextPage++;
        }
        
        // Если все еще мало, добавляем из предыдущих страниц
        let prevPage = pagination.current_page - 1;
        while (displayOperations.length < visibleCount && prevPage >= 1) {
            const prevStart = (prevPage - 1) * pagination.per_page;
            const prevEnd = Math.min(prevStart + pagination.per_page, operations.length);
            const prevOps = operations.slice(prevStart, prevEnd);
            
            for (const op of prevOps.reverse()) {
                if (!usedOperations.has(op.operation_id)) {
                    displayOperations.unshift(op);
                    usedOperations.add(op.operation_id);
                    if (displayOperations.length >= visibleCount) break;
                }
            }
            prevPage--;
        }
    }
    
    // Ограничиваем количество отображаемых операций
    displayOperations = displayOperations.slice(0, visibleCount);
    
    let html = '';
    
    // Обрабатываем операции
    displayOperations.forEach((op, index) => {
        let iconClass = '';
        let iconType = '';
        let displayText = '';
        let detailsLine = '';
        let displayDate = op.date;
        
        // Определяем иконку
        if (op.direction == 'in' || op.operation_type == 'buy_asset' || op.operation_type == 'sell_income' || op.operation_type == 'deposit' || op.operation_type == 'transfer_in') {
            iconClass = 'icon-buy';
            iconType = 'fa-arrow-down';
        } else {
            iconClass = 'icon-sell';
            iconType = 'fa-arrow-up';
        }
        
        // Формируем текст в зависимости от типа операции
        if (op.operation_type == 'buy_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'buy_payment');
            
            if (secondaryOp) {
                const assetAmount = formatAmount(op.amount, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const moneyAmount = formatAmount(secondaryOp.amount_out, secondaryOp.currency);
                
                displayText = `Куплено ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform} ← ${secondaryOp.platform}`;
            } else {
                const assetAmount = formatAmount(op.amount, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const totalCost = formatAmount(op.amount * op.price, op.price_currency);
                
                displayText = `Куплено ${assetAmount} ${op.currency} за ${totalCost} ${op.price_currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}`;
            }
        }
        else if (op.operation_type == 'sell_asset') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const secondaryOp = relatedOps.find(o => o.operation_type === 'sell_income');
            
            // Форматируем прибыль, если есть данные
            let profitText = '';
            let profitColor = '';
            
            if (op.profit !== undefined && op.profit !== null && op.profit !== 0) {
                const profitFormatted = formatAmount(Math.abs(op.profit), op.price_currency);
                const profitPercentFormatted = op.profit_percent ? op.profit_percent.toFixed(1) : '0';
                
                if (op.profit > 0) {
                    profitText = `  |  📈 +${profitFormatted} ${op.price_currency} (+${profitPercentFormatted}%)`;
                    profitColor = '#00a86b';
                } else if (op.profit < 0) {
                    profitText = `  |  📉 -${profitFormatted} ${op.price_currency} (${profitPercentFormatted}%)`;
                    profitColor = '#e53e3e';
                }
            } else if (op.profit === 0) {
                profitText = `  |  ➖ 0 ${op.price_currency} (0%)`;
                profitColor = '#6b7a8f';
            }
            
            if (secondaryOp) {
                const assetAmount = formatAmount(op.amount_out, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const moneyAmount = formatAmount(secondaryOp.amount, secondaryOp.currency);
                
                displayText = `Продано ${assetAmount} ${op.currency} за ${moneyAmount} ${secondaryOp.currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}${profitText}`;
            } else {
                const assetAmount = formatAmount(op.amount_out, op.currency);
                const price = formatAmount(op.price, op.price_currency);
                const totalIncome = formatAmount(op.amount_out * op.price, op.price_currency);
                
                displayText = `Продано ${assetAmount} ${op.currency} за ${totalIncome} ${op.price_currency}`;
                detailsLine = `${formatDate(displayDate)} · по ${price} ${op.price_currency} · ${op.platform}${profitText}`;
            }
        }
        else if (op.operation_type == 'deposit') {
            displayText = `Пополнение: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
        }
        else if (op.operation_type == 'transfer_in') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const outOp = relatedOps.find(o => o.operation_type === 'transfer_out');
            
            displayText = `Входящий перевод: +${formatAmount(op.amount, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
            
            if (outOp && outOp.commission && outOp.commission > 0) {
                const commissionAmount = formatAmount(outOp.commission, outOp.commission_currency || outOp.currency);
                detailsLine += ` · комиссия ${commissionAmount} ${outOp.commission_currency || outOp.currency}`;
            }
        }
        else if (op.operation_type == 'transfer_out') {
            const relatedOps = operations.filter(o => o.operation_id === op.operation_id);
            const inOp = relatedOps.find(o => o.operation_type === 'transfer_in');
            
            displayText = `Исходящий перевод: ${formatAmount(op.amount_out, op.currency)} ${op.currency}`;
            detailsLine = `${formatDate(displayDate)} · ${op.platform}`;
            
            if (op.commission && op.commission > 0) {
                const commissionAmount = formatAmount(op.commission, op.commission_currency || op.currency);
                detailsLine += ` · комиссия ${commissionAmount} ${op.commission_currency || op.currency}`;
            }
            
            iconClass = 'icon-convert';
            iconType = 'fa-exchange-alt';
            
            html += `
                <div class="operation-item">
                    <div class="operation-icon ${iconClass}">
                        <i class="fas ${iconType}"></i>
                    </div>
                    <div class="operation-details">
                        <div class="operation-title">${displayText}</div>
                        <div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">
                            ${detailsLine}
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        
        if (displayText) {
            html += `
                <div class="operation-item">
                    <div class="operation-icon ${iconClass}">
                        <i class="fas ${iconType}"></i>
                    </div>
                    <div class="operation-details">
                        <div class="operation-title">${displayText}</div>
                        <div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">
                            ${detailsLine}
                        </div>
                    </div>
                </div>
            `;
        }
    });
    
    if (html === '') {
        html = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет операций для отображения</div>';
    }
    
    container.innerHTML = html;
    
    // Обновляем пагинацию
    updatePagination(pagination, displayOperations.length);
    
    // Добавляем обработчик изменения размера окна
    if (!window.operationsResizeHandler) {
        window.operationsResizeHandler = true;
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (allFilteredOperations.length > 0) {
                    loadOperations(currentOperationsPage);
                }
            }, 250);
        });
    }
}

function updatePagination(pagination, visibleCount) {
    const paginationHtml = document.getElementById('paginationControls');
    
    // Показываем пагинацию только если общее количество операций больше видимого
    if (pagination.total <= visibleCount) {
        paginationHtml.innerHTML = '';
        return;
    }
    
    let html = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #edf2f7;">
            <div style="display: flex; gap: 5px;">
    `;
    
    if (pagination.has_previous) {
        html += `
            <button onclick="loadOperations(${pagination.current_page - 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px; border: none; cursor: pointer;">
                <i class="fas fa-chevron-left"></i> Назад
            </button>
        `;
    }
    
    if (pagination.has_next) {
        html += `
            <button onclick="loadOperations(${pagination.current_page + 1})" class="quick-platform-btn" style="min-width: auto; padding: 6px 12px; border: none; cursor: pointer;">
                Вперед <i class="fas fa-chevron-right"></i>
            </button>
        `;
    }
    
    html += `
            </div>
            <div style="color: #6b7a8f; font-size: 13px;">
                Страница ${pagination.current_page} из ${pagination.total_pages}
            </div>
        </div>
    `;
    
    paginationHtml.innerHTML = html;
}

function formatDate(dateString) {
    // Если дата уже в формате YYYY-MM-DD, парсим как локальную дату без учета часового пояса
    if (typeof dateString === 'string' && dateString.match(/^\d{4}-\d{2}-\d{2}/)) {
        // Разбираем дату как локальную, без преобразования в UTC
        const parts = dateString.split('T')[0].split('-');
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    
    // Для других форматов используем стандартный парсинг
    const date = new Date(dateString);
    // Проверяем, что дата валидна
    if (isNaN(date.getTime())) {
        return dateString;
    }
    // Форматируем с учетом локального времени, но без сдвига
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${day}.${month}.${year}`;
}

function formatNumber(num, decimals) {
    if (num === null || num === undefined) return '0';
    
    let formatted = num.toFixed(decimals);
    // Убираем лишние нули в конце
    formatted = formatted.replace(/\.?0+$/, '');
    
    // Разделяем целую и дробную части
    let parts = formatted.split('.');
    // Форматируем целую часть с пробелами
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Возвращаем без пробелов в дробной части
    if (parts.length > 1 && parts[1]) {
        return parts[0] + '.' + parts[1];
    }
    return parts[0];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatAmount(amount, currency) {
    if (!amount && amount !== 0) return '0';
    
    // Преобразуем в число
    let num = parseFloat(amount);
    if (isNaN(num)) return '0';
    
    // Проверяем, целое ли число
    if (Number.isInteger(num)) {
        return num.toLocaleString('ru-RU').replace(/,/g, ' ');
    }
    
    // Список криптовалют (все, что есть в БД)
    const cryptoList = ['USDT', 'USDC', 'BTC', 'ETH', 'SOL', 'BNB', 'LINK', 'STX', 'ZK', 'FIL', 'ONDO', 'RENDER', 'GRT', 'TWT', 'APE', 'CELO', 'GOAT', 'TRUMP', 'IMX', 'POL', 'ARKM'];
    
    // Для криптовалют показываем до 4-6 знаков, убирая лишние нули
    if (cryptoList.includes(currency)) {
        // Для BTC и ETH можно оставить 6 знаков, для остальных 4
        let decimals = (currency === 'BTC' || currency === 'ETH') ? 6 : 4;
        let rounded = num.toFixed(decimals);
        // Убираем лишние нули в конце
        rounded = rounded.replace(/\.?0+$/, '');
        
        // Разделяем целую и дробную части
        let parts = rounded.split('.');
        // Форматируем целую часть с пробелами
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        // Дробную часть оставляем без пробелов
        if (parts.length > 1 && parts[1]) {
            return parts[0] + '.' + parts[1];
        }
        return parts[0];
    }
    
    // Для фиата (RUB, USD, EUR) показываем 2 знака, убирая нули
    let rounded = num.toFixed(2);
    rounded = rounded.replace(/\.?0+$/, '');
    
    // Разделяем целую и дробную части
    let parts = rounded.split('.');
    // Форматируем целую часть с пробелами
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    // Дробную часть оставляем без пробелов
    if (parts.length > 1 && parts[1]) {
        return parts[0] + '.' + parts[1];
    }
    return parts[0];
}

// ============================================================================
// ПЕРЕКЛЮЧЕНИЕ ТЕМЫ
// ============================================================================

document.getElementById('themeToggleBtn').addEventListener('click', function() {
    const isDarkTheme = document.body.classList.contains('dark-theme');
    const newTheme = isDarkTheme ? 'light' : 'dark';
    const icon = this.querySelector('i');
    const text = this.querySelector('#themeToggleText');
    
    // Показываем индикатор загрузки
    this.style.opacity = '0.7';
    this.disabled = true;
    
    // Отправляем запрос на сохранение темы
    fetch(API_URL_PHP, {  // было window.location.href
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=save_theme&theme=' + newTheme
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Меняем тему
            if (newTheme === 'dark') {
                document.body.classList.add('dark-theme');
                icon.className = 'fas fa-sun';
                text.textContent = 'Светлая';
            } else {
                document.body.classList.remove('dark-theme');
                icon.className = 'fas fa-moon';
                text.textContent = 'Темная';
            }
            
            // Показываем уведомление
            //showNotification('success', 'Тема изменена', 
            //    newTheme === 'dark' ? 'Включена темная тема' : 'Включена светлая тема');
        } else {
            //showNotification('error', 'Ошибка', 'Не удалось сохранить тему');
        }
    })
    .catch(error => {
        showNotification('error', 'Ошибка', 'Не удалось сохранить тему');
    })
    .finally(() => {
        // Возвращаем кнопку в нормальное состояние
        this.style.opacity = '1';
        this.disabled = false;
    });
});

// ============================================================================
// ЛИМИТНЫЕ ОРДЕРА
// ============================================================================

const limitOrderModal = document.getElementById('limitOrderModal');
let selectedLimitPlatform = { id: null, name: '' };
let selectedLimitAsset = { id: null, symbol: '' };
let selectedLimitCurrency = 'USD';

function openLimitOrderModal() {
    limitOrderModal.classList.add('active');
    document.getElementById('limitQuantity').value = '';
    document.getElementById('limitPrice').value = '';
    document.getElementById('limitExpiryDate').value = '';
    document.getElementById('limitNotes').value = '';
    document.getElementById('selectedLimitPlatformDisplay').textContent = 'Выбрать площадку';
    document.getElementById('selectedLimitAssetDisplay').textContent = 'Выбрать актив';
    document.getElementById('selectedLimitCurrencyDisplay').textContent = 'Выбрать'; // Изменено с USD на Выбрать
    document.getElementById('limitPlatformId').value = '';
    document.getElementById('limitAssetId').value = '';
    document.getElementById('limitCurrency').value = ''; // Изменено с USD на пустую строку
    document.getElementById('limitTotalEstimate').textContent = '0';
    
    // По умолчанию выбираем покупку
    document.querySelectorAll('.limit-type-btn').forEach(btn => {
        btn.style.opacity = '0.7';
    });
    document.querySelector('.limit-type-btn[data-type="buy"]').style.opacity = '1';
    document.querySelector('.limit-type-btn[data-type="buy"]').style.border = '2px solid white';
}

function closeLimitOrderModal() {
    limitOrderModal.classList.remove('active');
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
        if (currency === 'BTC' || currency === 'ETH') {
            formattedTotal = total.toFixed(6);
        }
        document.getElementById('limitTotalEstimate').textContent = `${formattedTotal} ${currency}`;
    } else {
        document.getElementById('limitTotalEstimate').textContent = `0 ${currency}`;
    }
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

    if (!platformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку');
        return;
    }

    if (!assetId) {
        showNotification('error', 'Ошибка', 'Выберите актив');
        return;
    }

    if (!quantity || quantity <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректное количество');
        return;
    }

    if (!limitPrice || limitPrice <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректную цену');
        return;
    }

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
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', 'Лимитный ордер создан');
            closeLimitOrderModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос');
    }
}

// Закрытие модального окна
document.getElementById('closeLimitOrderModalBtn')?.addEventListener('click', closeLimitOrderModal);
document.getElementById('cancelLimitOrderBtn')?.addEventListener('click', closeLimitOrderModal);

// КНОПКА ПОДТВЕРЖДЕНИЯ ЛИМИТНОГО ОРДЕРА
document.getElementById('confirmLimitOrderBtn')?.addEventListener('click', confirmLimitOrder);

// Закрытие по клику на overlay
if (limitOrderModal) {
    limitOrderModal.addEventListener('click', (e) => {
        if (e.target === limitOrderModal) {
            closeLimitOrderModal();
        }
    });
}

// ============================================================================
// МОДАЛЬНЫЕ ОКНА ПОДТВЕРЖДЕНИЯ ДЛЯ ЛИМИТНЫХ ОРДЕРОВ
// ============================================================================

const executeModal = document.getElementById('executeOrderModal');
const cancelModal = document.getElementById('cancelOrderModal');
let currentOrderId = null;

function openExecuteModal(orderId, orderData) {
    currentOrderId = orderId;
    
    // Заполняем данными
    document.getElementById('executeOrderTitle').textContent = 
        `${orderData.operation_type === 'buy' ? 'Покупка' : 'Продажа'} ${orderData.symbol}`;
    document.getElementById('executeOrderPlatform').textContent = orderData.platform_name;
    document.getElementById('executeOrderQuantity').textContent = 
        `${formatAmount(orderData.quantity, orderData.symbol)} ${orderData.symbol}`;
    document.getElementById('executeOrderPrice').textContent = 
        `${formatAmount(orderData.limit_price, orderData.price_currency)} ${orderData.price_currency}`;
    
    const total = orderData.quantity * orderData.limit_price;
    document.getElementById('executeOrderTotal').textContent = 
        `${formatAmount(total, orderData.price_currency)} ${orderData.price_currency}`;
    
    // Форматируем дату для отображения
    if (orderData.created_at instanceof Date && !isNaN(orderData.created_at)) {
        document.getElementById('executeOrderCreated').textContent = 
            orderData.created_at.toLocaleString('ru-RU');
    } else {
        document.getElementById('executeOrderCreated').textContent = 'Дата не указана';
    }
    
    if (orderData.expiry_date) {
        const expiryDate = new Date(orderData.expiry_date);
        if (!isNaN(expiryDate)) {
            document.getElementById('executeOrderExpiry').textContent = 
                expiryDate.toLocaleDateString('ru-RU');
        } else {
            document.getElementById('executeOrderExpiry').textContent = 'Бессрочно';
        }
    } else {
        document.getElementById('executeOrderExpiry').textContent = 'Бессрочно';
    }
    
    const warningText = orderData.operation_type === 'buy' 
        ? `Будет создана сделка на покупку. Средства (${formatAmount(total, orderData.price_currency)} ${orderData.price_currency}) будут списаны с площадки ${orderData.platform_name}.`
        : `Будет создана сделка на продажу. ${formatAmount(orderData.quantity, orderData.symbol)} ${orderData.symbol} будут списаны с площадки ${orderData.platform_name}.`;
    
    document.getElementById('executeOrderWarning').textContent = warningText;
    
    executeModal.classList.add('active');
}

function closeExecuteModal() {
    executeModal.classList.remove('active');
    currentOrderId = null;
}

function openCancelModal(orderId, orderData) {
    currentOrderId = orderId;
    
    document.getElementById('cancelOrderTitle').textContent = 
        `Отмена ордера на ${orderData.operation_type === 'buy' ? 'покупку' : 'продажу'}`;
    document.getElementById('cancelOrderDescription').textContent = 
        `Вы уверены, что хотите отменить ордер на ${orderData.operation_type === 'buy' ? 'покупку' : 'продажу'} ${formatAmount(orderData.quantity, orderData.symbol)} ${orderData.symbol}?`;
    document.getElementById('cancelOrderPlatform').textContent = orderData.platform_name;
    document.getElementById('cancelOrderPrice').textContent = 
        `${formatAmount(orderData.limit_price, orderData.price_currency)} ${orderData.price_currency}`;
    document.getElementById('cancelOrderQuantity').textContent = 
        `${formatAmount(orderData.quantity, orderData.symbol)} ${orderData.symbol}`;
    
    cancelModal.classList.add('active');
}

function closeCancelModal() {
    cancelModal.classList.remove('active');
    currentOrderId = null;
}

// Обновленные функции для работы с модальными окнами
function showExecuteConfirmation(orderId) {    
     // Находим карточку ордера и собираем данные
    const orderCard = document.getElementById(`order-${orderId}`);
    if (!orderCard) return;
    
    // Устанавливаем currentOrderId
    currentOrderId = orderId;

    // Получаем текст из элементов
    const orderActionText = orderCard.querySelector('.order-action').textContent;
    const orderExchangeText = orderCard.querySelector('.order-exchange').textContent.trim();
    const orderFooterSpans = orderCard.querySelectorAll('.order-footer span');
    const orderPriceText = orderCard.querySelector('.order-price').textContent;
    
    // Парсим количество (убираем "шт" и пробелы)
    const quantityText = orderFooterSpans[1]?.textContent.replace(' шт', '').replace(/\s/g, '') || '0';
    
    // Парсим цену
    const priceParts = orderPriceText.split(' ');
    const limitPrice = parseFloat(priceParts[0].replace(/\s/g, '')) || 0;
    const priceCurrency = priceParts[1] || '';
    
    // Парсим дату создания (формат: "🕒 19.03.2026 16:05")
    const createdText = orderFooterSpans[0]?.textContent.replace('🕒', '').trim() || '';
    
    // Парсим дату истечения
    let expiryDate = null;
    const expiryElement = orderCard.querySelector('div[style*="font-size: 11px"]');
    if (expiryElement) {
        const expiryText = expiryElement.textContent.replace('до', '').trim();
        // Пробуем распарсить дату в формате "19.03.2026"
        const dateParts = expiryText.split('.');
        if (dateParts.length === 3) {
            expiryDate = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`;
        }
    }
    
    // Преобразуем дату создания в формат ISO для корректного отображения
    let createdDate = new Date();
    if (createdText) {
        const dateParts = createdText.split(' ');
        if (dateParts.length >= 2) {
            const dayMonthYear = dateParts[0].split('.');
            const time = dateParts[1] || '00:00';
            if (dayMonthYear.length === 3) {
                // Формат: DD.MM.YYYY HH:MM
                createdDate = new Date(`${dayMonthYear[2]}-${dayMonthYear[1]}-${dayMonthYear[0]}T${time}`);
            }
        }
    }
    
    const orderData = {
        id: orderId,
        operation_type: orderActionText.includes('Покупка') ? 'buy' : 'sell',
        symbol: orderActionText.split(' ')[1] || '',
        platform_name: orderExchangeText,
        quantity: parseFloat(quantityText) || 0,
        limit_price: limitPrice,
        price_currency: priceCurrency,
        created_at: createdDate,
        expiry_date: expiryDate
    };
    
    openExecuteModal(orderId, orderData);
}

function showCancelConfirmation(orderId) {    
    // Находим карточку ордера и собираем данные
    const orderCard = document.getElementById(`order-${orderId}`);
    if (!orderCard) {
        return;
    }
    
    // Устанавливаем currentOrderId и testOrderId
    currentOrderId = orderId;
    testOrderId = orderId; // ДОБАВЛЯЕМ ТЕСТОВУЮ ПЕРЕМЕННУЮ
    
    const orderData = {
        operation_type: orderCard.querySelector('.order-action').textContent.includes('Покупка') ? 'buy' : 'sell',
        symbol: orderCard.querySelector('.order-action').textContent.split(' ')[1],
        platform_name: orderCard.querySelector('.order-exchange').textContent.trim(),
        quantity: parseFloat(orderCard.querySelector('.order-footer span:last-child').textContent),
        limit_price: parseFloat(orderCard.querySelector('.order-price').textContent.split(' ')[0]),
        price_currency: orderCard.querySelector('.order-price').textContent.split(' ')[1],
    };
    
    openCancelModal(orderId, orderData);
}

// Обновленные асинхронные функции
async function confirmExecuteOrder() {    
    if (!currentOrderId) {
        showNotification('error', 'Ошибка', 'ID ордера не указан');
        return;
    }
    
    closeExecuteModal();
    
    const formData = new FormData();
    formData.append('action', 'execute_limit_order');
    formData.append('order_id', String(currentOrderId)); // Принудительно преобразуем в строку
    
    for (let pair of formData.entries()) {
    }
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            
            const orderCard = document.getElementById(`order-${currentOrderId}`);
            if (orderCard) {
                orderCard.style.transition = 'all 0.3s ease';
                orderCard.style.opacity = '0';
                orderCard.style.transform = 'translateX(100px)';
                
                setTimeout(() => {
                    orderCard.remove();
                    
                    const ordersList = document.getElementById('limitOrdersList');
                    if (ordersList && ordersList.children.length === 0) {
                        ordersList.innerHTML = `
                            <div class="order-empty">
                                <i class="fas fa-clock"></i>
                                <p>Нет активных лимитных ордеров</p>
                                <button class="add-order-btn" onclick="openLimitOrderModal()">
                                    <i class="fas fa-plus-circle"></i> Создать ордер
                                </button>
                            </div>
                        `;
                    }
                    
                    const badge = document.querySelector('.card-orders .stat-badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        badge.textContent = currentCount - 1;
                    }
                }, 300);
            }
            
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось исполнить ордер');
    }
}

async function confirmCancelOrder() {    
    if (!currentOrderId) {
        showNotification('error', 'Ошибка', 'ID ордера не указан');
        return;
    }
    
    closeCancelModal();
    
    // Создаем FormData разными способами для теста
    const formData = new FormData();
    formData.append('action', 'cancel_limit_order');
    formData.append('order_id', currentOrderId); // Без преобразования в строку
    formData.append('order_id_str', String(currentOrderId)); // С преобразованием в строку
    formData.append('order_id_int', parseInt(currentOrderId)); // Как число
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            
            const orderCard = document.getElementById(`order-${currentOrderId}`);
            if (orderCard) {
                orderCard.style.transition = 'all 0.3s ease';
                orderCard.style.opacity = '0';
                orderCard.style.transform = 'translateX(-100px)';
                
                setTimeout(() => {
                    orderCard.remove();
                    
                    const ordersList = document.getElementById('limitOrdersList');
                    if (ordersList && ordersList.children.length === 0) {
                        ordersList.innerHTML = `
                            <div class="order-empty">
                                <i class="fas fa-clock"></i>
                                <p>Нет активных лимитных ордеров</p>
                                <button class="add-order-btn" onclick="openLimitOrderModal()">
                                    <i class="fas fa-plus-circle"></i> Создать ордер
                                </button>
                            </div>
                        `;
                    }
                    
                    const badge = document.querySelector('.card-orders .stat-badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        badge.textContent = currentCount - 1;
                    }
                }, 300);
            }
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось отменить ордер');
    }
}

// ============================================================================
// УПРАВЛЕНИЕ ЗАМЕТКАМИ
// ============================================================================

let currentNoteId = null;
let currentDeleteNoteData = null;

// Функция открытия модального окна добавления заметки
function openAddNoteModal() {
    currentNoteId = null;
    document.getElementById('noteModalTitleText').textContent = 'Добавить заметку';
    document.getElementById('confirmNoteBtnText').textContent = 'Сохранить';
    document.getElementById('noteId').value = '';
    document.getElementById('noteTitle').value = '';
    document.getElementById('noteContent').value = '';
    document.getElementById('noteType').value = 'general';
    document.getElementById('noteReminderDate').value = '';
    document.getElementById('reminderDateGroup').style.display = 'none';
    
    // Сбрасываем активные кнопки типа
    document.querySelectorAll('.note-type-option').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector('.note-type-option[data-type="general"]').classList.add('active');
    
    document.getElementById('noteModal').classList.add('active');
}

// Функция открытия модального окна редактирования заметки
function openEditNoteModal(noteId, title, content, type, reminderDate) {
    currentNoteId = noteId;
    document.getElementById('noteModalTitleText').textContent = 'Редактировать заметку';
    document.getElementById('confirmNoteBtnText').textContent = 'Обновить';
    document.getElementById('noteId').value = noteId;
    document.getElementById('noteTitle').value = title || '';
    document.getElementById('noteContent').value = content;
    document.getElementById('noteType').value = type;
    document.getElementById('noteReminderDate').value = reminderDate || '';
    
    // Устанавливаем активную кнопку типа
    document.querySelectorAll('.note-type-option').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.querySelector(`.note-type-option[data-type="${type}"]`);
    if (activeBtn) activeBtn.classList.add('active');
    
    // Показываем поле даты если тип reminder
    document.getElementById('reminderDateGroup').style.display = type === 'reminder' ? 'block' : 'none';
    
    document.getElementById('noteModal').classList.add('active');
}

// Обработчик выбора типа заметки
document.querySelectorAll('.note-type-option').forEach(btn => {
    btn.addEventListener('click', function() {
        const type = this.dataset.type;
        document.querySelectorAll('.note-type-option').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('noteType').value = type;
        
        // Показываем поле даты только для напоминаний
        const reminderGroup = document.getElementById('reminderDateGroup');
        reminderGroup.style.display = type === 'reminder' ? 'block' : 'none';
    });
});

// Сохранение заметки
async function saveNote() {
    const noteId = document.getElementById('noteId').value;
    const title = document.getElementById('noteTitle').value;
    const content = document.getElementById('noteContent').value;
    const type = document.getElementById('noteType').value;
    const reminderDate = document.getElementById('noteReminderDate').value;
    
    if (!content.trim()) {
        showNotification('error', 'Ошибка', 'Введите содержание заметки');
        return;
    }
    
    const action = noteId ? 'update_note' : 'add_note';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('title', title);
    formData.append('content', content);
    formData.append('note_type', type);
    if (reminderDate) formData.append('reminder_date', reminderDate);
    if (noteId) formData.append('note_id', noteId);
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeNoteModal();
            // Перезагружаем заметки
            loadNotes();
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось сохранить заметку');
    }
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.remove('active');
}

// Удаление заметки
async function deleteNote(noteId, noteTitle) {
    currentDeleteNoteData = { id: noteId, title: noteTitle };
    
    // Показываем информацию о заметке
    const infoDiv = document.getElementById('deleteNoteInfo');
    infoDiv.innerHTML = `
        <div style="display: flex; gap: 8px; align-items: center;">
            <i class="fas fa-sticky-note"></i>
            <strong>${escapeHtml(noteTitle || 'Без заголовка')}</strong>
        </div>
        <div style="font-size: 12px; color: var(--text-tertiary); margin-top: 4px;">
            Это действие нельзя отменить
        </div>
    `;
    
    document.getElementById('confirmDeleteModal').classList.add('active');
}

async function confirmDeleteNote() {
    if (!currentDeleteNoteData) return;
    
    // Находим элемент заметки в DOM (если он видим)
    const noteElement = document.querySelector(`.note-item[data-note-id="${currentDeleteNoteData.id}"], .archived-note-item[data-note-id="${currentDeleteNoteData.id}"]`);
    
    // Анимация удаления
    if (noteElement) {
        noteElement.style.transition = 'all 0.3s ease';
        noteElement.style.opacity = '0';
        noteElement.style.transform = 'translateX(-20px)';
        
        // Ждем анимацию, но не дольше 300ms
        await new Promise(resolve => setTimeout(resolve, 200));
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_note');
    formData.append('note_id', currentDeleteNoteData.id);
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeConfirmDeleteModal();
            
            // Перезагружаем основные заметки
            await loadNotes();
            
            // Проверяем, открыто ли модальное окно архивов
            const archivedModal = document.getElementById('archivedNotesModal');
            if (archivedModal && archivedModal.classList.contains('active')) {
                // Если открыто, обновляем содержимое
                await loadArchivedNotes();
            }
        } else {
            showNotification('error', 'Ошибка', result.message);
            // Если ошибка, возвращаем элемент обратно
            if (noteElement) {
                noteElement.style.opacity = '1';
                noteElement.style.transform = 'translateX(0)';
            }
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось удалить заметку');
        // Если ошибка, возвращаем элемент обратно
        if (noteElement) {
            noteElement.style.opacity = '1';
            noteElement.style.transform = 'translateX(0)';
        }
    }
}

function closeConfirmDeleteModal() {
    document.getElementById('confirmDeleteModal').classList.remove('active');
    currentDeleteNoteData = null;
}

// Архивация/восстановление заметки
async function archiveNote(noteId, archive) {
    const formData = new FormData();
    formData.append('action', 'archive_note');
    formData.append('note_id', noteId);
    formData.append('archive', archive ? 1 : 0);
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            
            // Перезагружаем основные заметки
            loadNotes();
            
            // Проверяем, открыто ли модальное окно архивов
            const archivedModal = document.getElementById('archivedNotesModal');
            if (archivedModal && archivedModal.classList.contains('active')) {
                // Если открыто, обновляем содержимое
                await loadArchivedNotes();
            } else {
                // Если не открыто, просто обновляем данные для следующего открытия
                // Можно очистить кэш или просто ничего не делать
            }
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось выполнить операцию');
    }
}

// Загрузка заметок
async function loadNotes() {
    const notesContainer = document.querySelector('.card-notes');
    const notesList = document.getElementById('notesList');
    
    if (!notesList) {
        const container = notesContainer.querySelector('.card-header').nextSibling;
        const newList = document.createElement('div');
        newList.id = 'notesList';
        notesContainer.insertBefore(newList, container);
    }
    
    const container = document.getElementById('notesList');
    if (container) container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_notes');
    formData.append('include_archived', 0); // 0 - только неархивированные
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && container) {
            displayNotes(result.notes, container, false);
        } else if (container) {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Не удалось загрузить заметки</div>';
        }
    } catch (error) {
        if (container) {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Ошибка загрузки заметок</div>';
        }
    }
}

// Загрузка архивных заметок
async function loadArchivedNotes() {
    const container = document.getElementById('archivedNotesList');
    if (!container) return;
    
    container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_notes');
    formData.append('include_archived', 1); // 1 - только архивированные
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            displayArchivedNotes(result.notes, container);
        } else {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Не удалось загрузить архивные заметки</div>';
        }
    } catch (error) {
        container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Ошибка загрузки</div>';
    }
}

// Отображение заметок
function displayNotes(notes, container, isArchived) {
    if (!notes || notes.length === 0) {
        container.innerHTML = `
                <div style="margin-bottom: 15px; text-align: center;">
                    <button class="add-order-btn" onclick="openAddNoteModal()" style="margin-top: 10px;">
                        <i class="fas fa-plus-circle"></i> Создать заметку
                    </button>
                </div>
                <div class="order-empty">
                    <i class="fas fa-sticky-note"></i>
                    <p>Нет заметок</p>
                </div>
        `;
        return;
    }
    
    let html = '';
    // Добавляем кнопку создания заметки
    html += `
        <div style="margin-bottom: 15px; text-align: center;">
            <button class="add-order-btn" onclick="openAddNoteModal()" style="margin-top: 10px;">
                <i class="fas fa-plus-circle"></i> Создать заметку
            </button>
        </div>
    `;
    notes.forEach(note => {
        const noteTypeClass = note.note_type || 'general';
        const icon = getNoteIcon(note.note_type);
        const date = new Date(note.created_at).toLocaleDateString('ru-RU');
        const reminderIcon = note.reminder_date ? `📅 ${new Date(note.reminder_date).toLocaleDateString('ru-RU')}` : '';
        
        html += `
            <div class="note-item ${noteTypeClass}" data-note-id="${note.id}">
                <div class="note-header">
                    <div>
                        ${note.title ? `<div class="note-title">${escapeHtml(note.title)}</div>` : ''}
                        <div class="note-date">
                            <i class="far fa-calendar-alt"></i> ${date}
                            ${reminderIcon ? `<span style="margin-left: 8px;">${reminderIcon}</span>` : ''}
                        </div>
                    </div>
                </div>
                <div class="note-content">${escapeHtml(note.content)}</div>
                <div class="note-actions">
                    <button class="note-action-btn edit" onclick="openEditNoteModal(${note.id}, '${escapeHtml(note.title || '').replace(/'/g, "\\'")}', '${escapeHtml(note.content).replace(/'/g, "\\'")}', '${note.note_type}', '${note.reminder_date || ''}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="note-action-btn archive" onclick="archiveNote(${note.id}, true)">
                        <i class="fas fa-archive"></i>
                    </button>
                    <button class="note-action-btn delete" onclick="deleteNote(${note.id}, '${escapeHtml(note.title || 'Без заголовка').replace(/'/g, "\\'")}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Отображение архивных заметок
function displayArchivedNotes(notes, container) {
    if (!notes || notes.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-archive" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">Нет архивных заметок</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    notes.forEach(note => {
        const date = new Date(note.created_at).toLocaleDateString('ru-RU');
        const archivedDate = note.updated_at ? new Date(note.updated_at).toLocaleDateString('ru-RU') : date;
        
        html += `
            <div class="archived-note-item">
                <div class="archived-note-header">
                    <div class="archived-note-title">
                        ${note.title ? escapeHtml(note.title) : 'Без заголовка'}
                    </div>
                    <div class="archived-note-date">
                        <i class="far fa-calendar-alt"></i> ${date}
                        <span style="margin-left: 8px;">📦 ${archivedDate}</span>
                    </div>
                </div>
                <div class="archived-note-content">
                    ${escapeHtml(note.content)}
                </div>
                <div class="archived-note-actions">
                    <button class="note-action-btn restore" onclick="archiveNote(${note.id}, false)">
                        <i class="fas fa-undo-alt"></i> Восстановить
                    </button>
                    <button class="note-action-btn delete" onclick="deleteNote(${note.id}, '${escapeHtml(note.title || 'Без заголовка').replace(/'/g, "\\'")}')">
                        <i class="fas fa-trash-alt"></i> Удалить
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Получение иконки для типа заметки
function getNoteIcon(type) {
    switch(type) {
        case 'important': return '⚠️';
        case 'reminder': return '📌';
        case 'idea': return '💡';
        default: return '📝';
    }
}

// Открытие модального окна архивных заметок
function openArchivedNotesModal() {
    const modal = document.getElementById('archivedNotesModal');
    if (!modal) return;
    
    modal.classList.add('active');
    
    // Принудительно перезагружаем архивные заметки при открытии
    loadArchivedNotes();
}

function closeArchivedNotesModal() {
    document.getElementById('archivedNotesModal').classList.remove('active');
}

// Функция экранирования HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================================
// ДАННЫЕ ДЛЯ СЕТЕЙ
// ============================================================================

// Хранилище всех сетей (загружается из БД)
let allNetworks = [...networksFromDB];

// Предустановленные сети (для быстрого доступа - берем из БД)
const predefinedNetworks = networksFromDB;

// Переменные для выбранных сетей
let selectedFromNetwork = { name: '' };
let selectedToNetwork = { name: '' };

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА СЕТИ (АНАЛОГИЧНЫЕ ВЫБОРУ АКТИВОВ)
// ============================================================================

// Функция для получения сети по имени
function getNetworkByName(name) {
    return allNetworks.find(n => n.name === name);
}

// Функция для добавления новой сети (с сохранением в БД)
async function addNetworkToDatabase(networkData) {
    const formData = new FormData();
    formData.append('action', 'add_network');
    formData.append('name', networkData.name);
    formData.append('icon', networkData.icon);
    formData.append('color', networkData.color);
    formData.append('full_name', networkData.full_name);
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && result.network_id) {
            // Добавляем в локальный массив
            allNetworks.push({
                id: result.network_id,
                name: networkData.name,
                icon: networkData.icon,
                color: networkData.color,
                full_name: networkData.full_name
            });
            return true;
        }
        return false;
    } catch (error) {
        return false;
    }
}

function openNetworkModal(context, currentNetwork = '') {
    setModalContext('transfer', 'network', context);
    
    const modalTitle = document.querySelector('#networkSelectModal .modal-header h2');
    let titleText = 'Выберите сеть';
    if (context === 'from') {
        titleText = 'Выберите сеть отправителя';
    } else if (context === 'to') {
        titleText = 'Выберите сеть получателя';
    }
    modalTitle.innerHTML = `<i class="fas fa-network-wired" style="color: #ff9f4a;"></i> ${titleText}`;
    
    const modal = document.getElementById('networkSelectModal');
    if (modal) {
        filterNetworksForSelect('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('networkSearch')?.focus();
        }, 100);
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
    
    let networksToShow = allNetworks;
    if (searchTextLower) {
        networksToShow = allNetworks.filter(n => 
            n.name.toLowerCase().includes(searchTextLower) || 
            (n.full_name && n.full_name.toLowerCase().includes(searchTextLower))
        );
    }
    
    // Сортируем: сначала предустановленные по порядку, потом пользовательские
    networksToShow.sort((a, b) => {
        const aIndex = networksFromDB.findIndex(p => p.name === a.name);
        const bIndex = networksFromDB.findIndex(p => p.name === b.name);
        if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex;
        if (aIndex !== -1) return -1;
        if (bIndex !== -1) return 1;
        return a.name.localeCompare(b.name);
    });
    
    if (networksToShow.length === 0 && originalSearchText) {
        listContainer.innerHTML = `
            <div onclick="addNewNetworkFromModal('${originalSearchText.replace(/'/g, "\\'")}')" 
                 style="padding: 15px; cursor: pointer; border-radius: 8px; text-align: center; color: #ff9f4a; transition: all 0.2s;" 
                 onmouseover="this.style.background='#f0f3f7'" 
                 onmouseout="this.style.background='transparent'">
                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i>
                Добавить сеть "${originalSearchText.toUpperCase()}"
            </div>
        `;
        return;
    }
    
    listContainer.innerHTML = networksToShow.map(network => {
        let iconHtml = `<i class="${network.icon}"></i>`;
        
        return `
            <div onclick="selectNetworkFromModal('${network.name.replace(/'/g, "\\'")}')" 
                 style="padding: 12px; cursor: pointer; border-radius: 10px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; border: 1px solid transparent;"
                 onmouseover="this.style.background='var(--bg-tertiary)'; this.style.borderColor='#e0e6ed'" 
                 onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'">
                <div style="width: 36px; height: 36px; background: ${network.color}20; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: ${network.color};">
                    ${iconHtml}
                </div>
                <div style="flex: 1;">
                    <div class="asset-symbol">${network.name}</div>
                    <div style="font-size: 12px; color: #6b7a8f;">${network.full_name || network.name}</div>
                </div>
                <i class="fas fa-chevron-right" style="color: #95a5a6; font-size: 12px;"></i>
            </div>
        `;
    }).join('');
}

function selectNetworkFromModal(networkName) {
    const context = currentModalContext.source;
    const subMode = currentModalContext.subMode;
    
    if (context === 'transfer') {
        if (subMode === 'from') {
            selectFromNetwork(networkName);
        } else if (subMode === 'to') {
            selectToNetwork(networkName);
        }
    } else if (context === 'trade') {
        // Для модального окна покупки/продажи
        selectTradeNetwork(networkName);
    }
    
    closeNetworkModal();
}

function selectFromNetwork(name) {
    selectedFromNetwork = { name };
    
    const display = document.getElementById('selectedFromNetworkDisplay');
    if (display) {
        display.textContent = name;
    }
    
    const hiddenInput = document.getElementById('transferNetworkFrom');
    if (hiddenInput) {
        hiddenInput.value = name;
    }
}

function selectToNetwork(name) {
    selectedToNetwork = { name };
    
    const display = document.getElementById('selectedToNetworkDisplay');
    if (display) {
        display.textContent = name;
    }
    
    const hiddenInput = document.getElementById('transferNetworkTo');
    if (hiddenInput) {
        hiddenInput.value = name;
    }
}

function addNewNetworkFromModal(networkName) {
    if (!networkName) return;
    
    const newNetworkName = networkName.trim().toUpperCase();
    
    // Проверяем, существует ли уже
    const exists = allNetworks.some(n => n.name === newNetworkName);
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
    
    if (!nameInput) return;
    
    nameInput.value = networkName.toUpperCase();
    if (fullNameInput) fullNameInput.value = '';
    if (colorInput) colorInput.value = '#ff9f4a';
    
    // Обновляем превью
    updateNetworkPreview(networkName.toUpperCase(), '');
    
    // Добавляем обработчики для превью
    if (fullNameInput) {
        fullNameInput.oninput = function() {
            updateNetworkPreview(nameInput.value, this.value);
        };
    }
    nameInput.oninput = function() {
        updateNetworkPreview(this.value, fullNameInput ? fullNameInput.value : '');
    };
    
    // Закрываем модальное окно выбора сети
    closeNetworkModal();
    
    if (modal) {
        modal.classList.add('active');
        
        setTimeout(() => {
            if (fullNameInput) fullNameInput.focus();
        }, 100);
    }
}

function updateNetworkPreview(name, fullName) {
    const previewIcon = document.getElementById('previewNetworkIcon');
    const previewName = document.getElementById('previewNetworkName');
    const previewFullName = document.getElementById('previewNetworkFullName');
    
    if (!previewName) return;
    
    // Определяем иконку по названию
    let icon = 'fas fa-network-wired';
    const upperName = name.toUpperCase();
    if (upperName.includes('ERC')) icon = 'fab fa-ethereum';
    else if (upperName.includes('BEP')) icon = 'fas fa-bolt';
    else if (upperName.includes('TRC')) icon = 'fab fa-t';
    else if (upperName === 'SOL') icon = 'fas fa-sun';
    else if (upperName === 'BTC') icon = 'fab fa-bitcoin';
    
    // Обновляем иконку
    if (previewIcon) {
        previewIcon.innerHTML = `<i class="${icon}"></i>`;
    }
    
    // Обновляем название
    previewName.textContent = name || 'Название сети';
    previewFullName.textContent = fullName || 'Полное название';
}

function closeAddNetworkModal() {
    const modal = document.getElementById('addNetworkModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

async function saveNewNetwork() {
    const networkName = document.getElementById('newNetworkName').value.toUpperCase();
    const networkFullName = document.getElementById('newNetworkFullName').value.trim();
    const networkColor = document.getElementById('newNetworkColor').value;
    
    if (!networkName) {
        showNotification('error', 'Ошибка', 'Введите аббревиатуру сети');
        return;
    }
    
    // Если полное название не указано, используем аббревиатуру
    const fullName = networkFullName || networkName;
    
    // Определяем иконку по названию
    let icon = 'fas fa-network-wired';
    const upperName = networkName.toUpperCase();
    if (upperName.includes('ERC')) icon = 'fab fa-ethereum';
    else if (upperName.includes('BEP')) icon = 'fas fa-bolt';
    else if (upperName.includes('TRC')) icon = 'fab fa-t';
    else if (upperName === 'SOL') icon = 'fas fa-sun';
    else if (upperName === 'BTC') icon = 'fab fa-bitcoin';
    
    // Сохраняем в базу данных
    const result = await addNetworkToDatabase({
        name: networkName,
        icon: icon,
        color: networkColor,
        full_name: fullName
    });
    
    if (result) {
        // Выбираем сеть в зависимости от контекста
        const context = currentModalContext.source;
        
        if (context === 'transfer') {
            if (currentModalContext.subMode === 'from') {
                selectFromNetwork(networkName);
            } else if (currentModalContext.subMode === 'to') {
                selectToNetwork(networkName);
            }
        } else if (context === 'trade') {
            selectTradeNetwork(networkName);
        }
        
        closeAddNetworkModal();
        showNotification('success', 'Успешно', `Сеть ${networkName} добавлена`);
    } else {
        showNotification('error', 'Ошибка', 'Не удалось добавить сеть');
    }
}

// ============================================================================
// ПОПУЛЯРНЫЕ СЕТИ ДЛЯ БЫСТРОГО ВЫБОРА
// ============================================================================

function renderPopularNetworksForTransfer() {
    const container = document.getElementById('transferPopularNetworks');
    if (!container) return;
    
    // Берем первые 6 сетей из БД для быстрого выбора
    const popular = networksFromDB.slice(0, 6);
    
    container.innerHTML = popular.map(network => `
        <button type="button" class="quick-asset-btn" onclick="selectFromNetwork('${network.name}')" 
                style="background: ${network.color}20; border-color: ${network.color}; color: ${network.color};">
            <i class="${network.icon}"></i> ${network.name}
        </button>
    `).join('');
}

// ============================================================================
// ПОПУЛЯРНЫЕ СЕТИ ДЛЯ БЫСТРОГО ВЫБОРА В ТОРГОВЛЕ
// ============================================================================

function renderPopularNetworksForTrade() {
    const container = document.getElementById('tradePopularNetworks');
    if (!container) return;
    
    // Берем первые 6 сетей из БД для быстрого выбора
    const popular = networksFromDB.slice(0, 6);
    
    container.innerHTML = popular.map(network => `
        <button type="button" class="quick-asset-btn" onclick="selectTradeNetwork('${network.name}')" 
                style="background: ${network.color}20; border-color: ${network.color}; color: ${network.color};">
            <i class="${network.icon}"></i> ${network.name}
        </button>
    `).join('');
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ВЫБОРА СЕТИ В ТОРГОВЛЕ
// ============================================================================

function selectTradeNetwork(name) {
    selectedTradeNetwork = { name };
    
    const display = document.getElementById('selectedTradeNetworkDisplay');
    if (display) {
        display.textContent = name;
    }
    
    const hiddenInput = document.getElementById('tradeNetwork');
    if (hiddenInput) {
        hiddenInput.value = name;
    }
}

function openTradeNetworkModal() {
    setModalContext('trade', 'network');
    
    const modalTitle = document.querySelector('#networkSelectModal .modal-header h2');
    modalTitle.innerHTML = '<i class="fas fa-network-wired" style="color: #ff9f4a;"></i> Выберите сеть';
    
    const modal = document.getElementById('networkSelectModal');
    if (modal) {
        filterNetworksForSelect('');
        modal.classList.add('active');
        
        setTimeout(() => {
            document.getElementById('networkSearch')?.focus();
        }, 100);
    }
}

// ============================================================================
// МОДАЛЬНОЕ ОКНО АКТИВОВ ПЛОЩАДКИ
// ============================================================================

function openPlatformAssetsModal(platformId, platformName) {
    const modal = document.getElementById('platformAssetsModal');
    const titleSpan = document.getElementById('platformAssetsName');
    const body = document.getElementById('platformAssetsBody');
    
    if (!modal || !body) return;
    
    // Устанавливаем заголовок
    titleSpan.textContent = platformName;
    
    // Показываем загрузку
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    
    modal.classList.add('active');
    
    // Получаем активы для этой площадки
    const platformData = platformAssetsData[platformId];
    
    if (!platformData || !platformData.assets || platformData.assets.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">На площадке "${platformName}" нет активов</p>
            </div>
        `;
        return;
    }
    
    // Сортируем активы по стоимости (от большей к меньшей)
    const assets = [...platformData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    
    // Рассчитываем общую стоимость
    let totalValueUsd = 0;
    
    assets.forEach(asset => {
        totalValueUsd += parseFloat(asset.value_usd) || 0;
    });
    
    const totalValueRub = totalValueUsd * usdRubRate;
    
    // Форматируем общую стоимость USD с пробелами только в целой части
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    
    // Форматируем общую стоимость RUB с пробелами только в целой части
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Формируем HTML
    let html = `
        <table class="platform-assets-table">
            <thead>
                <tr>
                    <th>Актив</th>
                    <th style="text-align: right;">Количество</th>
                    <th style="text-align: right;">Средняя цена</th>
                    <th style="text-align: right;">Стоимость</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        // Преобразуем quantity в число для форматирования
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        // ========== ФОРМАТИРОВАНИЕ КОЛИЧЕСТВА ==========
        let quantityFormatted = '';
        if (asset.asset_type === 'crypto') {
            if (Math.floor(quantityNum) === quantityNum) {
                quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
            } else {
                // Форматируем без пробелов в дробной части
                let str = quantityNum.toFixed(6).replace(/\.?0+$/, '');
                let parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            }
        } else if (asset.symbol === 'RUB' || asset.symbol === 'USD' || asset.symbol === 'EUR') {
            // Для фиата - 2 знака после запятой, пробелы только в целой части
            let str = quantityNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        } else {
            // Для остальных - до 4 знаков
            let str = quantityNum.toFixed(4).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        }
        
        // ========== ФОРМАТИРОВАНИЕ СРЕДНЕЙ ЦЕНЫ ==========
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            if (asset.asset_type === 'crypto' && asset.symbol !== 'USDT') {
                // Для криптовалют (кроме USDT) - до 4 знаков
                let str = avgPriceNum.toFixed(4).replace(/\.?0+$/, '');
                let parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            } else {
                // Для фиата и USDT - 2 знака
                let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
                let parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            }
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        // ========== ФОРМАТИРОВАНИЕ СТОИМОСТИ ==========
        const valueRubNum = valueUsdNum * usdRubRate;
        
        // Форматируем USD с пробелами только в целой части
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        
        // Форматируем RUB с пробелами только в целой части
        let rubStr = Math.round(valueRubNum).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        
        // Определяем иконку для актива
        let assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: var(--bg-tertiary, #f0f3f7); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div class="platform-assets-symbol">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.asset_name || asset.symbol}</div>
                        </div>
                    </div>
                </td>
                <td class="platform-assets-quantity">${quantityFormatted}</td>
                <td class="platform-assets-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td class="platform-assets-value">${valueFormatted}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
        
        <div class="platform-assets-summary">
            <div class="platform-assets-summary-row">
                <span style="font-weight: 600;">Всего активов:</span>
                <span style="font-weight: 600;">${assets.length}</span>
            </div>
            <div class="platform-assets-summary-row">
                <span>Общая стоимость:</span>
                <span class="platform-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span>
            </div>
        </div>
    `;
    
    body.innerHTML = html;
}

// Функция для получения иконки актива
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

function closePlatformAssetsModal() {
    const modal = document.getElementById('platformAssetsModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// ============================================================================
// МОДАЛЬНОЕ ОКНО АКТИВОВ СЕТИ
// ============================================================================

function openNetworkAssetsModal(networkName) {
    const modal = document.getElementById('networkAssetsModal');
    const titleSpan = document.getElementById('networkAssetsName');
    const body = document.getElementById('networkAssetsBody');
    
    if (!modal || !body) return;
    
    // Устанавливаем заголовок
    titleSpan.textContent = networkName;
    
    // Показываем загрузку
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    
    modal.classList.add('active');
    
    // Получаем активы для этой сети
    const networkData = networkAssetsData[networkName];
    
    if (!networkData || !networkData.assets || networkData.assets.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">В сети "${networkName}" нет активов</p>
            </div>
        `;
        return;
    }
    
    // Сортируем активы по стоимости (от большей к меньшей)
    const assets = [...networkData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    
    // Рассчитываем общую стоимость
    let totalValueUsd = networkData.total_value_usd;
    
    const totalValueRub = totalValueUsd * usdRubRate;
    
    // Форматируем общую стоимость
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Формируем HTML
    let html = `
        <style>
            .network-assets-table {
                width: 100%;
                border-collapse: collapse;
            }
            .network-assets-table th {
                text-align: left;
                padding: 12px 8px;
                background: var(--bg-tertiary, #f8fafd);
                font-weight: 600;
                font-size: 13px;
                color: var(--text-secondary, #6b7a8f);
                border-bottom: 2px solid var(--border-color, #edf2f7);
            }
            .network-assets-table td {
                padding: 12px 8px;
                border-bottom: 1px solid var(--border-color, #edf2f7);
                vertical-align: middle;
            }
            .network-assets-table tr:hover {
                background: var(--bg-tertiary, #f8fafd);
            }
            .network-assets-symbol {
                font-weight: 600;
                color: var(--text-primary, #2c3e50);
            }
            .network-assets-quantity {
                font-family: monospace;
                text-align: right;
            }
            .network-assets-value {
                text-align: right;
                font-weight: 500;
                color: #ff9f4a;
            }
            .network-assets-summary {
                background: var(--bg-tertiary, #f0f3f7);
                border-radius: 12px;
                padding: 16px;
                margin-top: 16px;
            }
            .network-assets-summary-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
            }
            .network-assets-summary-row:first-child {
                border-bottom: 1px solid var(--border-color, #e0e6ed);
                margin-bottom: 8px;
                padding-bottom: 12px;
            }
            .network-assets-total {
                font-weight: 700;
                font-size: 18px;
                color: #ff9f4a;
            }
            .dark-theme .network-assets-summary {
                background: var(--bg-tertiary);
            }
        </style>
        
        <table class="network-assets-table">
            <thead>
                <tr>
                    <th>Актив</th>
                    <th style="text-align: right;">Количество</th>
                    <th style="text-align: right;">Средняя цена</th>
                    <th style="text-align: right;">Стоимость</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        // ========== ФОРМАТИРОВАНИЕ КОЛИЧЕСТВА (МАКСИМУМ 8 ЗНАКОВ) ==========
        let quantityFormatted = '';
        
        // Преобразуем число в строку
        let quantityStr = quantityNum.toString();
        
        // Если число в экспоненциальной форме
        if (quantityStr.includes('e')) {
            quantityStr = quantityNum.toFixed(12);
        }
        
        // Разделяем целую и дробную части
        let quantityParts = quantityStr.split('.');
        
        // Форматируем целую часть с пробелами
        quantityParts[0] = quantityParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        if (quantityParts.length > 1 && quantityParts[1]) {
            // Берем дробную часть, ограничиваем до 8 знаков, убираем лишние нули в конце
            let decimalPart = quantityParts[1];
            
            // Ограничиваем до 8 знаков
            if (decimalPart.length > 8) {
                decimalPart = decimalPart.substring(0, 8);
            }
            
            // Убираем лишние нули в конце (но не все, если это не целое число)
            decimalPart = decimalPart.replace(/0+$/, '');
            
            if (decimalPart.length > 0) {
                quantityFormatted = quantityParts[0] + '.' + decimalPart;
            } else {
                quantityFormatted = quantityParts[0];
            }
        } else {
            quantityFormatted = quantityParts[0];
        }
        
        // ========== ФОРМАТИРОВАНИЕ СРЕДНЕЙ ЦЕНЫ ==========
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            // Для цены оставляем до 6 знаков после запятой
            let priceStr = avgPriceNum.toFixed(8).replace(/\.?0+$/, '');
            let priceParts = priceStr.split('.');
            priceParts[0] = priceParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = priceParts[0] + (priceParts[1] ? '.' + priceParts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        // ========== ФОРМАТИРОВАНИЕ СТОИМОСТИ ==========
        const valueRubNum = valueUsdNum * usdRubRate;
        
        // Форматируем USD
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        
        // Форматируем RUB
        let rubStr = Math.round(valueRubNum).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        
        // Определяем иконку для актива
        let assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: var(--bg-tertiary, #f0f3f7); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div class="network-assets-symbol">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </td>
                <td class="network-assets-quantity" style="font-family: monospace; white-space: nowrap;">${quantityFormatted}</td>
                <td class="network-assets-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td class="network-assets-value">${valueFormatted}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
        
        <div class="network-assets-summary">
            <div class="network-assets-summary-row">
                <span style="font-weight: 600;">Всего активов:</span>
                <span style="font-weight: 600;">${assets.length}</span>
            </div>
            <div class="network-assets-summary-row">
                <span>Общая стоимость в сети ${networkName}:</span>
                <span class="network-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span>
            </div>
        </div>
    `;
    
    body.innerHTML = html;
}

function closeNetworkAssetsModal() {
    const modal = document.getElementById('networkAssetsModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Добавляем обработчики для модального окна активов сети
document.getElementById('closeNetworkAssetsModalBtn')?.addEventListener('click', closeNetworkAssetsModal);
document.getElementById('closeNetworkAssetsModalFooterBtn')?.addEventListener('click', closeNetworkAssetsModal);

// Закрытие по клику на overlay
const networkAssetsModal = document.getElementById('networkAssetsModal');
if (networkAssetsModal) {
    networkAssetsModal.addEventListener('click', (e) => {
        if (e.target === networkAssetsModal) {
            closeNetworkAssetsModal();
        }
    });
}

// ============================================================================
// МОДАЛЬНОЕ ОКНО АКТИВОВ ПО СЕКТОРАМ
// ============================================================================

function openSectorAssetsModal(sectorName, displayName) {
    const modal = document.getElementById('sectorAssetsModal');
    const titleSpan = document.getElementById('sectorAssetsName');
    const body = document.getElementById('sectorAssetsBody');
    
    if (!modal || !body) return;
    
    // Устанавливаем заголовок
    titleSpan.textContent = displayName;
    
    // Показываем загрузку
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    
    modal.classList.add('active');
    
    // Получаем активы для этого сектора
    const sectorData = sectorAssetsData[sectorName];
    
    if (!sectorData || !sectorData.assets || sectorData.assets.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">В секторе "${displayName}" нет активов</p>
            </div>
        `;
        return;
    }
    
    // Сортируем активы по стоимости (от большей к меньшей)
    const assets = [...sectorData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    
    // Рассчитываем общую стоимость
    let totalValueUsd = sectorData.total_value_usd;
    
    // Получаем курс USD/RUB
    const totalValueRub = totalValueUsd * usdRubRate;
    
    // Форматируем общую стоимость
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Формируем HTML
    let html = `
        <style>
            .sector-assets-table {
                width: 100%;
                border-collapse: collapse;
            }
            .sector-assets-table th {
                text-align: left;
                padding: 12px 8px;
                background: var(--bg-tertiary, #f8fafd);
                font-weight: 600;
                font-size: 13px;
                color: var(--text-secondary, #6b7a8f);
                border-bottom: 2px solid var(--border-color, #edf2f7);
            }
            .sector-assets-table td {
                padding: 12px 8px;
                border-bottom: 1px solid var(--border-color, #edf2f7);
                vertical-align: middle;
            }
            .sector-assets-table tr:hover {
                background: var(--bg-tertiary, #f8fafd);
            }
            .sector-assets-symbol {
                font-weight: 600;
                color: var(--text-primary, #2c3e50);
            }
            .sector-assets-quantity {
                font-family: monospace;
                text-align: right;
            }
            .sector-assets-value {
                text-align: right;
                font-weight: 500;
                color: #4a9eff;
            }
            .sector-assets-summary {
                background: var(--bg-tertiary, #f0f3f7);
                border-radius: 12px;
                padding: 16px;
                margin-top: 16px;
            }
            .sector-assets-summary-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
            }
            .sector-assets-summary-row:first-child {
                border-bottom: 1px solid var(--border-color, #e0e6ed);
                margin-bottom: 8px;
                padding-bottom: 12px;
            }
            .sector-assets-total {
                font-weight: 700;
                font-size: 18px;
                color: #4a9eff;
            }
            .dark-theme .sector-assets-summary {
                background: var(--bg-tertiary);
            }
        </style>
        
        <table class="sector-assets-table">
            <thead>
                <tr>
                    <th>Актив</th>
                    <th style="text-align: right;">Количество</th>
                    <th style="text-align: right;">Средняя цена</th>
                    <th style="text-align: right;">Стоимость</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        // Форматирование количества
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
        
        // Форматирование средней цены
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        // Форматирование стоимости
        const valueRubNum = valueUsdNum * usdRubRate;
        
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        
        let rubStr = Math.round(valueRubNum).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        let valueFormatted;
        if (asset.currency_code === 'RUB' || asset.symbol === 'RUB') {
            // Для рублевых активов - показываем только рубли
            const rubAmount = (asset.quantity * asset.average_buy_price).toFixed(2);
            const rubFormatted = rubAmount.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            valueFormatted = `${rubFormatted} ₽`;
        } else {
            // Для остальных - USD и RUB
            const usdFormatted = asset.value_usd.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            const rubValue = (asset.value_usd * usdRubRate).toFixed(0);
            const rubFormatted = rubValue.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubFormatted} ₽</span>`;
        }
        
        // Определяем иконку для актива
        let assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: var(--bg-tertiary, #f0f3f7); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div class="sector-assets-symbol">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </td>
                <td class="sector-assets-quantity">${quantityFormatted}</td>
                <td class="sector-assets-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td class="sector-assets-value">${valueFormatted}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
        
        <div class="sector-assets-summary">
            <div class="sector-assets-summary-row">
                <span style="font-weight: 600;">Всего активов:</span>
                <span style="font-weight: 600;">${assets.length}</span>
            </div>
            <div class="sector-assets-summary-row">
                <span>Общая стоимость в секторе ${displayName}:</span>
                <span class="sector-assets-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span>
            </div>
        </div>
    `;
    
    body.innerHTML = html;
}

function closeSectorAssetsModal() {
    const modal = document.getElementById('sectorAssetsModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// ============================================================================
// МОДАЛЬНОЕ ОКНО АКТИВОВ ПО ТИПАМ КРИПТОВАЛЮТ
// ============================================================================

function openCryptoTypeModal(type, displayName) {
    const modal = document.getElementById('cryptoTypeModal');
    const titleSpan = document.getElementById('cryptoTypeName');
    const body = document.getElementById('cryptoTypeBody');
    
    if (!modal || !body) return;
    
    // Устанавливаем заголовок
    titleSpan.textContent = displayName;
    
    // Показываем загрузку
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка активов...</div>';
    
    modal.classList.add('active');
    
    // Получаем активы для этого типа
    const typeData = cryptoTypeAssetsData[type];
    
    if (!typeData || !typeData.assets || typeData.assets.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">В категории "${displayName}" нет активов</p>
            </div>
        `;
        return;
    }
    
    // Сортируем активы по стоимости (от большей к меньшей)
    const assets = [...typeData.assets].sort((a, b) => (parseFloat(b.value_usd) || 0) - (parseFloat(a.value_usd) || 0));
    
    // Рассчитываем общую стоимость
    let totalValueUsd = typeData.total_value_usd;
    
    // Получаем курс USD/RUB
    const totalValueRub = totalValueUsd * usdRubRate;
    
    // Форматируем общую стоимость
    let totalUsdStr = totalValueUsd.toFixed(2);
    let totalUsdParts = totalUsdStr.split('.');
    totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
    
    let totalRubStr = Math.round(totalValueRub).toString();
    totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    // Формируем HTML
    let html = `
        <style>
            .crypto-type-table {
                width: 100%;
                border-collapse: collapse;
            }
            .crypto-type-table th {
                text-align: left;
                padding: 12px 8px;
                background: var(--bg-tertiary, #f8fafd);
                font-weight: 600;
                font-size: 13px;
                color: var(--text-secondary, #6b7a8f);
                border-bottom: 2px solid var(--border-color, #edf2f7);
            }
            .crypto-type-table td {
                padding: 12px 8px;
                border-bottom: 1px solid var(--border-color, #edf2f7);
                vertical-align: middle;
            }
            .crypto-type-table tr:hover {
                background: var(--bg-tertiary, #f8fafd);
            }
            .crypto-type-symbol {
                font-weight: 600;
                color: var(--text-primary, #2c3e50);
            }
            .crypto-type-quantity {
                font-family: monospace;
                text-align: right;
            }
            .crypto-type-value {
                text-align: right;
                font-weight: 500;
                color: #ff9f4a;
            }
            .crypto-type-summary {
                background: var(--bg-tertiary, #f0f3f7);
                border-radius: 12px;
                padding: 16px;
                margin-top: 16px;
            }
            .crypto-type-summary-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
            }
            .crypto-type-summary-row:first-child {
                border-bottom: 1px solid var(--border-color, #e0e6ed);
                margin-bottom: 8px;
                padding-bottom: 12px;
            }
            .crypto-type-total {
                font-weight: 700;
                font-size: 18px;
                color: #ff9f4a;
            }
            .dark-theme .crypto-type-summary {
                background: var(--bg-tertiary);
            }
        </style>
        
        <table class="crypto-type-table">
            <thead>
                <tr>
                    <th>Актив</th>
                    <th style="text-align: right;">Количество</th>
                    <th style="text-align: right;">Средняя цена</th>
                    <th style="text-align: right;">Стоимость</th>
                 </tr>
            </thead>
            <tbody>
    `;
    
    assets.forEach(asset => {
        const quantityNum = parseFloat(asset.quantity) || 0;
        const avgPriceNum = parseFloat(asset.average_buy_price) || 0;
        const valueUsdNum = parseFloat(asset.value_usd) || 0;
        
        // Форматирование количества
        let quantityFormatted = '';
        if (Math.floor(quantityNum) === quantityNum) {
            quantityFormatted = quantityNum.toLocaleString('ru-RU').replace(/,/g, ' ');
        } else {
            let str = quantityNum.toFixed(6).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
        }
        
        // Форматирование средней цены
        let avgPriceFormatted = '—';
        let avgPriceCurrency = '';
        if (avgPriceNum > 0) {
            let str = avgPriceNum.toFixed(2).replace(/\.?0+$/, '');
            let parts = str.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgPriceFormatted = parts[0] + (parts[1] ? '.' + parts[1] : '');
            avgPriceCurrency = asset.currency_code || 'USD';
        }
        
        // Форматирование стоимости
        const valueRubNum = valueUsdNum * usdRubRate;
        
        let usdStr = valueUsdNum.toFixed(2);
        let usdParts = usdStr.split('.');
        usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
        
        let rubStr = Math.round(valueRubNum).toString();
        rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        
        const valueFormatted = `$${usdFormatted}<br><span style="font-size: 11px; color: #6b7a8f;">${rubStr} ₽</span>`;
        
        // Определяем иконку для актива
        let assetIcon = getAssetIcon(asset.symbol);
        
        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; background: var(--bg-tertiary, #f0f3f7); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="${assetIcon.icon}" style="color: ${assetIcon.color};"></i>
                        </div>
                        <div>
                            <div class="crypto-type-symbol">${asset.symbol}</div>
                            <div style="font-size: 11px; color: #6b7a8f;">${asset.platform_name}</div>
                        </div>
                    </div>
                </td>
                <td class="crypto-type-quantity">${quantityFormatted}</td>
                <td class="crypto-type-quantity">${avgPriceFormatted} ${avgPriceCurrency}</td>
                <td class="crypto-type-value">${valueFormatted}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
        
        <div class="crypto-type-summary">
            <div class="crypto-type-summary-row">
                <span style="font-weight: 600;">Всего активов:</span>
                <span style="font-weight: 600;">${assets.length}</span>
            </div>
            <div class="crypto-type-summary-row">
                <span>Общая стоимость в категории ${displayName}:</span>
                <span class="crypto-type-total">$${totalUsdFormatted}<br><span style="font-size: 12px; font-weight: normal;">${totalRubStr} ₽</span></span>
            </div>
        </div>
    `;
    
    body.innerHTML = html;
}

function closeCryptoTypeModal() {
    const modal = document.getElementById('cryptoTypeModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Функция для загрузки истории покупок при продаже
async function loadPurchaseHistoryForSell(assetId, platformId) {
    if (!assetId || !platformId) return;
    
    const historyBlock = document.getElementById('sellPurchaseHistory');
    const purchaseList = document.getElementById('sellPurchaseList');
    const currentBalanceSpan = document.getElementById('sellCurrentBalance');
    const quickActions = document.getElementById('sellQuickActions');
    
    if (!historyBlock) return;
    
    // Показываем блок с загрузкой
    historyBlock.style.display = 'block';
    purchaseList.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7a8f;"><i class="fas fa-spinner fa-spin"></i> Загрузка истории...</div>';
    quickActions.style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'get_purchase_history');
    formData.append('asset_id', assetId);
    formData.append('platform_id', platformId);
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            const currentQuantity = data.current_quantity;
            const avgPrice = data.avg_buy_price;
            const purchases = data.purchases;
            
            // Показываем текущий баланс
            const assetSymbol = document.getElementById('selectedTradeAssetDisplay').textContent;
            currentBalanceSpan.innerHTML = `<i class="fas fa-wallet"></i> Доступно: ${formatAmount(currentQuantity, assetSymbol)} ${assetSymbol}`;
            
            if (purchases.length === 0) {
                purchaseList.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-info-circle"></i> Нет истории покупок на этой площадке
                    </div>
                `;
                quickActions.style.display = 'none';
            } else {
                // Формируем список покупок
                let html = '<div style="font-size: 12px; margin-bottom: 8px; color: #6b7a8f;">История покупок:</div>';
                
                purchases.forEach(purchase => {
                    const date = new Date(purchase.operation_date).toLocaleDateString('ru-RU');
                    const quantity = formatAmount(purchase.quantity, assetSymbol);
                    const price = formatAmount(purchase.price, purchase.price_currency);
                    const total = formatAmount(purchase.quantity * purchase.price, purchase.price_currency);
                    
                    html += `
                        <div class="purchase-item" style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border-color, #edf2f7); cursor: pointer;" 
                             onclick="fillSellFromPurchase(${purchase.quantity}, ${purchase.price}, '${purchase.price_currency}')">
                            <div>
                                <div style="font-weight: 500;">${quantity} ${assetSymbol}</div>
                                <div style="font-size: 11px; color: #6b7a8f;">${date}</div>
                            </div>
                            <div style="text-align: right;">
                                <div>по ${price} ${purchase.price_currency}</div>
                                <div style="font-size: 11px; color: #00a86b;">${total} ${purchase.price_currency}</div>
                            </div>
                        </div>
                    `;
                });
                
                // Добавляем информацию о средней цене
                if (avgPrice > 0) {
                    html += `
                        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--border-color, #e0e6ed);">
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span><i class="fas fa-chart-line"></i> Средняя цена покупки:</span>
                                <span style="font-weight: 600;">${formatAmount(avgPrice, 'USD')} USD</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 4px;">
                                <span><i class="fas fa-coins"></i> Общая стоимость:</span>
                                <span style="font-weight: 600;">${formatAmount(currentQuantity * avgPrice, 'USD')} USD</span>
                            </div>
                        </div>
                    `;
                }
                
                purchaseList.innerHTML = html;
                quickActions.style.display = 'block';
                
                // Сохраняем данные для быстрых действий
                window.sellAssetData = {
                    assetId: assetId,
                    platformId: platformId,
                    currentQuantity: currentQuantity,
                    avgPrice: avgPrice,
                    symbol: document.getElementById('selectedTradeAssetDisplay').textContent
                };
            }
        } else {
            purchaseList.innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;">Ошибка загрузки истории</div>';
            quickActions.style.display = 'none';
        }
    } catch (error) {
        purchaseList.innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;">Ошибка загрузки</div>';
        quickActions.style.display = 'none';
    }
}

// Функция для заполнения формы из конкретной покупки
function fillSellFromPurchase(quantity, price, currency) {
    const quantityInput = document.getElementById('tradeQuantity');
    const priceInput = document.getElementById('tradePrice');
    const priceCurrencyBtn = document.getElementById('selectedTradePriceCurrencyDisplay');
    const priceCurrencyHidden = document.getElementById('tradePriceCurrency');
    
    if (quantityInput) {
        // Форматируем количество
        let quantityStr = quantity.toString();
        if (Number.isInteger(quantity)) {
            quantityStr = quantity.toLocaleString('ru-RU').replace(/,/g, ' ');
        } else {
            quantityStr = quantity.toFixed(6).replace(/\.?0+$/, '');
            let parts = quantityStr.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityStr = parts.join('.');
        }
        quantityInput.value = quantityStr;
        
        // Триггерим событие для пересчета итога
        quantityInput.dispatchEvent(new Event('input'));
    }
    
    if (priceInput) {
        // Форматируем цену
        let priceStr = price.toFixed(2);
        let parts = priceStr.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        priceInput.value = parts.join('.');
        
        // Триггерим событие для пересчета итога
        priceInput.dispatchEvent(new Event('input'));
    }
    
    // Устанавливаем валюту цены
    if (priceCurrencyBtn && priceCurrencyHidden) {
        priceCurrencyBtn.textContent = currency;
        priceCurrencyHidden.value = currency;
        
        // Копируем валюту в комиссию, если там ничего не выбрано
        const commissionDisplay = document.getElementById('selectedTradeCommissionCurrencyDisplay');
        const commissionHidden = document.getElementById('tradeCommissionCurrency');
        
        if (commissionDisplay && commissionHidden && !commissionHidden.value) {
            commissionDisplay.textContent = currency;
            commissionHidden.value = currency;
        }
    }
    
    // Показываем уведомление
    //showNotification('info', 'Заполнено', `Количество: ${quantity} ${window.sellAssetData?.symbol || ''}`);
}

// Функция для быстрого заполнения "Продать всё"
function fillSellAll() {
    if (!window.sellAssetData) return;
    
    const quantity = window.sellAssetData.currentQuantity;
    const avgPrice = window.sellAssetData.avgPrice;
    
    if (quantity <= 0) {
        showNotification('error', 'Ошибка', 'Нет доступного количества для продажи');
        return;
    }
    
    const quantityInput = document.getElementById('tradeQuantity');
    if (quantityInput) {
        let quantityStr = quantity.toString();
        if (Number.isInteger(quantity)) {
            quantityStr = quantity.toLocaleString('ru-RU').replace(/,/g, ' ');
        } else {
            quantityStr = quantity.toFixed(6).replace(/\.?0+$/, '');
            let parts = quantityStr.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            quantityStr = parts.join('.');
        }
        quantityInput.value = quantityStr;
        quantityInput.dispatchEvent(new Event('input'));
    }
    
    showNotification('info', 'Заполнено', `Продажа всего количества: ${formatAmount(quantity, window.sellAssetData.symbol)} ${window.sellAssetData.symbol}`);
}

// Функция для быстрого заполнения по средней цене
function fillSellByAvgPrice() {
    if (!window.sellAssetData || window.sellAssetData.avgPrice <= 0) {
        showNotification('error', 'Ошибка', 'Нет данных о средней цене');
        return;
    }
    
    const priceInput = document.getElementById('tradePrice');
    const price = window.sellAssetData.avgPrice;
    
    if (priceInput) {
        let priceStr = price.toFixed(2);
        let parts = priceStr.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        priceInput.value = parts.join('.');
        priceInput.dispatchEvent(new Event('input'));
    }
    
    showNotification('info', 'Заполнено', `Цена установлена по средней: ${formatAmount(window.sellAssetData.avgPrice, 'USD')} USD`);
}

// Функция для загрузки баланса площадки
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
    
    if (balanceTitle) {
        balanceTitle.innerHTML = `<i class="fas fa-wallet"></i> Баланс: ${platformName}`;
    }
    
    balanceBlock.style.display = 'block';
    assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #6b7a8f;"><i class="fas fa-spinner fa-spin"></i> Загрузка баланса...</div>';
    totalDiv.style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'get_platform_balance');
    formData.append('platform_id', platformId);
    
    try {
        const response = await fetch(API_URL_PHP, {
            method: 'POST',
            body: formData
        });
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
            
            totalValueSpan.innerHTML = `<i class="fas fa-chart-line"></i> ${totalUsdFormatted} $ / ${totalRubStr} ₽`;
            
            if (assets.length === 0) {
                assetsList.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-box-open"></i> Нет активов на площадке ${platformName}
                    </div>
                `;
                totalDiv.style.display = 'none';
            } else {
                let html = '';
                
                assets.forEach(asset => {
                    const quantity = parseFloat(asset.quantity);
                    const valueUsd = parseFloat(asset.value_usd);
                    const valueRub = valueUsd * usdRubRate;
                    
                    let quantityFormatted = '';
                    if (asset.asset_type === 'crypto') {
                        if (Math.floor(quantity) === quantity) {
                            quantityFormatted = quantity.toLocaleString('ru-RU').replace(/,/g, ' ');
                        } else {
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
                    
                    let assetIcon = getAssetIcon(asset.symbol);
                    
                    // Формируем строку с сетью, если она есть
                    let networkHtml = '';
                    if (asset.network && asset.asset_type === 'crypto') {
                        networkHtml = `<div style="font-size: 10px; color: #6b7a8f; margin-top: 2px;">
                            <i class="fas fa-network-wired"></i> ${asset.network}
                        </div>`;
                    }
                    
                    html += `
                        <div class="platform-asset-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; margin-right:20px; border-bottom: 1px solid var(--border-color, #edf2f7); cursor: pointer;" 
                             onclick="selectTransferAssetFromBalance('${asset.asset_id}', '${asset.symbol}', '${asset.asset_type}', '${quantityFormatted}')"
                             onmouseover="this.style.background='var(--bg-tertiary)'; this.style.borderRadius='8px'; this.style.paddingLeft='8px'; this.style.paddingRight='8px';" 
                             onmouseout="this.style.background='transparent'; this.style.paddingLeft='0'; this.style.paddingRight='0';">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 28px; height: 28px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="${assetIcon.icon}" style="color: ${assetIcon.color}; font-size: 14px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 500; font-size: 13px;">${asset.symbol}</div>
                                    <div style="font-size: 10px; color: #6b7a8f;">${quantityFormatted}</div>
                                    ${networkHtml}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 12px; font-weight: 500;">$${usdFormatted}</div>
                                <div style="font-size: 10px; color: #6b7a8f;">${rubStr} ₽</div>
                            </div>
                        </div>
                    `;
                });
                
                assetsList.innerHTML = html;
                totalDiv.style.display = 'block';
                totalUsdSpan.innerHTML = `$${totalUsdFormatted} (${totalRubStr} ₽)`;
            }
            
            currentPlatformBalanceData = {
                platformId: platformId,
                platformName: platformName,
                assets: assets,
                totalUsd: totalUsd,
                totalRub: totalRub
            };
            
        } else {
            assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #e53e3e;">Ошибка загрузки баланса</div>';
            totalDiv.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading platform balance:', error);
        assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #e53e3e;">Ошибка загрузки</div>';
        totalDiv.style.display = 'none';
    }
}

// Функция для сброса заголовка баланса
function resetPlatformBalanceTitle() {
    const balanceTitle = document.getElementById('platformBalanceTitle');
    if (balanceTitle) {
        balanceTitle.innerHTML = '<i class="fas fa-wallet"></i> Баланс площадки';
    }
}

// Модифицируем функцию closeTransferModal для сброса заголовка
function closeTransferModal() {
    transferModal.classList.remove('active');
    hidePlatformBalance();
    resetPlatformBalanceTitle(); // Добавляем сброс заголовка
    currentPlatformBalanceData = null;
}

// Функция для быстрого выбора актива из баланса
function selectTransferAssetFromBalance(assetId, symbol, assetType, quantityFormatted) {
    // Выбираем актив
    selectAsset(assetId, symbol);
    
    // Показываем уведомление с количеством
    //showNotification('info', 'Актив выбран', `${symbol}: доступно ${quantityFormatted}`);
    
    // АВТОМАТИЧЕСКИ ЗАПОЛНЯЕМ КОЛИЧЕСТВО ДЛЯ ПЕРЕВОДА
    const amountInput = document.getElementById('transferAmount');
    if (amountInput) {
        // Извлекаем числовое значение из отформатированной строки
        // quantityFormatted приходит в виде "123.456" или "1 234.567"
        const numericValue = parseFloat(quantityFormatted.replace(/\s/g, '').replace(',', '.'));
        if (!isNaN(numericValue) && numericValue > 0) {
            // Форматируем число с пробелами
            let formattedValue = numericValue.toString();
            
            // Для криптовалют - больше знаков после запятой
            if (assetType === 'crypto') {
                formattedValue = numericValue.toFixed(6).replace(/\.?0+$/, '');
            } else {
                formattedValue = numericValue.toFixed(2).replace(/\.?0+$/, '');
            }
            
            // Добавляем пробелы в целой части
            let parts = formattedValue.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            
            amountInput.value = parts.join('.');
            
            // Триггерим событие input для возможных расчетов (если есть)
            amountInput.dispatchEvent(new Event('input'));
        }
    }
}

// Функция для скрытия блока баланса
function hidePlatformBalance() {
    const balanceBlock = document.getElementById('transferFromPlatformBalance');
    if (balanceBlock) {
        balanceBlock.style.display = 'none';
    }
}

// Функция открытия модального окна добавления категории
function openAddExpenseCategoryModal() {
    const modal = document.getElementById('addExpenseCategoryModal');
    if (modal) {
        modal.classList.add('active');
        disableBodyScroll();
        
        // Сбрасываем поля
        document.getElementById('newCategoryName').value = '';
        document.getElementById('newCategoryNameRu').value = '';
        document.getElementById('newCategoryIcon').value = 'fas fa-tag';
        document.getElementById('newCategoryColor').value = '#ff9f4a';
        
        // Обновляем превью
        updateCategoryPreview();
    }
}

function closeAddExpenseCategoryModal() {
    const modal = document.getElementById('addExpenseCategoryModal');
    if (modal) {
        modal.classList.remove('active');
        enableBodyScroll(); // Разблокируем скролл
    }
}

// Обновление превью иконки и цвета
function updateCategoryPreview() {
    const icon = document.getElementById('newCategoryIcon').value;
    const color = document.getElementById('newCategoryColor').value;
    const previewIcon = document.querySelector('#iconPreview i');
    const previewText = document.getElementById('iconPreviewText');
    
    if (previewIcon) {
        // Разбиваем строку иконки (например "fas fa-tag")
        const iconParts = icon.split(' ');
        previewIcon.className = '';
        iconParts.forEach(part => {
            previewIcon.classList.add(part);
        });
        previewIcon.style.color = color;
    }
    if (previewText) {
        previewText.textContent = icon;
    }
}

// Функция сохранения категории
async function saveExpenseCategory() {
    const name = document.getElementById('newCategoryName').value.trim().toLowerCase();
    const name_ru = document.getElementById('newCategoryNameRu').value.trim();
    const icon = document.getElementById('newCategoryIcon').value.trim();
    const color = document.getElementById('newCategoryColor').value;
    
    if (!name || !name_ru) {
        showNotification('error', 'Ошибка', 'Заполните название категории');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_expense_category');
    formData.append('name', name);
    formData.append('name_ru', name_ru);
    formData.append('icon', icon);
    formData.append('color', color);
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeAddExpenseCategoryModal();
            await loadExpenseCategories(); // Перезагружаем список категорий
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось добавить категорию');
    }
}

// Функция выбора иконки
function openIconSelectModal() {
    const modal = document.getElementById('iconSelectModal');
    if (modal) {
        modal.classList.add('active');
        disableBodyScroll();
    }
}

function closeIconSelectModal() {
    const modal = document.getElementById('iconSelectModal');
    if (modal) {
        modal.classList.remove('active');
        enableBodyScroll();
    }
}

function selectIcon(iconClass) {
    document.getElementById('newCategoryIcon').value = iconClass;
    updateCategoryPreview();
    closeIconSelectModal();
}

// Функция открытия списка расходов
async function openExpensesListModal() {
    const modal = document.getElementById('expensesListModal');
    const body = document.getElementById('expensesListBody');
    
    if (!modal || !body) return;
    
    modal.classList.add('active');
    disableBodyScroll();
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка расходов...</div>';
    
    await loadExpensesList();
}

function closeExpensesListModal() {
    const modal = document.getElementById('expensesListModal');
    if (modal) {
        modal.classList.remove('active');
        enableBodyScroll();
    }
}

// Функция загрузки списка расходов
async function loadExpensesList() {
    const body = document.getElementById('expensesListBody');
    if (!body) return;
    
    const formData = new FormData();
    formData.append('action', 'get_expenses');
    formData.append('limit', 50);
    formData.append('offset', 0);
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            displayExpensesList(result);
        } else {
            body.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки расходов</div>';
        }
    } catch (error) {
        body.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки</div>';
    }
}

// Загрузка категорий расходов
async function loadExpenseCategories() {
    const container = document.getElementById('expenseCategoriesList');
    if (!container) return;
    
    container.innerHTML = '<div style="text-align: center; padding: 10px; width: 100%; color: #6b7a8f;"><i class="fas fa-spinner fa-spin"></i> Загрузка категорий...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_expense_categories');
    
    try {
        const response = await fetch(API_URL_PHP, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && result.categories) {
            expenseCategories = result.categories; // сохраняем глобально
            displayExpenseCategories(result.categories);
        } else {
            container.innerHTML = '<div style="text-align: center; padding: 10px; width: 100%; color: #e53e3e;">Ошибка загрузки категорий</div>';
        }
    } catch (error) {
        container.innerHTML = '<div style="text-align: center; padding: 10px; width: 100%; color: #e53e3e;">Ошибка загрузки</div>';
    }
}

// Отображение категорий расходов
function displayExpenseCategories(categories) {
    const container = document.getElementById('expenseCategoriesList');
    if (!container) return;
    
    if (!categories || categories.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 10px; width: 100%; color: #6b7a8f;">Нет категорий расходов</div>';
        return;
    }
    
    let html = '';
    categories.forEach(category => {
        html += `
            <button type="button" class="quick-asset-btn" 
                    data-category-id="${category.id}"
                    onclick="selectExpenseCategory(${category.id}, '${category.name_ru.replace(/'/g, "\\'")}')"
                    style="background: ${category.color}20; border-color: ${category.color}; color: ${category.color};">
                <i class="${category.icon}"></i> ${category.name_ru}
            </button>
        `;
    });
    
    container.innerHTML = html;
}

// Выбор категории расхода
function selectExpenseCategory(categoryId, categoryName) {
    // Убираем активный класс у всех кнопок
    document.querySelectorAll('#expenseCategoriesList .quick-asset-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.style.opacity = '0.7';
    });
    
    // Добавляем активный класс выбранной кнопке
    const selectedBtn = document.querySelector(`#expenseCategoriesList .quick-asset-btn[data-category-id="${categoryId}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
        selectedBtn.style.opacity = '1';
    }
    
    // Сохраняем выбранную категорию
    const hiddenInput = document.getElementById('expenseCategoryId');
    if (hiddenInput) {
        hiddenInput.value = categoryId;
    }
    
    // Показываем уведомление
    // showNotification('info', 'Категория выбрана', categoryName);
}

// Функция отображения списка расходов
function displayExpensesList(data) {
    const body = document.getElementById('expensesListBody');
    if (!body) return;
    
    const expenses = data.expenses;
    const total = data.total;
    const stats = data.stats;
    
    if (expenses.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-receipt" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <p style="color: #6b7a8f;">Нет добавленных расходов</p>
                <button class="add-order-btn" onclick="openExpenseModal()" style="margin-top: 15px;">
                    <i class="fas fa-plus-circle"></i> Добавить расход
                </button>
            </div>
        `;
        return;
    }
    
    // Формируем статистику
    let statsHtml = `
        <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span style="font-weight: 600;">Всего расходов:</span>
                <span style="font-weight: 600; color: #ff9f4a;">${formatAmount(total, 'RUB')} ₽</span>
            </div>
            <div style="margin-top: 10px;">
                <div style="font-size: 12px; color: #6b7a8f; margin-bottom: 8px;">По категориям:</div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
    `;
    
    stats.forEach(stat => {
        const category = expenseCategories.find(c => c.name === stat.category);
        const color = category ? category.color : '#95a5a6';
        statsHtml += `
            <span style="background: ${color}20; color: ${color}; padding: 4px 10px; border-radius: 20px; font-size: 12px;">
                ${category?.name_ru || stat.category}: ${formatAmount(stat.total_amount, 'RUB')} ₽
            </span>
        `;
    });
    
    statsHtml += `
                </div>
            </div>
        </div>
    `;
    
    // Формируем список расходов
    let expensesHtml = `
        <div style="display: flex; flex-direction: column; gap: 10px;">
    `;
    
    expenses.forEach(expense => {
        const category = expenseCategories.find(c => c.name === expense.category);
        const icon = category?.icon || 'fas fa-receipt';
        const color = category?.color || '#95a5a6';
        const date = new Date(expense.expense_date).toLocaleDateString('ru-RU');
        
        expensesHtml += `
            <div class="expense-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-secondary); border-radius: 12px; border-left: 4px solid ${color};">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 36px; height: 36px; background: ${color}20; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="${icon}" style="color: ${color};"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500;">${category?.name_ru || expense.category}</div>
                        <div style="font-size: 12px; color: #6b7a8f;">${date}</div>
                        ${expense.description ? `<div style="font-size: 11px; color: #6b7a8f; margin-top: 2px;">${escapeHtml(expense.description)}</div>` : ''}
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 600; color: #e53e3e;">- ${formatAmount(expense.amount, expense.currency_code)} ${expense.currency_code}</div>
                    <button class="delete-expense-btn" data-id="${expense.id}" style="background: none; border: none; color: #95a5a6; cursor: pointer; margin-top: 4px; font-size: 12px;">
                        <i class="fas fa-trash-alt"></i> Удалить
                    </button>
                </div>
            </div>
        `;
    });
    
    expensesHtml += '</div>';
    
    body.innerHTML = statsHtml + expensesHtml;
    
    // Добавляем обработчики удаления
    document.querySelectorAll('.delete-expense-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.stopPropagation();
            const expenseId = this.dataset.id;
            if (confirm('Удалить этот расход?')) {
                await deleteExpense(expenseId);
            }
        });
    });
}

// Функция удаления расхода
async function deleteExpense(expenseId) {
    const formData = new FormData();
    formData.append('action', 'delete_expense');
    formData.append('expense_id', expenseId);
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            await loadExpensesList(); // Перезагружаем список
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось удалить расход');
    }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ РАСХОДОВ (НОВАЯ ВЕРСИЯ)
// ============================================================================

let selectedExpensePlatform = { id: null, name: '' };
let selectedExpenseAsset = { id: null, symbol: '' };
let expensePlatformBalanceData = null;

function openExpenseModal() {
    const modal = document.getElementById('expenseModal');
    if (modal) {
        modal.classList.add('active');
        disableBodyScroll();
        
        // Устанавливаем дату
        document.getElementById('expenseDate').value = new Date().toISOString().split('T')[0];
        
        // Сбрасываем поля
        document.getElementById('expenseAmount').value = '';
        document.getElementById('expenseDescription').value = '';
        document.getElementById('expenseCategoryId').value = '';
        document.getElementById('expenseAssetId').value = '';
        document.getElementById('selectedExpenseAssetDisplay').textContent = 'Выбрать';
        document.getElementById('expensePlatformId').value = '';
        document.getElementById('selectedExpensePlatformDisplay').textContent = 'Выбрать площадку';
        
        // Скрываем блок баланса
        hideExpensePlatformBalance();
        
        // Загружаем категории
        loadExpenseCategories();
        
        // Сбрасываем выбранные данные
        selectedExpensePlatform = { id: null, name: '' };
        selectedExpenseAsset = { id: null, symbol: '' };
        expensePlatformBalanceData = null;
    }
}

function selectExpensePlatform(id, name) {
    selectedExpensePlatform = { id, name };
    
    const display = document.getElementById('selectedExpensePlatformDisplay');
    if (display) display.textContent = name;
    
    const hiddenInput = document.getElementById('expensePlatformId');
    if (hiddenInput) hiddenInput.value = id;
    
    // Загружаем баланс выбранной площадки
    loadExpensePlatformBalance(id, name);
}

function selectExpenseAssetFromBalance(assetId, symbol, assetType, quantityFormatted) {
    // Выбираем актив
    selectExpenseAsset(assetId, symbol);
    
    // Показываем уведомление с количеством
    //showNotification('info', 'Актив выбран', `${symbol}: доступно ${quantityFormatted}`);
}

// Функция для загрузки баланса площадки (расходы) - идентичная transfer
async function loadExpensePlatformBalance(platformId, platformName) {
    if (!platformId) return;
    
    const balanceBlock = document.getElementById('expensePlatformBalance');
    const assetsList = document.getElementById('expensePlatformAssetsList');
    const totalValueSpan = document.getElementById('expensePlatformTotalValue');
    const totalDiv = document.getElementById('expensePlatformTotal');
    const totalUsdSpan = document.getElementById('expensePlatformTotalUsd');
    const balanceTitle = document.getElementById('expenseBalanceTitle');
    
    // Проверяем существование всех элементов
    if (!balanceBlock) {
        console.error('expensePlatformBalance not found');
        return;
    }
    if (!assetsList) {
        console.error('expensePlatformAssetsList not found');
        return;
    }
    
    // Обновляем заголовок с названием площадки
    if (balanceTitle) {
        balanceTitle.innerHTML = `<i class="fas fa-wallet"></i> Баланс: ${platformName}`;
    }
    
    // Показываем блок с загрузкой
    balanceBlock.style.display = 'block';
    assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #6b7a8f;"><i class="fas fa-spinner fa-spin"></i> Загрузка баланса...</div>';
    if (totalDiv) totalDiv.style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'get_platform_balance');
    formData.append('platform_id', platformId);
    
    try {
        const response = await fetch(API_URL_PHP, {  // было window.location.href
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && result.assets) {
            const assets = result.assets;
            const totalUsd = result.total_value_usd;
            const totalRub = result.total_value_rub;
            
            // Форматируем общую стоимость
            let totalUsdStr = totalUsd.toFixed(2);
            let totalUsdParts = totalUsdStr.split('.');
            totalUsdParts[0] = totalUsdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            const totalUsdFormatted = totalUsdParts[0] + (totalUsdParts[1] ? '.' + totalUsdParts[1] : '');
            
            let totalRubStr = Math.round(totalRub).toString();
            totalRubStr = totalRubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            
            if (totalValueSpan) {
                totalValueSpan.innerHTML = `<i class="fas fa-chart-line"></i> ${totalUsdFormatted} $ / ${totalRubStr} ₽`;
            }
            
            if (assets.length === 0) {
                assetsList.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-box-open"></i> Нет активов на площадке ${platformName}
                    </div>
                `;
                if (totalDiv) totalDiv.style.display = 'none';
            } else {
                // Формируем список активов
                let html = '';
                
                assets.forEach(asset => {
                    const quantity = parseFloat(asset.quantity);
                    const valueUsd = parseFloat(asset.value_usd);
                    // ИСПРАВЛЕНИЕ: используем глобальную переменную usdRubRate
                    const usdRubRateGlobal = typeof usdRubRate !== 'undefined' ? usdRubRate : 92.50;
                    const valueRub = valueUsd * usdRubRateGlobal;
                    
                    // Форматируем количество
                    let quantityFormatted = '';
                    if (asset.asset_type === 'crypto') {
                        if (Math.floor(quantity) === quantity) {
                            quantityFormatted = quantity.toLocaleString('ru-RU').replace(/,/g, ' ');
                        } else {
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
                    
                    // Форматируем стоимость
                    let usdStr = valueUsd.toFixed(2);
                    let usdParts = usdStr.split('.');
                    usdParts[0] = usdParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    const usdFormatted = usdParts[0] + (usdParts[1] ? '.' + usdParts[1] : '');
                    
                    let rubStr = Math.round(valueRub).toString();
                    rubStr = rubStr.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    
                    // Определяем иконку для актива
                    let assetIcon = getAssetIcon(asset.symbol);
                    
                    html += `
                        <div class="platform-asset-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; margin-right:20px; border-bottom: 1px solid var(--border-color, #edf2f7); cursor: pointer;" 
                             onclick="selectExpenseAssetFromBalance('${asset.asset_id}', '${asset.symbol}', '${asset.asset_type}', '${quantityFormatted}')"
                             onmouseover="this.style.background='var(--bg-tertiary)'; this.style.borderRadius='8px'; this.style.paddingLeft='8px'; this.style.paddingRight='8px';" 
                             onmouseout="this.style.background='transparent'; this.style.paddingLeft='0'; this.style.paddingRight='0';">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 28px; height: 28px; background: ${assetIcon.color}20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="${assetIcon.icon}" style="color: ${assetIcon.color}; font-size: 14px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 500; font-size: 13px;">${asset.symbol}</div>
                                    <div style="font-size: 10px; color: #6b7a8f;">${quantityFormatted}</div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 12px; font-weight: 500;">$${usdFormatted}</div>
                                <div style="font-size: 10px; color: #6b7a8f;">${rubStr} ₽</div>
                            </div>
                        </div>
                    `;
                });
                
                assetsList.innerHTML = html;
                if (totalDiv) totalDiv.style.display = 'block';
                
                // Форматируем общую стоимость для отображения в нижней части
                if (totalUsdSpan) {
                    totalUsdSpan.innerHTML = `$${totalUsdFormatted} (${totalRubStr} ₽)`;
                }
            }
            
            // Сохраняем данные для быстрого доступа
            window.expensePlatformBalanceData = {
                platformId: platformId,
                platformName: platformName,
                assets: assets,
                totalUsd: totalUsd,
                totalRub: totalRub
            };
            
        } else {
            assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #e53e3e;">Ошибка загрузки баланса</div>';
            if (totalDiv) totalDiv.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading expense platform balance:', error);
        assetsList.innerHTML = '<div style="text-align: center; padding: 15px; color: #e53e3e;">Ошибка загрузки</div>';
        if (totalDiv) totalDiv.style.display = 'none';
    }
}

// Функция для настройки закрытия модального окна по клику на overlay
function setupModalCloseOnOverlay(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                // Закрываем соответствующее модальное окно
                if (modalId === 'expenseModal') closeExpenseModal();
                else if (modalId === 'addExpenseCategoryModal') closeAddExpenseCategoryModal();
            }
        });
    }
}

function selectExpenseAsset(id, symbol) {
    selectedExpenseAsset = { id, symbol };
    
    const display = document.getElementById('selectedExpenseAssetDisplay');
    if (display) display.textContent = symbol;
    
    const hiddenInput = document.getElementById('expenseAssetId');
    if (hiddenInput) hiddenInput.value = id;
}

function hideExpensePlatformBalance() {
    const balanceBlock = document.getElementById('expensePlatformBalance');
    if (balanceBlock) {
        balanceBlock.style.display = 'none';
    }
}

// Сохранение расхода
async function saveExpense() {
    const platformId = document.getElementById('expensePlatformId').value;
    const assetId = document.getElementById('expenseAssetId').value;
    const amount = parseFloat(document.getElementById('expenseAmount').value.replace(/\s/g, '').replace(',', '.')) || 0;
    const description = document.getElementById('expenseDescription').value;
    const categoryId = document.getElementById('expenseCategoryId').value;
    const expenseDate = document.getElementById('expenseDate').value;
    
    if (!platformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку списания');
        return;
    }
    
    if (!assetId) {
        showNotification('error', 'Ошибка', 'Выберите актив');
        return;
    }
    
    if (amount <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректную сумму расхода');
        return;
    }
    
    if (!categoryId) {
        showNotification('error', 'Ошибка', 'Выберите категорию расхода');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_expense_with_deduction');
    formData.append('platform_id', platformId);
    formData.append('asset_id', assetId);
    formData.append('amount', amount);
    formData.append('description', description);
    formData.append('category_id', categoryId);
    formData.append('expense_date', expenseDate);
    
    try {
        const response = await fetch(API_URL_PHP, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeExpenseModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        showNotification('error', 'Ошибка сети', 'Не удалось сохранить расход');
    }
}

// ============================================================================
// ФУНКЦИИ ДЛЯ ПРОДАЖИ (НЕОБХОДИМЫЕ)
// ============================================================================

// Глобальные переменные для продажи
let sellPurchaseHistory = [];
let sellPlatformBalances = [];
let selectedSellPlatformId = null;
let selectedSellPlatformName = ''; // Для хранения названия выбранной площадки
let selectedSellPlatformAvgPrice = 0; // Средняя цена на выбранной площадке

// Загрузка данных для продажи
async function loadSellData() {
    if (!selectedSellAsset.id || !selectedSellPriceCurrency.code) return;
    
    const price = parseFloat(document.getElementById('sellPrice').value.replace(/\s/g, '')) || 0;
    if (price <= 0) {
        return;
    }
    
    const lotsContainer = document.getElementById('sellLotsContainer');
    const lotsList = document.getElementById('sellLotsList');
    
    lotsContainer.style.display = 'block';
    lotsList.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка данных...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_sell_data');
    formData.append('asset_id', selectedSellAsset.id);
    formData.append('price_currency', selectedSellPriceCurrency.code);
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            sellPurchaseHistory = result.purchase_history;
            sellPlatformBalances = result.platform_balances;
            
            const totalQuantity = result.total_quantity;
            const avgPrice = result.avg_price;
            
            let html = `
                <!-- БЛОК 1: ИСТОРИЯ ПОКУПОК с ползунками процентов -->
                <div style="margin-bottom: 20px;">
                    <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 12px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Всего доступно:</span>
                            <span style="font-weight: 600;">${formatAmount(totalQuantity, selectedSellAsset.symbol)} ${selectedSellAsset.symbol}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Средняя цена покупки:</span>
                            <span>${formatAmount(avgPrice, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}</span>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="margin: 0; font-size: 14px;"><i class="fas fa-history"></i> История покупок</h4>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" id="sellSelectAllBtn" class="quick-platform-btn" style="font-size: 11px; padding: 4px 10px;">
                                <i class="fas fa-check-double"></i> Выбрать всё
                            </button>
                            <button type="button" id="sellClearSelectionBtn" class="quick-platform-btn" style="font-size: 11px; padding: 4px 10px;">
                                <i class="fas fa-times"></i> Сбросить все
                            </button>
                        </div>
                    </div>
                    
                    <div style="max-height: 400px; overflow-y: auto;" id="purchasesListContainer">
            `;
            
            if (sellPurchaseHistory.length === 0) {
    html += '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет истории покупок</div>';
} else {
    // Сохраняем все покупки в глобальную переменную для доступа из других функций
    window.sellPurchaseItems = [];
    
    // Сортируем покупки по дате (сначала старые - для FIFO)
    const sortedPurchases = [...sellPurchaseHistory].sort((a, b) => {
        return new Date(a.operation_date) - new Date(b.operation_date);
    });
    
    for (const purchase of sortedPurchases) {
        const purchaseId = `purchase_${purchase.trade_id}`;
        const quantity = parseFloat(purchase.quantity); // Это уже остаток!
        const originalQuantity = parseFloat(purchase.original_quantity || purchase.quantity);
        const soldQuantity = parseFloat(purchase.sold_quantity || 0);
        const hasPartialSale = purchase.has_partial_sale || false;
        const actualSales = purchase.actual_sales || [];
        
        const quantityFormatted = formatAmount(quantity, selectedSellAsset.symbol);
        const originalQuantityFormatted = formatAmount(originalQuantity, selectedSellAsset.symbol);
        
        const purchasePrice = parseFloat(purchase.price);
        const purchasePriceFormatted = formatAmount(purchasePrice, purchase.price_currency);
        const totalCost = originalQuantity * purchasePrice;
        const totalCostFormatted = formatAmount(totalCost, purchase.price_currency);
        const remainingCost = quantity * purchasePrice;
        const remainingCostFormatted = formatAmount(remainingCost, purchase.price_currency);
        
        const expectedRevenue = quantity * price;
        const expectedRevenueFormatted = formatAmount(expectedRevenue, selectedSellPriceCurrency.code);
        const profitPurchase = expectedRevenue - remainingCost;
        const profitPercentPurchase = remainingCost > 0 ? (profitPurchase / remainingCost) * 100 : 0;
        const profitColor = profitPurchase >= 0 ? '#00a86b' : '#e53e3e';
        const profitSign = profitPurchase >= 0 ? '+' : '';
        
        // Расчет данных по проданной части (ФИКСИРОВАННЫЕ данные из БД)
        let soldPartHtml = '';
        if (hasPartialSale && actualSales.length > 0) {
            // Суммируем все продажи по этому лоту
            let totalSoldQuantity = 0;
            let totalSellTotal = 0;
            let totalBuyTotal = 0;
            let totalProfit = 0;
            
            actualSales.forEach(sale => {
                totalSoldQuantity += parseFloat(sale.sold_quantity);
                totalSellTotal += parseFloat(sale.sell_total);
                totalBuyTotal += parseFloat(sale.buy_total);
                totalProfit += parseFloat(sale.profit);
            });
            
            const totalSoldQuantityFormatted = formatAmount(totalSoldQuantity, selectedSellAsset.symbol);
            const totalSellTotalFormatted = formatAmount(totalSellTotal, selectedSellPriceCurrency.code);
            const totalProfitFormatted = formatAmount(Math.abs(totalProfit), selectedSellPriceCurrency.code);
            const totalProfitSign = totalProfit >= 0 ? '+' : '';
            const totalProfitColor = totalProfit >= 0 ? '#00a86b' : '#e53e3e';
            const totalProfitPercent = totalBuyTotal > 0 ? (totalProfit / totalBuyTotal) * 100 : 0;
            
            // Формируем детали по каждой продаже
            let salesDetails = '';
            if (actualSales.length === 1) {
                const sale = actualSales[0];
                const sellPriceFormatted = formatAmount(parseFloat(sale.sell_price), sale.sell_currency);
                const sellDate = new Date(sale.sell_date).toLocaleDateString('ru-RU');
                const saleProfitFormatted = formatAmount(Math.abs(parseFloat(sale.profit)), selectedSellPriceCurrency.code);
                const saleProfitSign = parseFloat(sale.profit) >= 0 ? '+' : '';
                const saleProfitColor = parseFloat(sale.profit) >= 0 ? '#00a86b' : '#e53e3e';
                
                salesDetails = `
                    <div style="font-size: 10px; color: var(--text-tertiary); margin-top: 4px;">
                        🏷️ Продано ${formatAmount(parseFloat(sale.sold_quantity), selectedSellAsset.symbol)} ${selectedSellAsset.symbol} 
                        ${sellDate} по ${sellPriceFormatted} ${sale.sell_currency}
                    </div>
                `;
            } else {
                salesDetails = `
                    <div style="font-size: 10px; color: var(--text-tertiary); margin-top: 4px;">
                        📊 Всего продаж: ${actualSales.length}
                    </div>
                `;
            }
            
            soldPartHtml = `
                <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed var(--border-color); font-size: 11px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-tertiary);">📊 Продано ранее:</span>
                        <span style="font-weight: 500;">${totalSoldQuantityFormatted} ${selectedSellAsset.symbol}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-tertiary);">💰 Выручено:</span>
                        <span>${totalSellTotalFormatted} ${selectedSellPriceCurrency.code}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-tertiary);">📈 Прибыль:</span>
                        <span style="color: ${totalProfitColor}; font-weight: 500;">
                            ${totalProfitSign}${totalProfitFormatted} ${selectedSellPriceCurrency.code} (${totalProfitSign}${totalProfitPercent.toFixed(1)}%)
                        </span>
                    </div>
                    ${salesDetails}
                </div>
            `;
        }
        
        // Сохраняем покупку для доступа
        window.sellPurchaseItems.push({
            id: purchaseId,
            trade_id: purchase.trade_id,
            platform_id: purchase.platform_id,
            platform_name: purchase.platform_name,
            quantity: quantity,
            original_quantity: originalQuantity,
            sold_quantity: soldQuantity,
            price: purchasePrice,
            price_currency: purchase.price_currency,
            totalCost: remainingCost,
            has_partial_sale: hasPartialSale,
            actual_sales: actualSales
        });
        
        html += `
            <div class="sell-purchase-item" data-purchase-id="${purchaseId}" data-trade-id="${purchase.trade_id}" data-platform-id="${purchase.platform_id}" data-platform-name="${purchase.platform_name}" data-quantity="${quantity}" data-price="${purchasePrice}" data-currency="${purchase.price_currency}" data-total-cost="${remainingCost}"
                style="background: var(--bg-secondary); border-radius: 12px; padding: 12px; margin-bottom: 12px; border: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <div>
                        <div style="font-weight: 500;">${formatDate(purchase.operation_date)} 
                            <span style="font-size: 12px; color: var(--text-tertiary);">
                                <i class="fas fa-building"></i> ${purchase.platform_name}
                            </span>
                        </div>
                        <div style="font-size: 13px; margin-top: 6px;">
                            ${quantityFormatted} ${selectedSellAsset.symbol} · по ${purchasePriceFormatted} ${purchase.price_currency}
                        </div>
                        <div style="font-size: 11px; color: var(--text-tertiary);">
                            ${remainingCostFormatted} ${purchase.price_currency} ${hasPartialSale ? `<span>(было куплено: ${originalQuantityFormatted} ${selectedSellAsset.symbol} за ${totalCostFormatted} ${purchase.price_currency})</span>` : ''}
                        </div>
                        ${soldPartHtml}
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 500;">${expectedRevenueFormatted} ${selectedSellPriceCurrency.code}</div>
                        <div style="color: ${profitColor}; font-weight: 500;">
                            ${profitSign}${formatAmount(profitPurchase, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}
                            (${profitSign}${profitPercentPurchase.toFixed(1)}%)
                        </div>
                    </div>
                </div>
                
                <!-- Ползунок процента продажи (только от остатка!) -->
                <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid var(--border-color);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 12px; color: var(--text-secondary);">
                            <i class="fas fa-percent"></i> Продать от остатка:
                        </span>
                        <span style="font-size: 13px; font-weight: 600;" id="percent-value-${purchaseId}">0%</span>
                    </div>
                    <input type="range" 
                        id="slider-${purchaseId}"
                        class="sell-percent-slider"
                        data-purchase-id="${purchaseId}"
                        data-max-quantity="${quantity}"
                        data-price="${purchasePrice}"
                        data-currency="${purchase.price_currency}"
                        data-symbol="${selectedSellAsset.symbol}"
                        data-total-cost="${remainingCost}"
                        data-expected-revenue="${expectedRevenue}"
                        min="0" 
                        max="100" 
                        value="0" 
                        step="1"
                        style="width: 100%; height: 6px; -webkit-appearance: none; background: linear-gradient(to right, #ff9f4a 0%, #ff9f4a 0%, #e0e6ed 0%, #e0e6ed 100%); border-radius: 3px; outline: none; cursor: pointer;">
                    <div style="display: flex; justify-content: space-between; margin-top: 6px;">
                        <span style="font-size: 10px; color: var(--text-tertiary);">0%</span>
                        <span style="font-size: 10px; color: var(--text-tertiary);" id="quantity-display-${purchaseId}">0 / ${quantityFormatted} ${selectedSellAsset.symbol}</span>
                        <span style="font-size: 10px; color: var(--text-tertiary);">100%</span>
                    </div>
                </div>
            </div>
        `;
    }
}
            
            html += `
                    </div>
                </div>
                
                <!-- БЛОК 2: ТЕКУЩИЕ ОСТАТКИ ПО ПЛОЩАДКАМ (кликабельные - выбор площадки списания) -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 12px; font-size: 14px;"><i class="fas fa-building"></i> С какой площадки продать:</h4>
                    <div style="max-height: 250px; overflow-y: auto;">
            `;
            
            if (sellPlatformBalances.length === 0) {
                html += '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет доступных остатков</div>';
            } else {
                sellPlatformBalances.forEach(balance => {
                    const quantityFormatted = formatAmount(balance.quantity, selectedSellAsset.symbol);
                    const platformName = balance.platform_name;
                    const platformId = balance.platform_id;
                    const isSelected = selectedSellPlatformId == platformId;
                    
                    html += `
                        <div class="sell-platform-item" data-platform-id="${platformId}" data-platform-name="${platformName}" data-quantity="${balance.quantity}" data-avg-price="${balance.average_buy_price}"
                             style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 2px solid ${isSelected ? '#ff9f4a' : 'var(--border-color)'}; border-radius: 12px; margin-bottom: 8px; background: ${isSelected ? 'rgba(255, 159, 74, 0.1)' : 'var(--bg-secondary)'}; cursor: pointer; transition: all 0.2s;"
                             onclick="selectSellPlatformQuick('${platformId}', '${platformName}', ${balance.quantity}, ${balance.average_buy_price})"
                             onmouseover="this.style.borderColor='#ff9f4a';" 
                             onmouseout="this.style.borderColor='${isSelected ? '#ff9f4a' : 'var(--border-color)'}';">
                            <div>
                                <div style="font-weight: 600;">${balance.platform_name}</div>
                                <div style="font-size: 13px; margin-top: 4px;">
                                    ${quantityFormatted} ${selectedSellAsset.symbol}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 13px; font-weight: 500;">
                                    ${formatAmount(balance.quantity * price, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            html += `
                    </div>
                </div>
            `;
            
            lotsList.innerHTML = html;
            
            // Добавляем обработчики для ползунков
            if (window.sellPurchaseItems) {
                window.sellPurchaseItems.forEach(item => {
                    const slider = document.getElementById(`slider-${item.id}`);
                    if (slider) {
                        slider.addEventListener('input', function(e) {
                            updateSliderStyle(this);
                            updateSellSummaryFromSliders(price);
                        });
                    }
                });
            }
            
            // Добавляем обработчики для кнопок "Выбрать всё" и "Сбросить все"
            const selectAllBtn = document.getElementById('sellSelectAllBtn');
            const clearSelectionBtn = document.getElementById('sellClearSelectionBtn');
            
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    document.querySelectorAll('.sell-percent-slider').forEach(slider => {
                        slider.value = 100;
                        updateSliderStyle(slider);
                    });
                    updateSellSummaryFromSliders(price);
                });
            }
            
            if (clearSelectionBtn) {
                clearSelectionBtn.addEventListener('click', function() {
                    document.querySelectorAll('.sell-percent-slider').forEach(slider => {
                        slider.value = 0;
                        updateSliderStyle(slider);
                    });
                    updateSellSummaryFromSliders(price);
                });
            }
            
            // Инициализируем сводку
            updateSellSummaryFromSliders(price);
            
        } else {
            lotsList.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">' + (result.message || 'Ошибка загрузки данных') + '</div>';
        }
    } catch (error) {
        console.error('Error loading sell data:', error);
        lotsList.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки: ' + error.message + '</div>';
    }
}

// Функция обновления стиля ползунка (градиент) и отображения количества/дохода
function updateSliderStyle(slider) {
    const val = (slider.value - slider.min) / (slider.max - slider.min);
    const percent = val * 100;
    slider.style.background = `linear-gradient(to right, #ff9f4a 0%, #ff9f4a ${percent}%, #e0e6ed ${percent}%, #e0e6ed 100%)`;
    
    const purchaseId = slider.dataset.purchaseId;
    const percentValue = document.getElementById(`percent-value-${purchaseId}`);
    if (percentValue) {
        percentValue.textContent = `${slider.value}%`;
    }
    
    // Получаем данные
    const maxQuantity = parseFloat(slider.dataset.maxQuantity);
    const selectedQuantity = maxQuantity * (slider.value / 100);
    const symbol = slider.dataset.symbol || '';
    const totalCost = parseFloat(slider.dataset.totalCost);
    const expectedRevenueFull = parseFloat(slider.dataset.expectedRevenue);
    const priceCurrency = slider.dataset.currency || selectedSellPriceCurrency?.code || 'USD';
    
    const quantityDisplay = document.getElementById(`quantity-display-${purchaseId}`);
    if (quantityDisplay) {
        // Форматируем количество с правильным числом знаков
        const formattedSelected = formatAmount(selectedQuantity, symbol);
        const formattedMax = formatAmount(maxQuantity, symbol);
        
        let displayText = `${formattedSelected} / ${formattedMax} ${symbol}`;
        
        // Добавляем информацию о доходе только если выбран не 0%
        if (slider.value > 0 && totalCost && expectedRevenueFull && !isNaN(totalCost) && !isNaN(expectedRevenueFull)) {
            const currentPercent = parseFloat(slider.value) / 100;
            const currentRevenue = expectedRevenueFull * currentPercent;
            const currentCost = totalCost * currentPercent;
            const currentProfit = currentRevenue - currentCost;
            const currentProfitPercent = currentCost > 0 ? (currentProfit / currentCost) * 100 : 0;
            const profitSign = currentProfit >= 0 ? '+' : '';
            const profitColor = currentProfit >= 0 ? '#00a86b' : '#e53e3e';
            
            // Форматируем сумму прибыли
            let formattedProfit = formatAmount(Math.abs(currentProfit), priceCurrency);
            if (priceCurrency === 'BTC' || priceCurrency === 'ETH') {
                formattedProfit = formatAmount(Math.abs(currentProfit), priceCurrency);
            } else {
                formattedProfit = formatAmount(Math.abs(currentProfit), priceCurrency);
            }
            
            displayText += ` <span style="color: ${profitColor};">· ${profitSign}${formattedProfit} ${priceCurrency} (${profitSign}${currentProfitPercent.toFixed(1)}%)</span>`;
        }
        
        quantityDisplay.innerHTML = displayText;
    }
}

// Функция обновления сводки на основе ползунков
function updateSellSummaryFromSliders(price) {
    let totalQuantity = 0;
    let totalCost = 0;
    let selectedPlatformsMap = new Map(); // platform_id -> { quantity, avg_price }
    
    // Собираем данные со всех ползунков
    document.querySelectorAll('.sell-percent-slider').forEach(slider => {
        const percent = parseFloat(slider.value) || 0;
        if (percent > 0) {
            const maxQuantity = parseFloat(slider.dataset.maxQuantity);
            const quantity = maxQuantity * (percent / 100);
            const purchasePrice = parseFloat(slider.dataset.price);
            const platformId = parseInt(slider.closest('.sell-purchase-item').dataset.platformId);
            
            totalQuantity += quantity;
            totalCost += quantity * purchasePrice;
            
            // Группируем по площадкам для последующей продажи
            if (selectedPlatformsMap.has(platformId)) {
                const existing = selectedPlatformsMap.get(platformId);
                existing.quantity += quantity;
                // Средневзвешенная цена
                existing.totalCost += quantity * purchasePrice;
            } else {
                selectedPlatformsMap.set(platformId, {
                    platform_id: platformId,
                    quantity: quantity,
                    totalCost: quantity * purchasePrice,
                    avg_price: purchasePrice
                });
            }
        }
    });
    
    // Пересчитываем среднюю цену для каждой площадки
    const selectedPlatforms = [];
    for (let [platformId, data] of selectedPlatformsMap.entries()) {
        selectedPlatforms.push({
            platform_id: platformId,
            quantity: data.quantity,
            avg_price: data.totalCost / data.quantity
        });
    }
    
    // Сохраняем выбранные площадки для отправки
    window.selectedSellPlatforms = selectedPlatforms;
    
    const detailsDiv = document.getElementById('sellTransactionDetails');
    
    if (totalQuantity === 0) {
        if (detailsDiv) detailsDiv.style.display = 'none';
        return;
    }
    
    if (detailsDiv) detailsDiv.style.display = 'block';
    
    const avgPrice = totalQuantity > 0 ? totalCost / totalQuantity : 0;
    const totalValue = totalQuantity * price;
    const profit = totalValue - totalCost;
    const profitPercent = totalCost > 0 ? (profit / totalCost) * 100 : 0;
    
    const commission = parseFloat(document.getElementById('sellCommission').value.replace(/\s/g, '')) || 0;
    const netValue = totalValue - commission;
    const netProfit = netValue - totalCost;
    const netProfitPercent = totalCost > 0 ? (netProfit / totalCost) * 100 : 0;
    
    const finalQuantity = document.getElementById('sellFinalQuantity');
    const finalPrice = document.getElementById('sellFinalPrice');
    const finalTotal = document.getElementById('sellFinalTotal');
    const finalProfit = document.getElementById('sellFinalProfit');
    const finalPlatform = document.getElementById('sellFinalPlatform');
    const finalAvgPrice = document.getElementById('sellFinalAvgPrice');
    
    // Определяем основную площадку (с максимальным количеством)
    let mainPlatformName = '';
    let maxQty = 0;
    selectedPlatforms.forEach(p => {
        if (p.quantity > maxQty) {
            maxQty = p.quantity;
            const purchaseItem = document.querySelector(`.sell-purchase-item[data-platform-id="${p.platform_id}"]`);
            if (purchaseItem) {
                mainPlatformName = purchaseItem.dataset.platformName;
            }
        }
    });
    
    if (finalAvgPrice) finalAvgPrice.textContent = `${formatAmount(avgPrice, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalQuantity) finalQuantity.textContent = `${formatAmount(totalQuantity, selectedSellAsset.symbol)} ${selectedSellAsset.symbol}`;
    if (finalPrice) finalPrice.textContent = `${formatAmount(price, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalTotal) finalTotal.textContent = `${formatAmount(totalValue, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalProfit) {
        finalProfit.innerHTML = `${netProfit >= 0 ? '+' : ''}${formatAmount(netProfit, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code} (${netProfitPercent.toFixed(1)}%)`;
        finalProfit.style.color = netProfit >= 0 ? '#00a86b' : '#e53e3e';
    }
    if (finalPlatform) {
        // Если выбрана площадка в блоке "С какой площадки продать", используем её
        if (selectedSellPlatformId && selectedSellPlatformName) {
            finalPlatform.textContent = selectedSellPlatformName;
        } else {
            finalPlatform.textContent = mainPlatformName || 'Не выбрана';
        }
    }
    
    // Обновляем скрытое поле площадки (если нужно)
    if (selectedPlatforms.length > 0) {
        const platformInput = document.getElementById('sellPlatformId');
        if (platformInput) platformInput.value = selectedPlatforms[0].platform_id;
    }

    // Проверяем, достаточно ли актива на выбранной площадке
    if (selectedSellPlatformId) {
        const selectedBalance = sellPlatformBalances.find(b => b.platform_id == selectedSellPlatformId);
        if (selectedBalance) {
            const isInsufficient = totalQuantity > selectedBalance.quantity;
            window.selectedPlatformSufficient = !isInsufficient;
            
            // Обновляем визуальное состояние выбранной площадки
            const selectedItem = document.querySelector(`.sell-platform-item[data-platform-id="${selectedSellPlatformId}"]`);
            if (selectedItem) {
                if (isInsufficient) {
                    selectedItem.style.borderColor = '#e53e3e';
                    selectedItem.style.background = 'rgba(229, 62, 62, 0.1)';
                } else {
                    selectedItem.style.borderColor = '#ff9f4a';
                    selectedItem.style.background = 'rgba(255, 159, 74, 0.1)';
                }
            }
            
            // Обновляем текст в поле "Площадка списания"
            const finalPlatform = document.getElementById('sellFinalPlatform');
            if (finalPlatform) {
                if (isInsufficient) {
                    finalPlatform.innerHTML = `${selectedSellPlatformName} <span style="color: #e53e3e;">(недостаточно средств!)</span>`;
                } else {
                    finalPlatform.textContent = selectedSellPlatformName;
                }
            }
        }
    }
}

// Функция для расчета выбранного количества (для confirmSell)
function getSelectedLotsFromSliders() {
    const selectedPlatforms = [];
    
    document.querySelectorAll('.sell-percent-slider').forEach(slider => {
        const percent = parseFloat(slider.value) || 0;
        if (percent > 0) {
            const maxQuantity = parseFloat(slider.dataset.maxQuantity);
            const quantity = maxQuantity * (percent / 100);
            const purchasePrice = parseFloat(slider.dataset.price);
            const platformId = parseInt(slider.closest('.sell-purchase-item').dataset.platformId);
            
            // Проверяем, не добавляли ли уже эту площадку
            const existing = selectedPlatforms.find(p => p.platform_id === platformId);
            if (existing) {
                existing.quantity += quantity;
                existing.totalCost += quantity * purchasePrice;
            } else {
                selectedPlatforms.push({
                    platform_id: platformId,
                    quantity: quantity,
                    totalCost: quantity * purchasePrice
                });
            }
        }
    });
    
    // Рассчитываем среднюю цену для каждой площадки
    return selectedPlatforms.map(p => ({
        platform_id: p.platform_id,
        quantity: p.quantity,
        avg_price: p.totalCost / p.quantity
    }));
}

// Хранилище выбранных покупок (Map: purchaseId -> { quantity, price, currency })
let selectedPurchases = new Map();

// Переключение выбора покупки (подставляет количество в manualSellQuantity)
function togglePurchaseSelection(purchaseId, quantity, price, currency, platformName) {
    if (selectedPurchases.has(purchaseId)) {
        selectedPurchases.delete(purchaseId);
    } else {
        selectedPurchases.set(purchaseId, { quantity, price, currency, platformName });
    }
    
    // Обновляем визуальное состояние
    const purchaseItem = document.querySelector(`.sell-purchase-item[data-purchase-id="${purchaseId}"]`);
    if (purchaseItem) {
        const isSelected = selectedPurchases.has(purchaseId);
        purchaseItem.style.borderColor = isSelected ? '#ff9f4a' : 'var(--border-color)';
        purchaseItem.style.background = isSelected ? 'rgba(255, 159, 74, 0.1)' : 'transparent';
        const checkbox = purchaseItem.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.checked = isSelected;
    }
    
    // Рассчитываем общее выбранное количество
    let totalSelectedQuantity = 0;
    for (let [id, purchase] of selectedPurchases.entries()) {
        totalSelectedQuantity += purchase.quantity;
    }
    
    // Подставляем количество в поле ручного ввода (без валидации)
    const manualInput = document.getElementById('manualSellQuantity');
    if (manualInput && totalSelectedQuantity > 0) {
        manualInput.value = formatAmount(totalSelectedQuantity, selectedSellAsset.symbol);
        formatInput(manualInput);
    }
    
    // Обновляем сводку
    const priceInput = parseFloat(document.getElementById('sellPrice').value.replace(/\s/g, '')) || 0;
    updateSellSummaryFromSelection(priceInput);
}

// Выбор площадки из списка остатков
function selectSellPlatformQuick(platformId, platformName, quantity, avgPrice) {
    selectedSellPlatformId = platformId;
    selectedSellPlatformName = platformName;
    selectedSellPlatformAvgPrice = avgPrice;
    
    // Получаем текущее выбранное количество из ползунков
    let totalSelectedQuantity = 0;
    document.querySelectorAll('.sell-percent-slider').forEach(slider => {
        const percent = parseFloat(slider.value) || 0;
        if (percent > 0) {
            const maxQuantity = parseFloat(slider.dataset.maxQuantity);
            totalSelectedQuantity += maxQuantity * (percent / 100);
        }
    });
    
    // Если количество не выбрано, берем 0
    if (totalSelectedQuantity === 0) {
        totalSelectedQuantity = window.manualSelectedQuantity || 0;
    }
    
    // Проверяем, достаточно ли актива на площадке
    const isInsufficient = totalSelectedQuantity > quantity;
    
    // Обновляем визуальное состояние
    document.querySelectorAll('.sell-platform-item').forEach(item => {
        const isSelected = item.dataset.platformId == platformId;
        item.style.borderColor = isSelected ? '#ff9f4a' : 'var(--border-color)';
        item.style.background = isSelected ? 'rgba(255, 159, 74, 0.1)' : 'var(--bg-secondary)';
    });
    
    // Подсвечиваем выбранную площадку красным, если недостаточно средств
    const selectedItem = document.querySelector(`.sell-platform-item[data-platform-id="${platformId}"]`);
    if (selectedItem) {
        if (isInsufficient) {
            selectedItem.style.borderColor = '#e53e3e';
            selectedItem.style.background = 'rgba(229, 62, 62, 0.1)';
        } else {
            selectedItem.style.borderColor = '#ff9f4a';
            selectedItem.style.background = 'rgba(255, 159, 74, 0.1)';
        }
    }
    
    // Обновляем скрытое поле площадки
    const platformInput = document.getElementById('sellPlatformId');
    if (platformInput) platformInput.value = platformId;
    
    // Обновляем поле "Площадка списания" в блоке деталей
    const finalPlatform = document.getElementById('sellFinalPlatform');
    if (finalPlatform) {
        if (isInsufficient) {
            finalPlatform.innerHTML = `${platformName} <span style="color: #e53e3e;">(недостаточно средств!)</span>`;
        } else {
            finalPlatform.textContent = platformName;
        }
    }
    
    // Показываем блок деталей, если он скрыт
    const detailsDiv = document.getElementById('sellTransactionDetails');
    if (detailsDiv && totalSelectedQuantity > 0) {
        detailsDiv.style.display = 'block';
    }
    
    // Сохраняем информацию о достаточности средств
    window.selectedPlatformSufficient = !isInsufficient;
}

// Новая функция для обновления сводки на основе выбранной площадки
function updateSellSummaryFromSelectedPlatform(platformId, platformName, quantity, avgPrice, price) {
    const detailsDiv = document.getElementById('sellTransactionDetails');
    
    if (!platformId || quantity === 0) {
        if (detailsDiv) detailsDiv.style.display = 'none';
        return;
    }
    
    if (detailsDiv) detailsDiv.style.display = 'block';
    
    const totalQuantity = quantity;
    const totalCost = totalQuantity * avgPrice;
    const totalValue = totalQuantity * price;
    const profit = totalValue - totalCost;
    const profitPercent = totalCost > 0 ? (profit / totalCost) * 100 : 0;
    
    const commission = parseFloat(document.getElementById('sellCommission').value.replace(/\s/g, '')) || 0;
    const netValue = totalValue - commission;
    const netProfit = netValue - totalCost;
    const netProfitPercent = totalCost > 0 ? (netProfit / totalCost) * 100 : 0;
    
    const finalQuantity = document.getElementById('sellFinalQuantity');
    const finalPrice = document.getElementById('sellFinalPrice');
    const finalTotal = document.getElementById('sellFinalTotal');
    const finalProfit = document.getElementById('sellFinalProfit');
    const finalPlatform = document.getElementById('sellFinalPlatform');
    const finalAvgPrice = document.getElementById('sellFinalAvgPrice');
    
    if (finalAvgPrice) finalAvgPrice.textContent = `${formatAmount(avgPrice, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalQuantity) finalQuantity.textContent = `${formatAmount(totalQuantity, selectedSellAsset.symbol)} ${selectedSellAsset.symbol}`;
    if (finalPrice) finalPrice.textContent = `${formatAmount(price, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalTotal) finalTotal.textContent = `${formatAmount(totalValue, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalProfit) {
        finalProfit.innerHTML = `${netProfit >= 0 ? '+' : ''}${formatAmount(netProfit, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code} (${netProfitPercent.toFixed(1)}%)`;
        finalProfit.style.color = netProfit >= 0 ? '#00a86b' : '#e53e3e';
    }
    if (finalPlatform) {
        finalPlatform.textContent = platformName;
    }
}

// Обновление ручного количества (автоматический пересчет)
function updateManualSellQuantity() {
    const manualInput = document.getElementById('manualSellQuantity');
    const manualQuantity = parseFloat(manualInput.value.replace(/\s/g, '')) || 0;
    
    // Если есть ручной ввод, очищаем выбранные покупки
    if (manualQuantity > 0 && selectedPurchases.size > 0) {
        selectedPurchases.clear();
        // Обновляем визуальное состояние всех покупок
        document.querySelectorAll('.sell-purchase-item').forEach(item => {
            item.style.borderColor = 'var(--border-color)';
            item.style.background = 'transparent';
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = false;
        });
    }
    
    const price = parseFloat(document.getElementById('sellPrice').value.replace(/\s/g, '')) || 0;
    
    if (manualQuantity > 0) {
        window.manualSelectedQuantity = manualQuantity;
        
        // Если выбрана площадка, используем её среднюю цену
        if (selectedSellPlatformId && selectedSellPlatformAvgPrice > 0) {
            window.manualSelectedAvgPrice = selectedSellPlatformAvgPrice;
        } else {
            // Иначе используем среднюю по всем
            const totalQuantityAll = sellPlatformBalances.reduce((sum, b) => sum + b.quantity, 0);
            const totalCostAll = sellPlatformBalances.reduce((sum, b) => sum + (b.quantity * b.average_buy_price), 0);
            const avgPriceAll = totalQuantityAll > 0 ? totalCostAll / totalQuantityAll : 0;
            window.manualSelectedAvgPrice = avgPriceAll;
        }
        
        updateSellSummaryFromSelection(price, manualQuantity, window.manualSelectedAvgPrice);
    } else {
        window.manualSelectedQuantity = 0;
        updateSellSummaryFromSelection(price);
    }
}

// Применение ручного ввода количества
function applyManualQuantitySelection(quantity, price) {
    if (!selectedSellAsset.id) return;
    
    // Очищаем предыдущий выбор
    selectedPurchases.clear();
    
    // Обновляем визуальное состояние всех покупок
    document.querySelectorAll('.sell-purchase-item').forEach(item => {
        item.style.borderColor = 'var(--border-color)';
        item.style.background = 'transparent';
        const checkbox = item.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.checked = false;
    });
    
    // Создаем виртуальный выбранный лот
    const avgPrice = sellPlatformBalances.length > 0 
        ? sellPlatformBalances.reduce((sum, b) => sum + (b.quantity * b.average_buy_price), 0) / sellPlatformBalances.reduce((sum, b) => sum + b.quantity, 0)
        : 0;
    
    // Сохраняем выбранное количество в глобальную переменную
    window.manualSelectedQuantity = quantity;
    window.manualSelectedAvgPrice = avgPrice;
    
    // Обновляем сводку
    updateSellSummaryFromSelection(price, quantity, avgPrice);
}

// Обновление сводки на основе выбранных покупок или ручного ввода
function updateSellSummaryFromSelection(price, manualQuantity = null, manualAvgPrice = null) {
    let totalQuantity = 0;
    let totalCost = 0;
    let platformNameForSell = selectedSellPlatformName;
    
    if (manualQuantity !== null && manualQuantity > 0) {
        // Режим ручного ввода
        totalQuantity = manualQuantity;
        totalCost = manualQuantity * (manualAvgPrice || 0);
        
        // Если площадка не выбрана, определяем по FIFO
        if (!selectedSellPlatformId) {
            let remainingToSell = manualQuantity;
            for (const balance of sellPlatformBalances) {
                if (remainingToSell <= 0) break;
                const sellFromThis = Math.min(balance.quantity, remainingToSell);
                if (sellFromThis > 0) {
                    platformNameForSell = balance.platform_name;
                    remainingToSell -= sellFromThis;
                }
            }
        }
    } else {
        // Режим выбора из истории покупок
        for (let [purchaseId, purchase] of selectedPurchases.entries()) {
            totalQuantity += purchase.quantity;
            totalCost += purchase.quantity * purchase.price;
            platformNameForSell = purchase.platformName;
        }
    }
    
    const detailsDiv = document.getElementById('sellTransactionDetails');
    
    if (totalQuantity === 0) {
        if (detailsDiv) detailsDiv.style.display = 'none';
        return;
    }
    
    if (detailsDiv) detailsDiv.style.display = 'block';
    
    const avgPrice = totalQuantity > 0 ? totalCost / totalQuantity : 0;
    const totalValue = totalQuantity * price;
    const profit = totalValue - totalCost;
    const profitPercent = totalCost > 0 ? (profit / totalCost) * 100 : 0;
    
    const commission = parseFloat(document.getElementById('sellCommission').value.replace(/\s/g, '')) || 0;
    const netValue = totalValue - commission;
    const netProfit = netValue - totalCost;
    const netProfitPercent = totalCost > 0 ? (netProfit / totalCost) * 100 : 0;
    
    const finalQuantity = document.getElementById('sellFinalQuantity');
    const finalPrice = document.getElementById('sellFinalPrice');
    const finalTotal = document.getElementById('sellFinalTotal');
    const finalProfit = document.getElementById('sellFinalProfit');
    const finalPlatform = document.getElementById('sellFinalPlatform');
    const finalAvgPrice = document.getElementById('sellFinalAvgPrice');
    
    if (finalAvgPrice) finalAvgPrice.textContent = `${formatAmount(avgPrice, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalQuantity) finalQuantity.textContent = `${formatAmount(totalQuantity, selectedSellAsset.symbol)} ${selectedSellAsset.symbol}`;
    if (finalPrice) finalPrice.textContent = `${formatAmount(price, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalTotal) finalTotal.textContent = `${formatAmount(totalValue, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalProfit) {
        finalProfit.innerHTML = `${netProfit >= 0 ? '+' : ''}${formatAmount(netProfit, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code} (${netProfitPercent.toFixed(1)}%)`;
        finalProfit.style.color = netProfit >= 0 ? '#00a86b' : '#e53e3e';
    }
    if (finalPlatform) {
        finalPlatform.textContent = platformNameForSell || (selectedSellPlatformName || 'Не выбрана');
    }
}

// Обновление деталей сделки на основе выбранного количества
function updateSellTransactionDetailsFromSelection(totalQuantity, totalCost, price) {
    const detailsDiv = document.getElementById('sellTransactionDetails');
    
    if (totalQuantity === 0) {
        if (detailsDiv) detailsDiv.style.display = 'none';
        return;
    }
    
    if (detailsDiv) detailsDiv.style.display = 'block';
    
    const totalValue = totalQuantity * price;
    const commission = parseFloat(document.getElementById('sellCommission').value.replace(/\s/g, '')) || 0;
    const netValue = totalValue - commission;
    const profit = netValue - totalCost;
    const profitPercent = totalCost > 0 ? (profit / totalCost) * 100 : 0;
    
    const finalQuantity = document.getElementById('sellFinalQuantity');
    const finalPrice = document.getElementById('sellFinalPrice');
    const finalTotal = document.getElementById('sellFinalTotal');
    const finalCost = document.getElementById('sellFinalCost');
    const finalProfit = document.getElementById('sellFinalProfit');
    
    if (finalQuantity) finalQuantity.textContent = `${formatAmount(totalQuantity, selectedSellAsset.symbol)} ${selectedSellAsset.symbol}`;
    if (finalPrice) finalPrice.textContent = `${formatAmount(price, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalTotal) finalTotal.textContent = `${formatAmount(totalValue, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalCost) finalCost.textContent = `${formatAmount(totalCost, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalProfit) {
        finalProfit.innerHTML = `${profit >= 0 ? '+' : ''}${formatAmount(profit, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code} (${profitPercent.toFixed(1)}%)`;
        finalProfit.style.color = profit >= 0 ? '#00a86b' : '#e53e3e';
    }
}

function selectSellPlatform(platformId, platformName, quantity, avgPrice) {
    selectedSellPlatformId = platformId;
    
    document.querySelectorAll('.sell-platform-item').forEach(item => {
        item.style.borderColor = 'var(--border-color)';
        item.style.background = 'transparent';
        if (item.dataset.platformId == platformId) {
            item.style.borderColor = '#ff9f4a';
            item.style.background = 'rgba(255, 159, 74, 0.1)';
        }
    });
    
    const price = parseFloat(document.getElementById('sellPrice').value.replace(/\s/g, '')) || 0;
    
    const hiddenInput = document.getElementById('sellPlatformId');
    if (hiddenInput) hiddenInput.value = platformId;
    
    updateSellSummaryFromSelected({ platform_id: platformId, platform_name: platformName, quantity: quantity, avg_price: avgPrice }, price);
}

function updateSellSummaryFromSelected(balance, price) {
    const summaryDiv = document.getElementById('sellSummary');
    const transactionDetails = document.getElementById('sellTransactionDetails');
    
    if (!balance) {
        if (summaryDiv) summaryDiv.style.display = 'none';
        if (transactionDetails) transactionDetails.style.display = 'none';
        return;
    }
    
    if (summaryDiv) summaryDiv.style.display = 'block';
    
    const totalQuantity = balance.quantity;
    const totalCost = totalQuantity * balance.avg_price;
    const totalValue = totalQuantity * price;
    const profit = totalValue - totalCost;
    
    const selectedCount = document.getElementById('sellSelectedCount');
    const selectedQuantity = document.getElementById('sellSelectedQuantity');
    const selectedAvgPrice = document.getElementById('sellSelectedAvgPrice');
    const selectedProfit = document.getElementById('sellSelectedProfit');
    const selectedTotalCost = document.getElementById('sellSelectedTotalCost');
    
    if (selectedCount) selectedCount.textContent = '1';
    if (selectedQuantity) selectedQuantity.textContent = `${formatAmount(totalQuantity, selectedSellAsset.symbol)} ${selectedSellAsset.symbol}`;
    if (selectedAvgPrice) selectedAvgPrice.textContent = `${formatAmount(balance.avg_price, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (selectedTotalCost) selectedTotalCost.textContent = `${formatAmount(totalCost, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (selectedProfit) {
        selectedProfit.innerHTML = `${profit >= 0 ? '+' : ''}${formatAmount(profit, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
        selectedProfit.style.color = profit >= 0 ? '#00a86b' : '#e53e3e';
    }
    
    updateSellTransactionDetailsFromSelected(balance, price);
}

function updateSellTransactionDetailsFromSelected(balance, price) {
    const detailsDiv = document.getElementById('sellTransactionDetails');
    
    if (!balance) {
        if (detailsDiv) detailsDiv.style.display = 'none';
        return;
    }
    
    if (detailsDiv) detailsDiv.style.display = 'block';
    
    const totalQuantity = balance.quantity;
    const totalCost = totalQuantity * balance.avg_price;
    const totalValue = totalQuantity * price;
    const commission = parseFloat(document.getElementById('sellCommission').value.replace(/\s/g, '')) || 0;
    const netValue = totalValue - commission;
    const profit = netValue - totalCost;
    
    const finalQuantity = document.getElementById('sellFinalQuantity');
    const finalPrice = document.getElementById('sellFinalPrice');
    const finalTotal = document.getElementById('sellFinalTotal');
    const finalCost = document.getElementById('sellFinalCost');
    const finalProfit = document.getElementById('sellFinalProfit');
    
    if (finalQuantity) finalQuantity.textContent = `${formatAmount(totalQuantity, selectedSellAsset.symbol)} ${selectedSellAsset.symbol}`;
    if (finalPrice) finalPrice.textContent = `${formatAmount(price, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalTotal) finalTotal.textContent = `${formatAmount(totalValue, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalCost) finalCost.textContent = `${formatAmount(totalCost, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
    if (finalProfit) {
        finalProfit.innerHTML = `${profit >= 0 ? '+' : ''}${formatAmount(profit, selectedSellPriceCurrency.code)} ${selectedSellPriceCurrency.code}`;
        finalProfit.style.color = profit >= 0 ? '#00a86b' : '#e53e3e';
    }
}

async function confirmSell() {
    console.log('confirmSell вызвана');
    
    const assetId = document.getElementById('sellAssetId').value;
    const price = parseFloat(document.getElementById('sellPrice').value.replace(/\s/g, '')) || 0;
    const priceCurrency = document.getElementById('sellPriceCurrency').value;
    const commission = parseFloat(document.getElementById('sellCommission').value.replace(/\s/g, '')) || 0;
    const commissionCurrency = document.getElementById('sellCommissionCurrency').value;
    const operationDate = document.getElementById('sellDate').value;
    const notes = document.getElementById('sellNotes').value;
    
    if (!assetId) {
        showNotification('error', 'Ошибка', 'Выберите актив');
        return;
    }
    
    if (price <= 0) {
        showNotification('error', 'Ошибка', 'Введите корректную цену');
        return;
    }
    
    if (!priceCurrency) {
        showNotification('error', 'Ошибка', 'Выберите валюту цены');
        return;
    }
    
    // Проверка: выбрана ли площадка
    if (!selectedSellPlatformId) {
        showNotification('error', 'Ошибка', 'Выберите площадку для продажи в разделе "С какой площадки продать"');
        return;
    }
    
    // Проверка: достаточно ли средств на площадке
    if (window.selectedPlatformSufficient === false) {
        showNotification('error', 'Ошибка', 'На выбранной площадке недостаточно актива для продажи');
        return;
    }
    
    // Получаем выбранные лоты из ползунков
    const selectedPlatforms = getSelectedLotsFromSliders();
    
    let totalQuantity = 0;
    selectedPlatforms.forEach(p => {
        totalQuantity += p.quantity;
    });
    
    if (totalQuantity <= 0) {
        showNotification('error', 'Ошибка', 'Выберите количество для продажи (передвиньте ползунки)');
        return;
    }
    
    console.log('Отправляем данные:', {
        asset_id: assetId,
        lots: selectedPlatforms,
        total_quantity: totalQuantity,
        price: price,
        price_currency: priceCurrency,
        commission: commission,
        commission_currency: commissionCurrency,
        operation_date: operationDate,
        notes: notes
    });
    
    const formData = new FormData();
    formData.append('action', 'sell_selected_lots');
    formData.append('asset_id', assetId);
    formData.append('lots', JSON.stringify(selectedPlatforms));
    formData.append('total_quantity', totalQuantity);
    formData.append('price', price);
    formData.append('price_currency', priceCurrency);
    formData.append('commission', commission);
    formData.append('commission_currency', commissionCurrency);
    formData.append('operation_date', operationDate);
    formData.append('notes', notes);
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        console.log('Ответ сервера:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Ошибка парсинга JSON:', e);
            showNotification('error', 'Ошибка сервера', 'Сервер вернул некорректный ответ: ' + responseText.substring(0, 200));
            return;
        }
        
        if (result.success) {
            showNotification('success', 'Успешно', result.message);
            closeSellModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            console.error('Ошибка от сервера:', result.message);
            showNotification('error', 'Ошибка', result.message);
        }
    } catch (error) {
        console.error('Ошибка сети:', error);
        showNotification('error', 'Ошибка сети', 'Не удалось отправить запрос: ' + error.message);
    }
}

// Быстрый выбор актива для продажи по символу
function selectSellAssetFromQuick(symbol) {
    const asset = assetsData.find(a => a.symbol === symbol);
    if (asset) {
        selectSellAsset(asset.id, asset.symbol, asset.type);
    } else {
        showNotification('warning', 'Внимание', `Актив ${symbol} не найден. Сначала добавьте его.`);
    }
}

// Добавьте эту функцию после функции openBuyModal или замените её

function openTradeModal(type) {
    // Устанавливаем тип операции
    tradeOperationType.value = type;
    
    // Меняем заголовок и текст кнопки в зависимости от типа
    const modalTitle = document.getElementById('tradeModalTitle');
    const titleSpan = document.getElementById('tradeModalTitleText');
    const confirmBtnText = document.getElementById('confirmTradeBtnText');
    
    if (type === 'buy') {
        modalTitle.innerHTML = '<i class="fas fa-arrow-down" style="color: #00a86b;"></i> <span id="tradeModalTitleText">Покупка</span>';
        titleSpan.textContent = 'Покупка';
        confirmBtnText.textContent = 'Купить';
        
        // Показываем блок выбора площадки списания
        const fromPlatformGroup = document.getElementById('tradeFromPlatformGroup');
        if (fromPlatformGroup) fromPlatformGroup.style.display = 'block';
        
        // Скрываем блок истории продажи
        const sellHistoryBlock = document.getElementById('sellPurchaseHistory');
        if (sellHistoryBlock) sellHistoryBlock.style.display = 'none';
        
    } else {
        modalTitle.innerHTML = '<i class="fas fa-arrow-up" style="color: #e53e3e;"></i> <span id="tradeModalTitleText">Продажа</span>';
        titleSpan.textContent = 'Продажа';
        confirmBtnText.textContent = 'Продать';
        
        // Скрываем блок выбора площадки списания (при продаже средства зачисляются на ту же площадку)
        const fromPlatformGroup = document.getElementById('tradeFromPlatformGroup');
        if (fromPlatformGroup) fromPlatformGroup.style.display = 'none';
    }
    
    // Устанавливаем дату
    const tradeDateInput = document.getElementById('tradeDate');
    if (tradeDateInput) {
        tradeDateInput.value = new Date().toISOString().split('T')[0];
    }
    
    // Сбрасываем поля
    document.getElementById('tradeQuantity').value = '';
    document.getElementById('tradePrice').value = '';
    document.getElementById('tradeCommission').value = '';
    document.getElementById('tradeNotes').value = '';
    document.getElementById('tradeNetwork').value = '';
    document.getElementById('selectedTradeNetworkDisplay').textContent = 'Выбрать сеть';
    
    // Сбрасываем выбранные значения
    selectedTradePlatform = { id: null, name: '' };
    selectedTradeFromPlatform = { id: null, name: '' };
    selectedTradeAsset = { id: null, symbol: '', type: '' };
    selectedTradePriceCurrency = { code: '' };
    selectedTradeCommissionCurrency = { code: '' };
    
    // Обновляем отображение
    const platformDisplay = document.getElementById('selectedTradePlatformDisplay');
    if (platformDisplay) platformDisplay.textContent = 'Выбрать площадку';
    
    const fromPlatformDisplay = document.getElementById('selectedTradeFromPlatformDisplay');
    if (fromPlatformDisplay) fromPlatformDisplay.textContent = 'Выбрать площадку';
    
    const assetDisplay = document.getElementById('selectedTradeAssetDisplay');
    if (assetDisplay) assetDisplay.textContent = 'Выбрать';
    
    const priceCurrencyDisplay = document.getElementById('selectedTradePriceCurrencyDisplay');
    if (priceCurrencyDisplay) priceCurrencyDisplay.textContent = 'Выбрать';
    
    const commissionCurrencyDisplay = document.getElementById('selectedTradeCommissionCurrencyDisplay');
    if (commissionCurrencyDisplay) commissionCurrencyDisplay.textContent = 'Выбрать';
    
    const totalField = document.getElementById('tradeTotal');
    if (totalField) totalField.value = '0';
    
    // Скрываем секцию выбора сети
    const cryptoSection = document.getElementById('tradeCryptoNetworkSection');
    if (cryptoSection) cryptoSection.style.display = 'none';
    
    // Скрываем историю продажи
    const sellHistoryBlock = document.getElementById('sellPurchaseHistory');
    if (sellHistoryBlock) sellHistoryBlock.style.display = 'none';
    
    // Показываем модальное окно
    tradeModal.classList.add('active');
}

// Добавьте эту функцию в ваш js.js файл (например, после функции openSellModalFromTrade)
function openTransferModal() {
    const modal = document.getElementById('transferModal');
    if (modal) {
        modal.classList.add('active');
        disableBodyScroll();
        
        // Устанавливаем дату
        document.getElementById('transferDate').value = new Date().toISOString().split('T')[0];
        
        // Сбрасываем поля
        document.getElementById('transferAmount').value = '';
        document.getElementById('transferCommission').value = '';
        document.getElementById('transferNotes').value = '';
        document.getElementById('transferNetworkFrom').value = '';
        document.getElementById('transferNetworkTo').value = '';
        document.getElementById('selectedFromNetworkDisplay').textContent = 'Выбрать сеть';
        document.getElementById('selectedToNetworkDisplay').textContent = 'Выбрать сеть';
        
        // Сбрасываем выбранные значения
        selectedFromPlatform = { id: null, name: '' };
        selectedToPlatform = { id: null, name: '' };
        selectedTransferAsset = { id: null, symbol: '' };
        selectedCommissionCurrency = { code: '' };
        selectedFromNetwork = { name: '' };
        selectedToNetwork = { name: '' };
        
        // Обновляем отображение
        const fromDisplay = document.getElementById('selectedFromPlatformDisplay');
        if (fromDisplay) fromDisplay.textContent = 'Выбрать площадку';
        
        const toDisplay = document.getElementById('selectedToPlatformDisplay');
        if (toDisplay) toDisplay.textContent = 'Выбрать площадку';
        
        const assetDisplay = document.getElementById('selectedAssetDisplay');
        if (assetDisplay) assetDisplay.textContent = 'Выбрать';
        
        const commissionDisplay = document.getElementById('selectedCommissionCurrencyDisplay');
        if (commissionDisplay) commissionDisplay.textContent = 'Выбрать';
        
        // Скрываем блок баланса
        hidePlatformBalance();
        
        // Скрываем секцию выбора сети (показываем только если выбран криптоактив)
        const cryptoSection = document.getElementById('transferCryptoNetworkSection');
        if (cryptoSection) cryptoSection.style.display = 'none';
        
        // Сбрасываем заголовок баланса
        resetPlatformBalanceTitle();
        
        // Очищаем данные баланса
        currentPlatformBalanceData = null;
    }
}

// Глобальные переменные для текущего актива
let currentAssetSymbol = '';
let currentAssetId = null;

// Функция показа деталей актива (вызывается при клике на строку таблицы)
async function showAssetDetails(symbol, assetId) {
    currentAssetSymbol = symbol;
    currentAssetId = assetId;
    
    const modal = document.getElementById('assetDetailsModal');
    const titleSpan = document.getElementById('assetDetailsSymbol');
    const body = document.getElementById('assetDetailsBody');
    
    titleSpan.textContent = symbol;
    modal.classList.add('active');
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка распределения...</div>';
    
    // Загружаем распределение по площадкам и сетям
    const formData = new FormData();
    formData.append('action', 'get_asset_distribution');
    formData.append('asset_id', assetId);
    formData.append('symbol', symbol);
    
    try {
        const response = await fetch(API_URL_PHP, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            displayAssetDistribution(result, symbol);
        } else {
            body.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки: ' + (result.message || 'Неизвестная ошибка') + '</div>';
        }
    } catch (error) {
        body.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки</div>';
    }
}

// Отображение распределения актива
function displayAssetDistribution(data, symbol) {
    const body = document.getElementById('assetDetailsBody');
    const total = data.total_quantity;
    const platforms = data.platforms;
    const networks = data.networks;
    
    let html = `
        <div style="margin-bottom: 20px; padding: 12px; background: var(--bg-tertiary); border-radius: 12px;">
            <div style="display: flex; justify-content: space-between;">
                <span>Всего ${symbol}:</span>
                <span style="font-weight: 700; font-size: 18px;">${formatAmount(total, symbol)} ${symbol}</span>
            </div>
        </div>
        
        <h4 style="margin-bottom: 12px;"><i class="fas fa-building"></i> Распределение по площадкам</h4>
        <div style="margin-bottom: 24px;">
    `;
    
    if (platforms.length === 0) {
        html += '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет данных по площадкам</div>';
    } else {
        platforms.forEach(platform => {
            const percent = (platform.quantity / total * 100).toFixed(1);
            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border-color);">
                    <div>
                        <span style="font-weight: 500;">${platform.platform_name}</span>
                        ${platform.network ? `<span style="font-size: 11px; color: #6b7a8f; margin-left: 8px;"><i class="fas fa-network-wired"></i> ${platform.network}</span>` : ''}
                    </div>
                    <div style="text-align: right;">
                        <div>${formatAmount(platform.quantity, symbol)} ${symbol}</div>
                        <div style="font-size: 12px; color: #6b7a8f;">${percent}%</div>
                    </div>
                </div>
            `;
        });
    }
    
    html += `
        </div>
        
        <h4 style="margin-bottom: 12px;"><i class="fas fa-network-wired"></i> Распределение по сетям (крипто)</h4>
        <div>
    `;
    
    if (networks.length === 0) {
        html += '<div style="text-align: center; padding: 20px; color: #6b7a8f;">Нет данных по сетям</div>';
    } else {
        networks.forEach(network => {
            const percent = (network.quantity / total * 100).toFixed(1);
            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border-color);">
                    <div>
                        <i class="fas fa-network-wired" style="color: #ff9f4a; width: 20px;"></i>
                        <span>${network.network}</span>
                    </div>
                    <div style="text-align: right;">
                        <div>${formatAmount(network.quantity, symbol)} ${symbol}</div>
                        <div style="font-size: 12px; color: #6b7a8f;">${percent}%</div>
                    </div>
                </div>
            `;
        });
    }
    
    html += `
        </div>
    `;
    
    body.innerHTML = html;
}

// Показать историю покупок
async function showAssetPurchaseHistory() {
    if (!currentAssetId) {
        showNotification('error', 'Ошибка', 'ID актива не найден');
        return;
    }
    
    // Закрываем модальное окно деталей
    closeAssetDetailsModal();
    
    // Открываем модальное окно истории
    const modal = document.getElementById('purchaseHistoryModal');
    const symbolSpan = document.getElementById('purchaseHistorySymbol');
    const body = document.getElementById('purchaseHistoryBody');
    
    symbolSpan.textContent = currentAssetSymbol;
    modal.classList.add('active');
    body.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Загрузка истории...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_asset_purchase_history');
    formData.append('asset_id', currentAssetId);
    
    try {
        const response = await fetch(API_URL_PHP, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success && result.history) {
            displayPurchaseHistory(result.history, currentAssetSymbol);
        } else {
            body.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки истории</div>';
        }
    } catch (error) {
        body.innerHTML = '<div style="text-align: center; padding: 30px; color: #e53e3e;">Ошибка загрузки</div>';
    }
}

// Отображение истории покупок
function displayPurchaseHistory(history, symbol) {
    const body = document.getElementById('purchaseHistoryBody');
    
    if (history.length === 0) {
        body.innerHTML = '<div style="text-align: center; padding: 30px; color: #6b7a8f;">Нет истории покупок</div>';
        return;
    }
    
    let html = '';
    history.forEach(item => {
        const date = new Date(item.operation_date).toLocaleDateString('ru-RU');
        const quantity = formatAmount(item.quantity, symbol);
        const price = formatAmount(item.price, item.price_currency);
        const total = formatAmount(item.quantity * item.price, item.price_currency);
        
        html += `
            <div style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="font-weight: 500;">${date}</span>
                    <span style="color: #00a86b;">${quantity} ${symbol}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; color: #6b7a8f;">
                    <span>${item.platform_name}</span>
                    <span>по ${price} ${item.price_currency} (${total} ${item.price_currency})</span>
                </div>
            </div>
        `;
    });
    
    body.innerHTML = html;
}

function closeAssetDetailsModal() {
    document.getElementById('assetDetailsModal').classList.remove('active');
}

function closePurchaseHistoryModal() {
    document.getElementById('purchaseHistoryModal').classList.remove('active');
}