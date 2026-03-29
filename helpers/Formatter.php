<?php
class Formatter {
    public static function quantity($value, $type = 'crypto') {
        if ($value === null) return '0';
        $num = (float)$value;
        
        if ($type === 'crypto') {
            if (floor($num) == $num) {
                return number_format($num, 0, '.', ' ');
            }
            $formatted = number_format($num, 8, '.', ' ');
            return rtrim(rtrim($formatted, '0'), '.');
        }
        return number_format($num, 2, '.', ' ');
    }
    
    public static function price($value, $currency = null) {
        if ($value === null) return '—';
        $formatted = number_format((float)$value, 2, '.', ' ');
        $formatted = preg_replace('/\.?0+$/', '', $formatted);
        return $currency ? "{$formatted} {$currency}" : $formatted;
    }
    
    public static function value($value, $currency = 'USD') {
        if ($value === null) return '0';
        $formatted = number_format((float)$value, 2, '.', ' ');
        $formatted = preg_replace('/\.?0+$/', '', $formatted);
        if ($currency === 'USD') return "$" . $formatted;
        if ($currency === 'RUB') return $formatted . " ₽";
        return "{$formatted} {$currency}";
    }
    
    public static function percent($value) {
        if ($value === null) return '0%';
        $formatted = number_format((float)$value, 1, '.', ' ');
        return ((float)$value > 0 ? '+' : '') . $formatted . '%';
    }
    
    public static function profitClass($value) {
        if ($value > 0) return 'positive';
        if ($value < 0) return 'negative';
        return 'neutral';
    }
    
    public static function profitIcon($value) {
        if ($value > 0) return 'fa-arrow-up';
        if ($value < 0) return 'fa-arrow-down';
        return 'fa-minus';
    }
}