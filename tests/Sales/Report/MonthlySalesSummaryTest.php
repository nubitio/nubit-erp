<?php

declare(strict_types=1);

namespace App\Tests\Sales\Report;

use App\Sales\Report\Summary\MonthlySalesSummary;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class MonthlySalesSummaryTest extends TestCase
{
    public function test_totals_are_scoped_by_branch_month_status_and_currency(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement(
            'CREATE TABLE sales (id TEXT, branch_id TEXT, issued_date TEXT, status TEXT,
            deleted_at TEXT, currency TEXT, grand_total DECIMAL(12,2), balance_due DECIMAL(12,2))',
        );
        foreach ([
            ['a', '2026-10-01', 'COMPLETED', null,         'PEN', '100.10', '20.10'],
            ['a', '2026-10-31', 'COMPLETED', null,         'PEN', '50.20',  '0.00'],
            ['a', '2026-10-15', 'COMPLETED', null,         'USD', '9.99',   '1.99'],
            ['b', '2026-10-15', 'COMPLETED', null,         'PEN', '900',    '900'],
            ['a', '2026-09-30', 'COMPLETED', null,         'PEN', '900',    '900'],
            ['a', '2026-11-01', 'COMPLETED', null,         'PEN', '900',    '900'],
            ['a', '2026-10-15', 'VOIDED',    null,         'PEN', '900',    '900'],
            ['a', '2026-10-15', 'COMPLETED', '2026-10-16', 'PEN', '900',    '900'],
        ] as $index => $row) {
            $db->executeStatement('INSERT INTO sales VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [(string) $index, ...$row]);
        }
        $db->executeStatement('CREATE TABLE tax_document (tax_document_id TEXT, sale_id TEXT,
            movimiento_id TEXT, reason_code TEXT, sunat_status TEXT, created_at TEXT)');
        foreach ([
            ['old',          '0', null, null, '2001',  '2026-10-01'],
            ['new',          '0', null, null, '0',     '2026-10-02'],
            ['pending1',     '1', null, null, null,    '2026-10-03'],
            ['pending2',     '1', null, null, 'ERROR', '2026-10-04'],
            ['credit-note',  '1', null, '01', '0',     '2026-10-05'],
            ['other-branch', '3', null, null, null,    '2026-10-03'],
        ] as $document) {
            $db->executeStatement('INSERT INTO tax_document VALUES (?, ?, ?, ?, ?, ?)', $document);
        }
        $summary = new MonthlySalesSummary($db);
        // November 1 UTC is still October in Lima.
        $now = new DateTimeImmutable('2026-11-01T02:00:00+00:00');
        self::assertSame(
            [
                'periodStart' => '2026-10-01',
                'periodEnd' => '2026-11-01',
                'currencies' => [
                    [
                        'currency' => 'PEN',
                        'sold' => '150.30',
                        'balanceDue' => '20.10',
                        'saleCount' => 2,
                        'sunatPending' => 1,
                    ],
                    [
                        'currency' => 'USD',
                        'sold' => '9.99',
                        'balanceDue' => '1.99',
                        'saleCount' => 1,
                        'sunatPending' => 0,
                    ],
                ],
            ],
            $summary->get('a', $now),
        );
        self::assertSame([], $summary->get('empty-branch', $now)['currencies']);
    }
}
