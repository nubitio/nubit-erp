<?php

namespace App\Product\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Product
{
    public function __construct(
        #[SerializedName('producto_id')]
        public ?string $producto_id,
        #[SerializedName('codigo')]
        public ?string $codigo,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id,
        #[SerializedName('descripcion')]
        public ?string $descripcion,
        #[SerializedName('categoria_id')]
        public ?string $categoria_id,
        #[SerializedName('unidad_id')]
        public ?string $unidad_id,
        #[SerializedName('destino_id')]
        public ?int $destino_id,
        #[SerializedName('stock_minimo')]
        public ?int $stock_minimo,
        #[SerializedName('precio')]
        public ?string $precio,
        #[SerializedName('precio_compra')]
        public ?string $precio_compra,
        #[SerializedName('imagen')]
        public ?string $imagen,
        #[SerializedName('estado')]
        public ?int $estado,
    ) {
    }
}
