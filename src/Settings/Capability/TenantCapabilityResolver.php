<?php

declare(strict_types=1);

namespace App\Settings\Capability;

use App\Settings\Entity\TenantCapability;
use App\Settings\Repository\TenantCapabilityRepository;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;

use function array_flip;
use function array_intersect_key;

/**
 * Single, fail-closed resolution of a tenant's operational capabilities (NB-6).
 *
 * A capability is enabled only when: it exists in {@see CapabilityCatalog}; its
 * required commercial entitlement (if any) is granted; every capability it
 * depends on is also enabled; and either the tenant has an explicit row saying
 * so or the catalogue marks it `defaultEnabled`. Unknown keys and unmet
 * dependencies resolve to "off", never an error — except {@see require()}, which
 * throws.
 */
final class TenantCapabilityResolver
{
    /** @var array<string, TenantCapability>|null */
    private ?array $rows = null;

    /** @var array<string, array{enabled: bool, config: array<string, mixed>}>|null */
    private ?array $entitlements = null;

    /** @var array<string, bool> */
    private array $enabledMemo = [];

    public function __construct(
        private readonly FeatureCheckerInterface $featureChecker,
        private readonly TenantCapabilityRepository $capabilityRepository,
    ) {}

    public function isEnabled(CapabilityKey|string $key): bool
    {
        $value = $key instanceof CapabilityKey ? $key->value : $key;

        return $this->enabledMemo[$value] ??= $this->resolve($value);
    }

    /**
     * The effective config payload for an enabled capability, restricted to the
     * keys the catalogue declares. Empty for a disabled or unknown capability.
     *
     * @return array<string, mixed>
     */
    public function config(CapabilityKey|string $key): array
    {
        $value = $key instanceof CapabilityKey ? $key->value : $key;
        $definition = CapabilityCatalog::get($value);
        if ($definition === null || !$this->isEnabled($value)) {
            return [];
        }

        $raw = $this->row($value)?->getConfig() ?? $definition->safeDefaults;

        return array_intersect_key($raw, array_flip($definition->configKeys));
    }

    public function require(CapabilityKey|string $key): void
    {
        $value = $key instanceof CapabilityKey ? $key->value : $key;
        if (!$this->isEnabled($value)) {
            throw new CapabilityNotAvailableException($value);
        }
    }

    /**
     * Every catalogue capability with its resolved state, for the runtime config.
     *
     * @return array<string, array{enabled: bool, config: array<string, mixed>}>
     */
    public function all(): array
    {
        $out = [];
        foreach (CapabilityCatalog::keys() as $key) {
            $enabled = $this->isEnabled($key);
            $out[$key] = ['enabled' => $enabled, 'config' => $enabled ? $this->config($key) : []];
        }

        return $out;
    }

    private function resolve(string $key): bool
    {
        $definition = CapabilityCatalog::get($key);
        if ($definition === null) {
            return false;
        }

        if ($definition->requiresEntitlement !== null && !$this->entitlementGranted($definition->requiresEntitlement)) {
            return false;
        }

        foreach ($definition->dependsOn as $dependency) {
            if (!$this->isEnabled($dependency)) {
                return false;
            }
        }

        $row = $this->row($key);
        if ($row !== null) {
            return $row->isEnabled();
        }

        // No explicit row: an entitlement-gated capability follows its entitlement
        // (already confirmed granted above); a free one follows the catalogue default.
        return $definition->requiresEntitlement !== null || $definition->defaultEnabled;
    }

    private function entitlementGranted(string $featureKey): bool
    {
        $this->entitlements ??= $this->featureChecker->getEntitlements();

        return $this->entitlements[$featureKey]['enabled'] ?? false;
    }

    private function row(string $key): ?TenantCapability
    {
        $this->rows ??= $this->capabilityRepository->allByKey();

        return $this->rows[$key] ?? null;
    }
}
