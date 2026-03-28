<?php
class Formatter {
    
    public static function number($value, $decimals = 2) {
        if ($value === null) return '0';
        
        $formatted = number_format((float)$value, $decimals, '.', '');
        $parts = explode('.', $formatted);
        $parts[0] = number_format((int)$parts[0], 0, '', ' ');
        
        if ($decimals > 0 && isset($parts[1]) && (int)$parts[1] > 0) {
            return $parts[0] . '.' . rtrim($parts[1], '0');
        }
        return $parts[0];
    }
    
    public static function currency($value, $currency = 'USD', $decimals = null) {
        if ($value === null) return '—';
        
        $isCrypto = in_array($currency, ['BTC', 'ETH', 'SOL', 'USDT', 'USDC']);
        $decimals = $decimals ?? ($isCrypto ? 6 : 2);
        
        $formatted = self::number($value, $decimals);
        
        $symbols = [
            'RUB' => '₽',
            'USD' => '$',
            'EUR' => '€',
            'BTC' => '₿',
            'ETH' => 'Ξ',
            'USDT' => '₮'
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        
        if ($currency === 'RUB') {
            return $formatted . ' ' . $symbol;
        }
        return $symbol . $formatted;
    }
    
    public static function date($date, $format = 'd.m.Y') {
        if (!$date) return '';
        $timestamp = is_string($date) ? strtotime($date) : $date;
        return date($format, $timestamp);
    }
    
    public static function quantity($value, $symbol, $isCrypto = false) {
        if ($value === null) return '0';
        
        if ($isCrypto || in_array($symbol, ['BTC', 'ETH', 'SOL', 'USDT', 'USDC'])) {
            $decimals = 6;
        } else {
            $decimals = 2;
        }
        
        return self::number($value, $decimals);
    }
}