<?php

namespace App\SaleDetail\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class SaleDetail
{
    public function __construct(
        #[SerializedName('movimiento_id')]
        public ?string $movimiento_id,
        #[SerializedName('producto_id')]
        public ?string $producto_id,
        #[SerializedName('cantidad')]
        public ?string $cantidad,
        #[SerializedName('precio_unitario')]
        public ?string $precio_unitario,
        #[SerializedName('descuento')]
        public ?string $descuento,
        #[SerializedName('precio_total')]
        public ?string $precio_total,
    ) {
    }
}
