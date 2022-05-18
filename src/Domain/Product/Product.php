<?php

namespace App\Domain\Product;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints\NotBlank;

#[IgnoreAutowire]
class Product
{
    #[SerializedName('id')]
    private string $producto_id;
    #[SerializedName('code')]
    #[NotBlank]x
    private string $codigo;
    #[SerializedName('name')]
    private string $descripcion;
    #[SerializedName('price')]
    private float $precio;
    #[SerializedName('active')]
    private bool $estado;

    /**
     * @param string $producto_id
     * @param string $descripcion
     * @param float $precio
     * @param bool $estado
     */
    public function __construct(string $producto_id, string $descripcion, float $precio, bool $estado)
    {
        $this->producto_id = $producto_id;
        $this->descripcion = $descripcion;
        $this->precio = $precio;
        $this->estado = $estado;
    }

    /**
     * @return string
     */
    public function getProductoId(): string
    {
        return $this->producto_id;
    }

    /**
     * @param string $producto_id
     */
    public function setProductoId(string $producto_id): void
    {
        $this->producto_id = $producto_id;
    }

    /**
     * @return string
     */
    public function getDescripcion(): string
    {
        return $this->descripcion;
    }

    /**
     * @param string $descripcion
     */
    public function setDescripcion(string $descripcion): void
    {
        $this->descripcion = $descripcion;
    }

    /**
     * @return float
     */
    public function getPrecio(): float
    {
        return $this->precio;
    }

    /**
     * @param float $precio
     */
    public function setPrecio(float $precio): void
    {
        $this->precio = $precio;
    }

    /**
     * @return bool
     */
    public function isEstado(): bool
    {
        return $this->estado;
    }

    /**
     * @param bool $estado
     */
    public function setEstado(bool $estado): void
    {
        $this->estado = $estado;
    }
}