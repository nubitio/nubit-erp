<?php

namespace App\Sale\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Sale\Domain\Sale;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "movimiento")]
class SaleEntity
{
    #[Id]
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
    #[Column(name: "estado", type: "integer")]
    public ?int $estado;
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

    public function mapToDomainModel(): Sale
    {
        return new Sale(
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

    public function mapFromDomainModel(Sale $sale): SaleEntity
    {
        $this->movimiento_id = $sale->movimiento_id;
        $this->operacion_id = $sale->operacion_id;
        $this->almacen_id = $sale->almacen_id;
        $this->cliente_id = $sale->cliente_id;
        $this->tipo_id = $sale->tipo_id;
        $this->fecha = $sale->fecha;
        $this->proveedor_id = $sale->proveedor_id;
        $this->tipo_pago = $sale->tipo_pago;
        $this->tipo_tarjeta = $sale->tipo_tarjeta;
        $this->serie = $sale->serie;
        $this->numero = $sale->numero;
        $this->descuento = $sale->descuento;
        $this->glosa = $sale->glosa;
        $this->orden_id = $sale->orden_id;
        $this->has_invoice = $sale->has_invoice;
        $this->is_consume = $sale->is_consume;
        $this->caja_id = $sale->caja_id;
        $this->estado = $sale->estado;
        $this->date_created = $sale->date_created;
        $this->date_updated = $sale->date_updated;
        $this->created_by = $sale->created_by;
        $this->updated_by = $sale->updated_by;
        $this->igv = $sale->igv;
        $this->recargo_consumo = $sale->recargo_consumo;

        return $this;
    }
}