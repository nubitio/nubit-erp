<?php

declare(strict_types=1);

namespace App\Settings\Capability;

/**
 * Metadata for one operational capability (NB-4). Declarative only — the
 * fail-closed resolution logic lands with NB-6 (`TenantCapabilityResolver`).
 */
final readonly class CapabilityDefinition
{
    /**
     * @param non-empty-string $label
     * @param non-empty-string $ownerContext  bounded context that owns the behaviour,
     *                                         matching a key in architecture/contexts.yaml
     * @param string|null $requiresEntitlement commercial feature key that must be granted
     *                                         first, matching FeatureCatalog; null = free
     * @param list<CapabilityKey> $dependsOn   capabilities that must also be enabled
     * @param bool $defaultEnabled             on for a tenant with no explicit row and,
     *                                         where applicable, the entitlement granted
     * @param list<non-empty-string> $configKeys allowed keys in the tenant config payload
     * @param array<non-empty-string, scalar|null> $safeDefaults default payload when enabled
     */
    public function __construct(
        public CapabilityKey $key,
        public string $label,
        public string $ownerContext,
        public ?string $requiresEntitlement = null,
        public array $dependsOn = [],
        public bool $defaultEnabled = false,
        public array $configKeys = [],
        public array $safeDefaults = [],
    ) {}
}
