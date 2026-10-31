<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Report;

use App\AccountsPayable\Report\SupplierBillSummaryQuery;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class SupplierBillSummaryQueryTest extends TestCase
{
    public function testSummaryScopesBranchSeparatesCurrenciesAndUsesOutstandingBalancesAndDueDates(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement(
            'CREATE TABLE supplier_bill (branch_id TEXT, supplier_id TEXT, currency TEXT, balance_due DECIMAL(12,2), status TEXT, due_date TEXT)',
        );
        $rows = [
            ['a', 'one',   'PEN', '0.10',   'PARTIAL', '2026-10-28'],
            ['a', 'one',   'PEN', '0.20',   'PENDING', '2026-10-29'],
            ['a', 'two',   'PEN', '10.00',  'PENDING', '2026-11-05'],
            ['a', 'two',   'PEN', '20.00',  'PENDING', '2026-11-06'],
            ['a', 'three', 'PEN', '30.00',  'PENDING', '2026-11-28'],
            ['a', 'three', 'PEN', '40.00',  'PENDING', '2026-11-29'],
            ['a', 'one',   'USD', '7.50',   'PENDING', '2026-10-29'],
            ['b', 'other', 'PEN', '999.00', 'PENDING', '2026-10-29'],
            ['a', 'one',   'PEN', '888.00', 'VOID',    '2026-10-29'],
            ['a', 'one',   'PEN', '0.00',   'PAID',    '2026-10-29'],
        ];
        foreach ($rows as $row) {
            $db->executeStatement('INSERT INTO supplier_bill VALUES (?, ?, ?, ?, ?, ?)', $row);
        }
        $query = new SupplierBillSummaryQuery($db);
        self::assertSame(
            [
                [
                    'currency' => 'PEN',
                    'balanceDue' => '100.30',
                    'overdueTotal' => '0.10',
                    'dueWithinSevenDays' => '10.20',
                    'dueWithinThirtyDays' => '60.20',
                    'billCount' => 6,
                    'supplierCount' => 3,
                ],
                [
                    'currency' => 'USD',
                    'balanceDue' => '7.50',
                    'overdueTotal' => '0.00',
                    'dueWithinSevenDays' => '7.50',
                    'dueWithinThirtyDays' => '7.50',
                    'billCount' => 1,
                    'supplierCount' => 1,
                ],
            ],
            $query->execute('a', new DateTimeImmutable('2026-10-29')),
        );
        self::assertSame([], $query->execute('missing', new DateTimeImmutable('2026-10-29')));
    }
}
