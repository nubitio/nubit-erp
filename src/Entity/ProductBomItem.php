<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductBomItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bill-of-Materials component line for COMPOSITE products.
 *
 * Invariant: parent must have inventory_behavior = COMPOSITE.
 *            component must have inventory_behavior = TRACKED.
 */
#[ORM\Entity(repositoryClass: ProductBomItemRepository::class)]
#[ORM\Table(name: 'product_bom_items')]
#[ORM\UniqueConstraint(name: 'uq_bom_parent_component', columns: ['parent_product_id', 'component_product_id'])]
class ProductBomItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'parent_product_id', referencedColumnName: 'producto_id', nullable: false, onDelete: 'CASCADE')]
    private Product $parentProduct;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'component_product_id', referencedColumnName: 'producto_id', nullable: false, onDelete: 'RESTRICT')]
    private Product $componentProduct;

    /** Quantity of component consumed per 1 unit of the parent, expressed in the component's base unit. */
    #[ORM\Column(name: 'component_qty_base', type: Types::DECIMAL, precision: 18, scale: 6)]
    private string $componentQtyBase;

    /**
     * Wastage multiplier. 1.0 = no wastage. 1.05 = consume 5% extra.
     * Applied as: effective_qty = component_qty_base * wastage_factor.
     */
    #[ORM\Column(name: 'wastage_factor', type: Types::DECIMAL, precision: 7, scale: 4, options: ['default' => '1.0000'])]
    private string $wastageFactor = '1.0000';

    /** When true, this component can be omitted (substitutable ingredient). */
    #[ORM\Column(name: 'is_optional', options: ['default' => false])]
    private bool $isOptional = false;

    #[ORM\Column(name: 'sort_order', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentProduct(): Product
    {
        return $this->parentProduct;
    }

    public function setParentProduct(Product $product): static
    {
        $this->parentProduct = $product;

        return $this;
    }

    public function getComponentProduct(): Product
    {
        return $this->componentProduct;
    }

    public function setComponentProduct(Product $product): static
    {
        $this->componentProduct = $product;

        return $this;
    }

    public function getComponentQtyBase(): string
    {
        return $this->componentQtyBase;
    }

    public function setComponentQtyBase(string $qty): static
    {
        $this->componentQtyBase = $qty;

        return $this;
    }

    public function getWastageFactor(): string
    {
        return $this->wastageFactor;
    }

    public function setWastageFactor(string $factor): static
    {
        $this->wastageFactor = $factor;

        return $this;
    }

    /** Effective quantity to deduct: component_qty_base × wastage_factor × parent_qty. */
    public function effectiveQty(string $parentQty): string
    {
        return bcmul(bcmul($this->componentQtyBase, $this->wastageFactor, 6), $parentQty, 6);
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    public function setIsOptional(bool $optional): static
    {
        $this->isOptional = $optional;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $order): static
    {
        $this->sortOrder = $order;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
