<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use App\Catalog\Entity\Product;
use App\Inventory\Repository\PriceListItemRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A special price of one product inside a price list (table `price_list_detail`).
 *
 * Not an API resource: the lists are edited in bulk through `POST /price-lists/update`,
 * which owns the audit trail and the Mercure announcement for the whole save.
 */
#[ORM\Entity(repositoryClass: PriceListItemRepository::class)]
#[ORM\Table(name: 'price_list_detail')]
#[ORM\UniqueConstraint(name: 'uniq_product_price_list', columns: ['product_id', 'price_list_id'])]
#[ORM\Index(name: 'IDX_pld_product', columns: ['product_id'])]
#[ORM\Index(name: 'IDX_pld_price_list', columns: ['price_list_id'])]
class PriceListItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: PriceList::class)]
    #[ORM\JoinColumn(name: 'price_list_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PriceList $priceList;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $price;

    /** Maintained by the database (`ON UPDATE CURRENT_TIMESTAMP`); read-only here. */
    #[ORM\Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        insertable: false,
        updatable: false,
        generated: 'ALWAYS',
    )]
    private DateTimeImmutable $updatedAt;

    public function __construct(PriceList $priceList, Product $product, string $price)
    {
        $this->priceList = $priceList;
        $this->product = $product;
        $this->price = $price;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getPriceList(): PriceList
    {
        return $this->priceList;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt ?? null;
    }
}
