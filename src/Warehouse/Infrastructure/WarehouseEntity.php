<?php

namespace App\Warehouse\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Warehouse\Domain\Warehouse;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "almacen")]
class WarehouseEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: "almacen_id", type: "string")]
    public ?string $almacen_id;
    #[Column(name: "local_id", type: "string")]
    public ?string $local_id;
    #[Column(name: "descripcion", type: "string")]
    public ?string $descripcion;
    #[Column(name: "tipo_id", type: "integer")]
    public ?int $tipo_id;
    #[Column(name: "is_default", type: "boolean")]
    public ?bool $is_default;
    #[Column(name: "estado", type: "boolean")]
    public ?bool $estado;

    public function mapToDomainModel(): Warehouse
    {
        return new Warehouse(
            $this->almacen_id,
            $this->local_id,
            $this->descripcion,
            $this->tipo_id,
            $this->is_default,
            $this->estado,

        );
    }

    public function mapFromDomainModel(Warehouse $warehouse): WarehouseEntity
    {
        $this->almacen_id = $warehouse->almacen_id;
        $this->local_id = $warehouse->local_id;
        $this->descripcion = $warehouse->descripcion;
        $this->tipo_id = $warehouse->tipo_id;
        $this->is_default = $warehouse->is_default;
        $this->estado = $warehouse->estado;

        return $this;
    }
}