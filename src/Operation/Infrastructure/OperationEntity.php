<?php

namespace App\Operation\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "operacion")]
class OperationEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'operacion_id', type: 'string')]
        public ?string $operacion_id = null,
        #[ORM\Column(name: 'descripcion', type: 'string')]
        public ?string $descripcion = null,
        #[ORM\Column(name: 'tipo_id', type: 'integer')]
        public ?int $tipo_id = null,
        #[ORM\Column(name: 'estado', type: 'boolean')]
        public ?bool $estado = null
    )
    {
    }
}