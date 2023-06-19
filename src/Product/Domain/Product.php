<?php

namespace App\Product\Domain;

use App\Category\Domain\Category;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Media\Domain\Media;
use App\UnitOfMeasure\Domain\UnitOfMeasure;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Product
{
    public function __construct(
        #[SerializedName('producto_id')]
        public ?string $producto_id = null,
        #[SerializedName('codigo')]
        public ?string $codigo = null,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('categoria')]
        public ?Category $categoria = null,
        #[SerializedName('unidad')]
        public ?UnitOfMeasure $unidad = null,
        #[SerializedName('destino_id')]
        public ?int $destino_id = null,
        #[SerializedName('stock_minimo')]
        public ?int $stock_minimo = null,
        #[SerializedName('precio')]
        public ?float $precio = null,
        #[SerializedName('precio_compra')]
        public ?float $precio_compra = null,
        #[SerializedName('imagen')]
        public ?Media $imagen = null,
        #[SerializedName('estado')]
        public ?bool $estado = false
    ) {
    }
}
