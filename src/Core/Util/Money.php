<?php

declare(strict_types=1);

namespace App\Core\Util;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Money
{
    public const int SCALE = 2;
    private const int RATE_SCALE = 6;

    private function __construct()
    {
    }

    public static function of(mixed $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }

        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        if (is_int($value) || is_string($value)) {
            return BigDecimal::of((string) $value);
        }

        if (is_float($value)) {
            $normalized = rtrim(rtrim(sprintf('%.14F', $value), '0'), '.');

            return BigDecimal::of($normalized === '' ? '0' : $normalized);
        }

        return BigDecimal::of((string) $value);
    }

    public static function money(mixed $value): BigDecimal
    {
        return self::of($value)->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    public static function rate(mixed $percent): BigDecimal
    {
        return self::of($percent)->dividedBy(100, self::RATE_SCALE, RoundingMode::HALF_UP);
    }

    public static function add(BigDecimal $left, BigDecimal $right): BigDecimal
    {
        return $left->plus($right)->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    public static function sub(BigDecimal $left, BigDecimal $right): BigDecimal
    {
        return $left->minus($right)->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    public static function mul(BigDecimal $left, BigDecimal $right): BigDecimal
    {
        return $left->multipliedBy($right)->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    public static function div(BigDecimal $left, BigDecimal $right, int $scale = self::RATE_SCALE): BigDecimal
    {
        if ($right->isZero()) {
            return BigDecimal::zero();
        }

        return $left->dividedBy($right, $scale, RoundingMode::HALF_UP);
    }

    public static function toString(BigDecimal $value, int $scale = self::SCALE): string
    {
        return $value->toScale($scale, RoundingMode::HALF_UP)->__toString();
    }

    public static function toFloat(BigDecimal $value, int $scale = self::SCALE): float
    {
        return (float) self::toString($value, $scale);
    }

    public static function toMinor(BigDecimal $value): int
    {
        $scaled = $value->toScale(self::SCALE, RoundingMode::HALF_UP);
        $minor = $scaled->multipliedBy(100)->toScale(0, RoundingMode::HALF_UP);

        return (int) $minor->__toString();
    }
}
