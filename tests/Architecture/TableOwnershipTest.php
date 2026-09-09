<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function in_array;
use function sprintf;

/**
 * NB-13 (#339), AR-005: every table in the live tenant schema is owned by
 * exactly one bounded context (architecture/contexts.yaml `ownsTables`) or is
 * declared external (`externalTables`). Catches a table with no owner, a table
 * claimed by two contexts, and a manifest entry with no matching table.
 *
 * The universe of tables is the schema produced by applying every migration in
 * migrations/ — read here from the connection the CI backend job migrates
 * before the test suite runs. Skipped when no tenant DB is reachable.
 */
final class TableOwnershipTest extends KernelTestCase
{
    public function testLiveSchemaMatchesTheOwnershipManifestExactly(): void
    {
        $live = $this->liveTables();
        $owners = $this->declaredOwners();

        $orphans = [];
        $shared = [];
        foreach ($live as $table) {
            $declared = $owners[$table] ?? [];
            if ($declared === []) {
                $orphans[] = $table;
            } elseif (count($declared) > 1) {
                $shared[] = sprintf('%s (%s)', $table, implode(' + ', $declared));
            }
        }

        $stale = [];
        foreach ($owners as $table => $declared) {
            if (in_array($table, $live, true)) {
                continue;
            }

            $stale[] = sprintf('%s (%s)', $table, implode(' + ', $declared));
        }

        sort($orphans);
        sort($shared);
        sort($stale);

        static::assertSame(
            [],
            $orphans,
            'tables with no owner — add each to a context ownsTables or to externalTables: ' . implode(', ', $orphans),
        );
        static::assertSame([], $shared, 'tables owned by more than one context: ' . implode(', ', $shared));
        static::assertSame(
            [],
            $stale,
            'ownsTables/externalTables entries with no live table — drop them: ' . implode(', ', $stale),
        );
    }

    /**
     * table name => list of owners (context names, or "external:<package>").
     *
     * @return array<string, list<string>>
     */
    private function declaredOwners(): array
    {
        $manifest = new ContextManifest();
        $owners = [];

        foreach ($manifest->contexts as $name => $context) {
            foreach ($context['ownsTables'] ?? [] as $table) {
                $owners[$table][] = $name;
            }
        }

        foreach ($manifest->externalTables as $table => $package) {
            $owners[$table][] = 'external:' . $package;
        }

        return $owners;
    }

    /**
     * Base table names in the tenant schema, lowercased.
     *
     * @return list<string>
     */
    private function liveTables(): array
    {
        self::bootKernel();

        $connection = static::getContainer()->get('doctrine.dbal.tenant_connection');
        static::assertInstanceOf(Connection::class, $connection);

        $sql =
            'SELECT table_name FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_type = :type';

        try {
            // information_schema, not listTableNames() (deprecated in DBAL 4) —
            // matches how the ControlPlane tenant tooling introspects a schema.
            $names = $connection->fetchFirstColumn($sql, ['type' => 'BASE TABLE']);
        } catch (DBALException $e) {
            static::markTestSkipped('no tenant database to inspect: ' . $e->getMessage());
        }

        $names = array_map(static fn(mixed $name): string => strtolower((string) $name), $names);
        sort($names);

        return $names;
    }
}
