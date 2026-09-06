<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use App\Settings\Repository\TenantProfileRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Singleton per-tenant onboarding profile (NB-5): which vertical preset was
 * applied, at which version, and which settings fields the tenant has since
 * pinned so a re-apply must not overwrite them.
 */
#[ORM\Entity(repositoryClass: TenantProfileRepository::class)]
#[ORM\Table(name: 'tenant_profile')]
class TenantProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $verticalKey = null;

    #[ORM\Column(nullable: true)]
    private ?int $presetVersion = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $appliedAt = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $lockedFields = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVerticalKey(): ?string
    {
        return $this->verticalKey;
    }

    public function getPresetVersion(): ?int
    {
        return $this->presetVersion;
    }

    public function getAppliedAt(): ?DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function recordApplied(string $verticalKey, int $presetVersion): self
    {
        $this->verticalKey = $verticalKey;
        $this->presetVersion = $presetVersion;
        $this->appliedAt = new DateTimeImmutable();

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getLockedFields(): array
    {
        return $this->lockedFields;
    }

    /**
     * @param list<string> $lockedFields
     */
    public function setLockedFields(array $lockedFields): self
    {
        $this->lockedFields = array_values(array_unique($lockedFields));

        return $this;
    }
}
