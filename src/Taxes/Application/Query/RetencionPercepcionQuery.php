<?php

declare(strict_types=1);

namespace App\Taxes\Application\Query;

use Doctrine\DBAL\Connection;

final readonly class RetencionPercepcionQuery
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /** @return array<string, mixed> */
    public function forPeriod(string $period = ''): array
    {
        if ('' === $period) {
            $period = date('Ym');
        }

        $items = $this->connection->fetchAllAssociative(
            "SELECT
                type,
                direction,
                counterparty_name,
                document_reference,
                rate,
                base_amount,
                amount
             FROM retention_percepcion
             WHERE DATE_FORMAT(period_date,'%Y%m') = :period
             ORDER BY period_date DESC",
            ['period' => $period],
        );

        foreach ($items as &$item) {
            $item['rate'] = (float) ($item['rate'] ?? 0);
            $item['base_amount'] = (float) ($item['base_amount'] ?? 0);
            $item['amount'] = (float) ($item['amount'] ?? 0);
        }
        unset($item);

        return [
            'items' => $items,
        ];
    }
}
