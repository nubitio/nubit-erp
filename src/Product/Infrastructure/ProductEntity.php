<?php

namespace App\Product\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Domain\Product;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'producto')]
class ProductEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'producto_id', type: 'string')]
    public ?string $producto_id;
    #[Column(name: 'codigo', type: 'string')]
    public ?string $codigo;
    #[Column(name: 'tipo_id', type: 'integer')]
    public ?int $tipo_id;
    #[Column(name: 'descripcion', type: 'string')]
    public ?string $descripcion;
    #[Column(name: 'categoria_id', type: 'string')]
    public ?string $categoria_id;
    #[Column(name: 'unidad_id', type: 'string')]
    public ?string $unidad_id;
    #[Column(name: 'destino_id', type: 'integer')]
    public ?int $destino_id;
    #[Column(name: 'stock_minimo', type: 'integer')]
    public ?int $stock_minimo;
    #[Column(name: 'precio', type: 'decimal')]
    public ?float $precio;
    #[Column(name: 'precio_compra', type: 'decimal')]
    public ?float $precio_compra;
    #[Column(name: 'imagen', type: 'string')]
    public ?string $imagen;
    #[Column(name: 'estado', type: 'integer')]
    public ?int $estado;

    public function mapToDomainModel(): Product
    {
        return new Product(
            $this->producto_id,
            $this->codigo,
            $this->tipo_id,
            $this->descripcion,
            $this->categoria_id,
            $this->unidad_id,
            $this->destino_id,
            $this->stock_minimo,
            $this->precio,
            $this->precio_compra,
            $this->imagen,
            $this->estado,
        );
    }

    public function mapFromDomainModel(Product $product): ProductEntity
    {
        $this->producto_id = $product->producto_id;
        $this->codigo = $product->codigo ?: null;
        $this->tipo_id = $product->tipo_id;
        $this->descripcion = $product->descripcion;
        $this->categoria_id = $product->categoria_id;
        $this->unidad_id = $product->unidad_id;
        $this->destino_id = $product->destino_id;
        $this->stock_minimo = $product->stock_minimo;
        $this->precio = $product->precio;
        $this->precio_compra = $product->precio_compra;
        $this->imagen = $product->imagen;
        $this->estado = $product->estado;

        return $this;
    }
}
