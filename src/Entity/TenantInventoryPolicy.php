<?php

declare(strict_types=1);

namespace App\Entity;

use App\Inventory\Core\Enum\StockPolicy;
use App\Repository\TenantInventoryPolicyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TenantInventoryPolicyRepository::class)]
#[ORM\Table(name: 'tenant_inventory_policy')]
#[ORM\UniqueConstraint(name: 'uq_tenant_inventory_policy_tenant', columns: ['tenant_id'])]
class TenantInventoryPolicy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'tenant_id', length: 128)]
    private string $tenantId;

    #[ORM\Column(enumType: StockPolicy::class)]
    private StockPolicy $policy = StockPolicy::STRICT;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): static
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function getPolicy(): StockPolicy
    {
        return $this->policy;
    }

    public function setPolicy(StockPolicy $policy): static
    {
        $this->policy = $policy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
