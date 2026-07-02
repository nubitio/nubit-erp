<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Entity;

/**
 * Represents a per-tenant override for a feature flag / configuration.
 *
 * Backed by the `tenant_feature_overrides` table in the control-plane database.
 * This is a plain value object hydrated from raw DBAL rows — the control-plane
 * connection has no dedicated ORM entity manager (see config/packages/doctrine.yaml).
 */
final class TenantFeatureOverride
{
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly string $featureKey,
        private bool $enabled,
        /** @var array<string, mixed>|null */
        private ?array $configJson,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly ?string $reason,
        public readonly ?\DateTimeImmutable $cleanedAt,
        public readonly \DateTimeImmutable $createdAt,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Returns true when the override has not yet expired (or has no expiry).
     */
    public function isActive(): bool
    {
        return $this->expiresAt === null || $this->expiresAt > new \DateTimeImmutable();
    }

    /**
     * Returns true when the override has passed its expiry date.
     */
    public function isExpired(): bool
    {
        return !$this->isActive();
    }

    /**
     * Read a value from the JSON config blob.
     *
     * @param mixed $default Returned when the key is absent or config is null.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->configJson[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfigJson(): ?array
    {
        return $this->configJson;
    }
}
