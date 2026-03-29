<?php
// ============================================================================
// КЛАСС ДЛЯ ФОРМАТИРОВАНИЯ ДАННЫХ
// ============================================================================

class Formatter {
    
    /**
     * Форматирование количества актива
     */
    public static function quantity($value, $type = 'crypto') {
        if ($value === null || $value === '') return '0';
        $num = (float)$value;
        
        if ($type === 'crypto') {
            if (floor($num) == $num) {
                return number_format($num, 0, '.', ' ');
            }
            $formatted = number_format($num, 8, '.', ' ');
            return rtrim(rtrim($formatted, '0'), '.');
        }
        
        if ($type === 'stock' || $type === 'etf') {
            return number_format($num, 0, '.', ' ') . ' шт';
        }
        
        return number_format($num, 2, '.', ' ');
    }
    
    /**
     * Форматирование цены
     */
    public static function price($value, $currency = null) {
        if ($value === null || $value === '') return '—';
        $num = (float)$value;
        
        $formatted = number_format($num, 2, '.', ' ');
        $formatted = preg_replace('/\.?0+$/', '', $formatted);
        
        return $currency ? "{$formatted} {$currency}" : $formatted;
    }
    
    /**
     * Форматирование стоимости
     */
    public static function value($value, $currency = 'USD') {
        if ($value === null || $value === '') return '0';
        $num = (float)$value;
        
        $formatted = number_format($num, 2, '.', ' ');
        $formatted = preg_replace('/\.?0+$/', '', $formatted);
        
        if ($currency === 'USD') return "$" . $formatted;
        if ($currency === 'RUB') return $formatted . " ₽";
        return "{$formatted} {$currency}";
    }
    
    /**
     * Форматирование процентов
     */
    public static function percent($value) {
        if ($value === null) return '0%';
        $num = (float)$value;
        $formatted = number_format($num, 1, '.', ' ');
        return ($num > 0 ? '+' : '') . $formatted . '%';
    }
    
    /**
     * Получение класса для доходности
     */
    public static function profitClass($value) {
        if ($value > 0) return 'positive';
        if ($value < 0) return 'negative';
        return 'neutral';
    }
    
    /**
     * Получение иконки для доходности
     */
    public static function profitIcon($value) {
        if ($value > 0) return 'fa-arrow-up';
        if ($value < 0) return 'fa-arrow-down';
        return 'fa-minus';
    }
}