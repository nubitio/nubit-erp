<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Core\Http\ApiResponse;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Dto\PriceList\PriceListDto;
use App\Dto\PriceList\PricesDto;
use App\Repository\PriceListRepository;
use App\State\PriceList\PriceListProcessor;
use App\State\PriceList\PriceListProvider;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceListRepository::class)]
#[ORM\Table(name: 'price_list')]
#[ApiResource(
    filters: [DevExtremeFilter::class],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/price-lists/{id}/prices',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'id',
                    fromClass: PriceList::class,
                ),
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
    ]
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
}
