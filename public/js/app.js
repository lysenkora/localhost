// Глобальные переменные
window.currentModalContext = {
    source: 'default',
    mode: null,
    subMode: null
};

// Функции форматирования
function formatNumberWithSpaces(value, decimals = null) {
    if (!value && value !== 0) return '';
    
    let num = parseFloat(String(value).replace(/\s/g, '').replace(',', '.'));
    if (isNaN(num)) return value;
    
    let decimalPlaces = decimals !== null ? decimals : 6;
    let formatted = num.toFixed(decimalPlaces);
    formatted = formatted.replace(/\.?0+$/, '');
    
    let parts = formatted.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
    if (parts.length > 1 && parts[1]) {
        return parts[0] + '.' + parts[1];
    }
    return parts[0];
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
    if (decimalMatch) {
        originalDecimalPlaces = decimalMatch[1].length;
    }
    
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
        const newCursorPos = Math.min(cursorPos + lengthDiff, newLength);
        input.setSelectionRange(newCursorPos, newCursorPos);
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
        const newCursorPos = Math.min(cursorPos + lengthDiff, newLength);
        input.setSelectionRange(newCursorPos, newCursorPos);
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
    
    if (rawValue === '.') {
        newCursorPos = formattedValue.indexOf('.') + 1;
    }
    
    input.setSelectionRange(newCursorPos, newCursorPos);
}

// Блокировка скролла
function disableBodyScroll() {
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

// Инициализация числовых полей
function initNumberInputs() {
    const numberInputs = [
        'depositAmount', 'tradeQuantity', 'tradePrice', 'tradeCommission',
        'transferAmount', 'transferCommission', 'limitQuantity', 'limitPrice',
        'expenseAmount'
    ];
    
    numberInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', function() { formatInput(this); });
            input.addEventListener('blur', function() { formatInput(this); });
        }
    });
}

// Экранирование HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    initNumberInputs();
});