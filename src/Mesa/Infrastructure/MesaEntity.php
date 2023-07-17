<?php

namespace App\Mesa\Infrastructure;

use App\Ambiente\Infrastructure\AmbienteEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'mesa')]
class MesaEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'mesa_id', type: 'integer')]
        public ?int $mesa_id = null,
        #[ManyToOne(targetEntity: AmbienteEntity::class)]
        #[JoinColumn(name: 'ambiente_id', referencedColumnName: 'ambiente_id')]
        public ?AmbienteEntity $ambiente = null,
        #[ORM\Column(name: 'descripcion', type: 'string')]
        public ?string $descripcion = null,
        #[ORM\Column(name: 'position', type: 'integer')]
        public ?int $position = null,
        #[ORM\Column(name: 'capacity', type: 'integer')]
        public ?int $capacity = null,
        #[ORM\Column(name: 'estado', type: 'boolean')]
        public ?bool $estado = null
    ) {
    }
}
