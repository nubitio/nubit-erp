<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use App\Settings\Enum\CapabilitySource;
use App\Settings\Repository\TenantCapabilityRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant-local operational state for one capability (NB-5). One row per
 * capability key; the resolver (NB-6) merges these over the catalogue defaults
 * and the applied preset.
 */
#[ORM\Entity(repositoryClass: TenantCapabilityRepository::class)]
#[ORM\Table(name: 'tenant_capabilities')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_capability_key', columns: ['capability_key'])]
class TenantCapability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $capabilityKey;

    #[ORM\Column]
    private bool $enabled = false;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $config = null;

    #[ORM\Column(enumType: CapabilitySource::class, length: 20)]
    private CapabilitySource $source = CapabilitySource::Manual;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $presetKey = null;

    #[ORM\Column(nullable: true)]
    private ?int $presetVersion = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $capabilityKey)
    {
        $this->capabilityKey = $capabilityKey;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCapabilityKey(): string
    {
        return $this->capabilityKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->touch();

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function setConfig(?array $config): self
    {
        $this->config = $config;
        $this->touch();

        return $this;
    }

    public function getSource(): CapabilitySource
    {
        return $this->source;
    }

    public function setSource(CapabilitySource $source): self
    {
        $this->source = $source;
        $this->touch();

        return $this;
    }

    public function getPresetKey(): ?string
    {
        return $this->presetKey;
    }

    public function getPresetVersion(): ?int
    {
        return $this->presetVersion;
    }

    public function setPreset(?string $key, ?int $version): self
    {
        $this->presetKey = $key;
        $this->presetVersion = $version;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
