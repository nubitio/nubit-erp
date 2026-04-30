<?php

declare(strict_types=1);

namespace App\Efact\Util;

final class ArrayHelper
{
    /**
     * @param array<string, mixed> $array
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        $keys = explode('.', $key);
        $current = $array;

        foreach ($keys as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function set(array &$array, string $key, mixed $value): void
    {
        if (!str_contains($key, '.')) {
            $array[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }

                $current = &$current[$segment];
            }
        }
    }

    /**
     * @param array<string, mixed> $array
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * @param array<string, mixed> $array
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    public static function flatten(array $array, string $separator = '.'): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $flattened = self::flatten($value, $separator);
                foreach ($flattened as $nestedKey => $nestedValue) {
                    $result[$key . $separator . $nestedKey] = $nestedValue;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $array
     */
    /**
     * @param array<array-key, mixed> $array
     */
    public static function isAssociative(array $array): bool
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
