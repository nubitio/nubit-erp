<?php

declare(strict_types=1);

namespace App\Accounting\Service;

use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Connection;

final readonly class TrialBalanceQuery
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array<string, mixed> */
    public function compute(string $period): array
    {
        if (!preg_match('/^\d{6}$/', $period)) {
            $period = date('Ym');
        }

        $from = substr($period, 0, 4) . '-' . substr($period, 4, 2) . '-01';
        $to = date('Y-m-t', strtotime($from) ?: time());

        $rows = $this->connection->fetchAllAssociative('SELECT a.code, a.name, a.type,
                    COALESCE(SUM(l.debit), 0) AS debit,
                    COALESCE(SUM(l.credit), 0) AS credit
             FROM chart_of_accounts a
             LEFT JOIN journal_entry_line l ON l.account_id = a.id
             LEFT JOIN journal_entry e ON e.id = l.journal_entry_id
                  AND e.entry_date >= :from AND e.entry_date <= :to
             WHERE a.is_header = 0 AND a.is_active = 1
             GROUP BY a.id, a.code, a.name, a.type
             ORDER BY a.code', ['from' => $from, 'to' => $to]);

        $accounts = [];
        $totalDebit = BigDecimal::zero();
        $totalCredit = BigDecimal::zero();
        foreach ($rows as $row) {
            $debit = Money::money($row['debit']);
            $credit = Money::money($row['credit']);
            $net = Money::sub($debit, $credit);
            $deudor = $net->isPositive() || $net->isZero()
                ? Money::toString($net->isPositive() ? $net : BigDecimal::zero())
                : '0.00';
            $acreedor = $net->isNegative() ? Money::toString($net->abs()) : '0.00';
            if ($debit->isZero() && $credit->isZero()) {
                continue;
            }

            $accounts[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
                'debit' => Money::toString($debit),
                'credit' => Money::toString($credit),
                'deudor' => $deudor,
                'acreedor' => $acreedor,
            ];
            $totalDebit = $totalDebit->plus($debit);
            $totalCredit = $totalCredit->plus($credit);
        }

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'accounts' => $accounts,
            'totalDebit' => Money::toString($totalDebit),
            'totalCredit' => Money::toString($totalCredit),
            'balanced' => $totalDebit->compareTo($totalCredit) === 0,
        ];
    }
}
