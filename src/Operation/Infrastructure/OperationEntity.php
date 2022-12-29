<?php

namespace App\Operation\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Operation\Domain\Operation;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'operacion')]
class OperationEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'operacion_id', type: 'string')]
    public ?string $operacion_id;
    #[Column(name: 'descripcion', type: 'string')]
    public ?string $descripcion;
    #[Column(name: 'tipo_id', type: 'integer')]
    public ?int $tipo_id;
    #[Column(name: 'estado', type: 'boolean')]
    public ?bool $estado;

    public function mapToDomainModel(): Operation
    {
        return new Operation(
            $this->operacion_id,
            $this->descripcion,
            $this->tipo_id,
            $this->estado,
        );
    }

    public function mapFromDomainModel(Operation $operation): OperationEntity
    {
        $this->operacion_id = $operation->operacion_id;
        $this->descripcion = $operation->descripcion;
        $this->tipo_id = $operation->tipo_id;
        $this->estado = $operation->estado;

        return $this;
    }
}
