<?php

declare(strict_types=1);

namespace App\Purchasing\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Catalog\Entity\Product;
use App\Purchasing\Repository\PurchaseOrderItemRepository;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One requested line of a PurchaseOrder. receivedQty is the only field
 * mutated after creation — a running total kept in sync by
 * App\Purchasing\Service\PurchaseOrderReceiptService whenever a Purchase
 * linked to this line is posted or voided. pendingQty is derived, never
 * stored, to avoid a second source of truth.
 *
 * Get-only ApiResource: not meant to be listed/created/edited directly
 * (items are always written through the parent PurchaseOrder's writeItems
 * payload) — exists so its IRI resolves when PurchaseItem::purchaseOrderItem
 * is denormalized from the "receive against PO" frontend flow.
 */
#[ApiResource(operations: [new Get()], normalizationContext: ['groups' => ['purchase_order:read']])]
#[ORM\Entity(repositoryClass: PurchaseOrderItemRepository::class)]
#[ORM\Table(name: 'purchase_order_items')]
#[ORM\Index(name: 'idx_purchase_order_items_order', columns: ['purchase_order_id'])]
class PurchaseOrderItem
{
    #[Groups(['purchase_order:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PurchaseOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'purchase_order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PurchaseOrder $purchaseOrder;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'line_no')]
    private int $lineNo = 0;

    #[Groups(['purchase_order:read'])]
    #[ApiProperty(description: 'purchase_order_item.product.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: true)]
    private ?Product $product = null;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'name_snapshot', length: 255)]
    private string $nameSnapshot = '';

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'sku_snapshot', length: 64, nullable: true)]
    private ?string $skuSnapshot = null;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'uom_snapshot', length: 20, nullable: true)]
    private ?string $uomSnapshot = null;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'ordered_qty', type: Types::DECIMAL, precision: 14, scale: 3)]
    private string $orderedQty = '0.000';

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'received_qty', type: Types::DECIMAL, precision: 14, scale: 3, options: ['default' => '0.000'])]
    private string $receivedQty = '0.000';

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'expected_unit_cost', type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $expectedUnitCost = '0.0000';

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'gross_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $grossAmount = '0.00';

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'tax_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $taxAmount = '0.00';

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'total_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $totalAmount = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPurchaseOrder(): PurchaseOrder
    {
        return $this->purchaseOrder;
    }

    public function setPurchaseOrder(PurchaseOrder $purchaseOrder): static
    {
        $this->purchaseOrder = $purchaseOrder;

        return $this;
    }

    public function getLineNo(): int
    {
        return $this->lineNo;
    }

    public function setLineNo(int $lineNo): static
    {
        $this->lineNo = $lineNo;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getNameSnapshot(): string
    {
        return $this->nameSnapshot;
    }

    public function setNameSnapshot(string $nameSnapshot): static
    {
        $this->nameSnapshot = $nameSnapshot;

        return $this;
    }

    public function getSkuSnapshot(): ?string
    {
        return $this->skuSnapshot;
    }

    public function setSkuSnapshot(?string $skuSnapshot): static
    {
        $this->skuSnapshot = $skuSnapshot;

        return $this;
    }

    public function getUomSnapshot(): ?string
    {
        return $this->uomSnapshot;
    }

    public function setUomSnapshot(?string $uomSnapshot): static
    {
        $this->uomSnapshot = $uomSnapshot;

        return $this;
    }

    public function getOrderedQty(): string
    {
        return $this->orderedQty;
    }

    public function setOrderedQty(string $orderedQty): static
    {
        $this->orderedQty = $orderedQty;

        return $this;
    }

    public function getReceivedQty(): string
    {
        return $this->receivedQty;
    }

    public function setReceivedQty(string $receivedQty): static
    {
        $this->receivedQty = $receivedQty;

        return $this;
    }

    #[Groups(['purchase_order:read'])]
    #[ApiProperty(description: 'purchase_order_item.pending_qty.label')]
    public function getPendingQty(): string
    {
        return BigDecimal::of($this->orderedQty)->minus(BigDecimal::of($this->receivedQty))->__toString();
    }

    public function getExpectedUnitCost(): string
    {
        return $this->expectedUnitCost;
    }

    public function setExpectedUnitCost(string $expectedUnitCost): static
    {
        $this->expectedUnitCost = $expectedUnitCost;

        return $this;
    }

    public function getGrossAmount(): string
    {
        return $this->grossAmount;
    }

    public function setGrossAmount(string $grossAmount): static
    {
        $this->grossAmount = $grossAmount;

        return $this;
    }

    public function getTaxAmount(): string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;

        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }
}
