<?php

declare(strict_types=1);

namespace App\Tests\Settings\Capability;

use App\ControlPlane\Billing\Feature\FeatureCatalog;
use App\Settings\Capability\CapabilityCatalog;
use App\Settings\Capability\CapabilityDefinition;
use App\Settings\Capability\CapabilityKey;
use App\Tests\Architecture\ContextManifest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function in_array;
use function sprintf;

/**
 * Integrity of the operational capability catalogue (NB-4): every enum case is
 * defined, dependencies resolve without cycles, entitlement keys and owner
 * contexts are real.
 */
final class CapabilityCatalogTest extends TestCase
{
    public function testEnumCasesAndCatalogEntriesMatchOneToOne(): void
    {
        $cases = array_map(static fn(CapabilityKey $k): string => $k->value, CapabilityKey::cases());
        $entries = CapabilityCatalog::keys();
        sort($cases);
        sort($entries);

        static::assertSame($cases, $entries, 'CapabilityKey cases and CapabilityCatalog entries diverge.');
    }

    public function testDependencyGraphHasNoCycles(): void
    {
        $graph = [];
        foreach (CapabilityCatalog::all() as $key => $definition) {
            $graph[$key] = array_map(static fn(CapabilityKey $k): string => $k->value, $definition->dependsOn);
        }

        $cyclic = [];
        foreach (array_keys($graph) as $start) {
            if ($this->reaches($graph, $start, $start)) {
                $cyclic[] = $start;
            }
        }

        static::assertSame([], $cyclic, 'capabilities on a dependency cycle: ' . implode(', ', $cyclic));
    }

    /**
     * @param CapabilityDefinition $definition
     */
    #[DataProvider('capabilities')]
    public function testDependenciesResolveToKnownCapabilities(string $key, CapabilityDefinition $definition): void
    {
        $unknown = array_filter(
            $definition->dependsOn,
            static fn(CapabilityKey $dep): bool => !CapabilityCatalog::has($dep->value),
        );

        static::assertSame([], array_values($unknown), sprintf('%s depends on an undefined capability.', $key));
    }

    /**
     * @param CapabilityDefinition $definition
     */
    #[DataProvider('capabilities')]
    public function testRequiredEntitlementIsARealFeature(string $key, CapabilityDefinition $definition): void
    {
        $entitlement = $definition->requiresEntitlement;
        $known = $entitlement === null || in_array($entitlement, FeatureCatalog::keys(), strict: true);

        static::assertTrue($known, sprintf('%s requires unknown entitlement "%s".', $key, (string) $entitlement));
    }

    /**
     * @param CapabilityDefinition $definition
     */
    #[DataProvider('capabilities')]
    public function testOwnerContextExistsInTheManifest(string $key, CapabilityDefinition $definition): void
    {
        static::assertArrayHasKey(
            $definition->ownerContext,
            new ContextManifest()->contexts,
            sprintf('%s is owned by unknown context "%s".', $key, $definition->ownerContext),
        );
    }

    /**
     * @param CapabilityDefinition $definition
     */
    #[DataProvider('capabilities')]
    public function testSafeDefaultsStayWithinDeclaredConfigKeys(string $key, CapabilityDefinition $definition): void
    {
        $stray = array_diff(array_keys($definition->safeDefaults), $definition->configKeys);

        static::assertSame(
            [],
            array_values($stray),
            sprintf('%s has safeDefaults outside configKeys: %s', $key, implode(', ', $stray)),
        );
    }

    /**
     * @return iterable<string, array{string, CapabilityDefinition}>
     */
    public static function capabilities(): iterable
    {
        foreach (CapabilityCatalog::all() as $key => $definition) {
            yield $key => [$key, $definition];
        }
    }

    /**
     * @param array<string, list<string>> $graph
     */
    private function reaches(array $graph, string $from, string $target): bool
    {
        $seen = [];
        $stack = $graph[$from] ?? [];

        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node === $target) {
                return true;
            }
            if (in_array($node, $seen, strict: true)) {
                continue;
            }
            $seen[] = $node;
            $stack = [...$stack, ...($graph[$node] ?? [])];
        }

        return false;
    }
}
