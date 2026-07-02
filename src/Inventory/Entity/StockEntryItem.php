<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Inventory\Repository\StockEntryItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Attribute\EmbeddedLines;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[EmbeddedLines(
    parentProperty: 'entry',
    route: '/api/stock_entry_items',
    normalizationGroups: ['stock_entry_items:read'],
)]
#[ORM\Entity(repositoryClass: StockEntryItemRepository::class)]
#[ORM\Table(name: 'stock_entry_items')]
#[ApiResource(
    uriTemplate: '/stock-entries/{id}/items',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(fromProperty: 'items', fromClass: StockEntry::class),
    ],
    normalizationContext: ['groups' => ['stock_entry_items:read']],
    paginationEnabled: false,
)]
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
    #[ApiProperty(description: 'stock_entry_item.product.label', readableLink: true, openapiContext: ['x-crud' => [
        'order' => 1,
    ]])]
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
    #[ApiProperty(description: 'stock_entry_item.qty.label', openapiContext: ['x-crud' => ['order' => 2]])]
    #[Context(denormalizationContext: [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true])]
    #[ORM\Column(name: 'qty', type: Types::DECIMAL, precision: 18, scale: 6)]
    #[Assert\Positive(message: 'La cantidad debe ser mayor a cero.')]
    private string|int|float $qty = '0.000000';

    /**
     * Stock snapshot before this entry — set by processor for ADJUSTMENT only.
     * Null for all other types.
     */
    #[Groups(['stock_entry:read', 'stock_entry_items:read'])]
    #[ApiProperty(description: 'stock_entry_item.qty_before.label', openapiContext: ['x-crud' => [
        'hideInGrid' => true,
        'showInForm' => false,
    ]])]
    #[ORM\Column(name: 'qty_before', type: Types::DECIMAL, precision: 18, scale: 6, nullable: true)]
    private ?string $qtyBefore = null;

    /**
     * Signed delta actually applied to the ledger — set by processor.
     * Negative for ISSUE / TRANSFER_OUT; positive for RECEIPT / TRANSFER_IN / positive ADJUSTMENT.
     */
    #[Groups(['stock_entry:read', 'stock_entry_items:read'])]
    #[ApiProperty(description: 'stock_entry_item.qty_delta.label', openapiContext: ['x-crud' => [
        'hideInGrid' => true,
        'showInForm' => false,
    ]])]
    #[ORM\Column(name: 'qty_delta', type: Types::DECIMAL, precision: 18, scale: 6, nullable: true)]
    private ?string $qtyDelta = null;

    #[Groups(['stock_entry:read', 'stock_entry:write', 'stock_entry_items:read'])]
    #[ApiProperty(description: 'stock_entry_item.unit.label', openapiContext: ['x-crud' => ['order' => 3]])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'unidad_id', nullable: true)]
    private ?UnitOfMeasure $unit = null;

    #[Groups(['stock_entry:read', 'stock_entry:write', 'stock_entry_items:read'])]
    #[ApiProperty(description: 'stock_entry_item.note.label', openapiContext: ['x-crud' => ['order' => 4]])]
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

    public function setQty(string|int|float $qty): static
    {
        $this->qty = bcadd((string) $qty, '0', 6);
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
