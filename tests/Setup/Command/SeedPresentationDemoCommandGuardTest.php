<?php

declare(strict_types=1);

namespace App\Tests\Setup\Command;

use App\Setup\Command\SeedPresentationDemoCommand;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Guard-only integration test for SeedPresentationDemoCommand (#315):
 * the command must refuse to seed presentation data into a tenant that
 * already has real operational data, in ANY of the modules it touches —
 * not just products/sales. Before this change, a tenant with only
 * purchases or payroll loaded (no products/sales yet) passed the
 * pre-flight check and got demo data mixed in.
 *
 * Each test asserts that the warning names the specific data source it
 * inserted (e.g. "compras", not "productos"/"ventas"), so a test can't pass
 * "by accident" via the pre-existing producto/sales guard instead of the new
 * one it's meant to exercise. This is checked via message content rather
 * than asserting producto/sales counts are 0: phpunit.coverage.xml (used by
 * CI) doesn't roll back between tests, so other suites' fixture data
 * (e.g. InventorySummaryServiceTest) can leave real rows in `producto`
 * when this test runs as part of the full suite — the message check is
 * unaffected by that pre-existing cross-test leakage.
 *
 * Only exercises the guard branch (Command::FAILURE, no side effects) —
 * the full happy-path seed is exercised by PresentationDemoSeeder's own
 * tests, not duplicated here.
 */
uses(KernelTestCase::class);

function markTenantAsConfigured(Connection $connection): void
{
    $suffix = random_int(100_000, 999_999);
    $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

    $connection->executeStatement(
        "INSERT INTO empresa (empresa_id, ruc, razon_social, domicilio_fiscal) VALUES (:id, '20000000001', 'Empresa Demo Guard SAC', 'Av. Demo 123')",
        ['id' => 'empresa-guard-' . $suffix],
    );
    $connection->executeStatement("INSERT INTO local (local_id, empresa_id, descripcion) VALUES (:id, :empresaId, 'Sucursal Demo Guard')", [
        'id' => 'local-guard-' . $suffix,
        'empresaId' => 'empresa-guard-' . $suffix,
    ]);
    $connection->executeStatement('INSERT INTO usuario (rol_id, username, password) VALUES (2, :username, :password)', [
        'username' => 'demo-guard-' . $suffix,
        'password' => bin2hex(random_bytes(4)),
    ]);
}

function runDemoSeedGuard(SeedPresentationDemoCommand $command, BufferedOutput $output): int
{
    $ref = new \ReflectionMethod($command, 'executeTenantCommand');

    return $ref->invoke($command, new ArrayInput([]), $output);
}

beforeEach(function (): void {
    self::bootKernel();
    $this->connection = static::getContainer()->get('doctrine.dbal.tenant_connection');
    $this->command = static::getContainer()->get(SeedPresentationDemoCommand::class);
});

it('blocks seeding when the tenant already has purchases (goods_receipts) but no products or sales', function (): void {
    markTenantAsConfigured($this->connection);

    $this->connection->executeStatement("INSERT INTO goods_receipts (id, branch_id, warehouse_id, buyer_user_id, issued_at)
         VALUES (:id, 'branch-x', 'warehouse-x', 1, NOW())", [
        'id' => bin2hex(random_bytes(16)),
    ]);

    $output = new BufferedOutput();
    $exitCode = runDemoSeedGuard($this->command, $output);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output->fetch())->toContain('compras');
});

it('blocks seeding when the tenant already has payroll runs', function (): void {
    markTenantAsConfigured($this->connection);

    $this->connection->executeStatement("INSERT INTO payroll_run (id, company_id, period, created_at) VALUES (:id, 'company-x', '2026-01-01', NOW())", [
        'id' => bin2hex(random_bytes(16)),
    ]);

    $output = new BufferedOutput();
    $exitCode = runDemoSeedGuard($this->command, $output);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output->fetch())->toContain('planilla');
});

it('blocks seeding when the tenant already has accounting journal entries', function (): void {
    markTenantAsConfigured($this->connection);

    $suffix = random_int(100_000, 999_999);
    $this->connection->executeStatement(
        "INSERT INTO journal_entry (id, entry_no, source, source_id, entry_date, description, created_at)
         VALUES (:id, :entryNo, 'manual', :sourceId, '2026-01-01', 'Asiento de prueba', NOW())",
        ['id' => bin2hex(random_bytes(16)), 'entryNo' => 'JE-TEST-' . $suffix, 'sourceId' => 'src-' . $suffix],
    );

    $output = new BufferedOutput();
    $exitCode = runDemoSeedGuard($this->command, $output);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output->fetch())->toContain('asientos contables');
});
