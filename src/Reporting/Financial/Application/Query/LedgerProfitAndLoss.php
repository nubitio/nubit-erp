<?php

declare(strict_types=1);

namespace App\Reporting\Financial\Application\Query;

use Doctrine\DBAL\Connection;

/**
 * One month of the profit and loss taken from the general ledger, in the same shape the operational estimate
 * returns. Income is the INCOME accounts (credits are sales, debits are credit notes and discounts), the cost
 * of sales is the 69 accounts and every other EXPENSE account is an operating expense, by account name.
 * Nothing here is estimated: it is what the accountant sees.
 */
final readonly class LedgerProfitAndLoss
{
    private const string COST_OF_SALES_PREFIX = '69';

    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array<string, mixed> */
    public function period(string $period): array
    {
        $rows = $this->connection->fetchAllAssociative("SELECT a.type, a.code, a.name,
                    COALESCE(SUM(l.debit), 0) AS debit,
                    COALESCE(SUM(l.credit), 0) AS credit
             FROM journal_entry_line l
             INNER JOIN journal_entry e ON e.id = l.journal_entry_id
             INNER JOIN chart_of_accounts a ON a.id = l.account_id
             WHERE DATE_FORMAT(e.entry_date, '%Y%m') = :period
               AND a.type IN ('INCOME', 'EXPENSE')
             GROUP BY a.id, a.type, a.code, a.name", [
            'period' => $period,
        ]);

        $gross = 0.0;
        $creditNotes = 0.0;
        $costOfSales = 0.0;
        $expenses = [];
        foreach ($rows as $row) {
            $debit = (float) $row['debit'];
            $credit = (float) $row['credit'];
            if ($row['type'] === 'INCOME') {
                $gross += $credit;
                $creditNotes += $debit;

                continue;
            }

            $net = $debit - $credit;
            if (str_starts_with((string) $row['code'], self::COST_OF_SALES_PREFIX)) {
                $costOfSales += $net;

                continue;
            }
            $expenses[(string) $row['name']] = ($expenses[(string) $row['name']] ?? 0.0) + $net;
        }

        arsort($expenses);
        $byCategory = [];
        foreach ($expenses as $name => $amount) {
            if ($amount !== 0.0) {
                $byCategory[] = ['categoria' => $name, 'monto' => $amount];
            }
        }
        $operating = array_sum(array_column($byCategory, 'monto'));
        $net = $gross - $creditNotes;
        $grossProfit = $net - $costOfSales;

        return [
            'ingresos' => ['bruto' => $gross, 'notasCredito' => $creditNotes, 'neto' => $net],
            'costoVentas' => ['monto' => $costOfSales, 'isEstimate' => false],
            'utilidadBruta' => $grossProfit,
            'gastosOperativos' => ['total' => $operating, 'porCategoria' => $byCategory],
            'utilidadNeta' => $grossProfit - $operating,
        ];
    }
}
