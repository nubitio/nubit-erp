<?php

declare(strict_types=1);

namespace App\Onboarding;

use DateTimeImmutable;
use DateTimeZone;

final readonly class OpeningBalanceRow
{
    public function __construct(
        public string $ruc,
        public string $name,
        public string $amount,
        public DateTimeImmutable $dueDate,
        public ?string $number,
        public ?string $phone,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function tryFrom(array $row): self|string
    {
        $ruc = self::pick($row, ['ruc', 'ruc/dni', 'dni', 'documento']);
        $name = self::pick($row, ['nombre', 'razon social', 'razón social', 'cliente', 'proveedor']);
        $amountRaw = self::pick($row, ['monto', 'importe', 'total', 'saldo']);
        $dueRaw = self::pick($row, ['vencimiento', 'fecha de vencimiento', 'due']);
        $number = self::pick($row, ['numero', 'número', 'factura', 'comprobante']) ?: null;
        $phone = self::pick($row, ['telefono', 'teléfono', 'phone']) ?: null;

        if ($ruc === '' || !preg_match('/^\d{8,11}$/', $ruc)) {
            return 'RUC/DNI inválido';
        }

        if ($name === '') {
            return 'Falta el nombre';
        }

        $amount = self::normalizeAmount($amountRaw);
        if (!is_numeric($amount) || (float) $amount <= 0) {
            return 'Monto inválido';
        }

        $dueDate = self::parseDate($dueRaw) ?? new DateTimeImmutable('today', new DateTimeZone('America/Lima'));

        return new self(
            ruc: $ruc,
            name: $name,
            amount: number_format((float) $amount, 2, '.', ''),
            dueDate: $dueDate,
            number: $number,
            phone: $phone,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function pick(array $row, array $aliases): string
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[mb_strtolower(trim((string) $key))] = trim((string) $value);
        }

        foreach ($aliases as $alias) {
            $value = $normalized[$alias] ?? '';
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function normalizeAmount(string $value): string
    {
        $raw = str_replace(' ', '', trim($value));
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{1,2}$/', $raw) === 1) {
            return str_replace(['.', ','], ['', '.'], $raw);
        }

        if (preg_match('/^\d{1,3}(,\d{3})+\.\d{1,2}$/', $raw) === 1) {
            return str_replace(',', '', $raw);
        }

        return str_replace(',', '.', $raw);
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $zone = new DateTimeZone('America/Lima');
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, $zone);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        return null;
    }
}
