<?php

namespace App\SaleDetail\Domain;

use App\Core\Domain\Annotation\Ignore;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Domain\Product;
use App\Sale\Domain\Sale;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class SaleDetail
{
    public function __construct(
        #[Ignore]
        #[SerializedName('movimiento')]
        public ?Sale $movimiento = null,
        #[SerializedName('producto')]
        public ?Product $producto = null,
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