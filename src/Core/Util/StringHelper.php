<?php

declare(strict_types=1);

namespace App\Core\Util;

final class StringHelper
{
    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . $end;
    }

    public static function random(int $length = 16): string
    {
        $string = '';
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $string;
    }

    public static function slug(string $value, string $separator = '-'): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9\s-]/', '', $value);
        $value = preg_replace('/[\s-]+/', $separator, $value);

        return trim($value, $separator);
    }

    public static function camelCase(string $value): string
    {
        return lcfirst(self::studlyCase($value));
    }

    public static function studlyCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);

        return str_replace(' ', '', $value);
    }

    public static function snakeCase(string $value): string
    {
        $value = preg_replace('/\s+/', '', ucwords($value));
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1_', $value);

        return mb_strtolower($value);
    }

    public static function kebabCase(string $value): string
    {
        return str_replace('_', '-', self::snakeCase($value));
    }

    public static function mask(string $value, string $character = '*', int $index = 0, ?int $length = null): string
    {
        if ($length === null) {
            $length = mb_strlen($value) - $index;
        }

        $segment = mb_substr($value, $index, $length);
        $mask = str_repeat($character, mb_strlen($segment));

        return mb_substr($value, 0, $index) . $mask . mb_substr($value, $index + $length);
    }
}
