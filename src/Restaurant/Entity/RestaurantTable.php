<?php

declare(strict_types=1);

namespace App\Restaurant\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Restaurant\Enum\RestaurantTableStatus;
use App\Restaurant\Repository\RestaurantTableRepository;
use App\Restaurant\State\DeleteRestaurantTableProcessor;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A dining table on the floor (NB-18). Composition, not a shared "location"
 * abstraction: this vertical owns its own floor plan, unrelated to
 * `Inventory\Warehouse` or `Organization\Branch`.
 *
 * `positionX`/`positionY` place the table on the floor-plan editor's canvas;
 * `null` means the owner hasn't dragged it onto the plan yet.
 */
#[ORM\Entity(repositoryClass: RestaurantTableRepository::class)]
#[ORM\Table(name: 'restaurant_tables')]
#[ApiResource(
    shortName: 'RestaurantTable',
    operations: [
        new GetCollection(uriTemplate: '/restaurant/tables', security: "is_granted('perm:restaurant.view')"),
        new Get(uriTemplate: '/restaurant/tables/{id}', security: "is_granted('perm:restaurant.view')"),
        new Post(uriTemplate: '/restaurant/tables', security: "is_granted('perm:restaurant.manage')"),
        new Patch(uriTemplate: '/restaurant/tables/{id}', security: "is_granted('perm:restaurant.manage')"),
        new Delete(
            uriTemplate: '/restaurant/tables/{id}',
            processor: DeleteRestaurantTableProcessor::class,
            security: "is_granted('perm:restaurant.manage')",
        ),
    ],
    normalizationContext: ['groups' => ['restaurant_table:read']],
    denormalizationContext: ['groups' => ['restaurant_table:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class RestaurantTable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['restaurant_table:read', 'restaurant_order:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    #[Groups(['restaurant_table:read', 'restaurant_table:write'])]
    private string $code;

    #[ORM\ManyToOne(targetEntity: RestaurantArea::class)]
    #[ORM\JoinColumn(name: 'area_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['restaurant_table:read', 'restaurant_table:write'])]
    private RestaurantArea $area;

    #[ORM\Column]
    #[Groups(['restaurant_table:read', 'restaurant_table:write'])]
    private int $seats;

    #[ORM\Column(enumType: RestaurantTableStatus::class, length: 20)]
    #[Groups(['restaurant_table:read'])]
    private RestaurantTableStatus $status = RestaurantTableStatus::Free;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['restaurant_table:read', 'restaurant_table:write'])]
    private ?float $positionX = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['restaurant_table:read', 'restaurant_table:write'])]
    private ?float $positionY = null;

    public function __construct(string $code, RestaurantArea $area, int $seats)
    {
        $this->code = $code;
        $this->area = $area;
        $this->seats = $seats;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getArea(): RestaurantArea
    {
        return $this->area;
    }

    public function setArea(RestaurantArea $area): self
    {
        $this->area = $area;

        return $this;
    }

    public function getSeats(): int
    {
        return $this->seats;
    }

    public function setSeats(int $seats): self
    {
        $this->seats = $seats;

        return $this;
    }

    public function getStatus(): RestaurantTableStatus
    {
        return $this->status;
    }

    public function getPositionX(): ?float
    {
        return $this->positionX;
    }

    public function setPositionX(?float $positionX): self
    {
        $this->positionX = $positionX;

        return $this;
    }

    public function getPositionY(): ?float
    {
        return $this->positionY;
    }

    public function setPositionY(?float $positionY): self
    {
        $this->positionY = $positionY;

        return $this;
    }

    public function isFree(): bool
    {
        return $this->status === RestaurantTableStatus::Free;
    }

    public function markOccupied(): self
    {
        $this->status = RestaurantTableStatus::Occupied;

        return $this;
    }

    public function markAwaitingPayment(): self
    {
        $this->status = RestaurantTableStatus::AwaitingPayment;

        return $this;
    }

    public function markFree(): self
    {
        $this->status = RestaurantTableStatus::Free;

        return $this;
    }
}
