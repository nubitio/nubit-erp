<?php

namespace App\Branch\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Entity\Company;
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
#[Table(name: 'local')]
class BranchEntity
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'CUSTOM')]
        #[CustomIdGenerator(class: UuidGenerator::class)]
        #[Column(name: 'local_id', type: 'string')]
        public ?string $local_id = null,
        #[ManyToOne(targetEntity: Company::class)]
        #[JoinColumn(name: 'empresa_id', referencedColumnName: 'empresa_id')]
        public ?Company $empresa = null,
        #[Column(name: 'descripcion', type: 'string')]
        public ?string $descripcion = null,
        #[Column(name: 'direccion', type: 'string')]
        public ?string $direccion = null,
        #[Column(name: 'telefono', type: 'string')]
        public ?string $telefono = null,
        #[Column(name: 'estado', type: 'boolean')]
        public ?bool $estado = null,
    ) {
    }
}
