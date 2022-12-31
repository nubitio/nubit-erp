<?php

namespace App\Local\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Local\Domain\Local;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'local')]
class LocalEntity
{
    #[Id]
    #[Column(name: 'local_id', type: 'string')]
    public ?string $local_id;
    #[Column(name: 'empresa_id', type: 'string')]
    public ?string $empresa_id;
    #[Column(name: 'descripcion', type: 'string')]
    public ?string $descripcion;
    #[Column(name: 'direccion', type: 'string')]
    public ?string $direccion;
    #[Column(name: 'telefono', type: 'string')]
    public ?string $telefono;
    #[Column(name: 'estado', type: 'boolean')]
    public ?bool $estado;

    public function mapToDomainModel(): Local
    {
        return new Local(
            $this->local_id,
            $this->empresa_id,
            $this->descripcion,
            $this->direccion,
            $this->telefono,
            $this->estado,
        );
    }

    public function mapFromDomainModel(Local $local): LocalEntity
    {
        $this->local_id = $local->local_id;
        $this->empresa_id = $local->empresa_id;
        $this->descripcion = $local->descripcion;
        $this->direccion = $local->direccion;
        $this->telefono = $local->telefono;
        $this->estado = $local->estado;

        return $this;
    }
}
