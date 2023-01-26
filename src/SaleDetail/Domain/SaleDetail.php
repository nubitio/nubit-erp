<?php

namespace App\SaleDetail\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class SaleDetail
{
    public function __construct(
        #[SerializedName('movimiento_id')]
        public ?string $movimiento_id = null,
        #[SerializedName('producto_id')]
        public ?string $producto_id = null,
        #[SerializedName('cantidad')]
        public ?float  $cantidad = null,
        #[SerializedName('precio_unitario')]
        public ?float  $precio_unitario = null,
        #[SerializedName('descuento')]
        public ?float  $descuento = null,
        #[SerializedName('precio_total')]
        public ?float  $precio_total = null
    )
    {
    }
}