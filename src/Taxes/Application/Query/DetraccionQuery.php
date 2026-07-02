<?php

declare(strict_types=1);

namespace App\Taxes\Application\Query;

use Doctrine\DBAL\Connection;

final readonly class DetraccionQuery
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array<string, mixed> */
    public function forPeriod(string $period = ''): array
    {
        if ('' === $period) {
            $period = date('Ym');
        }

        $cuenta = $this->connection->fetchOne('SELECT detraction_account FROM tenant_settings LIMIT 1');

        $items = $this->connection->fetchAllAssociative("SELECT
                td.series,
                td.number,
                COALESCE(c.razon_social, '—') as cliente,
                COALESCE(sc.description, 'Venta de bienes') as concepto,
                td.detraction_percent as spot_percent,
                td.total as base,
                td.detraction_amount as detraccion,
                CASE WHEN td.detraction_deposited_at IS NOT NULL THEN 'depositado' ELSE 'pendiente' END as estado
             FROM tax_document td
             LEFT JOIN cliente c ON c.id = td.customer_id
             LEFT JOIN sunat_catalog sc ON sc.catalog = '54' AND sc.code = td.detraction_code
             WHERE td.detraction_amount IS NOT NULL AND td.detraction_amount > 0
               AND DATE_FORMAT(td.issue_date,'%Y%m') = :period
               AND td.void_ticket IS NULL AND td.void_correlation IS NULL
             ORDER BY td.issue_date DESC", [
            'period' => $period,
        ]);

        $depositado = 0.0;
        $pendiente = 0.0;

        foreach ($items as &$item) {
            $item['spot_percent'] = (float) ($item['spot_percent'] ?? 0);
            $item['base'] = (float) ($item['base'] ?? 0);
            $item['detraccion'] = (float) ($item['detraccion'] ?? 0);

            if ('depositado' === $item['estado']) {
                $depositado += $item['detraccion'];
            } else {
                $pendiente += $item['detraccion'];
            }
        }
        unset($item);

        return [
            'cuenta' => $cuenta ?: '',
            'depositado' => $depositado,
            'pendiente' => $pendiente,
            'items' => $items,
        ];
    }
}
