<?php

namespace App\Ambiente\Infrastructure;

use App\Branch\Infrastructure\BranchEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'ambiente')]
class AmbienteEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'ambiente_id', type: 'integer')]
        public ?int $ambiente_id = null,
        #[ManyToOne(targetEntity: BranchEntity::class)]
        #[JoinColumn(name: 'local_id', referencedColumnName: 'local_id')]
        public ?BranchEntity $local = null,
        #[ORM\Column(name: 'descripcion', type: 'string')]
        public ?string $descripcion = null,
        #[ORM\Column(name: 'estado', type: 'boolean')]
        public ?bool $estado = null
    ) {
    }
}
