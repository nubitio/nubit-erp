<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Entity;

/**
 * Represents a feature flag / configuration entry for a billing plan.
 *
 * Backed by the `plan_features` table in the control-plane database.
 * This is a plain value object hydrated from raw DBAL rows — the control-plane
 * connection has no dedicated ORM entity manager (see config/packages/doctrine.yaml).
 */
final class PlanFeature
{
    public function __construct(
        public readonly int $id,
        public readonly string $planName,
        public readonly string $featureKey,
        private bool $enabled,
        /** @var array<string, mixed>|null */
        private ?array $configJson,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
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
