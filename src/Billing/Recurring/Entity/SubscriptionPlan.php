<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Billing\Recurring\Enum\BillingCycle;
use App\Billing\Recurring\Repository\SubscriptionPlanRepository;
use App\Catalog\Entity\Product;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
#[ORM\Table(name: 'subscription_plan')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('perm:subscriptions.view')"),
        new Post(security: "is_granted('perm:subscriptions.create')"),
        new Get(security: "is_granted('perm:subscriptions.view')"),
        new Patch(security: "is_granted('perm:subscriptions.edit')"),
        new Delete(security: "is_granted('perm:subscriptions.cancel')"),
    ],
    normalizationContext: ['groups' => ['subscription_plan:read']],
    denormalizationContext: ['groups' => ['subscription_plan:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class SubscriptionPlan
{
    #[Groups(['subscription_plan:read', 'customer_subscription:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Assert\NotBlank]
    #[Groups(['subscription_plan:read', 'subscription_plan:write', 'customer_subscription:read'])]
    #[ApiProperty(description: 'subscription_plan.name.label')]
    #[ORM\Column(name: 'name', length: 255)]
    private string $name;

    #[Assert\NotNull]
    #[Groups(['subscription_plan:read', 'subscription_plan:write', 'customer_subscription:read'])]
    #[ApiProperty(description: 'subscription_plan.product.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: false)]
    private Product $product;

    #[Groups(['subscription_plan:read', 'subscription_plan:write'])]
    #[ApiProperty(description: 'subscription_plan.billing_cycle.label')]
    #[ORM\Column(name: 'billing_cycle', length: 10, options: ['default' => 'MONTHLY'])]
    private string $billingCycle = BillingCycle::MONTHLY->value;

    #[Assert\NotBlank]
    #[Groups(['subscription_plan:read', 'subscription_plan:write'])]
    #[ApiProperty(description: 'subscription_plan.amount.label')]
    #[ORM\Column(name: 'amount', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $amount = '0.00';

    #[Groups(['subscription_plan:read', 'subscription_plan:write'])]
    #[ApiProperty(description: 'subscription_plan.payment_term_days.label')]
    #[ORM\Column(name: 'payment_term_days', type: Types::INTEGER, nullable: true)]
    private ?int $paymentTermDays = null;

    #[Groups(['subscription_plan:read', 'subscription_plan:write'])]
    #[ApiProperty(description: 'subscription_plan.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    /**
     * Branch/warehouse that generated sales are attributed to — required
     * because every row in `sales` needs a branch and warehouse (POS legacy
     * schema constraint). Also used for series lookup when auto-invoicing.
     */
    #[Assert\NotNull]
    #[Groups(['subscription_plan:read', 'subscription_plan:write'])]
    #[ApiProperty(description: 'subscription_plan.branch.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: false)]
    private Branch $branch;

    #[Assert\NotNull]
    #[Groups(['subscription_plan:read', 'subscription_plan:write'])]
    #[ApiProperty(description: 'subscription_plan.warehouse.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: false)]
    private Warehouse $warehouse;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getBillingCycle(): string
    {
        return $this->billingCycle;
    }

    public function getBillingCycleEnum(): BillingCycle
    {
        return BillingCycle::from($this->billingCycle);
    }

    public function setBillingCycle(BillingCycle|string $billingCycle): static
    {
        $this->billingCycle = $billingCycle instanceof BillingCycle
            ? $billingCycle->value
            : BillingCycle::from($billingCycle)->value;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPaymentTermDays(): ?int
    {
        return $this->paymentTermDays;
    }

    public function setPaymentTermDays(?int $paymentTermDays): static
    {
        $this->paymentTermDays = $paymentTermDays;

        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getBranch(): Branch
    {
        return $this->branch;
    }

    public function setBranch(Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;

        return $this;
    }
}
