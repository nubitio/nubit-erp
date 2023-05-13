<?php

namespace App\Supplier\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'proveedor')]
class SupplierEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'CUSTOM')]
        #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
        #[ORM\Column(name: 'proveedor_id', type: 'string')]
        public ?string $proveedor_id = null,
        #[ORM\Column(name: 'ruc', type: 'string')]
        public ?string $ruc = null,
        #[ORM\Column(name: 'razon_social', type: 'string')]
        public ?string $razon_social = null,
        #[ORM\Column(name: 'domicilio_fiscal', type: 'string')]
        public ?string $domicilio_fiscal = null,
        #[ORM\Column(name: 'telefono', type: 'string')]
        public ?string $telefono = null,
        #[ORM\Column(name: 'estado', type: 'boolean')]
        public ?bool $estado = null
    ) {
    }
}
