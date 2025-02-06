<?php

namespace App\Product\Infrastructure;

use App\Category\Infrastructure\CategoryEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Entity\Media;
use App\UnitOfMeasure\Infrastructure\UnitOfMeasureEntity;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'producto')]
class ProductEntity
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'CUSTOM')]
        #[CustomIdGenerator(class: UuidGenerator::class)]
        #[Column(name: 'producto_id', type: 'string')]
        public ?string $producto_id = null,
        #[Column(name: 'codigo', type: 'string')]
        public ?string $codigo = null,
        #[Column(name: 'tipo_id', type: 'integer')]
        public ?int $tipo_id = null,
        #[Column(name: 'descripcion', type: 'string')]
        public ?string $descripcion = null,
        #[ManyToOne(targetEntity: CategoryEntity::class)]
        #[JoinColumn(name: 'categoria_id', referencedColumnName: 'categoria_id')]
        public ?CategoryEntity $categoria = null,
        #[ManyToOne(targetEntity: UnitOfMeasureEntity::class)]
        #[JoinColumn(name: 'unidad_id', referencedColumnName: 'unidad_id')]
        public ?UnitOfMeasureEntity $unidad = null,
        #[Column(name: 'destino_id', type: 'integer')]
        public ?int $destino_id = null,
        #[Column(name: 'stock_minimo', type: 'integer')]
        public ?int $stock_minimo = null,
        #[Column(name: 'precio', type: 'decimal')]
        public ?float $precio = null,
        #[Column(name: 'precio_compra', type: 'decimal')]
        public ?float $precio_compra = null,
        #[ManyToOne(targetEntity: Media::class)]
        #[JoinColumn(name: 'imagen', referencedColumnName: 'id')]
        public ?Media $imagen = null,
        #[Column(name: 'estado', type: 'integer')]
        public ?bool $estado = null,
    ) {
    }
}
