<?php

declare(strict_types=1);

namespace App\Treasury\Report;

use App\Efact\Domain\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * What is still owed on a table of open items (customer sales or supplier bills), per currency, split by due
 * date: overdue, due within 7 days and due within 30 days. Both sides have a `balance_due`, a `due_date` and a
 * `currency`; the caller says which table and which rows count, with the same filters the screens use.
 */
final readonly class OpenItemsQuery
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @param string $where trusted SQL written by the caller, never user input
     *
     * @return array<string, array{balance: string, overdue: string, dueWithinSevenDays: string, dueWithinThirtyDays: string, count: int, overdueCount: int}>
     */
    public function byCurrency(string $table, string $where, DateTimeImmutable $today): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT currency,
                        COUNT(*) AS item_count,
                        SUM(balance_due) AS balance,
                        SUM(CASE WHEN due_date < :today THEN balance_due ELSE 0 END) AS overdue,
                        SUM(CASE WHEN due_date < :today THEN 1 ELSE 0 END) AS overdue_count,
                        SUM(CASE WHEN due_date >= :today AND due_date <= :week THEN balance_due ELSE 0 END) AS due_seven,
                        SUM(CASE WHEN due_date >= :today AND due_date <= :month THEN balance_due ELSE 0 END) AS due_thirty
                 FROM %s
                 WHERE %s
                 GROUP BY currency', $table, $where), [
            'today' => $today->format('Y-m-d'),
            'week' => $today->modify('+7 days')->format('Y-m-d'),
            'month' => $today->modify('+30 days')->format('Y-m-d'),
        ]);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['currency']] = [
                'balance' => self::money($row['balance']),
                'overdue' => self::money($row['overdue']),
                'dueWithinSevenDays' => self::money($row['due_seven']),
                'dueWithinThirtyDays' => self::money($row['due_thirty']),
                'count' => (int) $row['item_count'],
                'overdueCount' => (int) $row['overdue_count'],
            ];
        }

        return $out;
    }

    /** @return array{balance: string, overdue: string, dueWithinSevenDays: string, dueWithinThirtyDays: string, count: int, overdueCount: int} */
    public static function none(): array
    {
        return [
            'balance' => '0.00',
            'overdue' => '0.00',
            'dueWithinSevenDays' => '0.00',
            'dueWithinThirtyDays' => '0.00',
            'count' => 0,
            'overdueCount' => 0,
        ];
    }

    private static function money(mixed $value): string
    {
        return Money::toString(Money::money((string) $value));
    }
}
