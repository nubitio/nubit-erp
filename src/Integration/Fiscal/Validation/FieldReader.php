<?php

declare(strict_types=1);

namespace App\Integration\Fiscal\Validation;

/**
 * Null-safe reads over an untyped request body.
 */
final class FieldReader
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public static function arr(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }
}
