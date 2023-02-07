<?php

namespace App\VoucherType\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "tipo_comprobante")]
class VoucherTypeEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int    $id = null,
        #[ORM\Column(name: 'codigo', type: 'string')]
        public ?string $codigo = null,
        #[ORM\Column(name: 'descripcion', type: 'string')]
        public ?string $descripcion = null,
        #[ORM\Column(name: 'estado', type: 'boolean')]
        public ?bool   $estado = null
    )
    {
    }
}