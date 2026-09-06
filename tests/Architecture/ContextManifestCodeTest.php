<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function in_array;
use function sprintf;

/**
 * architecture/contexts.yaml (NB-2) cross-checked against the actual code, one
 * context per case: dependsOn must cover real references, ownsTables must cover
 * entity tables, and publicContracts must name real interfaces.
 *
 * @phpstan-import-type ContextEntry from ContextManifest
 */
final class ContextManifestCodeTest extends TestCase
{
    public function testEntityTablesAreOwnedByExactlyOneContext(): void
    {
        $manifest = new ContextManifest();
        $owner = [];
        $shared = [];

        foreach ($manifest->contexts as $name => $context) {
            foreach ($context['ownsTables'] ?? [] as $table) {
                $collision = isset($owner[$table]) ? ["{$table} ({$owner[$table]} + {$name})"] : [];
                $shared = [...$shared, ...$collision];
                $owner[$table] = $name;
            }
        }

        static::assertSame([], $shared, 'tables owned by two contexts: ' . implode(', ', $shared));
    }

    /**
     * @param ContextEntry $entry
     */
    #[DataProvider('contexts')]
    public function testDependsOnListsEveryContextReferencedInCode(string $name, array $entry): void
    {
        $missing = array_values(array_diff(new ContextManifest()->crossReferences($name), $entry['dependsOn'] ?? []));

        static::assertSame([], $missing, sprintf('%s dependsOn misses: %s', $name, implode(', ', $missing)));
    }

    /**
     * @param ContextEntry $entry
     */
    #[DataProvider('contexts')]
    public function testOwnsTablesCoversEntityBackedTables(string $name, array $entry): void
    {
        $missing = array_values(array_diff(new ContextManifest()->entityTables($name), $entry['ownsTables'] ?? []));

        static::assertSame(
            [],
            $missing,
            sprintf('%s ownsTables misses entity tables: %s', $name, implode(', ', $missing)),
        );
    }

    /**
     * @param ContextEntry $entry
     */
    #[DataProvider('contexts')]
    public function testPublicContractsNameRealInterfaces(string $name, array $entry): void
    {
        $declared = new ContextManifest()->declaredInterfaces($name);
        $missing = [];

        foreach ($entry['publicContracts'] ?? [] as $fqcn) {
            $parts = explode('\\', $fqcn);
            $known = in_array($parts[array_key_last($parts)], $declared, strict: true);
            $missing = [...$missing, ...($known ? [] : [$fqcn])];
        }

        static::assertSame(
            [],
            $missing,
            sprintf('%s publicContracts are not interfaces: %s', $name, implode(', ', $missing)),
        );
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
