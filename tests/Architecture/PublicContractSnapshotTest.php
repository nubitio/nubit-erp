<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * NB-21 (#347), AR-009: contract tests over the `publicContracts` declared in
 * architecture/contexts.yaml (NB-2). Every public contract must be an interface,
 * and its method surface — method names, parameter types/names/modifiers and
 * return types — is pinned to a committed snapshot. A backwards-incompatible
 * change to a contract another context depends on (renamed/removed method,
 * retyped or reordered parameter, narrowed return type) fails this suite until
 * the snapshot is regenerated with a reviewer looking at the diff.
 *
 * Regenerate after an intentional, coordinated contract change:
 *
 *   docker compose exec -e UPDATE_CONTRACT_SNAPSHOT=1 -e APP_ENV=test web \
 *     ./vendor/bin/pest tests/Architecture/PublicContractSnapshotTest.php
 *
 * @phpstan-import-type ContextEntry from ContextManifest
 */
final class PublicContractSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        PublicContractSnapshot::regenerateIfRequested(new ContextManifest());
    }

    public function testSnapshotFileIsPresentAndNonEmpty(): void
    {
        static::assertTrue(
            PublicContractSnapshot::exists(),
            'Missing ' . PublicContractSnapshot::path() . ' — generate it with UPDATE_CONTRACT_SNAPSHOT=1.',
        );
        static::assertNotSame([], PublicContractSnapshot::read(), 'snapshot is empty or not a JSON object');
    }

    /**
     * @param ContextEntry $entry
     */
    #[DataProvider('contexts')]
    public function testEveryPublicContractMatchesItsPinnedShape(string $name, array $entry): void
    {
        $contracts = $entry['publicContracts'] ?? [];
        if ($contracts === []) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $snapshot = PublicContractSnapshot::read();

        foreach ($contracts as $fqcn) {
            static::assertTrue(interface_exists($fqcn), "{$name}: public contract {$fqcn} is not an interface");
            static::assertArrayHasKey(
                $fqcn,
                $snapshot,
                "{$name}: {$fqcn} has no snapshot entry — regenerate with UPDATE_CONTRACT_SNAPSHOT=1",
            );
            static::assertSame(
                $snapshot[$fqcn],
                InterfaceSignature::describe($fqcn),
                "{$name}: the public contract {$fqcn} changed shape. If intentional, regenerate the "
                . 'snapshot with UPDATE_CONTRACT_SNAPSHOT=1 and update every consumer in the same PR.',
            );
        }
    }

    public function testSnapshotHasNoEntryForADroppedContract(): void
    {
        $declared = PublicContractSnapshot::declared(new ContextManifest());
        $stale = array_values(array_diff(array_keys(PublicContractSnapshot::read()), $declared));
        sort($stale);

        static::assertSame(
            [],
            $stale,
            'snapshot pins contracts no longer in contexts.yaml — regenerate with UPDATE_CONTRACT_SNAPSHOT=1: '
                . implode(', ', $stale),
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
