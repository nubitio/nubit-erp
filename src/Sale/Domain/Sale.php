<?php

namespace App\Sale\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Sale
{
    public function __construct(
        #[SerializedName('movimiento_id')]
        public ?string $movimiento_id,
        #[SerializedName('operacion_id')]
        public ?string $operacion_id,
        #[SerializedName('almacen_id')]
        public ?string $almacen_id,
        #[SerializedName('cliente_id')]
        public ?string $cliente_id,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id,
        #[SerializedName('fecha')]
        public ?\DateTime $fecha,
        #[SerializedName('proveedor_id')]
        public ?string $proveedor_id,
        #[SerializedName('tipo_pago')]
        public ?int $tipo_pago,
        #[SerializedName('tipo_tarjeta')]
        public ?int $tipo_tarjeta,
        #[SerializedName('serie')]
        public ?string $serie,
        #[SerializedName('numero')]
        public ?string $numero,
        #[SerializedName('descuento')]
        public ?string $descuento,
        #[SerializedName('glosa')]
        public ?string $glosa,
        #[SerializedName('orden_id')]
        public ?int $orden_id,
        #[SerializedName('has_invoice')]
        public ?bool $has_invoice,
        #[SerializedName('is_consume')]
        public ?bool $is_consume,
        #[SerializedName('caja_id')]
        public ?string $caja_id,
        #[SerializedName('estado')]
        public ?bool $estado,
        #[SerializedName('date_created')]
        public ?\DateTime $date_created,
        #[SerializedName('date_updated')]
        public ?\DateTime $date_updated,
        #[SerializedName('created_by')]
        public ?int $created_by,
        #[SerializedName('updated_by')]
        public ?int $updated_by,
        #[SerializedName('igv')]
        public ?int $igv,
        #[SerializedName('recargo_consumo')]
        public ?int $recargo_consumo,

    )
    {
    }
}