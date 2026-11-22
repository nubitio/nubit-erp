<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Inventory\Dto\PriceListApplyDto;
use App\Inventory\Dto\PriceListDto;
use App\Inventory\Dto\PricesDto;
use App\Inventory\Repository\PriceListRepository;
use App\Inventory\State\PriceListApplyProvider;
use App\Inventory\State\PriceListProcessor;
use App\Inventory\State\PriceListProvider;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Attribute\Auditable;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Nubit\Platform\Http\ApiResponse;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

#[Auditable]
#[ORM\Entity(repositoryClass: PriceListRepository::class)]
#[ORM\Table(name: 'price_list')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Patch(),
        new Delete(),
        new GetCollection(
            uriTemplate: '/price-lists/{id}/prices',
            uriVariables: [
                'id' => new Link(fromProperty: 'id', fromClass: PriceList::class),
            ],
            paginationEnabled: false,
            output: PriceListDto::class,
            provider: PriceListProvider::class,
        ),
        new Post(
            uriTemplate: '/price-lists/update',
            input: PricesDto::class,
            output: ApiResponse::class,
            processor: PriceListProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/price-lists/{id}/apply',
            uriVariables: [
                'id' => new Link(fromProperty: 'id', fromClass: PriceList::class),
            ],
            paginationEnabled: false,
            output: PriceListApplyDto::class,
            provider: PriceListApplyProvider::class,
        ),
    ],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class PriceList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ApiProperty(description: 'price_list.name.label')]
    #[ORM\Column(length: 255)]
    private string $name;

    /**
     * Optional global multiplier.
     * When set and no special price exists for the product (see PriceListItem),
     * the effective price is computed as: base_price × multiplier.
     * NULL means "explicit overrides only" (original behaviour).
     */
    #[ApiProperty(description: 'price_list.multiplier.label')]
    #[Context(denormalizationContext: [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true])]
    #[ORM\Column(type: 'decimal', precision: 8, scale: 4, nullable: true)]
    private ?string $multiplier = null;

    public function getId(): ?int
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

    public function getMultiplier(): ?string
    {
        return $this->multiplier;
    }

    /** The form posts a JSON number; Doctrine decimals are strings. */
    public function setMultiplier(string|int|float|null $multiplier): static
    {
        $this->multiplier = null === $multiplier || '' === $multiplier ? null : (string) $multiplier;

        return $this;
    }
}
