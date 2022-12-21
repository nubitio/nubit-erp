<?php

namespace App\Outbound\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Outbound\Domain\Outbound;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "movimiento")]
class OutboundEntity
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

    public function mapToDomainModel(): Outbound
    {
        return new Outbound(
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

    public function mapFromDomainModel(Outbound $outbound): OutboundEntity
    {
        $this->movimiento_id = $outbound->movimiento_id;
        $this->operacion_id = $outbound->operacion_id;
        $this->almacen_id = $outbound->almacen_id;
        $this->cliente_id = $outbound->cliente_id;
        $this->tipo_id = $outbound->tipo_id;
        $this->fecha = $outbound->fecha;
        $this->proveedor_id = $outbound->proveedor_id;
        $this->tipo_pago = $outbound->tipo_pago;
        $this->tipo_tarjeta = $outbound->tipo_tarjeta;
        $this->serie = $outbound->serie;
        $this->numero = $outbound->numero;
        $this->descuento = $outbound->descuento;
        $this->glosa = $outbound->glosa;
        $this->orden_id = $outbound->orden_id;
        $this->has_invoice = $outbound->has_invoice;
        $this->is_consume = $outbound->is_consume;
        $this->caja_id = $outbound->caja_id;
        $this->estado = $outbound->estado;
        $this->date_created = $outbound->date_created;
        $this->date_updated = $outbound->date_updated;
        $this->created_by = $outbound->created_by;
        $this->updated_by = $outbound->updated_by;
        $this->igv = $outbound->igv;
        $this->recargo_consumo = $outbound->recargo_consumo;

        return $this;
    }
}