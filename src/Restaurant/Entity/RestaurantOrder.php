<?php

declare(strict_types=1);

namespace App\Restaurant\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Restaurant\Repository\RestaurantOrderRepository;
use App\Restaurant\State\CloseOrderInput;
use App\Restaurant\State\CloseOrderProcessor;
use App\Restaurant\State\OpenTableInput;
use App\Restaurant\State\OpenTableProcessor;
use App\Restaurant\State\RequestBillInput;
use App\Restaurant\State\RequestBillProcessor;
use App\Restaurant\State\SendToKitchenInput;
use App\Restaurant\State\SendToKitchenProcessor;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A comanda: the accumulating order for one table's dining session (NB-18).
 * Stays open while the table is in use; only at `close()` does it hand off
 * to Sales — done entirely from the frontend against the same
 * `POST /api/sales` the web POS already uses, so this context never
 * references Sales in PHP (`saleId` here is an opaque string, no FK).
 */
#[ORM\Entity(repositoryClass: RestaurantOrderRepository::class)]
#[ORM\Table(name: 'restaurant_orders')]
#[ORM\Index(name: 'idx_restaurant_order_table', columns: ['table_id'])]
#[ApiResource(
    shortName: 'RestaurantOrder',
    operations: [
        new GetCollection(uriTemplate: '/restaurant/orders', security: "is_granted('perm:restaurant.view')"),
        new Get(uriTemplate: '/restaurant/orders/{id}', security: "is_granted('perm:restaurant.view')"),
        new Post(
            uriTemplate: '/restaurant/orders',
            input: OpenTableInput::class,
            processor: OpenTableProcessor::class,
            security: "is_granted('perm:restaurant.operate')",
        ),
        new Post(
            uriTemplate: '/restaurant/orders/{id}/send-to-kitchen',
            input: SendToKitchenInput::class,
            processor: SendToKitchenProcessor::class,
            security: "is_granted('perm:restaurant.operate')",
        ),
        new Post(
            uriTemplate: '/restaurant/orders/{id}/request-bill',
            input: RequestBillInput::class,
            processor: RequestBillProcessor::class,
            security: "is_granted('perm:restaurant.operate')",
        ),
        new Post(
            uriTemplate: '/restaurant/orders/{id}/close',
            input: CloseOrderInput::class,
            processor: CloseOrderProcessor::class,
            security: "is_granted('perm:restaurant.operate')",
        ),
    ],
    normalizationContext: ['groups' => ['restaurant_order:read']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class RestaurantOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['restaurant_order:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['restaurant_order:read'])]
    private int $tableId;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['restaurant_order:read'])]
    private DateTimeImmutable $openedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['restaurant_order:read'])]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['restaurant_order:read'])]
    private ?string $saleId = null;

    /** @var Collection<int, RestaurantOrderItem> */
    #[ORM\OneToMany(
        mappedBy: 'order',
        targetEntity: RestaurantOrderItem::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[Groups(['restaurant_order:read'])]
    private Collection $items;

    public function __construct(int $tableId)
    {
        $this->tableId = $tableId;
        $this->openedAt = new DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTableId(): int
    {
        return $this->tableId;
    }

    public function getOpenedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function isOpen(): bool
    {
        return $this->closedAt === null;
    }

    public function getSaleId(): ?string
    {
        return $this->saleId;
    }

    /**
     * @return Collection<int, RestaurantOrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(RestaurantOrderItem $item): self
    {
        $this->items->add($item);

        return $this;
    }

    public function sendPendingItemsToKitchen(): self
    {
        foreach ($this->items as $item) {
            $item->markSent();
        }

        return $this;
    }

    public function close(string $saleId): self
    {
        $this->saleId = $saleId;
        $this->closedAt = new DateTimeImmutable();

        return $this;
    }
}
