<?php

namespace App\Purchase\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Purchase\Domain\Purchase;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "movimiento")]
class PurchaseEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: "movimiento_id", type: "string")]
    public ?string $movimiento_id;
    #[Column(name: "operacion_id", type: "string")]
    public ?string $operacion_id;
    #[Column(name: "almacen_id", type: "string")]
    public ?string $almacen_id;
    #[Column(name: "cliente_id", type: "string")]
    public ?string $cliente_id;
    #[Column(name: "tipo_id", type: "integer")]
    public ?int $tipo_id;
    #[Column(name: "fecha", type: "date")]
    public ?\DateTime $fecha;
    #[Column(name: "proveedor_id", type: "string")]
    public ?string $proveedor_id;
    #[Column(name: "tipo_pago", type: "integer")]
    public ?int $tipo_pago;
    #[Column(name: "tipo_tarjeta", type: "integer")]
    public ?int $tipo_tarjeta;
    #[Column(name: "serie", type: "string")]
    public ?string $serie;
    #[Column(name: "numero", type: "string")]
    public ?string $numero;
    #[Column(name: "descuento", type: "decimal")]
    public ?string $descuento;
    #[Column(name: "glosa", type: "string")]
    public ?string $glosa;
    #[Column(name: "orden_id", type: "integer")]
    public ?int $orden_id;
    #[Column(name: "has_invoice", type: "boolean")]
    public ?bool $has_invoice;
    #[Column(name: "is_consume", type: "boolean")]
    public ?bool $is_consume;
    #[Column(name: "caja_id", type: "string")]
    public ?string $caja_id;
    #[Column(name: "estado", type: "boolean")]
    public ?bool $estado;
    #[Column(name: "date_created", type: "datetime")]
    public ?\DateTime $date_created;
    #[Column(name: "date_updated", type: "datetime")]
    public ?\DateTime $date_updated;
    #[Column(name: "created_by", type: "integer")]
    public ?int $created_by;
    #[Column(name: "updated_by", type: "integer")]
    public ?int $updated_by;
    #[Column(name: "igv", type: "integer")]
    public ?int $igv;
    #[Column(name: "recargo_consumo", type: "integer")]
    public ?int $recargo_consumo;

    public function mapToDomainModel(): Purchase
    {
        return new Purchase(
            $this->movimiento_id,
            $this->operacion_id,
            $this->almacen_id,
            $this->cliente_id,
            $this->tipo_id,
            $this->fecha,
            $this->proveedor_id,
            $this->tipo_pago,
            $this->tipo_tarjeta,
            $this->serie,
            $this->numero,
            $this->descuento,
            $this->glosa,
            $this->orden_id,
            $this->has_invoice,
            $this->is_consume,
            $this->caja_id,
            $this->estado,
            $this->date_created,
            $this->date_updated,
            $this->created_by,
            $this->updated_by,
            $this->igv,
            $this->recargo_consumo,

        );
    }

    public function mapFromDomainModel(Purchase $purchase): PurchaseEntity
    {
        $this->movimiento_id = $purchase->movimiento_id;
        $this->operacion_id = $purchase->operacion_id;
        $this->almacen_id = $purchase->almacen_id;
        $this->cliente_id = $purchase->cliente_id;
        $this->tipo_id = $purchase->tipo_id;
        $this->fecha = $purchase->fecha;
        $this->proveedor_id = $purchase->proveedor_id;
        $this->tipo_pago = $purchase->tipo_pago;
        $this->tipo_tarjeta = $purchase->tipo_tarjeta;
        $this->serie = $purchase->serie;
        $this->numero = $purchase->numero;
        $this->descuento = $purchase->descuento;
        $this->glosa = $purchase->glosa;
        $this->orden_id = $purchase->orden_id;
        $this->has_invoice = $purchase->has_invoice;
        $this->is_consume = $purchase->is_consume;
        $this->caja_id = $purchase->caja_id;
        $this->estado = $purchase->estado;
        $this->date_created = $purchase->date_created;
        $this->date_updated = $purchase->date_updated;
        $this->created_by = $purchase->created_by;
        $this->updated_by = $purchase->updated_by;
        $this->igv = $purchase->igv;
        $this->recargo_consumo = $purchase->recargo_consumo;

        return $this;
    }
}