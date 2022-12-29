<?php

namespace App\Unit\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Unit\Domain\Unit;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'unidad')]
class UnitEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'unidad_id', type: 'string')]
    public ?string $unidad_id;
    #[Column(name: 'descripcion', type: 'string')]
    public ?string $descripcion;
    #[Column(name: 'estado', type: 'boolean')]
    public ?bool $estado;

    public function mapToDomainModel(): Unit
    {
        return new Unit(
            $this->unidad_id,
            $this->descripcion,
            $this->estado,
        );
    }

    public function mapFromDomainModel(Unit $unit): UnitEntity
    {
        $this->unidad_id = $unit->unidad_id;
        $this->descripcion = $unit->descripcion;
        $this->estado = $unit->estado;

        return $this;
    }
}
