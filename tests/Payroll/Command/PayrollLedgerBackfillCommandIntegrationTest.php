<?php

declare(strict_types=1);

namespace App\Tests\Payroll\Command;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** Runs the real command against the migrated schema (accounts come from the migrations). */
final class PayrollLedgerBackfillCommandIntegrationTest extends KernelTestCase
{
    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if ($this->connection?->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testPostsApprovedAndPaidRunsOnceAndIsIdempotent(): void
    {
        $db = $this->connection();
        $db->beginTransaction();
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $this->insertRun($db, 'run-approved', 'APPROVED', '2026-09-01', '2026-09-30 10:00:00', null);
        $this->insertRun($db, 'run-paid', 'PAID', '2026-10-01', '2026-10-30 10:00:00', '2026-10-31 10:00:00');
        $this->insertRun($db, 'run-draft', 'DRAFT', '2026-11-01', null, null);
        $this->entry($db, 'run-approved', '3000.00', '0.00', '270.00', '390.00', '0.00', '0.00', '2610.00');
        $this->entry($db, 'run-paid', '5000.00', '0.00', '450.00', '0.00', '600.00', '100.00', '4300.00');

        $first = $this->execute([]);
        self::assertSame(0, $first->getStatusCode(), $first->getDisplay());
        self::assertStringContainsString('3 eventos de planilla', $first->getDisplay());

        $entries = $db->fetchAllAssociative(
            "SELECT source, source_id, entry_date FROM journal_entry WHERE source LIKE 'payroll_%' ORDER BY entry_date, source",
        );
        self::assertSame(
            [
                ['payroll_accrual', 'run-approved'],
                ['payroll_accrual', 'run-paid'],
                ['payroll_payment', 'run-paid'],
            ],
            array_map(static fn(array $e): array => [$e['source'], $e['source_id']], $entries),
        );
        self::assertSame('2026-10-31', $entries[2]['entry_date']);

        // Every entry balances and the payment settles the run's net against banco.
        $balance = $db->fetchAssociative(
            "SELECT SUM(l.debit) AS d, SUM(l.credit) AS c FROM journal_entry_line l JOIN journal_entry e ON e.id = l.journal_entry_id WHERE e.source LIKE 'payroll_%'",
        );
        self::assertIsArray($balance);
        self::assertSame($balance['d'], $balance['c']);
        $bank = $db->fetchOne(
            "SELECT SUM(l.credit) FROM journal_entry_line l JOIN journal_entry e ON e.id = l.journal_entry_id JOIN chart_of_accounts a ON a.id = l.account_id WHERE e.source = 'payroll_payment' AND a.code = '1041'",
        );
        self::assertSame('4300.00', $bank);

        // Re-running creates nothing new.
        $second = $this->execute([]);
        self::assertSame(0, $second->getStatusCode());
        self::assertSame(3, (int) $db->fetchOne("SELECT COUNT(*) FROM journal_entry WHERE source LIKE 'payroll_%'"));
    }

    public function testDryRunWritesNothingAndCashOptionChangesTheSettlementKind(): void
    {
        $db = $this->connection();
        $db->beginTransaction();
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->insertRun($db, 'run-paid', 'PAID', '2026-10-01', '2026-10-30 10:00:00', '2026-10-31 10:00:00');
        $this->entry($db, 'run-paid', '1000.00', '0.00', '90.00', '130.00', '0.00', '0.00', '870.00');

        $dry = $this->execute(['--dry-run' => true, '--cash' => true]);

        self::assertStringContainsString('PAYROLL_PAYMENT_CASH', $dry->getDisplay());
        self::assertStringContainsString('Simulación', $dry->getDisplay());
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM journal_entry WHERE source LIKE 'payroll_%'"));
    }

    /** @param array<string, mixed> $options */
    private function execute(array $options): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $tester = new CommandTester($application->find('app:payroll:ledger-backfill'));
        $tester->execute($options);

        return $tester;
    }

    private function connection(): Connection
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $this->connection = $connection;
    }

    private function insertRun(
        Connection $db,
        string $id,
        string $status,
        string $period,
        ?string $approvedAt,
        ?string $paidAt,
    ): void {
        $db->insert('payroll_run', [
            'id' => $id,
            'company_id' => 'c1',
            'period' => $period,
            'type' => 'REGULAR',
            'status' => $status,
            'approved_at' => $approvedAt,
            'paid_at' => $paidAt,
            'created_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function entry(
        Connection $db,
        string $runId,
        string $gross,
        string $bonus,
        string $essalud,
        string $onp,
        string $afp,
        string $tax,
        string $net,
    ): void {
        $db->insert('payslip_entry', [
            'id' => $runId . '-e',
            'payroll_run_id' => $runId,
            'employee_id' => 'emp-1',
            'gross_salary' => $gross,
            'bonificacion_extraordinaria' => $bonus,
            'essalud_employer_contribution' => $essalud,
            'onp_amount' => $onp,
            'afp_mandatory_contribution' => $afp,
            'quinta_categoria_retention' => $tax,
            'net_pay' => $net,
            'created_at' => '2026-01-01 00:00:00',
        ]);
    }
}
