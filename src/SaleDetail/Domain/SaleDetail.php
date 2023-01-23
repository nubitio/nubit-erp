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
        public ?string $cantidad = null,
        #[SerializedName('precio_unitario')]
        public ?string $precio_unitario = null,
        #[SerializedName('descuento')]
        public ?string $descuento = null,
        #[SerializedName('precio_total')]
        public ?string $precio_total = null
    )
    {
    }
}