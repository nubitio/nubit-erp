<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Billing\Recurring\Repository\CustomerSubscriptionLineRepository;
use App\Catalog\Entity\Product;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerSubscriptionLineRepository::class)]
#[ORM\Table(name: 'customer_subscription_line')]
#[ORM\Index(name: 'idx_subscription_line_subscription_sort', columns: ['subscription_id', 'sort_order'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('perm:subscriptions.view')"),
        new GetCollection(
            uriTemplate: '/customer-subscriptions/{id}/lines',
            uriVariables: [
                'id' => new Link(fromProperty: 'lines', fromClass: CustomerSubscription::class),
            ],
            normalizationContext: ['groups' => ['customer_subscription_line:read']],
            paginationEnabled: false,
            security: "is_granted('perm:subscriptions.view')",
        ),
        new Post(security: "is_granted('perm:subscriptions.edit')"),
        new Get(security: "is_granted('perm:subscriptions.view')"),
        new Patch(security: "is_granted('perm:subscriptions.edit')"),
        new Delete(security: "is_granted('perm:subscriptions.edit')"),
    ],
    normalizationContext: ['groups' => ['customer_subscription_line:read']],
    denormalizationContext: ['groups' => ['customer_subscription_line:write']],
    filters: [DataGridFilter::class],
)]
class CustomerSubscriptionLine
{
    #[Groups(['customer_subscription_line:read', 'customer_subscription:write'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['customer_subscription_line:read', 'customer_subscription_line:write'])]
    #[ApiProperty(readableLink: true)]
    #[ORM\ManyToOne(targetEntity: CustomerSubscription::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'subscription_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private CustomerSubscription $subscription;

    #[Groups(['customer_subscription_line:read', 'customer_subscription_line:write', 'customer_subscription:write'])]
    #[ApiProperty(readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: false)]
    private Product $product;

    #[Groups(['customer_subscription_line:read', 'customer_subscription_line:write', 'customer_subscription:write'])]
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'description', length: 255, nullable: true)]
    private ?string $description = null;

    #[Groups(['customer_subscription_line:read', 'customer_subscription_line:write', 'customer_subscription:write'])]
    #[Assert\Positive]
    #[ORM\Column(name: 'quantity', type: Types::DECIMAL, precision: 12, scale: 3)]
    private string $quantity = '1.000';

    #[Groups(['customer_subscription_line:read', 'customer_subscription_line:write', 'customer_subscription:write'])]
    #[Assert\PositiveOrZero]
    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $unitPrice = '0.0000';

    #[Groups(['customer_subscription_line:read', 'customer_subscription_line:write', 'customer_subscription:write'])]
    #[ORM\Column(name: 'sort_order', type: Types::INTEGER, options: ['default' => 0])]
    private int $sortOrder = 0;

    public function getId(): ?string { return $this->id; }
    public function getSubscription(): CustomerSubscription { return $this->subscription; }
    public function setSubscription(CustomerSubscription $subscription): static { $this->subscription = $subscription; return $this; }
    public function getProduct(): Product { return $this->product; }
    public function setProduct(Product $product): static { $this->product = $product; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description === null || trim($description) === '' ? null : trim($description); return $this; }
    public function getQuantity(): string { return $this->quantity; }
    public function setQuantity(string $quantity): static { $this->quantity = $quantity; return $this; }
    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }
}
