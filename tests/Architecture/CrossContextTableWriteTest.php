<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function in_array;
use function sprintf;

/**
 * NB-15 (#341), AR-005, gap G5: a bounded context must not issue raw-SQL writes
 * (INSERT / UPDATE / DELETE / REPLACE / TRUNCATE) against a table owned by
 * another context (`ownsTables` in architecture/contexts.yaml, inventoried by
 * NB-13). Cross-context *reads* (denormalised report models, dashboards) are
 * out of scope and not matched.
 *
 * This is the guard the epic's acceptance criterion asks for: CI fails if e.g.
 * a Restaurant vertical starts writing `inventory_*` rows directly instead of
 * going through Inventory's public contract.
 *
 * Two escape hatches, both intentionally small and enumerated here so widening
 * one is a visible, reviewed change:
 *
 *  - EXEMPT_CONTEXTS: cross-cutting tooling that legitimately rewrites the
 *    whole tenant schema (seeders, wipes, schema normalisation). These are not
 *    domain contexts and own no business invariants.
 *  - SANCTIONED: specific (context, table) domain bridges that predate this
 *    guard and are already accepted elsewhere (see ContextBoundaryTest and the
 *    Sales/CashRegister note in ADR-018) — Sales/Billing posting cash-drawer
 *    movements for a sale or its void. New entries need an ADR reference.
 *
 * @phpstan-import-type ContextEntry from ContextManifest
 */
final class CrossContextTableWriteTest extends TestCase
{
    /** @var list<string> */
    private const array EXEMPT_CONTEXTS = [
        'Setup', // tenant seeders, demo data, e2e fixtures, TenantWipeCommand
        'ControlPlane', // TenantSchemaNormalizeCommand and tenant provisioning tooling
    ];

    /** @var array<string, list<string>> context => tables it may write despite another owner */
    private const array SANCTIONED = [
        // Sales posts the CASH_IN drawer movement for a cash sale / receivable
        // collection through SalesWriteGateway raw SQL because src/Sales must
        // not import App\CashRegister (ContextBoundaryTest). ADR-018.
        'Sales' => ['cash_movements'],
        // Billing finalises the sale row it invoices and posts the cash refund
        // movement when a paid sale is voided (CashRefundRegistrar). ADR-018.
        'Billing' => ['sales', 'cash_movements'],
    ];

    /**
     * @param ContextEntry $entry
     */
    #[DataProvider('contexts')]
    public function testContextDoesNotWriteTablesOwnedByAnotherContext(string $name, array $entry): void
    {
        $manifest = new ContextManifest();
        $owners = self::tableOwners($manifest);
        $exempt = in_array($name, self::EXEMPT_CONTEXTS, strict: true);
        $sanctioned = self::SANCTIONED[$name] ?? [];

        $violations = [];
        foreach ($manifest->rawSqlWriteTargets($name) as $table) {
            $owner = $owners[$table] ?? null;
            if ($owner === null || $owner === $name) {
                continue;
            }

            if ($exempt || in_array($table, $sanctioned, strict: true)) {
                continue;
            }

            $violations[] = sprintf('%s writes %s (owned by %s)', $name, $table, $owner);
        }

        sort($violations);

        static::assertSame(
            [],
            $violations,
            "Cross-context raw-SQL writes — route through the owning context's public contract, "
                . 'or add an ADR-referenced entry to SANCTIONED: '
                . implode('; ', $violations),
        );
    }

    /**
     * The scanner really does see raw-SQL write verbs (guards against a regex
     * that quietly matches nothing, which would make the suite pass forever).
     */
    public function testScannerDetectsWriteTargetsInARealContext(): void
    {
        // SalesWriteGateway does `UPDATE sales ...` and `INSERT INTO cash_movements ...`.
        $targets = new ContextManifest()->rawSqlWriteTargets('Sales');

        static::assertContains('sales', $targets, 'UPDATE <table> must be picked up');
        static::assertContains('cash_movements', $targets, 'INSERT INTO <table> must be picked up');
    }

    /**
     * table name => owning context, from every context's `ownsTables` (trailing
     * `# dead` / `# legacy` comments stripped). External tables are excluded —
     * they have no src/ context to cross.
     *
     * @return array<string, string>
     */
    private static function tableOwners(ContextManifest $manifest): array
    {
        $owners = [];
        foreach ($manifest->contexts as $name => $context) {
            foreach ($context['ownsTables'] ?? [] as $table) {
                $owners[trim((string) preg_replace('/#.*$/', replacement: '', subject: $table))] = $name;
            }
        }

        unset($owners['']);

        return $owners;
    }

    /**
     * @return iterable<string, array{string, ContextEntry}>
     */
    public static function contexts(): iterable
    {
        foreach (new ContextManifest()->contexts as $name => $entry) {
            yield $name => [$name, $entry];
        }
    }
}
