<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use DateTimeImmutable;

/**
 * Sales to the customers that have an account or want a factura, placed on fixed days so the
 * receivables ageing is the same every night.
 */
final class AccountSales
{
    /** @var array<string, list<array{code: string, qty: int}>> */
    private const array BASKETS = [
        'carlos' => [
            ['code' => 'ARR5', 'qty' => 1],
            ['code' => 'ACE1', 'qty' => 1],
            ['code' => 'AZU1', 'qty' => 2],
            ['code' => 'LEC400', 'qty' => 3],
        ],
        'rosa' => [['code' => 'PAN', 'qty' => 10], ['code' => 'LEC400', 'qty' => 2], ['code' => 'HUE12', 'qty' => 1]],
        'restaurante' => [
            ['code' => 'ARR5', 'qty' => 6],
            ['code' => 'ACE1', 'qty' => 8],
            ['code' => 'AZU1', 'qty' => 10],
            ['code' => 'HUE12', 'qty' => 6],
            ['code' => 'FID5', 'qty' => 12],
        ],
        'panaderia' => [
            ['code' => 'AZU1', 'qty' => 12],
            ['code' => 'ACE1', 'qty' => 6],
            ['code' => 'HUE12', 'qty' => 8],
        ],
    ];

    /**
     * Days ago => [customer, payment, days of credit]. Carlos is behind on every tab; the others are current.
     *
     * @var array<int, array{0: string, 1: string, 2: int}>
     */
    private const array PLAN = [
        38 => ['carlos', 'Credito', 15],
        33 => ['carlos', 'Credito', 15],
        29 => ['carlos', 'Credito', 15],
        23 => ['carlos', 'Credito', 15],
        20 => ['restaurante', 'Credito', 30],
        12 => ['panaderia', 'Transferencia', 0],
        6 => ['rosa', 'Credito', 15],
        5 => ['panaderia', 'Yape', 0],
        4 => ['restaurante', 'Transferencia', 0],
        2 => ['rosa', 'Credito', 15],
    ];

    /** The day SUNAT rejected a factura for a bad receiver RUC. */
    private const int REJECTED_DAYS_AGO = 4;

    /** @return list<SalePlan> */
    public function forDay(int $daysAgo, DateTimeImmutable $shiftStart): array
    {
        if (!isset(self::PLAN[$daysAgo])) {
            return [];
        }

        [$customer, $payment, $creditDays] = self::PLAN[$daysAgo];

        return [
            new SalePlan(
                cashier: 'maria',
                at: $shiftStart->modify('+' . (95 + $daysAgo) . ' minutes'),
                customerKey: $customer,
                lines: self::BASKETS[$customer],
                payment: $payment,
                sunat: $daysAgo === self::REJECTED_DAYS_AGO ? 'rejected' : 'accepted',
                creditDays: $creditDays,
            ),
        ];
    }
}
