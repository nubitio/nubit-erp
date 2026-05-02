<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Inventory\Core\Enum\StockPolicy;
use App\Inventory\Repository\TenantInventoryPolicyRepository;
use App\Inventory\State\TenantInventoryPolicyProcessor;
use App\Inventory\State\TenantInventoryPolicyProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-tenant stock validation policy (STRICT / WARNING_ONLY / ALLOW_NEGATIVE).
 *
 * Exposed as a singleton-style resource: GET returns the current policy,
 * POST upserts it.  Both operations are restricted to ROLE_ADMIN.
 */
#[ORM\Entity(repositoryClass: TenantInventoryPolicyRepository::class)]
#[ORM\Table(name: 'tenant_inventory_policy')]
#[ORM\UniqueConstraint(name: 'uq_tenant_inventory_policy_tenant', columns: ['tenant_id'])]
#[ApiResource(
    shortName: 'InventoryPolicy',
    operations: [
        new Get(
            uriTemplate: '/inventory-policy',
            provider: TenantInventoryPolicyProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/inventory-policy',
            processor: TenantInventoryPolicyProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    normalizationContext:   ['groups' => ['inventory_policy:read']],
    denormalizationContext: ['groups' => ['inventory_policy:write']],
)]
class TenantInventoryPolicy
{
    #[Groups(['inventory_policy:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[Groups(['inventory_policy:read'])]
    #[ORM\Column(name: 'tenant_id', length: 128)]
    private string $tenantId;

    #[Groups(['inventory_policy:read', 'inventory_policy:write'])]
    #[Assert\NotNull]
    #[ORM\Column(enumType: StockPolicy::class)]
    private StockPolicy $policy = StockPolicy::STRICT;

    #[Groups(['inventory_policy:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[Groups(['inventory_policy:read'])]
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
