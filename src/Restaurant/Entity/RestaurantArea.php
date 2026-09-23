<?php

declare(strict_types=1);

namespace App\Restaurant\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Restaurant\Repository\RestaurantAreaRepository;
use App\Restaurant\State\DeleteRestaurantAreaProcessor;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A named area of the floor (e.g. "Salón", "Terraza VIP"), scoped to a floor
 * number for restaurants with more than one level (NB-18 follow-up). The
 * floor-plan editor and the operational table map both show one tab per
 * area, ordered by `(floor, orderIndex)`.
 *
 * `name`/`floor`/`orderIndex` also carry `restaurant_table:read` so a
 * `RestaurantTable` embeds its area inline instead of just an IRI — same
 * pattern already used between `Warehouse`/`Branch` in this project.
 */
#[ORM\Entity(repositoryClass: RestaurantAreaRepository::class)]
#[ORM\Table(name: 'restaurant_areas')]
#[ApiResource(
    shortName: 'RestaurantArea',
    operations: [
        new GetCollection(uriTemplate: '/restaurant/areas', security: "is_granted('perm:restaurant.view')"),
        new Get(uriTemplate: '/restaurant/areas/{id}', security: "is_granted('perm:restaurant.view')"),
        new Post(uriTemplate: '/restaurant/areas', security: "is_granted('perm:restaurant.manage')"),
        new Patch(uriTemplate: '/restaurant/areas/{id}', security: "is_granted('perm:restaurant.manage')"),
        new Delete(
            uriTemplate: '/restaurant/areas/{id}',
            processor: DeleteRestaurantAreaProcessor::class,
            security: "is_granted('perm:restaurant.manage')",
        ),
    ],
    normalizationContext: ['groups' => ['restaurant_area:read']],
    denormalizationContext: ['groups' => ['restaurant_area:write']],
    filters: [DataGridFilter::class],
)]
class RestaurantArea
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['restaurant_area:read', 'restaurant_table:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    #[Groups(['restaurant_area:read', 'restaurant_area:write', 'restaurant_table:read'])]
    private string $name;

    #[ORM\Column]
    #[Groups(['restaurant_area:read', 'restaurant_area:write', 'restaurant_table:read'])]
    private int $floor = 1;

    #[ORM\Column]
    #[Groups(['restaurant_area:read', 'restaurant_area:write', 'restaurant_table:read'])]
    private int $orderIndex = 0;

    public function __construct(string $name, int $floor = 1, int $orderIndex = 0)
    {
        $this->name = $name;
        $this->floor = $floor;
        $this->orderIndex = $orderIndex;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getFloor(): int
    {
        return $this->floor;
    }

    public function setFloor(int $floor): self
    {
        $this->floor = $floor;

        return $this;
    }

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): self
    {
        $this->orderIndex = $orderIndex;

        return $this;
    }
}
