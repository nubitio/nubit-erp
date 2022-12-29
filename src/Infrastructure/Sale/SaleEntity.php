<?php

namespace App\Infrastructure\Sale;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;

class SaleEntity
{
    #[Id]
    #[Column(name: 'movimiento_id', type: 'string')]
    private string $id;
    #[Column(name: 'tipo_id', type: 'int')]
    private ?int $typeId = null;
    #[Column(name: 'fecha', type: 'date')]
    private ?string $date = null;
    #[Column(name: 'operacion_id', type: 'string')]
    private ?string $operationId = null;
    #[Column(name: 'almacen_id', type: 'string')]
    private ?string $warehouseId = null;
    #[Column(name: 'proveedor_id', type: 'string')]
    private ?string $supplierId = null;
    #[Column(name: 'cliente_id', type: 'string')]
    private ?string $customerId = null;
    #[Column(name: 'tipo_pago', type: 'integer')]
    private ?int $paymentType = null;
    #[Column(name: 'tipo_tarjeta', type: 'integer')]
    private ?int $cardType = null;
    #[Column(name: 'serie', type: 'string')]
    private ?string $series = null;
    #[Column(name: 'numero', type: 'string')]
    private ?string $number = null;
    #[Column(name: 'descuento', type: 'decimal')]
    private ?float $discount = null;
    #[Column(name: 'glosa', type: 'string')]
    private ?string $obs = null;
    #[Column(name: 'orden_id', type: 'integer')]
    private ?int $orderId = null;
    #[Column(name: 'has_invoice', type: 'boolean')]
    private ?bool $hasInvoice = null;
    #[Column(name: 'is_consume', type: 'boolean')]
    private ?bool $isConsume = null;
    #[Column(name: 'caja_id', type: 'string')]
    private ?string $cashBoxId = null;
    #[Column(name: 'estado', type: 'boolean')]
    private ?int $status = null;
    #[Column(name: 'date_created', type: 'date')]
    private ?string $createdAt = null;
    #[Column(name: 'date_updated', type: 'date')]
    private ?string $updatedAt = null;
    #[Column(name: 'created_by', type: 'date')]
    private ?int $createdBy = null;
    #[Column(name: 'updated_by', type: 'date')]
    private ?int $updatedBy = null;
    #[Column(name: 'igv', type: 'integer')]
    private ?int $tax = null;
    #[Column(name: 'recargo_consumo', type: 'integer')]
    private ?int $charge = null;
    #[OneToMany(targetEntity: SaleDetailEntity::class)]
    private ArrayCollection $detail;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getTypeId(): ?int
    {
        return $this->typeId;
    }

    public function setTypeId(?int $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(?string $date): void
    {
        $this->date = $date;
    }

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function setOperationId(?string $operationId): void
    {
        $this->operationId = $operationId;
    }

    public function getWarehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(?string $warehouseId): void
    {
        $this->warehouseId = $warehouseId;
    }

    public function getSupplierId(): ?string
    {
        return $this->supplierId;
    }

    public function setSupplierId(?string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getPaymentType(): ?int
    {
        return $this->paymentType;
    }

    public function setPaymentType(?int $paymentType): void
    {
        $this->paymentType = $paymentType;
    }

    public function getCardType(): ?int
    {
        return $this->cardType;
    }

    public function setCardType(?int $cardType): void
    {
        $this->cardType = $cardType;
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setSeries(?string $series): void
    {
        $this->series = $series;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): void
    {
        $this->number = $number;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): void
    {
        $this->discount = $discount;
    }

    public function getObs(): ?string
    {
        return $this->obs;
    }

    public function setObs(?string $obs): void
    {
        $this->obs = $obs;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function setOrderId(?int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getHasInvoice(): ?bool
    {
        return $this->hasInvoice;
    }

    public function setHasInvoice(?bool $hasInvoice): void
    {
        $this->hasInvoice = $hasInvoice;
    }

    public function getIsConsume(): ?bool
    {
        return $this->isConsume;
    }

    public function setIsConsume(?bool $isConsume): void
    {
        $this->isConsume = $isConsume;
    }

    public function getCashBoxId(): ?string
    {
        return $this->cashBoxId;
    }

    public function setCashBoxId(?string $cashBoxId): void
    {
        $this->cashBoxId = $cashBoxId;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    public function getTax(): ?int
    {
        return $this->tax;
    }

    public function setTax(?int $tax): void
    {
        $this->tax = $tax;
    }

    public function getCharge(): ?int
    {
        return $this->charge;
    }

    public function setCharge(?int $charge): void
    {
        $this->charge = $charge;
    }

    public function getDetail(): ArrayCollection
    {
        return $this->detail;
    }

    public function setDetail(ArrayCollection $detail): void
    {
        $this->detail = $detail;
    }
}
