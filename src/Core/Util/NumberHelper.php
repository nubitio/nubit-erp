<?php

declare(strict_types=1);

namespace App\Core\Util;

final class NumberHelper
{
    public static function format(
        float $number,
        int $decimals = 2,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ','
    ): string {
        return number_format($number, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    public static function formatCurrency(
        float $amount,
        string $currency = 'USD',
        int $decimals = 2
    ): string {
        return $currency . ' ' . self::format($amount, $decimals);
    }

    public static function percentage(float $value, float $total, int $decimals = 2): float
    {
        if ($total == 0) {
            return 0;
        }

        return round(($value / $total) * 100, $decimals);
    }

    public static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    public static function isEven(int $number): bool
    {
        return $number % 2 === 0;
    }

    public static function isOdd(int $number): bool
    {
        return !self::isEven($number);
    }

    /**
     * @param 1|2|3|4 $mode Rounding mode constant
     * PHP_ROUND_HALF_UP=1,
     * PHP_ROUND_HALF_DOWN=2,
     * PHP_ROUND_HALF_EVEN=3,
     * PHP_ROUND_HALF_ODD=4
     */
    public static function round(float $value, int $precision = 0, int $mode = PHP_ROUND_HALF_UP): float
    {
        return round($value, $precision, $mode);
    }

    public static function ceil(float $value, int $precision = 0): float
    {
        $multiplier = pow(10, $precision);
        return ceil($value * $multiplier) / $multiplier;
    }

    public static function floor(float $value, int $precision = 0): float
    {
        $multiplier = pow(10, $precision);
        return floor($value * $multiplier) / $multiplier;
    }

    public static function inRange(float $value, float $min, float $max): bool
    {
        return $value >= $min && $value <= $max;
    }
}
