<?php

namespace App\Domain\Sale;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Sale
{
    #[SerializedName('movimiento_id')]
    private string $id;
    #[SerializedName('tipo_id')]
    private ?int $typeId = null;
    #[SerializedName('fecha')]
    private ?string $date = null;
    #[SerializedName('operacion_id')]
    private ?string $operationId = null;
    #[SerializedName('almacen_id')]
    private ?string $warehouseId = null;
    #[SerializedName('proveedor_id')]
    private ?string $supplierId = null;
    #[SerializedName('cliente_id')]
    private ?string $customerId = null;
    #[SerializedName('tipo_pago')]
    private ?int $paymentType = null;
    #[SerializedName('tipo_tarjeta')]
    private ?int $cardType = null;
    #[SerializedName('serie')]
    private ?string $series = null;
    #[SerializedName('numero')]
    private ?string $number = null;
    #[SerializedName('descuento')]
    private ?float $discount = null;
    #[SerializedName('glosa')]
    private ?string $obs = null;
    #[SerializedName('orden_id')]
    private ?int $orderId = null;
    #[SerializedName('has_invoice')]
    private ?bool $hasInvoice = null;
    #[SerializedName('is_consume')]
    private ?bool $isConsume = null;
    #[SerializedName('caja_id')]
    private ?string $cashBoxId = null;
    #[SerializedName('estado')]
    private ?int $status = null;
    #[SerializedName('date_created')]
    private ?string $createdAt = null;
    #[SerializedName('date_updated')]
    private ?string $updatedAt = null;
    #[SerializedName('created_by')]
    private ?int $createdBy = null;
    #[SerializedName('updated_by')]
    private ?int $updatedBy = null;
    #[SerializedName('igv')]
    private ?int $tax = null;
    #[SerializedName('recargo_consumo')]
    private ?int $charge = null;
    /**
     * @var SaleDetail[]
     */
    private array $detail;

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

    /**
     * @return SaleDetail[]
     */
    public function getDetail(): array
    {
        return $this->detail;
    }

    /**
     * @param SaleDetail[] $detail
     */
    public function setDetail(array $detail): void
    {
        $this->detail = $detail;
    }
}
