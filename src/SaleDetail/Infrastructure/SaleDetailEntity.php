<?php

namespace App\SaleDetail\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\SaleDetail\Domain\SaleDetail;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "movimiento_detalle")]
class SaleDetailEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: "movimiento_id", type: "string")]
    public ?string $movimiento_id;
    #[Column(name: "producto_id", type: "string")]
    public ?string $producto_id;
    #[Column(name: "cantidad", type: "decimal")]
    public ?string $cantidad;
    #[Column(name: "precio_unitario", type: "decimal")]
    public ?string $precio_unitario;
    #[Column(name: "descuento", type: "decimal")]
    public ?string $descuento;
    #[Column(name: "precio_total", type: "decimal")]
    public ?string $precio_total;

    public function mapToDomainModel(): SaleDetail
    {
        return new SaleDetail(
            $this->movimiento_id,
            $this->producto_id,
            $this->cantidad,
            $this->precio_unitario,
            $this->descuento,
            $this->precio_total,

        );
    }

    public function mapFromDomainModel(SaleDetail $saledetail): SaleDetailEntity
    {
        $this->movimiento_id = $saledetail->movimiento_id;
        $this->producto_id = $saledetail->producto_id;
        $this->cantidad = $saledetail->cantidad;
        $this->precio_unitario = $saledetail->precio_unitario;
        $this->descuento = $saledetail->descuento;
        $this->precio_total = $saledetail->precio_total;

        return $this;
    }
}