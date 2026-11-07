<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use Brick\Math\BigDecimal;

/** Lectura tipada de una fila de base de datos (todo llega como texto o nulo). */
final readonly class RvieRow
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
    ) {}

    public function text(string $key): string
    {
        $value = $this->data[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    public function nullable(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function money(string $key): BigDecimal
    {
        $value = $this->text($key);

        return BigDecimal::of($value === '' ? '0' : $value);
    }

    public function int(string $key): int
    {
        return (int) $this->text($key);
    }

    public function isNull(string $key): bool
    {
        return ($this->data[$key] ?? null) === null;
    }
}
