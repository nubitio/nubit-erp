<?php

declare(strict_types=1);

namespace App\Accounting\Service;

use App\Efact\Domain\Money;
use Doctrine\DBAL\Connection;

final readonly class LedgerQuery
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array<string, mixed> */
    public function compute(string $accountCode, string $from, string $to): array
    {
        $account = $this->connection->fetchAssociative(
            'SELECT id, code, name, type FROM chart_of_accounts WHERE code = :code AND is_active = 1',
            ['code' => $accountCode],
        );
        if ($account === false) {
            return [
                'account' => null,
                'from' => $from,
                'to' => $to,
                'opening' => '0.00',
                'lines' => [],
                'closing' => '0.00',
            ];
        }

        $openingRow = $this->connection->fetchAssociative(
            'SELECT COALESCE(SUM(l.debit - l.credit), 0) AS balance
             FROM journal_entry_line l
             INNER JOIN journal_entry e ON e.id = l.journal_entry_id
             WHERE l.account_id = :account AND e.entry_date < :from',
            ['account' => $account['id'], 'from' => $from],
        );
        $opening = Money::money($openingRow['balance'] ?? 0);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT e.entry_date, e.entry_no, e.description, e.source, l.debit, l.credit, l.memo
             FROM journal_entry_line l
             INNER JOIN journal_entry e ON e.id = l.journal_entry_id
             WHERE l.account_id = :account
               AND e.entry_date >= :from
               AND e.entry_date <= :to
             ORDER BY e.entry_date ASC, e.entry_no ASC, l.line_no ASC',
            ['account' => $account['id'], 'from' => $from, 'to' => $to],
        );

        $running = $opening;
        $lines = [];
        foreach ($rows as $row) {
            $debit = Money::money($row['debit']);
            $credit = Money::money($row['credit']);
            $running = $running->plus($debit)->minus($credit);
            $lines[] = [
                'date' => $row['entry_date'],
                'entryNo' => $row['entry_no'],
                'description' => $row['description'],
                'source' => $row['source'],
                'memo' => $row['memo'],
                'debit' => Money::toString($debit),
                'credit' => Money::toString($credit),
                'balance' => Money::toString($running),
            ];
        }

        return [
            'account' => [
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
            ],
            'from' => $from,
            'to' => $to,
            'opening' => Money::toString($opening),
            'lines' => $lines,
            'closing' => Money::toString($running),
        ];
    }
}
