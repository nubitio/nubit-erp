<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Inventory\Repository\StockEntryItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockEntryItemRepository::class)]
#[ORM\Table(name: 'stock_entry_items')]
class StockEntryItem
{
    #[Groups(['stock_entry:read', 'stock_entry_items:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'entry_id', referencedColumnName: 'id', nullable: false)]
    /** @phpstan-ignore-next-line doctrine.associationType */
    private ?StockEntry $entry = null;

    #[Groups(['stock_entry:read', 'stock_entry:write', 'stock_entry_items:read'])]
    #[ApiProperty(description: 'stock_entry_item.product.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: false)]
    #[Assert\NotNull]
    /** @phpstan-ignore-next-line doctrine.associationType */
    private ?Product $product = null;

    /**
     * User-entered quantity.
     * - RECEIPT / ISSUE / TRANSFER: how many units move (delta, always positive)
     * - ADJUSTMENT: the new real quantity (absolute)
     */
    #[Groups(['stock_entry:read', 'stock_entry:write', 'stock_entry_items:read'])]
    #[ApiProperty(description: 'stock_entry_item.qty.label')]
    #[ORM\Column(name: 'qty', type: Types::DECIMAL, precision: 18, scale: 6)]
    #[Assert\Positive(message: 'La cantidad debe ser mayor a cero.')]
    private string $qty = '0.000000';

    /**
     * Stock snapshot before this entry — set by processor for ADJUSTMENT only.
     * Null for all other types.
     */
    #[Groups(['stock_entry:read', 'stock_entry_items:read'])]
    #[ApiProperty(description: 'stock_entry_item.qty_before.label')]
    #[ORM\Column(name: 'qty_before', type: Types::DECIMAL, precision: 18, scale: 6, nullable: true)]
    private ?string $qtyBefore = null;

    /**
     * Signed delta actually applied to the ledger — set by processor.
     * Negative for ISSUE / TRANSFER_OUT; positive for RECEIPT / TRANSFER_IN / positive ADJUSTMENT.
     */
    #[Groups(['stock_entry:read', 'stock_entry_items:read'])]
    #[ApiProperty(description: 'stock_entry_item.qty_delta.label')]
    #[ORM\Column(name: 'qty_delta', type: Types::DECIMAL, precision: 18, scale: 6, nullable: true)]
    private ?string $qtyDelta = null;

    #[Groups(['stock_entry:read', 'stock_entry:write', 'stock_entry_items:read'])]
    #[ApiProperty(description: 'stock_entry_item.unit.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'unidad_id', nullable: true)]
    private ?UnitOfMeasure $unit = null;

    #[Groups(['stock_entry:read', 'stock_entry:write', 'stock_entry_items:read'])]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): ?StockEntry
    {
        return $this->entry;
    }
    public function setEntry(?StockEntry $entry): static
    {
        $this->entry = $entry;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }
    public function setProduct(?Product $p): static
    {
        $this->product = $p;
        return $this;
    }

    public function getQty(): string
    {
        return $this->qty;
    }
    public function setQty(string $qty): static
    {
        $this->qty = bcadd($qty, '0', 6);
        return $this;
    }

    public function getQtyBefore(): ?string
    {
        return $this->qtyBefore;
    }
    public function setQtyBefore(?string $v): static
    {
        $this->qtyBefore = $v !== null ? bcadd($v, '0', 6) : null;
        return $this;
    }

    public function getQtyDelta(): ?string
    {
        return $this->qtyDelta;
    }
    public function setQtyDelta(?string $v): static
    {
        $this->qtyDelta = $v;
        return $this;
    }

    public function getUnit(): ?UnitOfMeasure
    {
        return $this->unit;
    }
    public function setUnit(?UnitOfMeasure $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }
}
