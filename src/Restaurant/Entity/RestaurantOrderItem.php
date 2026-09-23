<?php

declare(strict_types=1);

namespace App\Restaurant\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Restaurant\Enum\RestaurantOrderItemStatus;
use App\Restaurant\Repository\RestaurantOrderItemRepository;
use App\Restaurant\State\AddOrderItemInput;
use App\Restaurant\State\AddOrderItemProcessor;
use App\Restaurant\State\MarkOrderItemDeliveredInput;
use App\Restaurant\State\MarkOrderItemDeliveredProcessor;
use App\Restaurant\State\MarkOrderItemReadyInput;
use App\Restaurant\State\MarkOrderItemReadyProcessor;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One comanda line (NB-18). Snapshots the product's name/price at the time
 * it was added — a menu price change later must not alter a comanda already
 * in progress. Linked to `Catalog\Product` by id only (composition, no FK),
 * same pattern as `Pharmacy\Entity\PharmacyProduct`.
 */
#[ORM\Entity(repositoryClass: RestaurantOrderItemRepository::class)]
#[ORM\Table(name: 'restaurant_order_items')]
#[ORM\Index(name: 'idx_restaurant_order_item_order', columns: ['order_id'])]
#[ApiResource(
    shortName: 'RestaurantOrderItem',
    operations: [
        new Post(
            uriTemplate: '/restaurant/order-items',
            input: AddOrderItemInput::class,
            processor: AddOrderItemProcessor::class,
            security: "is_granted('perm:restaurant.operate')",
        ),
        new Post(
            uriTemplate: '/restaurant/order-items/{id}/ready',
            input: MarkOrderItemReadyInput::class,
            processor: MarkOrderItemReadyProcessor::class,
            security: "is_granted('perm:restaurant.operate')",
        ),
        new Post(
            uriTemplate: '/restaurant/order-items/{id}/delivered',
            input: MarkOrderItemDeliveredInput::class,
            processor: MarkOrderItemDeliveredProcessor::class,
            security: "is_granted('perm:restaurant.operate')",
        ),
    ],
    normalizationContext: ['groups' => ['restaurant_order_item:read']],
)]
class RestaurantOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RestaurantOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false)]
    private RestaurantOrder $order;

    #[ORM\Column(length: 64)]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private string $productId;

    #[ORM\Column(length: 255)]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private string $productName;

    #[ORM\Column(length: 20)]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private string $unitPrice;

    #[ORM\Column]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private int $qty;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private ?string $notes = null;

    #[ORM\Column(enumType: RestaurantOrderItemStatus::class, length: 20)]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private RestaurantOrderItemStatus $status = RestaurantOrderItemStatus::Pending;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private ?DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private ?DateTimeImmutable $readyAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['restaurant_order_item:read', 'restaurant_order:read'])]
    private ?DateTimeImmutable $deliveredAt = null;

    public function __construct(
        RestaurantOrder $order,
        string $productId,
        string $productName,
        string $unitPrice,
        int $qty,
        ?string $notes = null,
    ) {
        $this->order = $order;
        $this->productId = $productId;
        $this->productName = $productName;
        $this->unitPrice = $unitPrice;
        $this->qty = $qty;
        $this->notes = $notes;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): RestaurantOrder
    {
        return $this->order;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function getQty(): int
    {
        return $this->qty;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getStatus(): RestaurantOrderItemStatus
    {
        return $this->status;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getReadyAt(): ?DateTimeImmutable
    {
        return $this->readyAt;
    }

    public function getDeliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function markSent(): self
    {
        if ($this->status === RestaurantOrderItemStatus::Pending) {
            $this->status = RestaurantOrderItemStatus::Sent;
            $this->sentAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function markReady(): self
    {
        $this->status = RestaurantOrderItemStatus::Ready;
        $this->readyAt = new DateTimeImmutable();

        return $this;
    }

    public function markDelivered(): self
    {
        $this->status = RestaurantOrderItemStatus::Delivered;
        $this->deliveredAt = new DateTimeImmutable();

        return $this;
    }
}
