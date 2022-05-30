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

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int|null
     */
    public function getTypeId(): ?int
    {
        return $this->typeId;
    }

    /**
     * @param int|null $typeId
     */
    public function setTypeId(?int $typeId): void
    {
        $this->typeId = $typeId;
    }

    /**
     * @return string|null
     */
    public function getDate(): ?string
    {
        return $this->date;
    }

    /**
     * @param string|null $date
     */
    public function setDate(?string $date): void
    {
        $this->date = $date;
    }

    /**
     * @return string|null
     */
    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    /**
     * @param string|null $operationId
     */
    public function setOperationId(?string $operationId): void
    {
        $this->operationId = $operationId;
    }

    /**
     * @return string|null
     */
    public function getWarehouseId(): ?string
    {
        return $this->warehouseId;
    }

    /**
     * @param string|null $warehouseId
     */
    public function setWarehouseId(?string $warehouseId): void
    {
        $this->warehouseId = $warehouseId;
    }

    /**
     * @return string|null
     */
    public function getSupplierId(): ?string
    {
        return $this->supplierId;
    }

    /**
     * @param string|null $supplierId
     */
    public function setSupplierId(?string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    /**
     * @return string|null
     */
    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    /**
     * @param string|null $customerId
     */
    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return int|null
     */
    public function getPaymentType(): ?int
    {
        return $this->paymentType;
    }

    /**
     * @param int|null $paymentType
     */
    public function setPaymentType(?int $paymentType): void
    {
        $this->paymentType = $paymentType;
    }

    /**
     * @return int|null
     */
    public function getCardType(): ?int
    {
        return $this->cardType;
    }

    /**
     * @param int|null $cardType
     */
    public function setCardType(?int $cardType): void
    {
        $this->cardType = $cardType;
    }

    /**
     * @return string|null
     */
    public function getSeries(): ?string
    {
        return $this->series;
    }

    /**
     * @param string|null $series
     */
    public function setSeries(?string $series): void
    {
        $this->series = $series;
    }

    /**
     * @return string|null
     */
    public function getNumber(): ?string
    {
        return $this->number;
    }

    /**
     * @param string|null $number
     */
    public function setNumber(?string $number): void
    {
        $this->number = $number;
    }

    /**
     * @return float|null
     */
    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    /**
     * @param float|null $discount
     */
    public function setDiscount(?float $discount): void
    {
        $this->discount = $discount;
    }

    /**
     * @return string|null
     */
    public function getObs(): ?string
    {
        return $this->obs;
    }

    /**
     * @param string|null $obs
     */
    public function setObs(?string $obs): void
    {
        $this->obs = $obs;
    }

    /**
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    /**
     * @param int|null $orderId
     */
    public function setOrderId(?int $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * @return bool|null
     */
    public function getHasInvoice(): ?bool
    {
        return $this->hasInvoice;
    }

    /**
     * @param bool|null $hasInvoice
     */
    public function setHasInvoice(?bool $hasInvoice): void
    {
        $this->hasInvoice = $hasInvoice;
    }

    /**
     * @return bool|null
     */
    public function getIsConsume(): ?bool
    {
        return $this->isConsume;
    }

    /**
     * @param bool|null $isConsume
     */
    public function setIsConsume(?bool $isConsume): void
    {
        $this->isConsume = $isConsume;
    }

    /**
     * @return string|null
     */
    public function getCashBoxId(): ?string
    {
        return $this->cashBoxId;
    }

    /**
     * @param string|null $cashBoxId
     */
    public function setCashBoxId(?string $cashBoxId): void
    {
        $this->cashBoxId = $cashBoxId;
    }

    /**
     * @return int|null
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @param int|null $status
     */
    public function setStatus(?int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * @param string|null $createdAt
     */
    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @param string|null $updatedAt
     */
    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return int|null
     */
    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    /**
     * @param int|null $createdBy
     */
    public function setCreatedBy(?int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return int|null
     */
    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    /**
     * @param int|null $updatedBy
     */
    public function setUpdatedBy(?int $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    /**
     * @return int|null
     */
    public function getTax(): ?int
    {
        return $this->tax;
    }

    /**
     * @param int|null $tax
     */
    public function setTax(?int $tax): void
    {
        $this->tax = $tax;
    }

    /**
     * @return int|null
     */
    public function getCharge(): ?int
    {
        return $this->charge;
    }

    /**
     * @param int|null $charge
     */
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
