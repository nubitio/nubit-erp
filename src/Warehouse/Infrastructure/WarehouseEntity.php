<?php

namespace App\Warehouse\Infrastructure;

use App\Branch\Infrastructure\BranchEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
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
#[Table(name: 'almacen')]
class WarehouseEntity
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'CUSTOM')]
        #[CustomIdGenerator(class: UuidGenerator::class)]
        #[Column(name: 'almacen_id', type: 'string')]
        public ?string $almacen_id = null,
        #[ManyToOne(targetEntity: BranchEntity::class)]
        #[JoinColumn(name: 'local_id', referencedColumnName: 'local_id')]
        public ?BranchEntity $local = null,
        #[Column(name: 'descripcion', type: 'string')]
        public ?string $descripcion = null,
        #[Column(name: 'tipo_id', type: 'integer')]
        public ?int $tipo_id = null,
        #[Column(name: 'is_default', type: 'boolean')]
        public ?bool $is_default = null,
        #[Column(name: 'estado', type: 'boolean')]
        public ?bool $estado = null,
    ) {
    }
}
