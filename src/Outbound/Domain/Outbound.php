<?php

namespace App\Outbound\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\SaleDetail\Domain\SaleDetail;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Outbound
{
    public function __construct(
        #[SerializedName('movimiento_id')]
        public ?string $movimiento_id = null,
        #[SerializedName('operacion_id')]
        public ?string $operacion_id = null,
        #[SerializedName('almacen_id')]
        public ?string $almacen_id = null,
        #[SerializedName('cliente_id')]
        public ?string $cliente_id = null,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id = null,
        #[SerializedName('fecha')]
        public ?\DateTime $fecha = null,
        #[SerializedName('proveedor_id')]
        public ?string $proveedor_id = null,
        #[SerializedName('tipo_pago')]
        public ?int $tipo_pago = null,
        #[SerializedName('tipo_tarjeta')]
        public ?int $tipo_tarjeta = null,
        #[SerializedName('serie')]
        public ?string $serie = null,
        #[SerializedName('numero')]
        public ?string $numero = null,
        #[SerializedName('descuento')]
        public ?string $descuento = null,
        #[SerializedName('glosa')]
        public ?string $glosa = null,
        #[SerializedName('orden_id')]
        public ?int $orden_id = null,
        #[SerializedName('has_invoice')]
        public ?bool $has_invoice = null,
        #[SerializedName('is_consume')]
        public ?bool $is_consume = null,
        #[SerializedName('caja_id')]
        public ?string $caja_id = null,
        #[SerializedName('estado')]
        public ?bool $estado = null,
        #[SerializedName('date_created')]
        public ?\DateTime $date_created = null,
        #[SerializedName('date_updated')]
        public ?\DateTime $date_updated = null,
        #[SerializedName('created_by')]
        public ?int $created_by = null,
        #[SerializedName('updated_by')]
        public ?int $updated_by = null,
        #[SerializedName('igv')]
        public ?int $igv = null,
        #[SerializedName('recargo_consumo')]
        public ?int $recargo_consumo = null,
        /** @var SaleDetail[] $items */
        public ?array $items = [],
    ) {
    }
}
