<?php

namespace App\OrdenDetail\Domain;

use App\Core\Domain\Annotation\Ignore;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Orden\Domain\Orden;
use App\Product\Domain\Product;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class OrdenDetail
{
    public function __construct(
        public ?int $id = null,
        #[Ignore]
        #[SerializedName('orden')]
        public ?Orden $orden = null,
        #[SerializedName('producto')]
        public ?Product $producto = null,
        #[SerializedName('cantidad')]
        public ?int $cantidad = null,
        #[SerializedName('cantidad_helper')]
        public ?int $cantidad_helper = null,
        #[SerializedName('precio_unitario')]
        public ?float $precio_unitario = null,
        #[SerializedName('precio_total')]
        public ?float $precio_total = null,
        #[SerializedName('nota')]
        public ?string $nota = null,
        #[SerializedName('devolver')]
        public ?bool $devolver = false,
        #[SerializedName('desechar')]
        public ?bool $desechar = false,
        #[SerializedName('billed')]
        public ?bool $billed = false,
        #[SerializedName('billed_quantity')]
        public ?int $billed_quantity = null,
        #[SerializedName('date_created')]
        public ?\DateTimeInterface $date_created = null,
        #[SerializedName('date_updated')]
        public ?\DateTimeInterface $date_updated = null,
        #[SerializedName('estado_cocina')]
        public ?bool $estado_cocina = false,
        #[SerializedName('estado_barra')]
        public ?bool $estado_barra = false,
        #[SerializedName('estado')]
        public ?int $estado = null
    ) {
    }
}
