<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

final class JournalPleDates
{
    public static function toDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value) && $value !== '') {
            return new \DateTimeImmutable($value);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function periodBounds(string $period): array
    {
        $start = \DateTimeImmutable::createFromFormat('Ymd', $period . '01');
        if ($start === false) {
            throw new \InvalidArgumentException('Periodo inválido, se espera AAAAMM.');
        }

        return [$start->format('Y-m-d'), $start->modify('last day of this month')->format('Y-m-d')];
    }

    public static function money(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round($value, 2);
        }
        if (is_string($value) && is_numeric($value)) {
            return round((float) $value, 2);
        }

        return 0.0;
    }

    public static function clip(string $value, int $max): string
    {
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function glosa(array $row): string
    {
        $description = (string) ($row['description'] ?? '');
        $source = $description !== '' ? $description : (string) ($row['account_name'] ?? 'Asiento');

        return self::clip($source, 200);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function currency(array $row): string
    {
        $currency = strtoupper((string) ($row['currency'] ?? 'PEN'));

        return $currency !== '' ? $currency : 'PEN';
    }
}
